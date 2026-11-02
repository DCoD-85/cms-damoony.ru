"""
Production Multi-Agent: Architect -> Builder -> Markdown Parser -> Designer.
"""
import json
import sys
import re
import time
from typing import Any

from .models import OllamaClient
from .aicli_client import AicliClient

# --- ПРОМПТЫ ---
ARCHITECT_STRUCTURE_PROMPT = """You are a web architect.
Given a brief, design the structure of a website.
Output ONLY a JSON object:
{
  "pages": [
    {
      "name": "<MUST BE ONLY ENGLISH LETTERS, lowercase or __root__>",
      "title": "<page title>",
      "layout": ["top", "center", "bottom"]
    }
  ]
}
Rules:
- Include __root__ (home page).
- CRITICAL: "name" must be ONLY English letters, numbers, and underscores.
- Output ONLY the JSON object."""

WRITER_MARKDOWN_PROMPT = """You are a professional copywriter.
Write a detailed, engaging article for the page "{page_title}".
Context: {brief}
Available URLs for links: {valid_urls}

RULES:
1. Output ONLY Markdown text. No JSON. No HTML tags.
2. Use # for the main heading (exactly one).
3. Use ## and ### for subheadings.
4. Use - for bullet lists.
5. Where an image is needed, use the exact format: [IMG: 1-3 word topic]
6. Use [LINK: /url | Link Text] for links.

Example output:
# Welcome to Our Site
This is a great paragraph.

[IMG: happy dog]

## Why Choose Us
- Fast service
- Great quality

[LINK: /contact | Contact Us]
"""

LEAD_DESIGNER_PROMPT = """You are the Lead Web Designer.
Website brief: {brief}
Here are the custom CSS classes used across the site: {classes}

Write a beautiful, modern CSS based on these classes.
Output ONLY valid CSS code. No HTML, no JSON, no markdown fences, no explanations. Just the CSS.
Include styles for body, basic tags (h1, h2, p, ul, a), and ALL the custom classes listed above."""

# --- ТРАНЗАКЦИИ ---
class Transaction:
    def __init__(self, client: AicliClient):
        self.client = client
        self.undo = []

    def record_domain(self, domain: str):
        self.undo.append(("domain:remove", {"domain": domain, "force": True}))

    def record_allias(self, domain: str, allias: str):
        self.undo.append(("allias:remove", {"domain": domain, "name": allias, "force": True}))

    def rollback(self):
        print("\n[!] CRITICAL ERROR. Rolling back changes...")
        for cmd, args in reversed(self.undo):
            print(f"  -> Undo: {cmd}")
            self.client.call_cli(cmd, args)

# --- ХЕЛПЕРЫ ---
def safe_chat(llm: OllamaClient, model: str, messages: list, retries: int = 3) -> dict:
    for i in range(retries):
        try:
            return llm.chat(model, messages)
        except Exception as e:
            if i == retries - 1:
                raise e
            print(f"  [!] LLM error: {e}. Retrying in 3s... (Attempt {i+1}/{retries})")
            time.sleep(3)

def fetch_image(client: AicliClient, domain: str, allias: str, category: str, theme: str) -> str:
    short_theme = " ".join(theme.split()[:3])
    search = client.call_aicli("image:search", {"query": short_theme, "count": 3})
    if not search.ok or not search.data.get("results"):
        return "https://via.placeholder.com/800x600?text=Image+Not+Found"
    url = search.data["results"][0].get("url")
    download = client.call_aicli("image:download", {
        "url": url, "domain": domain, "allias": allias, "category": category, "alt": theme
    })
    if download.ok:
        return download.data.get("local_url", "")
    return "https://via.placeholder.com/800x600?text=Download+Failed"

def parse_markdown_to_blocks(md_text: str, client: AicliClient, domain: str, allias: str, category: str, used_classes: set) -> list:
    """Превращает Markdown от LLM в JSON-блоки для CLI."""
    blocks = []
    lines = md_text.split('\n')
    
    current_list = []
    current_text = []

    def flush_text():
        if current_text:
            text = " ".join(current_text).strip()
            if text:
                blocks.append({"type": "text", "content": text, "class": "paragraph"})
                used_classes.add("paragraph")
            current_text.clear()

    def flush_list():
        if current_list:
            blocks.append({"type": "ul", "items": list(current_list), "class": "features-list"})
            used_classes.add("features-list")
            current_list.clear()

    for line in lines:
        line = line.strip()
        if not line:
            flush_text()
            flush_list()
            continue

        # Заголовки
        if line.startswith('# '):
            flush_text(); flush_list()
            blocks.append({"type": "h1", "content": line[2:].strip(), "class": "hero-title"})
            used_classes.add("hero-title")
        elif line.startswith('## '):
            flush_text(); flush_list()
            blocks.append({"type": "h2", "content": line[3:].strip(), "class": "section-title"})
            used_classes.add("section-title")
        elif line.startswith('### '):
            flush_text(); flush_list()
            blocks.append({"type": "h3", "content": line[4:].strip(), "class": "sub-title"})
            used_classes.add("sub-title")
            
        # Списки
        elif line.startswith('- ') or line.startswith('* '):
            flush_text()
            current_list.append(line[2:].strip())

        # Картинки
        elif line.startswith('[IMG:') and line.endswith(']'):
            flush_text(); flush_list()
            topic = line[5:-1].strip()
            print(f"     [img] searching for '{topic}'...")
            local_url = fetch_image(client, domain, allias, category, topic)
            blocks.append({"type": "img", "content": local_url, "alt": topic, "class": "img-fluid"})
            used_classes.add("img-fluid")

        # Ссылки/Кнопки
        elif line.startswith('[LINK:') and line.endswith(']'):
            flush_text(); flush_list()
            inner = line[6:-1].strip()
            if '|' in inner:
                url, label = inner.split('|', 1)
                blocks.append({"type": "button", "url": url.strip(), "content": label.strip(), "class": "primary-btn"})
                used_classes.add("primary-btn")
            else:
                blocks.append({"type": "link", "url": inner, "content": inner, "class": "text-link"})
                used_classes.add("text-link")

        # Обычный текст
        else:
            flush_list()
            current_text.append(line)

    flush_text()
    flush_list()
    
    # Убедимся, что H1 есть в начале
    if not blocks or blocks[0]["type"] != "h1":
        blocks.insert(0, {"type": "h1", "content": "Welcome", "class": "hero-title"})

    return blocks


def run_multi_agent(brief: str, domain: str, allias: str, model: str = "gemma4:e4b"):
    llm = OllamaClient(timeout=180)
    client = AicliClient()
    tx = Transaction(client)
    
    try:
        # 1. ARCHITECT (СТРУКТУРА)
        print("[1/4] Architect is planning the high-level structure...")
        res = safe_chat(llm, model, [{"role": "user", "content": f"BRIEF: {brief}\n\n{ARCHITECT_STRUCTURE_PROMPT}"}])
        plan_text = res.get("message", {}).get("content", "").strip().removeprefix("```json").removesuffix("```").strip()
        
        plan = json.loads(plan_text)

        for p in plan.get("pages", []):
            if p["name"] != "__root__":
                clean_name = re.sub(r'[^a-z0-9_]', '', p["name"].lower())
                p["name"] = clean_name if clean_name else "page"

        valid_urls = ["/"] + [f"/{p['name']}" for p in plan.get("pages", []) if p["name"] != "__root__"]
        urls_str = ", ".join(valid_urls)

        # 2. BUILDER (КАРКАС)
        print("[2/4] Creating skeleton...")
        client.call_cli("domain:new", {"domain": domain})
        tx.record_domain(domain) 
        
        client.call_cli("allias:new", {"domain": domain, "name": allias})
        tx.record_allias(domain, allias) 
        
        client.call_aicli("skin:new", {"domain": domain, "allias": allias, "name": "default"})
        
        for page in plan.get("pages", []):
            if page["name"] != "__root__":
                client.call_cli("page:new", {"domain": domain, "allias": allias, "name": page["name"]})

        for b in ["top", "left", "right", "bottom", "policy"]:
            client.call_aicli("block:new", {"domain": domain, "name": b, "type": "visual"})
        
        client.call_aicli("filler:new", {"domain": domain, "allias": allias, "block": "top"})
        menu_items = [{"title": "Главная", "url": "/"}] + [{"title": p["title"], "url": f"/{p['name']}"} for p in plan.get("pages", []) if p["name"] != "__root__"]
        
        client.call_aicli("filler:write", {
            "domain": domain, "allias": allias, "block": "top", 
            "blocks": [{"type": "nav", "items": menu_items, "class": "main-navigation"}]
        })

        scheme_json = {
            "children": [{"type": "slot", "name": s} for s in ["top", "left", "center", "right", "bottom", "policy"]],
            "meta": {
                "css": [
                    "/media/shared/css/main.css",
                    f"/media/{domain}/{allias}/skin/default/css/main.css"
                ], 
                "js": ["/media/shared/scripts/jquery.min.js"]
            }
        }
        client.call_aicli("scheme:save", {"domain": domain, "name": allias, "scheme_json": scheme_json})

        # 3. CONTENT WORKERS (WRITER + MARKDOWN PARSER)
        print("[3/4] Generating content (Markdown mode)...")
        used_classes = set(["main-navigation"])
        
        for page in plan.get("pages", []):
            page_name = page["name"]
            page_title = page["title"]
            
            print(f"  -> Writing: {page_title}")
            
            # ОДИН запрос на генерацию Markdown
            writer_prompt = WRITER_MARKDOWN_PROMPT.format(
                page_title=page_title, brief=brief, valid_urls=urls_str
            )
            r = safe_chat(llm, model, [{"role": "user", "content": writer_prompt}])
            md_text = r.get("message", {}).get("content", "").strip()

            # Парсинг в JSON
            final_blocks = parse_markdown_to_blocks(md_text, client, domain, allias, page_name or "__root__", used_classes)

            # Запись в CLI
            cli_name = "" if page_name == "__root__" else page_name
            client.call_aicli("page:write", {
                "domain": domain, "allias": allias, "name": cli_name, "blocks": final_blocks, "with_schema": "1"
            })

        # 4. LEAD DESIGNER
        print("[4/4] Designer is creating the main.css...")
        classes_str = ", ".join(used_classes)
        ld_prompt = LEAD_DESIGNER_PROMPT.format(brief=brief, classes=classes_str)
        
        css_written = False
        for attempt in range(2):
            r = safe_chat(llm, model, [{"role": "user", "content": ld_prompt}])
            css_code = r.get("message", {}).get("content", "").strip().removeprefix("```css").removesuffix("```").strip()

            if not css_code:
                continue

            res = client.call_aicli("skin:edit", {
                "domain": domain, "allias": allias, "name": "default", 
                "file": "css/main.css", "content": css_code
            })
            
            if res.ok:
                print("  [OK] main.css written.")
                css_written = True
                break
            else:
                print(f"  [!] CLI rejected CSS: {res.error}. Retrying...")
                ld_prompt += "\n\nYour previous answer was rejected. Output ONLY valid CSS with balanced { and } braces."

        if not css_written:
            print("  [ERROR] Could not generate valid CSS. Skipping.")

        print("Done!")
        
    except Exception as e:
        print(f"\n[!] Fatal error: {e}")
        tx.rollback()
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Usage: python -m ai_agent.brain <domain> <allias> <brief> [model_name]")
        sys.exit(1)
        
    domain, allias, brief = sys.argv[1], sys.argv[2], sys.argv[3]
    model = sys.argv[4] if len(sys.argv) > 4 else "gemma4:e4b"
    
    run_multi_agent(brief, domain, allias, model=model)
"""
Production Multi-Agent: Architect -> Builder -> Markdown Parser -> Modular Designer.
"""
import json
import sys
import re
import time
import base64
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
      "parent": "<parent page name or null>",
      "layout": ["top", "left", "center", "right", "bottom", "policy"]
    }
  ]
}
Rules:
- Include __root__ (home page).
- CRITICAL: "name" must be ONLY English letters, numbers, and underscores.
- CRITICAL: "parent" must be the name of the parent page (e.g., "services" for "services/install"), or null for top-level pages.
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
7. You can use **bold** text for emphasis.
8. CRITICAL: Write the article in the SAME LANGUAGE as the brief (Context). If the brief is in Russian, write in Russian.

Example output:
# Welcome to Our Site
This is a **great** paragraph.

[IMG: happy dog]

## Why Choose Us
- Fast service
- Great quality

[LINK: /contact | Contact Us]
"""

DESIGNER_BASE_PROMPT = """You are an expert UI/UX Web Designer.
Write ONLY the base CSS variables for the site based on this brief: {brief}
Output ONLY valid CSS. No HTML, no markdown.
Include :root {{ --primary-color: ...; --secondary-color: ...; --text-color: ...; --bg-color: ...; }}
And style the body {{ font-family, background, margin: 0; }}
Ensure all images are responsive (use max-width: 100%; height: auto;)
"""

DESIGNER_BLOCK_PROMPT = """You are an expert UI/UX Web Designer.
Website brief: {brief}
You are styling the block "{block_name}".
Here are the CSS classes used in this block: {classes}

Write COMPLETE CSS for these specific classes.
CRITICAL RULES:
1. Output ONLY valid CSS. No HTML, no JSON, no markdown fences.
2. Use nice colors, padding, and layout.
3. Ensure all {{ and }} braces are perfectly balanced.
4. Ensure images do not overflow their container (use max-width: 100%; height: auto;)
"""

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
    blocks = []
    lines = md_text.split('\n')
    current_list = []
    current_text = []

    def parse_inline(text: str) -> str:
        # Убираем пробелы внутри ссылок
        def replace_link(m):
            url = m.group(1).strip()
            label = m.group(2).strip()
            return f'<a href="{url}">{label}</a>'
        
        text = re.sub(r'\[LINK:\s*([^|\]]+)\s*\|\s*([^\]]+)\]', replace_link, text)
        text = re.sub(r'\*\*(.*?)\*\*', r'<strong>\1</strong>', text)
        return text.strip()

    def flush_text():
        if current_text:
            text = " ".join(current_text).strip()
            if text:
                blocks.append({"type": "text", "content": parse_inline(text), "class": "paragraph"})
                used_classes.add("paragraph")
            current_text.clear()

    def flush_list():
        if current_list:
            cleaned_items = [parse_inline(item) for item in current_list]
            blocks.append({"type": "ul", "items": cleaned_items, "class": "features-list"})
            used_classes.add("features-list")
            current_list.clear()

    for line in lines:
        line = line.strip()
        if not line:
            flush_text(); flush_list(); continue

        if line.startswith('#'):
            level = len(line) - len(line.lstrip('#'))
            content = line.lstrip('#').strip()
            if content:
                flush_text(); flush_list()
                if level == 1:
                    blocks.append({"type": "h1", "content": parse_inline(content), "class": "hero-title"})
                    used_classes.add("hero-title")
                elif level == 2:
                    blocks.append({"type": "h2", "content": parse_inline(content), "class": "section-title"})
                    used_classes.add("section-title")
                elif level >= 3:
                    blocks.append({"type": "h3", "content": parse_inline(content), "class": "sub-title"})
                    used_classes.add("sub-title")
        elif line.startswith('- ') or line.startswith('* '):
            flush_text(); current_list.append(line[2:].strip())
        elif line.startswith('[IMG:') and line.endswith(']'):
            flush_text(); flush_list()
            topic = line[5:-1].strip()
            print(f"     [img] searching for '{topic}'...")
            local_url = fetch_image(client, domain, allias, category, topic)
            blocks.append({"type": "img", "content": local_url, "alt": topic, "class": "img-fluid"})
            used_classes.add("img-fluid")
        else:
            flush_list(); current_text.append(line)

    flush_text(); flush_list()
    if not blocks or blocks[0]["type"] != "h1":
        blocks.insert(0, {"type": "h1", "content": "Welcome", "class": "hero-title"})
    return blocks


def run_multi_agent(brief: str, domain: str, allias: str, model: str = "qwen2.5-coder:7b"):
    llm = OllamaClient(timeout=180)
    client = AicliClient()
    tx = Transaction(client)
    
    total_start = time.time()
    
    try:
        # 1. ARCHITECT
        step_start = time.time()
        print("[1/5] Architect is planning the high-level structure...")
        res = safe_chat(llm, model, [{"role": "user", "content": f"BRIEF: {brief}\n\n{ARCHITECT_STRUCTURE_PROMPT}"}])
        plan_text = res.get("message", {}).get("content", "").strip().removeprefix("```json").removesuffix("```").strip()
        plan = json.loads(plan_text)

        for p in plan.get("pages", []):
            if p["name"] != "__root__":
                clean_name = re.sub(r'[^a-z0-9_]', '', p["name"].lower())
                p["name"] = clean_name if clean_name else "page"

        valid_urls = ["/"] + [f"/{p['name']}" for p in plan.get("pages", []) if p["name"] != "__root__"]
        urls_str = ", ".join(valid_urls)
        print(f"  [Time] Step 1: {time.time() - step_start:.2f}s")

        # 2. BUILDER
        step_start = time.time()
        print("[2/5] Creating skeleton...")
        client.call_cli("domain:new", {"domain": domain})
        tx.record_domain(domain) 
        client.call_cli("allias:new", {"domain": domain, "name": allias})
        tx.record_allias(domain, allias) 
        client.call_aicli("skin:new", {"domain": domain, "allias": allias, "name": "default"})
        
        for page in plan.get("pages", []):
            if page["name"] != "__root__":
                args = {"domain": domain, "allias": allias, "name": page["name"]}
                if page.get("parent"):
                    args["parent"] = page["parent"]
                client.call_cli("page:new", args)

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
                "css": ["/media/shared/css/main.css", f"/media/{domain}/{allias}/skin/default/css/main.css"], 
                "js": ["/media/shared/scripts/jquery.min.js"]
            }
        }
        client.call_aicli("scheme:save", {"domain": domain, "name": allias, "scheme_json": scheme_json})

        # Создаем пустые файлы для блоков
        for b in ["top", "left", "right", "bottom", "policy", "center"]:
            empty_css = f"/* {b}.css - Generated by AI */\n"
            b64 = base64.b64encode(empty_css.encode('utf-8')).decode('utf-8')
            client.call_aicli("skin:edit", {
                "domain": domain, "allias": allias, "name": "default", 
                "file": f"css/{b}.css", "content_b64": b64
            })
        print(f"  [Time] Step 2: {time.time() - step_start:.2f}s")

        # 3. CONTENT WORKERS
        step_start = time.time()
        print("[3/5] Generating content (Markdown mode)...")
        used_classes = set(["main-navigation"])
        
        for page in plan.get("pages", []):
            page_start = time.time() # <--- ТАЙМЕР ДЛЯ КАЖДОЙ СТРАНИЦЫ
            page_name = page["name"]
            page_title = page["title"]
            print(f"  -> Writing: {page_title}")
            
            writer_prompt = WRITER_MARKDOWN_PROMPT.format(page_title=page_title, brief=brief, valid_urls=urls_str)
            r = safe_chat(llm, model, [{"role": "user", "content": writer_prompt}])
            md_text = r.get("message", {}).get("content", "").strip()

            final_blocks = parse_markdown_to_blocks(md_text, client, domain, allias, page_name or "__root__", used_classes)

            cli_name = "" if page_name == "__root__" else page_name
            client.call_aicli("page:write", {
                "domain": domain, "allias": allias, "name": cli_name, "blocks": final_blocks, "with_schema": "1"
            })
            print(f"     [Time] Page '{page_title}': {time.time() - page_start:.2f}s")
            
        print(f"  [Time] Step 3: {time.time() - step_start:.2f}s")

        # 4. DESIGNER (Базовые переменные)
        step_start = time.time()
        print("[4/5] Designer is creating base CSS variables...")
        base_prompt = DESIGNER_BASE_PROMPT.format(brief=brief)
        r = safe_chat(llm, model, [{"role": "user", "content": base_prompt}])
        base_css = r.get("message", {}).get("content", "").strip().removeprefix("```css").removesuffix("```").strip()
        
        layout_css = """
/* Global safety net for images */
img, .img-fluid { max-width: 100% !important; max-height: 500px !important; object-fit: cover; display: block; border-radius: 8px; margin: 20px 0; }
.layout { display: flex; flex-direction: column; min-height: 100vh; max-width: 1200px; margin: 0 auto; background: #fff; box-shadow: 0 0 20px rgba(0,0,0,0.05); }
.page-content { padding: 40px; flex-grow: 1; }
.top { background: var(--bg-color, #f8f9fa); padding: 0; }
.left, .right, .bottom, .policy { padding: 20px; }
"""
        full_main_css = base_css + "\n\n" + layout_css
        b64_main = base64.b64encode(full_main_css.encode('utf-8')).decode('utf-8')
        client.call_aicli("skin:edit", {
            "domain": domain, "allias": allias, "name": "default", 
            "file": "css/main.css", "content_b64": b64_main
        })
        print(f"  [Time] Step 4: {time.time() - step_start:.2f}s")

        # 5. DESIGNER (Стили для блоков)
        step_start = time.time()
        print("[5/5] Designer is creating CSS for blocks...")
        
        # top.css (Меню)
        print("  -> Styling top.css (navigation)")
        top_prompt = DESIGNER_BLOCK_PROMPT.format(brief=brief, block_name="top (header navigation)", classes="main-navigation")
        r = safe_chat(llm, model, [{"role": "user", "content": top_prompt}])
        top_css = r.get("message", {}).get("content", "").strip().removeprefix("```css").removesuffix("```").strip()
        if top_css:
            b64_top = base64.b64encode(top_css.encode('utf-8')).decode('utf-8')
            client.call_aicli("skin:edit", {
                "domain": domain, "allias": allias, "name": "default", 
                "file": "css/top.css", "content_b64": b64_top
            })

        # center.css (Контент)
        center_classes = [c for c in used_classes if c not in ["main-navigation"]]
        if center_classes:
            print("  -> Styling center.css (content)")
            center_prompt = DESIGNER_BLOCK_PROMPT.format(brief=brief, block_name="center (page content)", classes=", ".join(center_classes))
            r = safe_chat(llm, model, [{"role": "user", "content": center_prompt}])
            center_css = r.get("message", {}).get("content", "").strip().removeprefix("```css").removesuffix("```").strip()
            if center_css:
                b64_center = base64.b64encode(center_css.encode('utf-8')).decode('utf-8')
                client.call_aicli("skin:edit", {
                    "domain": domain, "allias": allias, "name": "default", 
                    "file": "css/center.css", "content_b64": b64_center
                })
        print(f"  [Time] Step 5: {time.time() - step_start:.2f}s")

        print(f"\nDone! Total time: {time.time() - total_start:.2f}s")
        
    except Exception as e:
        print(f"\n[!] Fatal error: {e}")
        tx.rollback()
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Usage: python -m ai_agent.brain <domain> <allias> <brief> [model_name]")
        sys.exit(1)
        
    domain, allias, brief = sys.argv[1], sys.argv[2], sys.argv[3]
    model = sys.argv[4] if len(sys.argv) > 4 else "qwen2.5-coder:7b"
    
    run_multi_agent(brief, domain, allias, model=model)
"""
Production Multi-Agent: Architect -> Builder -> Content -> Designer.
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
- CRITICAL: "layout" must be a list of slots. Available slots: top, left, center, right, bottom.
- Output ONLY the JSON object."""

ARCHITECT_DETAIL_PROMPT = """You are a content planner.
Design the content blocks for the page "{page_title}".
Context: {brief}
Output ONLY a JSON object:
{{
  "blocks": [
    {{"type": "h1", "topic": "Main page headline"}},
    {{"type": "text", "topic": "Introduction"}},
    {{"type": "img", "topic": "it agency office"}},
    {{"type": "button", "topic": "Call to action"}}
  ]
}}
Rules:
- CRITICAL: For "img" blocks, the "topic" MUST be 1-3 English words.
- Allowed block types: h1, h2, h3, text, img, ul, button, link.
- Every page MUST start with h1.
- Output ONLY the JSON object."""

WRITER_PROMPT = """You are a professional copywriter.
Write the content for a '{block_type}' block on the page "{page_title}".
Topic: "{topic}"
Context: {brief}
Available URLs: {valid_urls}

Output format: You MUST output a valid JSON object with two keys: "content" and "class".
Example: {{"content": "Your amazing text here", "class": "hero-heading"}}

Rules:
1. NEVER output empty text.
2. The "class" must be a custom CSS class name (e.g., "hero-title", "intro-text", "action-btn", "menu-list").
3. For 'ul' blocks: {{"items": ["item 1", "item 2"], "class": "feature-list"}}
4. For 'button' or 'link' blocks: {{"url": "/page", "content": "Button Text", "class": "primary-btn"}}
5. Output ONLY the JSON object. No markdown, no explanations."""

LEAD_DESIGNER_PROMPT = """You are the Lead Web Designer.
Website brief: {brief}
Here are the custom CSS classes used across the site: {classes}

Create a Style Guide and write the complete CSS.
Output ONLY a JSON object:
{{
  "style_guide": "Use a modern dark blue theme. Font: Inter, sans-serif. Primary color: #007bff.",
  "css_code": "body {{ margin: 0; font-family: 'Inter', sans-serif; }}\\n.hero-heading {{ font-size: 3rem; color: #007bff; }}"
}}
Rules:
- Include styles for body, basic tags (h1, h2, p, ul, a), and ALL the custom classes listed above.
- Output ONLY the JSON object. No markdown. Ensure the CSS code is fully complete and valid."""

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
def clean_html(text: str) -> str:
    return re.sub(r'<[^>]+>', '', text).strip()

def safe_chat(llm: OllamaClient, model: str, messages: list, retries: int = 3) -> dict:
    """Обертка над llm.chat с повторными попытками."""
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


def run_multi_agent(brief: str, domain: str, allias: str, model: str = "gemma4:e4b"):
    llm = OllamaClient(timeout=180)
    client = AicliClient()
    tx = Transaction(client)
    
    try:
        # 1. ARCHITECT 1 (СТРУКТУРА)
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

        # 2. BUILDER (КАРКАС)
        print("[2/5] Creating skeleton...")
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
            "meta": {"css": ["/media/shared/css/main.css"], "js": ["/media/shared/scripts/jquery.min.js"]}
        }
        client.call_aicli("scheme:save", {"domain": domain, "name": allias, "scheme_json": scheme_json})

        # 3. CONTENT WORKERS
        print("[3/5] Generating content (block by block)...")
        used_classes = set(["main-navigation"])
        page_blocks_map = {}

        for page in plan.get("pages", []):
            page_name = page["name"]
            page_title = page["title"]
            
            detail_prompt = ARCHITECT_DETAIL_PROMPT.format(page_title=page_title, brief=brief)
            r_det = safe_chat(llm, model, [{"role": "user", "content": detail_prompt}])
            det_text = r_det.get("message", {}).get("content", "").strip().removeprefix("```json").removesuffix("```").strip()
            
            try:
                blocks_plan = json.loads(det_text).get("blocks", [])
            except:
                blocks_plan = [{"type": "h1", "topic": page_title}, {"type": "text", "topic": "Welcome"}]

            final_blocks = []
            print(f"  -> Writing: {page_title} ({len(blocks_plan)} blocks)")
            
            for block in blocks_plan:
                block_type = block.get("type", "text")
                topic = block.get("topic", page_title)

                if block_type == "img":
                    local_url = fetch_image(client, domain, allias, page_name or "__root__", topic)
                    final_blocks.append({"type": "img", "content": local_url, "alt": topic, "class": "img-fluid"})
                    used_classes.add("img-fluid")
                    continue

                prompt = WRITER_PROMPT.format(
                    block_type=block_type, page_title=page_title, topic=topic, brief=brief, valid_urls=urls_str
                )
                r = safe_chat(llm, model, [{"role": "user", "content": prompt}])
                content = r.get("message", {}).get("content", "").strip().removeprefix("```json").removesuffix("```").strip()

                try:
                    data = json.loads(content)
                except Exception:
                    data = {"content": content, "class": f"{block_type}-default"}

                css_class = data.get("class", f"{block_type}-default")
                used_classes.add(css_class)
                
                if block_type == "ul":
                    items = data.get("items", [])
                    if not items: items = ["Item 1", "Item 2", "Item 3"]
                    final_blocks.append({"type": "ul", "items": items, "class": css_class})
                elif block_type in ("button", "link"):
                    final_blocks.append({"type": block_type, "url": data.get("url", "/"), "content": data.get("content", "Click Here"), "class": css_class})
                else:
                    text_content = clean_html(data.get("content", ""))
                    if not text_content: text_content = f"Welcome to {topic}"
                    final_blocks.append({"type": block_type, "content": text_content, "class": css_class})

            cli_name = "" if page_name == "__root__" else page_name
            page_blocks_map[cli_name] = final_blocks

        for p_name, blocks in page_blocks_map.items():
            client.call_aicli("page:write", {
                "domain": domain, "allias": allias, "name": p_name, "blocks": blocks, "with_schema": "1"
            })

        # 4. LEAD DESIGNER
        print("[4/5] Designer is creating the main.css...")
        classes_str = ", ".join(used_classes)
        ld_prompt = LEAD_DESIGNER_PROMPT.format(brief=brief, classes=classes_str)
        r = safe_chat(llm, model, [{"role": "user", "content": ld_prompt}])
        ld_text = r.get("message", {}).get("content", "").strip().removeprefix("```json").removesuffix("```").strip()

        try:
            design_data = json.loads(ld_text)
            css_code = design_data.get("css_code", "")
            if len(css_code) < 20 or not css_code.endswith("}"):
                raise ValueError("Incomplete CSS")
        except Exception:
            print("  [!] Designer failed to output valid CSS. Using fallback.")
            css_code = f"body {{ font-family: sans-serif; line-height: 1.6; }}\n" + "\n".join([f".{c.strip()} {{ margin-bottom: 15px; }}" for c in used_classes if c.strip()])

        res = client.call_aicli("skin:edit", {
            "domain": domain, "allias": allias, "name": "default", 
            "file": "css/main.css", "content": css_code
        })
        
        if res.ok:
            print("  [OK] main.css written.")
        else:
            print(f"  [ERROR] Failed to write main.css: {res.error}")

        print("[5/5] Done!")
        
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
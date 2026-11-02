"""
Production Multi-Agent: Architect -> Markdown Parser -> Designer.
Работает через строгий State Machine API (wizard:*) в bin/aicli.php.
"""
from __future__ import annotations

import json
import sys
import time
import base64
import re
from typing import Any

from .models import OllamaClient, Result
from .aicli_client import AicliClient

# --- ПРОМПТЫ ---
ARCHITECT_STRUCTURE_PROMPT = """You are a web architect.
Given a brief, design the structure of a website.
Output ONLY a JSON object:
{
  "pages": [
    {
      "name": "<MUST BE ONLY ENGLISH LETTERS, lowercase or __root__>",
      "title": "<human-readable page title IN RUSSIAN>",
      "parent": "<parent page name or null>"
    }
  ]
}
Rules:
- Include __root__ (home page).
- CRITICAL: "name" must be ONLY English letters, numbers, and underscores.
- CRITICAL: "title" MUST be written in RUSSIAN.

CRITICAL EXAMPLE (YOU MUST FOLLOW THIS FORMAT EXACTLY):
{
  "pages": [
    {"name": "__root__", "title": "Главная", "parent": null},
    {"name": "about_us", "title": "О нас", "parent": null},
    {"name": "care_guide", "title": "Уход за собаками", "parent": null},
    {"name": "documentation", "title": "Документация", "parent": "care_guide"}
  ]
}

Output ONLY the JSON object."""

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
8. CRITICAL: Write the article in the SAME LANGUAGE as the brief (Context)."""

DESIGNER_BASE_PROMPT = """You are an expert UI/UX Web Designer.
Write ONLY the base CSS variables for the site based on this brief: {brief}
Output ONLY valid CSS. No HTML, no markdown.
Include :root {{ --primary-color: ...; --secondary-color: ...; --text-color: ...; --bg-color: ...; }}
And style the body {{ font-family, background, margin: 0; }}"""

DESIGNER_BLOCK_PROMPT = """You are an expert UI/UX Web Designer.
Website brief: {brief}
You are styling the block "{block_name}".
Here are the CSS classes used in this block: {classes}

Write COMPLETE CSS for these specific classes.
CRITICAL RULES:
1. Output ONLY valid CSS. No HTML, no JSON, no markdown fences.
2. Ensure all {{ and }} braces are perfectly balanced."""

# --- ХЕЛПЕРЫ ---
def _safe_href(url: str) -> str | None:
    url = url.strip()
    if not url: return None
    low = url.lower()
    if any(low.startswith(s) for s in ('javascript:', 'data:', 'vbscript:', 'file:')): return None
    if url.startswith('//'): return None
    if not any(url.startswith(p) for p in ('/', '#', 'http://', 'https://')): return None
    return url.replace('"', '%22').replace("'", '%27').replace('<', '%3C').replace('>', '%3E')

def safe_chat(llm: OllamaClient, model: str, messages: list, retries: int = 3) -> dict:
    for i in range(retries):
        try:
            return llm.chat(model, messages)
        except Exception as e:
            if i == retries - 1: raise e
            print(f"  [!] LLM error: {e}. Retrying in 3s... (Attempt {i + 1}/{retries})")
            time.sleep(3)
    raise RuntimeError("safe_chat: unreachable")

def fetch_image(client: AicliClient, session_id: str, domain: str, allias: str, category: str, theme: str) -> str:
    short_theme = " ".join(theme.split()[:3])
    search = client.call_aicli("image:search", {"query": short_theme, "count": 3})
    if not search.ok or not search.data or not search.data.get("results"):
        return ""
    url = search.data["results"][0].get("url")
    if not url: return ""
    
    download = client.call_aicli("image:download", {
        "url": url, "domain": domain, "allias": allias, "category": category, "alt": theme,
    })
    if not download.ok: return ""
    return (download.data or {}).get("local_url", "")

def parse_markdown_to_blocks(md_text: str, client: AicliClient, session_id: str, domain: str, allias: str, category: str, used_classes: set[str]) -> list:
    blocks: list[dict] = []
    lines = md_text.split('\n')
    current_list: list[str] = []
    current_text: list[str] = []

    def parse_inline(text: str) -> str:
        def replace_link(m: re.Match) -> str:
            url = m.group(1).strip()
            label = m.group(2).strip()
            safe = _safe_href(url)
            if safe is None: return label
            return f'<a href="{safe}">{label}</a>'
        def replace_bold(m: re.Match) -> str:
            inner = m.group(1)
            inner = re.sub(r'<a\b[^>]*>', '', inner).replace('</a>', '')
            inner = re.sub(r'<strong\b[^>]*>', '', inner).replace('</strong>', '')
            return f'<strong>{inner}</strong>'
        text = re.sub(r'\[LINK:\s*([^|\]]+?)\s*\|\s*([^\]]+?)\s*\]', replace_link, text)
        text = re.sub(r'\*\*(.+?)\*\*', replace_bold, text)
        return text.strip()

    def flush_text() -> None:
        if current_text:
            text = " ".join(current_text).strip()
            if text:
                blocks.append({"type": "text", "content": parse_inline(text), "class": "paragraph"})
                used_classes.add("paragraph")
            current_text.clear()

    def flush_list() -> None:
        if current_list:
            cleaned_items = [parse_inline(item) for item in current_list]
            blocks.append({"type": "ul", "items": cleaned_items, "class": "features-list"})
            used_classes.add("features-list")
            current_list.clear()

    for raw_line in lines:
        line = raw_line.strip()
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
                else:
                    blocks.append({"type": "h3", "content": parse_inline(content), "class": "sub-title"})
                    used_classes.add("sub-title")
        elif line.startswith('- ') or line.startswith('* '):
            flush_text(); current_list.append(line[2:].strip())
        elif line.startswith('[IMG:') and line.endswith(']'):
            flush_text(); flush_list()
            topic = line[5:-1].strip()
            print(f"     [img] searching for '{topic}'...")
            local_url = fetch_image(client, session_id, domain, allias, category, topic)
            if local_url:
                blocks.append({"type": "img", "content": local_url, "alt": topic, "class": "img-fluid"})
                used_classes.add("img-fluid")
        else:
            flush_list(); current_text.append(line)

    flush_text(); flush_list()
    if not blocks or blocks[0].get("type") != "h1":
        blocks.insert(0, {"type": "h1", "content": "Добро пожаловать", "class": "hero-title"})
        used_classes.add("hero-title")
    return blocks

def run_multi_agent(brief: str, domain: str, allias: str, model: str = "qwen2.5-coder:7b") -> None:
    llm = OllamaClient(timeout=180)
    client = AicliClient()
    total_start = time.time()

    def log_time(step_name: str, start_time: float):
        print(f"  [Time] {step_name}: {time.time() - start_time:.2f}s")

    # === 1. WIZARD: START ===
    step_start = time.time()
    r = client.call_aicli("wizard:start", {})
    if not r.ok:
        print(f"[!] Fatal: wizard:start failed: {r.error}")
        sys.exit(1)
    session_id = r.data['session_id']
    print(f"[1/5] Wizard started (Session: {session_id[:8]}...).")
    log_time("Start", step_start)

    try:
        # === 2. ARCHITECT (LLM) ===
        step_start = time.time()
        print("[2/5] Architect is planning the structure...")
        res = safe_chat(llm, model, [{
            "role": "user", "content": f"BRIEF: {brief}\n\n{ARCHITECT_STRUCTURE_PROMPT}"
        }])
        plan_text = (res.get("message", {}).get("content", "").strip().removeprefix("```json").removesuffix("```").strip())
        plan = json.loads(plan_text)
        
        # Отправляем домен и алиас (строгая проверка на каждом шаге)
        r_dom = client.call_aicli("wizard:domain", {"session_id": session_id, "domain": domain})
        if not r_dom.ok:
            raise RuntimeError(f"Domain rejected: {r_dom.error}")
            
        r_alias = client.call_aicli("wizard:allias", {"session_id": session_id, "allias": allias})
        if not r_alias.ok:
            raise RuntimeError(f"Allias rejected: {r_alias.error}")
        
        # Отправляем план
        r_plan = client.call_aicli("wizard:plan", {"session_id": session_id, "pages": plan.get("pages", [])})
        if not r_plan.ok:
            print(f"\n[!] LLM generated invalid plan:\n{json.dumps(plan, ensure_ascii=False, indent=2)}")
            raise RuntimeError(f"Plan rejected by engine: {r_plan.error}")

        # Карта путей для ссылок
        path_map = {}
        for _ in range(3):
            for p in plan.get("pages", []):
                if p["name"] == "__root__" or p["name"] in path_map: continue
                if not p.get("parent"): path_map[p["name"]] = f"/{p['name']}"
                elif p["parent"] in path_map: path_map[p["name"]] = f"{path_map[p['parent']]}/{p['name']}"
        urls_str = ", ".join(["/"] + list(path_map.values()))
        log_time("Architect", step_start)

        # === 3. CONTENT WORKERS ===
        step_start = time.time()
        print("[3/5] Generating content (Markdown mode)...")
        used_classes = {"main-navigation"}
        
        for page in plan.get("pages", []):
            page_start = time.time()
            page_name = page["name"]
            page_title = page["title"]
            print(f"  -> Writing: {page_title}")
            
            writer_prompt = WRITER_MARKDOWN_PROMPT.format(page_title=page_title, brief=brief, valid_urls=urls_str)
            r_llm = safe_chat(llm, model, [{"role": "user", "content": writer_prompt}])
            md_text = r_llm.get("message", {}).get("content", "").strip()

            final_blocks = parse_markdown_to_blocks(md_text, client, session_id, domain, allias, page_name or "__root__", used_classes)
            
            r_content = client.call_aicli("wizard:content", {
                "session_id": session_id, "page": page_name, "blocks": final_blocks
            })
            if not r_content.ok:
                raise RuntimeError(f"Content rejected: {r_content.error}")
            print(f"     [Time] Page '{page_title}': {time.time() - page_start:.2f}s")
        log_time("Content Workers", step_start)

        # === 4. DESIGNER: main.css ===
        step_start = time.time()
        print("[4/5] Designer is creating main.css...")
        r_llm = safe_chat(llm, model, [{"role": "user", "content": DESIGNER_BASE_PROMPT.format(brief=brief)}])
        base_css = (r_llm.get("message", {}).get("content", "").strip().removeprefix("```css").removesuffix("```").strip())

        center_classes = [c for c in used_classes if c not in ("main-navigation",)]
        center_css = ""
        if center_classes:
            center_prompt = DESIGNER_BLOCK_PROMPT.format(brief=brief, block_name="center", classes=", ".join(sorted(center_classes)))
            r_llm = safe_chat(llm, model, [{"role": "user", "content": center_prompt}])
            center_css = (r_llm.get("message", {}).get("content", "").strip().removeprefix("```css").removesuffix("```").strip())

        layout_css = """
img, .img-fluid { max-width: 100% !important; max-height: 500px !important; object-fit: cover; display: block; border-radius: 8px; margin: 20px 0; }
.layout { display: flex; flex-direction: column; min-height: 100vh; max-width: 1200px; margin: 0 auto; background: #fff; box-shadow: 0 0 20px rgba(0,0,0,0.05); }
.page-content { padding: 40px; flex-grow: 1; }
.top { background: var(--bg-color, #f8f9fa); padding: 0; }
.left, .right, .bottom, .policy { padding: 20px; }
"""
        full_main_css = base_css + "\n\n" + layout_css
        if center_css: full_main_css += "\n\n/* Center */\n" + center_css
        
        client.call_aicli("wizard:skin", {"session_id": session_id, "file": "css/main.css", "content": full_main_css})
        log_time("Designer (main.css)", step_start)

        # === 5. DESIGNER: top.css ===
        step_start = time.time()
        print("[5/5] Designer is styling top.css...")
        top_prompt = DESIGNER_BLOCK_PROMPT.format(brief=brief, block_name="top (navigation)", classes="main-navigation")
        r_llm = safe_chat(llm, model, [{"role": "user", "content": top_prompt}])
        top_css = (r_llm.get("message", {}).get("content", "").strip().removeprefix("```css").removesuffix("```").strip())
        if top_css:
            client.call_aicli("wizard:skin", {"session_id": session_id, "file": "css/top.css", "content": top_css})
        log_time("Designer (top.css)", step_start)

        # === COMMIT ===
        step_start = time.time()
        print("\n[=>] All drafts collected. Committing to filesystem...")
        r_fin = client.call_aicli("wizard:finish", {"session_id": session_id})
        if not r_fin.ok: raise RuntimeError(f"Finish failed: {r_fin.error}")
        
        r_commit = client.call_aicli("wizard:commit", {"session_id": session_id})
        if not r_commit.ok:
            raise RuntimeError(f"Commit failed: {r_commit.error}")
        log_time("Commit", step_start)

        print(f"\nDone! Total time: {time.time() - total_start:.2f}s")

    except Exception as e:
        print(f"\n[!] Fatal error: {e}")
        print("Aborting session (no files were committed).")
        sys.exit(1)

if __name__ == "__main__":
    # Force UTF-8 to fix Windows console mojibake (□□□□□□□)
    if hasattr(sys.stdout, 'reconfigure'):
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    if hasattr(sys.stderr, 'reconfigure'):
        sys.stderr.reconfigure(encoding='utf-8', errors='replace')

    if len(sys.argv) < 4:
        print("Usage: python -m ai_agent.brain <domain> <allias> <brief> [model_name]")
        sys.exit(1)
    domain_arg, allias_arg, brief_arg = sys.argv[1], sys.argv[2], sys.argv[3]
    model_arg = sys.argv[4] if len(sys.argv) > 4 else "qwen2.5-coder:7b"
    run_multi_agent(brief_arg, domain_arg, allias_arg, model=model_arg)
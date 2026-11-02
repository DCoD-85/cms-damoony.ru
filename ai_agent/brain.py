"""
Production Multi-Agent: Architect -> Validator -> Builder -> Markdown Parser -> Modular Designer.
"""
from __future__ import annotations

import json
import sys
import re
import time
import base64
from pathlib import Path
from typing import Any

from .models import OllamaClient, Result
from .aicli_client import AicliClient, Transaction

# --- ПРОМПТЫ ---
ARCHITECT_STRUCTURE_PROMPT = """You are a web architect.
Given a brief, design the structure of a website.
Output ONLY a JSON object:
{
  "pages": [
    {
      "name": "<MUST BE ONLY ENGLISH LETTERS, lowercase or __root__>",
      "title": "<human-readable page title IN RUSSIAN>",
      "parent": "<parent page name or null>",
      "layout": ["top", "center", "bottom"]
    }
  ]
}
Rules:
- Include __root__ (home page).
- CRITICAL: "name" must be ONLY English letters, numbers, and underscores.
- CRITICAL: "title" MUST be written in RUSSIAN (e.g., "О нас", "Уход за собаками").

CRITICAL NESTING RULES (READ CAREFULLY):
If you create a sub-page (e.g., Documentation inside Care Guide), you MUST separate the parent and the child:
Correct:  {"name": "documentation", "title": "Документация", "parent": "care_guide"}
INCORRECT: {"name": "care_guide_documentation", "title": "Документация", "parent": "care_guide"}

Here is an example of a valid structure:
{
  "pages": [
    {"name": "__root__", "title": "Главная", "parent": null, "layout": ["top", "center"]},
    {"name": "about_us", "title": "О нас", "parent": null, "layout": ["top", "center"]},
    {"name": "care_guide", "title": "Уход за собаками", "parent": null, "layout": ["top", "center"]},
    {"name": "documentation", "title": "Документация", "parent": "care_guide", "layout": ["top", "center"]}
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

# --- КОНСТАНТЫ ---
NAME_RE = re.compile(r'^[a-z][a-z0-9_]*$')
SAFE_HREF_PREFIX = ('/', '#', 'http://', 'https://')
DANGEROUS_SCHEMES = ('javascript:', 'data:', 'vbscript:', 'file:')


# --- ХЕЛПЕРЫ ---
def _must(result: Result, context: str) -> Result:
    """Raise if result not ok. Returns result for chaining."""
    if not result.ok:
        msg = result.error or result.hint or 'unknown error'
        raise RuntimeError(f"{context}: {msg}")
    return result


def _extract_list(data: Any, keys: tuple[str, ...]) -> list[str]:
    """Extract list of strings from CLI result.data, tolerant to shape."""
    if data is None:
        return []
    if isinstance(data, list):
        return [str(x) for x in data if isinstance(x, (str, int))]
    if isinstance(data, dict):
        for key in keys:
            v = data.get(key)
            if isinstance(v, list):
                return [str(x) for x in v if isinstance(x, (str, int))]
            if isinstance(v, dict):
                return [str(k) for k in v.keys()]
    return []


def _safe_href(url: str) -> str | None:
    """Validate and encode href. Return None if URL is unsafe."""
    url = url.strip()
    if not url:
        return None
    low = url.lower()
    for scheme in DANGEROUS_SCHEMES:
        if low.startswith(scheme):
            return None
    if url.startswith('//'):
        return None
    if not any(url.startswith(p) for p in SAFE_HREF_PREFIX):
        return None
    # Encode chars that could break attribute context
    return (
        url.replace('"', '%22')
        .replace("'", '%27')
        .replace('<', '%3C')
        .replace('>', '%3E')
    )


def _sanitize_name(name: str) -> str:
    name = name.strip().lower()
    if name == '__root__':
        return name
    clean = re.sub(r'[^a-z0-9_]', '', name)
    if not clean or not clean[0].isalpha():
        clean = 'page_' + clean if clean else 'page'
    return clean


def _validate_and_sort_plan(plan: dict) -> dict:
    """Deterministic fix of LLM plan + topological sort by depth."""
    pages = plan.get("pages", [])
    if not isinstance(pages, list):
        pages = []

    # 1. Sanitize names (except __root__)
    for page in pages:
        page['name'] = _sanitize_name(str(page.get('name', '')))

    # 2. Split glued names: care_guide_documentation -> documentation
    names = {p['name'] for p in pages}
    for page in pages:
        parent = page.get('parent')
        name = page['name']
        if parent and isinstance(parent, str) and name.startswith(parent + '_'):
            candidate = name[len(parent) + 1:]
            if candidate and candidate not in names:
                page['name'] = candidate

    # 3. Sanitize parent
    for page in pages:
        parent = page.get('parent')
        if parent is None or parent == '':
            page['parent'] = None
        elif isinstance(parent, str):
            p = parent.strip().lower()
            if p == '__root__' or not NAME_RE.match(p):
                page['parent'] = None
            else:
                page['parent'] = p

    # 4. Drop orphan parents (parent not in plan)
    names = {p['name'] for p in pages}
    for page in pages:
        if page['parent'] and page['parent'] not in names:
            page['parent'] = None

    # 5. Topological sort by depth (with cycle guard)
    parent_map = {p['name']: p.get('parent') for p in pages}
    depth_cache: dict[str, int] = {}
    visiting: set[str] = set()

    def depth(name: str) -> int:
        if name in depth_cache:
            return depth_cache[name]
        if name in visiting:
            return 0  # cycle — break
        visiting.add(name)
        p = parent_map.get(name)
        d = 0 if not p or p not in parent_map else 1 + depth(p)
        visiting.discard(name)
        depth_cache[name] = d
        return d

    plan['pages'] = sorted(pages, key=lambda pg: depth(pg['name']))
    return plan


def validate_plan(plan: dict) -> dict:
    """Public alias, used by tests."""
    return _validate_and_sort_plan(plan)


def preflight(client: AicliClient, domain: str, allias: str) -> None:
    """Refuse to run on existing domain/allias; verify agent.config."""
    # 1. Existing domains
    r = client.call_aicli("domain:list", {})
    _must(r, "preflight domain:list")
    existing_domains = _extract_list(r.data, ("domains", "items", "list"))
    if domain in existing_domains:
        raise RuntimeError(
            f"Domain '{domain}' already exists. Refusing to overwrite. "
            f"Use a new domain name or remove it manually via CLI."
        )

    # 2. Existing aliases
    r = client.call_aicli("allias:list", {"domain": domain})
    if r.ok:
        existing_alliases = _extract_list(r.data, ("alliases", "aliases", "items", "list"))
        if allias in existing_alliases:
            raise RuntimeError(
                f"Allias '{allias}' already exists in '{domain}'. Refusing to overwrite."
            )

    # 3. agent.config allowed_domains
    cfg_path = Path(__file__).resolve().parent.parent / "config" / "agent.config"
    if not cfg_path.is_file():
        raise RuntimeError("config/agent.config is missing")
    try:
        cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
    except Exception as e:
        raise RuntimeError(f"Cannot parse config/agent.config: {e}")
    allowed = cfg.get("allowed_domains", [])
    if domain not in allowed:
        raise RuntimeError(
            f"Domain '{domain}' not in config/agent.config allowed_domains. "
            f"Add it first."
        )


def safe_chat(llm: OllamaClient, model: str, messages: list, retries: int = 3) -> dict:
    for i in range(retries):
        try:
            return llm.chat(model, messages)
        except Exception as e:
            if i == retries - 1:
                raise e
            print(f"  [!] LLM error: {e}. Retrying in 3s... (Attempt {i + 1}/{retries})")
            time.sleep(3)
    raise RuntimeError("safe_chat: unreachable")


def fetch_image(client: AicliClient, domain: str, allias: str, category: str, theme: str) -> str:
    """Return local_url or '' on failure (skip <img> — no external placeholder)."""
    short_theme = " ".join(theme.split()[:3])
    search = client.call_aicli("image:search", {"query": short_theme, "count": 3})
    if not search.ok or not search.data or not search.data.get("results"):
        return ""
    url = search.data["results"][0].get("url")
    if not url:
        return ""
    download = client.call_aicli("image:download", {
        "url": url, "domain": domain, "allias": allias, "category": category, "alt": theme,
    })
    if not download.ok:
        return ""
    # NB: content:store may deduplicate — do NOT record content:remove in undo.
    return (download.data or {}).get("local_url", "")


def parse_markdown_to_blocks(
    md_text: str,
    client: AicliClient,
    domain: str,
    allias: str,
    category: str,
    used_classes: set[str],
) -> list:
    blocks: list[dict] = []
    lines = md_text.split('\n')
    current_list: list[str] = []
    current_text: list[str] = []

    def parse_inline(text: str) -> str:
        def replace_link(m: re.Match) -> str:
            url = m.group(1).strip()
            label = m.group(2).strip()
            safe = _safe_href(url)
            if safe is None:
                return label  # drop unsafe link, keep text
            return f'<a href="{safe}">{label}</a>'

        def replace_bold(m: re.Match) -> str:
            inner = m.group(1)
            # Defense in depth: strip <a>/<strong> from bold content
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
            flush_text()
            flush_list()
            continue

        if line.startswith('#'):
            level = len(line) - len(line.lstrip('#'))
            content = line.lstrip('#').strip()
            if content:
                flush_text()
                flush_list()
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
            flush_text()
            current_list.append(line[2:].strip())
        elif line.startswith('[IMG:') and line.endswith(']'):
            flush_text()
            flush_list()
            topic = line[5:-1].strip()
            print(f"     [img] searching for '{topic}'...")
            local_url = fetch_image(client, domain, allias, category, topic)
            if local_url:
                blocks.append({"type": "img", "content": local_url, "alt": topic, "class": "img-fluid"})
                used_classes.add("img-fluid")
            else:
                print(f"     [img] skipped (no image for '{topic}')")
        else:
            flush_list()
            current_text.append(line)

    flush_text()
    flush_list()

    if not blocks or blocks[0].get("type") != "h1":
        blocks.insert(0, {"type": "h1", "content": "Добро пожаловать", "class": "hero-title"})
        used_classes.add("hero-title")
    return blocks


def run_multi_agent(brief: str, domain: str, allias: str, model: str = "qwen2.5-coder:7b") -> None:
    llm = OllamaClient(timeout=180)
    client = AicliClient()
    tx = Transaction(client)

    total_start = time.time()
    orphans: list[str] = []

    try:
        # === PREFLIGHT ===
        preflight(client, domain, allias)
        print(f"[0/5] Preflight OK: '{domain}/{allias}' is new, agent.config allows it.")

        # 1. ARCHITECT
        step_start = time.time()
        print("[1/5] Architect is planning the high-level structure...")
        res = safe_chat(llm, model, [{
            "role": "user",
            "content": f"BRIEF: {brief}\n\n{ARCHITECT_STRUCTURE_PROMPT}",
        }])
        plan_text = (res.get("message", {}).get("content", "")
                     .strip().removeprefix("```json").removesuffix("```").strip())
        plan = json.loads(plan_text)
        plan = validate_plan(plan)

        # Build path map for links (respect nesting)
        path_map: dict[str, str] = {}
        for _ in range(3):
            for p in plan.get("pages", []):
                if p["name"] == "__root__" or p["name"] in path_map:
                    continue
                if not p.get("parent"):
                    path_map[p["name"]] = f"/{p['name']}"
                elif p["parent"] in path_map:
                    path_map[p["name"]] = f"{path_map[p['parent']]}/{p['name']}"

        valid_urls = ["/"] + list(path_map.values())
        urls_str = ", ".join(valid_urls)
        print(f"  [Time] Step 1: {time.time() - step_start:.2f}s")

        # 2. BUILDER
        step_start = time.time()
        print("[2/5] Creating skeleton...")
        _must(client.call_cli("domain:new", {"domain": domain}), "domain:new")
        tx.record_domain_new(domain)

        _must(client.call_cli("allias:new", {"domain": domain, "name": allias}), "allias:new")
        tx.record_allias_new(domain, allias)

        _must(
            client.call_aicli("skin:new", {"domain": domain, "allias": allias, "name": "default"}),
            "skin:new",
        )

        for page in plan.get("pages", []):
            if page["name"] == "__root__":
                continue
            args: dict[str, Any] = {"domain": domain, "allias": allias, "name": page["name"]}
            if page.get("parent"):
                args["parent"] = page["parent"]
            _must(client.call_cli("page:new", args), f"page:new {page['name']}")

        for b in ["top", "left", "right", "bottom", "policy"]:
            _must(
                client.call_aicli("block:new", {"domain": domain, "name": b, "type": "visual"}),
                f"block:new {b}",
            )

        _must(
            client.call_aicli("filler:new", {"domain": domain, "allias": allias, "block": "top"}),
            "filler:new top",
        )

        menu_items = [{"title": "Главная", "url": "/"}]
        for p in plan.get("pages", []):
            if p["name"] != "__root__":
                url = path_map.get(p["name"], f"/{p['name']}")
                menu_items.append({"title": p["title"], "url": url})

        _must(
            client.call_aicli("filler:write", {
                "domain": domain, "allias": allias, "block": "top",
                "blocks": [{"type": "nav", "items": menu_items, "class": "main-navigation"}],
            }),
            "filler:write top",
        )

        scheme_json = {
            "children": [{"type": "slot", "name": s} for s in
                         ["top", "left", "center", "right", "bottom", "policy"]],
            "meta": {
                "css": [
                    "/media/shared/css/main.css",
                    f"/media/{domain}/{allias}/skin/default/css/main.css",
                ],
                "js": ["/media/shared/scripts/jquery.min.js"],
            },
        }
        _must(
            client.call_aicli("scheme:save", {"domain": domain, "name": allias, "scheme_json": scheme_json}),
            "scheme:save",
        )

        # Empty CSS placeholders for blocks (top/left/.../policy). center.css NOT created:
        # its styles go to main.css (always loaded via scheme_json.meta.css).
        for b in ["top", "left", "right", "bottom", "policy"]:
            empty_css = f"/* {b}.css - Generated by AI */\n"
            b64 = base64.b64encode(empty_css.encode('utf-8')).decode('utf-8')
            _must(
                client.call_aicli("skin:edit", {
                    "domain": domain, "allias": allias, "name": "default",
                    "file": f"css/{b}.css", "content_b64": b64,
                }),
                f"skin:edit css/{b}.css",
            )
        print(f"  [Time] Step 2: {time.time() - step_start:.2f}s")

        # 3. CONTENT WORKERS
        step_start = time.time()
        print("[3/5] Generating content (Markdown mode)...")
        used_classes: set[str] = {"main-navigation"}

        for page in plan.get("pages", []):
            page_start = time.time()
            page_name = page["name"]
            page_title = page["title"]
            print(f"  -> Writing: {page_title}")

            writer_prompt = WRITER_MARKDOWN_PROMPT.format(
                page_title=page_title, brief=brief, valid_urls=urls_str,
            )
            r = safe_chat(llm, model, [{"role": "user", "content": writer_prompt}])
            md_text = r.get("message", {}).get("content", "").strip()

            final_blocks = parse_markdown_to_blocks(
                md_text, client, domain, allias, page_name or "__root__", used_classes,
            )

            cli_name = "" if page_name == "__root__" else page_name
            _must(
                client.call_aicli("page:write", {
                    "domain": domain, "allias": allias, "name": cli_name,
                    "blocks": final_blocks, "with_schema": "1",
                }),
                f"page:write {cli_name or '__root__'}",
            )
            print(f"     [Time] Page '{page_title}': {time.time() - page_start:.2f}s")

        print(f"  [Time] Step 3: {time.time() - step_start:.2f}s")

        # 4. DESIGNER: base + center CSS (both -> main.css)
        step_start = time.time()
        print("[4/5] Designer is creating main.css (base + content)...")

        # 4a. Base variables
        r = safe_chat(llm, model, [{"role": "user", "content": DESIGNER_BASE_PROMPT.format(brief=brief)}])
        base_css = (r.get("message", {}).get("content", "")
                    .strip().removeprefix("```css").removesuffix("```").strip())

        # 4b. Center (content) styles
        center_classes = [c for c in used_classes if c not in ("main-navigation",)]
        center_css = ""
        if center_classes:
            center_prompt = DESIGNER_BLOCK_PROMPT.format(
                brief=brief, block_name="center (page content)",
                classes=", ".join(sorted(center_classes)),
            )
            r = safe_chat(llm, model, [{"role": "user", "content": center_prompt}])
            center_css = (r.get("message", {}).get("content", "")
                          .strip().removeprefix("```css").removesuffix("```").strip())

        layout_css = """
/* Global safety net for images */
img, .img-fluid { max-width: 100% !important; max-height: 500px !important; object-fit: cover; display: block; border-radius: 8px; margin: 20px 0; }
.layout { display: flex; flex-direction: column; min-height: 100vh; max-width: 1200px; margin: 0 auto; background: #fff; box-shadow: 0 0 20px rgba(0,0,0,0.05); }
.page-content { padding: 40px; flex-grow: 1; }
.top { background: var(--bg-color, #f8f9fa); padding: 0; }
.left, .right, .bottom, .policy { padding: 20px; }
"""
        full_main_css = base_css + "\n\n" + layout_css
        if center_css:
            full_main_css += "\n\n/* Center (content) */\n" + center_css

        b64_main = base64.b64encode(full_main_css.encode('utf-8')).decode('utf-8')
        _must(
            client.call_aicli("skin:edit", {
                "domain": domain, "allias": allias, "name": "default",
                "file": "css/main.css", "content_b64": b64_main,
            }),
            "skin:edit css/main.css",
        )
        print(f"  [Time] Step 4: {time.time() - step_start:.2f}s")

        # 5. DESIGNER: top.css (navigation)
        step_start = time.time()
        print("[5/5] Designer is styling top.css (navigation)...")
        top_prompt = DESIGNER_BLOCK_PROMPT.format(
            brief=brief, block_name="top (header navigation)", classes="main-navigation",
        )
        r = safe_chat(llm, model, [{"role": "user", "content": top_prompt}])
        top_css = (r.get("message", {}).get("content", "")
                   .strip().removeprefix("```css").removesuffix("```").strip())
        if top_css:
            b64_top = base64.b64encode(top_css.encode('utf-8')).decode('utf-8')
            _must(
                client.call_aicli("skin:edit", {
                    "domain": domain, "allias": allias, "name": "default",
                    "file": "css/top.css", "content_b64": b64_top,
                }),
                "skin:edit css/top.css",
            )
        print(f"  [Time] Step 5: {time.time() - step_start:.2f}s")

        tx.commit()
        print(f"\nDone! Total time: {time.time() - total_start:.2f}s")
        if orphans:
            print("Orphan content (run `php bin/cli.php content:gc` to clean):")
            for o in orphans:
                print(f"  - {o}")

    except Exception as e:
        print(f"\n[!] Fatal error: {e}")
        log = tx.rollback()
        for line in log:
            print(f"  {line}")
        if orphans:
            print("  Orphan content left behind (run `content:gc` to clean):")
            for o in orphans:
                print(f"    - {o}")
        sys.exit(1)


if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Usage: python -m ai_agent.brain <domain> <allias> <brief> [model_name]")
        sys.exit(1)

    domain_arg, allias_arg, brief_arg = sys.argv[1], sys.argv[2], sys.argv[3]
    model_arg = sys.argv[4] if len(sys.argv) > 4 else "qwen2.5-coder:7b"

    run_multi_agent(brief_arg, domain_arg, allias_arg, model=model_arg)
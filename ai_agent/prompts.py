"""
Промпты для Planner и Content (Builder детерминирован — LLM не нужен).

Planner:  brief → plan JSON (domain, allias, pages, theme)
Content:  page + local_url → blocks JSON (h1, text, img, ...)

Content НЕ использует tool-calling. Orchestrator детерминированно:
  1. image:search(theme) → выбрать url
  2. image:download(url) → local_url
  3. LLM(theme, local_url) → blocks
  4. page:write(blocks)
"""

from __future__ import annotations


# === PLANNER ===

PLANNER_SYSTEM = """You are a planner. Given a brief, produce a strict JSON plan for a website.

Output ONLY a JSON object, no explanations, no markdown fences.

Schema:
{
  "domain": "<FQDN from allowed_domains, or empty if not specified>",
  "allias": "<lowercase letters, digits, underscores; no hyphens; 1-32 chars>",
  "theme": "<short theme description for image search, e.g. 'coffee shop'>",
  "pages": [
    {"name": "<lowercase, digits, underscores; no hyphens>", "title": "<human-readable>", "theme": "<image search theme for this page>"}
  ],
  "images": true
}

Rules:
- 1 to 10 pages. Root page is implicit (do not include it).
- names must match ^[a-z][a-z0-9_]*$ (start with letter).
- theme: 1-3 words, in English, for image search.
- domain must be from the allowed list in the brief; if none specified, use the first.
- If brief says "no images" — set images=false.
- Output ONLY the JSON object. No markdown. No explanations.
"""


def planner_user(brief: str, allowed_domains: list[str]) -> str:
    domains = ", ".join(allowed_domains) if allowed_domains else "NONE"
    return f"""BRIEF:
{brief}

ALLOWED DOMAINS (must use one of these):
{domains}

Produce the plan JSON now."""


# === CONTENT ===

CONTENT_SYSTEM = """You are a content writer for one page of a website.

Output ONLY a JSON array of blocks, no explanations, no markdown fences.

Supported block types:
- {"type": "h1", "content": "..."}                — main heading (exactly 1 per page)
- {"type": "h2", "content": "..."}                — subheading
- {"type": "text", "content": "..."}              — paragraph (2-4 sentences)
- {"type": "img", "content": "/media/...", "alt": "..."}  — image (use provided local_url)
- {"type": "ul", "items": ["...", "..."]}         — bullet list
- {"type": "link", "url": "/...", "content": "..."} — link to another page

Optional fields on any block: "class", "style".

Rules:
- Start with exactly ONE h1.
- 3 to 6 blocks total.
- If an image local_url is provided — include exactly ONE img block with content=local_url and alt="<descriptive>".
- Do NOT invent external URLs. Only use the provided local_url (if any).
- Write in English, 50-150 words total.
- Output ONLY the JSON array. No markdown. No explanations.
"""


def content_user(
    page_name: str,
    page_title: str,
    page_theme: str,
    site_theme: str,
    local_url: str | None,
    other_pages: list[str],
) -> str:
    others = ", ".join(other_pages) if other_pages else "none"
    img_part = (
        f'- Image local_url: {local_url}\n  (include exactly one img block with content="{local_url}")'
        if local_url
        else "- No image for this page (do not add img blocks)."
    )
    return f"""PAGE:
- name: {page_name}
- title: {page_title}
- theme: {page_theme}
- site theme: {site_theme}
- other pages (for links): {others}

{img_part}

Write the blocks JSON now."""
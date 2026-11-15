PROFILES = {
    "default": {
        "model": "qwen2.5-coder:7b",
        "timeout": 180,
        "planner_system": """You are a planner. Given a brief, produce a strict JSON plan for a website.

Output ONLY a JSON object, no explanations, no markdown fences.

Schema:
{
  "domain": "<FQDN>",
  "allias": "<lowercase, no hyphens>",
  "theme": "<short theme>",
  "pages": [{"name": "<lowercase>", "title": "<human-readable>", "theme": "<theme>"}],
  "images": true
}

Rules:
- If the brief mentions the home page, include a page with name "__root__".
- names must match ^[a-z_][a-z0-9_]*$.
- Output ONLY the JSON object.""",
        "content_system": """You are a content writer for one page of a website.
Output ONLY a JSON array of blocks.

Types: h1, h2, h3, text, img, link, button, ul, nav.
Rules:
- Start with exactly ONE h1.
- Write 50-150 words total.
- If local_url is provided — include exactly ONE img block.
- Output ONLY the JSON array."""
    },
    "rich": {
        "model": "gemma4:26b",
        "timeout": 600,
        "planner_system": """You are an expert web planner. Produce a detailed JSON plan for a website.

Output ONLY a JSON object, no explanations, no markdown fences.

Schema:
{
  "domain": "<FQDN>",
  "allias": "<lowercase, no hyphens>",
  "theme": "<short theme>",
  "pages": [{"name": "<lowercase>", "title": "<human-readable>", "theme": "<theme>"}],
  "images": true
}

Rules:
- If the brief mentions the home page, include a page with name "__root__".
- names must match ^[a-z_][a-z0-9_]*$.
- Output ONLY the JSON object.""",
        "content_system": """You are a professional copywriter and web developer.
Output ONLY a JSON array of blocks, no explanations, no markdown fences.

Types: h1, h2, h3, text, img, link, button, ul, nav.

Rules:
- Start with exactly ONE h1.
- Write RICH, detailed, and marketing-oriented content (aim for 300-500 words).
- You MUST include 2-3 cross-links to other pages using "link" or "button".
- If the brief mentions products, create 3 detailed product cards (img -> h3 -> text -> button).
- If local_url is provided — include exactly ONE img block.
- Output ONLY the JSON array."""
    }
}
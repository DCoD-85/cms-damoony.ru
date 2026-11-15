"""
Planner: brief → plan (валидированный + нормализованный).

- 1 вызов LLM (без tools, JSON-only).
- Парсинг с поддержкой markdown-фенсов и raw_decode.
- Нормализация (alias → allias, отдельные page.theme fallbacks).
- Валидация (regex, allowed_domains, 1..10 pages).
- Retry 2× при invalid (передаём ошибку в LLM).
"""

from __future__ import annotations

import json
import re
from typing import Any

from .models import OllamaClient
from .prompts import planner_user
from .session import Session


MAX_RETRIES = 2

NAME_RE = re.compile(r"^[a-z_][a-z0-9_]*$")
DOMAIN_RE = re.compile(r"^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$")


class PlannerError(Exception):
    pass


def run_planner(
    brief: str,
    allowed_domains: list[str],
    model: str,
    session: Session,
    system_prompt: str,
    llm: OllamaClient | None = None,
) -> dict[str, Any]:
    """
    Возвращает нормализованный план:
    {
      "domain": str,
      "allias": str,
      "theme": str,
      "pages": [{"name": str, "title": str, "theme": str}],
      "images": bool,
    }
    """
    llm = llm or OllamaClient()

    messages: list[dict[str, Any]] = [
        {"role": "system", "content": system_prompt},
        {"role": "user", "content": planner_user(brief, allowed_domains)},
    ]

    last_error: str = ""

    for attempt in range(MAX_RETRIES + 1):
        session.log(f"Planner attempt {attempt + 1}/{MAX_RETRIES + 1}")
        try:
            resp = llm.chat(model, messages)
        except Exception as e:
            last_error = f"LLM call failed: {e}"
            session.log(f"Planner LLM error: {e}")
            continue

        content = (resp.get("message") or {}).get("content", "") or ""
        session.log(f"Planner raw: {content[:400]}")

        try:
            plan_raw = _parse_json_object(content)
        except PlannerError as e:
            last_error = str(e)
            session.log(f"Planner parse error: {last_error}")
            messages.append({"role": "assistant", "content": content})
            messages.append({
                "role": "user",
                "content": f"Your response was invalid: {last_error}. Output ONLY the JSON object, no markdown.",
            })
            continue

        try:
            plan = _normalize_and_validate(plan_raw, allowed_domains)
        except PlannerError as e:
            last_error = str(e)
            session.log(f"Planner validation error: {last_error}")
            messages.append({"role": "assistant", "content": content})
            messages.append({
                "role": "user",
                "content": f"Validation failed: {last_error}. Fix and output ONLY the JSON object.",
            })
            continue

        session.write_json("plan.json", plan)
        session.log(
            f"Planner OK: domain={plan['domain']}, allias={plan['allias']}, "
            f"pages={len(plan['pages'])}, images={plan['images']}"
        )
        return plan

    raise PlannerError(f"Planner failed after {MAX_RETRIES + 1} attempts: {last_error}")


# === Parsing ===

def _parse_json_object(content: str) -> dict[str, Any]:
    """Срезает markdown-фенсы, ищет первый валидный JSON-объект."""
    content = content.strip()
    if not content:
        raise PlannerError("empty response")

    # Срезаем ```json ... ```
    stripped = re.sub(r"^```(?:json)?\s*", "", content)
    stripped = re.sub(r"\s*```\s*$", "", stripped).strip()

    # Попытка 1: весь текст — JSON
    try:
        data = json.loads(stripped)
        if isinstance(data, dict):
            return data
    except json.JSONDecodeError:
        pass

    # Попытка 2: raw_decode — найти первый валидный объект
    decoder = json.JSONDecoder()
    for i, ch in enumerate(stripped):
        if ch != "{":
            continue
        try:
            obj, _ = decoder.raw_decode(stripped[i:])
            if isinstance(obj, dict):
                return obj
        except json.JSONDecodeError:
            continue

    raise PlannerError("no valid JSON object found in response")


# === Normalization + Validation ===

def _normalize_and_validate(raw: dict[str, Any], allowed_domains: list[str]) -> dict[str, Any]:
    errors: list[str] = []

    # 1. domain (alias → allias нормализация ниже)
    domain = _pick_str(raw, ["domain", "host", "site"])
    if not domain:
        errors.append("missing 'domain'")
    elif not DOMAIN_RE.match(domain):
        errors.append(f"invalid domain '{domain}' (must be FQDN)")
    elif allowed_domains and domain not in allowed_domains:
        errors.append(f"domain '{domain}' not in allowed_domains {allowed_domains}")

    # 2. allias (Qwen возвращает "alias", а не "allias")
    allias = _pick_str(raw, ["allias", "alias", "alias_name", "section"])
    if not allias:
        errors.append("missing 'allias' (or 'alias')")
    elif not NAME_RE.match(allias):
        errors.append(f"invalid allias '{allias}' (must match {NAME_RE.pattern})")

    # 3. theme
    theme = _pick_str(raw, ["theme", "site_theme", "description"])
    if not theme:
        theme = "website"  # fallback

    # 4. images
    images = raw.get("images", True)
    if not isinstance(images, bool):
        images = True

    # 5. pages
    pages_raw = raw.get("pages")
    if not isinstance(pages_raw, list) or not pages_raw:
        errors.append("missing or empty 'pages'")
        pages_raw = []

    if len(pages_raw) > 10:
        errors.append(f"too many pages ({len(pages_raw)}, max 10)")

    pages: list[dict[str, str]] = []
    seen_names: set[str] = set()
    for idx, p in enumerate(pages_raw):
        if not isinstance(p, dict):
            errors.append(f"pages[{idx}] is not an object")
            continue

        name = _pick_str(p, ["name", "path", "slug", "page"])
        if not name:
            errors.append(f"pages[{idx}] missing 'name'")
            continue
            
        # Добавлена нормализация главной страницы
        if name.lower() in ("root", "home", "index", "main"):
            name = "__root__"

        if not NAME_RE.match(name):
            errors.append(f"pages[{idx}] invalid name '{name}'")
            continue
        if name in seen_names:
            errors.append(f"pages[{idx}] duplicate name '{name}'")
            continue
        seen_names.add(name)

        title = _pick_str(p, ["title", "heading", "h1"]) or name.replace("_", " ").title()
        page_theme = _pick_str(p, ["theme", "query", "image_theme"]) or theme

        pages.append({"name": name, "title": title, "theme": page_theme})

    if not pages and not errors:
        errors.append("no valid pages after normalization")

    if errors:
        raise PlannerError("; ".join(errors))

    return {
        "domain": domain,
        "allias": allias,
        "theme": theme,
        "pages": pages,
        "images": images,
    }


def _pick_str(d: dict[str, Any], keys: list[str]) -> str:
    """Возвращает первое непустое строковое значение по списку ключей."""
    for k in keys:
        v = d.get(k)
        if isinstance(v, str) and v.strip():
            return v.strip()
    return ""
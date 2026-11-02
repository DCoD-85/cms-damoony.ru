"""
Content: plan + skeleton → контент на каждой странице.

Для каждой страницы из plan.pages:
1. Если images=true и page.theme — image:search(page.theme, count=5)
   → ранжировать (CC/PD, размер 800..5000 px) → image:download (full → preview fallback)
2. LLM (изолированный контекст: 1 страница) → blocks JSON
3. page:write(blocks)
4. Обновить content.json (status: written | failed)
"""

from __future__ import annotations

import json
import re
from typing import Any

from .aicli_client import AicliClient, Transaction
from .models import OllamaClient
from .prompts import CONTENT_SYSTEM, content_user
from .session import Session


MAX_LLM_RETRIES = 2

MIN_WIDTH = 800
MIN_HEIGHT = 600
MAX_WIDTH = 5000
MAX_HEIGHT = 5000
GOOD_LICENSES = {"cc0", "cc by", "cc by-sa", "public domain", "pd", "by", "by-sa", "cc-by", "cc-by-sa"}


class ContentError(Exception):
    pass


def run_content(
    plan: dict[str, Any],
    skeleton: dict[str, Any],
    session: Session,
    client: AicliClient | None = None,
    llm: OllamaClient | None = None,
    model: str | None = None,
) -> dict[str, Any]:
    client = client or AicliClient()
    llm = llm or OllamaClient()
    model = model or session.config.get("model") or "qwen2.5-coder:7b"

    domain = plan["domain"]
    allias = plan["allias"]
    site_theme = plan["theme"]
    images = plan.get("images", True)
    pages = plan["pages"]

    content: dict[str, dict[str, Any]] = {}
    session.log(f"Content: {len(pages)} page(s), images={images}, model={model}")

    for idx, page in enumerate(pages, 1):
        name = page["name"]
        title = page["title"]
        page_theme = page["theme"]
        other_names = [p["name"] for p in pages if p["name"] != name]

        session.log(f"--- Page {idx}/{len(pages)}: {name} ({title}) ---")

        try:
            result = _write_one_page(
                client=client,
                llm=llm,
                model=model,
                session=session,
                domain=domain,
                allias=allias,
                site_theme=site_theme,
                page_name=name,
                page_title=title,
                page_theme=page_theme,
                other_pages=other_names,
                images=images,
            )
            content[name] = result
        except ContentError as e:
            content[name] = {"status": "failed", "error": str(e)}
            session.log(f"Page {name} FAILED: {e}")

        session.write_json("content.json", content)

    written = sum(1 for v in content.values() if v.get("status") == "written")
    failed = sum(1 for v in content.values() if v.get("status") == "failed")
    session.log(f"Content done: {written} written, {failed} failed")
    return content


def _write_one_page(
    *,
    client: AicliClient,
    llm: OllamaClient,
    model: str,
    session: Session,
    domain: str,
    allias: str,
    site_theme: str,
    page_name: str,
    page_title: str,
    page_theme: str,
    other_pages: list[str],
    images: bool,
) -> dict[str, Any]:
    tx = Transaction(client)
    local_url: str | None = None

    if images:
        try:
            media = _fetch_image(
                client=client,
                session=session,
                domain=domain,
                allias=allias,
                category=page_name,
                theme=page_theme,
            )
            if media:
                local_url = media["local_url"]
                tx.record_media(
                    domain=domain,
                    allias=allias,
                    category=page_name,
                    file_hash=media["hash"],
                )
                session.log(f"  image: {local_url} ({media['width']}x{media['height']})")
        except Exception as e:
            session.log(f"  image: skipped ({e})")

    try:
        blocks = _generate_blocks(
            llm=llm,
            model=model,
            session=session,
            page_name=page_name,
            page_title=page_title,
            page_theme=page_theme,
            site_theme=site_theme,
            local_url=local_url,
            other_pages=other_pages,
        )
    except ContentError as e:
        rb = tx.rollback()
        session.log(f"  blocks: failed ({e}), rollback {len(rb)} op(s)")
        raise

    write = client.call_aicli(
        "page:write",
        {
            "domain": domain,
            "allias": allias,
            "name": page_name,
            "blocks": blocks,
            "with_schema": "1",  # Оборачиваем контент в схему с меню и стилями
        },
    )
    session.log(f"  page:write: ok={write.ok} blocks={len(blocks)}")

    if not write.ok:
        rb = tx.rollback()
        session.log(f"  page:write FAILED, rollback {len(rb)} op(s)")
        raise ContentError(f"page:write failed: {write.error}")

    tx.commit()

    return {
        "status": "written",
        "blocks_count": len(blocks),
        "media": [local_url] if local_url else [],
    }


# === Image pipeline ===

def _fetch_image(
    *,
    client: AicliClient,
    session: Session,
    domain: str,
    allias: str,
    category: str,
    theme: str,
) -> dict[str, Any] | None:
    """image:search → ранжировать → image:download (full → preview fallback)."""
    search = client.call_aicli("image:search", {"query": theme, "count": 5})
    if not search.ok:
        session.log(f"  image:search failed: {search.error[:80]}")
        return None

    results = (search.data or {}).get("results") or []
    if not results:
        session.log(f"  image:search: 0 results for '{theme}'")
        return None

    candidates = _rank_candidates(results)

    for candidate in candidates:
        urls_to_try: list[tuple[str, str]] = []
        if candidate.get("preview_url"):
            urls_to_try.append(("preview", candidate["preview_url"]))
        if candidate.get("url"):
            urls_to_try.append(("full", candidate["url"]))

        for kind, url in urls_to_try:
            download = client.call_aicli(
                "image:download",
                {
                    "url": url,
                    "domain": domain,
                    "allias": allias,
                    "category": category,
                    "alt": candidate.get("title") or f"{theme} image",
                },
            )
            if download.ok:
                data = download.data or {}
                if data.get("local_url"):
                    if kind == "preview":
                        session.log("  image: used preview_url (full too large)")
                    return {
                        "local_url": data["local_url"],
                        "hash": data.get("hash", ""),
                        "width": int(data.get("width", 0)),
                        "height": int(data.get("height", 0)),
                        "mime": data.get("mime", ""),
                        "source_url": url,
                    }
            session.log(f"  image:download ({kind}) failed: {download.error[:80]}")

    session.log(f"  image:search: all candidates failed for '{theme}'")
    return None


def _rank_candidates(results: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Ранжировать: сначала CC/PD средних размеров, потом остальные."""
    def score(r: dict[str, Any]) -> tuple[int, int, int]:
        license_low = str(r.get("license", "")).lower()
        license_ok = 1 if any(lic in license_low for lic in GOOD_LICENSES) else 0
        w = int(r.get("width", 0))
        h = int(r.get("height", 0))
        size_ok = 1 if (MIN_WIDTH <= w <= MAX_WIDTH and MIN_HEIGHT <= h <= MAX_HEIGHT) else 0
        area = min(w * h, 4000 * 4000)
        return (license_ok, size_ok, area)

    return sorted(results, key=score, reverse=True)


# === LLM → blocks ===

def _generate_blocks(
    *,
    llm: OllamaClient,
    model: str,
    session: Session,
    page_name: str,
    page_title: str,
    page_theme: str,
    site_theme: str,
    local_url: str | None,
    other_pages: list[str],
) -> list[dict[str, Any]]:
    messages: list[dict[str, Any]] = [
        {"role": "system", "content": CONTENT_SYSTEM},
        {
            "role": "user",
            "content": content_user(
                page_name=page_name,
                page_title=page_title,
                page_theme=page_theme,
                site_theme=site_theme,
                local_url=local_url,
                other_pages=other_pages,
            ),
        },
    ]

    last_error = ""

    for _ in range(MAX_LLM_RETRIES + 1):
        try:
            resp = llm.chat(model, messages)
        except Exception as e:
            last_error = f"LLM call failed: {e}"
            session.log(f"  blocks LLM error: {e}")
            continue

        content = (resp.get("message") or {}).get("content", "") or ""
        session.log(f"  blocks raw: {content[:300]}")

        try:
            blocks = _parse_blocks(content)
            blocks = _normalize_blocks(blocks, local_url)
            if not blocks:
                raise ContentError("empty blocks after normalization")
            return blocks
        except ContentError as e:
            last_error = str(e)
            session.log(f"  blocks parse error: {last_error}")
            messages.append({"role": "assistant", "content": content})
            messages.append({
                "role": "user",
                "content": f"Invalid blocks: {last_error}. Output ONLY the JSON array. First block MUST be h1. Include exactly one img with the provided local_url (if given).",
            })

    raise ContentError(f"LLM failed after {MAX_LLM_RETRIES + 1} attempts: {last_error}")


def _parse_blocks(content: str) -> list[dict[str, Any]]:
    content = content.strip()
    if not content:
        raise ContentError("empty response")

    stripped = re.sub(r"^```(?:json)?\s*", "", content)
    stripped = re.sub(r"\s*```\s*$", "", stripped).strip()

    try:
        data = json.loads(stripped)
        if isinstance(data, list):
            return [b for b in data if isinstance(b, dict)]
    except json.JSONDecodeError:
        pass

    decoder = json.JSONDecoder()
    for i, ch in enumerate(stripped):
        if ch != "[":
            continue
        try:
            obj, _ = decoder.raw_decode(stripped[i:])
            if isinstance(obj, list):
                return [b for b in obj if isinstance(b, dict)]
        except json.JSONDecodeError:
            continue

    raise ContentError("no valid JSON array found")


def _normalize_blocks(blocks: list[dict[str, Any]], local_url: str | None) -> list[dict[str, Any]]:
    allowed_types = {"h1", "h2", "h3", "text", "img", "link", "button", "ul"}
    out: list[dict[str, Any]] = []
    img_count = 0

    for b in blocks:
        if not isinstance(b, dict):
            continue
        t = b.get("type")
        if t not in allowed_types:
            continue

        if t == "img":
            if not b.get("content"):
                b["content"] = b.get("local_url") or b.get("src") or b.get("url") or local_url or ""
            if not b.get("content"):
                continue
            img_count += 1
            if img_count > 1:
                continue

        out.append(b)

    if not out or out[0].get("type") != "h1":
        h1_idx = next((i for i, b in enumerate(out) if b.get("type") == "h1"), None)
        if h1_idx is not None and h1_idx > 0:
            out.insert(0, out.pop(h1_idx))

    return out
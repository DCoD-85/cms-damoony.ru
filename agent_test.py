"""
Damoony AI Agent — Python-цикл для LLM (Ollama) ↔ AICLI ↔ CLI.

Архитектура: AI → AICLI (единственный фасад) → {CLI, AINET(lib)} → CLI(FS)
Rollback: доверенный Python-код вызывает CLI напрямую (AI не имеет доступа).
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import urllib.request
from dataclasses import dataclass, field
from typing import Any

# Форсируем UTF-8 вывод (Windows cp1251 не может печатать Unicode-символы)
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

# === Конфигурация ===
OLLAMA_URL = "http://localhost:11434/api/chat"
DEFAULT_MODEL = "qwen2.5-coder:7b"
DEFAULT_MAX_STEPS = 10
BATCH_SIZE = 5
OLLAMA_TIMEOUT = 180
CLI_TIMEOUT = 60

PROJECT_ROOT = os.path.dirname(__file__)
AICLI_PATH = f"{PROJECT_ROOT}/bin/aicli.php"
CLI_PATH = f"{PROJECT_ROOT}/bin/cli.php"


@dataclass
class ToolCall:
    name: str
    args: dict[str, Any]


@dataclass
class Result:
    ok: bool
    data: dict[str, Any] | None = None
    error: str = ""
    hint: str = ""
    context: dict[str, Any] = field(default_factory=dict)
    raw: str = ""

    @classmethod
    def from_json(cls, raw: str) -> "Result":
        raw = raw.strip()
        if not raw:
            return cls(ok=False, error="Empty response", raw=raw)
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            return cls(ok=False, error=f"Invalid JSON: {raw[:200]}", raw=raw)
        if not isinstance(data, dict):
            return cls(ok=False, error="Expected object", raw=raw)
        return cls(
            ok=bool(data.get("ok", False)),
            data=data.get("data"),
            error=str(data.get("error", "")),
            hint=str(data.get("hint", "")),
            context=data.get("context", {}) or {},
            raw=raw,
        )


class AicliClient:
    def __init__(self, aicli_path: str = AICLI_PATH, cli_path: str = CLI_PATH):
        self.aicli_path = aicli_path
        self.cli_path = cli_path

    def call_aicli(self, command: str, args: dict[str, Any]) -> Result:
        payload = json.dumps({"command": command, "args": args}, ensure_ascii=False)
        try:
            proc = subprocess.run(
                ["php", self.aicli_path],
                input=payload,
                text=True,
                capture_output=True,
                encoding="utf-8",
                timeout=CLI_TIMEOUT,
            )
        except subprocess.TimeoutExpired:
            return Result(ok=False, error=f"Timeout: {command}")
        except Exception as e:
            return Result(ok=False, error=f"Subprocess failed: {e}")

        if proc.returncode != 0 and not proc.stdout.strip():
            return Result(ok=False, error=f"aicli exit {proc.returncode}: {proc.stderr[:200]}")

        return Result.from_json(proc.stdout)

    def call_cli(self, command: str, args: dict[str, Any]) -> Result:
        cmd = ["php", self.cli_path, command]
        for k, v in args.items():
            if isinstance(v, bool):
                if v:
                    cmd.append(f"--{k}")
            else:
                cmd.append(f"--{k}={v}")
        try:
            proc = subprocess.run(cmd, text=True, capture_output=True, encoding="utf-8", timeout=CLI_TIMEOUT)
        except Exception as e:
            return Result(ok=False, error=f"CLI subprocess failed: {e}")

        output = proc.stdout.strip()
        ok = "OK" in output and "FAILED" not in output and proc.returncode == 0
        return Result(ok=ok, data={"output": output}, raw=output)


class Transaction:
    """Undo-стек. commit() очищает undo (Step успешен), rollback() — откатывает."""

    def __init__(self, client: AicliClient):
        self.client = client
        self.undo: list[tuple[str, dict[str, Any]]] = []

    def record_domain_new(self, domain: str) -> None:
        self.undo.append(("domain:remove", {"domain": domain, "force": True}))

    def record_allias_new(self, domain: str, allias: str) -> None:
        self.undo.append(("allias:remove", {"domain": domain, "name": allias, "force": True}))

    def record_media(self, domain: str, allias: str, category: str, file_hash: str) -> None:
        self.undo.append((
            "media:remove",
            {"domain": domain, "allias": allias, "category": category, "hash": file_hash, "force": True},
        ))

    def commit(self) -> None:
        """Зафиксировать текущий шаг: очистить undo (транзакция остаётся активной)."""
        self.undo = []

    def rollback(self) -> list[str]:
        log = []
        for cmd, args in reversed(self.undo):
            log.append(f"ROLLBACK: {cmd} {args}")
            self.client.call_cli(cmd, args)
        self.undo = []
        return log


class Batcher:
    def __init__(self, items: list[ToolCall], size: int = BATCH_SIZE):
        self.batches = [items[i : i + size] for i in range(0, len(items), size)]

    def __len__(self) -> int:
        return len(self.batches)

    def __iter__(self):
        return iter(self.batches)


def parse_tool_calls(message: dict[str, Any]) -> list[ToolCall]:
    calls: list[ToolCall] = []

    # 1. Нативный tool_calls
    raw_calls = message.get("tool_calls") or []
    for tc in raw_calls:
        try:
            fn = tc.get("function", {})
            name = fn.get("name", "")
            args_raw = fn.get("arguments", {})
            if isinstance(args_raw, str):
                args = json.loads(args_raw) if args_raw else {}
            else:
                args = args_raw
            if name:
                calls.append(ToolCall(name=name, args=args or {}))
        except (json.JSONDecodeError, AttributeError, TypeError):
            continue
    if calls:
        return _dedupe(calls)

    # 2. Content как JSON (с поддержкой markdown-фенсов ```json ... ```)
    content = (message.get("content") or "").strip()
    if not content:
        return []

    # Срезаем markdown-фенсы
    stripped = re.sub(r"^```(?:json)?\s*", "", content)
    stripped = re.sub(r"\s*```\s*$", "", stripped)
    stripped = stripped.strip()

    candidates: list[Any] = []
    try:
        candidates.append(json.loads(stripped))
    except json.JSONDecodeError:
        pass

    try:
        decoder = json.JSONDecoder()
        for i, ch in enumerate(stripped):
            if ch in "[{":
                try:
                    obj, _ = decoder.raw_decode(stripped[i:])
                    candidates.append(obj)
                    break
                except json.JSONDecodeError:
                    continue
    except Exception:
        pass

    for cand in candidates:
        if not isinstance(cand, list):
            continue
        for item in cand:
            if not isinstance(item, dict):
                continue
            name = item.get("name") or (item.get("function") or {}).get("name")
            args = item.get("arguments") or item.get("args") or {}
            if isinstance(args, str):
                try:
                    args = json.loads(args)
                except json.JSONDecodeError:
                    args = {}
            if name:
                calls.append(ToolCall(name=name, args=args or {}))
        if calls:
            break

    return _dedupe(calls)


def _dedupe(calls: list[ToolCall]) -> list[ToolCall]:
    seen: set[tuple[str, str]] = set()
    unique: list[ToolCall] = []
    for c in calls:
        try:
            key = (c.name, json.dumps(c.args, sort_keys=True, ensure_ascii=False))
        except TypeError:
            key = (c.name, str(c.args))
        if key in seen:
            continue
        seen.add(key)
        unique.append(c)
    if len(unique) != len(calls):
        print(f"  [dedupe] removed {len(calls) - len(unique)} duplicate(s)")
    return unique


def is_already_exists_error(error: str) -> bool:
    low = error.lower()
    return "уже существует" in low or "already exists" in low or "уже создан" in low


def build_system_prompt() -> str:
    return """You are Damoony AI Agent. Use ONLY these tools:
- domain:new, allias:new, page:new, page:write, page:read
- image:search, image:download, fetch, net:status

RULES:
1. Output ONLY valid JSON array of actions (no explanations, no markdown).
2. Page/alias names: lowercase letters, digits, underscores. NO hyphens.
3. Root page is created automatically by allias:new. Use page:write with name='' for root.
4. NEVER put external URLs in img blocks. Use image:download first, then local_url from its response.
5. For each image: image:search -> pick url (width/height >= 800, license CC/PD) -> image:download -> page:write with local_url.
6. Always pass 'alt' to image:download.
7. If a command returns {ok: false, error, hint} — read hint and self-correct in next step.
8. If 'already exists' error — skip that command, do not retry.
9. Domain must be in config/agent.config allowed_domains.
10. Keep each JSON array <= 5 actions (batch limit). For longer plans, do it in multiple steps.
11. Do NOT repeat commands. Each (command, args) pair appears EXACTLY ONCE per step.
12. CRITICAL: Call page:write for each page EXACTLY ONCE per run. If a page is already written — do NOT write it again. Look at tool results from previous steps.
13. When all pages are written — respond with text 'DONE' and nothing else.
14. Do NOT stop until EVERY page has content. After page:new you MUST call page:write.
15. If brief mentions images — you MUST call image:search, then image:download, then use local_url in page:write for EACH page.
"""


def build_user_prompt(brief: str, allowed_domains: list[str]) -> str:
    ainet_present = "present" if _file_exists(f"{PROJECT_ROOT}/config/ainet.config") else "MISSING"
    return f"""BRIEF:
{brief}

CONTEXT:
- Allowed domains (from agent.config): {", ".join(allowed_domains) or "NONE"}
- AINET config: {ainet_present}
- Use ONLY allowed domains. If domain not allowed -> refuse.

REQUIRED CHECKLIST (do NOT stop until all done):
1. domain:new (if not exists)
2. allias:new (if not exists)
3. page:new for EACH page
4. image:search for EACH page theme
5. image:download for EACH chosen image (get local_url)
6. page:write for EACH page (with local_url in img blocks if applicable)

Rules:
- Max 5 actions per step. If checklist not complete -> continue in next step.
- After creating pages (step 1), you MUST proceed to images and page:write (step 2+).
- Do NOT call page:write twice for the same page.
- When ALL pages have page:write — respond with text 'DONE' and stop.

Start now with step 1 (skeleton), then continue to step 2 (content)."""


def _file_exists(path: str) -> bool:
    return os.path.isfile(path)


def call_ollama(model: str, messages: list[dict[str, Any]], tools: list[dict[str, Any]]) -> dict[str, Any]:
    payload = {
        "model": model,
        "messages": messages,
        "tools": tools,
        "stream": False,
        "options": {"temperature": 0, "num_ctx": 32768},
    }
    req = urllib.request.Request(
        OLLAMA_URL,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=OLLAMA_TIMEOUT) as resp:
        return json.loads(resp.read().decode("utf-8"))


def build_tools() -> list[dict[str, Any]]:
    str_prop = {"type": "string"}
    return [
        {"type": "function", "function": {"name": "domain:new",
            "description": "Create a domain. Domain must be in agent.config allowed_domains.",
            "parameters": {"type": "object", "properties": {"domain": str_prop}, "required": ["domain"]}}},
        {"type": "function", "function": {"name": "allias:new",
            "description": "Create an alias in domain.",
            "parameters": {"type": "object", "properties": {"domain": str_prop, "name": str_prop}, "required": ["domain", "name"]}}},
        {"type": "function", "function": {"name": "page:new",
            "description": "Create a page (empty). Root page is auto-created by allias:new.",
            "parameters": {"type": "object", "properties": {"domain": str_prop, "allias": str_prop, "name": str_prop}, "required": ["domain", "allias", "name"]}}},
        {"type": "function", "function": {"name": "page:write",
            "description": "Write JSON blocks to page (ONCE per page). Use name='' for root. For img use local_url from image:download.",
            "parameters": {"type": "object", "properties": {
                "domain": str_prop, "allias": str_prop, "name": str_prop,
                "blocks": {"type": "array", "items": {"type": "object", "properties": {
                    "type": {"type": "string", "enum": ["h1", "h2", "h3", "text", "img", "link", "button", "ul"]},
                    "content": str_prop, "url": str_prop,
                    "items": {"type": "array", "items": str_prop},
                    "class": str_prop, "id": str_prop, "style": str_prop, "alt": str_prop,
                }, "required": ["type"]}},
            }, "required": ["domain", "allias", "name", "blocks"]}}},
        {"type": "function", "function": {"name": "page:read",
            "description": "Read PHP code of a page.",
            "parameters": {"type": "object", "properties": {"domain": str_prop, "allias": str_prop, "name": str_prop}, "required": ["domain", "allias", "name"]}}},
        {"type": "function", "function": {"name": "image:search",
            "description": "Search images. Returns url, preview_url, width, height, license, author.",
            "parameters": {"type": "object", "properties": {
                "query": str_prop,
                "count": {"type": "integer", "minimum": 1, "maximum": 20},
                "provider": {"type": "string", "enum": ["wikimedia", "openverse"]},
            }, "required": ["query"]}}},
        {"type": "function", "function": {"name": "image:download",
            "description": "Download image to www/media/. Returns local_url for page:write img.content.",
            "parameters": {"type": "object", "properties": {
                "url": str_prop, "domain": str_prop, "allias": str_prop, "category": str_prop, "alt": str_prop,
            }, "required": ["url", "domain", "allias", "category"]}}},
        {"type": "function", "function": {"name": "fetch",
            "description": "GET whitelisted URL. Returns JSON or text.",
            "parameters": {"type": "object", "properties": {
                "url": str_prop, "type": {"type": "string", "enum": ["json", "text"]},
            }, "required": ["url"]}}},
        {"type": "function", "function": {"name": "net:status",
            "description": "AINET diagnostics: providers, cache, rate limit.",
            "parameters": {"type": "object", "properties": {}}}},
    ]


def run_agent(brief: str, model: str = DEFAULT_MODEL, max_steps: int = DEFAULT_MAX_STEPS,
              batch_size: int = BATCH_SIZE, dry_run: bool = False) -> dict[str, Any]:
    client = AicliClient()
    tx = Transaction(client)

    allowed_domains: list[str] = []
    try:
        with open(f"{PROJECT_ROOT}/config/agent.config", encoding="utf-8") as f:
            allowed_domains = json.load(f).get("allowed_domains", [])
    except Exception:
        pass

    messages: list[dict[str, Any]] = [
        {"role": "system", "content": build_system_prompt()},
        {"role": "user", "content": build_user_prompt(brief, allowed_domains)},
    ]
    tools = build_tools()

    print(f"\n=== Agent started (model: {model}, max_steps: {max_steps}, batch: {batch_size}) ===\n")
    stats = {"steps": 0, "commands": 0, "errors": 0, "rollbacks": 0, "batches": 0,
             "wrote_pages": 0, "skipped_duplicates": 0}

    # Трекинг по страницам (domain, allias, name) за весь прогон
    created_pages: set[tuple[str, str, str]] = set()
    written_pages: set[tuple[str, str, str]] = set()

    for step in range(1, max_steps + 1):
        stats["steps"] = step
        print(f"--- Step {step}/{max_steps} ---")

        try:
            response = call_ollama(model, messages, tools)
        except Exception as e:
            print(f"[!] Ollama error: {e}")
            return {"ok": False, "error": f"Ollama: {e}", "stats": stats}

        message = response.get("message", {})
        calls = parse_tool_calls(message)

        if not calls:
            final = (message.get("content") or "").strip()

            # Если LLM вернул JSON-план в тексте, но с markdown-фенсами — попробуем ещё раз
            if final.startswith("```") and step < max_steps:
                print("  [retry] content looks like markdown code fence, asking to fix")
                messages.append({"role": "assistant", "content": final})
                messages.append({
                    "role": "user",
                    "content": "Your response was wrapped in ```json ... ``` fences. Output ONLY the raw JSON array, no markdown. Try again.",
                })
                continue

            if not final:
                if step < max_steps:
                    print(f"  [retry] empty response, asking to continue (step {step+1})")
                    messages.append({"role": "assistant", "content": "(empty)"})
                    messages.append({
                        "role": "user",
                        "content": "You returned empty response. Check tool results: did you call page:write for EVERY page? If not — continue with JSON array. If done — respond with 'DONE'.",
                    })
                    continue
                final = "(agent finished with no text response — all actions done)"

            is_done = final.strip().upper().startswith("DONE") or "done" in final.lower()
            wrote_pages = stats.get("wrote_pages", 0)

            print(f"\n=== Final answer from agent ===\n{final}\n")

            # Успех: либо DONE, либо хотя бы одна страница записана
            if not is_done and wrote_pages == 0:
                return {
                    "ok": False,
                    "error": "Agent stopped without calling page:write",
                    "hint": "LLM returned text instead of actions; check prompt or increase max_steps",
                    "stats": stats,
                }

            tx.commit()
            return {"ok": True, "final": final, "stats": stats}

        messages.append({
            "role": "assistant",
            "content": message.get("content", ""),
            "tool_calls": [{"function": {"name": c.name, "arguments": c.args}} for c in calls],
        })

        batcher = Batcher(calls, size=batch_size)
        stats["batches"] += len(batcher)
        had_errors = False

        for batch_idx, batch in enumerate(batcher, 1):
            print(f"  Batch {batch_idx}/{len(batcher)}: {len(batch)} command(s)")
            for call in batch:
                stats["commands"] += 1
                print(f"  [AI] -> {call.name}  args={_short(call.args)}")

                if dry_run:
                    print("  [dry-run] skipped")
                    continue

                # Дедупликация page:write: одна страница — одна запись за прогон
                if call.name == "page:write":
                    key = _page_key(call.args)
                    if key in written_pages:
                        stats["skipped_duplicates"] += 1
                        print(f"  [skip] page '{key[2]}' already written in this run")
                        messages.append({
                            "role": "tool",
                            "content": json.dumps(
                                {"ok": True, "note": f"page '{key[2]}' already written, skipped"},
                                ensure_ascii=False,
                            ),
                        })
                        continue

                result = client.call_aicli(call.name, call.args)
                print(f"  [aicli] ok={result.ok}  {_short(result.data or result.error)}")

                if not result.ok:
                    if is_already_exists_error(result.error):
                        # Для page:new "уже существует" — это норма, добавляем в created_pages
                        if call.name == "page:new":
                            created_pages.add(_page_key(call.args))
                        print(f"  [skip] already exists")
                        messages.append({
                            "role": "tool",
                            "content": json.dumps({"ok": True, "note": "already exists, skipped"}, ensure_ascii=False),
                        })
                        continue

                    stats["errors"] += 1
                    had_errors = True
                    print(f"  [ERROR] {result.error}  hint={result.hint}")
                    rollback_log = tx.rollback()
                    stats["rollbacks"] += 1
                    print(f"  [ROLLBACK] {len(rollback_log)} operation(s)")
                    for line in rollback_log:
                        print(f"    {line}")
                    return {
                        "ok": False,
                        "error": result.error,
                        "hint": result.hint,
                        "context": result.context,
                        "stats": stats,
                    }

                # Успешные операции — трекинг
                if result.ok:
                    if call.name == "page:new":
                        created_pages.add(_page_key(call.args))
                    elif call.name == "page:write":
                        stats["wrote_pages"] += 1
                        written_pages.add(_page_key(call.args))

                _record_undo(tx, call, result)
                messages.append({
                    "role": "tool",
                    "content": json.dumps(_compact(result.data), ensure_ascii=False),
                })

        if had_errors:
            print("[!] Some commands failed, continuing to next step...")
            continue

        tx.commit()
        print(f"  [OK] Step {step} complete (committed)\n")

        # Auto-DONE: все созданные/записанные страницы покрыты → работа завершена
        if written_pages and (not created_pages or created_pages.issubset(written_pages)):
            missing = created_pages - written_pages
            if not missing:
                print(f"\n=== Auto-DONE: all pages written ({len(written_pages)}) ===\n")
                return {
                    "ok": True,
                    "final": f"DONE (auto: {len(written_pages)} pages written)",
                    "stats": stats,
                }

    print(f"\n[!] Max steps ({max_steps}) reached without final answer.")
    # Если хотя бы одна страница записана — считаем частичным успехом
    if stats.get("wrote_pages", 0) > 0:
        return {
            "ok": True,
            "final": f"(max_steps reached, {stats['wrote_pages']} pages written — partial success)",
            "stats": stats,
        }
    return {"ok": False, "error": "max_steps reached, no pages written", "stats": stats}


def _page_key(args: dict[str, Any]) -> tuple[str, str, str]:
    """Ключ страницы: (domain, allias, name). Для root — '__root__'."""
    return (
        str(args.get("domain", "")),
        str(args.get("allias", "")),
        str(args.get("name", "") or "__root__"),
    )


def _record_undo(tx: Transaction, call: ToolCall, result: Result) -> None:
    if call.name == "domain:new":
        tx.record_domain_new(call.args.get("domain", ""))
    elif call.name == "allias:new":
        tx.record_allias_new(call.args.get("domain", ""), call.args.get("name", ""))
    elif call.name == "image:download":
        data = result.data or {}
        if isinstance(data, dict) and data.get("hash"):
            tx.record_media(
                domain=call.args.get("domain", ""),
                allias=call.args.get("allias", ""),
                category=call.args.get("category", ""),
                file_hash=data["hash"],
            )


def _short(v: Any, n: int = 120) -> str:
    s = json.dumps(v, ensure_ascii=False) if not isinstance(v, str) else v
    return s if len(s) <= n else s[:n] + "..."


def _compact(data: Any) -> Any:
    if isinstance(data, dict):
        return {k: _compact(v) for k, v in data.items() if k not in ("bytes", "cli_output")}
    if isinstance(data, list):
        return [_compact(x) for x in data]
    return data


def main() -> int:
    parser = argparse.ArgumentParser(description="Damoony AI Agent")
    parser.add_argument("--brief", type=str, default="", help="Task description")
    parser.add_argument("--brief-file", type=str, default="", help="Read brief from file")
    parser.add_argument("--model", type=str, default=DEFAULT_MODEL, help=f"Ollama model (default: {DEFAULT_MODEL})")
    parser.add_argument("--max-steps", type=int, default=DEFAULT_MAX_STEPS)
    parser.add_argument("--batch-size", type=int, default=BATCH_SIZE)
    parser.add_argument("--dry-run", action="store_true", help="Don't execute commands")
    args = parser.parse_args()

    brief = args.brief
    if args.brief_file:
        with open(args.brief_file, encoding="utf-8") as f:
            brief = f.read()

    if not brief.strip():
        print("ERROR: --brief or --brief-file required")
        return 1

    result = run_agent(brief=brief, model=args.model, max_steps=args.max_steps,
                       batch_size=args.batch_size, dry_run=args.dry_run)

    print("\n=== Stats ===")
    print(json.dumps(result.get("stats", {}), indent=2))
    print(f"\nResult: {'OK' if result.get('ok') else 'FAILED'}")
    return 0 if result.get("ok") else 1


if __name__ == "__main__":
    sys.exit(main())
"""
Модели данных и OllamaClient.

- ToolCall: {name, args} — вызов инструмента из LLM.
- Result: {ok, data, error, hint, context, raw} — ответ aicli.
- OllamaClient: HTTP-обёртка над Ollama /api/chat.
- _short, _compact: утилиты для логов и передачи в LLM.
"""

from __future__ import annotations

import json
import urllib.request
from dataclasses import dataclass, field
from typing import Any


OLLAMA_URL = "http://localhost:11434/api/chat"
OLLAMA_TIMEOUT = 180


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


class OllamaClient:
    def __init__(self, url: str = OLLAMA_URL, timeout: int = OLLAMA_TIMEOUT):
        self.url = url
        self.timeout = timeout

    def chat(
        self,
        model: str,
        messages: list[dict[str, Any]],
        tools: list[dict[str, Any]] | None = None,
        temperature: float = 0.0,
        num_ctx: int = 32768,
    ) -> dict[str, Any]:
        payload: dict[str, Any] = {
            "model": model,
            "messages": messages,
            "stream": False,
            "options": {"temperature": temperature, "num_ctx": num_ctx},
        }
        if tools is not None:
            payload["tools"] = tools

        req = urllib.request.Request(
            self.url,
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        with urllib.request.urlopen(req, timeout=self.timeout) as resp:
            return json.loads(resp.read().decode("utf-8"))


def _short(v: Any, n: int = 120) -> str:
    """Сжать значение для лога."""
    s = json.dumps(v, ensure_ascii=False) if not isinstance(v, str) else v
    return s if len(s) <= n else s[:n] + "..."


def _compact(data: Any) -> Any:
    """Убрать bytes/cli_output из ответа перед передачей в LLM."""
    if isinstance(data, dict):
        return {k: _compact(v) for k, v in data.items() if k not in ("bytes", "cli_output")}
    if isinstance(data, list):
        return [_compact(x) for x in data]
    return data
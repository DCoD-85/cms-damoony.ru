"""
Модели данных, клиенты для Ollama, DeepSeek, Groq и Cloud.ru.
"""
from __future__ import annotations

import json
import urllib.request
import os
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


class OpenAICompatibleClient:
    """Базовый клиент для любого API, совместимого с OpenAI (DeepSeek, Groq, Cloud.ru)."""
    def __init__(self, api_key: str, base_url: str):
        self.api_key = api_key
        self.url = f"{base_url.rstrip('/')}/chat/completions"

    def chat(self, model: str, messages: list[dict[str, Any]], **kwargs) -> dict[str, Any]:
        payload = {
            "model": model,
            "messages": messages,
            "temperature": kwargs.get("temperature", 0.0),
            "stream": False
        }
        req = urllib.request.Request(
            self.url,
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {self.api_key}"
            },
            method="POST"
        )
        with urllib.request.urlopen(req, timeout=120) as resp:
            data = json.loads(resp.read().decode("utf-8"))
            # Приводим формат OpenAI к формату Ollama для совместимости с brain.py
            return {
                "message": {
                    "content": data["choices"][0]["message"]["content"]
                }
            }


def get_llm_client() -> tuple[Any, str]:
    """Читает конфиг и возвращает готовый клиент + название модели."""
    config_path = os.path.join(os.path.dirname(__file__), '..', 'config', 'agent.config')
    if not os.path.isfile(config_path):
        return OllamaClient(), "qwen2.5-coder:7b"

    with open(config_path, 'r', encoding='utf-8') as f:
        cfg = json.load(f)

    provider = cfg.get("llm_provider", "local")

    if provider == "deepseek":
        key = cfg.get("deepseek", {}).get("api_key", "")
        model = cfg.get("deepseek", {}).get("model", "deepseek-chat")
        base_url = cfg.get("deepseek", {}).get("base_url", "https://api.deepseek.com/v1")
        if not key:
            raise RuntimeError("DeepSeek API key is missing in config/agent.config")
        return OpenAICompatibleClient(api_key=key, base_url=base_url), model

    if provider == "groq":
        key = cfg.get("groq", {}).get("api_key", "")
        model = cfg.get("groq", {}).get("model", "llama-3.3-70b-versatile")
        base_url = cfg.get("groq", {}).get("base_url", "https://api.groq.com/openai/v1")
        if not key:
            raise RuntimeError("Groq API key is missing in config/agent.config")
        return OpenAICompatibleClient(api_key=key, base_url=base_url), model

    if provider == "yandex":
        key = cfg.get("yandex", {}).get("api_key", "")
        folder_id = cfg.get("yandex", {}).get("folder_id", "")
        model_name = cfg.get("yandex", {}).get("model", "deepseek-v4-flash")
        base_url = cfg.get("yandex", {}).get("base_url", "https://llm.api.cloud.yandex.net/v1")
        
        if not key or not folder_id:
            raise RuntimeError("Yandex API key or folder_id is missing")
        
        # Формируем правильный URI для Яндекса
        model_uri = f"gpt://{folder_id}/{model_name}"
        return OpenAICompatibleClient(api_key=key, base_url=base_url), model_uri
		
    if provider == "cloud_ru":
        key = cfg.get("cloud_ru", {}).get("api_key", "")
        model = cfg.get("cloud_ru", {}).get("model", "GigaChat-Lightning")
        base_url = cfg.get("cloud_ru", {}).get("base_url", "https://foundation-models.api.cloud.ru/v1")
        if not key:
            raise RuntimeError("Cloud.ru API key is missing in config/agent.config")
        return OpenAICompatibleClient(api_key=key, base_url=base_url), model

    # Fallback: локальная Ollama
    model = cfg.get("llm_model", "qwen2.5-coder:7b")
    return OllamaClient(), model


def _short(v: Any, n: int = 120) -> str:
    s = json.dumps(v, ensure_ascii=False) if not isinstance(v, str) else v
    return s if len(s) <= n else s[:n] + "..."

def _compact(data: Any) -> Any:
    if isinstance(data, dict):
        return {k: _compact(v) for k, v in data.items() if k not in ("bytes", "cli_output")}
    if isinstance(data, list):
        return [_compact(x) for x in data]
    return data
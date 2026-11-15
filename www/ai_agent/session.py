"""
Session — персистентность состояния работы агента.

Каждый запуск создаёт ai_agent/.session/<YYYYMMDD_HHMMSS>/:
- brief.txt (вход)
- config.json (model, allowed_domains)
- plan.json (Planner)
- skeleton.json (Builder)
- content.json (Content, обновляется после каждой страницы)
- log.txt (полный лог, append)
- state.json (running/completed/failed)

На успехе — стирается (по умолчанию). На падении — сохраняется (для отладки).
Флаги: keep_on_success, resume_from (путь).
"""

from __future__ import annotations

import json
import shutil
from datetime import datetime
from pathlib import Path
from typing import Any


SESSION_BASE = Path(__file__).parent / ".session"


class Session:
    def __init__(self, path: Path, brief: str, config: dict[str, Any]):
        self.path = path
        self.brief = brief
        self.config = config

    # === Фабрика ===

    @classmethod
    def create(cls, brief: str, config: dict[str, Any], base: Path | None = None) -> "Session":
        base = base or SESSION_BASE
        ts = datetime.now().strftime("%Y%m%d_%H%M%S")
        path = base / ts
        # Коллизия по секундам — добавим суффикс
        i = 1
        while path.exists():
            path = base / f"{ts}_{i}"
            i += 1
        path.mkdir(parents=True, exist_ok=False)

        session = cls(path, brief, config)
        session.write_text("brief.txt", brief)
        session.write_json("config.json", config)
        session.write_json("state.json", {
            "status": "running",
            "started_at": datetime.now().isoformat(timespec="seconds"),
        })
        session.log(f"Session created: {path.name}")
        session.log(f"Model: {config.get('model', '?')}, allowed_domains: {config.get('allowed_domains', [])}")
        return session

    @classmethod
    def resume(cls, timestamp: str, base: Path | None = None) -> "Session":
        base = base or SESSION_BASE
        path = base / timestamp
        if not path.is_dir():
            raise FileNotFoundError(f"Session not found: {path}")
        brief = (path / "brief.txt").read_text(encoding="utf-8")
        config_file = path / "config.json"
        config = json.loads(config_file.read_text(encoding="utf-8")) if config_file.is_file() else {}
        session = cls(path, brief, config)
        session.log(f"Session resumed: {timestamp}")
        return session

    # === Запись ===

    def write_text(self, name: str, text: str) -> None:
        (self.path / name).write_text(text, encoding="utf-8")

    def write_json(self, name: str, data: Any) -> None:
        (self.path / name).write_text(
            json.dumps(data, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )

    def log(self, message: str) -> None:
        ts = datetime.now().isoformat(timespec="seconds")
        with (self.path / "log.txt").open("a", encoding="utf-8") as f:
            f.write(f"[{ts}] {message}\n")

    # === Чтение (для resume) ===

    def read_json(self, name: str) -> Any | None:
        p = self.path / name
        if not p.is_file():
            return None
        return json.loads(p.read_text(encoding="utf-8"))

    def read_text(self, name: str) -> str | None:
        p = self.path / name
        return p.read_text(encoding="utf-8") if p.is_file() else None

    # === Lifecycle ===

    def complete(self, erase: bool = True) -> None:
        self.write_json("state.json", {
            "status": "completed",
            "started_at": self.read_json("state.json").get("started_at") if self.read_json("state.json") else None,
            "completed_at": datetime.now().isoformat(timespec="seconds"),
        })
        self.log("Session completed")
        if erase:
            self.erase()

    def fail(self, error: str) -> None:
        self.write_json("state.json", {
            "status": "failed",
            "started_at": self.read_json("state.json").get("started_at") if self.read_json("state.json") else None,
            "failed_at": datetime.now().isoformat(timespec="seconds"),
            "error": error,
        })
        self.log(f"Session failed: {error}")

    def erase(self) -> None:
        shutil.rmtree(self.path, ignore_errors=True)
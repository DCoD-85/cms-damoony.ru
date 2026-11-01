"""
AicliClient + Transaction.

- AicliClient.call_aicli: subprocess → php bin/aicli.php (STDIN JSON, STDOUT JSON).
  Единственный канал для AI (валидация, whitelist доменов, tool-роутинг).
- AicliClient.call_cli: subprocess → php bin/cli.php (для rollback, доверенный Python).
- Transaction: undo-стек. commit() очищает undo, rollback() — обратный порядок.
"""

from __future__ import annotations

import json
import subprocess
from pathlib import Path
from typing import Any

from .models import Result


PROJECT_ROOT = Path(__file__).resolve().parent.parent
AICLI_PATH = PROJECT_ROOT / "bin" / "aicli.php"
CLI_PATH = PROJECT_ROOT / "bin" / "cli.php"

CLI_TIMEOUT = 60


class AicliClient:
    def __init__(self, aicli_path: Path = AICLI_PATH, cli_path: Path = CLI_PATH, timeout: int = CLI_TIMEOUT):
        self.aicli_path = aicli_path
        self.cli_path = cli_path
        self.timeout = timeout

    def call_aicli(self, command: str, args: dict[str, Any]) -> Result:
        """AI-фасад: валидация, whitelist, домены. Только разрешённое."""
        payload = json.dumps({"command": command, "args": args}, ensure_ascii=False)
        try:
            proc = subprocess.run(
                ["php", str(self.aicli_path)],
                input=payload,
                text=True,
                capture_output=True,
                encoding="utf-8",
                timeout=self.timeout,
            )
        except subprocess.TimeoutExpired:
            return Result(ok=False, error=f"Timeout: {command}")
        except Exception as e:
            return Result(ok=False, error=f"Subprocess failed: {e}")

        if proc.returncode != 0 and not proc.stdout.strip():
            return Result(ok=False, error=f"aicli exit {proc.returncode}: {proc.stderr[:200]}")

        return Result.from_json(proc.stdout)

    def call_cli(self, command: str, args: dict[str, Any]) -> Result:
        """Прямой CLI: для rollback (доверенный Python, не AI)."""
        cmd = ["php", str(self.cli_path), command]
        for k, v in args.items():
            if isinstance(v, bool):
                if v:
                    cmd.append(f"--{k}")
            else:
                cmd.append(f"--{k}={v}")
        try:
            proc = subprocess.run(
                cmd,
                text=True,
                capture_output=True,
                encoding="utf-8",
                timeout=self.timeout,
            )
        except Exception as e:
            return Result(ok=False, error=f"CLI subprocess failed: {e}")

        output = proc.stdout.strip()
        ok = "OK" in output and "FAILED" not in output and proc.returncode == 0
        return Result(ok=ok, data={"output": output}, raw=output)


class Transaction:
    """
    Undo-стек. Записывает откатывающие команды. commit() очищает (шаг успешен),
    rollback() — выполняет в обратном порядке (при падении).
    """

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
        """Зафиксировать: очистить undo. Транзакция остаётся активной."""
        self.undo = []

    def rollback(self) -> list[str]:
        """Откат в обратном порядке. Возвращает лог."""
        log: list[str] = []
        for cmd, args in reversed(self.undo):
            log.append(f"ROLLBACK: {cmd} {args}")
            self.client.call_cli(cmd, args)
        self.undo = []
        return log
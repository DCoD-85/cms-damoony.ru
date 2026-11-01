"""
Builder: plan → skeleton.

Детерминированный (без LLM). Последовательность:
1. domain:new (если нет — ok=true или "already exists" → skip)
2. allias:new (то же)
3. page:new × N (для каждой pages[i])

Использует AicliClient.call_aicli (AI-фасад: валидация, whitelist, домен).
Transaction: при падении на шаге — rollback предыдущих.
На "already exists" — не ошибка, skip и продолжаем (idempotency).

Результат:
{
  "domain": str,
  "allias": str,
  "created": ["domain", "allias", "page:about", ...],
  "skipped": ["domain", "page:menu", ...]
}
"""

from __future__ import annotations

from typing import Any

from .aicli_client import AicliClient, Transaction
from .models import Result
from .session import Session


class BuilderError(Exception):
    pass


def run_builder(
    plan: dict[str, Any],
    session: Session,
    client: AicliClient | None = None,
) -> dict[str, Any]:
    client = client or AicliClient()
    tx = Transaction(client)

    domain = plan["domain"]
    allias = plan["allias"]
    pages = plan["pages"]

    created: list[str] = []
    skipped: list[str] = []

    session.log(f"Builder: domain={domain}, allias={allias}, pages={len(pages)}")

    try:
        # 1. domain:new
        _run_or_skip(
            client=client,
            session=session,
            tx=tx,
            command="domain:new",
            args={"domain": domain},
            label="domain",
            created=created,
            skipped=skipped,
            undo=None,
        )

        # 2. allias:new
        _run_or_skip(
            client=client,
            session=session,
            tx=tx,
            command="allias:new",
            args={"domain": domain, "name": allias},
            label="allias",
            created=created,
            skipped=skipped,
            undo=lambda: tx.record_allias_new(domain, allias),
        )

        # 3. page:new × N
        for p in pages:
            name = p["name"]
            _run_or_skip(
                client=client,
                session=session,
                tx=tx,
                command="page:new",
                args={"domain": domain, "allias": allias, "name": name},
                label=f"page:{name}",
                created=created,
                skipped=skipped,
                undo=None,  # page:new не откатываем (пустая страница безопасна)
            )

        # Успех — коммитим (undo больше не нужен)
        tx.commit()

        skeleton = {
            "domain": domain,
            "allias": allias,
            "created": created,
            "skipped": skipped,
        }
        session.write_json("skeleton.json", skeleton)
        session.log(f"Builder OK: created={len(created)}, skipped={len(skipped)}")

        return skeleton

    except BuilderError as e:
        rollback_log = tx.rollback()
        session.log(f"Builder FAILED: {e}")
        session.log(f"Builder rollback: {len(rollback_log)} op(s)")
        for line in rollback_log:
            session.log(f"  {line}")
        raise


def _run_or_skip(
    *,
    client: AicliClient,
    session: Session,
    tx: Transaction,
    command: str,
    args: dict[str, Any],
    label: str,
    created: list[str],
    skipped: list[str],
    undo: Any,
) -> None:
    """
    Выполняет одну команду. На "already exists" — skip. На прочих ошибках — raise.
    """
    result = client.call_aicli(command, args)
    session.log(f"  {command} {args} → ok={result.ok}")

    if result.ok:
        created.append(label)
        if undo is not None:
            undo()
        session.log(f"    + created {label}")
        return

    if _is_already_exists(result.error):
        skipped.append(label)
        session.log(f"    ~ skipped {label} (already exists)")
        return

    # Реальная ошибка
    raise BuilderError(
        f"{label} failed: {result.error}"
        + (f" (hint: {result.hint})" if result.hint else "")
    )


def _is_already_exists(error: str) -> bool:
    low = error.lower()
    return "уже существует" in low or "already exists" in low or "уже создан" in low
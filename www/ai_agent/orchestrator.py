"""
Orchestrator: main entry point.

Pipeline:
  1. Session.create(brief, config)
  2. Planner.run  → plan.json
  3. Builder.run  → skeleton.json
  4. Content.run  → content.json (по страницам)
  5. Session.complete(erase=not keep_session)
"""

from __future__ import annotations

import argparse
import json
import sys
import time
from pathlib import Path
from typing import Any

from .builder import BuilderError, run_builder
from .content import ContentError, run_content
from .models import OllamaClient
from .planner import PlannerError, run_planner
from .session import Session
from .profiles import PROFILES


PROJECT_ROOT = Path(__file__).resolve().parent.parent


def run(
    brief: str,
    model: str | None = None,
    allowed_domains: list[str] | None = None,
    keep_session: bool = False,
    reset: bool = False,
    llm: OllamaClient | None = None,
    profile_name: str = "default",
    force_allias: str | None = None,
    force_domain: str | None = None,
) -> dict[str, Any]:
    """Полный прогон: Planner → Builder → Content."""
    profile = PROFILES.get(profile_name, PROFILES["default"])
    model = model or profile["model"]
    allowed_domains = allowed_domains or _load_allowed_domains()
    llm = llm or OllamaClient(timeout=profile["timeout"])

    if not allowed_domains:
        return {
            "ok": False,
            "status": "failed",
            "error": "No allowed_domains (add to config/agent.config)",
            "stats": _empty_stats(),
        }

    session = Session.create(brief, {
        "model": model,
        "profile": profile_name,
        "allowed_domains": allowed_domains,
    })
    session.log(f"Orchestrator started: model={model}, profile={profile_name}, brief={brief[:80]}")

    started = time.monotonic()
    stats = _empty_stats()

    result: dict[str, Any] = {
        "ok": False,
        "status": "failed",
        "plan": None,
        "skeleton": None,
        "content": None,
        "error": "",
        "session_path": str(session.path),
        "stats": stats,
    }

    # === Phase 1: Planner ===
    try:
        plan = run_planner(
            brief=brief,
            allowed_domains=allowed_domains,
            model=model,
            session=session,
            llm=llm,
            system_prompt=profile["planner_system"],
        )
        stats["llm_calls"] += 1
        stats["pages_total"] = len(plan["pages"])
        result["plan"] = plan
        session.log(f"Planner done: {len(plan['pages'])} pages")

        # Принудительная установка домена/алиаса, если переданы флаги
        if force_domain:
            plan["domain"] = force_domain
            session.log(f"Domain overridden by CLI: {force_domain}")
            
        if force_allias:
            plan["allias"] = force_allias
            session.log(f"Allias overridden by CLI: {force_allias}")

        if force_domain or force_allias:
            session.write_json("plan.json", plan)

    except PlannerError as e:
        result["error"] = f"Planner failed: {e}"
        stats["duration_sec"] = round(time.monotonic() - started, 2)
        session.fail(result["error"])
        return result

    # === Phase 2: Builder ===
    try:
        # --reset: удалить существующий алиас перед пересборкой
        if reset:
            session.log(f"Reset: removing existing alias {plan['domain']}/{plan['allias']}")
            from .aicli_client import AicliClient
            _reset_alias(plan["domain"], plan["allias"], AicliClient(), session)

        skeleton = run_builder(plan=plan, session=session)
        result["skeleton"] = skeleton
        session.log(f"Builder done: created={len(skeleton['created'])}, skipped={len(skeleton['skipped'])}")
    except BuilderError as e:
        result["error"] = f"Builder failed: {e}"
        stats["duration_sec"] = round(time.monotonic() - started, 2)
        session.fail(result["error"])
        return result

    # === Phase 3: Content ===
    try:
        content = run_content(
            plan=plan,
            skeleton=skeleton,
            session=session,
            model=model,
            llm=llm,
            system_prompt=profile["content_system"],
        )
        stats["llm_calls"] += len(plan["pages"])
        result["content"] = content

        written = sum(1 for v in content.values() if v.get("status") == "written")
        failed = sum(1 for v in content.values() if v.get("status") == "failed")
        stats["pages_written"] = written
        stats["pages_failed"] = failed
        stats["media_downloaded"] = sum(len(v.get("media", [])) for v in content.values())

        if failed == 0:
            result["ok"] = True
            result["status"] = "completed"
        elif written > 0:
            result["ok"] = True
            result["status"] = "partial"
        else:
            result["error"] = f"All pages failed ({failed})"
            result["status"] = "failed"
    except ContentError as e:
        result["error"] = f"Content failed: {e}"
        stats["duration_sec"] = round(time.monotonic() - started, 2)
        session.fail(result["error"])
        return result

    stats["duration_sec"] = round(time.monotonic() - started, 2)

    if result["ok"]:
        session.complete(erase=not keep_session)
    else:
        session.fail(result["error"] or "unknown")

    return result


def _reset_alias(domain: str, allias: str, client: Any, session: Session) -> None:
    """Удалить алиас (в карантин) для чистого rebuild."""
    result = client.call_cli("allias:remove", {
        "domain": domain,
        "name": allias,
        "force": True,
    })
    session.log(f"  allias:remove → ok={result.ok}")


def _load_allowed_domains() -> list[str]:
    """Читает config/agent.config (allowed_domains)."""
    path = PROJECT_ROOT / "config" / "agent.config"
    if not path.is_file():
        return []
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        domains = data.get("allowed_domains", [])
        return [d for d in domains if isinstance(d, str)]
    except Exception:
        return []


def _empty_stats() -> dict[str, Any]:
    return {
        "pages_total": 0,
        "pages_written": 0,
        "pages_failed": 0,
        "media_downloaded": 0,
        "llm_calls": 0,
        "duration_sec": 0.0,
    }


# === CLI entry point ===

def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        prog="ai_agent",
        description="Damoony AI Agent — Planner → Builder → Content",
    )
    parser.add_argument("--brief", type=str, default="", help="Task description (inline)")
    parser.add_argument("--brief-file", type=str, default="", help="Read brief from file")
    parser.add_argument("--model", type=str, default=None,
                        help="Ollama model (overrides profile)")
    parser.add_argument("--domains", type=str, default="",
                        help="Comma-separated allowed domains (default: from agent.config)")
    parser.add_argument("--keep-session", action="store_true",
                        help="Не стирать session-директорию на успехе (для отладки)")
    parser.add_argument("--reset", action="store_true",
                        help="Удалить существующий алиас перед пересборкой (fresh site)")
    parser.add_argument("--json", action="store_true",
                        help="Вывести результат как JSON (для скриптов)")
    parser.add_argument("--profile", type=str, default="default", choices=["default", "rich"],
                        help="Профиль агента (default/rich)")
    parser.add_argument("--allias", type=str, default="",
                        help="Принудительно задать имя алиаса (игнорирует выбор LLM)")
    parser.add_argument("--domain", type=str, default="",
                        help="Принудительно задать домен (игнорирует выбор LLM)")
    args = parser.parse_args(argv)

    brief = args.brief
    if args.brief_file:
        brief = Path(args.brief_file).read_text(encoding="utf-8")
    if not brief.strip():
        print("ERROR: --brief or --brief-file required", file=sys.stderr)
        return 1

    allowed_domains: list[str] | None = None
    if args.domains:
        allowed_domains = [d.strip() for d in args.domains.split(",") if d.strip()]

    result = run(
        brief=brief,
        model=args.model,
        allowed_domains=allowed_domains,
        keep_session=args.keep_session,
        reset=args.reset,
        profile_name=args.profile,
        force_allias=args.allias,
        force_domain=args.domain,
    )

    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        _print_human(result)

    return 0 if result.get("ok") else 1


def _print_human(result: dict[str, Any]) -> None:
    print()
    print(f"Status: {result['status'].upper()}")
    if result.get("error"):
        print(f"Error:  {result['error']}")
    if result.get("session_path"):
        print(f"Session: {result['session_path']}")

    plan = result.get("plan")
    if plan:
        print(f"\nPlan:   domain={plan['domain']}, allias={plan['allias']}, "
              f"pages={len(plan['pages'])}, images={plan['images']}")

    content = result.get("content") or {}
    if content:
        print("\nPages:")
        for name, info in content.items():
            status = info.get("status", "?")
            mark = "[OK]" if status == "written" else "[!!]"
            extra = ""
            if status == "written":
                extra = f"blocks={info.get('blocks_count', 0)}"
                if info.get("media"):
                    extra += f", media={len(info['media'])}"
            else:
                extra = info.get("error", "?")[:60]
            print(f"  {mark} {name:<20} {extra}")

    stats = result.get("stats", {})
    print("\nStats:")
    print(f"  pages:   {stats.get('pages_written', 0)}/{stats.get('pages_total', 0)} written, "
          f"{stats.get('pages_failed', 0)} failed")
    print(f"  media:   {stats.get('media_downloaded', 0)}")
    print(f"  LLM:     {stats.get('llm_calls', 0)} call(s)")
    print(f"  time:    {stats.get('duration_sec', 0)}s")


if __name__ == "__main__":
    sys.exit(main())
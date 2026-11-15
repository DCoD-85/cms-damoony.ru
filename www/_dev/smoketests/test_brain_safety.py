"""
Smoke tests for ai_agent/brain.py (pure functions).

Run with either:
    python _dev/smoketests/test_brain_safety.py      # plain, no deps
    python -m pytest _dev/smoketests/ -v             # with pytest

See _doc/<smoke-readme>.md for scope.
"""
import sys
from pathlib import Path

# Make `ai_agent` importable when running from project root
sys.path.insert(0, str(Path(__file__).resolve().parent.parent.parent))

from ai_agent.brain import _safe_href, _sanitize_name, validate_plan


# --- _safe_href ---
def test_safe_href_local_path():
    assert _safe_href('/about') == '/about'

def test_safe_href_anchor():
    assert _safe_href('#anchor') == '#anchor'

def test_safe_href_https():
    assert _safe_href('https://example.com') == 'https://example.com'

def test_safe_href_http():
    assert _safe_href('http://example.com') == 'http://example.com'

def test_safe_href_javascript_rejected():
    assert _safe_href('javascript:alert(1)') is None

def test_safe_href_data_rejected():
    assert _safe_href('data:text/html,<script>') is None

def test_safe_href_vbscript_rejected():
    assert _safe_href('vbscript:msgbox(1)') is None

def test_safe_href_file_rejected():
    assert _safe_href('file:///etc/passwd') is None

def test_safe_href_protocol_relative_rejected():
    assert _safe_href('//evil.com/x') is None

def test_safe_href_empty_rejected():
    assert _safe_href('') is None
    assert _safe_href('   ') is None

def test_safe_href_escapes_quotes():
    assert _safe_href('/foo" onclick="x') == '/foo%22 onclick=%22x'

def test_safe_href_escapes_angle_brackets():
    assert _safe_href('/foo<script>') == '/foo%3Cscript%3E'


# --- _sanitize_name ---
def test_sanitize_name_lowercase():
    assert _sanitize_name('About') == 'about'

def test_sanitize_name_strips_spaces():
    assert _sanitize_name('About Us') == 'aboutus'

def test_sanitize_name_strips_special():
    assert _sanitize_name('Care-Guide!') == 'careguide'

def test_sanitize_name_root_preserved():
    assert _sanitize_name('__root__') == '__root__'

def test_sanitize_name_numeric_prefix():
    assert _sanitize_name('123page') == 'page_123page'

def test_sanitize_name_empty():
    assert _sanitize_name('') == 'page'


# --- validate_plan ---
def test_validate_plan_topo_sort():
    plan = {"pages": [
        {"name": "child", "title": "C", "parent": "parent"},
        {"name": "__root__", "title": "Root", "parent": None},
        {"name": "parent", "title": "P", "parent": None},
    ]}
    fixed = validate_plan(plan)
    names = [p["name"] for p in fixed["pages"]]
    assert names.index("__root__") < names.index("child")
    assert names.index("parent") < names.index("child")
    assert fixed["pages"][names.index("child")]["parent"] == "parent"

def test_validate_plan_orphan_parent_dropped():
    plan = {"pages": [
        {"name": "orphan", "title": "O", "parent": "nonexistent"},
        {"name": "__root__", "title": "R", "parent": None},
    ]}
    fixed = validate_plan(plan)
    names = [p["name"] for p in fixed["pages"]]
    assert fixed["pages"][names.index("orphan")]["parent"] is None

def test_validate_plan_glued_name_split():
    plan = {"pages": [
        {"name": "__root__", "title": "R"},
        {"name": "care_guide", "title": "CG", "parent": None},
        {"name": "care_guide_documentation", "title": "D", "parent": "care_guide"},
    ]}
    fixed = validate_plan(plan)
    names = [p["name"] for p in fixed["pages"]]
    assert "care_guide" in names
    assert "care_guide_documentation" not in names
    assert "documentation" in names
    assert fixed["pages"][names.index("documentation")]["parent"] == "care_guide"

def test_validate_plan_invalid_parent_sanitized():
    plan = {"pages": [
        {"name": "__root__", "title": "R", "parent": None},
        {"name": "child", "title": "C", "parent": "Parent With Spaces"},
    ]}
    fixed = validate_plan(plan)
    names = [p["name"] for p in fixed["pages"]]
    assert fixed["pages"][names.index("child")]["parent"] is None

def test_validate_plan_cycle_does_not_hang():
    plan = {"pages": [
        {"name": "a", "title": "A", "parent": "b"},
        {"name": "b", "title": "B", "parent": "a"},
        {"name": "__root__", "title": "R", "parent": None},
    ]}
    fixed = validate_plan(plan)
    assert len(fixed["pages"]) == 3


# --- Plain runner (no pytest required) ---
def _run_plain() -> int:
    """Plain runner that works on Windows cp1251/cp866 consoles (ASCII output)."""
    # Force UTF-8 on stdout/stderr if possible; ignore if not supported.
    for stream in (sys.stdout, sys.stderr):
        try:
            stream.reconfigure(encoding="utf-8", errors="replace")  # type: ignore[attr-defined]
        except (AttributeError, ValueError, OSError):
            pass

    failed = 0
    passed = 0
    for name, obj in sorted(globals().items()):
        if name.startswith("test_") and callable(obj):
            try:
                obj()
                passed += 1
                print(f"  [OK] {name}")
            except AssertionError as e:
                failed += 1
                print(f"  [FAIL] {name}: {e}")
    print(f"\n{passed} passed, {failed} failed")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(_run_plain())
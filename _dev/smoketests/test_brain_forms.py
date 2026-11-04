"""Smoke test: _build_form_blocks determinism (no LLM)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent.parent))

from ai_agent.brain import _build_form_blocks


def test_contact_form():
    spec = {
        "needs_form": True,
        "target_page": "__root__",
        "form_name": "contact",
        "fields": [
            {"name": "name", "type": "text", "label": "Ваше имя", "required": True},
            {"name": "email", "type": "email", "label": "Email", "required": True},
            {"name": "message", "type": "textarea", "label": "Сообщение", "required": False},
        ],
    }
    blocks = _build_form_blocks(spec)
    assert len(blocks) == 1
    form = blocks[0]
    assert form["type"] == "form"
    assert form["method"] == "POST"
    assert form["action"] == "/submit"
    assert form["class"] == "contact-form"
    children = form["children"]
    assert children[0]["name"] == "_form_name"
    assert children[0]["value"] == "contact"
    assert children[-1]["type"] == "button"
    textareas = [c for c in children if c["type"] == "textarea"]
    assert len(textareas) == 1
    assert textareas[0]["name"] == "message"
    print("OK: test_contact_form")


def test_empty_fields():
    assert _build_form_blocks({"form_name": "x", "fields": []}) == []
    assert _build_form_blocks({"form_name": "x"}) == []
    print("OK: test_empty_fields")


def test_select_field():
    spec = {
        "form_name": "order",
        "fields": [
            {"name": "coffee", "type": "select", "label": "Кофе", "required": True,
             "options": [{"value": "espresso", "label": "Эспрессо"},
                         {"value": "latte", "label": "Латте"}]},
        ],
    }
    blocks = _build_form_blocks(spec)
    selects = [c for c in blocks[0]["children"] if c["type"] == "select"]
    assert len(selects) == 1
    assert selects[0]["name"] == "coffee"
    assert len(selects[0]["options"]) == 2
    print("OK: test_select_field")


if __name__ == "__main__":
    test_contact_form()
    test_empty_fields()
    test_select_field()
    print("\n3 passed, 0 failed")

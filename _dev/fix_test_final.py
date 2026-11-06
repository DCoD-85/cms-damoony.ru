from pathlib import Path

p = Path("_dev/tests/Unit/Ai/Orchestrator/OrchestratorTest.php")
text = p.read_text(encoding="utf-8")

# Добавляем 'brief' в фейковый Интервьюер
find = "return ['action' => 'build', 'message' => 'test'];"
replace = "return ['action' => 'build', 'message' => 'test', 'brief' => 'Test prompt'];"

if find in text:
    text = text.replace(find, replace)
    p.write_text(text, encoding="utf-8")
    print("Patched: OrchestratorTest.php (brief added)")
else:
    print("Pattern not found or already patched!")
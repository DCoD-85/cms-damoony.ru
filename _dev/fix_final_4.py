from pathlib import Path

p = Path("_dev/tests/Unit/Ai/Orchestrator/OrchestratorTest.php")
text = p.read_text(encoding="utf-8")

# Заменяем попытку мока на создание реального объекта
find = "$fakeAicli = $this->createMock(\\Damoony\\Ai\\Aicli\\AicliClient::class);"
replace = "$fakeAicli = new \\Damoony\\Ai\\Aicli\\AicliClient($this->root);"

if find in text:
    text = text.replace(find, replace)
    p.write_text(text, encoding="utf-8")
    print("Patched: OrchestratorTest.php")
else:
    print("Pattern not found or already patched!")
import json
import subprocess
import urllib.request

OLLAMA_URL = "http://localhost:11434/api/chat"
MODEL_NAME = "gemma4:26b"

messages = [
    {
        "role": "system", 
        "content": "You are an AI managing the Damoony Engine. You can create domains, aliases, and pages. To create a full site: 1) Create domain 'damoony3test.ru'. 2) Create alias 'coffee'. 3) Create page 'about' for alias 'coffee'."
    },
    {
        "role": "user", 
        "content": "Create a new test site for a coffee shop on the domain 'damoony3test.ru'. Create the alias 'coffee'. Then create a page named 'about' inside the 'coffee' alias."
    }
]

tools = [
    {
        "type": "function",
        "function": {
            "name": "engine_status",
            "description": "Runs structure:check to verify the engine's health.",
            "parameters": {"type": "object", "properties": {}, "required": []}
        }
    },
    {
        "type": "function",
        "function": {
            "name": "domain:new",
            "description": "Creates a new domain.",
            "parameters": {
                "type": "object",
                "properties": {"domain": {"type": "string"}},
                "required": ["domain"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "allias:new",
            "description": "Creates a new alias for a domain.",
            "parameters": {
                "type": "object",
                "properties": {
                    "domain": {"type": "string"},
                    "name": {"type": "string"}
                },
                "required": ["domain", "name"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "page:new",
            "description": "Creates a new page inside an alias.",
            "parameters": {
                "type": "object",
                "properties": {
                    "domain": {"type": "string"},
                    "allias": {"type": "string"},
                    "name": {"type": "string"}
                },
                "required": ["domain", "allias", "name"]
            }
        }
    }
]

def call_ollama(payload):
    req = urllib.request.Request(
        OLLAMA_URL,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode("utf-8"))

print(f"Запуск агента ({MODEL_NAME})...\n")

step = 1
while True:
    print(f"--- Шаг {step}: Запрос к Gemma ---")
    
    payload = {"model": MODEL_NAME, "messages": messages, "tools": tools, "stream": False}
    
    try:
        data = call_ollama(payload)
    except Exception as e:
        print(f"Ошибка: {e}")
        break
        
    message = data.get("message", {})
    messages.append(message) 
    
    tool_calls = message.get("tool_calls")
    
    if not tool_calls:
        print("\n==================================================")
        print("ФИНАЛЬНЫЙ ОТВЕТ АГЕНТА:")
        print("==================================================")
        print(message.get("content"))
        print("==================================================")
        break
        
    for tool_call in tool_calls:
        cmd_name = tool_call["function"]["name"]
        cmd_args = tool_call["function"]["arguments"]
        
        print(f"[AI] Решил вызвать: {cmd_name}")
        print(f"[AI] Аргументы: {cmd_args}")
        
        php_input = json.dumps({"command": cmd_name, "args": cmd_args})
        result = subprocess.run(
            ["php", "bin/aicli.php"],
            input=php_input, text=True, capture_output=True, encoding='utf-8'
        )
        
        print(f"[Safe-CLI] Ответ: {result.stdout.strip()}")
        
        messages.append({"role": "tool", "content": result.stdout, "name": cmd_name})
        
    step += 1
    print("")
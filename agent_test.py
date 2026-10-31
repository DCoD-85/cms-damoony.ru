import json
import subprocess
import urllib.request

OLLAMA_URL = "http://localhost:11434/api/chat"
MODEL_NAME = "gemma4:26b"
MAX_STEPS = 10  # Защита от зацикливания

messages = [
    {
        "role": "system", 
        "content": "You are an AI managing the Damoony Engine. Follow these steps exactly: 1) domain:new for 'damoony3test.ru'. 2) allias:new 'coffee'. 3) page:new 'about'. 4) page:write to add content. For page:write, pass the 'blocks' parameter as a JSON array of objects."
    },
    {
        "role": "user", 
        "content": "Build the coffee shop site. Fill the 'about' page with a heading 'About Coffee' and text 'Best coffee in town'."
    }
]

tools = [
    {
        "type": "function",
        "function": {
            "name": "domain:new",
            "description": "Creates a new domain.",
            "parameters": {"type": "object", "properties": {"domain": {"type": "string"}}, "required": ["domain"]}
        }
    },
    {
        "type": "function",
        "function": {
            "name": "allias:new",
            "description": "Creates a new alias.",
            "parameters": {"type": "object", "properties": {"domain": {"type": "string"}, "name": {"type": "string"}}, "required": ["domain", "name"]}
        }
    },
    {
        "type": "function",
        "function": {
            "name": "page:new",
            "description": "Creates a new page.",
            "parameters": {"type": "object", "properties": {"domain": {"type": "string"}, "allias": {"type": "string"}, "name": {"type": "string"}}, "required": ["domain", "allias", "name"]}
        }
    },
    {
        "type": "function",
        "function": {
            "name": "page:write",
            "description": "Writes content to a page. The 'blocks' parameter must be a JSON array (list) of objects. Example: [{\"type\": \"h1\", \"content\": \"Title\"}, {\"type\": \"text\", \"content\": \"Body\"}]",
            "parameters": {
                "type": "object",
                "properties": {
                    "domain": {"type": "string"},
                    "allias": {"type": "string"},
                    "name": {"type": "string"},
                    "blocks": {
                        "type": "array",
                        "description": "Array of blocks (h1, h2, text, img)",
                        "items": {
                            "type": "object",
                            "properties": {
                                "type": {"type": "string"},
                                "content": {"type": "string"}
                            }
                        }
                    }
                },
                "required": ["domain", "allias", "name", "blocks"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "page:read",
            "description": "Reads the PHP code of a page.",
            "parameters": {
                "type": "object",
                "properties": {"domain": {"type": "string"}, "allias": {"type": "string"}, "name": {"type": "string"}},
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
while step <= MAX_STEPS:
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

if step > MAX_STEPS:
    print(f"\n⚠️ Агент достиг лимита {MAX_STEPS} шагов. Принудительная остановка.")
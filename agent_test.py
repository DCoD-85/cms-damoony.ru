import json
import subprocess
import urllib.request

OLLAMA_URL = "http://localhost:11434/api/chat"
MODEL_NAME = "gemma4:26b"

messages = [
    {
        "role": "system", 
        "content": "You are an AI managing the Damoony Engine. First, create the domain 'damoony3test.ru', alias 'coffee', and page 'about'. Then, use 'page:write' to add a heading 'About Coffee' and text 'Best coffee in town'. Finally, use 'page:read' to verify the code."
    },
    {
        "role": "user", 
        "content": "Build the coffee shop site and fill the 'about' page with content."
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
            "description": "Writes content to a page. JSON must be an array of blocks: [{\"type\": \"h1\", \"content\": \"Title\"}, {\"type\": \"text\", \"content\": \"Hello\"}]",
            "parameters": {
                "type": "object",
                "properties": {
                    "domain": {"type": "string"},
                    "allias": {"type": "string"},
                    "name": {"type": "string"},
                    "json": {"type": "string", "description": "JSON string of blocks"}
                },
                "required": ["domain", "allias", "name", "json"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "page:read",
            "description": "Reads the PHP code of a page to verify it.",
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
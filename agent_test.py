import json
import subprocess
import urllib.request
import re

OLLAMA_URL = "http://localhost:11434/api/chat"
MODEL_NAME = "qwen2.5-coder:7b"
MAX_STEPS = 3

ALLOWED_COMMANDS = ['domain:new', 'allias:new', 'page:new', 'page:write']

messages = [
    {
        "role": "system", 
        "content": "You are a strict AI agent. Use ONLY these commands: 'domain:new', 'allias:new', 'page:new', 'page:write'. Respond with ONE valid JSON array of actions. Do not explain, just output the JSON."
    },
    {
        "role": "user", 
        "content": """Create a coffee shop with 3 interlinked pages. Add CSS classes and inline styles for design.
1. domain:new 'damoony3test.ru'
2. allias:new 'damoony3test.ru', name 'coffee'
3. page:new for 'about', 'menu', 'contacts' (all in coffee alias)
4. page:write for each page:
   - about: blocks=[
       {type:'h1', content:'About Us', class:'hero-title', style:'color: #6b3e26; font-size: 2.5em; text-align: center;'},
       {type:'text', content:'Founded in 2024 with a passion for great coffee.', style:'text-align: center; font-size: 1.2em;'},
       {type:'link', url:'/menu', content:'Our Menu', class:'nav-link', style:'display:inline-block; margin-right:20px; padding:10px; background:#6b3e26; color:#fff; text-decoration:none;'},
       {type:'link', url:'/contacts', content:'Contact Us', class:'nav-link', style:'display:inline-block; padding:10px; background:#6b3e26; color:#fff; text-decoration:none;'}
     ]
   - menu: blocks=[
       {type:'h1', content:'Our Menu', style:'text-align:center; border-bottom: 2px solid #6b3e26;'},
       {type:'ul', items:['Espresso - $3','Latte - $4','Cappuccino - $4.5'], class:'menu-list', style:'line-height:2; font-size:1.2em; list-style-type: none;'},
       {type:'link', url:'/about', content:'About Us', style:'display:inline-block; margin-right:20px; color:#6b3e26;'},
       {type:'link', url:'/contacts', content:'Contacts', style:'display:inline-block; color:#6b3e26;'}
     ]
   - contacts: blocks=[
       {type:'h1', content:'Contact Us', style:'text-align:center;'},
       {type:'text', content:'hello@damoony.coffee', style:'font-size:1.5em; color:#0066cc; text-align:center;'},
       {type:'link', url:'/about', content:'About Us', class:'nav-link', style:'display:inline-block; margin-right:20px;'},
       {type:'link', url:'/menu', content:'Our Menu', class:'nav-link', style:'display:inline-block;'}
     ]
"""
    }
]

tools = [
    {
        "type": "function", 
        "function": {
            "name": "domain:new", 
            "description": "Creates a domain", 
            "parameters": {"type": "object", "properties": {"domain": {"type": "string"}}, "required": ["domain"]}
        }
    },
    {
        "type": "function", 
        "function": {
            "name": "allias:new", 
            "description": "Creates an alias", 
            "parameters": {"type": "object", "properties": {"domain": {"type": "string"}, "name": {"type": "string"}}, "required": ["domain", "name"]}
        }
    },
    {
        "type": "function", 
        "function": {
            "name": "page:new", 
            "description": "Creates a page", 
            "parameters": {"type": "object", "properties": {"domain": {"type": "string"}, "allias": {"type": "string"}, "name": {"type": "string"}}, "required": ["domain", "allias", "name"]}
        }
    },
    {
        "type": "function", 
        "function": {
            "name": "page:write", 
            "description": "Writes blocks to a page. Each block supports optional 'class', 'id', 'style' fields.",
            "parameters": {
                "type": "object", 
                "properties": {
                    "domain": {"type": "string"}, 
                    "allias": {"type": "string"}, 
                    "name": {"type": "string"}, 
                    "blocks": {
                        "type": "array", 
                        "items": {
                            "type": "object",
                            "properties": {
                                "type": {"type": "string", "enum": ["h1", "h2", "h3", "text", "img", "button", "link", "ul"]},
                                "content": {"type": "string"},
                                "url": {"type": "string"},
                                "items": {"type": "array", "items": {"type": "string"}},
                                "class": {"type": "string", "description": "CSS class(es)"},
                                "id": {"type": "string"},
                                "style": {"type": "string", "description": "Inline CSS"},
                                "alt": {"type": "string", "description": "Alt text for images"}
                            },
                            "required": ["type"]
                        }
                    }
                }, 
                "required": ["domain", "allias", "name", "blocks"]
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
    with urllib.request.urlopen(req, timeout=180) as resp:
        return json.loads(resp.read().decode("utf-8"))

print(f"Запуск агента ({MODEL_NAME})...\n")

step = 1
while step <= MAX_STEPS:
    print(f"--- Шаг {step} ---")
    
    payload = {"model": MODEL_NAME, "messages": messages, "tools": tools, "stream": False, "options": {"temperature": 0}}
    
    try:
        data = call_ollama(payload)
    except Exception as e:
        print(f"Ошибка: {e}")
        break
        
    message = data.get("message", {})
    content = message.get("content", "")
    tool_calls = message.get("tool_calls")
    
    # Парсим JSON, если он пришел текстом
    if not tool_calls and content:
        try:
            plan = json.loads(content.strip())
            if isinstance(plan, list):
                tool_calls = [{"function": {"name": a["name"], "arguments": a["arguments"]}} for a in plan if "name" in a]
        except json.JSONDecodeError:
            match = re.search(r'\[.*\]', content, re.DOTALL)
            if match:
                try:
                    plan = json.loads(match.group(0))
                    if isinstance(plan, list):
                        tool_calls = [{"function": {"name": a["name"], "arguments": a["arguments"]}} for a in plan if "name" in a]
                except json.JSONDecodeError:
                    print("[!] Не удалось распарсить JSON от агента.")

    if not tool_calls:
        print("\nФИНАЛЬНЫЙ ОТВЕТ АГЕНТА:")
        print(content)
        break
        
    messages.append({"role": "assistant", "content": content, "tool_calls": tool_calls})
    
    all_success = True
    for tool_call in tool_calls:
        cmd_name = tool_call["function"]["name"]
        cmd_args = tool_call["function"]["arguments"]
        
        if cmd_name not in ALLOWED_COMMANDS:
            print(f"[!] Агент выдумал команду '{cmd_name}'. Пропускаем.")
            all_success = False
            messages.append({"role": "tool", "content": "Error: Unknown command.", "name": cmd_name})
            continue
            
        print(f"[AI] Вызывает: {cmd_name}")
        
        php_input = json.dumps({"command": cmd_name, "args": cmd_args})
        result = subprocess.run(
            ["php", "bin/aicli.php"],
            input=php_input, text=True, capture_output=True, encoding='utf-8'
        )
        
        out = result.stdout.strip()
        print(f"[Safe-CLI] Ответ: {out[:100]}...")
        
        if '"ok":false' in out and 'уже существует' not in out and 'already exists' not in out:
            all_success = False
            
        messages.append({"role": "tool", "content": out, "name": cmd_name})
        
    if all_success:
        print("\n✅ Все команды выполнены. Завершаем.")
        break
        
    step += 1

if step > MAX_STEPS:
    print(f"\n⚠️ Лимит шагов ({MAX_STEPS}) достигнут.")
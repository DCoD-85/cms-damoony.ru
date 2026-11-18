# Минимальная развертка (`new --assembly=minimal`)

Контракт того, что получается «из коробки» после установки проекта. Источник —
текущий `app/damoony.ru/www` (витрина), собранный в `stubs/assemblies/minimal`.

## Что входит

| Часть | Состав |
|---|---|
| Блоки (глобальные) | `top`, `left`, `center`, `right`, `bottom`, `footer`, `policy`, `toast` |
| Схема | `.schemesglobal/www.php` — слоты top/left/center/right/bottom/footer/policy/toast |
| Страницы | `/` (редирект на язык), `/{lang}/`, `about`, `login`, `logout`, `profile`, `policy` + `accept` + `ajax`, `skin`, `registration` |
| Скины | `light`, `dark` — полный набор CSS (базовые + на слот) |
| i18n | `ru`, `en` в `.i18nglobal` |
| БД | `.dbglobal/schema.json` (нужна для авторизации/регистрации) |

## Поведение по умолчанию

- корень `/` → 301 на `/{default_lang}/`;
- `top`: логотип (`site.name`), меню Home/About, справа — Login/Registration или Profile/Logout;
- языковой свитчер: активный язык определяется **по URL**, а не по cookie;
- свитчер скинов: активный скин — реально выбранный, список из файлов скина;
- `left`: простое меню Home/About + Profile (для авторизованных) или Login/Registration;
- `policy`: cookie-баннер, работает и без JS (`/{lang}/policy/`, accept/decline через `/policy/accept` и AJAX);
- `registration`: валидация логина/почты/пароля (≥ 8 символов), CSRF, создание пользователя, авто-вход, редирект в профиль;
- `login`: CSRF, AJAX-ответ, редирект в профиль; ошибка — flash;
- `profile`: требует авторизации, иначе редирект на login.

## Чего нет

- страниц `tests/*` и провайдеров (это демо-сборка `developer`);
- админки — ставится отдельно, сборкой;
- локализации текстов страниц дальше `ru/en`.

## Проверка сборки

Автотест `_dev/tests/Unit/Cli/AssemblyStubsTest`:

1. `assembly.json` валиден;
2. каждый `.php` парсится после подстановки `{{namespace}}/{{real}}/{{alias}}/{{domain}}`;
3. каждый `.json` валиден;
4. правило 3 файлов для всех страниц (`algoritm/_*.php`) и блоков.

Живая проверка (локально, `new --assembly=minimal --domain=…`):

```
/                        301 → /ru/
/ru/, /en/, /ru/about/   200
/ru/policy/              200
/ru/login/               200
/ru/registration/        200, POST → 302 /ru/profile/
/ru/profile/             после регистрации 200 с логином и выходом
```

## Языки в сборке

`assembly.json`:

```json
{
    "name": "Minimal",
    "langs": {
        "project": ["ru", "en"],
        "default": "ru",
        "aliases": { "www": ["ru", "en"] }
    }
}
```

- `langs` нет — прежние `ru,en` (обратная совместимость);
- `default` входит в `project`, в конфиг пишется первым;
- языки алиаса ⊆ языков проекта;
- для каждого языка проекта обязателен `app/.i18nglobal/{lang}.json` (иначе `new` падает до записи);
- оверрайды: `new --assembly=minimal --langs=ru,en --default-lang=ru`.

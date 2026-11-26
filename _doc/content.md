# Контент-админка

URL: `/ru/content/`.

## Почему `/` не редактируется

Корень сайта — `algoritm.php`, он делает редирект на `/{lang}/`. Поэтому в дереве его нет.

- главная страница языка — путь `lang`;
- обычная страница — `lang/{name}`.

## Создать страницу

```bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=promo
```

Создаёт:

```
app/example.ru/www/algoritm/promo/algoritm_promo.php
app/example.ru/www/algoritm/promo/algoritm_promo.rules.json
app/example.ru/www/algoritm/promo/algoritm_promo.editor.json
```

Открыть в админке:

```
/ru/content/?project=example.ru&alias=www&path=promo
```

URL страницы: `/ru/promo/`.

## JSON-блоки

```json
[
    { "type": "h1", "content": "Промо" },
    { "type": "text", "content": "Описание страницы" },
    { "type": "button", "content": "Связаться", "url": "/ru/about/", "class": "btn" }
]
```

Доступные типы: `h1–h6`, `text/p`, `img`, `link`, `button`, `ul`, `nav`, `section/div`, `table`.

Поля: `type`, `content`, `class`, `id`, `style`, `url`, `alt`, `items`, `button_type`, `children`, `headers`, `rows`.

## Автодополнение

- `Ctrl+Space` — подсказки;
- после `"`, `:`, `.` — автоматически;
- JSON: типы, поля, методы;
- PHP: методы провайдеров (`user`, `news`, `files`, `audit`, `form_submission` + кастомные).

## PHP-режим

Для страниц без JSON-блоков доступен PHP-редактор:

- только `superadmin`;
- синтаксис проверяется через `token_get_all`;
- перед записью создаётся бэкап в `var/backups/content`.

## Медиа

В редакторе страницы, блок «Медиа»:

- список файлов контента и скинов;
- загрузка: PNG/JPEG/GIF/WebP, до 25 MB, минимум 200×200;
- выбор назначения: `Контент` или `Скин: <name>`;
- кнопка «Вставить URL»;
- удаление с подтверждением.

Пути:

```
/content/{folder}/{alias}/upload/<hash>.<ext>
/media/{folder}/{alias}/skin/{skin}/img/<hash>.<ext>
```

## Блоки

`/ru/blocks/` — список global/local блоков, переключение и редактор JSON/PHP.
`/ru/domain/?tab=blocks` — блоки в рамках домена с разделением по алиасам.

## Ограничения

- корень `/` — только редирект;
- PHP-редактор — только `superadmin`, с бэкапом;
- медиа: без SVG;
- редактор схем/БД — отдельный шаг.

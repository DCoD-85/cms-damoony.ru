# Логические страницы и правила маршрутизации (Page Rules)

Документ описывает, как создавать страницы и настраивать их доступность. 
Обязателен к прочтению разработчикам и AI-агентам.

## 1. Три файла страницы
Каждая страница (включая корень) состоит из трех файлов:
1. algoritm_{name}.php — логика (класс с методом content()). Для вложенной страницы имя = `algoritm_{parent}_{name}` (например, `algoritm_lang_about.php` для `lang/about/`, `algoritm_about_about.php` для `about/about/`). Родитель — все сегменты пути через `_`.
2. algoritm_{name}.rules.json — правила маршрутизации и доступности.
3. algoritm_{name}.editor.json — данные для UI (визуальный редактор).

## 2. Структура .rules.json
Файл определяет, как движок (FileRouter) сопоставляет URL с файлами.

{
    "available": {
        "": "^$",
        "about": "^about$",
        "contacts": "^contacts$"
    },
    "auth": false
}

*   available: Ассоциативный массив (target => regex), где ключ — это имя папки или параметр, а значение — регулярное выражение.
    *   "": "^$" — файл обрабатывает свой собственный корень (например, для /visuo/).
    *   "about": "^about$" — файл разрешает переход на /visuo/about.
    *   "lang": "^([a-z]{2})$" — захват параметра из URL (например, /ru/). Значение попадет в аргумент lang.
*   auth: Метаданные (движок не защищает автоматически, страница должна сама вызвать $this->isAuthenticated()).

## 3. Логика FileRouter
1. Роутер берет URL (например, /visuo/about).
2. Читает algoritm.rules.json в корне алиаса.
3. Ищет сегмент about в ключах ассоциативного массива available и проверяет его по регулярке.
4. Если находит — спускается в папку about/ и ищет там algoritm_about.php и algoritm_about.rules.json.
5. Если в available встречается "" — роутер останавливается и вызывает content() текущего файла.

## 4. Правила для AI
*   Никогда не пишите default() в PHP — только protected function content(): Node|Response|null.
*   Для создания корневой страницы используйте CLI: php bin/cli.php page:write --domain=X --allias=Y --name=__root__ --json='[...]'.
*   CLI генерирует для корня `"": "^$"` в `available` (ассоциативный формат target → regex).

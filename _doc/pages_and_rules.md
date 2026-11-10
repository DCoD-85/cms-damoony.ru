# Логические страницы и правила маршрутизации (Page Rules)

Документ описывает, как создавать страницы и настраивать их доступность. 
Обязателен к прочтению разработчикам и AI-агентам.

## 1. Три файла страницы
Каждая страница (включая корень) состоит из трех файлов:
1. algoritm_{name}.php — логика (класс с методом content()).
2. algoritm_{name}.rules.json — правила маршрутизации и доступности.
3. algoritm_{name}.editor.json — данные для UI (визуальный редактор).

## 2. Структура .rules.json
Файл определяет, как движок (FileRouter) сопоставляет URL с файлами.

{
    "available": [""],
    "auth": false,
    "search": true,
    "default": false
}

*   available: Массив разрешенных URL-сегментов (дочерних путей).
    *   [""] — файл обрабатывает свой собственный корень (например, для /visuo/).
    *   ["about", "contacts"] — файл является родительским узлом и разрешает переходы на /visuo/about и /visuo/contacts.
*   auth: Метаданные (движок не защищает автоматически, страница должна сама вызвать $this->isAuthenticated()).
*   search: Метаданные для поиска.

## 3. Логика FileRouter
1. Роутер берет URL (например, /visuo/about).
2. Читает algoritm.rules.json в корне алиаса.
3. Ищет сегмент about в массиве available.
4. Если находит — спускается в папку about/ и ищет там algoritm_about.php и algoritm_about.rules.json.
5. Если в available встречается "" — роутер останавливается и вызывает content() текущего файла.

## 4. Правила для AI
*   Никогда не пишите default() в PHP — только protected function content(): Node|Response|null.
*   Для создания корневой страницы используйте CLI: php bin/cli.php page:write --domain=X --allias=Y --name=__root__ --json='[...]'.
*   CLI автоматически генерирует правильный available: [""] для корня.

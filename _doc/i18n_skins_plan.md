--- НАЧАЛО ДОКУМЕНТА ---

I18n и Skins — статус
Этот документ больше не является источником истины. Актуальный статус I18n и скинов — в MANIFEST.md §4 (🟡). Ниже — краткая выжимка и ссылки, чтобы не дублировать.

I18n (мультиязычность)
Статус: 🟡 частично (MANIFEST.md §4).

Что есть:

LanguageNegotiator (URL → Cookie → Конфиг).

LanguagePathMiddleware (первый сегмент URL), LanguageCookieMiddleware.

DomainContext->language.

Админка: RU/EN/ZH, cookie admin_lang, Translator с fallback.

Чего нет (для ✅):

UI-переключателя языка на сайте.

Ключей site.* в lang/{ru,en,zh}.php для контента.

hreflang (SEO).

Где смотреть: src/I18n/, lang/, MANIFEST.md §4.

Skins (визуальные темы)
Статус: 🟡 частично (MANIFEST.md §4).

Что есть:

SkinManager (сканирует www/media/<domain>/skin/).

SkinCookieMiddleware.

Автоподключение main.css/main.js активного скина в PageHandler::applySkin().

Валидация через domains_skins_allowed в site.config.

Чего нет (для ✅):

UI-менеджера скинов в админке.

Переключателя скинов на фронте.

Ограничение: PageHandler::applySkin() читает $_COOKIE['skin'] напрямую, минуя SkinCookieMiddleware (MANIFEST.md §6.20, P2). Валидация дублируется.

Где смотреть: src/Skin/, www/media/<domain>/skin/, MANIFEST.md §4.

Roadmap
Цели — в MANIFEST.md §12 «Видение»:

I18n: UI-переключатель, site.*, hreflang.

Skins: UI-менеджер в админке, переключатель на фронте.

Почему этот документ сокращён
Раньше здесь был подробный план (архитектура, middleware, приоритеты). Но он дублировал MANIFEST.md §4 и §12 и устаревал быстрее, чем обновлялся. Теперь истина — в MANIFEST.md. Этот файл оставлен как краткая выжимка для тех, кто ищет именно I18n/Skins и не хочет читать весь MANIFEST.

--- КОНЕЦ ДОКУМЕНТА ---
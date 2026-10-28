--- НАЧАЛО ДОКУМЕНТА ---

Сценарии проекта Damoony
Ключевые сценарии (Use Cases), которые проверяют работоспособность архитектуры. Сгруппированы по слоям: структура (CLI/файлы), рендеринг (frontend), безопасность, вывод (AJAX), Low-Code, диагностика.

Легенда:

✅ — работает как описано (подтверждено кодом).

🟡 — работает с оговорками (см. ссылку в MANIFEST.md).

🔴 — сломан/не реализован на момент аудита (P0/P1).

Тесты: _dev/tests/Unit/ и _dev/tests/Scenarios/. 245 тестов, 542 assertions, 48 файлов (верифицировано). Запуск: php vendor/bin/phpunit -c _dev/phpunit.xml.

Сценарии управления структурой (CLI и файловая система)
S01. Создание домена — ✅
text
php bin/cli.php domain:new --domain=example.ru --namespace=example
Ожидание: создана папка app/example.ru/, алиас www, обновлён config/site.config (domains_real, domains_namespace, alliases_real).

Фактически: ✅ работает. Алиас www создаётся автоматически.

Оговорка: для IP-хоста (например 192.168.1.10) — работает; для IP с портом (192.168.1.10:8080) — не сработает из-за отсутствия нормализации Host (MANIFEST.md §6.17, P0).

S02. Создание дополнительного алиаса — ✅
text
php bin/cli.php allias:new --domain=example.ru --name=doc
Ожидание: создана app/example.ru/doc/algoritm/, обновлён site.config (alliases_real).

Фактически: ✅ работает.

Оговорка: на один IP-хост без DNS доступен только один алиас (www). Алиас определяется по Host, не по пути.

S03. Создание глобального блока — ✅
text
php bin/cli.php block:new --domain=example.ru --name=top --type=ui
Ожидание: создана папка app/example.ru/.alliasglobal/top/ с top.php и top.data.php, в blocks.json добавлена запись с типом ui.

Фактически: ✅ создаётся.

Оговорка: blocks.json и top.data.php не читаются движком (MANIFEST.md §4). Тип блока в рантайме не используется. Источник истины — папка .alliasglobal/top/.

S04. Создание филлера — ✅
text
php bin/cli.php filler:new --domain=example.ru --allias=www --block=top
Ожидание: созданы top_www.php и top_www.data.php в папке блока, в site.config добавлен fillers (включён).

Фактически: ✅ работает.

Оговорка: top_www.data.php не читается движком (§4). Данные — в top_www.php.

S05. Создание страницы — 🟡
text
php bin/cli.php page:new --domain=example.ru --allias=www --name=about
Ожидание: создана папка about/ с algoritm_about.php и .rules.json.

Фактически: ✅ создаётся.

Оговорка (важно): algoritm_about.php не рендерит схему — шапки/футера/регионов не будет. Это отличается от визуально сохранённой страницы (MANIFEST.md §6.1, P1). См. guide.md §4 — два варианта ручной страницы.

S06. Создание глобальной схемы — ✅
text
php bin/cli.php scheme:new --domain=example.ru --name=default
Ожидание: создан default.data.php в .alliasscheme/, обновлён schemes.json.

Фактически: ✅ создаётся.

Оговорка: ✅ Закрыто (stage-1): SchemeCompiler использует чистый var_export без str_replace. Строки со скобками сохраняются. Регрессия покрыта SchemeCompilerTest.

S07. Очистка карантина — ✅
text
php bin/cli.php trash:clear --force --older=7d
Ожидание: удалены папки trash, старше 7 дней.

Фактически: ✅ работает. По умолчанию — dry-run (только показывает).

Сценарии рендеринга и роутинга (Frontend)
S08. Успешный рендеринг главной страницы — ✅
Вход: GET http://example.ru/

Шаги: DomainResolver → FileRouter → PageHandler → SchemeRenderer.

Ожидание: HTTP 200, HTML-документ, собранный из слотов схемы.

Фактически: ✅ работает только для скомпилированных страниц (PageCompiler) или страниц, наследующих HomePage, или явно вызывающих SchemeRenderer. Для ручной страницы без схемы — 200, но без шапки/футера (§6.1).

S09. Рендеринг 404 — ✅
Вход: GET http://example.ru/unknown-path

Ожидание: HTTP 404, HTML-страница ошибки.

Фактически: ✅ работает. ErrorPage::notFound() через Builder.

Оговорка: ErrorPage не подключает скин (main.css) — стили классов .error-404 должны быть в глобальном CSS, иначе страница «голая».

S10. Режим обслуживания (503) — ✅
Вход: GET http://example.ru/ при "domains_available": false.

Ожидание: HTTP 503, HTML-заглушка из stubs/.

Фактически: ✅ работает. FrontController загружает stubs/<stub>.html или ErrorPage::maintenance().

S11. Проверка статуса алиаса — ✅
Вход: GET http://doc.example.ru/ при "alliases_available": {"example.ru": {"doc": false}}.

Ожидание: HTTP 503 для doc, www работает.

Фактически: ✅ работает — резолвер корректно разделяет available для домена и алиаса.

S12. Доступ к админке при выключенном сайте — ✅
Вход: GET http://example.ru/admin/ при "domains_available": false.

Ожидание: админка открывается (игнорирует 503).

Фактически: ✅ работает — FrontController проверяет str_starts_with($request->path, '/admin') и не отдаёт заглушку. www/admin/index.php — отдельная точка входа, не через FrontController.

Сценарии безопасности и авторизации
S13. Защита страницы авторизацией — ✅
Вход: GET http://example.ru/about, где .rules.json: "auth": true.

Ожидание: HTTP 302 → /login?return=/about.

Фактически: ✅ работает. FrontController проверяет $match->auth и редиректит.

S14. Успешный вход (Login) — ✅
Вход: POST /login с CSRF-токеном, логином, паролем.

Ожидание: HTTP 302 → исходная страница, пользователь авторизован.

Фактически: ✅ работает — DbAuth::attempt() + SessionAuth + CSRF.

S15. Защита от CSRF — ✅
Вход: POST /login без CSRF-токена.

Ожидание: HTTP 403.

Фактически: ✅ работает.

Оговорка: destructive-эндпоинты (SchemeController::delete/copy, BlockController::remove, StubController::remove) теперь тоже требуют POST+CSRF (stage-2b). GET → 405, POST без токена → 403.

S16. Управление привязками доменов — ✅
Вход: в админке добавить example.online → example.ru.

Ожидание: domains_real["example.online"] = "example.ru"; example.online открывает тот же сайт.

Фактически: ✅ работает.

Оговорка: порт и trailing-dot не нормализуются (§6.17) — для example.online:8080 не сработает.

Сценарии вывода (AJAX)
S17. Ответ в формате JSON (Min) — ✅
Вход: GET /api-endpoint с X-Requested-With: XMLHttpRequest.

Шаги: контроллер возвращает MinStruct.

Ожидание: HTTP 200, Content-Type: application/json, поля error, messages, release, form.

Фактически: ✅ работает. $this->isAjax() в Algoritm.

S18. Ответ в формате JSON (Ajax Layout) — ✅
Вход: аналогично S17.

Шаги: контроллер возвращает AjaxStruct.

Ожидание: JSON с полями error, address, layers (dt_top, dt_center и т.д.).

Фактически: ✅ работает.

Сценарии Low-Code и Админки
S19. Визуальное редактирование страницы — 🟡
Вход: «Визуально» у страницы, выбрать слот center (статус local), добавить блок H1, «Сохранить».

Шаги: PageController::save() → .edit.json ({slots}) → PageCompiler.

Ожидание: algoritm_about.php перезаписан; на сайте новый H1.

Фактически: 🟡 закрыто частично (stage-1+2a): addslashes больше не ломает контент, legacy .edit.json блокируется с предупреждением. Осталось: входящий хост (damoony3.ru) — пустые слоты (§6.16, P1).

S20. Системная диагностика (Structure Check) — 🟡
Вход: php bin/cli.php structure:check или «Обзор» в админке.

Ожидание: список ошибок или «Все проверки пройдены».

Фактически: 🟡 проверяет наличие файлов, схем, блоков, стыковку филлер↔слот. Не ловит .edit.json ↔ PHP, legacy .edit.json, blocks.json ↔ папки, schemes.json ↔ файлы (§7.2 п.3, P1). «Сироты» (visuo.data.php без записи в schemes.json) — не диагностируются.

S21. Безопасная смена пароля — ✅
Вход: профиль админки → текущий пароль, новый, подтверждение.

Ожидание: пароль изменён, в логах зафиксировано, при повторном входе требуется новый.

Фактически: ✅ работает. ProfileController → admin:passwd (эквивалент).

Сценарии, добавленные по итогам аудита
Сценарии ниже не покрыты тестами и выявлены при аудите. Их стоит превратить в тесты, чтобы закрыть регрессии.

S22. Развёртывание по IP — 🟡
Вход: GET http://192.168.1.10/, domains_real["192.168.1.10"] = "example.ru".

Ожидание: сайт открывается.

Фактически: 🟡 работает без порта. С портом (192.168.1.10:8080) — 404 из-за отсутствия нормализации Host (§6.17, P0). Трейлинг-точка (example.ru.) — тоже не матчится.

S23. Визуальное сохранение схемы с вложенностью — 🔴
Вход: открыть visuo.data.php (вложенная схема dt_middle → dt_left/dt_center/dt_right) в визуальном редакторе, сохранить.

Ожидание: вложенность сохранена.

Фактически: 🔴 схема схлопывается в плоский div.layout (§6.15, P1). Вложенность теряется без предупреждения.

S24. Визуальное сохранение страницы со старым .edit.json — ✅ (defensive, no direct test)
Вход: about/.edit.json содержит {"blocks": [...]} (старый формат), открыть «Визуально», сохранить.

Ожидание: контент сохранён.

Фактически: ✅ закрыто (stage-2a): PageController::edit() и save() блокируют legacy {blocks} с предупреждением; about/.edit.json удалён. Визуальное сохранение не может затереть ручной PHP. Автомиграция {blocks} → {slots} не реализована.

S25. default(): null — 🔴
Вход: ручная страница возвращает null из default().

Ожидание (по контракту AlgoritmInterface): что-то осмысленное (пусто, 204).

Фактически: 🔴 RuntimeException → 500 (§6.18, P1). Контракт разрешает null, runtime ломает.

S26. CSRF-атака на удаление схемы — ✅ (defensive, no direct test)
Вход: залогиненный админ открывает сторонний сайт, тот делает GET /admin/?p=scheme:delete&domain=example.ru&name=www.

Ожидание: операция отклонена (CSRF).

Фактически: ✅ закрыто (stage-2b): SchemeController::delete/copy и BlockController::remove требуют POST+CSRF. GET → 405, POST без токена → 403. StubController::remove также защищён (валидация имени).

Покрытие тестами
Сценарий	Покрыт	Где
S01–S07	✅	_dev/tests/Unit/Cli/
S08–S12	✅	_dev/tests/Unit/App/, Http/
S13–S16	✅	_dev/tests/Unit/Admin/AuthTest.php, Security/CsrfTest.php
S17–S18	✅	_dev/tests/Unit/Response/
S19	🟡	нет прямого теста PageController::save()
S20	🟡	нет StructureCheckerTest
S21	✅	_dev/tests/Unit/Admin/
S22–S23, S25	🔴	нет тестов — добавить
S24, S26	✅ (defensive)	нет прямого теста; защита через review
Пробелы в тестах (P1, MANIFEST.md §7.3 п.10):

StructureCheckerTest — «сироты» в schemes.json, legacy .edit.json.

PageHandlerTest — схема по умолчанию, default(): null.

ConfigDomainResolverTest — порт, trailing-dot (частично покрыт; расширить).

✅ PageCompilerTest, RegionCompilerTest, SchemeCompilerTest — закрыты в stage-1 (контент с ', \, \n, $, (, )).

Эти тесты — обязательное условие закрытия P1: без них фиксы вернутся.

Итог
Работает как задумано (✅): 19 из 26 сценариев.

Частично (🟡): 4 (S05, S19, S20, S22).

Сломано (🔴): 3 (S23, S25 — реальные, S22 — при порте).

Stage-1+2a+2b закрыли S06, S24, S26 (было 🔴/🟡). Осталось stage-3: Host (§6.17), getMessage (§7.1 п.3). После stage-3 и P1 — 90%+.

Подробнее — MANIFEST.md §7 (P0/P1/P2). О командах — cli.md. Об админке — admin.md.

--- КОНЕЦ ДОКУМЕНТА ---
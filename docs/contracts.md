# Контракты проекта Damoony

Источник: docs/philosophy.md, _devdoc/_auditfinal/.
Пять контрактов, которые переносятся из старого проекта в новый движок.
Код не переносится. Переносятся сигнатуры, инварианты и границы ответственности.
Каждый контракт — интерфейс. Реализация — отдельный модуль.

---

## 1. DomainResolver

Назначение: определить текущий домен и алиас по запросу, дать доступ к конфигу домена.

Методы:
- resolve(Request): DomainContext
- exists(string domain): bool
- real(string domain): string
- namespace(string domain): string

DomainContext (value object, readonly):
- domain: string (входной домен)
- real: string (канонический домен)
- allias: string
- logic: string
- skin: string
- language: string
- available: bool
- stub: ?string

Инварианты:
- resolve идемпотентен в рамках запроса.
- real(domain) для неизвестного домена возвращает входной domain.
- available=false не бросает исключение, а помечает контекст; решение — за вызывающим.
- DomainResolver не знает про БД, регионы, UI.

Не входит:
- загрузка конфигов (Config)
- маршрутизация (Router)
- выбор схемы (Scheme)

Источник: _auditfinal/05, _auditfinal/30, _auditfinal/32.

---

## 2. Region

Назначение: единица шаблона домена (top, left, center, right, bottom, policy, debug и любые пользовательские).

Методы:
- name(): string
- render(Context): Node
- enabled(): bool

Filler (расширение Region для конкретного алиаса):
- appliesTo(DomainContext): bool
- render(Context): Node

Реестр регионов:
- add(string name, callable factory): void
- get(string name): Region
- has(string name): bool
- all(): array

Инварианты:
- Регион не знает о других регионах.
- Регион возвращает Node, не строку.
- Filler имеет приоритет над базовым регионом.
- Реестр не хранит состояние между запросами.

Не входит:
- сборка страницы (Scheme)
- рендер в HTML (Renderer)
- маршрутизация

Источник: _auditfinal/34, _auditfinal/35.

---

## 3. Router

Назначение: по URL и контексту домена найти обработчик страницы.

Методы:
- match(Request, DomainContext): MatchResult
- MatchResult: found: bool, handler: ?callable, args: array, code: int

Правила:
- правила хранятся в .rules.json рядом со страницей
- available: список сегментов или regex
- auth: bool
- обработчик — callable, возвращающий Node или Response

Инварианты:
- match не выполняет обработчик.
- match не бросает исключение на 404, возвращает found=false.
- Приоритет: точное совпадение, затем regex, затем пустой сегмент.
- Router не знает про UI, регионы, БД.

Не входит:
- выполнение страницы
- рендер ошибок
- auth-проверка (только флаг)

Источник: _auditfinal/08.

---

## 4. UiNode

Назначение: узел дерева представления. Заменяет visuotag/visuoel концептуально, но не кодом.

Базовый узел:
- tag(): string
- attrs(): Attrs
- children(): array
- append(Node): static
- prepend(Node): static
- parent(): ?Node
- release(): string

Attrs:
- add(string key, string value, bool empty=false): static
- get(string key): ?string
- has(string key): bool
- class(): ClassSet
- style(): StyleSet
- data(): DataSet

Составные узлы (по контракту, не по реализации):
- Element (базовый)
- Group (прозрачный контейнер)
- Region (в контракте Region, но результат — Node)
- Form, Field, Button, Table, Tabs, Message, Block

Инварианты:
- Дерево иммутабельно после release().
- escape выполняется на уровне attrs/content, не на уровне рендера.
- Никаких глобальных состояний.
- Node не знает про домен, БД, роутер.

Не входит:
- сборка страницы (Scheme)
- HTTP-ответ
- кэширование

Источник: _auditfinal/25, _auditfinal/26, _auditfinal/27, _auditfinal/28, _auditfinal/29.

---

## 5. Scheme

Назначение: каркас страницы, собираемый из регионов. Определяет, какие регионы и в каком порядке попадут в итоговый документ.

Методы:
- load(string name): self
- render(Context): Node
- regions(): array

Манифест схемы (декларативно, не PHP-код):
- tag: string
- class: string
- layout: horizontal|vertical
- children: array of nodes
- каждый child: tag, class, type (block|layout), enabled, return

Инварианты:
- Схема — данные, не код.
- Схема не знает о конкретных регионах, только о их именах.
- Порядок детей определяет порядок в документе.
- enabled=false исключает узел без ошибки.

Не входит:
- содержимое регионов
- HTTP
- рендер в HTML

Источник: _auditfinal/35.

---

## Границы между контрактами

- DomainResolver — до всего.
- Router — после DomainResolver, до выполнения обработчика.
- Обработчик возвращает UiNode.
- Scheme собирает UiNode из регионов.
- Region рендерит свою часть UiNode.
- UiNode ничего не знает о Router, Region, Scheme, Domain.

## Что НЕ входит ни в один контракт

- БД (отдельный контракт Repository).
- CLI (отдельный контракт Command).
- Admin (отдельный пакет).
- Config (инфраструктура, не домен).
- Logger (PSR-3, инфраструктура).

Эти контракты фиксируются отдельно на этапах 1 и 3.
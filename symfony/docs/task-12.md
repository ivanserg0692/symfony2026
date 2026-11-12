# Task 12

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [RU](#ru)
  - [Статус](#%D1%81%D1%82%D0%B0%D1%82%D1%83%D1%81)
  - [Название](#%D0%BD%D0%B0%D0%B7%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5)
  - [Описание задачи](#%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
  - [Цель](#%D1%86%D0%B5%D0%BB%D1%8C)
  - [Критерии приёмки](#%D0%BA%D1%80%D0%B8%D1%82%D0%B5%D1%80%D0%B8%D0%B8-%D0%BF%D1%80%D0%B8%D1%91%D0%BC%D0%BA%D0%B8)
  - [Технический подход](#%D1%82%D0%B5%D1%85%D0%BD%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8%D0%B9-%D0%BF%D0%BE%D0%B4%D1%85%D0%BE%D0%B4)
  - [Как тестировать](#%D0%BA%D0%B0%D0%BA-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D1%82%D1%8C)
  - [Примечания](#%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%87%D0%B0%D0%BD%D0%B8%D1%8F)
- [EN](#en)
  - [Status](#status)
  - [Title](#title)
  - [Task Description](#task-description)
  - [Goal](#goal)
  - [Acceptance Criteria](#acceptance-criteria)
  - [Technical Approach](#technical-approach)
  - [How To Test](#how-to-test)
  - [Notes](#notes)

<!-- END doctoc -->

## RU

### Статус

**Запланировано (`planned`).** На текущем этапе оформляется только документация задачи. Реализация фронтенда и проверка пользовательских сценариев ещё не выполнены.

### Название

Разработка фронтенда интернет-магазина.

### Описание задачи

Разработать пользовательский интерфейс интернет-магазина в отдельном репозитории: <https://github.com/ivanserg0692/symfony2026-frontend>.

Основной стек — **Next.js, React, Refine**. Фронтенд должен работать с существующим Symfony API через публичный API Gateway и поддерживать светлую и тёмную темы. Работа продолжает базовое frontend-приложение из [Task 6](task-6.md), развивая его для покупательских сценариев интернет-магазина.

В планируемый объём входят каталог товаров, карточка товара, поиск и доступные API фильтры, корзина, оформление заказа и просмотр собственных заказов. Авторизация и обработка ошибок должны соответствовать существующим API-контрактам.

### Цель

Создать единый адаптивный интерфейс, в котором пользователь может найти товар, изучить его, добавить в корзину и оформить заказ через существующий Symfony API в выбранной светлой или тёмной теме.

### Критерии приёмки

- Разработка ведётся в отдельном репозитории `symfony2026-frontend` на Next.js, React и Refine.
- Каталог и карточка товара показывают данные существующего API; поиск, фильтрация, сортировка и пагинация используют поддерживаемые API параметры.
- Пользователь может просмотреть корзину, добавить и удалить товар, изменить количество и увидеть актуальные данные API.
- Авторизованный пользователь может оформить заказ и просмотреть свои заказы в пределах существующих API-возможностей.
- Авторизация использует существующий контракт; защищённые действия корректно обрабатывают отсутствие или истечение авторизации.
- Светлая и тёмная темы доступны во всех основных разделах; пользователь может переключать тему, а выбранное значение сохраняется между посещениями.
- Интерфейс адаптирован для мобильных и настольных экранов; основные действия доступны с клавиатуры, текст и элементы управления читаемы в обеих темах.
- Для основных экранов предусмотрены состояния загрузки, отсутствия данных и ошибки API; ошибки валидации отображаются рядом с соответствующими полями.
- В frontend-репозитории описаны настройка подключения к API и проверка основных пользовательских сценариев.
- Выполнение этих критериев фиксируется в MR-файлах только после реализации и проверки.

### Технический подход

1. Проверить состояние существующего frontend-приложения из Task 6 и актуальные публичные контракты Auth, Catalog и Cart/Order перед реализацией.
2. Подготовить структуру страниц и общий layout на Next.js и React; использовать Refine для подключения ресурсов и работы с API.
3. Интегрировать интерфейс через публичный HTTP API `/api/v1` API Gateway. Данные товаров, цен, остатков, корзины и заказов получать из API; серверные проверки остаются на backend.
4. Использовать существующий механизм авторизации, включая cookie, CSRF и refresh там, где это предусмотрено действующим контрактом. Конкретные настройки подключения проверить перед реализацией.
5. Реализовать основные покупательские сценарии и общее оформление светлой и тёмной тем с сохранением выбора пользователя.
6. Проверить сценарии с работающим API и документировать фактический результат в отдельных frontend MR и MR-логах Task 12.

### Как тестировать

- Пройти каталог → карточка товара → корзина → оформление заказа → просмотр собственного заказа с работающим Symfony API.
- Проверить поиск, доступные фильтры, сортировку и пагинацию, включая пустой результат и отсутствие товара.
- Проверить изменение количества и удаление товаров из корзины, а также отображение ошибок бизнес-валидации API.
- Проверить вход, защищённые действия и истечение авторизации по существующему контракту.
- Повторить основные сценарии в светлой и тёмной темах, проверить переключение и сохранение выбора после перезагрузки страницы.
- Проверить мобильный и настольный интерфейс, навигацию с клавиатуры, загрузку данных, ошибки API и недоступность backend.
- Зафиксировать фактически выполненные проверки в MR-логах; этот документ содержит план, а не результаты тестирования.

### Примечания

- В текущем изменении создаётся только документация Task 12; frontend-код и backend-код не изменяются.
- Backend и frontend остаются отдельными репозиториями. Frontend не обращается напрямую к базам данных, Elasticsearch или внутреннему gRPC.
- Недостающие API-возможности и изменения контрактов согласуются отдельно; задача не предполагает их незаметного добавления.
- Платёжные интеграции, доставка и административный интерфейс не входят в базовый объём задачи.
- Backend Merge Request 12: TBD.
- Frontend Merge Request 12: TBD.
- MR-логи: [EN](mr-task-12-en.md), [RU](mr-task-12-ru.md).

## EN

### Status

**Planned (`planned`).** The current change only prepares task documentation. Frontend implementation and user-flow verification have not been completed.

### Title

Online store frontend development.

### Task Description

Develop the online store user interface in a separate repository: <https://github.com/ivanserg0692/symfony2026-frontend>.

The main stack is **Next.js, React, Refine**. The frontend must consume the existing Symfony API through the public API Gateway and support light and dark themes. This work continues the base frontend application from [Task 6](task-6.md), extending it to cover online store customer flows.

The planned scope includes the product catalog, product details, search and API-supported filters, cart, checkout, and viewing the customer's own orders. Authentication and error handling must follow existing API contracts.

### Goal

Create a consistent responsive interface where customers can find a product, inspect it, add it to the cart, and place an order through the existing Symfony API using their chosen light or dark theme.

### Acceptance Criteria

- Development takes place in the separate `symfony2026-frontend` repository using Next.js, React, and Refine.
- Catalog and product pages display existing API data; search, filtering, sorting, and pagination use API-supported parameters.
- Customers can view the cart, add and remove products, change quantities, and see current API data.
- Authenticated customers can place orders and view their own orders within existing API capabilities.
- Authentication follows the existing contract; protected actions handle missing or expired authentication correctly.
- Light and dark themes cover all main sections; customers can switch themes, and their selection persists between visits.
- The interface supports mobile and desktop screens; main actions are keyboard accessible, and text and controls remain readable in both themes.
- Main screens include loading, empty, and API error states; field validation errors appear next to the relevant fields.
- The frontend repository documents API connection setup and verification of the main user flows.
- These criteria are recorded as completed in MR logs only after implementation and verification.

### Technical Approach

1. Inspect the existing Task 6 frontend and current public Auth, Catalog, and Cart/Order contracts before implementation.
2. Prepare pages and a shared layout with Next.js and React; use Refine for resource integration and API access.
3. Integrate through the API Gateway's public HTTP API `/api/v1`. Obtain products, prices, stock, carts, and orders from the API; server-side validation remains with the backend.
4. Use the existing authentication mechanism, including cookies, CSRF, and refresh where required by the current contract. Verify connection settings before implementation.
5. Implement the main customer flows and shared light and dark theme styling with persistent theme selection.
6. Verify flows against a running API and document actual results in separate frontend MRs and Task 12 MR logs.

### How To Test

- Complete catalog → product details → cart → checkout → own order details against a running Symfony API.
- Check search, available filters, sorting, and pagination, including empty results and missing products.
- Check cart quantity changes and product removal, including API business-validation errors.
- Check login, protected actions, and expired authentication against the existing contract.
- Repeat the main flows in light and dark themes; verify switching and persistence after a page reload.
- Check mobile and desktop layouts, keyboard navigation, data loading, API errors, and backend unavailability.
- Record checks actually performed in the MR logs; this document contains a plan rather than test results.

### Notes

- The current change only creates Task 12 documentation; frontend and backend code are not changed.
- Backend and frontend remain separate repositories. The frontend does not access databases, Elasticsearch, or internal gRPC directly.
- Missing API capabilities and contract changes require separate agreement; the task does not implicitly add them.
- Payment integrations, shipping, and an administration interface are outside the base scope.
- Backend Merge Request 12: TBD.
- Frontend Merge Request 12: TBD.
- MR logs: [EN](mr-task-12-en.md), [RU](mr-task-12-ru.md).

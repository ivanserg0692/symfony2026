# Task 6

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [RU](#ru)
  - [Название](#%D0%BD%D0%B0%D0%B7%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5)
  - [Описание задачи](#%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
  - [Цель](#%D1%86%D0%B5%D0%BB%D1%8C)
  - [Критерии приемки](#%D0%BA%D1%80%D0%B8%D1%82%D0%B5%D1%80%D0%B8%D0%B8-%D0%BF%D1%80%D0%B8%D0%B5%D0%BC%D0%BA%D0%B8)
  - [Технический подход](#%D1%82%D0%B5%D1%85%D0%BD%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8%D0%B9-%D0%BF%D0%BE%D0%B4%D1%85%D0%BE%D0%B4)
  - [Как тестировать](#%D0%BA%D0%B0%D0%BA-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D1%82%D1%8C)
  - [Примечания](#%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%87%D0%B0%D0%BD%D0%B8%D1%8F)
- [EN](#en)
  - [Title](#title)
  - [Task Description](#task-description)
  - [Goal](#goal)
  - [Acceptance Criteria](#acceptance-criteria)
  - [Technical Approach](#technical-approach)
  - [How To Test](#how-to-test)
  - [Notes](#notes)

<!-- END doctoc -->

## RU

### Название
Frontend-приложение на refine для существующего API

### Описание задачи
На этом этапе требуется добавить отдельное frontend-приложение на React, Next.js, refine и Ant Design, которое работает с уже существующим backend API `/api/v1` как внешний клиент.

Symfony остается отдельным backend-приложением. Frontend не должен зависеть от внутренних Symfony-шаблонов, EasyAdmin или прямого доступа к backend-коду. Взаимодействие выполняется через HTTP API, cookie-based authentication и уже подготовленные публичные API-ручки.

В минимальный объем задачи войдут:
- создание отдельного frontend-приложения на refine
- подключение frontend к существующему `/api/v1`
- настройка REST `dataProvider`
- настройка авторизации через HttpOnly cookies без `Authorization` header
- поддержка CSRF для небезопасных API-запросов
- поддержка refresh access token при истечении access token
- добавление формы входа с Cloudflare Turnstile
- настройка локализации `ru` и `en`
- переключение локали через path
- подключение Ant Design и настройка светлой/темной темы
- добавление базового layout с меню, header и breadcrumbs
- реализация раздела новостей со списком и детальной страницей

### Цель
Подготовить отдельный frontend-клиент, который показывает, как существующий Symfony API можно использовать из внешнего React-приложения без прямой интеграции frontend в Symfony.

### Критерии приемки
- frontend находится в отдельном каталоге и может развиваться как отдельное приложение
- frontend использует существующий backend API `/api/v1`
- список новостей загружается через API
- детальная страница новости загружается через API по slug
- поиск новостей отправляет параметр `query` в API
- список новостей поддерживает пагинацию и сортировку
- состояние таблицы синхронизируется с URL
- сброс поиска очищает фильтр и URL
- список новостей можно переключать между table view и card view
- просмотр новости открывается через ссылку с клиентской навигацией Next.js
- публичные новости доступны без принудительного перехода на страницу входа
- форма входа использует Cloudflare Turnstile
- авторизация работает через HttpOnly cookies
- refresh access token используется при `401` на API-запросах, где это допустимо
- CSRF token переиспользуется после получения и обновляется только при необходимости
- интерфейс поддерживает локализацию `ru` и `en`
- локаль хранится в path, а не в query string
- интерфейс поддерживает светлую и темную темы Ant Design
- breadcrumbs отображаются один раз и не дублируются с refine-компонентами

### Технический подход
- использовать Next.js и React как основу frontend-приложения
- использовать refine для ресурсов, data provider, auth provider, i18n provider и интеграции с таблицами
- использовать Ant Design для базовых UI-компонентов
- подключить `@refinedev/antd`, `@refinedev/react-table` и связанные refine-пакеты по мере необходимости
- реализовать API client поверх `fetch` с `credentials: "include"`
- не добавлять `Authorization` header, потому что backend использует HttpOnly cookies
- держать CSRF token в client-side cache и переиспользовать последний полученный token
- при `401` выполнять refresh и повторять исходный запрос один раз
- использовать path-based locale routes вида `/ru/...` и `/en/...`
- хранить переводимые строки в локальных locale-файлах
- задавать ресурсы refine в `src/app/providers.tsx`
- отключать встроенные breadcrumbs refine-компонентов там, где общий layout уже выводит breadcrumbs

### Как тестировать
- открыть список новостей без авторизации и убедиться, что не происходит редирект на login
- проверить, что список новостей загружается из `/api/v1/news`
- выполнить поиск и убедиться, что в URL и API-запросе появляется `query`
- сбросить поиск и убедиться, что `query` исчезает из URL
- переключить сортировку по доступным колонкам и проверить обновление URL/API-запроса
- переключить размер страницы и страницы пагинации
- переключить отображение списка между таблицей и карточками
- открыть детальную страницу новости из таблицы и карточки
- проверить, что переход на детальную страницу выполняется без полной перезагрузки страницы
- открыть login form и проверить наличие Turnstile
- выполнить успешный login и убедиться, что authenticated state обновляется
- выполнить logout и убедиться, что frontend обновляет состояние авторизации
- проверить переключение локали через URL path
- проверить светлую и темную темы

### Примечания
- frontend intentionally remains an external client for the API, not a Symfony bundle or Twig integration
- generated refine CRUD/inferencer code can be useful for experiments, but production pages should be reviewed and simplified manually
- future work can add richer filters, notification UI, admin-specific screens, and stronger reusable UI abstractions
- if backend search semantics change, the frontend `dataProvider` should map refine filters to the new API contract explicitly

## EN

### Title
Refine frontend application for the existing API

### Task Description
At this stage, the project needs a separate frontend application built with React, Next.js, refine, and Ant Design. The frontend should use the existing backend API `/api/v1` as an external client.

Symfony remains a separate backend application. The frontend must not depend on internal Symfony templates, EasyAdmin, or direct access to backend code. Communication happens through HTTP API calls, cookie-based authentication, and the already prepared public API endpoints.

The initial scope includes:
- creating a separate refine frontend application
- connecting the frontend to the existing `/api/v1`
- configuring a REST `dataProvider`
- configuring authentication through HttpOnly cookies without an `Authorization` header
- supporting CSRF for unsafe API requests
- supporting access token refresh when the access token expires
- adding a login form with Cloudflare Turnstile
- configuring `ru` and `en` localization
- switching locale through the path
- adding Ant Design and light/dark theme support
- adding a base layout with menu, header, and breadcrumbs
- implementing the news section with list and detail pages

### Goal
Prepare a separate frontend client that demonstrates how the existing Symfony API can be consumed from an external React application without embedding the frontend into Symfony.

### Acceptance Criteria
- the frontend lives in a separate directory and can evolve as a separate application
- the frontend uses the existing backend API `/api/v1`
- the news list is loaded through the API
- the news detail page is loaded through the API by slug
- news search sends the `query` parameter to the API
- the news list supports pagination and sorting
- table state is synchronized with the URL
- search reset clears both the filter and the URL
- the news list can switch between table view and card view
- news detail opens through a link with Next.js client-side navigation
- public news are available without forcing a login redirect
- the login form uses Cloudflare Turnstile
- authentication works through HttpOnly cookies
- access token refresh is used after `401` responses where refresh is allowed
- the CSRF token is reused after being received and refreshed only when needed
- the UI supports `ru` and `en` localization
- locale is stored in the path instead of the query string
- the UI supports light and dark Ant Design themes
- breadcrumbs are shown once and are not duplicated with refine components

### Technical Approach
- use Next.js and React as the frontend application foundation
- use refine for resources, data provider, auth provider, i18n provider, and table integration
- use Ant Design for base UI components
- add `@refinedev/antd`, `@refinedev/react-table`, and related refine packages as needed
- implement the API client on top of `fetch` with `credentials: "include"`
- do not add an `Authorization` header because the backend uses HttpOnly cookies
- keep the CSRF token in a client-side cache and reuse the latest received token
- after `401`, run refresh and retry the original request once
- use path-based locale routes such as `/ru/...` and `/en/...`
- keep translatable strings in local locale files
- define refine resources in `src/app/providers.tsx`
- disable built-in refine component breadcrumbs where the shared layout already renders breadcrumbs

### How To Test
- open the news list without authentication and verify that it does not redirect to login
- verify that the news list loads from `/api/v1/news`
- run a search and verify that `query` appears in the URL and API request
- reset search and verify that `query` is removed from the URL
- change sorting on supported columns and verify the URL/API request update
- change page size and pagination pages
- switch the news list between table and card views
- open the news detail page from both table and card views
- verify that detail navigation happens without a full page reload
- open the login form and verify that Turnstile is present
- complete a successful login and verify that the authenticated state updates
- log out and verify that the frontend refreshes authentication state
- verify locale switching through the URL path
- verify light and dark themes

### Notes
- frontend intentionally remains an external client for the API, not a Symfony bundle or Twig integration
- generated refine CRUD/inferencer code can be useful for experiments, but production pages should be reviewed and simplified manually
- future work can add richer filters, notification UI, admin-specific screens, and stronger reusable UI abstractions
- if backend search semantics change, the frontend `dataProvider` should map refine filters to the new API contract explicitly

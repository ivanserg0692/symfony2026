# Лог Результата MR Task 6

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Обзор](#%D0%BE%D0%B1%D0%B7%D0%BE%D1%80)
- [Планируемый результат](#%D0%BF%D0%BB%D0%B0%D0%BD%D0%B8%D1%80%D1%83%D0%B5%D0%BC%D1%8B%D0%B9-%D1%80%D0%B5%D0%B7%D1%83%D0%BB%D1%8C%D1%82%D0%B0%D1%82)
- [Скриншоты](#%D1%81%D0%BA%D1%80%D0%B8%D0%BD%D1%88%D0%BE%D1%82%D1%8B)
- [Доработки за 2026-05-07](#%D0%B4%D0%BE%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D0%BA%D0%B8-%D0%B7%D0%B0-2026-05-07)
  - [Отдельное frontend-приложение](#%D0%BE%D1%82%D0%B4%D0%B5%D0%BB%D1%8C%D0%BD%D0%BE%D0%B5-frontend-%D0%BF%D1%80%D0%B8%D0%BB%D0%BE%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5)
  - [API client и dataProvider](#api-client-%D0%B8-dataprovider)
  - [Авторизация, CSRF и refresh](#%D0%B0%D0%B2%D1%82%D0%BE%D1%80%D0%B8%D0%B7%D0%B0%D1%86%D0%B8%D1%8F-csrf-%D0%B8-refresh)
  - [Login form и Turnstile](#login-form-%D0%B8-turnstile)
  - [Локализация](#%D0%BB%D0%BE%D0%BA%D0%B0%D0%BB%D0%B8%D0%B7%D0%B0%D1%86%D0%B8%D1%8F)
  - [Theme provider](#theme-provider)
  - [Layout и breadcrumbs](#layout-%D0%B8-breadcrumbs)
  - [Раздел новостей](#%D1%80%D0%B0%D0%B7%D0%B4%D0%B5%D0%BB-%D0%BD%D0%BE%D0%B2%D0%BE%D1%81%D1%82%D0%B5%D0%B9)
  - [Refine CLI и generated code](#refine-cli-%D0%B8-generated-code)

<!-- END doctoc -->

## Обзор

Этот документ описывает видимый результат merge request 6.

Merge request: <https://github.com/ivanserg0692/symfony2026/pull/6>

Задача добавляет отдельное frontend-приложение на Next.js, React, refine и Ant Design, которое использует существующий Symfony API `/api/v1` как внешний клиент.

## Планируемый результат

Ожидаемый результат включает:
- отдельный frontend-клиент рядом с Symfony backend
- подключение frontend к существующему `/api/v1`
- REST `dataProvider` для ресурсов refine
- auth flow через HttpOnly cookies, CSRF и refresh access token
- форму входа с Cloudflare Turnstile
- локализацию `ru` и `en` через URL path
- светлую и темную темы Ant Design
- layout с меню, header и breadcrumbs
- раздел новостей со списком, поиском, сортировкой, пагинацией, table/card view и детальной страницей

## Скриншоты

Скриншоты будут добавлены после визуальной проверки frontend-приложения.

## Доработки за 2026-05-07

### Отдельное frontend-приложение

Frontend создан как отдельное приложение на Next.js, React, refine и Ant Design. Оно не встраивается в Symfony и не использует Twig/EasyAdmin как UI-слой. Symfony остается backend-приложением, а frontend работает с ним через HTTP API `/api/v1`.

### API client и dataProvider

Добавлен client-side API слой поверх `fetch` с `credentials: "include"`. Это сохраняет cookie-based authentication и не требует передавать `Authorization` header из frontend-кода.

`dataProvider` связывает refine-ресурсы с REST API:
- `getList` строит запросы к `/{resource}` с `page`, `limit`, сортировкой и фильтрами
- `getOne` загружает детальную запись по id/slug
- mutation-запросы используют CSRF header
- фильтр поиска новостей преобразуется в query parameter `query`

### Авторизация, CSRF и refresh

Frontend учитывает backend-схему авторизации через HttpOnly cookies. Access token не читается JavaScript-кодом и не передается в headers вручную.

CSRF token кэшируется как последний полученный token и переиспользуется для небезопасных запросов. Если backend возвращает CSRF-ошибку, token очищается и запрашивается заново.

При `401` на API-запросах, где refresh допустим, frontend выполняет refresh access token и повторяет исходный запрос один раз.

### Login form и Turnstile

Форма входа перенесена в frontend-приложение и оформлена на Ant Design. Для защиты login flow подключен Cloudflare Turnstile. Site key хранится в frontend environment, а проверка token остается ответственностью backend.

### Локализация

Добавлен i18n provider для refine и локальные locale-файлы для `ru` и `en`. Локаль хранится в path, например `/ru/news` и `/en/news`, а не в query string.

Переводы используются в меню, breadcrumbs, auth form, новостях, кнопках, поиске, переключателях view mode и theme controls.

### Theme provider

Добавлен theme provider поверх Ant Design `ConfigProvider`. Поддерживаются светлая и темная темы, переключение темы и сохранение выбранного режима в localStorage.

Цвета интерфейса выровнены через Ant Design tokens и общие CSS variables, чтобы контентная область, layout, карточки и детальная страница читались согласованно в обеих темах.

### Layout и breadcrumbs

Добавлен общий layout с sidebar menu, header, content area и breadcrumbs. Breadcrumbs строятся через refine `useBreadcrumb`, локализуются через i18n provider и выводятся один раз на уровне layout.

Встроенные breadcrumbs refine-компонентов отключены на страницах, где общий layout уже выводит breadcrumbs.

### Раздел новостей

Реализован публичный раздел новостей:
- список новостей
- детальная страница новости
- поиск по `query`
- сортировка по поддерживаемым колонкам
- пагинация и выбор размера страницы
- синхронизация состояния таблицы с URL
- сброс поиска с очисткой фильтров и URL
- переключение table/card view
- переход к детальной новости через `next/link` с сохранением визуала Ant Design button

Публичный список новостей не оборачивается в обязательную auth-проверку, поэтому пользователь может читать новости без редиректа на login.

### Refine CLI и generated code

Во время работы проверен refine CLI и generated CRUD/inferencer подход. Генерация полезна для быстрого эксперимента и получения стартового кода, но итоговые страницы новостей были упрощены и приведены к более контролируемой структуре для дальнейшей поддержки.

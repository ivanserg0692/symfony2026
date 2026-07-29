# MR Task 6 Result Log

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Overview](#overview)
- [Planned Result](#planned-result)
- [Screenshots](#screenshots)
- [Updates for 2026-05-07](#updates-for-2026-05-07)
  - [Separate Frontend Application](#separate-frontend-application)
  - [API Client and DataProvider](#api-client-and-dataprovider)
  - [Authentication, CSRF, and Refresh](#authentication-csrf-and-refresh)
  - [Login Form and Turnstile](#login-form-and-turnstile)
  - [Localization](#localization)
  - [Theme Provider](#theme-provider)
  - [Layout and Breadcrumbs](#layout-and-breadcrumbs)
  - [News Section](#news-section)
  - [Refine CLI and Generated Code](#refine-cli-and-generated-code)

<!-- END doctoc -->

## Overview

This document describes the visible result of merge request 6.

Merge request: <https://github.com/ivanserg0692/symfony2026/pull/6>

The task adds a separate frontend application built with Next.js, React, refine, and Ant Design. The frontend consumes the existing Symfony API `/api/v1` as an external client.

## Planned Result

The expected result includes:
- a separate frontend client next to the Symfony backend
- frontend connection to the existing `/api/v1`
- a REST `dataProvider` for refine resources
- an auth flow through HttpOnly cookies, CSRF, and access token refresh
- a login form with Cloudflare Turnstile
- `ru` and `en` localization through the URL path
- light and dark Ant Design themes
- layout with menu, header, and breadcrumbs
- a news section with list, search, sorting, pagination, table/card view, and detail page

## Screenshots

Screenshots will be added after the frontend application is visually verified.

## Updates for 2026-05-07

### Separate Frontend Application

The frontend was created as a separate application built with Next.js, React, refine, and Ant Design. It is not embedded into Symfony and does not use Twig or EasyAdmin as its UI layer. Symfony remains the backend application, while the frontend communicates with it through the `/api/v1` HTTP API.

### API Client and DataProvider

A client-side API layer was added on top of `fetch` with `credentials: "include"`. This preserves cookie-based authentication and does not require sending an `Authorization` header from frontend code.

The `dataProvider` connects refine resources to the REST API:
- `getList` builds requests to `/{resource}` with `page`, `limit`, sorting, and filters
- `getOne` loads a detail record by id/slug
- mutation requests use a CSRF header
- the news search filter is mapped to the `query` query parameter

### Authentication, CSRF, and Refresh

The frontend follows the backend authentication model based on HttpOnly cookies. The access token is not read by JavaScript code and is not manually passed in headers.

The CSRF token is cached as the latest received token and reused for unsafe requests. If the backend returns a CSRF error, the token is cleared and requested again.

After `401` responses on API requests where refresh is allowed, the frontend refreshes the access token and retries the original request once.

### Login Form and Turnstile

The login form was moved into the frontend application and styled with Ant Design. Cloudflare Turnstile was added to protect the login flow. The site key is stored in the frontend environment, while token verification remains a backend responsibility.

### Localization

An i18n provider was added for refine, with local locale files for `ru` and `en`. Locale is stored in the path, for example `/ru/news` and `/en/news`, instead of the query string.

Translations are used in the menu, breadcrumbs, auth form, news section, buttons, search controls, view mode switcher, and theme controls.

### Theme Provider

A theme provider was added on top of Ant Design `ConfigProvider`. Light and dark themes are supported, including theme switching and persistence in localStorage.

UI colors are aligned through Ant Design tokens and shared CSS variables so the content area, layout, cards, and detail page remain readable in both themes.

### Layout and Breadcrumbs

A shared layout was added with sidebar menu, header, content area, and breadcrumbs. Breadcrumbs are built through refine `useBreadcrumb`, localized through the i18n provider, and rendered once at the layout level.

Built-in refine component breadcrumbs are disabled on pages where the shared layout already renders breadcrumbs.

### News Section

The public news section was implemented:
- news list
- news detail page
- search through `query`
- sorting on supported columns
- pagination and page size selection
- table state synchronization with the URL
- search reset with filter and URL cleanup
- table/card view switching
- detail navigation through `next/link` while preserving the Ant Design button visual

The public news list is not wrapped in a mandatory auth check, so users can read news without being redirected to login.

### Refine CLI and Generated Code

During the work, refine CLI and generated CRUD/inferencer code were reviewed. Generation is useful for quick experiments and starter code, but the final news pages were simplified and moved into a more controlled structure for future maintenance.

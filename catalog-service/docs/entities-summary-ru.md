# Catalog Service Entity Summary

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Русский](#%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)
  - [Общая модель](#%D0%BE%D0%B1%D1%89%D0%B0%D1%8F-%D0%BC%D0%BE%D0%B4%D0%B5%D0%BB%D1%8C)
  - [CatalogSections](#catalogsections)
  - [CatalogElements](#catalogelements)
  - [Stores](#stores)
  - [StoresElementsStocks](#storeselementsstocks)
  - [Итоговая схема](#%D0%B8%D1%82%D0%BE%D0%B3%D0%BE%D0%B2%D0%B0%D1%8F-%D1%81%D1%85%D0%B5%D0%BC%D0%B0)

<!-- END doctoc -->

## Русский

### Общая модель

В `catalog-service` сейчас используются четыре основные Doctrine entity:

- `CatalogSections`
- `CatalogElements`
- `Stores`
- `StoresElementsStocks`

### CatalogSections

`CatalogSections` описывает секцию каталога. Entity хранит дерево разделов и связь с товарами.

Основные поля:

- `id`
- `name`
- `slug`
- `active`
- `description`
- `pictureId`
- `level`
- `leftMargin`
- `rightMargin`
- `sort`

Связи:

- `parent` - `ManyToOne` на `CatalogSections`
- `catalogSections` - `OneToMany` дочерних секций
- `catalogElements` - `ManyToMany` с `CatalogElements`

### CatalogElements

`CatalogElements` описывает товар каталога.

Основные поля:

- `id`
- `name`
- `createdAt`
- `active`
- `createdBy`
- `description`
- `slug`
- `pictureId`
- `sort`

Связи:

- `sections` - `ManyToMany` с `CatalogSections`
- `storeStocks` - `OneToMany` на `StoresElementsStocks`

Дополнительная логика:

- `getTotalStock()` считает общий остаток товара по всем магазинам.
- `getStores()` возвращает коллекцию магазинов через `storeStocks`, без отдельной ORM `ManyToMany` связи.

### Stores

`Stores` описывает магазин.

Основные поля:

- `id`
- `name`
- `slug`
- `active`
- `description`

Связи:

- `elementStocks` - `OneToMany` на `StoresElementsStocks`

### StoresElementsStocks

`StoresElementsStocks` - это ассоциативная entity, то есть промежуточная таблица для логической связи `many-to-many` между `Stores` и `CatalogElements`.

Это не Doctrine `ManyToMany`, а явная модель связи через две `ManyToOne`, потому что у связи есть собственное поле `stock`.

Связи:

- `store` - `ManyToOne` на `Stores`, часть составного primary key
- `element` - `ManyToOne` на `CatalogElements`, часть составного primary key

Поля:

- `stock`

Логически:

```text
Stores
    N --- M CatalogElements

через StoresElementsStocks
```

Физически:

```text
stores_elements_stocks
- store_id
- element_id
- stock
```

`store_id + element_id` вместе образуют составной primary key, чтобы одна и та же пара магазин-товар не дублировалась.

### Итоговая схема

```text
CatalogSections
    1 --- N CatalogSections
    parent / children

CatalogSections
    N --- M CatalogElements
    через Doctrine ManyToMany

CatalogElements
    1 --- N StoresElementsStocks

Stores
    1 --- N StoresElementsStocks

StoresElementsStocks
    N --- 1 CatalogElements
    N --- 1 Stores
    хранит stock
```

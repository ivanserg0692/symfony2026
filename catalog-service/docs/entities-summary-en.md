# Catalog Service Entity Summary

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [English](#english)
  - [General Model](#general-model)
  - [CatalogSections](#catalogsections)
  - [CatalogElements](#catalogelements)
  - [Stores](#stores)
  - [StoresElementsStocks](#storeselementsstocks)
  - [Final Schema](#final-schema)

<!-- END doctoc -->

## English

### General Model

`catalog-service` currently uses four main Doctrine entities:

- `CatalogSections`
- `CatalogElements`
- `Stores`
- `StoresElementsStocks`

### CatalogSections

`CatalogSections` describes a catalog section. The entity stores the section tree and the relation to products.

Main fields:

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

Relations:

- `parent` - `ManyToOne` to `CatalogSections`
- `catalogSections` - `OneToMany` child sections
- `catalogElements` - `ManyToMany` with `CatalogElements`

### CatalogElements

`CatalogElements` describes a catalog product.

Main fields:

- `id`
- `name`
- `createdAt`
- `active`
- `createdBy`
- `description`
- `slug`
- `pictureId`
- `sort`

Relations:

- `sections` - `ManyToMany` with `CatalogSections`
- `storeStocks` - `OneToMany` to `StoresElementsStocks`

Additional logic:

- `getTotalStock()` calculates the total product stock across all stores.
- `getStores()` returns the store collection through `storeStocks`, without a separate ORM `ManyToMany` relation.

### Stores

`Stores` describes a store or stock location.

Main fields:

- `id`
- `name`
- `slug`
- `active`
- `description`

Relations:

- `elementStocks` - `OneToMany` to `StoresElementsStocks`

### StoresElementsStocks

`StoresElementsStocks` is an associative entity, meaning an intermediate table for the logical `many-to-many` relation between `Stores` and `CatalogElements`.

It is not a Doctrine `ManyToMany` relation, but an explicit relation model through two `ManyToOne` relations because the relation has its own `stock` field.

Relations:

- `store` - `ManyToOne` to `Stores`, part of the composite primary key
- `element` - `ManyToOne` to `CatalogElements`, part of the composite primary key

Fields:

- `stock`

Logically:

```text
Stores
    N --- M CatalogElements

through StoresElementsStocks
```

Physically:

```text
stores_elements_stocks
- store_id
- element_id
- stock
```

`store_id + element_id` form a composite primary key together, so the same store-product pair cannot be duplicated.

### Final Schema

```text
CatalogSections
    1 --- N CatalogSections
    parent / children

CatalogSections
    N --- M CatalogElements
    through Doctrine ManyToMany

CatalogElements
    1 --- N StoresElementsStocks

Stores
    1 --- N StoresElementsStocks

StoresElementsStocks
    N --- 1 CatalogElements
    N --- 1 Stores
    stores stock
```

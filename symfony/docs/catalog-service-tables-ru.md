# Catalog Service Tables

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Русский](#%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)
  - [ERD таблиц](#erd-%D1%82%D0%B0%D0%B1%D0%BB%D0%B8%D1%86)
  - [Назначение таблиц](#%D0%BD%D0%B0%D0%B7%D0%BD%D0%B0%D1%87%D0%B5%D0%BD%D0%B8%D0%B5-%D1%82%D0%B0%D0%B1%D0%BB%D0%B8%D1%86)

<!-- END doctoc -->

## Русский

### ERD таблиц

Диаграмма показывает таблицы `catalog-service`, построенные по Doctrine entities и `doctrine.orm.naming_strategy.underscore`.

<!-- plantuml src="plantuml/catalog-service/tables.puml" alt="Catalog service tables" out="images/plantuml/catalog-service/tables.png" -->
![Catalog service tables](images/plantuml/catalog-service/tables.png)
<!-- /plantuml -->

### Назначение таблиц

| Таблица | Назначение | Основные Doctrine-связи |
| --- | --- | --- |
| `catalog_sections` | Дерево разделов каталога. | `ManyToOne` на родительский раздел, `OneToMany` на дочерние разделы, `ManyToMany` с товарами. |
| `catalog_elements` | Карточки товаров или элементов каталога. | `ManyToMany` с разделами, `OneToMany` с остатками по магазинам, `OneToMany` с ценами. |
| `catalog_elements_catalog_sections` | Неявная join-table Doctrine для привязки товаров к разделам. | Две стороны `ManyToMany`: товар и раздел. |
| `stores` | Магазины или складские точки, по которым ведутся остатки. | `OneToMany` с остатками товаров. |
| `stores_elements_stocks` | Остаток конкретного товара в конкретном магазине. | Две `ManyToOne` связи: на магазин и товар; обе входят в составной primary key. |
| `price_type` | Типы цен товаров: базовая, акционная, оптовая или другой ценовой канал. | `OneToMany` с ценами товаров. |
| `product_price` | Цена товара для конкретного типа цены, валюты и периода действия. | `ManyToOne` на товар и `ManyToOne` на тип цены. |

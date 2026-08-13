# E-Commerce API

REST API для интернет-магазина на Laravel.

## Технологии

- Laravel 12
- MySQL
- Laravel Sanctum (API аутентификация)
- PHP 8.2+

## API Эндпоинты

### Публичные
- `POST /api/auth/register` - Регистрация
- `POST /api/auth/login` - Вход
- `GET /api/products` - Список товаров
- `GET /api/products/{id}` - Просмотр товара
- `GET /api/categories` - Список категорий

### Защищенные (требуют токен)
- `GET /api/user` - Данные пользователя
- `GET /api/cart` - Корзина
- `POST /api/cart/add` - Добавить в корзину
- `POST /api/orders` - Создать заказ
- `GET /api/orders` - История заказов
- `POST /api/products/{id}/review` - Оставить отзыв

## Аутентификация

API использует Bearer Token аутентификацию через Laravel Sanctum.

# Получить токен

`POST /api/auth/login`

```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

# Использовать токен

```json
Authorization: Bearer {ваш_токен}
```

## База данных

# Структура базы данных включает:

- users
- categories
- brands
- products
- product_images
- attributes
- reviews
- orders
- order_items
- carts
- shipping_addresses

# Полная стуктура методов:
📁 E-Commerce API
│
├── 📁 Public (НЕ требуют токен)
│   ├── POST   /auth/register          - Регистрация
│   ├── POST   /auth/login             - Вход (получение токена)
│   ├── GET    /products               - Список товаров (с фильтрацией)
│   ├── GET    /products/{id}          - Товар по ID
│   ├── GET    /products/{id}/similar  - Похожие товары
│   ├── GET    /categories             - Список категорий
│   ├── GET    /categories/tree        - Дерево категорий
│   ├── GET    /categories/{id}/products - Товары категории
│   └── GET    /products/{id}/reviews  - Отзывы на товар
│
├── 📁 Protected (ТРЕБУЮТ токен)
│   │
│   ├── 📁 Auth
│   │   ├── POST /auth/logout          - Выход
│   │   └── GET  /user                 - Данные пользователя
│   │
│   ├── 📁 Cart
│   │   ├── GET    /cart               - Корзина
│   │   ├── POST   /cart/add           - Добавить в корзину
│   │   ├── PUT    /cart/update        - Обновить количество
│   │   ├── DELETE /cart/remove/{id}   - Удалить из корзины
│   │   └── DELETE /cart/clear         - Очистить корзину
│   │
│   ├── 📁 Orders
│   │   ├── POST /orders               - Создать заказ
│   │   ├── GET  /orders               - История заказов
│   │   ├── GET  /orders/{id}          - Просмотр заказа
│   │   └── PUT  /orders/{id}/cancel   - Отменить заказ
│   │
│   └── 📁 Reviews
│       ├── POST   /products/{id}/review - Оставить отзыв
│       ├── PUT    /reviews/{id}         - Обновить отзыв
│       └── DELETE /reviews/{id}         - Удалить отзыв
│
└── 📁 Admin (ТРЕБУЮТ токен + права админа)
    │
    ├── GET    /admin/dashboard          - Дашборд
    ├── GET    /admin/products           - Все товары (админ)
    ├── POST   /admin/products           - Создать товар
    ├── PUT    /admin/products/{id}      - Обновить товар
    ├── DELETE /admin/products/{id}      - Удалить товар
    ├── GET    /admin/orders             - Все заказы
    ├── GET    /admin/orders/{id}        - Просмотр заказа (админ)
    ├── PUT    /admin/orders/{id}/status - Обновить статус
    └── GET    /admin/reports/sales      - Отчет по продажам
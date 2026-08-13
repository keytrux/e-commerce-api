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

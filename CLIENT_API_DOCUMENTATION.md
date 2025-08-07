# Client API Documentation

## Аутентификация

Для доступа к API используется аутентификация по ключам. Каждый запрос должен содержать заголовки:

```
X-API-Key: your_api_key
X-API-Secret: your_api_secret
```

## Базовый URL

```
https://your-domain.com/api/client/v1/
```

## Endpoints

### 1. Получение заказов

**GET** `/orders`

Параметры:

-   `warehouse_id` (обязательный) - ID склада
-   `date_from` (опциональный) - Дата начала (YYYY-MM-DD)
-   `date_to` (опциональный) - Дата окончания (YYYY-MM-DD)
-   `status` (опциональный) - Статус заказа
-   `order_id` (опциональный) - ID заказа для поиска
-   `limit` (опциональный) - Лимит результатов (максимум 100, по умолчанию 50)
-   `offset` (опциональный) - Смещение для пагинации

Пример запроса:

```bash
curl -X GET "https://your-domain.com/api/client/v1/orders?warehouse_id=1&limit=10" \
  -H "X-API-Key: your_api_key" \
  -H "X-API-Secret: your_api_secret" \
  -H "Content-Type: application/json"
```

Пример ответа:

```json
{
    "success": true,
    "data": [
        {
            "IDOrder": 12345,
            "Number": "ZO123/25 - WH1",
            "Created": "2025-01-08 10:00:00",
            "Modified": "2025-01-08 10:30:00",
            "status": "W realizacji",
            "customer_name": "Jan Kowalski",
            "customer_email": "jan@example.com",
            "source_type": "api",
            "external_order_id": "EXT_12345",
            "details": {
                "delivery_method": "Kurier",
                "delivery_address": "ul. Przykładowa 123",
                "delivery_city": "Warszawa",
                "payment_method": "Przelew"
            },
            "products": [
                {
                    "product_name": "Przykładowy produkt",
                    "ean": "1234567890123",
                    "quantity": 2,
                    "PriceGross": 99.99
                }
            ]
        }
    ],
    "meta": {
        "limit": 10,
        "offset": 0,
        "count": 1
    }
}
```

### 2. Создание заказа

**POST** `/orders`

Тело запроса:

```json
{
    "warehouse_id": 1,
    "external_order_id": "EXT_12345",
    "customer": {
        "name": "Jan Kowalski",
        "email": "jan@example.com",
        "phone": "+48123456789",
        "company": "Firma ABC"
    },
    "delivery": {
        "fullname": "Jan Kowalski",
        "address": "ul. Przykładowa 123",
        "city": "Warszawa",
        "postcode": "00-001",
        "country_code": "PL",
        "method": "Kurier",
        "company": "Firma ABC"
    },
    "products": [
        {
            "ean": "1234567890123",
            "name": "Przykładowy produkt",
            "quantity": 2,
            "price_brutto": 99.99,
            "tax_rate": 23
        }
    ],
    "payment_method": "Przelew",
    "delivery_price": 15.0,
    "user_comments": "Komentarz klienta",
    "admin_comments": "Komentarz administracyjny"
}
```

Пример ответа:

```json
{
    "success": true,
    "data": {
        "order_id": 12345,
        "order_number": "ZO123/25 - WH1",
        "external_order_id": "EXT_12345",
        "status": "W realizacji"
    }
}
```

### 3. Обновление статуса заказа

**PATCH** `/orders/{orderId}/status`

Параметры URL:

-   `orderId` - ID заказа (может быть внутренний ID, номер заказа или external_order_id)

Тело запроса:

```json
{
    "warehouse_id": 1,
    "status": "Wysłane"
}
```

Пример ответа:

```json
{
    "success": true,
    "data": {
        "order_id": 12345,
        "status": "Wysłane",
        "updated_at": "2025-01-08T12:00:00Z"
    }
}
```

### 4. Получение возвратов

**GET** `/returns`

Параметры:

-   `warehouse_id` (обязательный) - ID склада
-   `order_id` (опциональный) - ID заказа
-   `date_from` (опциональный) - Дата начала
-   `date_to` (опциональный) - Дата окончания

## Коды ошибок

-   `400` - Неверные параметры запроса
-   `401` - Неверные API ключи
-   `403` - Недостаточно прав или доступ к складу запрещен
-   `404` - Ресурс не найден
-   `429` - Превышен лимит запросов
-   `500` - Внутренняя ошибка сервера

## Лимиты и ограничения

-   Максимальное количество запросов: настраивается для каждого клиента (по умолчанию 1000/час)
-   Максимальное количество заказов в одном запросе: 100
-   Размер тела запроса: максимум 1MB

## Безопасность

-   Все запросы должны выполняться по HTTPS
-   API ключи должны храниться в безопасном месте
-   Возможно ограничение по IP адресам (настраивается)

## Webhook уведомления

При изменении статуса заказа система может отправлять уведомления на указанный webhook URL.

Формат уведомления:

```json
{
    "event": "order.status_changed",
    "order_id": 12345,
    "external_order_id": "EXT_12345",
    "warehouse_id": 1,
    "old_status": "W realizacji",
    "new_status": "Wysłane",
    "timestamp": "2025-01-08T12:00:00Z"
}
```

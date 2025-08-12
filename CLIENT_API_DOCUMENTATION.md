# Документация по API для клиентов

## Аутентификация

Каждый запрос должен содержать заголовок:

```
X-API-Key: your_api_key
```

## Базовый URL

```
https://your-domain.com/api/
```

## Endpoints

### Получение заказов

**GET** `/api/orders`

Параметры:

-   `date_from` (опционально) — дата начала (YYYY-MM-DD)
-   `date_to` (опционально) — дата окончания (YYYY-MM-DD)
-   `status` (опционально) — статус заказа
-   `order_id` (опционально) — внешний ID заказа
-   `limit` (опционально) — лимит (до 100, по умолчанию 50)
-   `offset` (опционально) — смещение

Пример:

```bash
curl -X GET "https://your-domain.com/api/orders?limit=10" \
    -H "X-API-Key: your_api_key" \
    -H "Content-Type: application/json"
```

Ответ:

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
            "external_order_id": "EXT_12345",
            "details": { ... },
            "products": [ ... ]
        }
    ],
    "meta": {
        "limit": 10,
        "offset": 0,
        "count": 1
    }
}
```

### Upsert заказа (создание/обновление)

**POST** `/api/order`

Тело запроса:

```json
{
    "external_order_id": "EXT_12345",
    "customer": {
        "name": "Jan Kowalski",
        "email": "jan@example.com"
    },
    "delivery": {
        "fullname": "Jan Kowalski",
        "address": "ul. Примерная 123",
        "city": "Warszawa",
        "postcode": "00-001",
        "country_code": "PL",
        "method": "Kurier"
    },
    "products": [
        {
            "ean": "1234567890123",
            "name": "Тестовый продукт",
            "quantity": 2,
            "price_brutto": 99.99
        }
    ],
    "payment_method": "Przelew",
    "delivery_price": 15.0,
    "user_comments": "Комментарий клиента"
}
```

**Все поля, кроме `external_order_id`, опциональны.** Можно обновлять только нужные поля (например, только телефон, только адрес, только товары).

Пример частичного обновления (только телефон):

```json
{
    "external_order_id": "EXT_12345",
    "customer": {
        "phone": "+48123456789"
    }
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

### Получение возвратов

**GET /api/returns** — не используется (endpoint зарезервирован, функционал не реализован)

## Коды ошибок

-   400 — Неверные параметры запроса
-   401 — Неверный API-ключ
-   403 — Нет доступа к складу
-   404 — Заказ не найден
-   409 — Заказ нельзя обновить в текущем статусе
-   429 — Превышен лимит запросов
-   500 — Внутренняя ошибка сервера

## Ограничения

-   Максимум 100 заказов за запрос
-   Максимум 1000 запросов в час (по умолчанию)
-   Размер тела запроса — до 1MB
-   Обновлять можно только заказы в статусах: "Anulowany", "Nie wysyłać", "Nowe zamówienia", "W realizacji"
-   Для upsert обязателен только `external_order_id`, остальные поля — опциональны

## Безопасность

-   Все запросы — только по HTTPS
-   Храните API-ключи в безопасном месте
-   Для критичных клиентов можно настроить ограничение по IP

## Логирование

-   Все операции логируются в таблице `log_orders` и Laravel logs

## Техническая поддержка

Для обращения укажите:

-   внешний ID заказа
-   время запроса
-   текст ошибки
-   пример запроса

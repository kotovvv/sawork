# Инструкции по развертыванию API для клиентов

## Обзор изменений

Было реализовано комплексное API решение для предоставления клиентам доступа к функционалу заказов, возвратов и доставок. Система позволяет:

1. **Создавать и управлять API клиентами** с гибкими правами доступа
2. **Отслеживать источник заказов** (BaseLinker vs API vs Manual)
3. **Обеспечивать безопасность** через аутентификацию по ключам и контроль доступа
4. **Контролировать нагрузку** через rate limiting и мониторинг

## Компоненты решения

### База данных

Все таблицы API создаются в основной базе данных (MSSQL) для упрощения работы с заказами:

-   `api_clients` - управление API клиентами
-   `order_sources` - отслеживание источника заказов

**Важно**: Используются адаптированные типы данных для MSSQL:

-   `JSON` → `TEXT` с JSON-строками
-   `BOOLEAN` → `BIT`
-   `UNSIGNED BIGINT` → `BIGINT`

### 1. Модели

-   `ApiClient` - управление API клиентами
-   `OrderSource` - отслеживание источника заказов

### 2. Контроллеры

-   `ClientApiController` - основной API для клиентов
-   `ApiClientManagementController` - управление API клиентами (для админов)

### 3. Middleware

-   `ApiAuthentication` - аутентификация API запросов

### 4. Команды

-   `ManageApiClient` - CLI команды для управления клиентами

## Шаги развертывания

### 1. Выполнить миграции

```bash
# Для основной базы данных (MSSQL)
php artisan migrate
```

### 2. Зарегистрировать команду в Console/Kernel.php

Добавить в `app/Console/Kernel.php`:

```php
protected $commands = [
    Commands\ManageApiClient::class,
];
```

### 3. Создать демо API клиента (опционально)

```bash
php artisan db:seed --class=ApiClientSeeder
```

### 4. Создать API клиента через команду

```bash
# Создать нового клиента
php artisan api:client create --name="Test Client" --warehouses="1,2" --permissions="orders.read,orders.create"

# Посмотреть всех клиентов
php artisan api:client list

# Показать детали клиента
php artisan api:client show --id=1

# Перегенерировать ключи
php artisan api:client regenerate --id=1
```

## Настройка прав доступа

### Доступные разрешения:

-   `orders.read` - чтение заказов
-   `orders.create` - создание заказов
-   `orders.update` - обновление заказов
-   `returns.read` - чтение возвратов
-   `returns.create` - создание возвратов
-   `deliveries.read` - чтение доставок
-   `deliveries.update` - обновление доставок

### Настройка складов:

Каждый API клиент имеет доступ только к указанным складам.

## Использование API

### Базовый URL для клиентов:

```
https://your-domain.com/api/client/v1/
```

### Аутентификация:

Каждый запрос должен содержать заголовки:

```
X-API-Key: client_api_key
X-API-Secret: client_api_secret
```

### Основные endpoints:

-   `GET /orders` - получение заказов
-   `POST /orders` - создание заказа
-   `PATCH /orders/{id}/status` - обновление статуса
-   `GET /returns` - получение возвратов

## Мониторинг и управление

### Административные endpoints:

```
GET /api/admin/api-clients - список клиентов
POST /api/admin/api-clients - создание клиента
GET /api/admin/api-clients/{id} - детали клиента
PUT /api/admin/api-clients/{id} - обновление клиента
DELETE /api/admin/api-clients/{id} - удаление клиента
POST /api/admin/api-clients/{id}/regenerate - перегенерация ключей
GET /api/admin/api-usage - статистика использования
```

### CLI команды:

```bash
# Управление клиентами
php artisan api:client create
php artisan api:client list
php artisan api:client show --id=1
php artisan api:client regenerate --id=1
php artisan api:client disable --id=1
php artisan api:client enable --id=1
```

## Отслеживание источника заказов

Система автоматически отслеживает источник каждого заказа:

-   **BaseLinker заказы**: помечаются в таблице `order_sources` с типом `baselinker`
-   **API заказы**: помечаются с типом `api` и привязкой к клиенту
-   **Ручные заказы**: помечаются с типом `manual`

Это позволяет:

-   Различать заказы по источнику
-   Ограничивать операции (например, API клиенты могут изменять только свои заказы)
-   Вести аналитику по источникам

## Безопасность

### Реализованные меры:

1. **API ключи** - двухфакторная аутентификация (key + secret)
2. **IP whitelist** - ограничение по IP адресам
3. **Rate limiting** - ограничение количества запросов
4. **Права доступа** - гранулярные разрешения
5. **Ограничения по складам** - доступ только к разрешенным складам

### Рекомендации:

-   Используйте HTTPS для всех API запросов
-   Регулярно ротируйте API ключи
-   Настройте IP whitelist для критических клиентов
-   Мониторьте использование API через логи

## Расширение функционала

### Добавление новых endpoints:

1. Добавить метод в `ClientApiController`
2. Добавить разрешение в список permissions
3. Добавить маршрут в `routes/api.php`
4. Обновить документацию

### Добавление webhook уведомлений:

В контроллерах уже предусмотрена поддержка webhook URL из настроек клиента.

## Тестирование

### Пример curl запроса:

```bash
curl -X GET "https://your-domain.com/api/client/v1/orders?warehouse_id=1" \
  -H "X-API-Key: your_api_key" \
  -H "X-API-Secret: your_api_secret" \
  -H "Content-Type: application/json"
```

### Создание заказа:

```bash
curl -X POST "https://your-domain.com/api/client/v1/orders" \
  -H "X-API-Key: your_api_key" \
  -H "X-API-Secret: your_api_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "warehouse_id": 1,
    "external_order_id": "TEST_001",
    "customer": {
      "name": "Test Customer",
      "email": "test@example.com"
    },
    "delivery": {
      "fullname": "Test Customer",
      "address": "Test Address 123",
      "city": "Test City",
      "postcode": "00-000",
      "country_code": "PL",
      "method": "Kurier"
    },
    "products": [{
      "ean": "1234567890123",
      "name": "Test Product",
      "quantity": 1,
      "price_brutto": 99.99
    }]
  }'
```

## Логирование и мониторинг

Все API операции логируются:

-   В таблице `log_orders` для операций с заказами
-   В Laravel logs для технических ошибок
-   Обновляется `last_used_at` для клиентов

## Обратная совместимость

Существующий функционал BaseLinker остается полностью рабочим. Новое API работает параллельно и не влияет на текущие процессы.

## Поддержка

Документация API доступна в файле `CLIENT_API_DOCUMENTATION.md`.

Для технической поддержки обращайтесь к разработчику с указанием:

-   ID API клиента
-   Время запроса
-   Полный текст ошибки
-   Пример запроса

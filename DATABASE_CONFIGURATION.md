# Конфигурация базы данных для API

## Важные изменения в архитектуре

**Проблема**: Изначально планировалось использовать отдельную MySQL базу для API, но это создавало сложности с cross-database запросами между MSSQL (заказы) и MySQL (API данные).

**Решение**: Все API таблицы перенесены в основную базу данных MSSQL для упрощения JOIN'ов и обеспечения целостности данных.

## Адаптация для MSSQL

### Типы данных:

-   `JSON` → `TEXT` (с JSON-строками)
-   `BOOLEAN` → `BIT`
-   `UNSIGNED BIGINT` → `BIGINT`
-   `ENUM` → `VARCHAR` с ограничениями

### Обработка JSON:

Для работы с JSON в MSSQL созданы аксессоры и мутаторы в моделях:

```php
// ApiClient модель
public function getWarehouseIdsAttribute($value)
{
    return $value ? json_decode($value, true) : [];
}

public function setWarehouseIdsAttribute($value)
{
    $this->attributes['warehouse_ids'] = json_encode($value);
}
```

## Структура базы данных

### MSSQL база (единая):

-   **Существующие таблицы**:

    -   `Orders` - заказы
    -   `OrderLines` - позиции заказов
    -   `OrderStatus` - статусы заказов
    -   `Kontrahent` - контрагенты
    -   `Towar` - товары

-   **Новые API таблицы**:

    -   `api_clients` - API клиенты
    -   `order_sources` - источники заказов

-   **MySQL таблицы** (остаются в second_mysql):
    -   `order_details` - детали заказов (уже существует)

## Преимущества единой базы:

1. **Простые JOIN'ы**: Можно делать прямые связи между Orders и order_sources
2. **ACID транзакции**: Вся операция создания заказа в одной транзакции
3. **Производительность**: Нет накладных расходов на cross-database запросы
4. **Целостность данных**: Foreign keys и constraints работают корректно
5. **Упрощенное резервное копирование**: Все в одной базе

## Запуск миграций

```bash
# Создать API таблицы в основной MSSQL базе
php artisan migrate

# Проверить статус миграций
php artisan migrate:status
```

## Проверка соединения

Создайте тестовый скрипт для проверки:

```php
// test_api_tables.php
<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Тест основного соединения (MSSQL)
    $connection = DB::connection();
    $connection->getPdo();
    echo "✓ MSSQL connection - OK\n";

    // Проверка API таблиц
    $apiClients = DB::table('api_clients')->count();
    echo "✓ api_clients table exists - {$apiClients} records\n";

    $orderSources = DB::table('order_sources')->count();
    echo "✓ order_sources table exists - {$orderSources} records\n";

    // Проверка связи с Orders
    $ordersCount = DB::table('Orders')->count();
    echo "✓ Orders table accessible - {$ordersCount} records\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
```

Запустить тест:

```bash
php test_api_tables.php
```

## Примеры запросов

### Получение заказов с источником:

```sql
SELECT
    o.IDOrder,
    o.Number,
    o.Created,
    os.source_type,
    os.external_order_id,
    ac.name as api_client_name
FROM Orders o
LEFT JOIN order_sources os ON o.IDOrder = os.order_id
LEFT JOIN api_clients ac ON os.api_client_id = ac.id
WHERE o.IDWarehouse = 1
```

### Статистика по источникам заказов:

```sql
SELECT
    ISNULL(os.source_type, 'manual') as source,
    COUNT(*) as count
FROM Orders o
LEFT JOIN order_sources os ON o.IDOrder = os.order_id
WHERE o.IDWarehouse = 1
GROUP BY ISNULL(os.source_type, 'manual')
```

## Миграция существующих данных

Если нужно пометить существующие заказы:

```sql
-- Пометить все заказы без источника как manual
INSERT INTO order_sources (order_id, warehouse_id, source_type, created_at, updated_at)
SELECT
    IDOrder,
    IDWarehouse,
    'manual',
    GETDATE(),
    GETDATE()
FROM Orders o
WHERE NOT EXISTS (
    SELECT 1 FROM order_sources os
    WHERE os.order_id = o.IDOrder
    AND os.warehouse_id = o.IDWarehouse
)
```

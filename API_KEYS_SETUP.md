# Настройка API ключей для DM API

## Способ 1: Использование основного .env файла (Рекомендуемый)

API ключи уже добавлены в файл `.env`:

```env
# API Keys Configuration for DM API
API_KEY_1=dm-warehouse-1-key-12345
API_KEY_1_WAREHOUSE=1

API_KEY_2=dm-warehouse-2-key-67890
API_KEY_2_WAREHOUSE=2

API_KEY_3=dm-warehouse-3-key-abcde
API_KEY_3_WAREHOUSE=3
```

### Изменение ключей:

1. Откройте файл `.env`
2. Найдите секцию `# API Keys Configuration for DM API`
3. Измените значения ключей на более безопасные
4. Сохраните файл

## Способ 2: Использование отдельного .env.api файла

Если нужно использовать отдельный файл конфигурации:

1. Файл `.env.api` уже создан
2. Провайдер `ApiConfigServiceProvider` уже зарегистрирован
3. При загрузке Laravel автоматически подключит переменные из `.env.api`

## Проверка конфигурации

Запустите тест-скрипт:

```bash
php test_api_keys.php
```

Или проверьте через API:

```bash
curl -X POST "http://fulstor.test/api/dm/create" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: dm-warehouse-1-key-12345" \
  -d '{
    "products": [
      {
        "Nazwa": "Test Product",
        "EAN": "1234567890123",
        "jednostka": "towar",
        "Ilość": 1,
        "Cena": 10.00
      }
    ]
  }'
```

## Генерация безопасных ключей

Используйте команду для генерации безопасных ключей:

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Или онлайн генератор: https://www.uuidgenerator.net/api-key-generator

## Структура ответа API

### Успешный ответ:

```json
{
    "status": "success",
    "document_id": 12345,
    "document_number": "DM123/25 - WH1",
    "warehouse_id": 1,
    "warehouse_name": "Main Warehouse",
    "message": "Dokument DM został utworzony pomyślnie przez API"
}
```

### Ошибка авторизации:

```json
{
    "status": "error",
    "message": "Nieprawidłowy klucz API"
}
```

## Безопасность

-   Храните API ключи в безопасности
-   Не добавляйте файлы с ключами в git (добавьте в `.gitignore`)
-   Регулярно меняйте ключи
-   Используйте разные ключи для разных окружений (dev/prod)

# API Keys Summary

## Current Working API Keys

After resolving the configuration issue, here are the current working API keys:

### Available API Keys:

1. **`12345`** → Warehouse 10 (Axent Group sp. z o.o.)
2. **`test-warehouse-2-key`** → Warehouse 11 (Waudog)
3. **`test-warehouse-3-key`** → Warehouse 12 (Yuliia Khanenko - Fulstor)

## Issue Resolution

**Problem:** "Nieprawidłowy klucz API lub brak dostępu do magazynu"

**Root Cause:** Configuration cache was not updated after changing .env file

**Solution:**

1. Updated .env file with new API_KEY_1 value
2. Ran `php artisan config:clear` to refresh configuration cache
3. Verified API keys are working correctly

## Usage Examples

### Example 1: Using key "12345"

```bash
curl -X POST https://fulstor.test/api/dm/create \
  -H "Content-Type: application/json" \
  -H "X-API-Key: 12345" \
  -d '{
    "tranzit_warehouse": 0,
    "numer_dokumentu": "DOC-001",
    "uwagi_dokumentu": "Test document",
    "products": [
      {
        "Nazwa": "Test Product",
        "EAN": "1234567890123",
        "SKU": "TEST-001",
        "jednostka": "towar",
        "Ilość": 5,
        "Cena": 29.99
      }
    ]
  }'
```

### Example 2: Using test-warehouse-3-key

```bash
curl -X POST https://fulstor.test/api/dm/create \
  -H "Content-Type: application/json" \
  -H "X-API-Key: test-warehouse-3-key" \
  -d '{
    "tranzit_warehouse": 0,
    "numer_dokumentu": "DOC-002",
    "uwagi_dokumentu": "Another test",
    "products": [
      {
        "Nazwa": "Another Product",
        "EAN": "9876543210987",
        "SKU": "TEST-002",
        "jednostka": "karton",
        "Ilość": 3,
        "Cena": 49.50
      }
    ]
  }'
```

## Important Notes

-   **Always clear configuration cache** after changing .env file: `php artisan config:clear`
-   API keys can be provided via:
    -   Header: `X-API-Key: your-key`
    -   Request parameter: `api_key: your-key`
-   Warehouse is automatically determined by the API key
-   Each API key is tied to a specific warehouse

## Testing Status

✅ All API keys tested and working
✅ Document creation successful
✅ Configuration properly loaded

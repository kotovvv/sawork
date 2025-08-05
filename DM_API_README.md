# DM API Documentation

## Endpoint: Create DM Document

**POST** `/api/dm/create`

### Authentication

API key required in one of the following ways:

-   Header: `X-API-Key: your-api-key`
-   Body parameter: `api_key: your-api-key`

**Important**: The warehouse is automatically determined by the API key. You don't need to specify `IDWarehouse` in the request.

### API Key Configuration

#### Option 1: Environment Variables (.env file)

```env
API_KEY_1=dm-warehouse-1-key-12345
API_KEY_1_WAREHOUSE=1

API_KEY_2=dm-warehouse-2-key-67890
API_KEY_2_WAREHOUSE=2

API_KEY_3=dm-warehouse-3-key-abcde
API_KEY_3_WAREHOUSE=3
```

#### Option 2: Database Table (api_keys)

Run the SQL script from `database/sql/create_api_keys_table.sql`

### Request Body

```json
{
    "tranzit_warehouse": 0,
    "numer_dokumentu": "DOC123456",
    "uwagi_dokumentu": "Optional comments",
    "products": [
        {
            "Nazwa": "Product Name",
            "EAN": "1234567890123",
            "SKU": "SKU123",
            "jednostka": "towar",
            "Ilość": 10,
            "Cena": 25.5,
            "Waga (kg)": 1.2,
            "Długość (cm)": 30,
            "Szerokość (cm)": 20,
            "Wysokość (cm)": 15,
            "m3": 0.009,
            "Informacje dodatkowe": "Additional info",
            "Numer kartonu": "CARTON001",
            "Numer palety": "PALLET001"
        }
    ]
}
```

### Required Fields

-   `products` - Array of products
-   For each product:
    -   `Nazwa` - Product name
    -   `EAN` - Barcode (unique)
    -   `jednostka` - Unit (allowed: "towar", "karton", "paleta")
    -   `Ilość` - Quantity (number > 0)
    -   `Cena` - Price (number)

### Optional Fields

-   `tranzit_warehouse` - Transit warehouse flag (0 or 1, default: 0)
-   `numer_dokumentu` - Document number (string)
-   `uwagi_dokumentu` - Document comments (string)
-   Product optional fields:
    -   `SKU` - Stock Keeping Unit
    -   `Waga (kg)` - Weight in kg
    -   `Długość (cm)` - Length in cm
    -   `Szerokość (cm)` - Width in cm
    -   `Wysokość (cm)` - Height in cm
    -   `m3` - Volume in cubic meters
    -   `Informacje dodatkowe` - Additional information
    -   `Numer kartonu` - Carton number (only for non-transit)
    -   `Numer palety` - Pallet number (only for non-transit)

### Success Response

```json
{
    "status": "success",
    "document_id": 12345,
    "document_number": "DM123/25 - WH1",
    "warehouse_id": 1,
    "warehouse_name": "Main Warehouse",
    "tranzit_warehouse": 0,
    "numer_dokumentu": "DOC123456",
    "created_products_count": 2,
    "total_products_count": 5,
    "message": "Dokument DM został utworzony pomyślnie przez API",
    "warnings": []
}
```

### Error Response

```json
{
    "status": "error",
    "message": "Error description",
    "errors": [
        "Wiersz 2: Brak nazwy produktu",
        "Wiersz 3: Nieprawidłowa jednostka 'piece'. Dozwolone: towar, karton, paleta"
    ]
}
```

### HTTP Status Codes

-   `200` - Success
-   `400` - Bad Request (missing required fields, invalid warehouse)
-   `401` - Unauthorized (invalid API key)
-   `422` - Validation Error (product validation failed)
-   `500` - Internal Server Error

### API Key to Warehouse Mapping

Each API key is mapped to exactly one warehouse:

-   `API_KEY_1` → Warehouse 1
-   `API_KEY_2` → Warehouse 2
-   `API_KEY_3` → Warehouse 3

The warehouse ID is automatically determined from the API key, so you don't need to specify it in the request.

### Example cURL Request

```bash
curl -X POST "http://fulstor.test/api/dm/create" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: dm-warehouse-1-key-12345" \
  -d '{
    "tranzit_warehouse": 0,
    "numer_dokumentu": "DOC123456",
    "uwagi_dokumentu": "API test document",
    "products": [
      {
        "Nazwa": "Test Product",
        "EAN": "1234567890123",
        "jednostka": "towar",
        "Ilość": 10,
        "Cena": 25.50
      }
    ]
  }'
```

### Notes

-   Warehouse is automatically determined by API key
-   Products with existing EAN codes will be used as-is
-   New products will be created automatically
-   Headers in product array are automatically detected and skipped
-   Empty rows in products array are automatically skipped
-   All operations are transactional (rollback on error)

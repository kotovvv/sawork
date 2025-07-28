# DM (Dostawa do magazynu) Import System

## Overview

This system allows importing warehouse delivery documents (DM) from Excel files. It validates products against the database and creates new products and delivery documents as needed.

## Excel Format Requirements

The Excel file should contain the following columns (in any order):

| Column               | Required | Description            |
| -------------------- | -------- | ---------------------- |
| Nazwa                | Yes      | Product name           |
| SKU                  | No\*     | Product SKU code       |
| EAN                  | No\*     | Product EAN/barcode    |
| Ilość                | Yes      | Quantity               |
| jednostka            | No       | Unit of measure        |
| Cena                 | Yes      | Price                  |
| Waga (kg)            | No       | Weight in kg           |
| Długość (cm)         | No       | Length in cm           |
| Szerokość (cm)       | No       | Width in cm            |
| Wysokość (cm)        | No       | Height in cm           |
| Informacje dodatkowe | No       | Additional information |

\*Either SKU or EAN is required for product identification

## Example Excel Data

```
Nazwa,SKU,EAN,Ilość,jednostka,Cena,Waga (kg),Długość (cm),Szerokość (cm),Wysokość (cm),Informacje dodatkowe
"Produkt testowy 1","SKU001","1234567890123",10,"szt",15.50,0.5,20,15,10,"Testowy produkt"
"Produkt testowy 2","SKU002","1234567890124",5,"kg",25.00,1.2,30,20,15,"Drugi produkt"
```

## Usage Instructions

1. **Select Warehouse**: Choose the target warehouse from the dropdown
2. **Upload Excel File**: Select an Excel file (.xlsx or .xls) containing product data
3. **Map Columns**: Use the dropdown menus in the table header to map Excel columns to required fields
4. **Validate Products**: Click "Sprawdź produkty" to validate the data:
    - Checks if products already exist (by EAN or SKU)
    - Validates required fields
    - Identifies missing units of measure
    - Reports any errors or warnings
5. **Create Document**: If validation passes without errors, click "Utwórz dokument DM" to:
    - Create missing units of measure
    - Create new products
    - Generate DM document with all products

## Database Structure

### Products (Towar)

-   Primary identification: EAN (KodKreskowy) and SKU (\_TowarTempString1)
-   Product groups: Uses "default" group if none exists
-   Custom fields mapping:
    -   Weight: \_TowarTempDecimal1
    -   Volume: \_TowarTempDecimal2 (calculated from dimensions)
    -   SKU: \_TowarTempString1
    -   Length: \_TowarTempDecimal3
    -   Width: \_TowarTempDecimal4
    -   Height: \_TowarTempDecimal5

### Document (RuchMagazynowy)

-   Document type: 200 (DM - Dostawa do magazynu)
-   Auto-generated document number: DM{number}/{year} - {warehouse_symbol}

### Document Items (ElementRuchuMagazynowego)

-   Links products to the delivery document
-   Contains quantity, price, and notes

## API Endpoints

### POST /api/checkDMProducts

Validates products from Excel data

**Request:**

```json
{
    "IDWarehouse": 1,
    "products": [
        {
            "Nazwa": "Product name",
            "SKU": "SKU001",
            "EAN": "1234567890123",
            "Ilość": "10",
            "Cena": "15.50"
        }
    ]
}
```

**Response:**

```json
{
    "status": "success",
    "existing_products": [],
    "new_products": [],
    "missing_units": [],
    "errors": [],
    "warnings": []
}
```

### POST /api/createDMDocument

Creates DM document with validated products

**Request:**

```json
{
  "IDWarehouse": 1,
  "products": [...],
  "user_id": 1
}
```

**Response:**

```json
{
    "status": "success",
    "document_id": 12345,
    "document_number": "DM1/25 - WAR",
    "message": "Dokument DM został utworzony pomyślnie"
}
```

## Error Handling

The system provides comprehensive error handling:

-   **Validation Errors**: Missing required fields, invalid data types
-   **Database Errors**: Connection issues, constraint violations
-   **File Errors**: Invalid file format, corrupted Excel files
-   **Business Logic Errors**: Duplicate products, missing references

All errors are logged and displayed to the user with clear, actionable messages in Polish.

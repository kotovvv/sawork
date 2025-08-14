# Fulstor Warehouse API Documentation

## Overview

This API allows clients to create, update, and retrieve orders in the Fulstor warehouse system. Authentication is performed via an API key.
URL: https://panel.fulstor.pl

---

## Authentication

-   **Header:** `X-API-Key: <your_api_key>`
-   API keys are managed in the warehouse settings table and are encrypted for security.

---

## Endpoints

### 1. Create/Update Order (Upsert)

-   **POST** `/api/client/v1/order`
-   **Description:** Creates a new order or updates an existing order by `external_id`.
-   **Request Body:**

```json
{
    "external_id": "string 50 (required)",
    "status": "string (required)",
    "customer": {
        "name": "string 200 (required)",
        "email": "string 200(required)",
        "phone": "string 50 (required)",
        "NIP": "string 30 (optional)"
    },
    "delivery": {
        "fullname": "string 200 (required)",
        "company": "string (optional)",
        "country_code": "string (optional, 2 chars)",
        "country": "string (optional)",
        "postcode": "string 20 (optional)",
        "state": "string (optional)",
        "city": "string (required)",
        "street": "string (optional)",
        "price": "number (optional)",
        "point": {
            "name": "string (optional)",
            "id": "string (optional)",
            "address": "string (optional)",
            "postcode": "string (optional)",
            "city": "string (optional)"
        },
        "method": "string (required)"
    },
    "products": [
        {
            "ean": "string (required)",
            "quantity": "number (required, min 1)",
            "price_brutto": "number (required, min 0.01)",
            "tax_rate": 23,
            "Remarks": "string (optional)"
        }
    ],
    "order_source": "string (optional)",
    "currency": "string (required)",
    "currency_rate": "number (optional)",
    "payment_method_cod": "string 1 (optional) Flag indicating whether the type of payment is COD (cash on delivery): '1' - yes, '0' - no",
    "date_confirmed": "(required) Date created document",
    "comments": "string (optional)"
}
```

-   **Example**

```json
{
    "external_id": "12345667",
    "status": "Nie wysyłać",
    "customer": {
        "name": "Fulstor2",
        "email": "Fulstor2@example.com",
        "phone": "+480123456772",
        "NIP": ""
    },
    "delivery": {
        "fullname": "Fulstorw",
        "company": "Fulstor",
        "country_code": "PL",
        "country": "Poland",
        "postcode": "01001",

        "state": "",
        "city": "Krakow",
        "street": "ul. Centralna 10",

        "price": 50,

        "point": {
            "name": "Fulstorw",
            "id": "1111111",
            "address": "ul. Centralna 10",
            "postcode": "01001",
            "city": "Krakow"
        },
        "method": "DPD"
    },
    "products": [
        {
            "ean": "4823089360478",
            "quantity": 2,
            "price_brutto": 21.99,
            "Remarks": "Some text",
            "tax_rate": 23
        }
    ],
    "order_source": "B2B",
    "currency": "PLN",
    "currency_rate": 1,
    "payment_method_cod": "0",
    "date_confirmed": "2025-08-01",
    "comments": "Zadzwoń przed dostawą"
}
```

-   **Response:**
    -   On create: `{ success: true, data: { order_id, order_number, external_id, status, created: true } }`
    -   On update: `{ success: true, data: { order_id, order_number, external_id, status, updated: true } }`
    -   On error: `{ error: "...", details: "..." }`
-   **Notes:**
    -   If the order exists, all products are overwritten (old products are deleted, new ones are added).

---

### 2. Get Orders

-   **GET** `/api/client/v1/orders`
-   **Description:** Retrieve orders for the authenticated warehouse.
-   **Query Parameters:**
    -   `date_from` (optional, date)
    -   `date_to` (optional, date)
    -   `status` (optional, string)
    -   `external_id` (optional, string)
    -   `limit` (optional, integer, max 100)
    -   `offset` (optional, integer)
-   **Response:**

```json
{
  "success": true,
  "data": [
    {
      "IDOrder": "integer",
      "Number": "string",
      "Created": "datetime",
      "Modified": "datetime",
      "Remarks": "string",
      "external_order_id": "string",
      "status": "string",
      "customer_name": "string",
      "customer_email": "string",
      "details": { ... },
      "products": [ ... ]
    }
  ],
  "meta": {
    "limit": "integer",
    "offset": "integer",
    "count": "integer"
  }
}
```

---

### 3. Get Journal List (Order Event Log)

-   **POST** `/api/client/v1/getJournalList`
-   **Description:** Returns order event log (log of changes, actions, statuses).
-   **Authentication:** Via API key .
-   **Request Body:**

```json
{
    "order_id": "integer (optional) in the system Fulstor ",
    "type": "integer (optional)",
    "last_log_id": "integer (optional)"
}
```

-   **Parameters:**
    -   `order_id` — order number in the system Fulstor (if specified, all events for this order are returned)
    -   `type` — type of event (e.g. 18 - change of status)
    -   `last_log_id` — return events with an id greater than the specified id
-   **Response:**

```json
{
    "success": true,
    "data": [
        {
            "last_log_id": "integer",
            "order_id": "integer",
            "type": "integer",
            "message": "string",
            "created_at": "datetime",
            "object_id": "string"
        }
    ],
    "meta": {
        "count": "integer",
        "total": "integer"
    }
}
```

-   **Examples of use:**
    -   Get all order events: `{ "order_id": "12345" }`
    -   Get only status changes for the last 3 days: `{ "type": 18 }`
    -   Get events after a certain id: `{ "last_log_id": 100 }`
-   **Notes:**
    -   If no parameters are specified, events for the last 3 days are returned.
    -   Event types: 1 - creation, 16 - update, 18 - status change, etc.

---

## Status Codes

-   `200 OK` — Success
-   `201 Created` — Order created
-   `400 Bad Request` — Validation error
-   `401 Unauthorized` — Invalid API key
-   `403 Forbidden` — Access denied
-   `409 Conflict` — Order cannot be updated in current status
-   `500 Internal Server Error` — Server error

---

## Order Statuses

-   `Anulowany` (Cancelled)
-   `Nie wysyłać` (Do not send)
-   `Nowe zamówienia` (New orders)
-   `W realizacji` (In progress)
-   `Kompletowanie` (Packing)
-   `Do wysłania` (To be sent)
-   `Wysłane` (Sent)

---

## Notes for Clients

-   All requests must include a valid API key.
-   For upsert, products are always overwritten (not merged).

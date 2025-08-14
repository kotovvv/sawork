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
-   **Description:** Creates a new order or updates an existing order by `order_id`.
-   **Request Body:**

```json
{
    "order_id": "string 50 (required)",
    "status": "string (optional)",
    "date_confirmed": "YYYY-MM-DD HH:MM:SS (optional)",
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
        "method": "string (required)",
        "method_id": "integer (optional)"
    },
    "products": [
        {
            "ean": "string (required)",
            "quantity": "number (required, min 0.01)",
            "price_brutto": "number (required, min 0)",
            "tax_rate": 23,
            "Remarks": "string (optional)"
        }
    ],
    "order_source": "string (optional)",
    "order_source_id": "string (optional)",
    "currency": "string (optional)",
    "currency_rate": "number (optional)",
    "payment_method_cod": "string 1 (optional) Flag indicating whether the type of payment is COD (cash on delivery): '1' - yes, '0' - no",
    "user_comments": "string (optional)"
}
```

-   **Response:**
    -   On create: `{ success: true, data: { order_id, order_number, external_order_id, status, created: true } }`
    -   On update: `{ success: true, data: { order_id, order_number, external_order_id, status, updated: true } }`
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
    -   `order_id` (optional, string)
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
    "order_id": "string (optional)",
    "type": "integer (optional)",
    "last_log_id": "integer (optional)"
}
```

-   **Parameters:**
    -   `order_id` — order number (if specified, all events for this order are returned)
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

-- SQL script to create api_keys table
-- This is optional - you can use config/app.php instead
CREATE TABLE api_keys (
    id INT IDENTITY(1, 1) PRIMARY KEY,
    key NVARCHAR(255) NOT NULL UNIQUE,
    name NVARCHAR(255) NULL,
    -- Optional name/description for the API key
    warehouse_id INT NOT NULL,
    -- Single warehouse ID mapped to this API key
    active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2 NOT NULL DEFAULT GETDATE()
);

-- Example data
INSERT INTO
    api_keys (key, name, warehouse_id, active)
VALUES
    (
        'api-key-warehouse-1',
        'API Key for Warehouse 1',
        1,
        1
    ),
    (
        'api-key-warehouse-2',
        'API Key for Warehouse 2',
        2,
        1
    ),
    (
        'api-key-warehouse-3',
        'API Key for Warehouse 3',
        3,
        1
    );

-- Index for performance
CREATE INDEX IX_api_keys_key ON api_keys(key);

CREATE INDEX IX_api_keys_active ON api_keys(active);

CREATE INDEX IX_api_keys_warehouse_id ON api_keys(warehouse_id);
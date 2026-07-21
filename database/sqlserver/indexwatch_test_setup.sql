/*
Run this file in SSMS with Query > SQLCMD Mode enabled.
Replace the value below locally. Never commit a real password.
*/
:setvar INDEXWATCH_SCANNER_PASSWORD password123

USE [master];
GO

IF '$(INDEXWATCH_SCANNER_PASSWORD)' = 'CHANGE_ME_WITH_A_STRONG_LOCAL_PASSWORD'
BEGIN
    THROW 50000, 'Replace INDEXWATCH_SCANNER_PASSWORD before running this script.', 1;
END;
GO

IF DB_ID(N'IndexWatch_Test') IS NULL
BEGIN
    CREATE DATABASE [IndexWatch_Test];
END;
GO

IF SUSER_ID(N'indexwatch_scanner') IS NULL
BEGIN
    CREATE LOGIN [indexwatch_scanner]
        WITH PASSWORD = '$(INDEXWATCH_SCANNER_PASSWORD)',
             CHECK_POLICY = ON,
             CHECK_EXPIRATION = OFF;
END;
GO

GRANT VIEW SERVER PERFORMANCE STATE TO [indexwatch_scanner];
GO

USE [IndexWatch_Test];
GO

IF USER_ID(N'indexwatch_scanner') IS NULL
BEGIN
    CREATE USER [indexwatch_scanner] FOR LOGIN [indexwatch_scanner];
END;
GO

GRANT CONNECT TO [indexwatch_scanner];
GRANT VIEW DEFINITION TO [indexwatch_scanner];
GRANT VIEW DATABASE PERFORMANCE STATE TO [indexwatch_scanner];
-- This is a non-production database. SELECT lets dm_db_stats_properties expose
-- statistics evidence without granting any write or DDL permission.
GRANT SELECT TO [indexwatch_scanner];
GO

IF OBJECT_ID(N'dbo.IndexWatchOrders', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.IndexWatchOrders
    (
        id bigint IDENTITY(1, 1) NOT NULL,
        customer_id int NOT NULL,
        status tinyint NOT NULL,
        created_at datetime2(0) NOT NULL,
        payload char(200) NOT NULL,
        CONSTRAINT PK_IndexWatchOrders PRIMARY KEY CLUSTERED (id)
    );

    CREATE INDEX IX_IndexWatchOrders_Customer
        ON dbo.IndexWatchOrders (customer_id, created_at)
        INCLUDE (status)
        WITH (FILLFACTOR = 90);

    CREATE INDEX IX_IndexWatchOrders_Status
        ON dbo.IndexWatchOrders (status, created_at);
END;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.IndexWatchOrders)
BEGIN
    INSERT dbo.IndexWatchOrders (customer_id, status, created_at, payload)
    SELECT TOP (100000)
        ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) % 5000,
        ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) % 5,
        DATEADD(second, -ROW_NUMBER() OVER (ORDER BY (SELECT NULL)), SYSUTCDATETIME()),
        REPLICATE('X', 200)
    FROM sys.all_objects AS a
    CROSS JOIN sys.all_objects AS b;

    UPDATE dbo.IndexWatchOrders
    SET status = (status + 1) % 5
    WHERE id % 3 = 0;
END;
GO

SELECT
    DB_NAME() AS database_name,
    SUSER_SNAME() AS setup_login,
    COUNT_BIG(*) AS sample_rows
FROM dbo.IndexWatchOrders;
GO

-- Migration (one-time): profile default address + PCI-safe card stub columns
-- For an already-existing bookstore database. Fresh installs get these columns
-- automatically from db/schema.sql. Run only once.
ALTER TABLE users
  ADD COLUMN ShipStreet   VARCHAR(160) NULL,
  ADD COLUMN ShipCity     VARCHAR(60)  NULL,
  ADD COLUMN ShipProvince VARCHAR(60)  NULL,
  ADD COLUMN ShipPostcode VARCHAR(12)  NULL,
  ADD COLUMN ShipCountry  VARCHAR(60)  NULL DEFAULT 'South Africa',
  ADD COLUMN CardToken    VARCHAR(100) NULL,
  ADD COLUMN CardLast4    VARCHAR(4)   NULL,
  ADD COLUMN CardExpiry   CHAR(5)      NULL;

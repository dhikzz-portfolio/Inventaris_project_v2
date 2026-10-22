CREATE TABLE IF NOT EXISTS "asset_unit_codes" (
  "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  "asset_unit_id" integer NOT NULL,
  "inventory_code" varchar(8) NOT NULL UNIQUE,
  "room_id" integer NOT NULL,
  "unit_no" tinyint unsigned NOT NULL,
  "issued_at" datetime NOT NULL,
  "revoked_at" datetime,
  "reason" varchar(30) NOT NULL DEFAULT 'PENEMPATAN',
  "created_at" datetime,
  "updated_at" datetime,
  UNIQUE("room_id", "unit_no")
);

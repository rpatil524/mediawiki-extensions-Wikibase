-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: repo/sql/abstract/wb_id_counters.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/wb_id_counters (
  id_type BLOB NOT NULL,
  id_value INTEGER UNSIGNED NOT NULL,
  PRIMARY KEY(id_type)
);

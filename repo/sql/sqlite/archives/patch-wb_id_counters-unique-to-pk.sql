-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: repo/sql/abstractSchemaChanges/patch-wb_id_counters-unique-to-pk.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TEMPORARY TABLE /*_*/__temp__wb_id_counters AS
SELECT
  id_value,
  id_type
FROM /*_*/wb_id_counters;

DROP TABLE /*_*/wb_id_counters;


CREATE TABLE /*_*/wb_id_counters (
  id_type BLOB NOT NULL,
  id_value INTEGER UNSIGNED NOT NULL,
  PRIMARY KEY(id_type)
);

INSERT INTO /*_*/wb_id_counters (id_value, id_type)
SELECT
  id_value,
  id_type
FROM
  /*_*/__temp__wb_id_counters;

DROP TABLE /*_*/__temp__wb_id_counters;

-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: repo/sql/abstractSchemaChanges/patch-wb_changes-change_timestamp.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE wb_changes
  ALTER change_time TYPE TIMESTAMPTZ;
ALTER TABLE wb_changes
  ALTER change_time TYPE TIMESTAMPTZ;

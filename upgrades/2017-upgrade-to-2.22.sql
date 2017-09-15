ALTER TABLE custom_field_value ADD COLUMN value_date_new DATE;
UPDATE custom_field_value SET value_date_new = DATE_ADD(STR_TO_DATE(value_date, '-%m-%d'), INTERVAL 1584 YEAR) WHERE value_date LIKE '-%';
UPDATE custom_field_value SET value_date_new = STR_TO_DATE(value_date, '%Y-%m-%d') WHERE value_date NOT LIKE '-%';
ALTER TABLE custom_field_value DROP COLUMN value_date;
ALTER TABLE custom_field_value ADD COLUMN value_date DATE;
UPDATE custom_field_value SET value_date = value_date_new;
ALTER TABLE custom_field_value DROP COLUMN value_date_new;

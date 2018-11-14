SELECT
  table_schema table_schema,
  table_name table_name,
  engine engine,
  table_rows table_rows,
  round(data_length / 1024)                  data_length,
  round(index_length / 1024)                 index_length,
  round((data_length + index_length) / 1024) total_length,
  table_collation table_collation
FROM information_schema.tables
WHERE table_schema = :db
ORDER BY total_length DESC
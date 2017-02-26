SELECT
  table_schema,
  round(sum(data_length / 1024))                  data_length,
  round(sum(index_length / 1024))                 index_length,
  round(sum((data_length + index_length) / 1024)) total_length
FROM information_schema.tables
GROUP BY 1
ORDER BY total_length DESC
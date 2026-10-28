-- Um grupo de páginas já lido, guardado só para o trabalho sobreviver a uma
-- interrupção: o cron mata o trabalhador em trinta minutos e um laudo longo não
-- cabe numa corrida. As linhas somem quando o documento sai da fila.
CREATE TABLE page_group_readings (
  id INTEGER PRIMARY KEY,
  document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  reader TEXT NOT NULL CHECK (reader IN ('A','B','C')),
  group_index INTEGER NOT NULL,
  pages_per_call INTEGER NOT NULL,
  model TEXT NOT NULL,
  prompt_version TEXT NOT NULL,
  payload_enc TEXT NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE (document_id, reader, group_index, pages_per_call, model, prompt_version)
);

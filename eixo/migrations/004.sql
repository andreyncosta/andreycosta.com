-- A transcrição é uma leitura como as outras: tem fornecedor, modelo, custo e erro, e
-- precisa aparecer no histórico do documento. O CHECK da coluna reader não previa 'T', e no
-- SQLite um CHECK só muda refazendo a tabela.
CREATE TABLE readings_novo (
  id INTEGER PRIMARY KEY,
  document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  reader TEXT NOT NULL CHECK (reader IN ('A','B','C','T')),
  provider TEXT NOT NULL,
  model TEXT NOT NULL,
  input_mode TEXT NOT NULL,
  prompt_version TEXT NOT NULL,
  ok INTEGER NOT NULL,
  input_tokens INTEGER,
  output_tokens INTEGER,
  payload_enc TEXT,
  error_code TEXT,
  created_at TEXT NOT NULL
);

INSERT INTO readings_novo (id, document_id, reader, provider, model, input_mode, prompt_version,
                           ok, input_tokens, output_tokens, payload_enc, error_code, created_at)
SELECT id, document_id, reader, provider, model, input_mode, prompt_version,
       ok, input_tokens, output_tokens, payload_enc, error_code, created_at FROM readings;

DROP TABLE readings;
ALTER TABLE readings_novo RENAME TO readings;

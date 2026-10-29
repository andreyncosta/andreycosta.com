-- Catálogo de faixas por laboratório. O mesmo laboratório imprime a mesma faixa
-- para o mesmo exame, laudo após laudo; guardar o que ele já imprimiu deixa o
-- cartão mostrar uma faixa quando um laudo seu veio sem ela. Nada aqui é
-- inventado: é sempre uma faixa que aquele laboratório imprimiu para você, e o
-- cartão diz que ela veio de um laudo anterior.
CREATE TABLE lab_ranges (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  lab_key TEXT NOT NULL,
  exam_key TEXT NOT NULL,
  unit_key TEXT NOT NULL,
  condition_key TEXT NOT NULL,
  payload_enc TEXT NOT NULL,
  seen INTEGER NOT NULL DEFAULT 1,
  first_seen TEXT NOT NULL,
  last_seen TEXT NOT NULL,
  UNIQUE (user_id, lab_key, exam_key, unit_key, condition_key)
);
CREATE INDEX lab_ranges_lookup ON lab_ranges(user_id, lab_key, exam_key, unit_key);

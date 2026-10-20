-- Eixo: initial schema. Health content lives only in *_enc columns (sealed with the
-- owner's data key); plain columns hold ids, states, dates and keyed hashes.

CREATE TABLE users (
  id INTEGER PRIMARY KEY,
  email TEXT NOT NULL UNIQUE,
  email_verified_at TEXT,
  password_hash TEXT,
  totp_secret_enc TEXT,
  totp_enabled INTEGER NOT NULL DEFAULT 0,
  totp_last_step INTEGER NOT NULL DEFAULT 0,
  recovery_codes_json TEXT NOT NULL DEFAULT '[]',
  dek_wrapped TEXT NOT NULL,
  profile_enc TEXT,
  created_at TEXT NOT NULL,
  deleted_at TEXT
);

CREATE TABLE invites (
  id INTEGER PRIMARY KEY,
  email TEXT NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  created_at TEXT NOT NULL
);

-- Append-only consent log; the current state of a purpose is its latest row.
CREATE TABLE consents (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  purpose TEXT NOT NULL CHECK (purpose IN ('store','ai','share','research')),
  granted INTEGER NOT NULL CHECK (granted IN (0,1)),
  text_version TEXT NOT NULL,
  at TEXT NOT NULL
);
CREATE INDEX consents_user ON consents(user_id, purpose, id);

CREATE TABLE login_attempts (
  id INTEGER PRIMARY KEY,
  key_hash TEXT NOT NULL,
  at TEXT NOT NULL
);
CREATE INDEX login_attempts_key ON login_attempts(key_hash, at);

CREATE TABLE documents (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  sha256 TEXT NOT NULL,
  mime TEXT NOT NULL,
  size INTEGER NOT NULL,
  blob_name TEXT NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('stored','queued','processing','done','failed','duplicate')),
  doc_type TEXT,
  collected_on TEXT,
  lab_hash TEXT,
  duplicate_of INTEGER REFERENCES documents(id) ON DELETE SET NULL,
  meta_enc TEXT,
  text_enc TEXT,
  error_code TEXT,
  uploaded_at TEXT NOT NULL,
  processed_at TEXT
);
CREATE UNIQUE INDEX documents_user_sha ON documents(user_id, sha256);
CREATE INDEX documents_user ON documents(user_id, uploaded_at);

CREATE TABLE jobs (
  id INTEGER PRIMARY KEY,
  document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  status TEXT NOT NULL CHECK (status IN ('pending','running','done','failed')),
  attempts INTEGER NOT NULL DEFAULT 0,
  run_after TEXT NOT NULL,
  locked_at TEXT,
  last_error TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX jobs_pending ON jobs(status, run_after);

-- One row per model reading of a document (A, B, tie-break C). No health data in plain columns.
CREATE TABLE readings (
  id INTEGER PRIMARY KEY,
  document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  reader TEXT NOT NULL CHECK (reader IN ('A','B','C')),
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

CREATE TABLE series (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  key_hash TEXT NOT NULL,
  merged_into INTEGER REFERENCES series(id) ON DELETE SET NULL,
  pinned INTEGER NOT NULL DEFAULT 0,
  payload_enc TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE UNIQUE INDEX series_user_key ON series(user_id, key_hash);

CREATE TABLE results (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  document_id INTEGER REFERENCES documents(id) ON DELETE CASCADE,
  series_id INTEGER REFERENCES series(id) ON DELETE SET NULL,
  status TEXT NOT NULL CHECK (status IN ('loaded','quarantine','corrected')),
  origin TEXT NOT NULL CHECK (origin IN ('extraction','patient_correction')),
  observed_on TEXT,
  replaces INTEGER REFERENCES results(id) ON DELETE SET NULL,
  payload_enc TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX results_user ON results(user_id, status, observed_on);
CREATE INDEX results_series ON results(series_id, observed_on);
CREATE INDEX results_document ON results(document_id);

CREATE TABLE medications (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  kind TEXT NOT NULL CHECK (kind IN ('medication','supplement')),
  payload_enc TEXT NOT NULL,
  created_at TEXT NOT NULL,
  archived_at TEXT
);

CREATE TABLE dose_events (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  medication_id INTEGER NOT NULL REFERENCES medications(id) ON DELETE CASCADE,
  type TEXT NOT NULL CHECK (type IN ('start','adjust','pause','resume','stop')),
  event_on TEXT NOT NULL,
  payload_enc TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX dose_events_user ON dose_events(user_id, event_on);

CREATE TABLE body_measurements (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  kind TEXT NOT NULL CHECK (kind IN ('bia','anthro')),
  measured_on TEXT NOT NULL,
  device_hash TEXT,
  document_id INTEGER REFERENCES documents(id) ON DELETE CASCADE,
  payload_enc TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX body_user ON body_measurements(user_id, kind, measured_on);

CREATE TABLE shares (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  code_hash TEXT,
  code_expires_at TEXT,
  scope TEXT NOT NULL CHECK (scope IN ('all','period')),
  scope_from TEXT,
  scope_to TEXT,
  payload_enc TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  revoked_at TEXT,
  created_at TEXT NOT NULL
);

-- Audit trail visible to the patient. Insert-only: rows are never edited, and only the
-- retention purge (older than the legal minimum) deletes them.
CREATE TABLE access_log (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL,
  actor TEXT NOT NULL CHECK (actor IN ('patient','doctor','system')),
  share_id INTEGER,
  action TEXT NOT NULL,
  object TEXT,
  at TEXT NOT NULL
);
CREATE INDEX access_log_user ON access_log(user_id, at);
CREATE TRIGGER access_log_no_update BEFORE UPDATE ON access_log
BEGIN SELECT RAISE(ABORT, 'access_log is insert-only'); END;

CREATE TABLE meta (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

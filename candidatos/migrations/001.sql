CREATE TABLE users (
 id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE COLLATE NOCASE, name TEXT NOT NULL,
 password TEXT, role TEXT NOT NULL CHECK(role IN ('admin','reviewer')),
 active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)), auth_version INTEGER NOT NULL DEFAULT 1,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE tokens (
 hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id), expires INTEGER NOT NULL,
 used_at TEXT, kind TEXT NOT NULL CHECK(kind IN ('invite','reset'))
);
CREATE TABLE candidates (
 id INTEGER PRIMARY KEY, year INTEGER NOT NULL, election TEXT NOT NULL, source_id TEXT NOT NULL,
 uf TEXT NOT NULL, party TEXT NOT NULL, office TEXT NOT NULL, name TEXT NOT NULL, full_name TEXT NOT NULL,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(year,election,source_id)
);
CREATE INDEX candidates_filter ON candidates(year,office,uf,name,id);
CREATE TABLE batches (
 id INTEGER PRIMARY KEY, actor INTEGER NOT NULL REFERENCES users(id), spec TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE assignments (
 id INTEGER PRIMARY KEY, candidate_id INTEGER NOT NULL REFERENCES candidates(id),
 user_id INTEGER NOT NULL REFERENCES users(id), batch_id INTEGER NOT NULL REFERENCES batches(id),
 status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','draft','done','returned')),
 version INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, ended_at TEXT
);
CREATE UNIQUE INDEX one_active_assignment ON assignments(candidate_id) WHERE ended_at IS NULL;
CREATE INDEX assignment_user ON assignments(user_id,ended_at,status);
CREATE TABLE evaluations (
 id INTEGER PRIMARY KEY, assignment_id INTEGER NOT NULL REFERENCES assignments(id),
 actor INTEGER NOT NULL REFERENCES users(id), ideological INTEGER CHECK(ideological IN (0,1)),
 profile INTEGER CHECK(profile BETWEEN -2 AND 2), status TEXT NOT NULL CHECK(status IN ('draft','done')),
 version INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(assignment_id,version), CHECK(status != 'done' OR (ideological IS NOT NULL AND profile IS NOT NULL))
);
CREATE TABLE events (
 id INTEGER PRIMARY KEY, actor INTEGER REFERENCES users(id), kind TEXT NOT NULL, data TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
INSERT INTO settings VALUES ('ideology_help','');
CREATE TABLE operations (id TEXT PRIMARY KEY, actor INTEGER NOT NULL REFERENCES users(id), result TEXT NOT NULL);
CREATE TABLE attempts (key TEXT PRIMARY KEY, hits INTEGER NOT NULL, expires INTEGER NOT NULL);
CREATE TRIGGER evaluations_no_update BEFORE UPDATE ON evaluations BEGIN SELECT RAISE(ABORT,'Immutable evaluations'); END;
CREATE TRIGGER evaluations_no_delete BEFORE DELETE ON evaluations BEGIN SELECT RAISE(ABORT,'Immutable evaluations'); END;
CREATE TRIGGER events_no_update BEFORE UPDATE ON events BEGIN SELECT RAISE(ABORT,'Immutable events'); END;
CREATE TRIGGER events_no_delete BEFORE DELETE ON events BEGIN SELECT RAISE(ABORT,'Immutable events'); END;

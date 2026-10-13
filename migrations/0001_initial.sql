CREATE TABLE rooms (
 id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, uuid TEXT UNIQUE,
 name TEXT, is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0,1)),
 status TEXT NOT NULL DEFAULT 'closed' CHECK(status IN ('closed','open','deleted')),
 created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE devices (
 id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
 status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('approved','pending','disapproved')),
 last_sync_at TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')),
 UNIQUE(id,room_id)
);
CREATE TABLE users (
 id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
 device_id INTEGER NOT NULL, is_primary INTEGER NOT NULL DEFAULT 0 CHECK(is_primary IN (0,1)), name TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')),
 FOREIGN KEY(device_id,room_id) REFERENCES devices(id,room_id) ON DELETE CASCADE, UNIQUE(id,device_id,room_id)
);
CREATE TABLE candidates (
 id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
 sequence INTEGER NOT NULL, name TEXT NOT NULL, name_key TEXT NOT NULL,
 admin_votes INTEGER NOT NULL DEFAULT 0 CHECK(admin_votes>=0),
 created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')),
 UNIQUE(room_id,name_key), UNIQUE(room_id,sequence), UNIQUE(id,room_id)
);
CREATE TABLE configs (
 id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
 key TEXT NOT NULL, value TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')),
 UNIQUE(room_id,key)
);
CREATE TABLE votes (
 id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
 device_id INTEGER NOT NULL, user_id INTEGER NOT NULL, candidate_id INTEGER NOT NULL,
 created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')),
 FOREIGN KEY(user_id,device_id,room_id) REFERENCES users(id,device_id,room_id) ON DELETE CASCADE,
 FOREIGN KEY(candidate_id,room_id) REFERENCES candidates(id,room_id) ON DELETE CASCADE,
 FOREIGN KEY(device_id,room_id) REFERENCES devices(id,room_id) ON DELETE CASCADE,
 UNIQUE(room_id,user_id,candidate_id)
);
CREATE INDEX devices_room_status ON devices(room_id,status);
CREATE INDEX users_room_device ON users(room_id,device_id);
CREATE INDEX votes_candidate ON votes(candidate_id);
CREATE INDEX votes_user ON votes(user_id);
CREATE INDEX votes_device ON votes(device_id);
CREATE INDEX rooms_activity ON rooms(is_active,updated_at);
CREATE UNIQUE INDEX projection_hash ON configs(value) WHERE key='view.hash';
CREATE VIEW candidate_results AS
 SELECT c.*, COUNT(v.id) AS user_votes, c.admin_votes+COUNT(v.id) AS total_votes,
 DENSE_RANK() OVER(PARTITION BY c.room_id ORDER BY c.admin_votes+COUNT(v.id) DESC) AS position
 FROM candidates c LEFT JOIN votes v ON v.candidate_id=c.id GROUP BY c.id;
-- These checks run within the INSERT transaction, including concurrent requests.
CREATE TRIGGER validate_vote BEFORE INSERT ON votes BEGIN
 SELECT CASE WHEN NOT EXISTS(SELECT 1 FROM rooms WHERE id=NEW.room_id AND is_active=1 AND status='open') THEN RAISE(ABORT,'A votação está fechada!') END;
 SELECT CASE WHEN NOT EXISTS(SELECT 1 FROM devices WHERE id=NEW.device_id AND room_id=NEW.room_id AND status='approved') THEN RAISE(ABORT,'Dispositivo não autorizado!') END;
 SELECT CASE WHEN EXISTS(SELECT 1 FROM votes WHERE room_id=NEW.room_id AND user_id=NEW.user_id AND candidate_id=NEW.candidate_id) THEN RAISE(ABORT,'Já foi registrado um voto para este candidato!') END;
 SELECT CASE WHEN (SELECT COUNT(*) FROM votes WHERE room_id=NEW.room_id AND user_id=NEW.user_id)>=COALESCE((SELECT CAST(value AS INTEGER) FROM configs WHERE room_id=NEW.room_id AND key='votes.num_candidates'),1) THEN RAISE(ABORT,'Seus votos já foram registrados. Não é possível votar novamente!') END;
END;
CREATE TRIGGER record_phase AFTER UPDATE OF status ON rooms WHEN NEW.status<>OLD.status AND NEW.status IN ('open','closed') BEGIN
 INSERT INTO configs(room_id,key,value) VALUES(NEW.id,'view.phase',CASE NEW.status WHEN 'open' THEN 'voting' ELSE 'results' END)
 ON CONFLICT(room_id,key) DO UPDATE SET value=excluded.value,updated_at=datetime('now');
END;
CREATE TRIGGER close_after_vote AFTER INSERT ON votes BEGIN
 UPDATE rooms SET status='closed',updated_at=datetime('now')
 WHERE id=NEW.room_id AND status='open'
 AND COALESCE((SELECT value FROM configs WHERE room_id=NEW.room_id AND key='votes.room_auto_close'),'1') IN ('1','true')
 AND NOT EXISTS(SELECT 1 FROM users u JOIN devices d ON d.id=u.device_id WHERE u.room_id=NEW.room_id AND d.status='approved'
 AND (SELECT COUNT(*) FROM votes v WHERE v.user_id=u.id)<COALESCE((SELECT CAST(value AS INTEGER) FROM configs WHERE room_id=NEW.room_id AND key='votes.num_candidates'),1));
END;

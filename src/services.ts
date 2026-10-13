import { all, one, stmt, type Database, type Row } from "./db.ts";
export const definitions: Row = {
  candidates: {
    title: "Candidatos",
    items: {
      repeated_names: {
        title: "Inserir nome repetido",
        type: "options",
        options: { block: "Bloquear", vote: "Adicionar 1 voto" },
        default: "block",
      },
      insert_number: {
        title: "Inserir número",
        type: "options",
        options: {
          block: "Bloquear",
          allow: "Permitir",
          vote: "Adicionar 1 voto ao candidato correspondente",
        },
        default: "block",
      },
      initial_votes: {
        title: "Qtd. inicial de votos",
        type: "integer",
        default: 0,
      },
    },
  },
  votes: {
    title: "Votação",
    items: {
      room_auto_close: {
        title: "Fechar a sala automaticamente após todos votarem?",
        type: "boolean",
        default: "true",
      },
      num_candidates: {
        title: "Qtd. candidatos a serem escolhidos",
        type: "integer",
        default: 1,
      },
    },
  },
};
export const bool = (v: unknown) =>
  v === true || v === 1 || v === "1" || v === "true";
export function configDefinition(key: string): Row | undefined {
  const [group, item] = key.split(".");
  return definitions[group]?.items[item];
}
export async function configValues(db: Database, room: number): Promise<Row> {
  const rows = await all(
    db,
    "SELECT key,value FROM configs WHERE room_id=?",
    room,
  );
  const values: Row = Object.fromEntries(rows.map((r) => [r.key, r.value]));
  for (const [g, group] of Object.entries(definitions))
    for (const [k, item] of Object.entries(group.items as Row)) {
      const key = `${g}.${k}`;
      const value = values[key] ?? item.default;
      values[key] =
        item.type === "boolean"
          ? bool(value)
          : item.type === "integer"
            ? Number(value)
            : value;
    }
  return values;
}
export function configPayload(values: Row) {
  const result = structuredClone(definitions);
  for (const [g, group] of Object.entries(result))
    for (const [k, item] of Object.entries(group.items as Row))
      item.value = values[`${g}.${k}`];
  return result;
}
export const configStatement = (
  db: Database,
  room: number,
  key: string,
  value: unknown,
) =>
  stmt(
    db,
    "INSERT INTO configs(room_id,key,value) VALUES(?,?,?) ON CONFLICT(room_id,key) DO UPDATE SET value=excluded.value,updated_at=datetime('now')",
    room,
    key,
    String(value),
  );
export function closeStatement(db: Database, room: number) {
  return stmt(
    db,
    `UPDATE rooms SET status='closed',updated_at=datetime('now') WHERE id=? AND status='open'
 AND COALESCE((SELECT value FROM configs WHERE room_id=rooms.id AND key='votes.room_auto_close'),'1') IN ('1','true')
 AND NOT EXISTS(SELECT 1 FROM users u JOIN devices d ON d.id=u.device_id WHERE u.room_id=rooms.id AND d.status='approved'
 AND (SELECT COUNT(*) FROM votes v WHERE v.user_id=u.id)<COALESCE((SELECT CAST(value AS INTEGER) FROM configs WHERE room_id=rooms.id AND key='votes.num_candidates'),1))`,
    room,
  );
}
export function initials(name: string) {
  const words = name.trim().split(/\s+/);
  return (
    (Array.from(words[0])[0] ?? "") +
    (words.length > 1 ? (Array.from(words.at(-1)!)[0] ?? "") : "")
  ).toUpperCase();
}
export function candidateJSON(row: Row): Row {
  const { name_key, ...result } = row;
  return { ...result, initials: initials(row.name) };
}
export const candidate = async (db: Database, room: number, cid: number) => {
  const row = await one(
    db,
    "SELECT * FROM candidate_results WHERE room_id=? AND id=?",
    room,
    cid,
  );
  return row ? candidateJSON(row) : null;
};
export async function projection(db: Database, hash: string) {
  if (!/^[a-f0-9]{64}$/.test(hash)) return null;
  // Single SQL statement gives a coherent snapshot of phase, results and participation.
  const row = await one(
    db,
    `SELECT json_object('phase',CASE WHEN r.status='open' THEN 'voting'
 WHEN (SELECT value FROM configs WHERE room_id=r.id AND key='view.phase')='results' THEN 'results'
 WHEN NOT EXISTS(SELECT 1 FROM configs WHERE room_id=r.id AND key='view.phase') AND EXISTS(SELECT 1 FROM votes WHERE room_id=r.id) THEN 'results' ELSE 'preparing' END,
 'room',json_object('id',r.id,'name',r.name,'code',r.code,'status',r.status,'is_active',json('true'),
 'candidates',json((SELECT json_group_array(json(item)) FROM (SELECT CASE WHEN r.status='closed' AND
 ((SELECT value FROM configs WHERE room_id=r.id AND key='view.phase')='results' OR (NOT EXISTS(SELECT 1 FROM configs WHERE room_id=r.id AND key='view.phase') AND EXISTS(SELECT 1 FROM votes WHERE room_id=r.id)))
 THEN json_object('id',c.id,'name',c.name,'sequence',c.sequence,'total_votes',c.total_votes)
 ELSE json_object('id',c.id,'name',c.name,'sequence',c.sequence) END AS item FROM candidate_results c WHERE c.room_id=r.id ORDER BY c.sequence,c.id))),
 'devices',json((SELECT json_group_array(json(item)) FROM (SELECT json_object('status',d.status,'users',json((SELECT json_group_array(json_object('id',u.id,'name',u.name,'votes_count',(SELECT COUNT(*) FROM votes WHERE user_id=u.id))) FROM (SELECT * FROM users WHERE device_id=d.id ORDER BY id) u))) AS item FROM devices d WHERE d.room_id=r.id AND d.status IN ('approved','pending') ORDER BY d.id)))),
 'configs',json_object('votes',json_object('items',json_object('num_candidates',json_object('value',MAX(1,COALESCE((SELECT CAST(value AS INTEGER) FROM configs WHERE room_id=r.id AND key='votes.num_candidates'),1))))))) AS payload
 FROM rooms r JOIN configs link ON link.room_id=r.id AND link.key='view.hash' WHERE link.value=? AND r.is_active=1 AND r.status IN ('open','closed')`,
    hash,
  );
  return row ? JSON.parse(row.payload) : null;
}
export async function maintenance(db: Database) {
  // Child activity matches the legacy policy; polling presence does not extend retention.
  const activity = `AND NOT EXISTS(SELECT 1 FROM devices WHERE room_id=rooms.id AND updated_at>=?) AND NOT EXISTS(SELECT 1 FROM users WHERE room_id=rooms.id AND updated_at>=?) AND NOT EXISTS(SELECT 1 FROM candidates WHERE room_id=rooms.id AND updated_at>=?) AND NOT EXISTS(SELECT 1 FROM configs WHERE room_id=rooms.id AND updated_at>=?) AND NOT EXISTS(SELECT 1 FROM votes WHERE room_id=rooms.id AND updated_at>=?)`;
  const cutoff = (hours: number) =>
    new Date(Date.now() - hours * 3600000)
      .toISOString()
      .slice(0, 19)
      .replace("T", " ");
  const expired = cutoff(48);
  const inactive = cutoff(8);
  // Temporary status excludes rooms from API before cleanup; all statements share a transaction.
  await db.batch([
    stmt(
      db,
      `UPDATE rooms SET status='deleted',updated_at=datetime('now') WHERE status<>'deleted' AND is_active=0 AND updated_at<? ${activity}`,
      expired,
      ...Array(5).fill(expired),
    ),
    ...["votes", "users", "devices", "candidates", "configs"].map((table) =>
      stmt(
        db,
        `DELETE FROM ${table} WHERE room_id IN (SELECT id FROM rooms WHERE status='deleted')`,
      ),
    ),
    stmt(
      db,
      `UPDATE rooms SET is_active=0,updated_at=datetime('now') WHERE is_active=1 AND updated_at<? ${activity}`,
      inactive,
      ...Array(5).fill(inactive),
    ),
  ]);
}

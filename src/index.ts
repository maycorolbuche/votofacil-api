import {
  all,
  one,
  run,
  stmt,
  required,
  id,
  ApiError,
  now,
  type Env,
  type Row,
} from "./db.ts";
import { authenticate, sign, randomHex, roomCode } from "./auth.ts";
import {
  definitions,
  configDefinition,
  configValues,
  configPayload,
  configStatement,
  closeStatement,
  candidate,
  candidateJSON,
  projection,
  maintenance,
  bool,
} from "./services.ts";

async function body(request: Request): Promise<Row> {
  const text = await request.text();
  if (!text) return {};
  const type = request.headers.get("Content-Type") ?? "";
  if (type.includes("application/x-www-form-urlencoded"))
    return Object.fromEntries(new URLSearchParams(text));
  try {
    const data = JSON.parse(text);
    if (!data || Array.isArray(data) || typeof data !== "object") throw Error();
    return data;
  } catch {
    throw new ApiError("Corpo da requisição inválido.", 400);
  }
}
async function entity(env: Env, table: string, room: number, value: unknown) {
  const row = await one(
    env.DB,
    `SELECT * FROM ${table} WHERE room_id=? AND id=?`,
    room,
    id(value),
  );
  if (!row) throw new ApiError("Registro não encontrado.");
  return row;
}
const nameKey = (name: string) =>
  name.normalize("NFC").toLocaleLowerCase("pt-BR");
function integer(v: unknown, min = 0) {
  const n = Number(v);
  if (!Number.isSafeInteger(n) || n < min)
    throw new ApiError("Valor inteiro inválido.");
  return n;
}
function name(v: unknown, message = "Nome não informado!") {
  const n = required(v, message);
  if (Array.from(n).length > 255)
    throw new ApiError("Nome deve ter no máximo 255 caracteres.");
  return n;
}
async function route(request: Request, env: Env): Promise<unknown> {
  const path =
    new URL(request.url).pathname.replace(/\/+/g, "/").replace(/\/$/, "") ||
    "/";
  const method = request.method;
  const db = env.DB;
  if (method === "GET" && path === "/") return [];
  if (method === "GET" && path.startsWith("/view/")) {
    const data = await projection(db, path.slice(6));
    if (!data) throw new ApiError("Link de projeção indisponível.", 404);
    return data;
  }
  if (method === "POST" && path === "/admin/room") {
    // Validate the secret before creating any records.
    await sign({ type: "probe" }, env.JWT_SECRET);
    let room: Row | null = null;
    for (let len = 5; len < 10; len++) {
      try {
        room = (
          await all(
            db,
            "INSERT INTO rooms(code) VALUES(?) RETURNING *",
            roomCode(len),
          )
        )[0];
        break;
      } catch (e) {
        if (!String(e).includes("UNIQUE")) throw e;
      }
    }
    if (!room) throw new ApiError("Não foi possível criar uma sala.");
    await db.batch(
      Object.entries(definitions).flatMap(([g, group]) =>
        Object.entries(group.items as Row).map(([k, item]) =>
          configStatement(
            db,
            room!.id,
            `${g}.${k}`,
            item.type === "boolean" ? "1" : item.default,
          ),
        ),
      ),
    );
    return {
      ...room,
      token: await sign(
        { type: "admin", room_id: room.id, code: room.code },
        env.JWT_SECRET,
      ),
    };
  }
  if (method === "POST" && path === "/user/device") {
    const data = await body(request);
    const code = required(
      data.room_code,
      "Código da sala não informado!",
    ).toUpperCase();
    const room = await one(db, "SELECT * FROM rooms WHERE code=?", code);
    if (!room) throw new ApiError(`Sala "${code}" não encontrada!`);
    if (!room.is_active || room.status === "deleted")
      throw new ApiError("Esta sala não está mais ativa!");
    await sign({ type: "probe" }, env.JWT_SECRET);
    const device = (
      await all(
        db,
        "INSERT INTO devices(room_id) VALUES(?) RETURNING *",
        room.id,
      )
    )[0];
    delete device.last_sync_at;
    return {
      ...device,
      token: await sign(
        {
          type: "user",
          device_id: device.id,
          room_id: room.id,
          code: room.code,
        },
        env.JWT_SECRET,
      ),
    };
  }
  const routes: Record<string, string[]> = {
    "/admin/room": ["GET", "PATCH", "DELETE"],
    "/admin/candidate": ["POST", "PATCH", "DELETE"],
    "/admin/device": ["PATCH"],
    "/admin/user": ["POST", "PATCH", "DELETE"],
    "/admin/vote": ["DELETE"],
    "/admin/config": ["POST"],
    "/admin/view": ["POST", "DELETE"],
    "/admin/sync": ["GET"],
    "/user/device": ["GET", "DELETE"],
    "/user/user": ["POST"],
    "/user/vote": ["POST"],
    "/user/sync": ["GET"],
  };
  if (!routes[path]?.includes(method))
    throw new ApiError("Rota não encontrada.", 404);
  const admin = path.startsWith("/admin/");
  const auth = await authenticate(request, env, admin ? "admin" : "user");
  const room = auth.room_id as number;
  const device = auth.device_id as number;
  const data = method === "GET" ? {} : await body(request);
  if (method === "GET" && (path === "/admin/room" || path === "/user/device"))
    return auth;
  if (path === "/admin/room") {
    if (method === "DELETE") {
      const result = await one(db, "SELECT * FROM rooms WHERE id=?", room);
      await run(db, "DELETE FROM rooms WHERE id=?", room);
      return result;
    }
    const fields: Row = {};
    if ("name" in data)
      fields.name = data.name === null ? null : name(data.name);
    if ("status" in data) {
      if (!["open", "closed"].includes(data.status))
        throw new ApiError("Status inválido.");
      fields.status = data.status;
    }
    if (Object.keys(fields).length)
      await run(
        db,
        `UPDATE rooms SET ${Object.keys(fields)
          .map((k) => `${k}=?`)
          .join(",")},updated_at=datetime('now') WHERE id=?`,
        ...Object.values(fields),
        room,
      );
    return one(db, "SELECT * FROM rooms WHERE id=?", room);
  }
  if (path === "/user/device" && method === "DELETE") {
    const result = await entity(env, "devices", room, device);
    await db.batch([
      stmt(db, "DELETE FROM devices WHERE id=? AND room_id=?", device, room),
      closeStatement(db, room),
    ]);
    delete result.last_sync_at;
    return result;
  }
  if (path === "/admin/device") {
    let did = data.id;
    if ("user_id" in data)
      did = (await entity(env, "users", room, data.user_id)).device_id;
    const result = await entity(env, "devices", room, did);
    if (!["approved", "pending", "disapproved"].includes(data.status))
      throw new ApiError("Status inválido.");
    await db.batch([
      stmt(
        db,
        "UPDATE devices SET status=?,updated_at=datetime('now') WHERE id=? AND room_id=?",
        data.status,
        result.id,
        room,
      ),
      closeStatement(db, room),
    ]);
    const updated = await entity(env, "devices", room, result.id);
    delete updated.last_sync_at;
    return updated;
  }
  if (path === "/admin/user" || path === "/user/user") {
    if (method === "POST") {
      const did = admin ? id(data.device_id) : device;
      await entity(env, "devices", room, did);
      const n = name(data.name);
      const result = (
        await all(
          db,
          "INSERT INTO users(room_id,device_id,name,is_primary) VALUES(?,?,?,?) RETURNING *",
          room,
          did,
          n,
          admin ? 0 : 1,
        )
      )[0];
      if (admin) await db.batch([closeStatement(db, room)]);
      return result;
    }
    const user = await entity(env, "users", room, data.id);
    if (method === "PATCH") {
      await run(
        db,
        "UPDATE users SET name=?,updated_at=datetime('now') WHERE id=? AND room_id=?",
        name(data.name),
        user.id,
        room,
      );
      return entity(env, "users", room, user.id);
    }
    await db.batch([
      user.is_primary
        ? stmt(
            db,
            "DELETE FROM devices WHERE id=? AND room_id=?",
            user.device_id,
            room,
          )
        : stmt(db, "DELETE FROM users WHERE id=? AND room_id=?", user.id, room),
      closeStatement(db, room),
    ]);
    return user;
  }
  if (path === "/admin/config") {
    const key = required(data.key, "Chave não informada!");
    const def = configDefinition(key);
    if (!def) throw new ApiError(`Chave "${key}" não existe!`);
    let value: unknown = data.value;
    if (def.type === "integer")
      value = integer(value, key === "votes.num_candidates" ? 1 : 0);
    if (def.type === "boolean") {
      if (
        ![true, false, 1, 0, "1", "0", "true", "false"].includes(value as any)
      )
        throw new ApiError("Valor booleano inválido.");
      value = bool(value) ? "1" : "0";
    }
    if (def.type === "options" && !Object.hasOwn(def.options, String(value)))
      throw new ApiError("Opção inválida.");
    await db.batch([
      configStatement(db, room, key, value),
      closeStatement(db, room),
    ]);
    return one(
      db,
      "SELECT * FROM configs WHERE room_id=? AND key=?",
      room,
      key,
    );
  }
  if (path === "/admin/view") {
    if (method === "POST") {
      await db.batch([configStatement(db, room, "view.hash", randomHex())]);
      return one(
        db,
        "SELECT * FROM configs WHERE room_id=? AND key='view.hash'",
        room,
      );
    }
    const result = await one(
      db,
      "SELECT * FROM configs WHERE room_id=? AND key='view.hash'",
      room,
    );
    await run(
      db,
      "DELETE FROM configs WHERE room_id=? AND key='view.hash'",
      room,
    );
    return result;
  }
  if (path === "/admin/vote") {
    const result = await all(db, "SELECT * FROM votes WHERE room_id=?", room);
    await db.batch([
      stmt(db, "DELETE FROM votes WHERE room_id=?", room),
      stmt(
        db,
        "UPDATE candidates SET admin_votes=0,updated_at=datetime('now') WHERE room_id=?",
        room,
      ),
      configStatement(
        db,
        room,
        "view.phase",
        (await one(db, "SELECT status FROM rooms WHERE id=?", room))?.status ===
          "open"
          ? "voting"
          : "preparing",
      ),
    ]);
    return result;
  }
  if (path === "/user/vote") {
    const uid = id(data.user_id),
      cid = id(data.candidate_id);
    const user = await entity(env, "users", room, uid);
    if (user.device_id !== device)
      throw new ApiError("Eleitor não encontrado!");
    await entity(env, "candidates", room, cid);
    return (
      await all(
        db,
        "INSERT INTO votes(room_id,device_id,user_id,candidate_id) VALUES(?,?,?,?) RETURNING *",
        room,
        device,
        uid,
        cid,
      )
    )[0];
  }
  if (path === "/admin/candidate") {
    if (method === "DELETE") {
      if (!Object.hasOwn(data, "id")) {
        const result = await all(
          db,
          "SELECT * FROM votes WHERE room_id=?",
          room,
        );
        await db.batch([
          stmt(db, "DELETE FROM candidates WHERE room_id=?", room),
          configStatement(
            db,
            room,
            "view.phase",
            (await one(db, "SELECT status FROM rooms WHERE id=?", room))
              ?.status === "open"
              ? "voting"
              : "preparing",
          ),
        ]);
        return result;
      }
      const result = await candidate(db, room, id(data.id));
      if (result)
        await run(
          db,
          "DELETE FROM candidates WHERE id=? AND room_id=?",
          result.id,
          room,
        );
      return result;
    }
    if (method === "PATCH") {
      const current = await entity(env, "candidates", room, data.id);
      const n = "name" in data ? name(data.name) : current.name;
      const manual =
        "admin_votes" in data ? integer(data.admin_votes) : current.admin_votes;
      await run(
        db,
        "UPDATE candidates SET name=?,name_key=?,admin_votes=?,updated_at=datetime('now') WHERE id=? AND room_id=?",
        n,
        nameKey(n),
        manual,
        current.id,
        room,
      );
      return candidate(db, room, current.id);
    }
    const values = await configValues(db, room);
    const insert = async (n: string) => {
      const rows = await all(
        db,
        "INSERT INTO candidates(room_id,name,name_key,sequence,admin_votes) SELECT ?,?,?,COALESCE(MAX(sequence),0)+1,? FROM candidates WHERE room_id=? RETURNING id",
        room,
        n,
        nameKey(n),
        values["candidates.initial_votes"],
        room,
      );
      return candidate(db, room, rows[0].id);
    };
    if (Array.isArray(data.name)) {
      const names = new Map<string, string>();
      for (const raw of data.name) {
        if (typeof raw !== "string") continue;
        const n = Array.from(raw.trim()).slice(0, 50).join("").trim();
        if (n && !names.has(nameKey(n))) names.set(nameKey(n), n);
      }
      const input = JSON.stringify(
        Array.from(names, ([key, name]) => ({ key, name })),
      );
      const ids = await all(
        db,
        `INSERT INTO candidates(room_id,name,name_key,sequence,admin_votes)
    SELECT ?,json_extract(j.value,'$.name'),json_extract(j.value,'$.key'),
     (SELECT COALESCE(MAX(sequence),0) FROM candidates WHERE room_id=?)+ROW_NUMBER() OVER(ORDER BY CAST(j.key AS INTEGER)),?
    FROM json_each(?) j WHERE NOT EXISTS(SELECT 1 FROM candidates c WHERE c.room_id=? AND c.name_key=json_extract(j.value,'$.key'))
    ORDER BY CAST(j.key AS INTEGER) LIMIT 100 RETURNING id`,
        room,
        room,
        values["candidates.initial_votes"],
        input,
        room,
      );
      const inserted = new Set(ids.map((r) => r.id));
      return (
        await all(
          db,
          "SELECT * FROM candidate_results WHERE room_id=? ORDER BY sequence",
          room,
        )
      )
        .filter((r) => inserted.has(r.id))
        .map(candidateJSON);
    }
    const n = name(data.name, "Nome do(a) candidato(a) não informado!");
    const numeric = Number.isFinite(Number(n));
    if (numeric && values["candidates.insert_number"] === "block")
      throw new ApiError("Não é possível inserir número!");
    let existing: Row | null;
    if (numeric && values["candidates.insert_number"] === "vote") {
      existing = await one(
        db,
        "SELECT * FROM candidates WHERE room_id=? AND sequence=?",
        room,
        Number(n),
      );
      if (!existing)
        throw new ApiError(`Não é encontrado candidato(a) de número ${n}!`);
    } else {
      existing = await one(
        db,
        "SELECT * FROM candidates WHERE room_id=? AND name_key=?",
        room,
        nameKey(n),
      );
      if (existing && values["candidates.repeated_names"] !== "vote")
        throw new ApiError(`Pessoa "${n}" já foi cadastrada!`);
    }
    if (existing) {
      await run(
        db,
        "UPDATE candidates SET admin_votes=admin_votes+1,updated_at=datetime('now') WHERE id=?",
        existing.id,
      );
      return candidate(db, room, existing.id);
    }
    return insert(n);
  }
  if (path === "/admin/sync") return adminSync(env, room);
  if (path === "/user/sync") return userSync(env, auth);
  throw new ApiError("Rota não encontrada.", 404);
}

async function adminSync(env: Env, roomId: number) {
  const db = env.DB;
  const room = await one(
    db,
    "SELECT id,code,is_active,status,uuid,name FROM rooms WHERE id=?",
    roomId,
  );
  if (!room) throw new ApiError("Sala não encontrada!");
  room.candidates = (
    await all(
      db,
      "SELECT * FROM candidate_results WHERE room_id=? ORDER BY position,sequence",
      roomId,
    )
  ).map((c) => {
    const item = candidateJSON(c);
    for (const k of ["created_at", "updated_at"]) delete item[k];
    return item;
  });
  const devices = await all(
    db,
    "SELECT id,room_id,status,last_sync_at FROM devices WHERE room_id=? ORDER BY id",
    roomId,
  );
  const users = await all(
    db,
    "SELECT u.id,u.device_id,u.name,u.is_primary,COUNT(v.id) AS votes_count FROM users u LEFT JOIN votes v ON v.user_id=u.id WHERE u.room_id=? GROUP BY u.id ORDER BY u.id",
    roomId,
  );
  const lastSync: Row = {};
  const resume: Row = {
    admin_votes: 0,
    user_votes: 0,
    total_votes: 0,
    total_candidates: room.candidates.length,
    total_users: users.length,
    total_devices: devices.length,
  };
  for (const status of ["approved", "pending", "disapproved"]) {
    resume[`total_${status}_devices`] = devices.filter(
      (d) => d.status === status,
    ).length;
    resume[`total_${status}_users`] = users.filter((u) =>
      devices.some((d) => d.id === u.device_id && d.status === status),
    ).length;
  }
  for (const c of room.candidates)
    for (const k of ["admin_votes", "user_votes", "total_votes"])
      resume[k] += c[k];
  room.devices = devices.map((d) => {
    if (d.last_sync_at)
      lastSync[d.id] = d.last_sync_at.replace(" ", "T") + ".000000Z";
    return {
      id: d.id,
      room_id: d.room_id,
      status: d.status,
      users: users.filter((u) => u.device_id === d.id),
    };
  });
  const values = await configValues(db, roomId);
  return {
    users_last_sync: Object.keys(lastSync).length ? lastSync : [],
    datetime: now(),
    room,
    configs: configPayload(values),
    view: { hash: values["view.hash"] ?? "" },
    resume,
  };
}
async function userSync(env: Env, auth: Row) {
  const db = env.DB,
    room = auth.room_id,
    device = auth.device_id;
  // At most one presence write per minute; updated_at retains business-activity semantics.
  await run(
    db,
    "UPDATE devices SET last_sync_at=datetime('now') WHERE id=? AND (last_sync_at IS NULL OR last_sync_at<datetime('now','-1 minute'))",
    device,
  );
  const r = await one(db, "SELECT name,status FROM rooms WHERE id=?", room);
  const users = await all(
    db,
    "SELECT id,name,is_primary FROM users WHERE device_id=? AND room_id=? ORDER BY id",
    device,
    room,
  );
  const data: Row = {
    datetime: now(),
    room_name: r?.name,
    status:
      users.length === 0
        ? "no-users"
        : auth.status !== "approved"
          ? "pending"
          : r?.status,
  };
  if (users.length) data.users = users;
  if (data.status === "open") {
    const values = await configValues(db, room);
    const max = values["votes.num_candidates"];
    const pending = await all(
      db,
      "SELECT u.id,u.name,u.is_primary,COUNT(v.id) AS votes_count FROM users u LEFT JOIN votes v ON v.user_id=u.id WHERE u.device_id=? AND u.room_id=? GROUP BY u.id HAVING COUNT(v.id)<? ORDER BY u.is_primary DESC,u.id",
      device,
      room,
      max,
    );
    if (!pending.length) data.status = "no-votes-left";
    else {
      data.user = pending[0];
      data.users_pending_count = pending.length;
      data.votes = (
        await all(
          db,
          "SELECT candidate_id FROM votes WHERE user_id=?",
          pending[0].id,
        )
      ).map((v) => v.candidate_id);
      data.votes_max = max;
      data.candidates = (
        await all(
          db,
          "SELECT id,sequence,name FROM candidates WHERE room_id=?",
          room,
        )
      ).sort((a, b) =>
        nameKey(a.name) < nameKey(b.name)
          ? -1
          : nameKey(a.name) > nameKey(b.name)
            ? 1
            : 0,
      );
    }
  }
  return data;
}
export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const origin = request.headers.get("Origin");
    const allowed = (env.CORS_ORIGINS ?? "*").split(",").map((s) => s.trim());
    const headers = new Headers({
      "Content-Type": "application/json; charset=utf-8",
      "Cache-Control": "no-store, private, max-age=0",
      Pragma: "no-cache",
      "X-Robots-Tag": "noindex, nofollow",
    });
    if (allowed.includes("*")) headers.set("Access-Control-Allow-Origin", "*");
    else if (origin && allowed.includes(origin)) {
      headers.set("Access-Control-Allow-Origin", origin);
      headers.set("Vary", "Origin");
    }
    headers.set(
      "Access-Control-Allow-Methods",
      "GET, POST, PATCH, DELETE, OPTIONS",
    );
    headers.set(
      "Access-Control-Allow-Headers",
      "Content-Type, Authorization, X-Requested-With",
    );
    headers.set("Access-Control-Max-Age", "86400");
    if (request.method === "OPTIONS")
      return new Response(null, { status: 204, headers });
    try {
      return new Response(JSON.stringify(await route(request, env)), {
        headers,
      });
    } catch (e) {
      let message = "Não foi possível processar a solicitação.";
      let status = 500;
      if (e instanceof ApiError) {
        message = e.message;
        status = e.status;
      } else {
        const detail = String(e);
        const known = [
          "A votação está fechada!",
          "Dispositivo não autorizado!",
          "Já foi registrado um voto para este candidato!",
          "Seus votos já foram registrados. Não é possível votar novamente!",
        ];
        const found = known.find((m) => detail.includes(m));
        if (found) message = found;
        else if (detail.includes("UNIQUE constraint"))
          message = "Registro duplicado.";
        else
          console.error("Falha na API", {
            path: new URL(request.url).pathname,
            error: e instanceof Error ? e.message : "unknown",
          });
      }
      return new Response(JSON.stringify({ error: message }), {
        status,
        headers,
      });
    }
  },
  async scheduled(_event: unknown, env: Env): Promise<void> {
    await maintenance(env.DB);
  },
};

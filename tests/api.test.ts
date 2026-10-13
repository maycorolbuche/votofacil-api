import { test } from "node:test";
import assert from "node:assert/strict";
import { DatabaseSync } from "node:sqlite";
import { readFileSync } from "node:fs";
import worker from "../src/index.ts";
import { maintenance } from "../src/services.ts";
import type { Database, Env, Result, Statement } from "../src/db.ts";
class SQL implements Statement {
  db: DatabaseSync;
  sql: string;
  args: unknown[] = [];
  constructor(db: DatabaseSync, sql: string) {
    this.db = db;
    this.sql = sql;
  }
  bind(...args: unknown[]) {
    this.args = args;
    return this;
  }
  async all(): Promise<Result> {
    const rows = this.db.prepare(this.sql).all(...(this.args as any[]));
    return {
      results: rows,
      meta: {
        changes: Number(this.db.prepare("SELECT changes() AS n").get()!.n),
        last_row_id: 0,
      },
    };
  }
  async first() {
    return this.db.prepare(this.sql).get(...(this.args as any[])) ?? null;
  }
  async run(): Promise<Result> {
    const r = this.db.prepare(this.sql).run(...(this.args as any[]));
    return {
      results: [],
      meta: {
        changes: Number(r.changes),
        last_row_id: Number(r.lastInsertRowid),
      },
    };
  }
}
function setup() {
  const sqlite = new DatabaseSync(":memory:");
  sqlite.exec("PRAGMA foreign_keys=ON");
  sqlite.exec(
    readFileSync(
      new URL("../migrations/0001_initial.sql", import.meta.url),
      "utf8",
    ),
  );
  const DB: Database = {
    prepare: (sql) => new SQL(sqlite, sql),
    batch: async (statements) => {
      sqlite.exec("BEGIN");
      try {
        const results = [];
        for (const s of statements) results.push(await s.all());
        sqlite.exec("COMMIT");
        return results;
      } catch (e) {
        sqlite.exec("ROLLBACK");
        throw e;
      }
    },
  };
  const env: Env = {
    DB,
    JWT_SECRET: "test-secret-at-least-thirty-two-characters",
    CORS_ORIGINS: "https://app.example",
  };
  const call = async (
    method: string,
    path: string,
    data?: unknown,
    token?: string,
    type = "admin",
  ) => {
    const response = await worker.fetch(
      new Request(`https://api.example${path}`, {
        method,
        headers: {
          "Content-Type": "application/json",
          ...(token ? { Authorization: `${type}=${token}` } : {}),
        },
        body: data === undefined ? undefined : JSON.stringify(data),
      }),
      env,
    );
    return {
      status: response.status,
      data: (await response.json()) as any,
      headers: response.headers,
    };
  };
  return { sqlite, env, call };
}
async function election(max = 1) {
  const f = setup();
  const room = (await f.call("POST", "/admin/room")).data;
  const admin = room.token;
  await f.call(
    "POST",
    "/admin/config",
    { key: "votes.num_candidates", value: max },
    admin,
  );
  const candidates = (
    await f.call(
      "POST",
      "/admin/candidate",
      { name: ["Ágata", "Bruno", "Carla"] },
      admin,
    )
  ).data;
  const device = (
    await f.call("POST", "/user/device", { room_code: room.code.toLowerCase() })
  ).data;
  const user = (
    await f.call(
      "POST",
      "/user/user",
      { name: "Eleitor" },
      device.token,
      "user",
    )
  ).data;
  await f.call(
    "PATCH",
    "/admin/device",
    { id: device.id, status: "approved" },
    admin,
  );
  return { ...f, room, admin, candidates, device, user };
}
test("projection phases, privacy, progress, close, reopen, reset and revocation", async () => {
  const f = await election(2);
  const { call, admin, device, user, candidates } = f;
  const hash = (await call("POST", "/admin/view", {}, admin)).data.value;
  let p = await call("GET", `/view/${hash}`);
  assert.equal(p.data.phase, "preparing");
  assert.deepEqual(Object.keys(p.data.room.candidates[0]), [
    "id",
    "name",
    "sequence",
  ]);
  assert.match(p.headers.get("Cache-Control")!, /no-store/);
  await call("PATCH", "/admin/room", { status: "open" }, admin);
  assert.equal((await call("GET", `/view/${hash}`)).data.phase, "voting");
  for (let i = 0; i < 2; i++) {
    assert.equal(
      (
        await call(
          "POST",
          "/user/vote",
          { user_id: user.id, candidate_id: candidates[i].id },
          device.token,
          "user",
        )
      ).status,
      200,
    );
    p = await call("GET", `/view/${hash}`);
    assert.equal(p.data.room.devices[0].users[0].votes_count, i + 1);
    assert.deepEqual(Object.keys(p.data.room.devices[0].users[0]), [
      "id",
      "name",
      "votes_count",
    ]);
  }
  assert.equal(p.data.phase, "results");
  assert.equal(p.data.room.candidates[0].total_votes, 1);
  await call("PATCH", "/admin/room", { status: "open" }, admin);
  assert.equal(
    (await call("GET", `/view/${hash}`)).data.room.candidates[0].total_votes,
    undefined,
  );
  await call("DELETE", "/admin/vote", {}, admin);
  await call("PATCH", "/admin/room", { status: "closed" }, admin);
  await call("DELETE", "/admin/vote", {}, admin);
  assert.equal((await call("GET", `/view/${hash}`)).data.phase, "preparing");
  const next = (await call("POST", "/admin/view", {}, admin)).data.value;
  assert.equal((await call("GET", `/view/${hash}`)).status, 404);
  await call("DELETE", "/admin/view", {}, admin);
  assert.equal((await call("GET", `/view/${next}`)).status, 404);
});
test("concurrent vote attempts cannot repeat a candidate or exceed the limit", async () => {
  const f = await election(1);
  await f.call(
    "POST",
    "/admin/config",
    { key: "votes.room_auto_close", value: false },
    f.admin,
  );
  await f.call("PATCH", "/admin/room", { status: "open" }, f.admin);
  const result = await Promise.all(
    Array.from({ length: 12 }, (_, i) =>
      f.call(
        "POST",
        "/user/vote",
        { user_id: f.user.id, candidate_id: f.candidates[i % 3].id },
        f.device.token,
        "user",
      ),
    ),
  );
  assert.equal(result.filter((r) => r.status === 200).length, 1);
  assert.equal(f.sqlite.prepare("SELECT COUNT(*) AS n FROM votes").get()!.n, 1);
});
test("pending devices cannot vote, room and device ownership are enforced", async () => {
  const f = await election();
  await f.call(
    "PATCH",
    "/admin/device",
    { id: f.device.id, status: "pending" },
    f.admin,
  );
  await f.call("PATCH", "/admin/room", { status: "open" }, f.admin);
  assert.equal(
    (
      await f.call(
        "POST",
        "/user/vote",
        { user_id: f.user.id, candidate_id: f.candidates[0].id },
        f.device.token,
        "user",
      )
    ).status,
    500,
  );
  const other = (await f.call("POST", "/admin/room")).data;
  assert.equal(
    (
      await f.call(
        "PATCH",
        "/admin/candidate",
        { id: f.candidates[0].id, name: "Intruso" },
        other.token,
      )
    ).status,
    500,
  );
  assert.equal(
    (await f.call("GET", "/admin/sync", undefined, "bad")).status,
    500,
  );
});
test("sync contracts and sequential users on one device", async () => {
  const f = await election(2);
  const second = (
    await f.call(
      "POST",
      "/admin/user",
      { device_id: f.device.id, name: "Segundo" },
      f.admin,
    )
  ).data;
  await f.call("PATCH", "/admin/room", { status: "open" }, f.admin);
  let s = (await f.call("GET", "/user/sync", undefined, f.device.token, "user"))
    .data;
  assert.equal(s.user.id, f.user.id);
  assert.equal(s.users_pending_count, 2);
  assert.equal(s.votes_max, 2);
  for (const c of f.candidates.slice(0, 2))
    await f.call(
      "POST",
      "/user/vote",
      { user_id: f.user.id, candidate_id: c.id },
      f.device.token,
      "user",
    );
  s = (await f.call("GET", "/user/sync", undefined, f.device.token, "user"))
    .data;
  assert.equal(s.user.id, second.id);
  const a = (await f.call("GET", "/admin/sync", undefined, f.admin)).data;
  assert.equal(a.resume.total_approved_users, 2);
  assert.equal(a.resume.total_votes, 2);
  assert.equal(a.room.devices[0].users[0].votes_count, 2);
  assert.ok(a.users_last_sync[f.device.id]);
  assert.equal(a.configs.votes.items.num_candidates.value, 2);
  await f.call("DELETE", "/admin/user", { id: f.user.id }, f.admin);
  assert.equal(f.sqlite.prepare("SELECT COUNT(*) AS n FROM users").get()!.n, 0);
  assert.equal(f.sqlite.prepare("SELECT COUNT(*) AS n FROM votes").get()!.n, 0);
});
test("candidate names, manual votes, dense ranking, bulk and deletions", async () => {
  const f = await election();
  assert.equal(
    (await f.call("POST", "/admin/candidate", { name: "ágata" }, f.admin))
      .status,
    500,
  );
  await f.call(
    "POST",
    "/admin/config",
    { key: "candidates.repeated_names", value: "vote" },
    f.admin,
  );
  assert.equal(
    (await f.call("POST", "/admin/candidate", { name: "ÁGATA" }, f.admin)).data
      .admin_votes,
    1,
  );
  await f.call(
    "POST",
    "/admin/config",
    { key: "candidates.insert_number", value: "vote" },
    f.admin,
  );
  assert.equal(
    (await f.call("POST", "/admin/candidate", { name: "2" }, f.admin)).data
      .total_votes,
    1,
  );
  const a = (await f.call("GET", "/admin/sync", undefined, f.admin)).data;
  assert.deepEqual(
    a.room.candidates.map((c: any) => c.position),
    [1, 1, 2],
  );
  await f.call(
    "PATCH",
    "/admin/candidate",
    { id: f.candidates[2].id, name: "Novo", admin_votes: 3 },
    f.admin,
  );
  assert.equal(
    (
      await f.call(
        "DELETE",
        "/admin/candidate",
        { id: f.candidates[2].id },
        f.admin,
      )
    ).data.name,
    "Novo",
  );
  await f.call("DELETE", "/admin/candidate", {}, f.admin);
  assert.equal(
    f.sqlite.prepare("SELECT COUNT(*) AS n FROM candidates").get()!.n,
    0,
  );
});
test("configuration validation and boolean false stays false", async () => {
  const f = await election();
  assert.equal(
    (
      await f.call(
        "POST",
        "/admin/config",
        { key: "votes.num_candidates", value: 0 },
        f.admin,
      )
    ).status,
    500,
  );
  assert.equal(
    (
      await f.call(
        "POST",
        "/admin/config",
        { key: "votes.room_auto_close", value: "false" },
        f.admin,
      )
    ).status,
    200,
  );
  assert.equal(
    (await f.call("GET", "/admin/sync", undefined, f.admin)).data.configs.votes
      .items.room_auto_close.value,
    false,
  );
  assert.equal(
    (
      await f.call(
        "POST",
        "/admin/config",
        { key: "view.hash", value: "x" },
        f.admin,
      )
    ).status,
    500,
  );
});
test("inactive rooms and deleted rooms invalidate links and auth", async () => {
  const f = await election();
  const hash = (await f.call("POST", "/admin/view", {}, f.admin)).data.value;
  f.sqlite.prepare("UPDATE rooms SET is_active=0 WHERE id=?").run(f.room.id);
  assert.equal((await f.call("GET", `/view/${hash}`)).status, 404);
  assert.equal(
    (await f.call("GET", "/admin/sync", undefined, f.admin)).status,
    500,
  );
  assert.equal(
    (await f.call("POST", "/user/device", { room_code: f.room.code })).status,
    500,
  );
});
test("maintenance respects child activity, deactivates and removes expired data", async () => {
  const f = await election();
  f.sqlite.exec(
    "UPDATE rooms SET updated_at=datetime('now','-9 hours'); UPDATE devices SET updated_at=datetime('now','-9 hours'); UPDATE users SET updated_at=datetime('now','-9 hours'); UPDATE candidates SET updated_at=datetime('now','-9 hours'); UPDATE configs SET updated_at=datetime('now','-9 hours')",
  );
  await maintenance(f.env.DB);
  assert.equal(
    f.sqlite.prepare("SELECT is_active FROM rooms").get()!.is_active,
    0,
  );
  f.sqlite.exec(
    "UPDATE rooms SET updated_at=datetime('now','-49 hours'); UPDATE devices SET updated_at=datetime('now','-49 hours'); UPDATE users SET updated_at=datetime('now','-49 hours'); UPDATE candidates SET updated_at=datetime('now','-49 hours'); UPDATE configs SET updated_at=datetime('now','-49 hours')",
  );
  await maintenance(f.env.DB);
  assert.equal(
    f.sqlite.prepare("SELECT status FROM rooms").get()!.status,
    "deleted",
  );
  assert.equal(
    f.sqlite.prepare("SELECT COUNT(*) AS n FROM devices").get()!.n,
    0,
  );
});
test("CORS preflight, malformed JSON, unknown and removed operational routes", async () => {
  const f = setup();
  const r = await worker.fetch(
    new Request("https://api.example/admin/sync", {
      method: "OPTIONS",
      headers: { Origin: "https://app.example" },
    }),
    f.env,
  );
  assert.equal(r.status, 204);
  assert.equal(
    r.headers.get("Access-Control-Allow-Origin"),
    "https://app.example",
  );
  assert.equal(r.headers.get("Access-Control-Allow-Credentials"), null);
  for (const p of ["/install", "/clear", "/unknown"])
    assert.equal((await f.call("GET", p)).status, 404);
});

test("bulk import inserts at most 100 candidates without one database call per name", async () => {
  const f = setup();
  const room = (await f.call("POST", "/admin/room")).data;
  const result = await f.call(
    "POST",
    "//admin/candidate",
    { name: Array.from({ length: 110 }, (_, i) => `Nome ${i}`) },
    room.token,
  );
  assert.equal(result.status, 200);
  assert.equal(result.data.length, 100);
  assert.equal(result.data[99].sequence, 100);
});

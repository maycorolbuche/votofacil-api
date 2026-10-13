import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { build } from "esbuild";
import { Miniflare, convertV4MiniflareOptions } from "miniflare";
test("real Workers/D1 runtime: schema, JWT, concurrent votes and projection snapshot", async () => {
  const bundle = await build({
    entryPoints: ["src/index.ts"],
    bundle: true,
    write: false,
    format: "esm",
    platform: "browser",
    target: "es2022",
  });
  const mf = new Miniflare(
    convertV4MiniflareOptions({
      workers: [
        {
          name: "api",
          modules: true,
          script: bundle.outputFiles[0].text,
          compatibilityDate: "2026-10-13",
          d1Databases: { DB: "test-db" },
          bindings: {
            JWT_SECRET: "runtime-test-secret-with-at-least-32-characters",
            CORS_ORIGINS: "*",
          },
        },
      ],
    }),
  );
  try {
    const db = await mf.getD1Database("DB");
    const schema = readFileSync("migrations/0001_initial.sql", "utf8").replace(
      /^--.*$/gm,
      "",
    );
    // Preserve trigger bodies when splitting the migration for D1 prepared statements.
    const statements = schema.match(
      /CREATE TRIGGER[\s\S]*?\nEND;|CREATE (?:TABLE|INDEX|UNIQUE INDEX|VIEW)[\s\S]*?;/g,
    )!;
    await db.batch(statements.map((sql) => db.prepare(sql)));
    const call = async (
      method: string,
      path: string,
      data?: unknown,
      token?: string,
      type = "admin",
    ) => {
      const response = await mf.dispatchFetch(`https://api.example${path}`, {
        method,
        headers: {
          "Content-Type": "application/json",
          ...(token ? { Authorization: `${type}=${token}` } : {}),
        },
        body: data === undefined ? undefined : JSON.stringify(data),
      });
      return { status: response.status, data: (await response.json()) as any };
    };
    const room = (await call("POST", "/admin/room")).data;
    assert.ok(room.token);
    const admin = room.token;
    const candidates = (
      await call(
        "POST",
        "/admin/candidate",
        { name: ["Candidato A", "Candidato B"] },
        admin,
      )
    ).data;
    assert.equal(candidates.length, 2);
    const device = (
      await call("POST", "/user/device", { room_code: room.code })
    ).data;
    const user = (
      await call(
        "POST",
        "/user/user",
        { name: "Eleitor" },
        device.token,
        "user",
      )
    ).data;
    await call(
      "PATCH",
      "/admin/device",
      { id: device.id, status: "approved" },
      admin,
    );
    await call(
      "POST",
      "/admin/config",
      { key: "votes.room_auto_close", value: false },
      admin,
    );
    const hash = (await call("POST", "/admin/view", {}, admin)).data.value;
    assert.equal((await call("GET", `/view/${hash}`)).data.phase, "preparing");
    await call("PATCH", "/admin/room", { status: "open" }, admin);
    const results = await Promise.all(
      Array.from({ length: 10 }, (_, i) =>
        call(
          "POST",
          "/user/vote",
          { user_id: user.id, candidate_id: candidates[i % 2].id },
          device.token,
          "user",
        ),
      ),
    );
    assert.equal(results.filter((r) => r.status === 200).length, 1);
    const voting = (await call("GET", `/view/${hash}`)).data;
    assert.equal(voting.phase, "voting");
    assert.equal(voting.room.devices[0].users[0].votes_count, 1);
    assert.equal(voting.room.candidates[0].total_votes, undefined);
    await call(
      "POST",
      "/admin/config",
      { key: "votes.room_auto_close", value: true },
      admin,
    );
    const closed = (await call("GET", `/view/${hash}`)).data;
    assert.equal(closed.phase, "results");
    assert.equal(
      closed.room.candidates.reduce(
        (n: number, c: any) => n + c.total_votes,
        0,
      ),
      1,
    );
    await call("DELETE", "/admin/view", {}, admin);
    assert.equal((await call("GET", `/view/${hash}`)).status, 404);
  } finally {
    await mf.dispose();
  }
});

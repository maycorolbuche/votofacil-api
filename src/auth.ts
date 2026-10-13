import { ApiError, one, type Env, type Row } from "./db.ts";
const encode = (data: Uint8Array) =>
  btoa(String.fromCharCode(...data))
    .replaceAll("+", "-")
    .replaceAll("/", "_")
    .replace(/=+$/, "");
const decode = (text: string) =>
  Uint8Array.from(atob(text.replaceAll("-", "+").replaceAll("_", "/")), (c) =>
    c.charCodeAt(0),
  );
async function key(secret: string) {
  if (!secret || secret.length < 32)
    throw new ApiError("JWT_SECRET não configurado corretamente.", 503);
  return crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign", "verify"],
  );
}
export async function sign(payload: Row, secret: string) {
  const head = encode(
    new TextEncoder().encode(JSON.stringify({ typ: "JWT", alg: "HS256" })),
  );
  const body = encode(new TextEncoder().encode(JSON.stringify(payload)));
  const input = `${head}.${body}`;
  return `${input}.${encode(new Uint8Array(await crypto.subtle.sign("HMAC", await key(secret), new TextEncoder().encode(input))))}`;
}
export async function authenticate(
  request: Request,
  env: Env,
  type: "admin" | "user",
): Promise<Row> {
  const pairs = new Map(
    (request.headers.get("Authorization") ?? "").split(",").map((p) => {
      const i = p.indexOf("=");
      return [p.slice(0, i).trim(), p.slice(i + 1).trim()];
    }),
  );
  let payload: Row;
  try {
    const parts = (pairs.get(type) ?? "").split(".");
    if (parts.length !== 3) throw Error();
    const [h, p, s] = parts;
    const header = JSON.parse(new TextDecoder().decode(decode(h)));
    if (header.alg !== "HS256") throw Error();
    if (
      !(await crypto.subtle.verify(
        "HMAC",
        await key(env.JWT_SECRET),
        decode(s),
        new TextEncoder().encode(`${h}.${p}`),
      ))
    )
      throw Error();
    payload = JSON.parse(new TextDecoder().decode(decode(p)));
    if (payload.type !== type || !Number.isSafeInteger(payload.room_id))
      throw Error();
  } catch (e) {
    if (e instanceof ApiError) throw e;
    throw new ApiError("Token inválido!");
  }
  const room = await one(
    env.DB,
    "SELECT id,code,is_active FROM rooms WHERE id=?",
    payload.room_id,
  );
  if (!room) throw new ApiError("Sala não encontrada!");
  if (!room.is_active)
    throw new ApiError(`Sala "${room.code}" não está ativa!`);
  const auth: Row = {
    room_id: room.id,
    code: room.code,
    is_active: room.is_active,
  };
  if (type === "user") {
    const device = await one(
      env.DB,
      "SELECT id,status FROM devices WHERE id=? AND room_id=?",
      payload.device_id,
      room.id,
    );
    if (!device) throw new ApiError("Dispositivo não encontrado!");
    if (device.status === "disapproved")
      throw new ApiError("Dispositivo não autorizado!");
    auth.device_id = device.id;
    auth.status = device.status;
  }
  return auth;
}
export function randomHex() {
  return Array.from(crypto.getRandomValues(new Uint8Array(32)), (b) =>
    b.toString(16).padStart(2, "0"),
  ).join("");
}
export function roomCode(length = 5) {
  const alphabet = "ABCDEFGHIJKLMNPQRSTUVWXYZ123456789";
  let code = "";
  while (code.length < length) {
    for (const b of crypto.getRandomValues(new Uint8Array(length)))
      if (b < 238 && code.length < length)
        code += alphabet[b % alphabet.length];
  }
  return code;
}

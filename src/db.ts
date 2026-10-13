export type Row = Record<string, any>;
export interface Result {
  results: Row[];
  meta: { changes: number; last_row_id: number };
}
export interface Statement {
  bind(...values: unknown[]): Statement;
  all(): Promise<Result>;
  first(): Promise<Row | null>;
  run(): Promise<Result>;
}
export interface Database {
  prepare(sql: string): Statement;
  batch(statements: Statement[]): Promise<Result[]>;
}
export interface Env {
  DB: Database;
  JWT_SECRET: string;
  CORS_ORIGINS?: string;
}
export const stmt = (db: Database, sql: string, ...args: unknown[]) =>
  db.prepare(sql).bind(...args);
export const all = async (db: Database, sql: string, ...args: unknown[]) =>
  (await stmt(db, sql, ...args).all()).results;
export const one = (db: Database, sql: string, ...args: unknown[]) =>
  stmt(db, sql, ...args).first();
export const run = (db: Database, sql: string, ...args: unknown[]) =>
  stmt(db, sql, ...args).run();
export class ApiError extends Error {
  status: number;
  constructor(message: string, status = 500) {
    super(message);
    this.status = status;
  }
}
export function required(value: unknown, message: string): string {
  if (typeof value !== "string" && typeof value !== "number")
    throw new ApiError(message);
  const text = String(value).trim();
  if (!text) throw new ApiError(message);
  return text;
}
export function id(value: unknown): number {
  const n = Number(value);
  if (!Number.isSafeInteger(n) || n <= 0) throw new ApiError("ID inválido.");
  return n;
}
export const now = () =>
  new Date().toISOString().slice(0, 19).replace("T", " ");

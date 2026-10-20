import mysql from 'mysql2/promise';
import type { RowDataPacket } from 'mysql2/promise';

const g = globalThis as unknown as { __pool?: mysql.Pool };

export const pool: mysql.Pool =
  g.__pool ??
  (g.__pool = mysql.createPool({
    host: process.env.DB_HOST || '127.0.0.1',
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    connectionLimit: 10,
    timezone: 'Z',
  }));

export async function query<T extends RowDataPacket = RowDataPacket>(sql: string, params: unknown[] = []): Promise<T[]> {
  const [rows] = await pool.query<T[]>(sql, params);
  return rows;
}

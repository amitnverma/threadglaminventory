import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const config = {
  host: process.env.DB_HOST || '127.0.0.1',
  port: parseInt(process.env.DB_PORT || '3306'),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  multipleStatements: true,
};

export const pool = mysql.createPool({
  ...config,
  database: process.env.DB_NAME || 'threadglam',
  waitForConnections: true,
  connectionLimit: 10,
});

export async function query(sql, params = []) {
  const [rows] = await pool.execute(sql, params);
  return rows;
}

export async function setupDatabase() {
  const schema = fs.readFileSync(path.join(__dirname, '../database/schema.sql'), 'utf8');
  const seed = fs.readFileSync(path.join(__dirname, '../database/seed.sql'), 'utf8');

  const connection = await mysql.createConnection(config);
  try {
    await connection.query(schema);
    console.log('Database schema created.');
    await connection.query(seed);
    console.log('Seed data inserted.');
  } finally {
    await connection.end();
  }
}

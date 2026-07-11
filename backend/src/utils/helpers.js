import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { query } from '../config/db.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const uploadDir = path.resolve(__dirname, '../../', process.env.UPLOAD_DIR || 'uploads');

export function ensureUploadDir() {
  if (!fs.existsSync(uploadDir)) {
    fs.mkdirSync(uploadDir, { recursive: true });
  }
  return uploadDir;
}

export function formatCurrency(amount, currency = 'INR') {
  const value = parseFloat(amount || 0);
  if (currency === 'INR') {
    return `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }
  return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
}

export async function getSettings() {
  const rows = await query('SELECT * FROM settings WHERE id = 1');
  return rows[0] || {};
}

export function parsePagination(req) {
  const page = Math.max(1, parseInt(req.query.page || '1'));
  const perPage = Math.min(100, Math.max(1, parseInt(req.query.per_page || '20')));
  const offset = (page - 1) * perPage;
  return { page, perPage, offset };
}

export function buildSearchWhere(fields, search) {
  if (!search) return { clause: '', params: [] };
  const conditions = fields.map((f) => `${f} LIKE ?`).join(' OR ');
  const params = fields.map(() => `%${search}%`);
  return { clause: `(${conditions})`, params };
}

export { uploadDir };

import { Router } from 'express';
import { query } from '../config/db.js';
import { parsePagination, buildSearchWhere } from '../utils/helpers.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const { search } = req.query;
    const { clause, params } = buildSearchWhere(['name', 'email', 'phone'], search);
    let where = 'deleted_at IS NULL';
    if (clause) where += ` AND ${clause}`;

    const customers = await query(
      `SELECT * FROM customers WHERE ${where} ORDER BY name ASC`,
      params
    );
    res.json(customers);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const [customer] = await query('SELECT * FROM customers WHERE id=? AND deleted_at IS NULL', [req.params.id]);
    if (!customer) return res.status(404).json({ error: 'Customer not found' });
    res.json(customer);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/', async (req, res) => {
  try {
    const { name, email, phone, address, notes } = req.body;
    const result = await query(
      'INSERT INTO customers (name, email, phone, address, notes) VALUES (?,?,?,?,?)',
      [name, email || null, phone || null, address || null, notes || null]
    );
    res.status(201).json({ id: result.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { name, email, phone, address, notes } = req.body;
    await query(
      'UPDATE customers SET name=?, email=?, phone=?, address=?, notes=?, updated_at=NOW() WHERE id=?',
      [name, email || null, phone || null, address || null, notes || null, req.params.id]
    );
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await query('UPDATE customers SET deleted_at=NOW() WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

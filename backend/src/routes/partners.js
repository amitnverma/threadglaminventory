import { Router } from 'express';
import { query } from '../config/db.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const partners = await query('SELECT * FROM partners ORDER BY name');
    res.json(partners);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/expenses', async (req, res) => {
  try {
    const { event_id, partner_id, from, to } = req.query;
    let where = '1=1';
    const params = [];
    if (event_id) { where += ' AND pe.event_id=?'; params.push(event_id); }
    if (partner_id) { where += ' AND pe.partner_id=?'; params.push(partner_id); }
    if (from) { where += ' AND pe.expense_date>=?'; params.push(from); }
    if (to) { where += ' AND pe.expense_date<=?'; params.push(to); }

    const expenses = await query(
      `SELECT pe.*, p.name as partner_name, e.title as event_title
       FROM partner_expenses pe
       JOIN partners p ON p.id=pe.partner_id
       LEFT JOIN events e ON e.id=pe.event_id
       WHERE ${where}
       ORDER BY pe.expense_date DESC`,
      params
    );
    res.json(expenses);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/', async (req, res) => {
  try {
    const { name, phone, email, default_split_percent, notes } = req.body;
    const result = await query(
      'INSERT INTO partners (name, phone, email, default_split_percent, notes) VALUES (?,?,?,?,?)',
      [name, phone || null, email || null, default_split_percent || 0, notes || null]
    );
    res.status(201).json({ id: result.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { name, phone, email, default_split_percent, notes } = req.body;
    await query(
      'UPDATE partners SET name=?, phone=?, email=?, default_split_percent=?, notes=?, updated_at=NOW() WHERE id=?',
      [name, phone || null, email || null, default_split_percent || 0, notes || null, req.params.id]
    );
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await query('DELETE FROM partners WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/expenses/bulk', async (req, res) => {
  try {
    const { expenses } = req.body;
    const ids = [];
    for (const exp of expenses) {
      const result = await query(
        'INSERT INTO partner_expenses (partner_id, event_id, category, description, amount, expense_date, receipt_path) VALUES (?,?,?,?,?,?,?)',
        [
          exp.partner_id, exp.event_id || null, exp.category || null,
          exp.description || null, exp.amount, exp.expense_date,
          exp.receipt_path || null,
        ]
      );
      ids.push(result.insertId);
    }
    res.status(201).json({ ids, count: ids.length });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/expenses', async (req, res) => {
  try {
    const exp = req.body;
    const result = await query(
      'INSERT INTO partner_expenses (partner_id, event_id, category, description, amount, expense_date, receipt_path) VALUES (?,?,?,?,?,?,?)',
      [
        exp.partner_id, exp.event_id || null, exp.category || null,
        exp.description || null, exp.amount, exp.expense_date,
        exp.receipt_path || null,
      ]
    );
    res.status(201).json({ id: result.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/expenses/:id', async (req, res) => {
  try {
    await query('DELETE FROM partner_expenses WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

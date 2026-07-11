import { Router } from 'express';
import { query } from '../config/db.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const { event_id } = req.query;
    let where = '1=1';
    const params = [];
    if (event_id) {
      where += ' AND b.event_id = ?';
      params.push(event_id);
    }

    const budgets = await query(
      `SELECT b.*, e.title as event_title FROM budgets b
       LEFT JOIN events e ON e.id = b.event_id
       WHERE ${where} ORDER BY b.category`,
      params
    );

    const enriched = await Promise.all(
      budgets.map(async (budget) => {
        let actual = 0;
        if (budget.event_id) {
          const [partnerExp] = await query(
            'SELECT COALESCE(SUM(amount),0) as total FROM partner_expenses WHERE event_id=? AND category=?',
            [budget.event_id, budget.category]
          );
          actual = parseFloat(partnerExp.total);
        }
        return { ...budget, actual_amount: actual, remaining: budget.allocated_amount - actual };
      })
    );

    res.json(enriched);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/', async (req, res) => {
  try {
    const { event_id, category, allocated_amount, notes } = req.body;
    const result = await query(
      'INSERT INTO budgets (event_id, category, allocated_amount, notes) VALUES (?,?,?,?)',
      [event_id || null, category, allocated_amount || 0, notes || null]
    );
    res.status(201).json({ id: result.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { category, allocated_amount, notes } = req.body;
    await query(
      'UPDATE budgets SET category=?, allocated_amount=?, notes=? WHERE id=?',
      [category, allocated_amount || 0, notes || null, req.params.id]
    );
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await query('DELETE FROM budgets WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

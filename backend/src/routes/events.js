import { Router } from 'express';
import { query } from '../config/db.js';
import { parsePagination, buildSearchWhere } from '../utils/helpers.js';
import { getEventProfitLoss } from '../services/profitLossService.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const { page, perPage, offset } = parsePagination(req);
    const { search, status, archived } = req.query;
    const { clause, params } = buildSearchWhere(['e.title', 'e.venue', 'c.name'], search);

    let where = 'e.deleted_at IS NULL';
    const whereParams = [...params];
    if (clause) where += ` AND ${clause}`;
    if (status) {
      where += ' AND e.status = ?';
      whereParams.push(status);
    }
    if (archived !== undefined) {
      where += ' AND e.archived = ?';
      whereParams.push(archived === 'true' ? 1 : 0);
    }

    const [countRow] = await query(
      `SELECT COUNT(*) as total FROM events e JOIN customers c ON c.id=e.customer_id WHERE ${where}`,
      whereParams
    );

    const events = await query(
      `SELECT e.*, c.name as customer_name, c.phone as customer_phone,
        (SELECT COUNT(*) FROM estimates WHERE event_id=e.id) as estimate_count
       FROM events e
       JOIN customers c ON c.id = e.customer_id
       WHERE ${where}
       ORDER BY e.event_date DESC, e.created_at DESC
       LIMIT ? OFFSET ?`,
      [...whereParams, perPage, offset]
    );

    res.json({ data: events, meta: { total: countRow.total, page, per_page: perPage } });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const [event] = await query(
      `SELECT e.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
       FROM events e JOIN customers c ON c.id=e.customer_id WHERE e.id=? AND e.deleted_at IS NULL`,
      [req.params.id]
    );
    if (!event) return res.status(404).json({ error: 'Event not found' });

    const [estimates, expenses, images, contracts, budgets, profitLoss] = await Promise.all([
      query('SELECT * FROM estimates WHERE event_id=? ORDER BY created_at DESC', [req.params.id]),
      query(
        `SELECT pe.*, p.name as partner_name FROM partner_expenses pe
         JOIN partners p ON p.id=pe.partner_id WHERE pe.event_id=? ORDER BY pe.expense_date DESC`,
        [req.params.id]
      ),
      query(
        `SELECT * FROM attachments WHERE attachable_type='event' AND attachable_id=? ORDER BY sort_order`,
        [req.params.id]
      ),
      query('SELECT * FROM contracts WHERE event_id=? ORDER BY created_at DESC', [req.params.id]),
      query('SELECT * FROM budgets WHERE event_id=?', [req.params.id]),
      getEventProfitLoss(req.params.id),
    ]);

    res.json({ ...event, estimates, partner_expenses: expenses, images, contracts, budgets, profit_loss: profitLoss });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/', async (req, res) => {
  try {
    const d = req.body;
    const result = await query(
      `INSERT INTO events (customer_id, title, ceremony_type, event_date, end_date, venue, status, internal_notes)
       VALUES (?,?,?,?,?,?,?,?)`,
      [
        d.customer_id, d.title, d.ceremony_type || null, d.event_date || null,
        d.end_date || null, d.venue || null, d.status || 'inquiry', d.internal_notes || null,
      ]
    );
    res.status(201).json({ id: result.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const d = req.body;
    await query(
      `UPDATE events SET customer_id=?, title=?, ceremony_type=?, event_date=?, end_date=?,
       venue=?, status=?, internal_notes=?, archived=?, updated_at=NOW() WHERE id=?`,
      [
        d.customer_id, d.title, d.ceremony_type || null, d.event_date || null,
        d.end_date || null, d.venue || null, d.status, d.internal_notes || null,
        d.archived ? 1 : 0, req.params.id,
      ]
    );
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await query('UPDATE events SET deleted_at=NOW() WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

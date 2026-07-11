import { Router } from 'express';
import { query } from '../config/db.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const sales = await query(
      `SELECT s.*, c.name as customer_name, e.title as event_title
       FROM sales s
       LEFT JOIN customers c ON c.id = s.customer_id
       LEFT JOIN events e ON e.id = s.event_id
       ORDER BY s.sale_date DESC`
    );
    res.json(sales);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const [sale] = await query('SELECT * FROM sales WHERE id=?', [req.params.id]);
    if (!sale) return res.status(404).json({ error: 'Sale not found' });
    const items = await query('SELECT * FROM sale_line_items WHERE sale_id=?', [req.params.id]);
    res.json({ ...sale, line_items: items });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/', async (req, res) => {
  try {
    const { sale, line_items } = req.body;
    const total = (line_items || []).reduce((s, i) => s + (i.quantity * i.unit_price), 0);

    const result = await query(
      'INSERT INTO sales (customer_id, event_id, estimate_id, sale_date, total, notes) VALUES (?,?,?,?,?,?)',
      [sale.customer_id || null, sale.event_id || null, sale.estimate_id || null, sale.sale_date, total, sale.notes || null]
    );
    const saleId = result.insertId;

    for (const item of line_items || []) {
      const lineTotal = item.quantity * item.unit_price;
      await query(
        'INSERT INTO sale_line_items (sale_id, inventory_item_id, label, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)',
        [saleId, item.inventory_item_id || null, item.label, item.quantity, item.unit_price, lineTotal]
      );
    }

    res.status(201).json({ id: saleId, total });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/from-estimate/:estimateId', async (req, res) => {
  try {
    const [estimate] = await query('SELECT * FROM estimates WHERE id=?', [req.params.estimateId]);
    if (!estimate) return res.status(404).json({ error: 'Estimate not found' });

    const lineItems = await query('SELECT * FROM estimate_line_items WHERE estimate_id=?', [req.params.estimateId]);
    const total = lineItems
      .filter((i) => i.line_type !== 'discount')
      .reduce((s, i) => s + i.quantity * i.unit_price, 0);

    const result = await query(
      'INSERT INTO sales (customer_id, event_id, estimate_id, sale_date, total, notes) VALUES (?,?,?,?,?,?)',
      [estimate.customer_id, estimate.event_id, estimate.id, new Date().toISOString().split('T')[0], total, `Created from estimate: ${estimate.title}`]
    );
    const saleId = result.insertId;

    for (const item of lineItems.filter((i) => i.line_type !== 'discount')) {
      const lineTotal = item.quantity * item.unit_price;
      await query(
        'INSERT INTO sale_line_items (sale_id, inventory_item_id, label, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)',
        [saleId, item.inventory_item_id, item.label, item.quantity, item.unit_price, lineTotal]
      );
    }

    res.status(201).json({ id: saleId, total });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await query('DELETE FROM sale_line_items WHERE sale_id=?', [req.params.id]);
    await query('DELETE FROM sales WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

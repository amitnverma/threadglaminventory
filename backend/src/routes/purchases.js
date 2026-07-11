import { Router } from 'express';
import { query } from '../config/db.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const purchases = await query(
      `SELECT p.*, (SELECT COUNT(*) FROM purchase_line_items WHERE purchase_id=p.id) as item_count
       FROM purchases p ORDER BY p.purchase_date DESC`
    );
    res.json(purchases);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const [purchase] = await query('SELECT * FROM purchases WHERE id=?', [req.params.id]);
    if (!purchase) return res.status(404).json({ error: 'Purchase not found' });
    const items = await query('SELECT * FROM purchase_line_items WHERE purchase_id=?', [req.params.id]);
    res.json({ ...purchase, line_items: items });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/', async (req, res) => {
  try {
    const { purchase, line_items } = req.body;
    const total = (line_items || []).reduce((s, i) => s + (i.quantity * i.unit_cost), 0);

    const result = await query(
      'INSERT INTO purchases (supplier, purchase_date, total, notes) VALUES (?,?,?,?)',
      [purchase.supplier || null, purchase.purchase_date, total, purchase.notes || null]
    );
    const purchaseId = result.insertId;

    for (const item of line_items || []) {
      const lineTotal = item.quantity * item.unit_cost;
      await query(
        'INSERT INTO purchase_line_items (purchase_id, inventory_item_id, label, quantity, unit_cost, line_total) VALUES (?,?,?,?,?,?)',
        [purchaseId, item.inventory_item_id || null, item.label, item.quantity, item.unit_cost, lineTotal]
      );

      if (item.inventory_item_id) {
        const [inv] = await query('SELECT quantity_on_hand, unit_cost FROM inventory_items WHERE id=?', [item.inventory_item_id]);
        if (inv) {
          const newQty = inv.quantity_on_hand + parseInt(item.quantity);
          const oldTotal = inv.quantity_on_hand * inv.unit_cost;
          const newTotal = oldTotal + lineTotal;
          const avgCost = newQty > 0 ? newTotal / newQty : item.unit_cost;
          await query('UPDATE inventory_items SET quantity_on_hand=?, unit_cost=?, updated_at=NOW() WHERE id=?', [newQty, avgCost, item.inventory_item_id]);
        }
      }
    }

    res.status(201).json({ id: purchaseId, total });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await query('DELETE FROM purchase_line_items WHERE purchase_id=?', [req.params.id]);
    await query('DELETE FROM purchases WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

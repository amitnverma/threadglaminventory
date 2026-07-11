import { Router } from 'express';
import { query } from '../config/db.js';
import { parsePagination, buildSearchWhere } from '../utils/helpers.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const { page, perPage, offset } = parsePagination(req);
    const { search, category_id, view } = req.query;
    const { clause, params } = buildSearchWhere(['i.name', 'i.sku', 'i.description'], search);

    let where = 'i.deleted_at IS NULL';
    const whereParams = [...params];
    if (clause) {
      where += ` AND ${clause}`;
    }
    if (category_id) {
      where += ' AND i.category_id = ?';
      whereParams.push(category_id);
    }

    const [countRow] = await query(
      `SELECT COUNT(*) as total FROM inventory_items i WHERE ${where}`,
      whereParams
    );

    const items = await query(
      `SELECT i.*, c.name as category_name,
        (SELECT file_path FROM attachments WHERE attachable_type='inventory' AND attachable_id=i.id ORDER BY sort_order LIMIT 1) as thumbnail
       FROM inventory_items i
       LEFT JOIN inventory_categories c ON c.id = i.category_id
       WHERE ${where}
       ORDER BY i.name ASC
       LIMIT ? OFFSET ?`,
      [...whereParams, perPage, offset]
    );

    res.json({ data: items, meta: { total: countRow.total, page, per_page: perPage, view } });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/categories', async (req, res) => {
  try {
    const categories = await query('SELECT * FROM inventory_categories ORDER BY name');
    res.json(categories);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/categories', async (req, res) => {
  try {
    const { name, description } = req.body;
    const result = await query(
      'INSERT INTO inventory_categories (name, description) VALUES (?, ?)',
      [name, description || null]
    );
    res.status(201).json({ id: result.insertId, name, description });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const [item] = await query(
      `SELECT i.*, c.name as category_name FROM inventory_items i
       LEFT JOIN inventory_categories c ON c.id = i.category_id
       WHERE i.id = ? AND i.deleted_at IS NULL`,
      [req.params.id]
    );
    if (!item) return res.status(404).json({ error: 'Item not found' });

    const images = await query(
      `SELECT * FROM attachments WHERE attachable_type='inventory' AND attachable_id=? ORDER BY sort_order`,
      [req.params.id]
    );
    const adjustments = await query(
      'SELECT * FROM inventory_adjustments WHERE inventory_item_id=? ORDER BY created_at DESC LIMIT 20',
      [req.params.id]
    );

    res.json({ ...item, images, adjustments });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/', async (req, res) => {
  try {
    const d = req.body;
    const result = await query(
      `INSERT INTO inventory_items (category_id, name, sku, description, quantity_on_hand, unit_cost,
       rental_price, sale_price, condition_status, reorder_level) VALUES (?,?,?,?,?,?,?,?,?,?)`,
      [
        d.category_id || null, d.name, d.sku || null, d.description || null,
        d.quantity_on_hand || 0, d.unit_cost || 0, d.rental_price || 0,
        d.sale_price || 0, d.condition_status || 'good', d.reorder_level || 0,
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
      `UPDATE inventory_items SET category_id=?, name=?, sku=?, description=?, unit_cost=?,
       rental_price=?, sale_price=?, condition_status=?, reorder_level=?, updated_at=NOW()
       WHERE id=? AND deleted_at IS NULL`,
      [
        d.category_id || null, d.name, d.sku || null, d.description || null,
        d.unit_cost || 0, d.rental_price || 0, d.sale_price || 0,
        d.condition_status || 'good', d.reorder_level || 0, req.params.id,
      ]
    );
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await query('UPDATE inventory_items SET deleted_at=NOW() WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/:id/adjust', async (req, res) => {
  try {
    const { adjustment_type, quantity, reason } = req.body;
    const [item] = await query('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [req.params.id]);
    if (!item) return res.status(404).json({ error: 'Item not found' });

    let newQty = item.quantity_on_hand;
    if (adjustment_type === 'add') newQty += parseInt(quantity);
    else if (adjustment_type === 'remove') newQty -= parseInt(quantity);
    else if (adjustment_type === 'set') newQty = parseInt(quantity);

    await query('UPDATE inventory_items SET quantity_on_hand=?, updated_at=NOW() WHERE id=?', [newQty, req.params.id]);
    await query(
      'INSERT INTO inventory_adjustments (inventory_item_id, adjustment_type, quantity, reason) VALUES (?,?,?,?)',
      [req.params.id, adjustment_type, quantity, reason || null]
    );
    res.json({ quantity_on_hand: newQty });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/:id/duplicate', async (req, res) => {
  try {
    const [item] = await query('SELECT * FROM inventory_items WHERE id=? AND deleted_at IS NULL', [req.params.id]);
    if (!item) return res.status(404).json({ error: 'Item not found' });

    const result = await query(
      `INSERT INTO inventory_items (category_id, name, sku, description, quantity_on_hand, unit_cost,
       rental_price, sale_price, condition_status, reorder_level)
       VALUES (?,?,?,?,0,?,?,?,?,?)`,
      [
        item.category_id, `${item.name} (Copy)`, item.sku ? `${item.sku}-COPY` : null,
        item.description, item.unit_cost, item.rental_price, item.sale_price,
        item.condition_status, item.reorder_level,
      ]
    );
    res.status(201).json({ id: result.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

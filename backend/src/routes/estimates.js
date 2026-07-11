import { Router } from 'express';
import { query } from '../config/db.js';
import {
  saveEstimateWithItems,
  cloneEstimate,
  getEstimateWithDetails,
} from '../services/estimateService.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const { templates, event_id, customer_id } = req.query;
    let where = '1=1';
    const params = [];

    if (templates === 'true') {
      where += ' AND e.is_template = 1';
    } else {
      where += ' AND e.is_template = 0';
    }
    if (event_id) {
      where += ' AND e.event_id = ?';
      params.push(event_id);
    }
    if (customer_id) {
      where += ' AND e.customer_id = ?';
      params.push(customer_id);
    }

    const estimates = await query(
      `SELECT e.*, c.name as customer_name, ev.title as event_title
       FROM estimates e
       LEFT JOIN customers c ON c.id = e.customer_id
       LEFT JOIN events ev ON ev.id = e.event_id
       WHERE ${where}
       ORDER BY e.updated_at DESC`,
      params
    );
    res.json(estimates);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/catalog', async (req, res) => {
  try {
    const { search, category_id } = req.query;
    let where = 'i.deleted_at IS NULL';
    const params = [];
    if (search) {
      where += ' AND (i.name LIKE ? OR i.sku LIKE ?)';
      params.push(`%${search}%`, `%${search}%`);
    }
    if (category_id) {
      where += ' AND i.category_id = ?';
      params.push(category_id);
    }

    const items = await query(
      `SELECT i.*, c.name as category_name,
        (SELECT file_path FROM attachments WHERE attachable_type='inventory' AND attachable_id=i.id ORDER BY sort_order LIMIT 1) as thumbnail
       FROM inventory_items i
       LEFT JOIN inventory_categories c ON c.id = i.category_id
       WHERE ${where}
       ORDER BY i.name`,
      params
    );
    res.json(items);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const estimate = await getEstimateWithDetails(req.params.id);
    if (!estimate) return res.status(404).json({ error: 'Estimate not found' });
    res.json(estimate);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/', async (req, res) => {
  try {
    const { estimate, line_items } = req.body;
    const result = await saveEstimateWithItems(estimate, line_items || []);
    const full = await getEstimateWithDetails(result.id);
    res.status(201).json(full);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { estimate, line_items } = req.body;
    await saveEstimateWithItems(estimate, line_items || [], parseInt(req.params.id));
    const full = await getEstimateWithDetails(req.params.id);
    res.json(full);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await query('DELETE FROM estimate_line_items WHERE estimate_id=?', [req.params.id]);
    await query('DELETE FROM estimates WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/:id/clone', async (req, res) => {
  try {
    const result = await cloneEstimate(req.params.id, req.body);
    const full = await getEstimateWithDetails(result.id);
    res.status(201).json(full);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/:id/save-template', async (req, res) => {
  try {
    const estimate = await getEstimateWithDetails(req.params.id);
    if (!estimate) return res.status(404).json({ error: 'Estimate not found' });

    const result = await saveEstimateWithItems(
      {
        customer_id: estimate.customer_id,
        title: req.body.name || `${estimate.title} Template`,
        tax_percent: estimate.tax_percent,
        discount_type: estimate.discount_type,
        discount_value: estimate.discount_value,
        is_template: true,
      },
      estimate.line_items
    );
    res.status(201).json({ id: result.id });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.patch('/:id/status', async (req, res) => {
  try {
    await query('UPDATE estimates SET status=?, updated_at=NOW() WHERE id=?', [req.body.status, req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

import { Router } from 'express';
import { query } from '../config/db.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const { q } = req.query;
    if (!q || q.length < 2) return res.json({ customers: [], events: [], inventory: [] });

    const search = `%${q}%`;
    const [customers, events, inventory] = await Promise.all([
      query(
        'SELECT id, name, email, phone FROM customers WHERE deleted_at IS NULL AND (name LIKE ? OR email LIKE ?) LIMIT 5',
        [search, search]
      ),
      query(
        `SELECT e.id, e.title, e.event_date, c.name as customer_name FROM events e
         JOIN customers c ON c.id=e.customer_id
         WHERE e.deleted_at IS NULL AND (e.title LIKE ? OR e.venue LIKE ?) LIMIT 5`,
        [search, search]
      ),
      query(
        'SELECT id, name, sku FROM inventory_items WHERE deleted_at IS NULL AND (name LIKE ? OR sku LIKE ?) LIMIT 5',
        [search, search]
      ),
    ]);

    res.json({ customers, events, inventory });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

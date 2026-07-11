import { Router } from 'express';
import { query } from '../config/db.js';
import { getDashboardStats, getEventProfitLoss, getMonthlyProfitLoss } from '../services/profitLossService.js';

const router = Router();

router.get('/dashboard', async (req, res) => {
  try {
    const stats = await getDashboardStats();
    res.json(stats);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/profit-loss', async (req, res) => {
  try {
    const { event_id, year, month } = req.query;
    if (event_id) {
      const data = await getEventProfitLoss(event_id);
      return res.json(data);
    }
    const y = parseInt(year || new Date().getFullYear());
    const m = parseInt(month || new Date().getMonth() + 1);
    const data = await getMonthlyProfitLoss(y, m);
    res.json(data);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/events-summary', async (req, res) => {
  try {
    const events = await query(
      `SELECT e.id, e.title, e.status, e.event_date, c.name as customer_name
       FROM events e JOIN customers c ON c.id=e.customer_id
       WHERE e.deleted_at IS NULL ORDER BY e.event_date DESC LIMIT 20`
    );

    const summaries = await Promise.all(
      events.map(async (event) => {
        const pl = await getEventProfitLoss(event.id);
        return { ...event, profit_loss: pl };
      })
    );

    res.json(summaries);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;

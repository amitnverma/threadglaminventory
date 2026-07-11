import { query } from '../config/db.js';
import { calculateEstimateTotals } from './estimateService.js';

export async function getEventProfitLoss(eventId) {
  const [event] = await query('SELECT * FROM events WHERE id = ?', [eventId]);
  if (!event) return null;

  const sales = await query(
    'SELECT COALESCE(SUM(total), 0) as total FROM sales WHERE event_id = ?',
    [eventId]
  );
  const approvedEstimates = await query(
    `SELECT COALESCE(SUM(total), 0) as total FROM estimates
     WHERE event_id = ? AND status = 'approved'`,
    [eventId]
  );
  const partnerExpenses = await query(
    'SELECT COALESCE(SUM(amount), 0) as total FROM partner_expenses WHERE event_id = ?',
    [eventId]
  );
  const purchases = await query(
    `SELECT COALESCE(SUM(pli.line_total), 0) as total
     FROM purchase_line_items pli
     JOIN purchases p ON p.id = pli.purchase_id
     WHERE p.notes LIKE ?`,
    [`%event:${eventId}%`]
  );

  const estimateItems = await query(
    `SELECT eli.* FROM estimate_line_items eli
     JOIN estimates e ON e.id = eli.estimate_id
     WHERE e.event_id = ? AND e.status IN ('approved', 'sent')`,
    [eventId]
  );

  const inventoryCost = estimateItems.reduce(
    (sum, item) => sum + parseFloat(item.quantity || 0) * parseFloat(item.unit_cost || 0),
    0
  );

  const revenue = parseFloat(sales[0].total) + parseFloat(approvedEstimates[0].total);
  const expenses =
    parseFloat(partnerExpenses[0].total) +
    parseFloat(purchases[0].total) +
    inventoryCost;

  return {
    event_id: eventId,
    event_title: event.title,
    revenue: round2(revenue),
    expenses: round2(expenses),
    profit: round2(revenue - expenses),
    margin_percent: revenue > 0 ? round2(((revenue - expenses) / revenue) * 100) : 0,
    breakdown: {
      sales: parseFloat(sales[0].total),
      approved_estimates: parseFloat(approvedEstimates[0].total),
      partner_expenses: parseFloat(partnerExpenses[0].total),
      purchases: parseFloat(purchases[0].total),
      inventory_cost: round2(inventoryCost),
    },
  };
}

export async function getMonthlyProfitLoss(year, month) {
  const startDate = `${year}-${String(month).padStart(2, '0')}-01`;
  const endDate = new Date(year, month, 0).toISOString().split('T')[0];

  const sales = await query(
    'SELECT COALESCE(SUM(total), 0) as total FROM sales WHERE sale_date BETWEEN ? AND ?',
    [startDate, endDate]
  );
  const estimates = await query(
    `SELECT COALESCE(SUM(total), 0) as total FROM estimates
     WHERE status = 'approved' AND created_at BETWEEN ? AND ?`,
    [startDate, `${endDate} 23:59:59`]
  );
  const partnerExpenses = await query(
    'SELECT COALESCE(SUM(amount), 0) as total FROM partner_expenses WHERE expense_date BETWEEN ? AND ?',
    [startDate, endDate]
  );
  const purchases = await query(
    'SELECT COALESCE(SUM(total), 0) as total FROM purchases WHERE purchase_date BETWEEN ? AND ?',
    [startDate, endDate]
  );

  const revenue = parseFloat(sales[0].total) + parseFloat(estimates[0].total);
  const expenses = parseFloat(partnerExpenses[0].total) + parseFloat(purchases[0].total);

  return {
    year,
    month,
    revenue: round2(revenue),
    expenses: round2(expenses),
    profit: round2(revenue - expenses),
  };
}

export async function getDashboardStats() {
  const [inventory] = await query(
    'SELECT COUNT(*) as total, SUM(quantity_on_hand) as qty FROM inventory_items WHERE deleted_at IS NULL'
  );
  const [events] = await query(
    "SELECT COUNT(*) as total FROM events WHERE deleted_at IS NULL AND status NOT IN ('completed', 'cancelled')"
  );
  const [revenue] = await query('SELECT COALESCE(SUM(total), 0) as total FROM sales');
  const [lowStock] = await query(
    'SELECT COUNT(*) as total FROM inventory_items WHERE deleted_at IS NULL AND quantity_on_hand <= reorder_level'
  );
  const recentEvents = await query(
    `SELECT e.*, c.name as customer_name FROM events e
     JOIN customers c ON c.id = e.customer_id
     WHERE e.deleted_at IS NULL ORDER BY e.event_date ASC LIMIT 5`
  );

  const monthlyData = [];
  const now = new Date();
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    const data = await getMonthlyProfitLoss(d.getFullYear(), d.getMonth() + 1);
    monthlyData.push({
      month: d.toLocaleString('default', { month: 'short', year: '2-digit' }),
      ...data,
    });
  }

  return {
    inventory_count: inventory.total,
    inventory_qty: inventory.qty,
    active_events: events.total,
    total_revenue: parseFloat(revenue.total),
    low_stock_count: lowStock.total,
    recent_events: recentEvents,
    monthly_chart: monthlyData,
  };
}

function round2(n) {
  return Math.round(parseFloat(n) * 100) / 100;
}

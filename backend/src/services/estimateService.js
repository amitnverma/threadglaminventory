import { query } from '../config/db.js';

export function calculateLineTotal(item) {
  const qty = parseFloat(item.quantity || 0);
  const price = parseFloat(item.unit_price || 0);
  return qty * price;
}

export function calculateEstimateTotals(lineItems, options = {}) {
  const subtotal = lineItems.reduce((sum, item) => {
    if (item.line_type === 'discount') return sum;
    return sum + calculateLineTotal(item);
  }, 0);

  const discountLines = lineItems
    .filter((i) => i.line_type === 'discount')
    .reduce((sum, item) => sum + Math.abs(calculateLineTotal(item)), 0);

  const taxPercent = parseFloat(options.tax_percent || 0);
  const discountType = options.discount_type || 'percent';
  const discountValue = parseFloat(options.discount_value || 0);

  let discountAmount = discountLines;
  if (discountType === 'percent') {
    discountAmount += (subtotal * discountValue) / 100;
  } else {
    discountAmount += discountValue;
  }

  const taxable = Math.max(0, subtotal - discountAmount);
  const taxAmount = (taxable * taxPercent) / 100;
  const total = taxable + taxAmount;

  const totalCost = lineItems.reduce((sum, item) => {
    if (item.line_type === 'discount') return sum;
    return sum + parseFloat(item.quantity || 0) * parseFloat(item.unit_cost || 0);
  }, 0);

  return {
    subtotal: round2(subtotal),
    discount_amount: round2(discountAmount),
    tax_amount: round2(taxAmount),
    total: round2(total),
    total_cost: round2(totalCost),
    profit: round2(total - totalCost),
    margin_percent: total > 0 ? round2(((total - totalCost) / total) * 100) : 0,
  };
}

function round2(n) {
  return Math.round(n * 100) / 100;
}

export async function saveEstimateWithItems(estimateData, lineItems, estimateId = null) {
  const totals = calculateEstimateTotals(lineItems, estimateData);

  if (estimateId) {
    await query(
      `UPDATE estimates SET customer_id=?, event_id=?, title=?, status=?, subtotal=?, tax_percent=?,
       tax_amount=?, discount_type=?, discount_value=?, discount_amount=?, total=?, valid_until=?, notes=?, updated_at=NOW()
       WHERE id=?`,
      [
        estimateData.customer_id, estimateData.event_id || null, estimateData.title,
        estimateData.status || 'draft', totals.subtotal, estimateData.tax_percent || 0,
        totals.tax_amount, estimateData.discount_type || 'percent', estimateData.discount_value || 0,
        totals.discount_amount, totals.total, estimateData.valid_until || null,
        estimateData.notes || null, estimateId,
      ]
    );
    await query('DELETE FROM estimate_line_items WHERE estimate_id = ?', [estimateId]);
  } else {
    const result = await query(
      `INSERT INTO estimates (customer_id, event_id, title, status, subtotal, tax_percent, tax_amount,
       discount_type, discount_value, discount_amount, total, valid_until, notes, is_template, parent_estimate_id, version)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        estimateData.customer_id, estimateData.event_id || null, estimateData.title,
        estimateData.status || 'draft', totals.subtotal, estimateData.tax_percent || 0,
        totals.tax_amount, estimateData.discount_type || 'percent', estimateData.discount_value || 0,
        totals.discount_amount, totals.total, estimateData.valid_until || null,
        estimateData.notes || null, estimateData.is_template ? 1 : 0,
        estimateData.parent_estimate_id || null, estimateData.version || 1,
      ]
    );
    estimateId = result.insertId;
  }

  for (let i = 0; i < lineItems.length; i++) {
    const item = lineItems[i];
    await query(
      `INSERT INTO estimate_line_items (estimate_id, line_type, inventory_item_id, label, description,
       quantity, unit_price, unit_cost, sort_order, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        estimateId, item.line_type || 'custom', item.inventory_item_id || null,
        item.label, item.description || null, item.quantity || 1, item.unit_price || 0,
        item.unit_cost || 0, item.sort_order ?? i, item.notes || null,
      ]
    );
  }

  return { id: estimateId, ...totals };
}

export async function cloneEstimate(estimateId, overrides = {}) {
  const [estimate] = await query('SELECT * FROM estimates WHERE id = ?', [estimateId]);
  if (!estimate) throw new Error('Estimate not found');

  const lineItems = await query(
    'SELECT * FROM estimate_line_items WHERE estimate_id = ? ORDER BY sort_order',
    [estimateId]
  );

  return saveEstimateWithItems(
    {
      ...estimate,
      ...overrides,
      title: overrides.title || `${estimate.title} (Copy)`,
      status: 'draft',
      parent_estimate_id: estimateId,
      version: (estimate.version || 1) + 1,
      is_template: false,
    },
    lineItems.map(({ id, estimate_id, ...rest }) => rest)
  );
}

export async function getEstimateWithDetails(id) {
  const [estimate] = await query(
    `SELECT e.*, c.name as customer_name, ev.title as event_title
     FROM estimates e
     LEFT JOIN customers c ON c.id = e.customer_id
     LEFT JOIN events ev ON ev.id = e.event_id
     WHERE e.id = ?`,
    [id]
  );
  if (!estimate) return null;

  const lineItems = await query(
    'SELECT * FROM estimate_line_items WHERE estimate_id = ? ORDER BY sort_order',
    [id]
  );

  const totals = calculateEstimateTotals(lineItems, estimate);
  return { ...estimate, line_items: lineItems, computed: totals };
}

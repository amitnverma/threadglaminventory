import { calculateEstimateTotals } from '../services/estimateService.js';

const lineItems = [
  { line_type: 'inventory', label: 'Chair', quantity: 100, unit_price: 350, unit_cost: 250 },
  { line_type: 'labor', label: 'Setup', quantity: 1, unit_price: 5000, unit_cost: 2000 },
];

const totals = calculateEstimateTotals(lineItems, { tax_percent: 18, discount_type: 'percent', discount_value: 10 });

console.assert(totals.subtotal === 40000, `Expected subtotal 40000, got ${totals.subtotal}`);
console.assert(totals.discount_amount === 4000, `Expected discount 4000, got ${totals.discount_amount}`);
console.assert(totals.total > 0, 'Total should be positive');
console.assert(totals.profit > 0, 'Profit should be positive');

console.log('All estimate service tests passed.');
console.log('Totals:', totals);

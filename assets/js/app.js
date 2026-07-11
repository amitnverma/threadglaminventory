// Simple helpers
document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
        if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });
});

// Estimate builder - add line from catalog
function addEstimateLine(item) {
    var tbody = document.getElementById('estimate-lines');
    if (!tbody) return;
    var row = document.createElement('tr');
    row.innerHTML =
        '<td><input type="hidden" name="line_inventory_id[]" value="' + (item.id || '') + '">' +
        '<input type="hidden" name="line_type[]" value="' + (item.type || 'inventory') + '">' +
        '<input type="hidden" name="line_cost[]" value="' + (item.cost || 0) + '">' +
        '<input type="text" name="line_label[]" value="' + (item.label || '') + '" class="line-label"></td>' +
        '<td><input type="number" name="line_qty[]" value="1" min="0" step="0.5" class="line-qty" onchange="updateEstimateTotal()"></td>' +
        '<td><input type="number" name="line_price[]" value="' + (item.price || 0) + '" min="0" step="0.01" class="line-price" onchange="updateEstimateTotal()"></td>' +
        '<td class="line-amount text-right">0</td>' +
        '<td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'tr\').remove();updateEstimateTotal()">×</button></td>';
    tbody.appendChild(row);
    updateEstimateTotal();
}

function addCustomLine(type) {
    addEstimateLine({ id: '', type: type, label: type === 'labor' ? 'Labor / Service' : 'Custom Item', price: 0, cost: 0 });
}

function updateEstimateTotal() {
    var rows = document.querySelectorAll('#estimate-lines tr');
    var subtotal = 0;
    rows.forEach(function (row) {
        var qty = parseFloat(row.querySelector('.line-qty')?.value || 0);
        var price = parseFloat(row.querySelector('.line-price')?.value || 0);
        var amt = qty * price;
        var cell = row.querySelector('.line-amount');
        if (cell) cell.textContent = '₹' + amt.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        subtotal += amt;
    });
    var taxPct = parseFloat(document.getElementById('tax_percent')?.value || 0);
    var discPct = parseFloat(document.getElementById('discount_value')?.value || 0);
    var discType = document.getElementById('discount_type')?.value || 'percent';
    var discount = discType === 'percent' ? subtotal * discPct / 100 : discPct;
    var taxable = Math.max(0, subtotal - discount);
    var tax = taxable * taxPct / 100;
    var total = taxable + tax;
    var el = function (id, val) { var e = document.getElementById(id); if (e) e.textContent = '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2 }); };
    el('est-subtotal', subtotal);
    el('est-discount', discount);
    el('est-tax', tax);
    el('est-total', total);
}

// Partner multi-expense rows
function addExpenseRow() {
    var container = document.getElementById('expense-rows');
    if (!container) return;
    var first = container.querySelector('.expense-row');
    if (!first) return;
    var clone = first.cloneNode(true);
    clone.querySelectorAll('input').forEach(function (i) { i.value = i.type === 'date' ? new Date().toISOString().split('T')[0] : (i.type === 'number' ? '' : ''); });
    clone.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
    container.appendChild(clone);
}

function togglePurchaseRow(select) {
    var row = select.closest('.purchase-row');
    var isNew = select.value === 'new';
    row.querySelector('.existing-fields').style.display = isNew ? 'none' : 'block';
    row.querySelector('.new-fields').style.display = isNew ? 'block' : 'none';
    var invSelect = row.querySelector('.line-inventory');
    if (invSelect) invSelect.required = !isNew;
    updatePurchaseRow(select);
}

function updatePurchaseRow(el) {
    var row = el.closest('.purchase-row');
    if (!row) return;
    var mode = row.querySelector('.line-mode').value;
    var qty = parseInt(row.querySelector('.line-qty').value || 0, 10);
    var stockCell = row.querySelector('.stock-after');

    if (mode === 'new') {
        stockCell.innerHTML = qty > 0
            ? '<span class="badge badge-approved">New: ' + qty + ' units</span>'
            : '—';
        return;
    }

    var select = row.querySelector('.line-inventory');
    var opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) {
        stockCell.textContent = '—';
        if (el === select || el.classList.contains('line-inventory')) {
            row.querySelector('.line-cost').value = '0';
        }
        return;
    }

    var current = parseInt(opt.dataset.qty || 0, 10);
    var cost = parseFloat(opt.dataset.cost || 0);
    if (el === select || el.classList.contains('line-inventory')) {
        row.querySelector('.line-cost').value = cost.toFixed(2);
    }
    var after = current + (qty || 0);
    stockCell.innerHTML = current + ' → <strong>' + after + '</strong>';
}

function buildInventoryOptions() {
    var html = '<option value="">— Select inventory item —</option>';
    (window.PURCHASE_INVENTORY || []).forEach(function (item) {
        html += '<option value="' + item.id + '" data-qty="' + item.qty + '" data-cost="' + item.cost + '" data-name="' + item.name + '">'
            + item.name + ' (' + item.sku + ') — ' + item.qty + ' in stock</option>';
    });
    return html;
}

function buildCategoryOptions() {
    var html = '<option value="">Category (optional)</option>';
    (window.PURCHASE_CATEGORIES || []).forEach(function (cat) {
        html += '<option value="' + cat.id + '">' + cat.name + '</option>';
    });
    return html;
}

function addPurchaseRow() {
    var tbody = document.getElementById('purchase-lines');
    if (!tbody) return;
    var row = document.createElement('tr');
    row.className = 'purchase-row';
    row.innerHTML =
        '<td><select name="line_mode[]" class="line-mode" onchange="togglePurchaseRow(this)">' +
        '<option value="existing">Restock existing</option><option value="new">Create new item</option></select></td>' +
        '<td><div class="existing-fields"><select name="line_inventory_id[]" class="line-inventory" onchange="updatePurchaseRow(this)" required>'
        + buildInventoryOptions() + '</select></div>' +
        '<div class="new-fields" style="display:none">' +
        '<input type="text" name="line_new_name[]" placeholder="New item name" class="mb-1">' +
        '<select name="line_category_id[]">' + buildCategoryOptions() + '</select></div></td>' +
        '<td><input type="number" name="line_qty[]" value="1" min="1" class="line-qty" onchange="updatePurchaseRow(this)" oninput="updatePurchaseRow(this)"></td>' +
        '<td><input type="number" step="0.01" name="line_cost[]" value="0" min="0" class="line-cost" onchange="updatePurchaseRow(this)" oninput="updatePurchaseRow(this)"></td>' +
        '<td class="stock-after text-muted">—</td>' +
        '<td><button type="button" class="btn btn-sm btn-danger" onclick="removePurchaseRow(this)">×</button></td>';
    tbody.appendChild(row);
}

function removePurchaseRow(btn) {
    var tbody = document.getElementById('purchase-lines');
    if (tbody.querySelectorAll('.purchase-row').length <= 1) return;
    btn.closest('.purchase-row').remove();
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.purchase-row').forEach(function (row) {
        updatePurchaseRow(row.querySelector('.line-inventory') || row);
    });
    if (window.PURCHASE_PRESELECT) {
        var first = document.querySelector('.line-inventory');
        if (first) {
            first.value = String(window.PURCHASE_PRESELECT);
            updatePurchaseRow(first);
        }
    }
});

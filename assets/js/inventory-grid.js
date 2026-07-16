(function () {
    'use strict';

    var cfg = window.INV_GRID;
    if (!cfg || !document.getElementById('inventory-edit-sheet')) return;

    var categoryOptions = [{ value: '', label: 'Select…' }].concat(
        (cfg.categories || []).map(function (c) {
            return { value: String(c.id), label: c.name };
        })
    );

    var statusEl = document.getElementById('inv-grid-status');
    var saveBtn = document.getElementById('inv-grid-save');

    function setStatus(text, cls) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'inv-sheet-status' + (cls ? ' ' + cls : '');
    }

    var sheet = InventorySheet.mount('#inventory-edit-sheet', {
        mode: 'edit',
        minRows: 0,
        rows: cfg.rows || [],
        columns: [
            {
                key: 'name',
                label: 'Item',
                type: 'text',
                placeholder: 'Item name',
                widthClass: 'col-item'
            },
            {
                key: 'sku',
                label: 'SKU',
                type: 'readonly',
                widthClass: 'col-sku',
                className: 'sku-cell',
                format: function (v) {
                    return v ? String(v) : '—';
                }
            },
            {
                key: 'category_id',
                label: 'Category',
                type: 'select',
                widthClass: 'col-cat',
                options: categoryOptions
            },
            {
                key: 'quantity_on_hand',
                label: 'Qty',
                type: 'readonly',
                widthClass: 'col-qty',
                align: 'right',
                format: function (v, row) {
                    var qty = Number(v) || 0;
                    var low = qty <= (Number(row.reorder_level) || 0);
                    return low
                        ? '<span class="inv-low-qty">' + qty + '</span>'
                        : String(qty);
                }
            },
            {
                key: 'unit_cost',
                label: 'Cost',
                type: 'money',
                min: 0,
                step: 0.01,
                widthClass: 'col-money',
                align: 'right'
            },
            {
                key: 'rental_price',
                label: 'Rental',
                type: 'money',
                min: 0,
                step: 0.01,
                widthClass: 'col-money',
                align: 'right'
            },
            {
                key: 'sale_price',
                label: 'Sale',
                type: 'money',
                min: 0,
                step: 0.01,
                widthClass: 'col-money',
                align: 'right'
            }
        ],
        actionsWidthClass: 'col-actions-wide',
        renderRowActions: function (row) {
            if (!row.id) return '';
            return (
                '<a class="btn btn-sm btn-secondary" href="inventory-view.php?id=' + row.id + '" title="Photos & history">⋯</a>' +
                '<a class="btn btn-sm btn-secondary" href="inventory-buy.php?item=' + row.id + '" title="Restock">+</a>' +
                '<button type="button" class="btn btn-sm btn-danger" data-sheet-action="delete" title="Delete">×</button>'
            );
        },
        onRowAction: function (action, row) {
            if (action !== 'delete' || !row || !row.id) return;
            if (!confirm('Delete "' + row.name + '"? This hides it from inventory.')) return;

            var body = new FormData();
            body.append('csrf_token', cfg.csrf);
            body.append('action', 'delete');
            body.append('id', String(row.id));

            fetch(cfg.apiUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) {
                        alert(res.error || 'Delete failed.');
                        return;
                    }
                    sheet.rows = sheet.rows.filter(function (r) { return r._uid !== row._uid; });
                    sheet._render();
                    setStatus('Item deleted', 'is-saved');
                })
                .catch(function () {
                    alert('Delete failed. Please try again.');
                });
        },
        onDirty: function (dirty) {
            if (saveBtn) saveBtn.disabled = !dirty;
            setStatus(dirty ? 'Unsaved changes' : 'All saved', dirty ? 'is-dirty' : '');
        }
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var dirty = sheet.getDirtyRows().filter(function (r) { return r.id; });
            if (!dirty.length) return;

            var payload = dirty.map(function (r) {
                return {
                    id: r.id,
                    name: r.name,
                    category_id: r.category_id === '' ? null : r.category_id,
                    unit_cost: r.unit_cost,
                    rental_price: r.rental_price,
                    sale_price: r.sale_price
                };
            });

            saveBtn.disabled = true;
            setStatus('Saving…', '');

            var body = new FormData();
            body.append('csrf_token', cfg.csrf);
            body.append('action', 'batch_update');
            body.append('rows', JSON.stringify(payload));

            fetch(cfg.apiUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) {
                        setStatus(res.error || 'Save failed', 'is-dirty');
                        saveBtn.disabled = false;
                        alert(res.error || 'Save failed.');
                        return;
                    }
                    sheet.markClean();
                    setStatus('Saved ' + res.updated + ' item(s)', 'is-saved');
                })
                .catch(function () {
                    setStatus('Save failed', 'is-dirty');
                    saveBtn.disabled = false;
                    alert('Save failed. Please try again.');
                });
        });
    }
})();

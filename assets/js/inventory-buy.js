(function () {
    'use strict';

    var cfg = window.INV_BUY || { items: [], categories: [], preselect: null };
    var categoryOptions = [{ value: '', label: '—' }].concat(
        (cfg.categories || []).map(function (c) {
            return { value: String(c.id), label: c.name };
        })
    );

    function money(n) {
        return (Number(n) || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    var initialRows = [];
    if (cfg.preselect) {
        initialRows.push({
            name: cfg.preselect.name,
            inventory_item_id: cfg.preselect.id,
            qty: 1,
            unit_cost: cfg.preselect.cost,
            category_id: '',
            _meta: { matched: cfg.preselect, autoCost: true }
        });
    }

    var sheet = InventorySheet.mount('#inventory-buy-sheet', {
        mode: 'entry',
        minRows: 8,
        rows: initialRows,
        typeahead: {
            key: 'name',
            items: cfg.items || [],
            fillsCost: true
        },
        columns: [
            {
                key: 'name',
                label: 'Item',
                type: 'text',
                placeholder: 'Type name to find or create',
                typeahead: true,
                widthClass: 'col-item'
            },
            {
                key: 'category_id',
                label: 'Category',
                type: 'select',
                widthClass: 'col-cat',
                options: categoryOptions,
                skipBlank: true
            },
            {
                key: 'qty',
                label: 'Qty',
                type: 'number',
                min: 1,
                default: 1,
                widthClass: 'col-qty',
                align: 'right'
            },
            {
                key: 'unit_cost',
                label: 'Cost',
                type: 'money',
                min: 0,
                step: 0.01,
                default: 0,
                widthClass: 'col-money',
                align: 'right'
            },
            {
                key: 'line_total',
                label: 'Total',
                type: 'readonly',
                widthClass: 'col-money',
                align: 'right',
                format: function (_v, row) {
                    return money((Number(row.qty) || 0) * (Number(row.unit_cost) || 0));
                }
            },
            {
                key: 'stock_after',
                label: 'After',
                type: 'readonly',
                widthClass: 'col-status',
                format: function (_v, row) {
                    var qty = Number(row.qty) || 0;
                    if (row.inventory_item_id && row._meta && row._meta.matched) {
                        var cur = Number(row._meta.matched.qty) || 0;
                        return '<span class="inv-pill inv-pill-restock">Restock</span> ' +
                            cur + '→<strong>' + (cur + qty) + '</strong>';
                    }
                    if (String(row.name || '').trim()) {
                        return '<span class="inv-pill inv-pill-new">New</span> ' + qty;
                    }
                    return '—';
                }
            }
        ],
        isBlankRow: function (row) {
            return !String(row.name || '').trim() && !row.inventory_item_id;
        },
        onTotals: function (info) {
            var totalEl = document.getElementById('inv-buy-total');
            var countEl = document.getElementById('inv-buy-count');
            if (totalEl) totalEl.textContent = money(info.total);
            if (countEl) countEl.textContent = info.count + (info.count === 1 ? ' line' : ' lines');
        }
    });

    if (cfg.preselect) {
        var first = sheet.rows[0];
        if (first) {
            first.inventory_item_id = cfg.preselect.id;
            first._meta = { matched: cfg.preselect, autoCost: true };
            sheet._refreshComputed(first);
        }
    }

    var addBtn = document.getElementById('inv-buy-add-row');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            sheet.addRow(true);
        });
    }

    var form = document.getElementById('inventory-buy-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            var filled = sheet.getFilledRows();
            if (!filled.length) {
                e.preventDefault();
                alert('Add at least one item row before saving.');
                return;
            }
            sheet.toFormFields(form, function (row) {
                return {
                    'line_name[]': row.name || '',
                    'line_inventory_id[]': row.inventory_item_id || '',
                    'line_category_id[]': row.category_id || '',
                    'line_qty[]': row.qty || 1,
                    'line_cost[]': row.unit_cost || 0
                };
            });
        });
    }
})();

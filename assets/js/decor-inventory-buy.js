(function () {
    'use strict';

    function money(n) {
        return (Number(n) || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function syncTotals(sheet) {
        var filled = sheet.getFilledRows();
        var total = 0;
        filled.forEach(function (r) {
            total += (Number(r.quantity) || 0) * (Number(r.unit_price) || 0);
        });
        var totalEl = document.getElementById('decor-buy-total');
        var countEl = document.getElementById('decor-buy-count');
        if (totalEl) totalEl.textContent = money(total);
        if (countEl) countEl.textContent = filled.length + (filled.length === 1 ? ' line' : ' lines');
    }

    var sheet = InventorySheet.mount('#decor-buy-sheet', {
        mode: 'entry',
        minRows: 8,
        columns: [
            {
                key: 'name',
                label: 'Item',
                type: 'text',
                placeholder: 'Item name',
                widthClass: 'col-item'
            },
            {
                key: 'quantity',
                label: 'Qty',
                type: 'number',
                min: 1,
                default: 1,
                widthClass: 'col-qty',
                align: 'right'
            },
            {
                key: 'unit_price',
                label: 'Cost',
                type: 'money',
                min: 0,
                step: 0.01,
                default: 0,
                widthClass: 'col-money',
                align: 'right'
            },
            {
                key: 'default_markup_percent',
                label: 'Markup %',
                type: 'number',
                min: 0,
                step: 0.1,
                default: 0,
                widthClass: 'col-qty',
                align: 'right',
                skipBlank: true
            },
            {
                key: 'line_total',
                label: 'Total',
                type: 'readonly',
                widthClass: 'col-money',
                align: 'right',
                format: function (_v, row) {
                    return money((Number(row.quantity) || 0) * (Number(row.unit_price) || 0));
                }
            },
            {
                key: 'suggested',
                label: 'Rate',
                type: 'readonly',
                widthClass: 'col-money',
                align: 'right',
                format: function (_v, row) {
                    var cost = Number(row.unit_price) || 0;
                    var markup = Number(row.default_markup_percent) || 0;
                    return money(cost * (1 + markup / 100));
                }
            }
        ],
        isBlankRow: function (row) {
            return !String(row.name || '').trim();
        },
        onChange: function (s) {
            syncTotals(s);
        },
        onTotals: function () {
            // overridden by onChange for quantity/unit_price field names
        }
    });

    syncTotals(sheet);

    var addBtn = document.getElementById('decor-buy-add-row');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            sheet.addRow(true);
        });
    }

    var form = document.getElementById('decor-buy-form');
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
                    'line_qty[]': row.quantity || 1,
                    'line_price[]': row.unit_price || 0,
                    'line_markup[]': row.default_markup_percent || 0
                };
            });
        });
    }
})();

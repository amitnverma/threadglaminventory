(function () {
    'use strict';

    var cfg = window.DECOR_GRID;
    if (!cfg || !document.getElementById('decor-edit-sheet')) return;

    function money(n) {
        return (Number(n) || 0).toFixed(2);
    }

    var statusEl = document.getElementById('decor-grid-status');
    var saveBtn = document.getElementById('decor-grid-save');
    var selectedEl = document.getElementById('decor-grid-selected');
    var deleteSelectedBtn = document.getElementById('decor-grid-delete-selected');

    function setStatus(text, cls) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'inv-sheet-status' + (cls ? ' ' + cls : '');
    }

    var sheet = InventorySheet.mount('#decor-edit-sheet', {
        mode: 'edit',
        minRows: 0,
        rows: cfg.rows || [],
        selectableRows: true,
        isRowSelectable: function (row) {
            return !!row.id && !row.is_returned;
        },
        onSelectionChange: function (selected) {
            var count = selected.length;
            if (selectedEl) selectedEl.textContent = count + ' selected';
            if (deleteSelectedBtn) deleteSelectedBtn.disabled = count === 0;
        },
        columns: [
            {
                key: 'name',
                label: 'Item',
                type: 'text',
                placeholder: 'Item name',
                widthClass: 'col-item'
            },
            {
                key: 'purchased_from',
                label: 'Store',
                type: 'text',
                widthClass: 'col-store',
                placeholder: 'Store'
            },
            {
                key: 'purchase_date',
                label: 'Date',
                type: 'date',
                widthClass: 'col-date'
            },
            {
                key: 'quantity',
                label: 'Bought',
                type: 'number',
                min: 1,
                widthClass: 'col-qty',
                align: 'right'
            },
            {
                key: 'quantity_on_hand',
                label: 'Owned',
                type: 'readonly',
                widthClass: 'col-qty',
                align: 'right'
            },
            {
                key: 'available_qty',
                label: 'Free',
                type: 'readonly',
                widthClass: 'col-qty',
                align: 'right',
                format: function (v) {
                    return '<strong>' + (Number(v) || 0) + '</strong>';
                }
            },
            {
                key: 'unit_price',
                label: 'Cost',
                type: 'money',
                min: 0,
                step: 0.01,
                widthClass: 'col-money',
                align: 'right'
            },
            {
                key: 'default_markup_percent',
                label: 'Mk %',
                type: 'number',
                min: 0,
                step: 0.1,
                widthClass: 'col-qty',
                align: 'right'
            },
            {
                key: 'status_label',
                label: 'Status',
                type: 'readonly',
                widthClass: 'col-status',
                format: function (v) {
                    return v || '—';
                }
            }
        ],
        actionsWidthClass: 'col-actions-wide',
        renderRowActions: function (row) {
            if (!row.id) return '';
            var html = '<a class="btn btn-sm btn-secondary" href="decor-inventory-form.php?id=' + row.id + '" title="Advanced">⋯</a>';
            if (!row.is_returned) {
                html += '<button type="button" class="btn btn-sm btn-secondary" data-sheet-action="return" title="Return">↩</button>';
                html += '<button type="button" class="btn btn-sm btn-danger" data-sheet-action="delete" title="Delete">×</button>';
            }
            return html;
        },
        onRowAction: function (action, row) {
            if (!row || !row.id) return;

            if (action === 'return') {
                var dialog = document.getElementById('decor-return-dialog');
                var idEl = document.getElementById('decor_return_id');
                var refundEl = document.getElementById('decor_return_refund');
                var dateEl = document.getElementById('decor_return_date');
                if (idEl) idEl.value = String(row.id);
                if (refundEl) refundEl.value = money((Number(row.quantity) || 0) * (Number(row.unit_price) || 0));
                if (dateEl && !dateEl.value) dateEl.value = new Date().toISOString().slice(0, 10);
                if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
                return;
            }

            if (action === 'delete') {
                if (!confirm('Delete "' + row.name + '"?')) return;
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
                        sheet._notifySelection();
                        setStatus('Item deleted', 'is-saved');
                        // Reload so handoff list stays in sync
                        window.location.reload();
                    })
                    .catch(function () {
                        alert('Delete failed. Please try again.');
                    });
            }
        },
        onDirty: function (dirty) {
            if (saveBtn) saveBtn.disabled = !dirty;
            setStatus(dirty ? 'Unsaved changes' : 'All saved', dirty ? 'is-dirty' : '');
        }
    });

    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function () {
            var selected = sheet.getSelectedRows();
            if (!selected.length) return;
            var count = selected.length;
            if (!confirm('Delete ' + count + ' selected Decor item' + (count === 1 ? '' : 's') + '? Items with reservations or handoff history will be protected.')) return;

            deleteSelectedBtn.disabled = true;
            setStatus('Deleting…', '');

            var body = new FormData();
            body.append('csrf_token', cfg.csrf);
            body.append('action', 'bulk_delete');
            body.append('ids', JSON.stringify(selected.map(function (row) { return row.id; })));

            fetch(cfg.apiUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) {
                        setStatus(res.error || 'Bulk delete failed', 'is-dirty');
                        deleteSelectedBtn.disabled = false;
                        alert(res.error || 'Bulk delete failed.');
                        return;
                    }
                    setStatus('Deleted ' + res.deleted + ' item(s)', 'is-saved');
                    window.location.reload();
                })
                .catch(function () {
                    setStatus('Bulk delete failed', 'is-dirty');
                    deleteSelectedBtn.disabled = false;
                    alert('Bulk delete failed. Please try again.');
                });
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var dirty = sheet.getDirtyRows().filter(function (r) { return r.id; });
            if (!dirty.length) return;

            var payload = dirty.map(function (r) {
                return {
                    id: r.id,
                    name: r.name,
                    purchased_from: r.purchased_from,
                    purchase_date: r.purchase_date,
                    quantity: r.quantity,
                    unit_price: r.unit_price,
                    default_markup_percent: r.default_markup_percent
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
                    window.location.reload();
                })
                .catch(function () {
                    setStatus('Save failed', 'is-dirty');
                    saveBtn.disabled = false;
                    alert('Save failed. Please try again.');
                });
        });
    }
})();

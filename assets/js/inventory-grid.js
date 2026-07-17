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
    var selectedEl = document.getElementById('inv-grid-selected');
    var deleteSelectedBtn = document.getElementById('inv-grid-delete-selected');
    var imageUploader = null;

    function setStatus(text, cls) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'inv-sheet-status' + (cls ? ' ' + cls : '');
    }

    var sheet = InventorySheet.mount('#inventory-edit-sheet', {
        mode: 'edit',
        minRows: 0,
        rows: cfg.rows || [],
        selectableRows: true,
        onSelectionChange: function (selected) {
            var count = selected.length;
            if (selectedEl) selectedEl.textContent = count + ' selected';
            if (deleteSelectedBtn) deleteSelectedBtn.disabled = count === 0;
        },
        columns: [
            {
                key: 'image_url',
                label: 'Image',
                type: 'readonly',
                widthClass: 'col-image',
                className: 'inv-image-cell',
                format: InventoryImageUpload.renderCell
            },
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
                format: function (v) {
                    return String(Number(v) || 0);
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
                '<a class="btn btn-sm btn-secondary" href="inventory-view.php?id=' + row.id + '" title="Details and history">⋯</a>' +
                '<a class="btn btn-sm btn-secondary" href="inventory-buy.php?item=' + row.id + '" title="Restock">+</a>' +
                '<button type="button" class="btn btn-sm btn-danger" data-sheet-action="delete" title="Delete">×</button>'
            );
        },
        onRowAction: function (action, row) {
            if (action === 'upload-image') {
                if (imageUploader) imageUploader.choose(row);
                return;
            }
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
                    sheet._notifySelection();
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

    imageUploader = InventoryImageUpload.create({
        apiUrl: cfg.apiUrl,
        csrf: cfg.csrf,
        onState: setStatus,
        onUploaded: function (row) {
            sheet._refreshComputed(row);
        }
    });

    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function () {
            var selected = sheet.getSelectedRows();
            if (!selected.length) return;
            var count = selected.length;
            if (!confirm('Delete ' + count + ' selected inventory item' + (count === 1 ? '' : 's') + '? This hides them from inventory.')) return;

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
                    var deleted = {};
                    selected.forEach(function (row) { deleted[String(row.id)] = true; });
                    sheet.rows = sheet.rows.filter(function (row) { return !deleted[String(row.id)]; });
                    sheet._render();
                    sheet._notifySelection();
                    setStatus('Deleted ' + res.deleted + ' item(s)', 'is-saved');
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

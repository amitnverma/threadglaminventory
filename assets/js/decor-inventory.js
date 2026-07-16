(function () {
    function formatUsd(amount) {
        return '$' + Number(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateLineTotal() {
        var qty = parseFloat(document.getElementById('decor_quantity')?.value || 0);
        var price = parseFloat(document.getElementById('decor_unit_price')?.value || 0);
        var markup = parseFloat(document.getElementById('decor_markup')?.value || 0);
        var el = document.getElementById('decor_line_total');
        if (el) el.textContent = formatUsd(qty * price);
        var suggested = document.getElementById('decor_suggested_rate');
        if (suggested) suggested.textContent = formatUsd(price * (1 + markup / 100));
    }

    ['decor_quantity', 'decor_unit_price', 'decor_markup'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', updateLineTotal);
    });
    updateLineTotal();

    var returnedChk = document.getElementById('decor_is_returned');
    var returnFields = document.getElementById('decor_return_fields');
    if (returnedChk && returnFields) {
        returnedChk.addEventListener('change', function () {
            returnFields.style.display = returnedChk.checked ? '' : 'none';
        });
    }

    function selectedChecks() {
        return Array.prototype.slice.call(document.querySelectorAll('.decor-item-check:checked'));
    }

    function syncSelectionUi() {
        var selected = selectedChecks();
        var countEl = document.getElementById('decor-selected-count');
        var openBtn = document.getElementById('decor-open-transfer');
        if (countEl) countEl.textContent = selected.length + ' selected';
        if (openBtn) openBtn.disabled = selected.length === 0;

        document.querySelectorAll('.decor-transfer-options').forEach(function (row) {
            var id = row.getAttribute('data-for');
            var checked = document.querySelector('.decor-item-check[value="' + id + '"]');
            row.hidden = !(checked && checked.checked);
        });
    }

    var selectAll = document.getElementById('decor-select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.decor-item-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            syncSelectionUi();
        });
    }

    document.querySelectorAll('.decor-item-check').forEach(function (cb) {
        cb.addEventListener('change', syncSelectionUi);
    });
    syncSelectionUi();

    document.querySelectorAll('.decor-transfer-mode').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var id = sel.getAttribute('data-id');
            var mode = sel.value;
            var newBox = document.querySelector('.decor-transfer-new[data-id="' + id + '"]');
            var existingBox = document.querySelector('.decor-transfer-existing[data-id="' + id + '"]');
            if (newBox) newBox.hidden = mode !== 'new';
            if (existingBox) existingBox.hidden = mode !== 'existing';
        });
    });

    var openTransfer = document.getElementById('decor-open-transfer');
    var transferForm = document.getElementById('decor-transfer-form');
    if (openTransfer && transferForm) {
        openTransfer.addEventListener('click', function () {
            var selected = selectedChecks();
            if (!selected.length) return;

            var missing = false;
            selected.forEach(function (cb) {
                var id = cb.value;
                var free = parseInt(cb.getAttribute('data-free') || '0', 10);
                var modeSel = document.querySelector('.decor-transfer-mode[data-id="' + id + '"]');
                var qtyInput = document.querySelector('.decor-transfer-qty[data-id="' + id + '"]');
                var mode = modeSel ? modeSel.value : '';
                var qty = qtyInput ? parseInt(qtyInput.value || '0', 10) : 0;
                if (!mode || qty < 1 || qty > free) {
                    missing = true;
                    return;
                }
                if (mode === 'existing') {
                    var inv = document.querySelector('.decor-transfer-existing[data-id="' + id + '"] select');
                    if (!inv || !inv.value) missing = true;
                }
            });

            if (missing) {
                alert('For each selected item, choose a valid quantity and Create new or Add to existing.');
                return;
            }

            if (!confirm('Hand off selected quantity(ies) into master inventory? Decor-owned stock will decrease.')) return;
            transferForm.submit();
        });
    }

    var dialog = document.getElementById('decor-return-dialog');
    var cancelBtn = document.getElementById('decor-return-cancel');
    document.querySelectorAll('.decor-return-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!dialog) return;
            document.getElementById('decor_return_id').value = btn.getAttribute('data-id') || '';
            document.getElementById('decor_return_refund').value = btn.getAttribute('data-total') || '0';
            document.getElementById('decor_return_date').value = new Date().toISOString().slice(0, 10);
            if (typeof dialog.showModal === 'function') dialog.showModal();
        });
    });
    if (cancelBtn && dialog) {
        cancelBtn.addEventListener('click', function () {
            dialog.close();
        });
    }
})();

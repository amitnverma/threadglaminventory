(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function money(value) {
        return '$' + (Number(value) || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function number(value) {
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function inventoryLine(item) {
        var available = Math.max(0, number(item.available));
        // Default contract rate = purchase cost from inventory (unit_cost).
        var purchaseCost = Math.max(0, number(item.cost != null ? item.cost : item.price));
        return [
            '<tr class="estimate-line-row" data-inventory-id="' + escapeHtml(item.id || '') + '"',
            ' data-available="' + available + '" data-source-rate="' + purchaseCost.toFixed(2) + '">',
            '<td>',
            '<input type="hidden" name="line_inventory_id[]" value="' + escapeHtml(item.id || '') + '">',
            '<input type="hidden" name="line_type[]" value="' + escapeHtml(item.type || 'inventory') + '">',
            '<input type="hidden" name="line_cost[]" value="' + purchaseCost.toFixed(2) + '">',
            '<input type="text" name="line_label[]" value="' + escapeHtml(item.label || '') + '" class="line-label">',
            '<small class="estimate-source-note">From inventory · purchase cost ' + money(purchaseCost) + '</small>',
            '</td>',
            '<td><div class="estimate-usage">',
            '<input type="number" name="line_qty[]" value="1" min="0" step="0.5" class="line-qty" oninput="updateEstimateTotal()">',
            '<span class="usage-count"><strong>1</strong> of ' + available + '</span>',
            '<span class="usage-track"><span></span></span>',
            '</div></td>',
            '<td><div class="estimate-rate-field">',
            '<input type="number" name="line_price[]" value="' + purchaseCost.toFixed(2) + '" min="0" step="0.01" class="line-price" oninput="updateEstimateTotal()">',
            '<div class="rate-source"><span>Purchase ' + money(purchaseCost) + '</span>',
            '<button type="button" class="rate-reset" onclick="resetEstimateRate(this)">Reset</button></div>',
            '<span class="rate-status">Using purchase cost</span>',
            '</div></td>',
            '<td class="line-amount text-right">' + money(purchaseCost) + '</td>',
            '<td><button type="button" class="btn btn-sm btn-danger" aria-label="Remove line" onclick="this.closest(\'tr\').remove();updateEstimateTotal()">×</button></td>',
            '</tr>'
        ].join('');
    }

    function customLine(item) {
        return [
            '<tr class="estimate-line-row" data-inventory-id="" data-available="" data-source-rate="">',
            '<td>',
            '<input type="hidden" name="line_inventory_id[]" value="">',
            '<input type="hidden" name="line_type[]" value="' + escapeHtml(item.type || 'custom') + '">',
            '<input type="hidden" name="line_cost[]" value="' + number(item.cost).toFixed(2) + '">',
            '<input type="text" name="line_label[]" value="' + escapeHtml(item.label || '') + '" class="line-label">',
            '<small class="estimate-source-note">Custom contract line</small>',
            '</td>',
            '<td><div class="estimate-usage">',
            '<input type="number" name="line_qty[]" value="1" min="0" step="0.5" class="line-qty" oninput="updateEstimateTotal()">',
            '</div></td>',
            '<td><div class="estimate-rate-field">',
            '<input type="number" name="line_price[]" value="' + number(item.price).toFixed(2) + '" min="0" step="0.01" class="line-price" oninput="updateEstimateTotal()">',
            '</div></td>',
            '<td class="line-amount text-right">' + money(number(item.price)) + '</td>',
            '<td><button type="button" class="btn btn-sm btn-danger" aria-label="Remove line" onclick="this.closest(\'tr\').remove();updateEstimateTotal()">×</button></td>',
            '</tr>'
        ].join('');
    }

    window.filterCatalog = function (query) {
        query = String(query || '').trim().toLowerCase();
        document.querySelectorAll('#catalog-list .catalog-item').forEach(function (element) {
            element.style.display = element.dataset.name.includes(query) ? '' : 'none';
        });
    };

    window.addEstimateLine = function (item) {
        var tbody = document.getElementById('estimate-lines');
        if (!tbody) return;

        if (item.id) {
            var existing = tbody.querySelector('tr[data-inventory-id="' + String(item.id).replace(/"/g, '\\"') + '"]');
            if (existing) {
                var qtyInput = existing.querySelector('.line-qty');
                if (qtyInput) {
                    qtyInput.value = String(number(qtyInput.value) + 1);
                    window.updateEstimateTotal();
                    existing.classList.remove('is-highlighted');
                    void existing.offsetWidth;
                    existing.classList.add('is-highlighted');
                }
                return;
            }
        }

        tbody.insertAdjacentHTML('beforeend', item.id ? inventoryLine(item) : customLine(item));
        window.updateEstimateTotal();
        var added = tbody.lastElementChild;
        if (added) {
            added.classList.add('is-highlighted');
            added.querySelector('.line-qty')?.focus();
        }
    };

    window.addCustomLine = function (type) {
        window.addEstimateLine({
            id: '',
            type: type,
            label: type === 'labor' ? 'Labor / Service' : 'Custom Item',
            price: 0,
            cost: 0
        });
    };

    window.resetEstimateRate = function (button) {
        var row = button.closest('.estimate-line-row');
        if (!row) return;
        var input = row.querySelector('.line-price');
        if (!input) return;
        input.value = number(row.dataset.sourceRate).toFixed(2);
        window.updateEstimateTotal();
        input.focus();
    };

    function updateUsage(row, quantity) {
        var availableRaw = row.dataset.available;
        if (availableRaw === '') return;

        var available = Math.max(0, number(availableRaw));
        var count = row.querySelector('.usage-count strong');
        var track = row.querySelector('.usage-track span');
        var usage = row.querySelector('.estimate-usage');
        if (count) count.textContent = String(quantity);
        if (track) {
            var percent = available > 0 ? Math.min(100, Math.max(0, quantity / available * 100)) : 100;
            track.style.width = percent + '%';
        }
        if (usage) usage.classList.toggle('is-over', quantity > available);
    }

    function updateRateStatus(row, rate) {
        var status = row.querySelector('.rate-status');
        if (!status || row.dataset.sourceRate === '') return;
        var sourceRate = number(row.dataset.sourceRate);
        var overridden = Math.abs(rate - sourceRate) > 0.0001;
        status.textContent = overridden ? 'Overridden for contract' : 'Using purchase cost';
        status.classList.toggle('is-overridden', overridden);
    }

    window.updateEstimateTotal = function () {
        var subtotal = 0;
        document.querySelectorAll('#estimate-lines .estimate-line-row').forEach(function (row) {
            var quantity = Math.max(0, number(row.querySelector('.line-qty')?.value));
            var rate = Math.max(0, number(row.querySelector('.line-price')?.value));
            var amount = quantity * rate;
            var amountCell = row.querySelector('.line-amount');
            if (amountCell) amountCell.textContent = money(amount);
            subtotal += amount;
            updateUsage(row, quantity);
            updateRateStatus(row, rate);
        });

        var taxPercent = number(document.getElementById('tax_percent')?.value);
        var discountValue = Math.max(0, number(document.getElementById('discount_value')?.value));
        var discountType = document.getElementById('discount_type')?.value || 'percent';
        var discount = discountType === 'percent' ? subtotal * discountValue / 100 : discountValue;
        var taxable = Math.max(0, subtotal - discount);
        var tax = taxable * taxPercent / 100;
        var total = taxable + tax;

        [
            ['est-subtotal', subtotal],
            ['est-discount', discount],
            ['est-tax', tax],
            ['est-total', total]
        ].forEach(function (pair) {
            var element = document.getElementById(pair[0]);
            if (element) element.textContent = money(pair[1]);
        });
    };

    document.addEventListener('DOMContentLoaded', window.updateEstimateTotal);
})();

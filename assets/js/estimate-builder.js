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

    function formatQty(value) {
        var n = number(value);
        var s = n.toFixed(2);
        return s.replace(/\.?0+$/, '');
    }

    function hideEmptyHint() {
        var hint = document.getElementById('estimate-empty-hint');
        if (hint) hint.hidden = true;
    }

    function inventoryLine(item) {
        var available = Math.max(0, number(item.available));
        var purchaseCost = Math.max(0, number(item.cost != null ? item.cost : item.price));
        return [
            '<tr class="estimate-line-row" data-inventory-id="' + escapeHtml(item.id || '') + '"',
            ' data-available="' + available + '" data-source-rate="' + purchaseCost.toFixed(2) + '">',
            '<td class="col-item">',
            '<input type="hidden" name="line_inventory_id[]" value="' + escapeHtml(item.id || '') + '">',
            '<input type="hidden" name="line_type[]" value="' + escapeHtml(item.type || 'inventory') + '">',
            '<input type="hidden" name="line_cost[]" value="' + purchaseCost.toFixed(2) + '">',
            '<input type="hidden" name="line_source_type[]" value="">',
            '<input type="hidden" name="line_source_id[]" value="">',
            '<input type="text" name="line_label[]" value="' + escapeHtml(item.label || '') + '" class="cell-input line-label" title="From inventory">',
            '</td>',
            '<td class="col-qty">',
            '<div class="estimate-qty-stepper">',
            '<button type="button" class="qty-step qty-minus" onclick="changeEstimateQty(this,-1)" aria-label="Reduce quantity">−</button>',
            '<input type="number" name="line_qty[]" value="1" min="1" max="' + available + '" step="1" class="cell-input cell-money line-qty" oninput="updateEstimateTotal()">',
            '<button type="button" class="qty-step qty-plus" onclick="changeEstimateQty(this,1)" aria-label="Increase quantity">+</button>',
            '</div>',
            '</td>',
            '<td class="col-avail"><div class="estimate-usage" title="Using 1 of ' + available + ' in stock">',
            '<span class="usage-count"><strong>1</strong>/' + available + '</span>',
            '<span class="usage-track"><span></span></span>',
            '</div></td>',
            '<td class="col-money"><span class="cell-readonly cell-money line-cost-display">' + purchaseCost.toFixed(2) + '</span></td>',
            '<td class="col-money"><div class="estimate-rate-cell">',
            '<input type="number" name="line_price[]" value="' + purchaseCost.toFixed(2) + '" min="0" step="0.01" class="cell-input cell-money line-price" oninput="updateEstimateTotal()">',
            '<button type="button" class="rate-reset" onclick="resetEstimateRate(this)" title="Reset to purchase cost">↺</button>',
            '</div></td>',
            '<td class="col-money"><span class="cell-readonly cell-money line-amount">' + purchaseCost.toFixed(2) + '</span></td>',
            '<td class="col-actions"><button type="button" class="btn btn-sm btn-danger" aria-label="Remove line" onclick="this.closest(\'tr\').remove();updateEstimateTotal()">×</button></td>',
            '</tr>'
        ].join('');
    }

    function customLine(item) {
        var cost = number(item.cost);
        var price = number(item.price);
        return [
            '<tr class="estimate-line-row" data-inventory-id="" data-available="" data-source-rate="">',
            '<td class="col-item">',
            '<input type="hidden" name="line_inventory_id[]" value="">',
            '<input type="hidden" name="line_type[]" value="' + escapeHtml(item.type || 'custom') + '">',
            '<input type="hidden" name="line_cost[]" value="' + cost.toFixed(2) + '">',
            '<input type="hidden" name="line_source_type[]" value="">',
            '<input type="hidden" name="line_source_id[]" value="">',
            '<input type="text" name="line_label[]" value="' + escapeHtml(item.label || '') + '" class="cell-input line-label" title="Custom / labor">',
            '</td>',
            '<td class="col-qty">',
            '<div class="estimate-qty-stepper">',
            '<button type="button" class="qty-step qty-minus" onclick="changeEstimateQty(this,-1)" aria-label="Reduce quantity">−</button>',
            '<input type="number" name="line_qty[]" value="1" min="0.5" step="0.5" class="cell-input cell-money line-qty" oninput="updateEstimateTotal()">',
            '<button type="button" class="qty-step qty-plus" onclick="changeEstimateQty(this,1)" aria-label="Increase quantity">+</button>',
            '</div>',
            '</td>',
            '<td class="col-avail"><span class="cell-readonly muted">—</span></td>',
            '<td class="col-money"><span class="cell-readonly cell-money line-cost-display">' + cost.toFixed(2) + '</span></td>',
            '<td class="col-money"><div class="estimate-rate-cell">',
            '<input type="number" name="line_price[]" value="' + price.toFixed(2) + '" min="0" step="0.01" class="cell-input cell-money line-price" oninput="updateEstimateTotal()">',
            '</div></td>',
            '<td class="col-money"><span class="cell-readonly cell-money line-amount">' + price.toFixed(2) + '</span></td>',
            '<td class="col-actions"><button type="button" class="btn btn-sm btn-danger" aria-label="Remove line" onclick="this.closest(\'tr\').remove();updateEstimateTotal()">×</button></td>',
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
                    var available = Math.max(0, number(existing.dataset.available));
                    var nextQuantity = number(qtyInput.value) + 1;
                    if (nextQuantity > available) {
                        existing.classList.remove('is-highlighted');
                        void existing.offsetWidth;
                        existing.classList.add('is-over-limit');
                        setTimeout(function () { existing.classList.remove('is-over-limit'); }, 900);
                        return;
                    }
                    qtyInput.value = String(nextQuantity);
                    window.updateEstimateTotal();
                    existing.classList.remove('is-highlighted');
                    void existing.offsetWidth;
                    existing.classList.add('is-highlighted');
                }
                return;
            }
        }

        hideEmptyHint();
        tbody.insertAdjacentHTML('beforeend', item.id ? inventoryLine(item) : customLine(item));
        window.updateEstimateTotal();
        var added = tbody.lastElementChild;
        if (added) {
            added.classList.add('is-highlighted');
            var focusEl = added.querySelector('.line-qty');
            if (focusEl) focusEl.focus();
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

    window.changeEstimateQty = function (button, direction) {
        var row = button.closest('.estimate-line-row');
        if (!row) return;
        var input = row.querySelector('.line-qty');
        if (!input) return;

        var step = Math.max(0.01, number(input.step) || 1);
        var current = Math.max(0, number(input.value));
        var next = current + (direction * step);
        var isInventory = row.dataset.inventoryId !== '';
        var max = isInventory ? Math.max(0, number(row.dataset.available)) : Infinity;

        if (direction < 0 && next < step) {
            row.remove();
            window.updateEstimateTotal();
            return;
        }
        if (next > max) {
            row.classList.remove('is-over-limit');
            void row.offsetWidth;
            row.classList.add('is-over-limit');
            setTimeout(function () { row.classList.remove('is-over-limit'); }, 900);
            return;
        }

        input.value = formatQty(next);
        window.updateEstimateTotal();
        input.focus();
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
        var count = row.querySelector('.usage-count');
        var track = row.querySelector('.usage-track span');
        var usage = row.querySelector('.estimate-usage');
        if (count) {
            count.innerHTML = '<strong>' + escapeHtml(formatQty(quantity)) + '</strong>/' + available;
        }
        if (usage) {
            usage.title = 'Using ' + formatQty(quantity) + ' of ' + available + ' in stock';
        }
        if (track) {
            var percent = available > 0 ? Math.min(100, Math.max(0, quantity / available * 100)) : 100;
            track.style.width = percent + '%';
        }
        if (usage) usage.classList.toggle('is-over', quantity > available);
    }

    function updateRateReset(row, rate) {
        var reset = row.querySelector('.rate-reset');
        if (!reset || row.dataset.sourceRate === '') return;
        var sourceRate = number(row.dataset.sourceRate);
        var overridden = Math.abs(rate - sourceRate) > 0.0001;
        reset.classList.toggle('is-visible', overridden);
    }

    function updateCatalogAvailability() {
        document.querySelectorAll('#catalog-list .catalog-item[data-inventory-id]').forEach(function (item) {
            var id = item.dataset.inventoryId;
            var total = Math.max(0, number(item.dataset.stockTotal));
            var row = document.querySelector('#estimate-lines .estimate-line-row[data-inventory-id="' + id.replace(/"/g, '\\"') + '"]');
            var used = row ? Math.max(0, number(row.querySelector('.line-qty') && row.querySelector('.line-qty').value)) : 0;
            var remaining = Math.max(0, total - used);
            var count = item.querySelector('.catalog-stock-count');
            var add = item.querySelector('.catalog-add-btn');
            if (count) {
                count.textContent = formatQty(remaining) + ' free';
                count.classList.toggle('is-available', remaining > 0);
                count.classList.toggle('is-empty', remaining <= 0);
            }
            if (add) {
                add.disabled = remaining <= 0;
                add.title = remaining > 0 ? 'Add one' : 'All stock is already on this estimate';
            }
        });
    }

    function updateProfit(preTaxRevenue) {
        var profitEl = document.getElementById('est-profit');
        if (!profitEl) return;
        var totalCost = 0;
        document.querySelectorAll('#estimate-lines .estimate-line-row').forEach(function (row) {
            var quantity = Math.max(0, number(row.querySelector('.line-qty') && row.querySelector('.line-qty').value));
            var costInput = row.querySelector('input[name="line_cost[]"]');
            totalCost += quantity * number(costInput && costInput.value);
        });
        profitEl.textContent = money(preTaxRevenue - totalCost);
    }

    window.updateEstimateTotal = function () {
        var subtotal = 0;
        document.querySelectorAll('#estimate-lines .estimate-line-row').forEach(function (row) {
            var quantity = Math.max(0, number(row.querySelector('.line-qty') && row.querySelector('.line-qty').value));
            var rate = Math.max(0, number(row.querySelector('.line-price') && row.querySelector('.line-price').value));
            var amount = quantity * rate;
            var amountCell = row.querySelector('.line-amount');
            if (amountCell) amountCell.textContent = amount.toFixed(2);
            subtotal += amount;
            updateUsage(row, quantity);
            updateRateReset(row, rate);
        });

        var taxPercent = number(document.getElementById('tax_percent') && document.getElementById('tax_percent').value);
        var discountValue = Math.max(0, number(document.getElementById('discount_value') && document.getElementById('discount_value').value));
        var discountType = (document.getElementById('discount_type') && document.getElementById('discount_type').value) || 'percent';
        var discount = discountType === 'percent' ? subtotal * discountValue / 100 : discountValue;
        discount = Math.max(0, Math.min(subtotal, discount));
        var profitAmount = Math.max(0, number(document.getElementById('profit_amount') && document.getElementById('profit_amount').value));
        var taxable = Math.max(0, subtotal - discount + profitAmount);
        var tax = taxable * taxPercent / 100;
        var total = taxable + tax;

        [
            ['est-subtotal', subtotal],
            ['est-discount', discount],
            ['est-profit-added', profitAmount],
            ['est-tax', tax],
            ['est-total', total]
        ].forEach(function (pair) {
            var element = document.getElementById(pair[0]);
            if (element) element.textContent = money(pair[1]);
        });
        updateCatalogAvailability();
        updateProfit(taxable);
    };

    document.addEventListener('DOMContentLoaded', window.updateEstimateTotal);
})();

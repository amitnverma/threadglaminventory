// Simple helpers
document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
        if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });
});

function formatUsd(amount) {
    return '$' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

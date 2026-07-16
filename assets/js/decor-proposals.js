(function () {
    'use strict';

    var search = document.getElementById('decor-picker-search');
    if (search) {
        search.addEventListener('input', function () {
            var q = search.value.toLowerCase().trim();
            document.querySelectorAll('#decor-picker-list .decor-picker-item').forEach(function (el) {
                var name = el.getAttribute('data-name') || '';
                el.style.display = !q || name.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    var unavailableToggle = document.getElementById('decor-toggle-unavailable');
    var unavailableList = document.getElementById('decor-unavailable-list');
    if (unavailableToggle && unavailableList) {
        var count = unavailableToggle.getAttribute('data-count') || '0';
        unavailableToggle.addEventListener('click', function () {
            var willShow = unavailableList.hidden;
            unavailableList.hidden = !willShow;
            unavailableToggle.textContent = willShow
                ? ('Hide ' + count + ' unavailable')
                : ('Show ' + count + ' unavailable');
        });
    }

    function closeLineEdit(row) {
        if (!row) return;
        row.classList.remove('is-editing');
        var editCell = row.querySelector('.decor-line-edit-cell');
        if (editCell) editCell.hidden = true;
        row.querySelectorAll('.decor-line-view').forEach(function (cell) {
            cell.hidden = false;
        });
    }

    document.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.decor-line-edit-btn');
        if (editBtn) {
            var row = editBtn.closest('.decor-line-row');
            if (!row) return;
            document.querySelectorAll('.decor-line-row.is-editing').forEach(closeLineEdit);
            row.classList.add('is-editing');
            row.querySelectorAll('.decor-line-view').forEach(function (cell) {
                cell.hidden = true;
            });
            var editCell = row.querySelector('.decor-line-edit-cell');
            if (editCell) {
                editCell.hidden = false;
                var focusInput = editCell.querySelector('input:not([disabled])');
                if (focusInput) focusInput.focus();
            }
            return;
        }

        var cancelBtn = e.target.closest('.decor-line-cancel');
        if (cancelBtn) {
            closeLineEdit(cancelBtn.closest('.decor-line-row'));
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var editing = document.querySelector('.decor-line-row.is-editing');
        if (editing) closeLineEdit(editing);
    });
})();

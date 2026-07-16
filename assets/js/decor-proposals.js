(function () {
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
})();

// Contract editor helpers
function insertPlaceholder(text) {
    var ta = document.getElementById('contract-content');
    if (!ta) return;
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + text + ta.value.substring(end);
    ta.selectionStart = ta.selectionEnd = start + text.length;
    ta.focus();
    updateContractPreview();
}

function updateContractPreview() {
    var ta = document.getElementById('contract-content');
    var preview = document.getElementById('contract-preview');
    if (!ta || !preview) return;
    preview.innerHTML = ta.value;
}

document.addEventListener('DOMContentLoaded', function () {
    var ta = document.getElementById('contract-content');
    if (ta) {
        ta.addEventListener('input', updateContractPreview);
        updateContractPreview();
    }
});

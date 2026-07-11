// Contract WYSIWYG editor — user-friendly, no HTML tags visible

var PLACEHOLDER_LABELS = window.CONTRACT_PLACEHOLDER_LABELS || {};

function placeholdersToChips(html) {
    if (!html) return '';
    return html.replace(/\{\{(\w+)\}\}/g, function (match, key) {
        var label = PLACEHOLDER_LABELS[key] || key.replace(/_/g, ' ');
        return '<span class="ph-tag" contenteditable="false" data-ph="' + key + '">' + label + '</span>';
    });
}

function chipsToPlaceholders(html) {
    if (!html) return '';
    var div = document.createElement('div');
    div.innerHTML = html;
    div.querySelectorAll('.ph-tag').forEach(function (el) {
        var key = el.getAttribute('data-ph');
        if (key) el.outerHTML = '{{' + key + '}}';
    });
    return div.innerHTML;
}

function insertPlaceholder(key, label) {
    if (!tinymce.activeEditor) return;
    var chip = '<span class="ph-tag" contenteditable="false" data-ph="' + key + '">' + label + '</span>&nbsp;';
    tinymce.activeEditor.insertContent(chip);
    tinymce.activeEditor.focus();
}

function syncEditorToTextarea() {
    var editor = tinymce.get('contract-content');
    if (!editor) return;
    var raw = chipsToPlaceholders(editor.getContent());
    document.getElementById('contract-content').value = raw;
}

document.addEventListener('DOMContentLoaded', function () {
    var textarea = document.getElementById('contract-content');
    if (!textarea) return;

    var initialContent = placeholdersToChips(textarea.value);

    tinymce.init({
        selector: '#contract-content',
        license_key: 'gpl',
        height: 620,
        menubar: false,
        statusbar: false,
        branding: false,
        promotion: false,
        plugins: 'lists table link fullscreen searchreplace',
        toolbar: 'undo redo | styles | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | table | removeformat | fullscreen',
        style_formats: [
            { title: 'Heading 1', block: 'h1' },
            { title: 'Heading 2', block: 'h2' },
            { title: 'Heading 3', block: 'h3' },
            { title: 'Paragraph', block: 'p' },
        ],
        content_style: [
            'body { font-family: Georgia, "Times New Roman", serif; font-size: 14px; line-height: 1.75; color: #1f2937; max-width: 720px; margin: 0 auto; padding: 24px 20px; }',
            'h1 { color: #5b21b6; text-align: center; font-size: 1.5rem; margin-bottom: .5rem; }',
            'h2 { color: #374151; font-size: 1.1rem; margin-top: 1.5rem; border-bottom: 1px solid #e5e7eb; padding-bottom: .3rem; }',
            'table { width: 100%; border-collapse: collapse; margin: 1rem 0; }',
            'td, th { border: 1px solid #d1d5db; padding: 8px 10px; }',
            'th { background: #f9fafb; font-weight: 600; }',
            '.ph-tag { display: inline-block; background: #ede9fe; color: #5b21b6; padding: 2px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; border: 1px solid #c4b5fd; font-family: Inter, sans-serif; }',
        ].join('\n'),
        setup: function (editor) {
            editor.on('init', function () {
                editor.setContent(initialContent);
            });
        },
    });

    var form = document.getElementById('contract-form');
    if (form) {
        form.addEventListener('submit', function () {
            syncEditorToTextarea();
        });
    }
});

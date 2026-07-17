(function (global) {
    'use strict';

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderCell(url, row) {
        var name = row && row.name ? row.name : 'inventory item';
        return (
            '<button type="button" class="inv-image-button" data-sheet-action="upload-image" ' +
                'title="Add or replace image" aria-label="Add or replace image for ' + esc(name) + '">' +
                '<img src="' + esc(url || 'assets/img/no-image.svg') + '" alt="">' +
                '<span class="inv-image-camera" aria-hidden="true">📷</span>' +
            '</button>'
        );
    }

    function create(options) {
        var opts = options || {};
        var activeRow = null;
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.hidden = true;
        document.body.appendChild(input);

        function setState(text, type) {
            if (typeof opts.onState === 'function') {
                opts.onState(text, type || '');
            }
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            var row = activeRow;
            input.value = '';
            if (!file || !row || !row.id) return;

            var body = new FormData();
            body.append('csrf_token', opts.csrf || '');
            body.append('action', 'upload_image');
            body.append('id', String(row.id));
            body.append('image', file);

            setState('Uploading image…');
            fetch(opts.apiUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (result) {
                    if (!result.ok) {
                        throw new Error(result.error || 'Image upload failed.');
                    }
                    row.image_url = result.image_url;
                    if (typeof opts.onUploaded === 'function') {
                        opts.onUploaded(row, result);
                    }
                    setState('Image uploaded', 'is-saved');
                })
                .catch(function (error) {
                    setState('Image upload failed', 'is-dirty');
                    alert(error.message || 'Image upload failed. Please try again.');
                });
        });

        return {
            choose: function (row) {
                if (!row || !row.id) return;
                activeRow = row;
                input.click();
            }
        };
    }

    global.InventoryImageUpload = {
        create: create,
        renderCell: renderCell
    };
})(window);

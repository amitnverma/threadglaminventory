/**
 * Compact Excel-like grid for inventory buy/edit sheets.
 * Usage:
 *   var sheet = InventorySheet.mount(rootEl, options);
 *   sheet.getRows(); sheet.addRow(); sheet.markClean();
 */
(function (global) {
    'use strict';

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function money(n) {
        var v = Number(n) || 0;
        return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parseTsv(text) {
        return String(text || '')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .split('\n')
            .filter(function (line, i, arr) {
                return line.length > 0 || i < arr.length - 1;
            })
            .map(function (line) {
                return line.split('\t');
            })
            .filter(function (cols) {
                return cols.some(function (c) { return String(c).trim() !== ''; });
            });
    }

    function defaultValue(col) {
        if (col.type === 'number' || col.type === 'money') return col.default != null ? col.default : (col.min != null ? col.min : 0);
        if (col.type === 'select') return col.default != null ? col.default : '';
        return col.default != null ? col.default : '';
    }

    function InventorySheet(root, options) {
        this.root = root;
        this.opts = options || {};
        this.columns = this.opts.columns || [];
        this.minRows = this.opts.minRows != null ? this.opts.minRows : 8;
        this.rows = [];
        this.dirty = false;
        this._idSeq = 0;
        this._typeahead = this.opts.typeahead || null;
        this._onChange = typeof this.opts.onChange === 'function' ? this.opts.onChange : null;
        this._onDirty = typeof this.opts.onDirty === 'function' ? this.opts.onDirty : null;

        this._build();
        var initial = this.opts.rows || [];
        if (initial.length) {
            initial.forEach(function (r) { this._pushRow(r, false); }.bind(this));
        }
        while (this.rows.length < this.minRows) {
            this._pushRow({}, false);
        }
        this._render();
        this._bind();
        this.markClean();
    }

    InventorySheet.prototype._build = function () {
        this.root.classList.add('inv-sheet-root');
        this.root.innerHTML =
            '<div class="inv-sheet-wrap">' +
            '<table class="inv-sheet" role="grid">' +
            '<thead><tr></tr></thead>' +
            '<tbody></tbody>' +
            '</table></div>';
        this.theadRow = this.root.querySelector('thead tr');
        this.tbody = this.root.querySelector('tbody');

        this.columns.forEach(function (col) {
            var th = document.createElement('th');
            th.textContent = col.label || col.key;
            var classes = [];
            if (col.widthClass) classes.push(col.widthClass);
            if (col.align === 'right') classes.push('is-right');
            if (classes.length) th.className = classes.join(' ');
            if (col.width) th.style.width = col.width;
            this.theadRow.appendChild(th);
        }.bind(this));

        if (this.opts.rowActions !== false) {
            var thAct = document.createElement('th');
            thAct.className = this.opts.actionsWidthClass || 'col-actions';
            thAct.textContent = '';
            this.theadRow.appendChild(thAct);
        }
    };

    InventorySheet.prototype._emptyRow = function (data) {
        var row = { _uid: 'r' + (++this._idSeq), _dirty: false, _meta: {} };
        this.columns.forEach(function (col) {
            if (data && data[col.key] != null) {
                row[col.key] = data[col.key];
            } else {
                row[col.key] = defaultValue(col);
            }
        });
        if (data) {
            Object.keys(data).forEach(function (k) {
                if (k.charAt(0) === '_' || row[k] !== undefined) return;
                row[k] = data[k];
            });
            if (data.id != null) row.id = data.id;
            if (data._meta) row._meta = data._meta;
        }
        return row;
    };

    InventorySheet.prototype._pushRow = function (data, dirty) {
        var row = this._emptyRow(data || {});
        row._dirty = !!dirty;
        this.rows.push(row);
        return row;
    };

    InventorySheet.prototype._isBlank = function (row) {
        if (typeof this.opts.isBlankRow === 'function') {
            return this.opts.isBlankRow(row);
        }
        return this.columns.every(function (col) {
            if (col.type === 'readonly' || col.skipBlank) return true;
            var v = row[col.key];
            if (col.type === 'number' || col.type === 'money') {
                var n = Number(v);
                var def = Number(defaultValue(col));
                return !n || n === def;
            }
            return String(v == null ? '' : v).trim() === '';
        });
    };

    InventorySheet.prototype._render = function () {
        var self = this;
        this.tbody.innerHTML = '';
        this.rows.forEach(function (row, index) {
            self.tbody.appendChild(self._renderRow(row, index));
        });
        this._updateTotals();
    };

    InventorySheet.prototype._renderRow = function (row, index) {
        var self = this;
        var tr = document.createElement('tr');
        tr.dataset.uid = row._uid;
        tr.dataset.index = String(index);
        if (row._dirty) tr.classList.add('is-dirty');
        if (row.id == null && !self._isBlank(row) && self.opts.mode === 'entry') {
            tr.classList.add('is-new-row');
        }

        this.columns.forEach(function (col) {
            var td = document.createElement('td');
            var classes = [];
            if (col.widthClass) classes.push(col.widthClass);
            if (col.align === 'right') classes.push('is-right');
            if (classes.length) td.className = classes.join(' ');
            td.appendChild(self._renderCell(row, col, index));
            tr.appendChild(td);
        });

        if (this.opts.rowActions !== false) {
            var tdAct = document.createElement('td');
            tdAct.className = this.opts.actionsWidthClass || 'col-actions';
            var actions = document.createElement('div');
            actions.className = 'row-actions';

            if (typeof this.opts.renderRowActions === 'function') {
                actions.innerHTML = this.opts.renderRowActions(row, index) || '';
            } else {
                var del = document.createElement('button');
                del.type = 'button';
                del.className = 'btn btn-sm btn-danger inv-sheet-remove';
                del.title = 'Remove row';
                del.setAttribute('aria-label', 'Remove row');
                del.textContent = '×';
                actions.appendChild(del);
            }
            tdAct.appendChild(actions);
            tr.appendChild(tdAct);
        }

        return tr;
    };

    InventorySheet.prototype._renderCell = function (row, col, index) {
        var self = this;
        var val = row[col.key];

        if (col.type === 'readonly' || col.readonly) {
            var span = document.createElement('div');
            span.className = 'cell-readonly' + (col.align === 'right' ? ' cell-money' : '');
            if (col.className) span.className += ' ' + col.className;
            if (typeof col.format === 'function') {
                span.innerHTML = col.format(val, row);
            } else if (col.type === 'money') {
                span.textContent = money(val);
            } else {
                span.textContent = val == null || val === '' ? '—' : String(val);
            }
            return span;
        }

        if (col.type === 'select') {
            var sel = document.createElement('select');
            sel.className = 'cell-select';
            sel.dataset.key = col.key;
            sel.dataset.uid = row._uid;
            var opts = typeof col.options === 'function' ? col.options(row) : (col.options || []);
            opts.forEach(function (opt) {
                var o = document.createElement('option');
                o.value = opt.value;
                o.textContent = opt.label;
                if (String(opt.value) === String(val)) o.selected = true;
                sel.appendChild(o);
            });
            return sel;
        }

        if (col.typeahead || (this._typeahead && this._typeahead.key === col.key)) {
            var wrap = document.createElement('div');
            wrap.className = 'inv-typeahead';
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'cell-input';
            input.dataset.key = col.key;
            input.dataset.uid = row._uid;
            input.value = val == null ? '' : String(val);
            input.autocomplete = 'off';
            input.placeholder = col.placeholder || '';
            wrap.appendChild(input);
            return wrap;
        }

        var input = document.createElement('input');
        input.className = 'cell-input' + (col.align === 'right' || col.type === 'money' || col.type === 'number' ? ' cell-money' : '');
        input.dataset.key = col.key;
        input.dataset.uid = row._uid;
        if (col.type === 'number' || col.type === 'money') {
            input.type = 'number';
            if (col.step != null) input.step = String(col.step);
            else input.step = col.type === 'money' ? '0.01' : '1';
            if (col.min != null) input.min = String(col.min);
            if (col.max != null) input.max = String(col.max);
        } else if (col.type === 'date') {
            input.type = 'date';
        } else {
            input.type = 'text';
        }
        input.value = val == null ? '' : String(val);
        if (col.placeholder) input.placeholder = col.placeholder;
        return input;
    };

    InventorySheet.prototype._findRow = function (uid) {
        for (var i = 0; i < this.rows.length; i++) {
            if (this.rows[i]._uid === uid) return this.rows[i];
        }
        return null;
    };

    InventorySheet.prototype._setDirty = function (row, dirty) {
        row._dirty = dirty !== false;
        this.dirty = this.rows.some(function (r) { return r._dirty; });
        var tr = this.tbody.querySelector('tr[data-uid="' + row._uid + '"]');
        if (tr) tr.classList.toggle('is-dirty', row._dirty);
        if (this._onDirty) this._onDirty(this.dirty);
    };

    InventorySheet.prototype._updateField = function (uid, key, value, fromPaste) {
        var row = this._findRow(uid);
        if (!row) return;
        var col = null;
        for (var i = 0; i < this.columns.length; i++) {
            if (this.columns[i].key === key) { col = this.columns[i]; break; }
        }
        if (col && (col.type === 'number' || col.type === 'money')) {
            value = value === '' || value == null ? defaultValue(col) : Number(value);
            if (isNaN(value)) value = defaultValue(col);
        }
        if (String(row[key]) === String(value) && !fromPaste) return;
        row[key] = value;

        if (this._typeahead && this._typeahead.key === key) {
            this._resolveTypeahead(row, value);
        }

        if (typeof this.opts.afterChange === 'function') {
            this.opts.afterChange(row, key, value);
        }

        this._setDirty(row, true);
        this._refreshComputed(row);
        if (this._onChange) this._onChange(this);
        this._ensureTrailingBlank();
    };

    InventorySheet.prototype._resolveTypeahead = function (row, value) {
        var cfg = this._typeahead;
        if (!cfg || !cfg.items) return;
        var name = String(value || '').trim().toLowerCase();
        var match = null;
        if (name) {
            for (var i = 0; i < cfg.items.length; i++) {
                if (String(cfg.items[i].name || '').toLowerCase() === name) {
                    match = cfg.items[i];
                    break;
                }
            }
        }
        if (match) {
            row.inventory_item_id = match.id;
            row._meta.matched = match;
            if (cfg.fillCost && (row.unit_cost == null || Number(row.unit_cost) === 0 || row._meta.autoCost)) {
                row.unit_cost = match.cost;
                row._meta.autoCost = true;
            }
        } else {
            row.inventory_item_id = null;
            row._meta.matched = null;
        }
        var tr = this.tbody.querySelector('tr[data-uid="' + row._uid + '"]');
        if (tr && match && cfg.fillCost) {
            var costInput = tr.querySelector('[data-key="unit_cost"]');
            if (costInput && row._meta.autoCost) costInput.value = Number(row.unit_cost).toFixed(2);
        }
    };

    InventorySheet.prototype._refreshComputed = function (row) {
        var tr = this.tbody.querySelector('tr[data-uid="' + row._uid + '"]');
        if (!tr) return;
        this.columns.forEach(function (col) {
            if (col.type !== 'readonly' && !col.readonly) return;
            if (typeof col.format !== 'function' && col.type !== 'money') return;
            var td = tr.querySelector('[data-key="' + col.key + '"]');
            // readonly cells don't have data-key on inner — find by column index
        });
        // Re-render readonly cells in this row only
        var cells = tr.querySelectorAll('td');
        this.columns.forEach(function (col, i) {
            if (!(col.type === 'readonly' || col.readonly)) return;
            var td = cells[i];
            if (!td) return;
            td.innerHTML = '';
            td.appendChild(this._renderCell(row, col, 0));
        }.bind(this));
        this._updateTotals();
    };

    InventorySheet.prototype._updateTotals = function () {
        if (typeof this.opts.onTotals === 'function') {
            var filled = this.getFilledRows();
            var total = 0;
            filled.forEach(function (r) {
                var qty = Number(r.qty != null ? r.qty : r.quantity) || 0;
                var cost = Number(r.unit_cost != null ? r.unit_cost : r.unit_price) || 0;
                total += qty * cost;
            });
            this.opts.onTotals({ count: filled.length, total: total, rows: filled });
        }
    };

    InventorySheet.prototype._ensureTrailingBlank = function () {
        if (this.opts.mode !== 'entry') return;
        if (!this.rows.length || !this._isBlank(this.rows[this.rows.length - 1])) {
            var row = this._pushRow({}, false);
            var index = this.rows.length - 1;
            this.tbody.appendChild(this._renderRow(row, index));
        }
    };

    InventorySheet.prototype._bind = function () {
        var self = this;

        this.tbody.addEventListener('input', function (e) {
            var el = e.target;
            if (!el.dataset || !el.dataset.uid || !el.dataset.key) return;
            if (el.dataset.key === 'unit_cost' || el.dataset.key === 'unit_price') {
                var row = self._findRow(el.dataset.uid);
                if (row) row._meta.autoCost = false;
            }
            self._updateField(el.dataset.uid, el.dataset.key, el.value);
        });

        this.tbody.addEventListener('change', function (e) {
            var el = e.target;
            if (!el.dataset || !el.dataset.uid || !el.dataset.key) return;
            self._updateField(el.dataset.uid, el.dataset.key, el.value);
        });

        this.tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('.inv-sheet-remove');
            if (btn) {
                var tr = btn.closest('tr');
                if (!tr) return;
                self.removeRow(tr.dataset.uid);
                return;
            }
            if (typeof self.opts.onRowAction === 'function') {
                var actionBtn = e.target.closest('[data-sheet-action]');
                if (actionBtn) {
                    var tr2 = actionBtn.closest('tr');
                    var row = tr2 ? self._findRow(tr2.dataset.uid) : null;
                    self.opts.onRowAction(actionBtn.getAttribute('data-sheet-action'), row, actionBtn);
                }
            }
        });

        this.tbody.addEventListener('keydown', function (e) {
            var el = e.target;
            if (!el.classList.contains('cell-input') && !el.classList.contains('cell-select')) return;
            var tr = el.closest('tr');
            if (!tr) return;
            var inputs = Array.prototype.slice.call(tr.querySelectorAll('.cell-input, .cell-select'));
            var idx = inputs.indexOf(el);
            var rowIndex = parseInt(tr.dataset.index, 10);

            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var nextTr = self.tbody.querySelector('tr[data-index="' + (rowIndex + 1) + '"]');
                if (!nextTr) {
                    self.addRow(true);
                    nextTr = self.tbody.querySelector('tr[data-index="' + (rowIndex + 1) + '"]');
                }
                if (nextTr) {
                    var nextInputs = nextTr.querySelectorAll('.cell-input, .cell-select');
                    var focusEl = nextInputs[Math.min(idx, nextInputs.length - 1)];
                    if (focusEl) focusEl.focus();
                }
            }

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                if (el.tagName === 'SELECT') return;
                e.preventDefault();
                var targetIndex = rowIndex + (e.key === 'ArrowDown' ? 1 : -1);
                var targetTr = self.tbody.querySelector('tr[data-index="' + targetIndex + '"]');
                if (targetTr) {
                    var tInputs = targetTr.querySelectorAll('.cell-input, .cell-select');
                    var tEl = tInputs[Math.min(idx, tInputs.length - 1)];
                    if (tEl) tEl.focus();
                }
            }
        });

        this.tbody.addEventListener('paste', function (e) {
            var el = e.target;
            if (!el.classList.contains('cell-input')) return;
            var text = (e.clipboardData || window.clipboardData).getData('text');
            if (!text || text.indexOf('\t') === -1 && text.indexOf('\n') === -1) return;
            e.preventDefault();
            self._paste(el, text);
        });

        if (this._typeahead) {
            this._bindTypeahead();
        }
    };

    InventorySheet.prototype._bindTypeahead = function () {
        var self = this;
        var cfg = this._typeahead;
        var menu = null;
        var activeUid = null;

        function closeMenu() {
            if (menu && menu.parentNode) menu.parentNode.removeChild(menu);
            menu = null;
            activeUid = null;
        }

        function openMenu(input, items) {
            closeMenu();
            var wrap = input.closest('.inv-typeahead');
            if (!wrap) return;
            menu = document.createElement('div');
            menu.className = 'inv-typeahead-menu';
            activeUid = input.dataset.uid;

            if (!items.length) {
                menu.innerHTML = '<div class="inv-typeahead-empty">New item — will be created on save</div>';
            } else {
                items.slice(0, 12).forEach(function (item, i) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'inv-typeahead-option' + (i === 0 ? ' is-active' : '');
                    btn.innerHTML = esc(item.name) +
                        '<small>' + esc(item.sku || '') +
                        (item.qty != null ? ' · ' + item.qty + ' in stock' : '') +
                        '</small>';
                    btn.addEventListener('mousedown', function (ev) {
                        ev.preventDefault();
                        input.value = item.name;
                        self._updateField(input.dataset.uid, cfg.key, item.name);
                        var row = self._findRow(input.dataset.uid);
                        if (row) {
                            row.inventory_item_id = item.id;
                            row._meta.matched = item;
                            if (cfg.fillCost) {
                                row.unit_cost = item.cost;
                                row._meta.autoCost = true;
                            }
                            self._refreshComputed(row);
                            var tr = self.tbody.querySelector('tr[data-uid="' + row._uid + '"]');
                            if (tr && cfg.fillCost) {
                                var costInput = tr.querySelector('[data-key="unit_cost"]');
                                if (costInput) costInput.value = Number(item.cost).toFixed(2);
                            }
                        }
                        closeMenu();
                        var tr2 = input.closest('tr');
                        var qty = tr2 && tr2.querySelector('[data-key="qty"]');
                        if (qty) qty.focus();
                    });
                    menu.appendChild(btn);
                });
            }
            wrap.appendChild(menu);
        }

        this.tbody.addEventListener('focusin', function (e) {
            var el = e.target;
            if (!el.classList.contains('cell-input') || el.dataset.key !== cfg.key) return;
            var q = String(el.value || '').trim().toLowerCase();
            var items = (cfg.items || []).filter(function (it) {
                if (!q) return true;
                return String(it.name || '').toLowerCase().indexOf(q) !== -1 ||
                    String(it.sku || '').toLowerCase().indexOf(q) !== -1;
            });
            openMenu(el, q ? items : items.slice(0, 8));
        });

        this.tbody.addEventListener('input', function (e) {
            var el = e.target;
            if (!el.classList.contains('cell-input') || el.dataset.key !== cfg.key) return;
            var q = String(el.value || '').trim().toLowerCase();
            var items = (cfg.items || []).filter(function (it) {
                if (!q) return true;
                return String(it.name || '').toLowerCase().indexOf(q) !== -1 ||
                    String(it.sku || '').toLowerCase().indexOf(q) !== -1;
            });
            openMenu(el, items);
        });

        this.tbody.addEventListener('focusout', function (e) {
            setTimeout(function () {
                if (!menu) return;
                if (menu.contains(document.activeElement)) return;
                closeMenu();
            }, 120);
        });
    };

    InventorySheet.prototype._paste = function (startInput, text) {
        var matrix = parseTsv(text);
        if (!matrix.length) return;
        var tr = startInput.closest('tr');
        var startRow = parseInt(tr.dataset.index, 10);
        var editableCols = this.columns.filter(function (c) {
            return !(c.type === 'readonly' || c.readonly);
        });
        var startColKey = startInput.dataset.key;
        var startCol = 0;
        for (var i = 0; i < editableCols.length; i++) {
            if (editableCols[i].key === startColKey) { startCol = i; break; }
        }

        for (var r = 0; r < matrix.length; r++) {
            while (startRow + r >= this.rows.length) {
                this._pushRow({}, true);
            }
            var row = this.rows[startRow + r];
            for (var c = 0; c < matrix[r].length; c++) {
                var col = editableCols[startCol + c];
                if (!col) break;
                var raw = matrix[r][c];
                if (col.type === 'money' || col.type === 'number') {
                    raw = String(raw).replace(/[$,]/g, '').trim();
                }
                row[col.key] = raw;
                if (this._typeahead && this._typeahead.key === col.key) {
                    this._resolveTypeahead(row, raw);
                }
            }
            row._dirty = true;
            if (typeof this.opts.afterChange === 'function') {
                this.opts.afterChange(row, null, null);
            }
        }
        this.dirty = true;
        this._render();
        if (this._onDirty) this._onDirty(true);
        if (this._onChange) this._onChange(this);
        this._ensureTrailingBlank();
    };

    InventorySheet.prototype.addRow = function (focus) {
        var row = this._pushRow({}, false);
        var index = this.rows.length - 1;
        this.tbody.appendChild(this._renderRow(row, index));
        if (focus) {
            var tr = this.tbody.querySelector('tr[data-uid="' + row._uid + '"]');
            var input = tr && tr.querySelector('.cell-input, .cell-select');
            if (input) input.focus();
        }
        return row;
    };

    InventorySheet.prototype.removeRow = function (uid) {
        if (this.rows.length <= 1) {
            var only = this.rows[0];
            this.columns.forEach(function (col) {
                only[col.key] = defaultValue(col);
            });
            only.inventory_item_id = null;
            only._meta = {};
            only._dirty = false;
            this._render();
            this.markClean();
            return;
        }
        this.rows = this.rows.filter(function (r) { return r._uid !== uid; });
        this.dirty = this.rows.some(function (r) { return r._dirty; });
        this._render();
        if (this._onDirty) this._onDirty(this.dirty);
        if (this._onChange) this._onChange(this);
    };

    InventorySheet.prototype.getFilledRows = function () {
        return this.rows.filter(function (r) { return !this._isBlank(r); }.bind(this));
    };

    InventorySheet.prototype.getDirtyRows = function () {
        return this.rows.filter(function (r) { return r._dirty && (r.id != null || !this._isBlank(r)); }.bind(this));
    };

    InventorySheet.prototype.getRows = function () {
        return this.rows.slice();
    };

    InventorySheet.prototype.markClean = function () {
        this.rows.forEach(function (r) { r._dirty = false; });
        this.dirty = false;
        this.tbody.querySelectorAll('tr.is-dirty').forEach(function (tr) {
            tr.classList.remove('is-dirty');
        });
        if (this._onDirty) this._onDirty(false);
    };

    InventorySheet.prototype.toFormFields = function (form, mapFn) {
        // Clear previous generated fields
        form.querySelectorAll('.inv-sheet-hidden').forEach(function (el) { el.remove(); });
        var filled = this.getFilledRows();
        filled.forEach(function (row, i) {
            var payload = typeof mapFn === 'function' ? mapFn(row, i) : row;
            Object.keys(payload).forEach(function (key) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.className = 'inv-sheet-hidden';
                input.name = key;
                input.value = payload[key] == null ? '' : String(payload[key]);
                form.appendChild(input);
            });
        });
        return filled.length;
    };

    InventorySheet.mount = function (root, options) {
        if (typeof root === 'string') root = document.querySelector(root);
        if (!root) throw new Error('InventorySheet root not found');
        return new InventorySheet(root, options);
    };

    global.InventorySheet = InventorySheet;
})(window);

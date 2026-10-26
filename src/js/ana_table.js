// Shared Tabulator helpers for class_metrics.php and method_metrics.php

// Header filter for numeric columns: "5", ">5", ">=5", "<5", "<=5", "=5", "!=5" or a range "2-8"
function anaNumericFilter(headerValue, rowValue) {
    const number = '-?\\d+(?:\\.\\d+)?';
    const text = String(headerValue).trim();
    const comparison = text.match(new RegExp(`^(>=|<=|!=|>|<|=)?\\s*(${number})$`));
    const range = text.match(new RegExp(`^(${number})\\s*-\\s*(${number})$`));

    // Half typed expression such as ">": keep every row until it is complete
    if (!comparison && !range) return true;

    const value = parseFloat(rowValue);
    if (isNaN(value)) return false;

    if (range) return value >= parseFloat(range[1]) && value <= parseFloat(range[2]);

    const operand = parseFloat(comparison[2]);
    switch (comparison[1]) {
        case '>': return value > operand;
        case '>=': return value >= operand;
        case '<': return value < operand;
        case '<=': return value <= operand;
        case '!=': return value !== operand;
        default: return value === operand;
    }
}

// Column definition factories: every column is sortable (Tabulator default) and has a header filter
const anaCol = {
    text: (title, field, tip, extra = {}) => ({
        title, field, headerTooltip: tip, tooltip: true,
        headerFilter: 'input',
        ...extra
    }),
    num: (title, field, tip, extra = {}) => ({
        title, field, headerTooltip: tip,
        sorter: 'number', hozAlign: 'right',
        headerFilter: 'input', headerFilterFunc: anaNumericFilter, headerFilterPlaceholder: 'e.g. >5',
        ...extra
    }),
    flag: (title, field, tip, extra = {}) => ({
        title, field, headerTooltip: tip,
        sorter: 'boolean', hozAlign: 'center', formatter: 'tickCross',
        headerFilter: 'tickCross', headerFilterParams: { tristate: true },
        headerFilterEmptyCheck: (value) => value === null,
        ...extra
    }),
    rowNumber: () => ({
        title: '#', formatter: 'rownum', hozAlign: 'right', headerSort: false, resizable: false, frozen: true, width: 55
    }),
    // Name column linking to detail.php. type: 'class' or 'method'; nameField: row field holding the full name
    detail: (title, field, tip, type, nameField, extra = {}) => anaCol.text(title, field, tip, {
        formatter: (cell) => {
            const row = cell.getRow().getData();
            if (row.kind === 'Native Function' || !cell.getValue()) return cell.getValue();
            const link = document.createElement('a');
            link.href = `detail.php?type=${type}&name=${encodeURIComponent(row[nameField])}`;
            link.textContent = cell.getValue();
            return link;
        },
        ...extra
    }),
    list: (title, field, tip, extra = {}) => ({
        title, field, headerTooltip: tip,
        headerFilter: 'list', headerFilterFunc: '=', headerFilterParams: { valuesLookup: true, clearable: true },
        ...extra
    })
};

// Wires the toolbar: row counter, clear filters, CSV export and the show/hide columns menu
function anaInitToolbar(table, csvName) {
    const status = document.getElementById('anaStatus');
    const columnsList = document.getElementById('anaColumnsList');

    const updateStatus = () => {
        status.textContent = `${table.getDataCount('active')} of ${table.getDataCount()} rows`;
    };

    table.on('renderComplete', updateStatus);
    table.on('dataLoadError', () => { status.textContent = 'Failed to load data'; });

    // The columns menu closes on Escape and on any click outside of it (headers, rows, toolbar)
    const columnsMenu = document.querySelector('.ana-columns');
    document.addEventListener('click', (event) => {
        if (!columnsMenu.contains(event.target)) columnsMenu.open = false;
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') columnsMenu.open = false;
    });

    // Keep the menu in step with visibility changes made elsewhere (layout dialog)
    table.on('columnVisibilityChanged', (column, visible) => {
        const checkbox = columnsList.querySelector(`[data-field="${CSS.escape(column.getField())}"]`);
        if (checkbox) checkbox.checked = visible;
    });

    table.on('tableBuilt', () => {
        table.getColumns().filter(column => column.getField()).forEach(column => {
            const label = document.createElement('label');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.dataset.field = column.getField();
            checkbox.checked = column.isVisible();
            checkbox.addEventListener('change', () => column.toggle());
            label.append(checkbox, ' ', column.getDefinition().title);
            columnsList.appendChild(label);
        });
    });

    document.getElementById('anaClear').addEventListener('click', () => {
        table.clearHeaderFilter();
        table.clearFilter(true);
    });
    document.getElementById('anaCsv').addEventListener('click', () => table.download('csv', csvName));
}

// Preset buttons: each one clears the filters, applies its row filter and sorts the table.
// preset = { label, tip, filter: (row) => bool (optional), sort: [{ column, dir }] }
function anaInitPresets(table, presets) {
    const bar = document.getElementById('anaPresets');
    const note = document.getElementById('anaPresetNote');

    const clearPreset = () => {
        table.clearHeaderFilter();
        table.clearFilter(true);
        buttons.forEach(button => button.classList.remove('active'));
        note.textContent = '';
    };

    const none = document.createElement('button');
    none.textContent = 'None';
    none.title = 'Deselect the preset and show every row again';
    none.className = 'ana-preset-none';
    none.addEventListener('click', clearPreset);
    bar.appendChild(none);

    const buttons = presets.map(preset => {
        const button = document.createElement('button');
        button.textContent = preset.label;
        button.title = preset.tip;
        button.addEventListener('click', () => {
            table.clearHeaderFilter();
            table.clearFilter(true);
            if (preset.filter) table.setFilter(preset.filter);
            table.setSort(preset.sort);
            buttons.forEach(other => other.classList.toggle('active', other === button));
            note.textContent = preset.tip;
        });
        bar.appendChild(button);
        return button;
    });

    document.getElementById('anaClear').addEventListener('click', () => {
        buttons.forEach(button => button.classList.remove('active'));
        note.textContent = '';
    });
}

// Column guide under the grid: what each column is and how to read it, from the column definitions
function anaInitGuide(table) {
    const guide = document.getElementById('anaGuide');
    const addEntries = (container, columns) => {
        columns.filter(column => column.field).forEach(column => {
            container.appendChild(Object.assign(document.createElement('dt'), { textContent: column.title }));
            container.appendChild(Object.assign(document.createElement('dd'), { textContent: column.headerTooltip || '' }));
        });
    };

    table.on('tableBuilt', () => {
        let loose = null;
        table.getColumnDefinitions().forEach(definition => {
            if (definition.columns) {
                guide.appendChild(Object.assign(document.createElement('h3'), { textContent: definition.title }));
                const list = document.createElement('dl');
                addEntries(list, definition.columns);
                guide.appendChild(list);
                return;
            }
            // Ungrouped columns (row number, name) are listed together before the first group
            if (loose === null) {
                loose = document.createElement('dl');
                guide.appendChild(loose);
            }
            addEntries(loose, [definition]);
        });
    });
}

// Toolbar search: highlights the matching column headers and scrolls to the first visible match
function anaInitFind(table) {
    const input = document.getElementById('anaFind');
    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        let first = null;
        table.getColumns().filter(column => column.getField()).forEach(column => {
            const matches = term !== '' && (column.getDefinition().title.toLowerCase().includes(term) ||
                column.getField().toLowerCase().includes(term));
            column.getElement().classList.toggle('ana-col-found', matches);
            if (matches && first === null && column.isVisible()) first = column.getField();
        });
        if (first !== null) table.scrollToColumn(first, 'middle', false);
    });
}

// Layout dialog: choose the visible columns and the row sort order, give the layout a name and save it.
// Layouts are saved in the database (api/layouts.php, table user_layouts), which is not part of a project's
// analysis, so they apply to every project. The layout marked as default is applied when the page opens.
// defaultSort: the table's initialSort, [{ column, dir }]
function anaInitLayout(table, storageKey, defaultSort) {
    const sortLevels = 3;
    let dialog = null;

    const leafColumns = () => table.getColumns().filter(column => column.getField());
    const columnLabel = (column) => {
        const group = column.getParentColumn();
        const title = column.getDefinition().title;
        return group ? `${group.getDefinition().title} › ${title}` : title;
    };

    // store = { default: name | null, layouts: { name: { hidden: [field], sort: [{ field, dir }] } } }
    let store = { default: null, layouts: {} };

    // Every request answers with the page's layouts
    const callLayouts = async (payload) => {
        const options = payload === undefined ? {} : {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ page: storageKey, ...payload })
        };
        const response = await fetch('api/layouts.php' + (payload === undefined ? '?page=' + encodeURIComponent(storageKey) : ''), options);
        const answer = await response.json();
        if (!response.ok) throw new Error(answer.error || 'Request failed');
        store = answer;
    };

    // Layouts an earlier version kept in the browser's local storage move to the database once
    const importLocalLayouts = async () => {
        try {
            const local = JSON.parse(localStorage.getItem('ana.layouts.' + storageKey));
            const legacy = JSON.parse(localStorage.getItem('ana.layout.' + storageKey));
            const layouts = local && local.layouts ? local.layouts : (legacy ? { 'Saved layout': legacy } : {});
            if (Object.keys(layouts).length === 0) return;
            await callLayouts({ action: 'import', layouts, default: local && local.layouts ? local.default : (legacy ? 'Saved layout' : null) });
            localStorage.removeItem('ana.layouts.' + storageKey);
            localStorage.removeItem('ana.layout.' + storageKey);
        } catch (e) { /* nothing to import, or storage blocked */ }
    };

    const showError = (error) => {
        dialog.querySelector('.ana-dialog-error').textContent = error ? error.message : '';
    };

    // Columns not listed as hidden are shown, so columns added later appear in layouts saved earlier
    const builtInLayout = { hidden: [], sort: defaultSort.map(sort => ({ field: sort.column, dir: sort.dir })) };
    const apply = (layout) => {
        leafColumns().forEach(column => {
            if (layout.hidden.includes(column.getField())) column.hide(); else column.show();
        });
        table.setSort(layout.sort.map(sort => ({ column: sort.field, dir: sort.dir })));
    };

    const currentLayout = () => ({
        hidden: leafColumns().filter(column => !column.isVisible()).map(column => column.getField()),
        sort: table.getSorters().map(sorter => ({ field: sorter.field, dir: sorter.dir }))
    });

    const build = () => {
        dialog = document.createElement('dialog');
        dialog.className = 'ana-dialog';
        dialog.innerHTML = `
            <h2>Layout</h2>
            <div class="ana-dialog-row">
                <label>Saved layouts <select class="ana-saved"></select></label>
                <button type="button" data-action="delete">Delete</button>
                <span class="ana-spacer"></span>
                <label>Name <input type="text" class="ana-name" placeholder="Layout name"></label>
            </div>
            <fieldset>
                <legend>Visible columns</legend>
                <div class="ana-dialog-row">
                    <input type="search" class="ana-dialog-find" placeholder="Find a column...">
                    <button type="button" data-action="all">Select all</button>
                    <button type="button" data-action="none">Select none</button>
                </div>
                <div class="ana-dialog-columns"></div>
            </fieldset>
            <fieldset>
                <legend>Sort order (first one wins)</legend>
                <div class="ana-dialog-sorts"></div>
            </fieldset>
            <label class="ana-dialog-default"><input type="checkbox" class="ana-default"> Open this page with this layout</label>
            <p class="ana-dialog-note">Layouts are saved in the database and apply to every project.</p>
            <p class="ana-dialog-error"></p>
            <div class="ana-dialog-row ana-dialog-buttons">
                <button type="button" data-action="reset">Reset to built-in</button>
                <span class="ana-spacer"></span>
                <button type="button" data-action="cancel">Cancel</button>
                <button type="button" data-action="apply">Apply</button>
                <button type="button" data-action="save" class="primary">Save</button>
            </div>`;
        document.body.appendChild(dialog);

        const columnsBox = dialog.querySelector('.ana-dialog-columns');
        leafColumns().forEach(column => {
            const label = document.createElement('label');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.dataset.field = column.getField();
            label.append(checkbox, ' ', columnLabel(column));
            columnsBox.appendChild(label);
        });

        const sortsBox = dialog.querySelector('.ana-dialog-sorts');
        for (let level = 0; level < sortLevels; level++) {
            const row = document.createElement('div');
            const field = document.createElement('select');
            field.add(new Option('(none)', ''));
            leafColumns().forEach(column => field.add(new Option(columnLabel(column), column.getField())));
            const dir = document.createElement('select');
            dir.add(new Option('Ascending', 'asc'));
            dir.add(new Option('Descending', 'desc'));
            row.append(field, dir);
            sortsBox.appendChild(row);
        }

        // Typing in the find box shows only the matching columns, with the match marked
        dialog.querySelector('.ana-dialog-find').addEventListener('input', (event) => {
            const term = event.target.value.trim().toLowerCase();
            columnsBox.querySelectorAll('label').forEach(label => {
                const matches = term === '' || label.textContent.toLowerCase().includes(term);
                label.hidden = !matches;
                label.classList.toggle('ana-col-found', term !== '' && matches);
            });
        });

        dialog.querySelector('.ana-saved').addEventListener('change', (event) => {
            const name = event.target.value;
            if (name === '' || !store.layouts[name]) return;
            fillForm(store.layouts[name]);
            dialog.querySelector('.ana-name').value = name;
            dialog.querySelector('.ana-default').checked = store.default === name;
        });

        // Clicking the backdrop closes the dialog; Escape is handled by <dialog> itself
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
                return;
            }
            const action = event.target.dataset ? event.target.dataset.action : undefined;
            if (action === 'all' || action === 'none') {
                columnsBox.querySelectorAll('label:not([hidden]) input').forEach(checkbox => { checkbox.checked = action === 'all'; });
            } else if (action === 'delete') {
                deleteSelected();
            } else if (action === 'reset') {
                run(async () => {
                    await callLayouts({ action: 'clear_default' });
                    apply(builtInLayout);
                    dialog.close();
                });
            } else if (action === 'cancel') {
                dialog.close();
            } else if (action === 'apply') {
                apply(readForm());
                dialog.close();
            } else if (action === 'save') {
                saveForm();
            }
        });
    };

    const readForm = () => {
        const hidden = [...dialog.querySelectorAll('.ana-dialog-columns input')]
            .filter(checkbox => !checkbox.checked).map(checkbox => checkbox.dataset.field);
        const sort = [...dialog.querySelectorAll('.ana-dialog-sorts div')]
            .map(row => ({ field: row.children[0].value, dir: row.children[1].value }))
            .filter(rule => rule.field !== '');
        return { hidden, sort };
    };

    const fillForm = (layout) => {
        dialog.querySelectorAll('.ana-dialog-columns input').forEach(checkbox => {
            checkbox.checked = !layout.hidden.includes(checkbox.dataset.field);
        });
        dialog.querySelectorAll('.ana-dialog-sorts div').forEach((row, level) => {
            row.children[0].value = layout.sort[level] ? layout.sort[level].field : '';
            row.children[1].value = layout.sort[level] ? layout.sort[level].dir : 'asc';
        });
    };

    // Runs a save, delete or reset, showing the error in the dialog when the database call fails
    const run = async (operation) => {
        showError(null);
        try { await operation(); } catch (error) { showError(error); }
    };

    const refreshSaved = (selectedName) => {
        const select = dialog.querySelector('.ana-saved');
        select.innerHTML = '';
        select.add(new Option('(none)', ''));
        Object.keys(store.layouts).sort().forEach(name => {
            select.add(new Option(name === store.default ? name + ' (default)' : name, name));
        });
        select.value = selectedName || '';
    };

    const saveForm = () => run(async () => {
        const name = dialog.querySelector('.ana-name').value.trim() || 'My layout';
        const layout = readForm();
        await callLayouts({ action: 'save', name, layout, is_default: dialog.querySelector('.ana-default').checked });
        apply(layout);
        dialog.close();
    });

    const deleteSelected = () => run(async () => {
        const name = dialog.querySelector('.ana-saved').value;
        if (name === '') return;
        await callLayouts({ action: 'delete', name });
        dialog.querySelector('.ana-name').value = '';
        dialog.querySelector('.ana-default').checked = false;
        refreshSaved('');
    });

    document.getElementById('anaLayout').addEventListener('click', async () => {
        if (dialog === null) build();
        showError(null);
        try { await callLayouts(); } catch (error) { showError(error); }
        fillForm(currentLayout());
        dialog.querySelector('.ana-name').value = '';
        dialog.querySelector('.ana-default').checked = false;
        dialog.querySelector('.ana-dialog-find').value = '';
        dialog.querySelector('.ana-dialog-find').dispatchEvent(new Event('input'));
        refreshSaved('');
        dialog.showModal();
    });

    table.on('tableBuilt', async () => {
        try {
            await callLayouts();
            if (Object.keys(store.layouts).length === 0) {
                await importLocalLayouts();
            }
        } catch (e) { return; /* database unavailable: the built-in layout stays */ }
        if (store.default && store.layouts[store.default]) apply(store.layouts[store.default]);
    });
}

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
            if (row.kind === 'Native Function') return cell.getValue();
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

// Layout dialog: choose the visible columns and the row sort order, optionally saved as the default.
// Saved in localStorage, which belongs to the site and not to a project, so it applies to every project.
// defaultSort: the table's initialSort, [{ column, dir }]
function anaInitLayout(table, storageKey, defaultSort) {
    const key = 'ana.layout.' + storageKey;
    const sortLevels = 3;
    let dialog = null;

    const leafColumns = () => table.getColumns().filter(column => column.getField());
    const columnLabel = (column) => {
        const group = column.getParentColumn();
        const title = column.getDefinition().title;
        return group ? `${group.getDefinition().title} \u203A ${title}` : title;
    };
    const readSaved = () => {
        try { return JSON.parse(localStorage.getItem(key)); } catch (e) { return null; }
    };
    const writeSaved = (layout) => {
        try {
            if (layout === null) localStorage.removeItem(key); else localStorage.setItem(key, JSON.stringify(layout));
        } catch (e) { /* storage blocked: the layout still applies to this visit */ }
    };

    // layout = { hidden: [field], sort: [{ field, dir }] }. Columns not listed as hidden are shown,
    // so columns added later appear for users who saved a layout earlier
    const defaultLayout = { hidden: [], sort: defaultSort.map(sort => ({ field: sort.column, dir: sort.dir })) };
    const apply = (layout) => {
        leafColumns().forEach(column => {
            if (layout.hidden.includes(column.getField())) column.hide(); else column.show();
        });
        table.setSort(layout.sort.map(sort => ({ column: sort.field, dir: sort.dir })));
    };

    const build = () => {
        dialog = document.createElement('dialog');
        dialog.className = 'ana-dialog';
        dialog.innerHTML = `
            <h2>Layout</h2>
            <fieldset>
                <legend>Visible columns</legend>
                <div class="ana-dialog-row">
                    <button type="button" data-action="all">Select all</button>
                    <button type="button" data-action="none">Select none</button>
                </div>
                <div class="ana-dialog-columns"></div>
            </fieldset>
            <fieldset>
                <legend>Sort order (first one wins)</legend>
                <div class="ana-dialog-sorts"></div>
            </fieldset>
            <p class="ana-dialog-note">Saved layouts are kept in this browser and apply to every project.</p>
            <div class="ana-dialog-row ana-dialog-buttons">
                <button type="button" data-action="reset">Reset to default</button>
                <span class="ana-spacer"></span>
                <button type="button" data-action="cancel">Cancel</button>
                <button type="button" data-action="apply">Apply</button>
                <button type="button" data-action="save" class="primary">Save as default</button>
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

        // Clicking the backdrop closes the dialog; Escape is handled by <dialog> itself
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('click', (event) => {
            const action = event.target.dataset ? event.target.dataset.action : undefined;
            if (action === 'all' || action === 'none') {
                columnsBox.querySelectorAll('input').forEach(checkbox => { checkbox.checked = action === 'all'; });
            } else if (action === 'reset') {
                writeSaved(null);
                apply(defaultLayout);
                dialog.close();
            } else if (action === 'cancel') {
                dialog.close();
            } else if (action === 'apply' || action === 'save') {
                const layout = readForm();
                apply(layout);
                if (action === 'save') writeSaved(layout);
                dialog.close();
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

    const fillForm = () => {
        dialog.querySelectorAll('.ana-dialog-columns input').forEach(checkbox => {
            checkbox.checked = table.getColumn(checkbox.dataset.field).isVisible();
        });
        const sorters = table.getSorters();
        dialog.querySelectorAll('.ana-dialog-sorts div').forEach((row, level) => {
            row.children[0].value = sorters[level] ? sorters[level].field : '';
            row.children[1].value = sorters[level] ? sorters[level].dir : 'asc';
        });
    };

    document.getElementById('anaLayout').addEventListener('click', () => {
        if (dialog === null) build();
        fillForm();
        dialog.showModal();
    });

    table.on('tableBuilt', () => {
        const saved = readSaved();
        if (saved) apply(saved);
    });
}

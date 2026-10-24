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

    table.on('tableBuilt', () => {
        table.getColumns().filter(column => column.getField()).forEach(column => {
            const label = document.createElement('label');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
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

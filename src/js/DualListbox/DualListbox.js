class DualListbox {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.availableData = options.available || [];
        this.selectedData = options.selected || [];
        this.titleAvailable = options.titleAvailable || 'Available';
        this.titleSelected = options.titleSelected || 'Selected';

        this.init();
    }

    init() {
        // Build the HTML structure
        this.container.classList.add('dual-listbox');
        this.container.innerHTML = `
            <div class="dl-filter-container">
                <input type="text" class="dl-filter" placeholder="Filter items...">
            </div>
            <div class="dl-columns">
                <div class="dl-column">
                    <div class="dl-header">${this.titleAvailable}</div>
                    <ul class="dl-list dl-available"></ul>
                </div>
                <div class="dl-column">
                    <div class="dl-header">${this.titleSelected}</div>
                    <ul class="dl-list dl-selected"></ul>
                </div>
            </div>
        `;

        this.listAvailable = this.container.querySelector('.dl-available');
        this.listSelected = this.container.querySelector('.dl-selected');
        this.filterInput = this.container.querySelector('.dl-filter');

        // Populate lists
        this.populateList(this.listAvailable, this.availableData);
        this.populateList(this.listSelected, this.selectedData);

        // Bind events
        this.bindEvents();
        this.initSortable();
    }

    populateList(listElement, dataArray) {
        listElement.innerHTML = '';
        dataArray.forEach(item => {
            const li = document.createElement('li');
            li.className = 'dl-item';
            li.textContent = item;
            li.dataset.value = item;
            listElement.appendChild(li);
        });
    }

    bindEvents() {
        // Real-time filtering across both lists
        this.filterInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const allItems = this.container.querySelectorAll('.dl-item');

            allItems.forEach(item => {
                if (item.textContent.toLowerCase().includes(term)) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        });

        // Double-click to instantly transfer item
        this.container.addEventListener('dblclick', (e) => {
            if (e.target.classList.contains('dl-item')) {
                const parentList = e.target.parentElement;
                if (parentList.classList.contains('dl-available')) {
                    this.listSelected.appendChild(e.target);
                } else {
                    this.listAvailable.appendChild(e.target);
                }
            }
        });
    }

    initSortable() {
        // Define a unique group name for this instance
        const groupName = 'shared_' + Math.random().toString(36).substr(2, 9);

        Sortable.create(this.listAvailable, {
            group: groupName,
            animation: 150,
            ghostClass: 'dl-ghost'
        });

        Sortable.create(this.listSelected, {
            group: groupName,
            animation: 150,
            ghostClass: 'dl-ghost'
        });
    }

    // Public method to retrieve current selections
    getSelected() {
        const items = Array.from(this.listSelected.querySelectorAll('.dl-item'));
        return items.map(item => item.dataset.value);
    }
}

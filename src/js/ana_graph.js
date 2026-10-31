// Shared graph helpers for call_graph.php, namespace_metrics.php, class_graph.php, method_graph.php and review.php

// Namespace of a class or function name; '[Global]' when it has none
function anaNamespaceOf(name) {
    const lastSlash = name.lastIndexOf('\\');
    return lastSlash > 0 ? name.substring(0, lastSlash) : '[Global]';
}

// Tarjan's strongly connected components. Returns Map node -> component id for the nodes that are
// part of a cycle (components with more than one node). pairs: [[from, to], ...]
function anaCycleNodes(pairs) {
    const adjacent = new Map();
    pairs.forEach(([from, to]) => {
        if (!adjacent.has(from)) adjacent.set(from, []);
        if (!adjacent.has(to)) adjacent.set(to, []);
        if (from !== to) adjacent.get(from).push(to);
    });

    const order = new Map();
    const low = new Map();
    const onStack = new Set();
    const stack = [];
    const inCycle = new Map();
    let counter = 0;
    let componentId = 0;

    const visit = (node) => {
        order.set(node, counter);
        low.set(node, counter);
        counter++;
        stack.push(node);
        onStack.add(node);
    };

    for (const root of adjacent.keys()) {
        if (order.has(root)) continue;
        visit(root);
        const work = [[root, 0]];

        while (work.length > 0) {
            const frame = work[work.length - 1];
            const node = frame[0];
            const neighbors = adjacent.get(node);

            if (frame[1] < neighbors.length) {
                const next = neighbors[frame[1]++];
                if (!order.has(next)) {
                    visit(next);
                    work.push([next, 0]);
                } else if (onStack.has(next)) {
                    low.set(node, Math.min(low.get(node), order.get(next)));
                }
                continue;
            }

            work.pop();
            if (work.length > 0) {
                const parent = work[work.length - 1][0];
                low.set(parent, Math.min(low.get(parent), low.get(node)));
            }
            if (low.get(node) !== order.get(node)) continue;

            const component = [];
            let member;
            do {
                member = stack.pop();
                onStack.delete(member);
                component.push(member);
            } while (member !== node);
            if (component.length > 1) {
                component.forEach(name => inCycle.set(name, componentId));
                componentId++;
            }
        }
    }
    return inCycle;
}

// Maximize button: the box fills the window (class ana-graph-maximized); Escape or the button goes back
function anaInitMaximize(box, button, chart) {
    const setMaximized = (isMaximized) => {
        box.classList.toggle('ana-graph-maximized', isMaximized);
        button.textContent = isMaximized ? 'Restore' : 'Maximize';
        chart.resize();
    };
    button.addEventListener('click', () => setMaximized(!box.classList.contains('ana-graph-maximized')));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && box.classList.contains('ana-graph-maximized')) setMaximized(false);
    });
}

// Spread, default spacing and Hold dropped nodes for a force layout graph.
// ids = { spread, tighten, hold } element ids. Use tools.force(base) for the series' force option and
// tools.setNodes(nodes) with the series data every time the graph is rendered.
// ECharts frees a node when it is dropped: with Hold ticked the drop pins every node where it is.
function anaForceGraphTools(chart, ids, step = 1.5) {
    let scale = 1;
    let base = { repulsion: 100, edgeLength: [50, 100] };
    let nodes = [];
    const holdBox = document.getElementById(ids.hold);

    const force = (newBase) => {
        if (newBase) base = newBase;
        return { ...base, repulsion: base.repulsion * scale, edgeLength: base.edgeLength.map(length => length * scale) };
    };

    // Every node free to move again, with the current spacing
    const release = () => {
        holdBox.checked = false;
        if (nodes.length === 0) return;
        chart.setOption({ series: [{ force: force(), data: nodes.map(node => ({ ...node, fixed: false })) }] });
    };

    const hold = () => {
        if (nodes.length === 0) return;
        const layout = chart.getModel().getSeriesByIndex(0).getData();
        chart.setOption({ series: [{ data: nodes.map((node, index) => {
            const [x, y] = layout.getItemLayout(index);
            return { ...node, x, y, fixed: true };
        }) }] });
    };

    document.getElementById(ids.spread).addEventListener('click', () => { scale *= step; release(); });
    document.getElementById(ids.tighten).addEventListener('click', () => { scale = 1; release(); });
    holdBox.addEventListener('change', () => { if (!holdBox.checked) release(); });
    chart.getZr().on('mouseup', () => { if (holdBox.checked) setTimeout(hold, 0); });

    return { force, setNodes: (newNodes) => { nodes = newNodes; } };
}

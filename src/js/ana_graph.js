// Shared graph helpers for call_graph.php and namespace_metrics.php

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

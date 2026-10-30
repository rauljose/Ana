/* Pure report calculations. No database writes or changes to the existing collectors. */
const AnaReportData = (() => {
    const id = (cls, method) => cls ? `${cls}::${method}` : method;
    const namespace = name => {
        const owner = name.includes('::') ? name.split('::')[0] : name;
        const slash = owner.lastIndexOf('\\');
        return slash < 0 ? '[Global]' : owner.slice(0, slash);
    };
    const owner = name => name.includes('::') ? name.split('::')[0] : `[function] ${name}`;
    const edges = rows => rows.map(r => ({
        from: id(r.from_class, r.from_method), to: id(r.to_class, r.to_method),
        fromClass: r.from_class, toClass: r.to_class,
        weight: Number(r.weight), native: Number(r.is_builtin) === 1,
    }));
    function impact(calls, target, maxDepth = Infinity) {
        const incoming = new Map();
        calls.forEach(e => {
            if (!incoming.has(e.to)) incoming.set(e.to, []);
            incoming.get(e.to).push(e);
        });
        const paths = new Map([[target, [target]]]);
        const queue = [target];
        const rows = [];
        for (let i = 0; i < queue.length; i++) {
            const current = queue[i], tail = paths.get(current);
            if (tail.length > maxDepth) continue;
            for (const edge of incoming.get(current) || []) {
                if (paths.has(edge.from)) continue;
                const path = [edge.from, ...tail];
                paths.set(edge.from, path);
                queue.push(edge.from);
                rows.push({ method: edge.from, namespace: namespace(edge.from), distance: path.length - 1,
                    relationship: path.length === 2 ? 'Direct caller' : 'Indirect caller', path: path.join(' → '), pathNodes: path });
            }
        }
        return rows;
    }
    function consumers(methods, calls) {
        const incoming = new Map();
        calls.forEach(e => {
            if (!incoming.has(e.to)) incoming.set(e.to, []);
            incoming.get(e.to).push(e);
        });
        return methods.filter(m => m.kind === 'Method' && m.visibility === 'public').map(m => {
            const name = m.fqn, all = incoming.get(name) || [];
            const external = all.filter(e => e.fromClass !== m.class_name);
            const externalNamespaces = new Set(external.map(e => namespace(e.from)).filter(ns => ns !== m.namespace));
            return { method: name, namespace: m.namespace,
                sites: all.reduce((sum, e) => sum + e.weight, 0),
                callers: new Set(all.map(e => e.from)).size,
                externalCallers: new Set(external.map(e => e.from)).size,
                externalOwners: new Set(external.map(e => owner(e.from))).size,
                externalNamespaces: externalNamespaces.size,
                usage: external.length ? 'Used outside class' : all.length ? 'Calls only within class' : 'No recorded calls',
                evidence: all, signature: m.signature || '',
            };
        });
    }
    function sharedState(accesses) {
        const groups = new Map();
        for (const access of accesses) {
            const isGlobal = ['Global variable', 'Superglobal variable'].includes(access.symbol_type);
            const isStatic = access.symbol_type === 'Static property' ||
                (access.symbol_type === 'Inherited member' && access.is_declared_static);
            if (!isGlobal && !isStatic) continue;
            // Normalize inherited static access to the known declaration; instance properties are excluded.
            const symbol = isStatic && access.declared_in
                ? `${access.declared_in}::${access.symbol.replace(/^\$/, '')}` : access.symbol_fqn;
            const key = `${isStatic ? 'static' : access.symbol_type}|${symbol}`;
            if (!groups.has(key)) groups.set(key, { symbol, kind: isStatic ? 'Static member' : access.symbol_type,
                readers: new Set(), writers: new Set(), namespaces: new Set(), classes: new Set(), functions: new Set(), evidence: [] });
            const group = groups.get(key);
            if (access.method_fqn.includes('::')) group.classes.add(access.method_fqn.split('::')[0]);
            else group.functions.add(access.method_fqn);
            if (access.access !== 'Write') group.readers.add(access.method_fqn);
            if (access.access !== 'Read') group.writers.add(access.method_fqn);
            group.namespaces.add(access.namespace);
            group.evidence.push(access);
        }
        return [...groups.values()].map(group => ({ symbol: group.symbol, kind: group.kind,
            readers: group.readers.size, writers: group.writers.size, namespaces: group.namespaces.size,
            classes: group.classes.size, functions: group.functions.size,
            sharing: group.classes.size > 1 ? 'Across multiple classes' : group.classes.size === 1
                ? (group.functions.size ? 'One class and functions' : 'Within one class') : 'Functions only',
            crossNamespace: [...group.writers].some(w => [...group.readers].some(r => namespace(w) !== namespace(r))),
            readerList: [...group.readers].sort().join(', '), writerList: [...group.writers].sort().join(', '),
            evidence: group.evidence,
        }));
    }
    function cycles(calls, level, scc) {
        const key = (e, side) => level === 'method' ? e[side] : level === 'namespace' ? namespace(e[side]) :
            e[`${side}Class`] || `[function] ${e[side]}`;
        const pairs = new Map();
        calls.filter(e => !e.native).forEach(e => {
            const from = key(e, 'from'), to = key(e, 'to');
            // Internal class/namespace calls are not architectural cycles. Method self-calls are recursion.
            if (from === to && level !== 'method') return;
            const k = JSON.stringify([from, to]);
            if (!pairs.has(k)) pairs.set(k, { from, to, weight: 0, evidence: [] });
            const pair = pairs.get(k);
            pair.weight += e.weight;
            pair.evidence.push(e);
        });
        const links = [...pairs.values()];
        const memberships = scc(links.map(e => [e.from, e.to]));
        const components = new Map();
        memberships.forEach((component, member) => {
            if (!components.has(component)) components.set(component, []);
            components.get(component).push(member);
        });
        links.filter(e => e.from === e.to && !memberships.has(e.from)).forEach(e => components.set(`self:${e.from}`, [e.from]));
        return [...components.values()].map(members => {
            members.sort();
            const set = new Set(members);
            const internal = links.filter(e => set.has(e.from) && set.has(e.to));
            const first = internal.find(e => e.from !== e.to) || internal[0];
            const back = first.from === first.to ? [first.to] :
                impact(internal, first.from).find(r => r.method === first.to).pathNodes;
            return { group: members[0], members: members.length, edges: internal.length,
                sites: internal.reduce((sum, e) => sum + e.weight, 0),
                memberList: members.join(', '), example: [first.from, ...back].join(' → '),
                memberNodes: members, evidence: internal,
            };
        }).sort((a, b) => b.members - a.members || a.group.localeCompare(b.group));
    }
    return { id, namespace, edges, impact, consumers, sharedState, cycles };
})();
if (typeof module !== 'undefined') module.exports = AnaReportData;

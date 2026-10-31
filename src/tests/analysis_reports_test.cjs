// Run: node src/tests/analysis_reports_test.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const D = require('../js/analysis_report_data.js');
const context = vm.createContext({});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../js/ana_graph.js'), 'utf8'), context);
const edge = (from, to, weight = 1) => ({ from, to, weight, native: false,
    fromClass: from.includes('::') ? from.split('::')[0] : '',
    toClass: to.includes('::') ? to.split('::')[0] : '' });

const calls = [edge('App\\A::run', 'App\\B::run', 3), edge('App\\B::run', 'App\\C::run'),
    edge('App\\C::run', 'App\\A::run'), edge('App\\D::run', 'App\\B::run'), edge('App\\B::run', 'App\\B::run')];
const impact = D.impact(calls, 'App\\C::run');
assert.equal(impact.length, 3, 'Each caller appears once and the target is excluded');
assert.deepEqual(impact.find(r => r.method === 'App\\D::run').pathNodes, ['App\\D::run', 'App\\B::run', 'App\\C::run']);
assert.equal(D.impact(calls, 'App\\C::run', 1).length, 1);
assert.deepEqual(D.impact(calls, 'Missing::method'), []);

const groups = D.cycles(calls, 'method', context.anaCycleNodes);
assert.equal(groups.length, 1);
assert.equal(groups[0].members, 3);
assert.equal(groups[0].sites, 6);
assert.ok(groups[0].example.startsWith('App\\A::run → App\\B::run'));
assert.equal(D.cycles(calls, 'namespace', context.anaCycleNodes).length, 0, 'Internal namespace calls are not architectural cycles');
assert.equal(D.cycles([edge('f', 'f')], 'method', context.anaCycleNodes)[0].example, 'f → f');
assert.equal(D.cycles([edge('f', 'f')], 'class', context.anaCycleNodes).length, 0);
assert.equal(D.cycles([edge('a', 'b')], 'method', context.anaCycleNodes).length, 0);

const publicMethods = [{ kind: 'Method', visibility: 'public', fqn: 'App\\B::run', class_name: 'App\\B', namespace: 'App' },
    { kind: 'Method', visibility: 'private', fqn: 'App\\A::run' },
    { kind: 'Method', visibility: 'public', fqn: 'App\\Unused::run', class_name: 'App\\Unused', namespace: 'App' }];
const consumers = D.consumers(publicMethods, calls);
assert.equal(consumers.length, 2);
assert.equal(consumers[0].sites, 5);
assert.equal(consumers[0].callers, 3);
assert.equal(consumers[0].externalCallers, 2);
assert.equal(consumers[0].externalNamespaces, 0);
assert.equal(consumers[1].usage, 'No recorded calls');

const shared = D.sharedState([
    { symbol_type: 'Static property', symbol: '$cache', symbol_fqn: 'Base::$cache', declared_in: 'Base', method_fqn: 'A\\Base::write', namespace: 'A', access: 'Write' },
    { symbol_type: 'Inherited member', symbol: 'cache', symbol_fqn: 'Child::cache', declared_in: 'Base', is_declared_static: true, method_fqn: 'B\\Child::read', namespace: 'B', access: 'Read/Write' },
    { symbol_type: 'Property', symbol: '$cache', symbol_fqn: 'Instance::$cache', method_fqn: 'Instance::write', namespace: '[Global]', access: 'Write' },
    { symbol_type: 'Inherited member', symbol: 'LIMIT', symbol_fqn: 'Child::LIMIT', declared_in: 'Base', is_declared_static: true, is_declared_constant: true, method_fqn: 'B\\Child::read', namespace: 'B', access: 'Read' },
    { symbol_type: 'Constant', symbol: 'DEBUG', symbol_fqn: 'DEBUG', method_fqn: 'f', namespace: '[Global]', access: 'Read' },
]);
assert.equal(shared.length, 1, 'Instance state, constants and inherited class constants excluded; inherited static state merged');
assert.equal(shared[0].symbol, 'Base::$cache');
assert.equal(shared[0].kind, 'Static property');
assert.equal(shared[0].writers, 2);
assert.equal(shared[0].readers, 1);
assert.equal(shared[0].crossNamespace, true);
assert.equal(shared[0].namespaces, 2);
assert.equal(shared[0].classes, 2);
assert.equal(shared[0].sharing, 'Across multiple classes');
const stateAccess = (method_fqn) => ({ symbol_type: 'Global variable', symbol_fqn: '$shared',
    method_fqn, namespace: 'App', access: 'Read' });
const oneClass = D.sharedState([stateAccess('App\\One::a'), stateAccess('App\\One::b')])[0];
assert.equal(oneClass.classes, 1, 'Multiple methods of one class are not multiple classes');
assert.equal(oneClass.sharing, 'Within one class');
assert.equal(D.sharedState([stateAccess('App\\One::a'), stateAccess('App\\helper')])[0].sharing, 'One class and functions');
assert.equal(D.sharedState([stateAccess('App\\helper')])[0].sharing, 'Functions only');
console.log('Analysis report tests passed: traversal, depth, cycles, recursion, consumers and shared state.');

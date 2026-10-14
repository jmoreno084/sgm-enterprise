/* Development-only deterministic precompilation of upstream dynamic functions. */
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const vm = require('vm');
const crypto = require('crypto');
const acorn = require('./tools/acorn/dist/acorn.js');
const root = path.resolve(__dirname, '..');
function archiveFile(archive, wanted) {
    const tar = zlib.gunzipSync(fs.readFileSync(path.join(root, archive)));
    for (let offset = 0; offset + 512 <= tar.length;) {
        const name = tar.subarray(offset, offset + 100).toString().replace(/\0.*$/s, '');
        const size = parseInt(tar.subarray(offset + 124, offset + 136).toString().replace(/\0.*$/s, '').trim(), 8) || 0;
        if (name === wanted) { return tar.subarray(offset + 512, offset + 512 + size).toString('utf8'); }
        offset += 512 + Math.ceil(size / 512) * 512;
    }
    throw new Error('Upstream archive entry missing: ' + wanted);
}
function walk(node, callback) {
    if (!node || typeof node !== 'object') { return; }
    if (node.type) { callback(node); }
    Object.keys(node).forEach(function (key) {
        if (Array.isArray(node[key])) { node[key].forEach(function (item) { walk(item, callback); }); }
        else if (node[key] && typeof node[key] === 'object') { walk(node[key], callback); }
    });
}
function precompile(kind, original) {
    const records = new Map();
    function Factory() {
        const args = Array.prototype.map.call(arguments, String);
        const fn = Function.apply(null, args); // Development only; never shipped to the browser.
        records.set(JSON.stringify(args), fn.toString());
        return fn;
    }
    Factory.prototype = Function.prototype;
    Object.setPrototypeOf(Factory, Function);
    const sandbox = {Function: Factory, console: console};
    sandbox.self = sandbox;
    const context = vm.createContext(sandbox);
    const tree = acorn.parse(original, {ecmaVersion: 6});
    if (kind === 'Turf') {
        sandbox.module = {exports: {}}; sandbox.exports = sandbox.module.exports;
        vm.runInContext(original, context);
        const turf = sandbox.module.exports;
        const polygon = {type: 'Feature', properties: {}, geometry: {type: 'Polygon', coordinates: [[[0,0],[1,0],[1,1],[0,0]]]}};
        turf.area(polygon); turf.centroid(polygon);
        turf.length({type: 'Feature', properties: {}, geometry: {type: 'LineString', coordinates: [[0,0],[1,1]]}});
    } else {
        let modules = null;
        walk(tree, function (node) {
            if (node.type === 'ArrayExpression' && node.elements.length > 20 && node.elements.every(function (item) { return item && item.type === 'FunctionExpression'; })) {
                if (!modules || node.elements.length > modules.length) { modules = node.elements; }
            }
        });
        if (!modules) { throw new Error('Expected pinned webpack module structure.'); }
        const cache = {};
        function load(index) {
            if (cache[index]) { return cache[index].exports; }
            const module = cache[index] = {exports: {}};
            vm.runInContext('(' + original.slice(modules[index].start, modules[index].end) + ')', context)(module, module.exports, load);
            return module.exports;
        }
        load.d = function (object, key, get) { Object.defineProperty(object, key, {enumerable: true, get: get}); };
        load.n = function (module) { const get = module && module.__esModule ? function () { return module.default; } : function () { return module; }; load.d(get, 'a', get); return get; };
        load.i = function (value) { return value; };
        load.o = function (object, key) { return Object.prototype.hasOwnProperty.call(object, key); };
        const templateIndex = modules.findIndex(function (node) { return /return Function\(/.test(original.slice(node.start, node.end)); });
        const template = load(templateIndex);
        const strings = [];
        modules.forEach(function (node) {
            walk(node.body, function (value) { if (value.type === 'Literal' && typeof value.value === 'string' && value.value.indexOf('<') === 0 && value.value.indexOf('{{') !== -1) { strings.push(value.value); } });
        });
        const order = [s => s.includes('js-toggle'), s => s.includes('lastpoint heading'), s => s.includes('model.lastCoord.dd') && !s.includes('lastpoint heading'), s => s.includes('model.lengthDisplay') && !s.includes('model.areaDisplay'), s => s.includes('model.areaDisplay') && !s.includes('model.pointCount')];
        order.forEach(function (match) {
            const text = strings.find(match); if (!text) { throw new Error('Pinned measurement template missing.'); }
            template(text, {imports: {numberFormat: function () {}}, interpolate: /{{([\s\S]+?)}}/g});
        });
    }
    records.set(JSON.stringify(['return this']), 'function () { return window; }');
    const name = 'sgpmStaticFunction' + kind;
    const replacements = [];
    walk(tree, function (node) {
        if ((node.type === 'CallExpression' || node.type === 'NewExpression') && node.callee.type === 'Identifier' && node.callee.name === 'Function') { replacements.push(node.callee); }
        else if (node.type === 'CallExpression' && node.callee.type === 'MemberExpression' && node.callee.object.type === 'Identifier' && node.callee.object.name === 'Function' && ['apply','call'].includes(node.callee.property.name)) { replacements.push(node.callee.object); }
    });
    replacements.sort(function (a,b) { return b.start-a.start; });
    let body = original;
    replacements.forEach(function (node) { body = body.slice(0,node.start) + name + body.slice(node.end); });
    let prefix = '/* SGPM CSP adaptation: precompiled pinned upstream factories; no runtime eval. */\nfunction ' + name + '() {\nvar key=JSON.stringify(Array.prototype.map.call(arguments,String));\nswitch(key) {\n';
    records.forEach(function (fn,key) { prefix += 'case ' + JSON.stringify(key) + ': return (' + fn + ');\n'; });
    prefix += "default: throw new Error('Dynamic vendor factory outside the audited SGPM profile.');\n}\n}\n";
    const result = prefix + body;
    acorn.parse(result, {ecmaVersion: 6});
    return {result: result, factories: records.size};
}
const manifestPath = path.join(root, 'public/assets/vendor/manifest.json');
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
[
    ['Measure','ops/dependency-sources/leaflet-measure-3.1.0.tgz','package/dist/leaflet-measure.es.js','public/assets/vendor/leaflet-measure/dist/leaflet-measure.es.js'],
    ['Turf','ops/dependency-sources/turf-turf-6.5.0.tgz','package/turf.min.js','public/assets/vendor/turf-turf/turf.min.js']
].forEach(function (item) {
    const original = archiveFile(item[1],item[2]); const adapted = precompile(item[0],original);
    fs.writeFileSync(path.join(root,item[3]), adapted.result);
    const hash = value => crypto.createHash('sha256').update(value).digest('hex');
    const oldLines = original.trimEnd().split('\n'); const newLines = adapted.result.trimEnd().split('\n');
    const patchPath = 'ops/patches/' + item[0].toLowerCase() + '-csp.patch';
    const patch = '--- a/' + item[3] + '\n+++ b/' + item[3] + '\n@@ -1,'+oldLines.length+' +1,'+newLines.length+' @@\n' + oldLines.map(l => '-'+l+'\n').join('') + newLines.map(l => '+'+l+'\n').join('');
    fs.writeFileSync(path.join(root,patchPath),patch);
    const entries = Array.isArray(manifest) ? manifest : manifest.files;
    const entry = entries.find(function (value) { return value.path === item[3] || 'public/assets/vendor/' + value.path === item[3]; });
    if (!entry) { throw new Error('Vendor manifest entry missing.'); }
    entry.original_sha256 = hash(original); entry.sha256 = hash(adapted.result); entry.patch = {path: patchPath, sha256: hash(patch)};
    entry.csp_profile = item[0] === 'Turf' ? 'area/length/centroid and pinned initialization' : 'five precompiled Spanish templates';
    console.log(item[0] + ': ' + adapted.factories + ' static factories; ES2015 parsed.');
});
fs.writeFileSync(manifestPath,JSON.stringify(manifest,null,2)+'\n');

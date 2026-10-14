// Development-only syntax check. No build or transpilation is used at runtime.
const fs = require('fs');
const path = require('path');
const acorn = require('./tools/acorn/dist/acorn.js');
const root = path.resolve(__dirname, '..');
const files = [];
function walk(directory) {
    for (const name of fs.readdirSync(directory)) {
        const target = path.join(directory, name);
        if (fs.statSync(target).isDirectory()) walk(target);
        else if (name.endsWith('.js')) files.push(target);
    }
}
walk(path.join(root, 'public/assets/js'));
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/assets/vendor/manifest.json'), 'utf8'));
for (const asset of manifest.files) if (asset.path.endsWith('.js')) files.push(path.join(root, asset.path));
let failed = false;
for (const file of files) {
    try { acorn.parse(fs.readFileSync(file, 'utf8'), {ecmaVersion: 6, sourceType: file.includes(path.join('js', 'modules')) ? 'module' : 'script'}); }
    catch (error) { failed = true; console.error(path.relative(root, file), error.message); }
}
console.log(files.length + ' JavaScript files checked against ES2015.');
process.exit(failed ? 1 : 0);

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const pluginSlug = 'one-patch-security';
const root = process.cwd();
const buildDir = path.join(root, 'build');

const EXCLUDE_DIRS = new Set([
    'node_modules',
    'vendor',
    '.git',
    '.github',
    '.idea',
    '.vscode',
    'build'
]);

const EXCLUDE_FILES = [
    '.gitignore',
    '.editorconfig',
    '.DS_Store',
    'package.json',
    'package-lock.json',
    'composer.json',
    'composer.lock',
    'webpack.config.js'
];

fs.rmSync(buildDir, { recursive: true, force: true });
fs.mkdirSync(buildDir);

function copyRecursive(src, dest) {
    const items = fs.readdirSync(src);

    items.forEach(item => {
        if (EXCLUDE_DIRS.has(item)) return;
        if (item.startsWith('.')) return;
        if (EXCLUDE_FILES.includes(item)) return;

        const srcPath = path.join(src, item);
        const destPath = path.join(dest, item);
        const stats = fs.statSync(srcPath);

        if (stats.isDirectory()) {
            fs.mkdirSync(destPath);
            copyRecursive(srcPath, destPath);
        } else {
            fs.copyFileSync(srcPath, destPath);
        }
    });
}

copyRecursive(root, buildDir);

const output = fs.createWriteStream(`${pluginSlug}.zip`);
const archive = archiver('zip', { zlib: { level: 9 } });

archive.pipe(output);
archive.directory(buildDir + '/', pluginSlug);
archive.finalize();

console.log(`✔ Built ${pluginSlug}.zip successfully.`);

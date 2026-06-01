#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const files = [
  ['src/shared/design-system.css', 'build/shared/design-system.css'],
  ['src/admin/styles/index.css', 'build/admin/index.css'],
  ['src/admin/styles/native-workflow.css', 'build/admin/native-workflow.css'],
  ['src/frontend/styles/switcher.css', 'build/frontend/switcher.css'],
  ['src/frontend/styles/visual-editor.css', 'build/frontend/visual-editor.css'],
];

for (const [source, target] of files) {
  const sourcePath = path.join(root, source);
  const targetPath = path.join(root, target);
  if (!fs.existsSync(sourcePath)) {
    throw new Error(`Missing CSS source: ${source}`);
  }
  fs.mkdirSync(path.dirname(targetPath), { recursive: true });
  const css = fs.readFileSync(sourcePath, 'utf8').trimEnd();
  fs.writeFileSync(targetPath, `/* Generated from ${source}. Do not edit build files directly. */\n${css}\n`);
  console.log(`${source} -> ${target}`);
}

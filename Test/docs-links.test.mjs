import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');

function markdownFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(directory, entry.name);
    if (entry.isDirectory()) return markdownFiles(target);
    return entry.isFile() && entry.name.endsWith('.md') ? [target] : [];
  });
}

test('all local Markdown links resolve', () => {
  const broken = [];
  for (const file of markdownFiles(root)) {
    const source = fs.readFileSync(file, 'utf8');
    for (const match of source.matchAll(/\[[^\]]+\]\(([^)]+)\)/g)) {
      const target = match[1].trim().replace(/^<|>$/g, '');
      if (!target || target.startsWith('#') || /^[a-z][a-z0-9+.-]*:/i.test(target) || target.startsWith('//')) continue;
      const [relative] = target.split('#', 1);
      const resolved = path.resolve(path.dirname(file), relative);
      if (!fs.existsSync(resolved)) broken.push(`${path.relative(root, file)} -> ${target}`);
    }
  }
  assert.deepEqual(broken, []);
});

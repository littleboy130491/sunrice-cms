import assert from 'node:assert/strict';
import { after, afterEach, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { readFile, readdir } from 'node:fs/promises';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';
import ts from 'typescript';

const server = await createServer({
    configFile: false,
    resolve: { alias: { '@': fileURLToPath(new URL('../../resources/js', import.meta.url)) } },
    server: { middlewareMode: true, watch: null },
    appType: 'custom',
});
const { CollapsibleCard } = await server.ssrLoadModule('/resources/js/components/app/collapsible-card.tsx');
after(() => server.close());
afterEach(() => delete globalThis.window);

const render = (props = {}, children = 'Card content') =>
    renderToStaticMarkup(createElement(CollapsibleCard, { title: 'Details', ...props }, children));

test('starts expanded and exposes an accessible, non-submit toggle', () => {
    const html = render();
    assert.match(html, /type="button" aria-expanded="true"/);
    const id = html.match(/aria-controls="([^"]+)"/)[1];
    assert.ok(html.includes(`id="${id}" data-state="open"`));
    assert.doesNotMatch(html, /inert=""/);
});

test('keeps fields mounted but inert and hidden from assistive technology when collapsed', () => {
    const html = render({ defaultOpen: false }, createElement('input', { name: 'title', defaultValue: 'Unsaved content' }));
    assert.match(html, /aria-expanded="false"/);
    assert.match(html, /data-state="closed"/);
    assert.match(html, /inert="" aria-hidden="true"/);
    assert.match(html, /name="title" value="Unsaved content"/);
    assert.doesNotMatch(html, /disabled/);
});

test('remembers a collapsed preference, but reveals validation errors', () => {
    globalThis.window = { localStorage: { getItem: (key) => {
        assert.equal(key, 'sunrice:card:settings:general');
        return '0';
    } } };
    assert.match(render({ storageKey: 'settings:general' }), /aria-expanded="false"/);
    assert.match(render({ storageKey: 'settings:general', hasErrors: true }), /aria-expanded="true"/);
});

test('falls back to the default state when browser storage is blocked', () => {
    globalThis.window = { localStorage: { getItem: () => { throw new Error('Storage blocked'); } } };
    assert.match(render({ storageKey: 'settings:general' }), /aria-expanded="true"/);
});

test('keeps header actions outside the collapse button', () => {
    const html = render({ headerAction: createElement('button', { type: 'button' }, 'Toggle all') });
    assert.match(html, /<\/button>.*<button type="button">Toggle all<\/button>/);
    const firstButton = html.match(/<button[^>]*>[\s\S]*?<\/button>/)[0];
    assert.doesNotMatch(firstButton, /Toggle all/);
});

test('page-level read-only fieldsets never disable card toggles', async () => {
    const directory = new URL('../../resources/js/pages/', import.meta.url);
    for (const file of (await readdir(directory, { recursive: true })).filter((name) => name.endsWith('.tsx'))) {
        const source = ts.createSourceFile(file, await readFile(new URL(file, directory), 'utf8'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
        const walk = (node) => {
            if (ts.isJsxElement(node) && node.openingElement.tagName.getText(source) === 'CollapsibleCard') {
                for (let parent = node.parent; parent; parent = parent.parent) {
                    if (!ts.isJsxElement(parent) || parent.openingElement.tagName.getText(source) !== 'fieldset') continue;
                    assert.ok(!parent.openingElement.attributes.properties.some((attribute) => attribute.name?.getText(source) === 'disabled'), `${file}: disable fields inside the card, not its header`);
                }
            }
            ts.forEachChild(node, walk);
        };
        walk(source);
    }
});

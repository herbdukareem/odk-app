import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { mkdir, readFile } from 'node:fs/promises';
import { resolve, extname, sep } from 'node:path';
import { createServer } from 'node:http';

const require = createRequire(import.meta.url);
const { chromium } = require(process.argv[2] || 'playwright');
const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
const entry = manifest['resources/js/app.ts'];
const css = [manifest['resources/css/app.css'].file, ...entry.css];
const shell = '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">' + css.map(file => '<link rel="stylesheet" href="/build/' + file + '">').join('') + '<script type="module" src="/build/' + entry.file + '"></script></head><body><div id="app"></div></body></html>';
const publicRoot = resolve('public');
const server = createServer(async (request, response) => {
  const pathname = new URL(request.url, 'http://localhost').pathname;
  if (pathname.startsWith('/forms/')) return response.writeHead(200, { 'Content-Type': 'text/html' }).end(shell);
  const path = resolve(publicRoot, decodeURIComponent(pathname).replace(/^\/+/, ''));
  if (!path.startsWith(publicRoot + sep)) return response.writeHead(403).end();
  try {
    const content = await readFile(path);
    response.writeHead(200, { 'Content-Type': ({ '.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.png': 'image/png' })[extname(path)] || 'application/octet-stream' }).end(content);
  } catch { response.writeHead(404).end(); }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const base = 'http://127.0.0.1:' + server.address().port;
const output = resolve('storage/app/private/ui-checks/form-scoring');
await mkdir(output, { recursive: true });
const option = (value, score) => ({ label: value, value, score });
const field = (id, label, score, scorable = true) => ({ id, key: 'field_' + id, label, type: 'select', assessment_form_section_id: 1, sort_order: id, settings: { scorable }, options: [option('Yes', score), option('No', 0)] });
const form = {
  id: 1, slug: 'scored-form', title: 'Scored assessment', status: 'draft', requires_scoring: true, scoring_max_score: 125,
  sections: [{ id: 1, key: 'assessment_metadata', label: 'Questions', sort_order: 1 }],
  fields: [field(1, 'Equipment', 2), field(2, 'Personnel', 3), field(3, 'Unscored question', 100, false)],
  current_version: { version: 1, fields: [{ key: 'published_only', label: 'Old published field', type: 'select', options: [option('Yes', 99)] }] },
};
const overviewWrites = [];
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('dialog', dialog => dialog.accept());
await page.addInitScript(() => localStorage.setItem('odk_auth_token', 'browser-test-token'));
await page.route('**/api/**', async route => {
  const request = route.request();
  const path = new URL(request.url()).pathname;
  const send = data => route.fulfill({ json: { success: true, data } });
  if (path === '/api/auth/me') return send({ id: 1, name: 'Form Manager', role: { key: 'admin', name: 'Admin' }, workspaces: [{ id: 1, name: 'Scoring workspace', pivot: { role_key: 'owner' } }] });
  if (path === '/api/auth/workspaces') return send({ current: { id: 1, name: 'Scoring workspace' }, items: [{ id: 1, name: 'Scoring workspace' }] });
  if (path.startsWith('/api/forms/scored-form/fields/')) {
    const id = Number(path.split('/').at(-1));
    if (request.method() === 'DELETE') form.fields = form.fields.filter(item => item.id !== id);
    else Object.assign(form.fields.find(item => item.id === id), request.postDataJSON());
    return send(form.fields.find(item => item.id === id) || {});
  }
  if (path === '/api/forms/scored-form') {
    if (request.method() === 'PATCH') {
      const body = request.postDataJSON();
      overviewWrites.push(body);
      Object.assign(form, body);
    }
    return send(form);
  }
  return send([]);
});
const maximum = page.getByLabel('Maximum score', { exact: true });
const overview = () => page.getByRole('button', { name: 'Overview', exact: true }).click();
const builder = () => page.getByRole('button', { name: 'Builder', exact: true }).click();
async function expectMaximum(value) {
  await maximum.waitFor();
  assert.equal(await maximum.inputValue(), value);
  assert.equal(await maximum.evaluate(input => input.readOnly), true);
}
try {
  await page.goto(base + '/forms/scored-form/settings');
  await expectMaximum('5.00');
  await builder();
  await page.locator('.builder-field-row').filter({ hasText: 'Equipment' }).getByRole('button', { name: 'Edit', exact: true }).click();
  const editor = page.getByRole('dialog', { name: 'Edit field', exact: true });
  await editor.getByLabel('Score', { exact: true }).first().fill('7');
  await editor.getByRole('button', { name: 'Save field', exact: true }).click();
  await editor.waitFor({ state: 'hidden' });
  await overview();
  await expectMaximum('10.00');
  await page.getByRole('button', { name: 'Save draft', exact: true }).click();
  await page.getByText('Draft details saved.', { exact: true }).waitFor();
  assert.equal(Object.hasOwn(overviewWrites[0], 'scoring_max_score'), false);
  await page.screenshot({ path: output + '/maximum-desktop.png', fullPage: true, animations: 'disabled' });

  await builder();
  await page.locator('.builder-field-row').filter({ hasText: 'Equipment' }).getByRole('button', { name: 'Edit', exact: true }).click();
  await editor.getByLabel('This field contributes to the assessment score', { exact: true }).uncheck();
  await editor.getByRole('button', { name: 'Save field', exact: true }).click();
  await editor.waitFor({ state: 'hidden' });
  await overview();
  await expectMaximum('3.00');

  await builder();
  for (const label of ['Equipment', 'Personnel', 'Unscored question']) {
    await page.locator('.builder-field-row').filter({ hasText: label }).getByRole('button', { name: 'Remove', exact: true }).click();
    await page.waitForFunction(label => !Array.from(document.querySelectorAll('.builder-field-row')).some(node => node.textContent.includes(label)), label);
  }
  await overview();
  await expectMaximum('0.00');
  const toggle = page.locator('.settings-block').filter({ has: page.getByRole('heading', { name: 'Scoring', exact: true }) }).locator('.qds-switch');
  await toggle.click();
  assert.equal(await maximum.count(), 0);
  await toggle.click();
  await expectMaximum('0.00');

  await page.setViewportSize({ width: 390, height: 844 });
  await maximum.scrollIntoViewIfNeeded();
  const bounds = await maximum.boundingBox();
  assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= 390, 'Maximum score overflows the mobile viewport');
  await page.screenshot({ path: output + '/maximum-mobile.png', animations: 'disabled' });
  assert.deepEqual(errors, []);
  console.log(JSON.stringify({ result: 'passed', calculated: [5, 10, 3, 0], readOnly: true, viewports: ['desktop', 'mobile'], screenshots: output }));
} finally {
  await browser.close();
  await new Promise(done => server.close(done));
}

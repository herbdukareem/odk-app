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
  if (pathname === '/assignments') return response.writeHead(200, { 'Content-Type': 'text/html' }).end(shell);
  const path = resolve(publicRoot, decodeURIComponent(pathname).replace(/^\/+/, ''));
  if (!path.startsWith(publicRoot + sep)) return response.writeHead(403).end();
  try {
    const content = await readFile(path);
    response.writeHead(200, { 'Content-Type': ({ '.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.png': 'image/png' })[extname(path)] || 'application/octet-stream' }).end(content);
  } catch { response.writeHead(404).end(); }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const base = 'http://127.0.0.1:' + server.address().port;
const output = resolve('storage/app/private/ui-checks/multi-exercise');
await mkdir(output, { recursive: true });
const exercise = (id, name, target_type, ends_at) => ({ id, uuid: 'exercise-' + id, name, code: 'EX-' + id, status: 'active', target_type, ends_at });
const exercises = [
  exercise(1, 'October supervision', 'facility', '2026-10-31'),
  exercise(2, 'November monitoring and evaluation supervision 2026', 'facility', '2026-11-30'),
  exercise(3, 'Site inspection', 'site', '2026-12-31'),
  exercise(4, 'General survey', 'no_target', '2026-12-31'),
];
const facilities = [
  { id: 10, source_id: 10, name: 'North clinic', source_code: 'NORTH', lga: { name: 'North LGA' }, ward: { name: 'Central ward' } },
  { id: 11, source_id: 11, name: 'South clinic', source_code: 'SOUTH' },
];
const users = [{ id: 1, uuid: 'user-1', name: 'Assignment Manager', email: 'manager@example.com' }];
const teams = [{ id: 2, uuid: 'team-2', name: 'First team' }, { id: 3, uuid: 'team-3', name: 'Second team' }];
const options = { exercises, facilities, users, teams, target_types: ['facility', 'site', 'no_target'], targets: [{ id: 20, type: 'site', name: 'Project site' }] };
const writes = [];
const updates = [];
let assignments = [];
let failNext = false;
const queries = [];
let holdUserRequest = false;
let releaseUserRequest;
const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_EXECUTABLE_PATH });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
page.setDefaultTimeout(10000);
const errors = [];
page.on('pageerror', error => errors.push(error.message));
await page.addInitScript(() => localStorage.setItem('odk_auth_token', 'browser-test-token'));
await page.route('**/api/**', async route => {
  const request = route.request();
  const path = new URL(request.url()).pathname;
  const send = data => route.fulfill({ json: { success: true, data } });
  if (path === '/api/auth/me') return send({ ...users[0], role: { key: 'admin', name: 'Admin' }, workspaces: [{ id: 1, name: 'Assignment workspace', pivot: { role_key: 'owner' } }] });
  if (path === '/api/auth/workspaces') return send({ current: { id: 1, name: 'Assignment workspace' }, items: [{ id: 1, name: 'Assignment workspace' }] });
  if (path === '/api/assignments' && request.method() === 'GET') {
    const query = new URL(request.url()).searchParams;
    queries.push(Object.fromEntries(query));
    const filtered = assignments.filter(item => (
      (!query.get('exercise_id') || String(item.assessment_exercise_id) === query.get('exercise_id'))
      && (!query.get('assignee_type') || item.assignee_type === query.get('assignee_type'))
      && (!query.get('assignee_id') || String(item.assignee_type === 'team' ? item.odk_team_id : item.odk_user_id) === query.get('assignee_id'))
      && (!query.get('status') || item.status === query.get('status'))
    ));
    const currentPage = Number(query.get('page') || 1);
    const perPage = Number(query.get('per_page') || 20);
    if (holdUserRequest && query.get('assignee_type') === 'user' && query.get('assignee_id') === '1') {
      holdUserRequest = false;
      await new Promise(done => { releaseUserRequest = done; });
    }
    return send({ assignments: { data: filtered.slice((currentPage - 1) * perPage, currentPage * perPage), last_page: Math.max(1, Math.ceil(filtered.length / perPage)) }, options, stats: { total: assignments.length, assigned: assignments.length, in_progress: 0, submitted: 0, reviewed: 0 } });
  }
  if (path === '/api/assignments' && request.method() === 'POST') {
    const body = request.postDataJSON();
    writes.push(body);
    await new Promise(done => setTimeout(done, 150));
    if (failNext) {
      failNext = false;
      return route.fulfill({ status: 422, json: { message: 'Already assigned for November monitoring.', errors: { facility_ids: ['Already assigned for November monitoring.'] } } });
    }
    const created = body.assessment_exercise_ids.flatMap(id => (
      body.target_type === 'facility' ? body.facility_ids : body.target_type === 'no_target' ? [null] : body.target_ids
    ).map(targetId => ({
      id: assignments.length + 1, uuid: 'assignment-' + id + '-' + targetId,
      assessment_exercise_id: id, exercise: exercises.find(item => item.id === id),
      reference_facility_id: body.target_type === 'facility' ? targetId : null,
      facility: facilities.find(item => item.id === targetId), target_type: body.target_type,
      odk_target_id: body.target_type === 'site' ? targetId : null,
      assignee_type: 'user', odk_user_id: body.assignee_id, user: users[0], status: 'assigned',
      due_at: body.due_at || exercises.find(item => item.id === id).ends_at,
      allow_duplicate_submission: body.allow_duplicate_submission, submissions_count: 0,
    })));
    assignments = created;
    return send(created);
  }
  if (path.startsWith('/api/assignments/') && request.method() === 'PATCH') {
    updates.push(request.postDataJSON());
    return send(assignments[0]);
  }
  return send([]);
});
const modal = page.getByRole('dialog');
const open = async () => {
  await page.getByRole('button', { name: 'New assignment', exact: true }).click();
  await modal.waitFor();
};
const selector = modal.locator('.qds-multi-select');
const checkbox = name => selector.getByRole('checkbox', { name, exact: true });
const selectFacilities = async () => {
  for (const name of ['North clinic', 'South clinic']) await modal.getByRole('checkbox', { name: new RegExp(name) }).check();
};
try {
  await page.goto(base + '/assignments');
  await open();
  assert.equal(await modal.getByLabel('Due date (optional)').inputValue(), '2026-10-31');
  await selectFacilities();
  await selector.getByRole('button', { name: 'Exercises', exact: true }).click();
  assert.equal(await checkbox('Site inspection (EX-3)').count(), 0);
  await checkbox('November monitoring and evaluation supervision 2026 (EX-2)').check();
  assert.equal(await modal.getByLabel('Due date (optional)').inputValue(), '');
  assert.equal(await modal.getByRole('checkbox', { name: /North clinic/ }).isChecked(), true);
  assert.equal(await modal.getByRole('checkbox', { name: /South clinic/ }).isChecked(), true);
  await modal.getByRole('button', { name: 'Create 4 assignments', exact: true }).waitFor();
  await page.screenshot({ path: output + '/batch-desktop.png', fullPage: true, animations: 'disabled' });

  await selector.getByRole('button', { name: 'Clear', exact: true }).click();
  assert.equal(await modal.getByRole('button', { name: 'Create 0 assignments', exact: true }).isDisabled(), true);
  await selector.getByPlaceholder('Search exercises...').fill('November');
  await checkbox('Select all').check();
  assert.equal(await checkbox('November monitoring and evaluation supervision 2026 (EX-2)').isChecked(), true);
  await selector.getByPlaceholder('Search exercises...').fill('');
  assert.equal(await checkbox('October supervision (EX-1)').isChecked(), false);
  await checkbox('Select all').check();
  await modal.getByLabel('Due date (optional)').fill('2026-12-20');
  await checkbox('October supervision (EX-1)').uncheck();
  assert.equal(await modal.getByLabel('Due date (optional)').inputValue(), '2026-12-20');
  await checkbox('October supervision (EX-1)').check();
  await modal.getByLabel('Due date (optional)').fill('');
  await modal.getByRole('button', { name: 'Create 4 assignments', exact: true }).click();
  assert.equal(await modal.getByRole('button', { name: 'Saving...', exact: true }).isDisabled(), true);
  await modal.waitFor({ state: 'hidden' });
  assert.equal(writes.length, 1);
  assert.deepEqual([...writes[0].assessment_exercise_ids].sort(), [1, 2]);
  assert.deepEqual(writes[0].facility_ids, [10, 11]);
  assert.equal(Object.hasOwn(writes[0], 'assessment_exercise_id'), false);
  assert.equal(writes[0].due_at, null);
  await page.getByText('4 assignments created.', { exact: true }).waitFor();

  // Existing records stay single-exercise edits, including a non-default team.
  assignments[0].assignee_type = 'team';
  assignments[0].odk_team_id = 3;
  assignments[0].team = teams[1];
  await page.getByRole('button', { name: 'Refresh', exact: true }).click();
  await page.getByRole('button', { name: 'Edit', exact: true }).first().click();
  assert.equal(await modal.getByLabel('Exercise', { exact: true }).evaluate(input => input.readOnly), true);
  assert.equal(await modal.getByRole('combobox', { name: /^Assignee/ }).inputValue(), '3');
  assert.equal(await modal.getByRole('combobox', { name: /^Target type/ }).isDisabled(), true);
  await modal.getByRole('button', { name: 'Save assignment', exact: true }).click();
  await modal.waitFor({ state: 'hidden' });
  assert.equal(updates[0].assignee_id, 3);

  await open();
  await selector.getByRole('button', { name: 'Exercises', exact: true }).click();
  await checkbox('Select all').check();
  await selectFacilities();
  failNext = true;
  await modal.getByRole('button', { name: 'Create 4 assignments', exact: true }).click();
  await modal.getByRole('alert').filter({ hasText: 'Already assigned' }).waitFor();
  assert.equal(await checkbox('October supervision (EX-1)').isChecked(), true);
  assert.equal(await modal.getByRole('checkbox', { name: /North clinic/ }).isChecked(), true);

  await page.setViewportSize({ width: 390, height: 844 });
  await selector.scrollIntoViewIfNeeded();
  const bounds = await selector.boundingBox();
  assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= 390, 'Exercise selector overflows mobile viewport');
  assert.equal(await modal.evaluate(node => node.scrollWidth <= node.clientWidth), true);
  await page.screenshot({ path: output + '/batch-mobile.png', animations: 'disabled' });

  await modal.getByRole('combobox', { name: /^Target type/ }).selectOption('site');
  assert.equal(await checkbox('October supervision (EX-1)').count(), 0);
  await checkbox('Site inspection (EX-3)').check();
  await modal.getByRole('checkbox', { name: /Project site/ }).check();
  await modal.getByRole('button', { name: 'Create 1 assignment', exact: true }).click();
  await modal.waitFor({ state: 'hidden' });
  assert.deepEqual(writes.at(-1).assessment_exercise_ids, [3]);
  assert.deepEqual(writes.at(-1).target_ids, [20]);
  assert.deepEqual(writes.at(-1).facility_ids, []);

  // Exercise and individual assignee filters are independent of assignment creation.
  users.push({ id: 2, uuid: 'user-2', name: 'Second assessor', email: 'second@example.com' });
  const row = (id, exerciseId, type, assigneeId) => ({
    id, uuid: 'filter-' + id, assessment_exercise_id: exerciseId, exercise: exercises.find(item => item.id === exerciseId),
    facility: { name: 'Filter facility ' + id }, reference_facility_id: id, target_type: 'facility',
    assignee_type: type, odk_user_id: type === 'user' ? assigneeId : null, odk_team_id: type === 'team' ? assigneeId : null,
    user: users.find(item => item.id === assigneeId), team: teams.find(item => item.id === assigneeId),
    status: 'assigned', allow_duplicate_submission: false, submissions_count: 0,
  });
  assignments = [
    ...Array.from({ length: 21 }, (_, index) => row(index + 100, 1, 'user', 1)),
    row(200, 1, 'user', 2), row(201, 2, 'user', 1),
    row(202, 1, 'team', 2), row(203, 1, 'team', 3), row(204, 2, 'team', 3),
  ];
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.getByRole('button', { name: 'Refresh', exact: true }).click();
  await page.getByText('Page 1 of 2', { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Next', exact: true }).click();
  await page.getByText('Page 2 of 2', { exact: true }).waitFor();
  const toolbar = page.locator('.qds-toolbar');
  const typeFilter = toolbar.getByRole('combobox', { name: /^Assignee/ });
  await typeFilter.selectOption('user');
  await page.getByText('Page 1 of 2', { exact: true }).waitFor();
  await toolbar.getByRole('combobox', { name: /^Exercise/ }).selectOption('1');
  holdUserRequest = true;
  const held = page.waitForRequest(request => new URL(request.url()).searchParams.get('assignee_id') === '1');
  await toolbar.getByRole('combobox', { name: /^User/ }).selectOption('1');
  await held;
  await toolbar.getByRole('combobox', { name: /^User/ }).selectOption('2');
  await page.locator('.assignments-table .qds-table__row').filter({ hasText: 'Second assessor' }).waitFor();
  releaseUserRequest();
  await page.waitForLoadState('networkidle');
  assert.equal(await page.locator('.assignments-table .qds-table__row').count(), 1);
  assert.equal(await toolbar.getByRole('combobox', { name: /^Exercise/ }).inputValue(), '1');
  assert.equal(queries.at(-1).assignee_id, '2');
  assert.equal(queries.at(-1).page, '1');
  assert.equal(queries.at(-1).exercise_id, '1');
  await page.screenshot({ path: output + '/filters-desktop.png', fullPage: true, animations: 'disabled' });

  await typeFilter.selectOption('team');
  assert.equal(await toolbar.getByRole('combobox', { name: /^Team/ }).inputValue(), '');
  await toolbar.getByRole('combobox', { name: /^Team/ }).selectOption('3');
  await page.locator('.assignments-table .qds-table__row').filter({ hasText: 'Second team' }).waitFor();
  assert.equal(await page.locator('.assignments-table .qds-table__row').count(), 1);
  assert.equal(queries.at(-1).assignee_type, 'team');
  assert.equal(queries.at(-1).assignee_id, '3');
  assert.equal(queries.at(-1).exercise_id, '1');
  await toolbar.getByRole('combobox', { name: /^Team/ }).selectOption('');
  await page.waitForFunction(() => document.querySelectorAll('.assignments-table .qds-table__row').length === 2);
  await typeFilter.selectOption('');
  await page.getByText('Page 1 of 2', { exact: true }).waitFor();
  assert.equal(Object.hasOwn(queries.at(-1), 'assignee_id'), false);
  assert.equal(Object.hasOwn(queries.at(-1), 'assignee_type'), false);
  await typeFilter.selectOption('team');
  await toolbar.getByRole('combobox', { name: /^Team/ }).selectOption('3');
  await page.locator('.assignments-table .qds-table__row').filter({ hasText: 'Second team' }).waitFor();
  await page.setViewportSize({ width: 390, height: 844 });
  await toolbar.scrollIntoViewIfNeeded();
  for (const field of await toolbar.getByRole('combobox').all()) {
    const box = await field.boundingBox();
    assert.ok(box.x >= 0 && box.x + box.width <= 390, 'Assignment filter overflows mobile viewport');
  }
  await page.screenshot({ path: output + '/filters-mobile.png', animations: 'disabled' });
  assert.deepEqual(errors, []);
  console.log(JSON.stringify({ result: 'passed', batchSize: 4, checks: ['search', 'select all', 'due dates', 'target preservation', 'edit', 'errors', 'workspace target types', 'assignee filters', 'pagination reset', 'stale responses', 'desktop', 'mobile'], screenshots: output }));
} finally {
  await browser.close();
  await new Promise(done => server.close(done));
}


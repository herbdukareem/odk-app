import assert from 'node:assert/strict';
import test from 'node:test';
import {
  EVIDENCE_UPLOAD_MAX_ATTEMPTS,
  EVIDENCE_UPLOAD_TIMEOUT_MS,
  SyncUploadError,
  uploadEvidenceWithRetry,
  uploadPendingEvidence,
} from '../services/evidenceUploads.ts';

const ok = { status: 201, body: '{"success":true}' };
const immediateRetries = { retryDelayMs: 0 };

test('evidence uploads have a separate two-minute timeout', () => {
  assert.equal(EVIDENCE_UPLOAD_TIMEOUT_MS, 120_000);
  assert.equal(EVIDENCE_UPLOAD_MAX_ATTEMPTS, 3);
});

test('an upload lasting longer than the old 15-second window is allowed', async (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] });
  let resolveUpload;
  let signal;
  const promise = uploadEvidenceWithRetry(currentSignal => {
    signal = currentSignal;
    return new Promise(resolve => { resolveUpload = resolve; });
  });
  t.mock.timers.tick(16_000);
  assert.equal(signal.aborted, false);
  resolveUpload(ok);
  await promise;
  t.mock.timers.tick(120_000);
  assert.equal(signal.aborted, false, 'Successful uploads must clear the timeout');
});

test('temporary network and server failures are retried before succeeding', async () => {
  let attempts = 0;
  await uploadEvidenceWithRetry(async () => {
    attempts += 1;
    if (attempts === 1) throw new Error('Network connection was lost');
    return attempts === 2 ? { status: 503, body: '<title>Unavailable</title>' } : ok;
  }, immediateRetries);
  assert.equal(attempts, 3);
});

for (const status of [408, 429, 500, 502, 504]) {
  test('HTTP ' + status + ' is retried with a fresh request', async () => {
    const signals = [];
    await uploadEvidenceWithRetry(async signal => {
      signals.push(signal);
      return signals.length === 1 ? { status, body: '{}' } : ok;
    }, immediateRetries);
    assert.equal(signals.length, 2);
    assert.notEqual(signals[0], signals[1]);
  });
}

for (const status of [401, 403, 404, 413, 422]) {
  test('HTTP ' + status + ' is not retried and preserves the API error', async () => {
    let attempts = 0;
    await assert.rejects(uploadEvidenceWithRetry(async () => {
      attempts += 1;
      return { status, body: '{"message":"Action required"}' };
    }, immediateRetries), /Action required/);
    assert.equal(attempts, 1);
  });
}

test('timeouts cancel each native upload and stop after the bounded attempts', async () => {
  const signals = [];
  await assert.rejects(uploadEvidenceWithRetry(signal => {
    signals.push(signal);
    return new Promise((_, reject) => signal.addEventListener('abort', () => reject(new Error('Native cancellation'))));
  }, { timeoutMs: 10, retryDelayMs: 0 }), /Evidence upload timed out/);
  assert.equal(signals.length, 3);
  assert.equal(signals.every(signal => signal.aborted), true);
});

test('a timeout can recover without changing the stable attachment ID', async () => {
  const ids = [];
  const attachment = { id: 'stable-file-id' };
  await uploadEvidenceWithRetry(signal => {
    ids.push(attachment.id);
    return ids.length === 1
      ? new Promise((_, reject) => signal.addEventListener('abort', () => reject(new Error('aborted'))))
      : Promise.resolve(ok);
  }, { timeoutMs: 10, retryDelayMs: 0 });
  assert.deepEqual(ids, ['stable-file-id', 'stable-file-id']);
});

test('the remote submission and every uploaded file are checkpointed immediately', async () => {
  const checkpoints = [];
  const count = await uploadPendingEvidence({
    remoteUuid: 'saved-submission',
    attachments: [{ id: 'first' }, { id: 'second' }],
    upload: async attachment => {
      assert.equal(checkpoints[0].remoteUuid, 'saved-submission');
      if (attachment.id === 'second') assert.equal(checkpoints.at(-1).attachmentStatus.first.status, 'uploaded');
    },
    checkpoint: async progress => { checkpoints.push(progress); },
  });
  assert.equal(count, 2);
  assert.equal(checkpoints.length, 3);
  assert.equal(checkpoints[0].attachmentStatus.first.status, 'pending', 'Earlier checkpoints must not be mutated');
  assert.equal(checkpoints[2].attachmentStatus.second.status, 'uploaded');
});

test('retry resumes only failed files against the saved remote submission', async () => {
  let persisted;
  const uploaded = [];
  const checkpoint = async progress => { persisted = progress; };
  await assert.rejects(uploadPendingEvidence({
    remoteUuid: 'saved-submission',
    attachments: [{ id: 'first' }, { id: 'second' }],
    upload: async attachment => {
      uploaded.push(attachment.id);
      if (attachment.id === 'second') throw new Error('Network unavailable');
    },
    checkpoint,
  }), error => {
    assert.ok(error instanceof SyncUploadError);
    assert.equal(error.remoteUuid, 'saved-submission');
    assert.equal(error.attachmentStatus.first.status, 'uploaded');
    assert.equal(error.attachmentStatus.second.error, 'Network unavailable');
    return true;
  });
  const count = await uploadPendingEvidence({
    remoteUuid: persisted.remoteUuid,
    attachments: [{ id: 'first' }, { id: 'second' }],
    previousStatus: persisted.attachmentStatus,
    upload: async attachment => { uploaded.push(attachment.id); },
    checkpoint,
  });
  assert.equal(count, 2);
  assert.deepEqual(uploaded, ['first', 'second', 'second']);
  assert.equal(persisted.remoteUuid, 'saved-submission');
});

test('a restarted sync skips completed files even if later checkpoint persistence failed', async () => {
  let lastCheckpoint;
  const uploaded = [];
  await assert.rejects(uploadPendingEvidence({
    remoteUuid: 'saved-submission',
    attachments: [{ id: 'first' }, { id: 'second' }],
    upload: async attachment => { uploaded.push(attachment.id); },
    checkpoint: async progress => {
      if (progress.attachmentStatus.second.status === 'uploaded') throw new Error('Storage unavailable');
      lastCheckpoint = progress;
    },
  }), /Storage unavailable/);
  await uploadPendingEvidence({
    remoteUuid: lastCheckpoint.remoteUuid,
    attachments: [{ id: 'first' }, { id: 'second' }],
    previousStatus: lastCheckpoint.attachmentStatus,
    upload: async attachment => { uploaded.push(attachment.id); },
    checkpoint: async () => {},
  });
  assert.deepEqual(uploaded, ['first', 'second', 'second']);
});

test('files are not uploaded when the remote checkpoint cannot be saved', async () => {
  let uploads = 0;
  await assert.rejects(uploadPendingEvidence({
    remoteUuid: 'saved-submission', attachments: [{ id: 'first' }],
    upload: async () => { uploads += 1; },
    checkpoint: async () => { throw new Error('Storage unavailable'); },
  }), /Storage unavailable/);
  assert.equal(uploads, 0);
});


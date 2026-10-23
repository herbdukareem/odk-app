export const EVIDENCE_UPLOAD_TIMEOUT_MS = 120_000;
export const EVIDENCE_UPLOAD_MAX_ATTEMPTS = 3;

export type AttachmentUploadStatus = Record<string, {
  status: 'pending' | 'uploaded' | 'failed';
  error?: string;
  uploadedAt?: string;
}>;

export type EvidenceUploadProgress = {
  remoteUuid: string;
  attachmentStatus: AttachmentUploadStatus;
};

type UploadResponse = { status: number; body: string };

class UploadResponseError extends Error {
  readonly status: number;

  constructor(status: number, message: string) {
    super(message);
    this.name = 'UploadResponseError';
    this.status = status;
  }
}

export class SyncUploadError extends Error {
  readonly remoteUuid: string;
  readonly attachmentStatus: AttachmentUploadStatus;

  constructor(message: string, remoteUuid: string, attachmentStatus: AttachmentUploadStatus) {
    super(message);
    this.name = 'SyncUploadError';
    this.remoteUuid = remoteUuid;
    this.attachmentStatus = attachmentStatus;
  }
}

function responseError(response: UploadResponse): UploadResponseError {
  let detail = '';
  try {
    const json = JSON.parse(response.body) as { message?: unknown; error?: unknown };
    detail = String(json.message ?? json.error ?? '').trim();
  } catch {
    detail = response.body.match(/<title[^>]*>(.*?)<\/title>/i)?.[1]?.replace(/\s+/g, ' ').trim() ?? '';
  }
  return new UploadResponseError(response.status, 'Evidence upload failed (' + response.status + ')' + (detail ? ': ' + detail : '.'));
}

export async function uploadEvidenceWithRetry(
  upload: (signal: AbortSignal) => Promise<UploadResponse>,
  options: { timeoutMs?: number; retryDelayMs?: number } = {}
): Promise<void> {
  const timeoutMs = options.timeoutMs ?? EVIDENCE_UPLOAD_TIMEOUT_MS;
  for (let attempt = 1; attempt <= EVIDENCE_UPLOAD_MAX_ATTEMPTS; attempt += 1) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);
    try {
      const result = await upload(controller.signal);
      if (result.status < 200 || result.status >= 300) throw responseError(result);
      return;
    } catch (error) {
      const failure = controller.signal.aborted
        ? new Error('Evidence upload timed out after ' + Math.round(timeoutMs / 1000) + 's.')
        : error instanceof Error ? error : new Error('Evidence upload failed.');
      const retryable = !(failure instanceof UploadResponseError)
        || failure.status === 408 || failure.status === 429 || failure.status >= 500;
      if (!retryable || attempt === EVIDENCE_UPLOAD_MAX_ATTEMPTS) throw failure;
    } finally {
      clearTimeout(timeout);
    }
    await new Promise(resolve => setTimeout(resolve, (options.retryDelayMs ?? 1000) * 2 ** (attempt - 1)));
  }
}

export async function uploadPendingEvidence<T extends { id: string }>({
  remoteUuid,
  attachments,
  previousStatus = {},
  upload,
  checkpoint,
}: {
  remoteUuid: string;
  attachments: T[];
  previousStatus?: AttachmentUploadStatus;
  upload: (attachment: T) => Promise<void>;
  checkpoint: (progress: EvidenceUploadProgress) => Promise<void>;
}): Promise<number> {
  const attachmentStatus: AttachmentUploadStatus = Object.fromEntries(
    attachments.map(attachment => [attachment.id, previousStatus[attachment.id] ?? { status: 'pending' }])
  );
  const persist = () => checkpoint({
    remoteUuid,
    attachmentStatus: Object.fromEntries(Object.entries(attachmentStatus).map(([id, status]) => [id, { ...status }])),
  });

  // Persist the remote identity before uploading files so an interrupted sync resumes it.
  await persist();
  for (const attachment of attachments) {
    if (attachmentStatus[attachment.id]?.status === 'uploaded') continue;
    try {
      await upload(attachment);
    } catch (error) {
      attachmentStatus[attachment.id] = { status: 'failed', error: error instanceof Error ? error.message : 'Evidence upload failed.' };
      await persist();
      throw new SyncUploadError('Submission saved, but an evidence upload failed.', remoteUuid, attachmentStatus);
    }
    attachmentStatus[attachment.id] = { status: 'uploaded', uploadedAt: new Date().toISOString() };
    await persist();
  }
  return Object.values(attachmentStatus).filter(item => item.status === 'uploaded').length;
}


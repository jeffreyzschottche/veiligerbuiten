type TrackingStatus = 'authorized' | 'denied' | 'restricted' | 'notDetermined';

/** Share concurrent callers, but retry an unanswered system request on the next call. */
export function createTrackingConsent(options: {
  isIos: () => boolean;
  whenActive: () => Promise<void>;
  status: () => Promise<{ status: TrackingStatus }>;
  request: () => Promise<void>;
}) {
  let pending: Promise<TrackingStatus | 'notApplicable'> | undefined;
  return function ensureTrackingConsent(): Promise<TrackingStatus | 'notApplicable'> {
    if (!options.isIos()) return Promise.resolve('notApplicable');
    if (pending) return pending;
    pending = (async () => {
      await options.whenActive();
      let { status } = await options.status();
      if (status === 'notDetermined') {
        await options.request();
        status = (await options.status()).status;
      }
      return status;
    })().finally(() => { pending = undefined; });
    return pending;
  };
}

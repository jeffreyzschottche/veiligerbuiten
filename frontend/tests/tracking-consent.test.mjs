import test from 'node:test';
import assert from 'node:assert/strict';
import { createTrackingConsent } from '../utils/trackingConsent.ts';

function setup(initial = 'notDetermined', result = 'authorized', active = Promise.resolve()) {
  let status = initial;
  let requests = 0;
  const ensure = createTrackingConsent({
    isIos: () => true,
    whenActive: () => active,
    status: async () => ({ status }),
    request: async () => { requests++; status = result; },
  });
  return { ensure, get requests() { return requests; }, setStatus(value) { status = value; } };
}

test('first launch requests ATT without waiting for a login or banner', async () => {
  const consent = setup();
  assert.equal(await consent.ensure(), 'authorized');
  assert.equal(consent.requests, 1);
});

test('concurrent startup and advertising callers share one request', async () => {
  const consent = setup();
  assert.deepEqual(await Promise.all([consent.ensure(), consent.ensure(), consent.ensure()]), ['authorized', 'authorized', 'authorized']);
  assert.equal(consent.requests, 1);
});

test('waits for the native app to become active before requesting ATT', async () => {
  let activate;
  const consent = setup('notDetermined', 'denied', new Promise((resolve) => { activate = resolve; }));
  const pending = consent.ensure();
  await Promise.resolve();
  assert.equal(consent.requests, 0);
  activate();
  assert.equal(await pending, 'denied');
});

for (const status of ['authorized', 'denied', 'restricted']) {
  test(`respects an existing ${status} decision without another prompt`, async () => {
    const consent = setup(status);
    assert.equal(await consent.ensure(), status);
    assert.equal(consent.requests, 0);
  });
}

test('an interrupted system prompt remains pending and is retried next time', async () => {
  const consent = setup('notDetermined', 'notDetermined');
  assert.equal(await consent.ensure(), 'notDetermined');
  assert.equal(await consent.ensure(), 'notDetermined');
  assert.equal(consent.requests, 2);
});

test('rereads tracking settings if permission is revoked after initialization', async () => {
  const consent = setup();
  assert.equal(await consent.ensure(), 'authorized');
  consent.setStatus('denied');
  assert.equal(await consent.ensure(), 'denied');
  assert.equal(consent.requests, 1);
});

test('web and Android never invoke the iOS permission APIs', async () => {
  const unexpected = async () => { throw new Error('iOS API called'); };
  const ensure = createTrackingConsent({ isIos: () => false, whenActive: unexpected, status: unexpected, request: unexpected });
  assert.equal(await ensure(), 'notApplicable');
});

test('a native API error fails closed and allows a later retry', async () => {
  let fail = true;
  const ensure = createTrackingConsent({
    isIos: () => true,
    whenActive: async () => {},
    status: async () => { if (fail) throw new Error('native error'); return { status: 'denied' }; },
    request: async () => {},
  });
  await assert.rejects(ensure(), /native error/);
  fail = false;
  assert.equal(await ensure(), 'denied');
});

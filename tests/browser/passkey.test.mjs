import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { runInNewContext } from 'node:vm';

function browser({conditional = true, create = true, verifyFailure = false, holdFirst = false} = {}) {
    const calls = [];
    let releaseFirst;
    const input = { value: 'old' };
    const credential = { id: 'key', type: 'public-key', rawId: new Uint8Array([1]).buffer,
        response: { clientDataJSON: new Uint8Array([2]).buffer, authenticatorData: new Uint8Array([3]).buffer,
            signature: new Uint8Array([4]).buffer, userHandle: null, attestationObject: new Uint8Array([5]).buffer } };
    const window = { PublicKeyCredential: {
        isConditionalMediationAvailable: async () => conditional,
        getClientCapabilities: async () => ({ conditionalCreate: create }),
        signalAllAcceptedCredentials: async (data) => { calls.push(['signal', data]); },
    }};
    const context = { window, PublicKeyCredential: window.PublicKeyCredential,
        document: { querySelectorAll: () => [input] }, AbortController, Uint8Array, atob, btoa,
        navigator: { credentials: {
            get: async (options) => { calls.push(['get', options]); return credential; },
            create: async (options) => { calls.push(['create', options]); return credential; },
        }},
        fetch: async (url, options) => {
            calls.push(['fetch', url, options]);
            if (holdFirst && calls.filter(c => c[0] === 'fetch').length === 1) { await new Promise(resolve => { releaseFirst = resolve; }); }
            const verify = url.endsWith('/verify');
            const data = url.includes('credentials/sync')
                ? { rpId: 'example.com', userId: 'dXNlcg', allAcceptedCredentialIds: ['a2V5'] }
                : { challenge: 'a2V5', rp: { id: 'example.com' }, rpId: 'example.com', user: { id: 'dXNlcg' }, token: verify && verifyFailure ? 'verify-token' : 'rotated' };
            const json = async () => data;
            return {ok: !(verify && verifyFailure), json, clone: () => ({json})};
        },
    };
    let script = readFileSync(new URL('../../src/Views/_webauthn_js.php', import.meta.url), 'utf8');
    script = script.replace(/<\/?script>/g, '').replace(/<\?= site_url\('([^']+)'\) \?>/g, '$1').replace(/<\?=[\s\S]*?\?>/g, 'csrf');
    runInNewContext(script, context);
    return { api: window.AuthWebAuthn, calls, input, releaseFirst: () => releaseFirst() };
}

test('conditional login uses discoverable options, abortable mediation and refreshes form CSRF', async () => {
    const {api, calls, input} = browser();
    await api.conditionalLogin();
    const get = calls.find(c => c[0] === 'get')[1];
    assert.equal(get.mediation, 'conditional');
    assert.ok(get.signal);
    assert.equal(input.value, 'rotated');
    assert.equal(JSON.parse(calls.find(c => c[0] === 'fetch')[2].body).email, null);
});

test('unsupported conditional login does not request a ceremony', async () => {
    const {api, calls} = browser({conditional: false});
    assert.equal(await api.conditionalLogin(), null);
    assert.equal(calls.length, 0);
});

test('conditional create is capability gated and does not block ordinary registration', async () => {
    const {api, calls} = browser();
    await api.conditionalRegister('Laptop');
    assert.equal(calls.find(c => c[0] === 'create')[1].mediation, 'conditional');
    const plain = browser({create: false});
    assert.equal(await plain.api.conditionalRegister('Laptop'), null);
    assert.equal(plain.calls.length, 0);
    await plain.api.register('Laptop');
    assert.equal(plain.calls.find(c => c[0] === 'create')[1].mediation, undefined);
});

test('credential synchronization sends only authenticated server snapshot to browser', async () => {
    const {api, calls} = browser();
    await api.syncCredentials();
    assert.equal(calls.find(c => c[0] === 'fetch')[2].credentials, 'same-origin');
    const data = calls.find(c => c[0] === 'signal')[1];
    assert.equal(data.rpId, 'example.com');
    assert.equal(data.userId, 'dXNlcg');
});


test('failed conditional verification refreshes CSRF for both password and passkey retries', async () => {
    const {api, calls, input} = browser({verifyFailure: true});
    const response = await api.conditionalLogin();
    assert.equal(response.ok, false);
    await response.json();
    assert.equal(api.csrfHash(), 'verify-token');
    assert.equal(input.value, 'verify-token');
    await api.login(null);
    const optionsCalls = calls.filter(c => c[0] === 'fetch' && c[1].endsWith('/login/options'));
    assert.equal(optionsCalls[1][2].headers.csrf, 'verify-token');
});


test('overlapping ceremony posts serialize CSRF rotation before sending the next request', async () => {
    const {api, calls, releaseFirst} = browser({holdFirst: true});
    const first = api.post('first', {});
    const second = api.post('second', {});
    await Promise.resolve();
    assert.equal(calls.filter(c => c[0] === 'fetch').length, 1);
    releaseFirst();
    await Promise.all([first, second]);
    const requests = calls.filter(c => c[0] === 'fetch');
    assert.equal(requests[1][2].headers.csrf, 'rotated');
});

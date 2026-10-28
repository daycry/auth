<script>
// Minimal base64url <-> ArrayBuffer helpers + ceremony wrappers (vanilla JS).
window.AuthWebAuthn = (function () {
    // CI4 CSRF: send the token on every state-changing request and rotate the
    // cached hash whenever the server returns a fresh one (CI4 regenerates the
    // token per request when csrf.regenerate is enabled).
    const CSRF_HEADER = '<?= csrf_header() ?>';
    let CSRF_HASH = '<?= csrf_hash() ?>';
    let conditionalController = null;
    const refreshCsrf = (json) => {
        if (json && typeof json.token === 'string') {
            CSRF_HASH = json.token;
            document.querySelectorAll('input[name="<?= csrf_token() ?>"]').forEach((input) => { input.value = CSRF_HASH; });
        }
        return json;
    };
    const b64urlToBuf = (s) => {
        s = s.replace(/-/g, '+').replace(/_/g, '/');
        const bin = atob(s.padEnd(Math.ceil(s.length / 4) * 4, '='));
        const buf = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) { buf[i] = bin.charCodeAt(i); }
        return buf.buffer;
    };
    const bufToB64url = (buf) => {
        const bytes = new Uint8Array(buf);
        let bin = '';
        for (let i = 0; i < bytes.length; i++) { bin += String.fromCharCode(bytes[i]); }
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    };
    const sendPost = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                [CSRF_HEADER]: CSRF_HASH,
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : null,
        });
        // Clone preserves the Response contract for callers while every
        // success and failure advances both helper and form CSRF state.
        try { refreshCsrf(await response.clone().json()); } catch (_) {}
        return response;
    };
    // CSRF regeneration makes overlapping POSTs unsafe. Serialize transport
    // requests, while credential prompts remain independently cancellable.
    let postQueue = Promise.resolve();
    const post = (url, body) => {
        const operation = postQueue.then(() => sendPost(url, body));
        postQueue = operation.then(() => undefined, () => undefined);
        return operation;
    };
    // Parse a JSON response and rotate the cached CSRF hash if a fresh token is present.
    const postJson = async (url, body) => {
        const response = await post(url, body);
        const data = refreshCsrf(await response.json());
        if (!response.ok) { throw new Error(data.message || 'WebAuthn request failed'); }
        return data;
    };
    const cancelConditional = () => {
        if (conditionalController) { conditionalController.abort(); conditionalController = null; }
    };
    const register = async (name, conditional = false) => {
        cancelConditional();
        const opts = await postJson('<?= site_url('webauthn/register/options') ?>', { name, conditional });
        const request = { publicKey: decodeCreation(opts) };
        if (conditional) { request.mediation = 'conditional'; }
        const cred = await navigator.credentials.create(request);
        if (!cred) { return null; }
        return post('<?= site_url('webauthn/register/verify') ?>', { name, credential: encodeAttestation(cred) });
    };
    const decodeCreation = (o) => {
        o.challenge = b64urlToBuf(o.challenge);
        o.user.id = b64urlToBuf(o.user.id);
        (o.excludeCredentials || []).forEach((c) => { c.id = b64urlToBuf(c.id); });
        return o;
    };
    const decodeRequest = (o) => {
        o.challenge = b64urlToBuf(o.challenge);
        (o.allowCredentials || []).forEach((c) => { c.id = b64urlToBuf(c.id); });
        return o;
    };
    const encodeAttestation = (cred) => ({
        id: cred.id, type: cred.type, rawId: bufToB64url(cred.rawId),
        response: {
            clientDataJSON: bufToB64url(cred.response.clientDataJSON),
            attestationObject: bufToB64url(cred.response.attestationObject),
        },
        clientExtensionResults: cred.getClientExtensionResults ? cred.getClientExtensionResults() : {},
    });
    const encodeAssertion = (cred) => ({
        id: cred.id, type: cred.type, rawId: bufToB64url(cred.rawId),
        response: {
            clientDataJSON: bufToB64url(cred.response.clientDataJSON),
            authenticatorData: bufToB64url(cred.response.authenticatorData),
            signature: bufToB64url(cred.response.signature),
            userHandle: cred.response.userHandle ? bufToB64url(cred.response.userHandle) : null,
        },
        clientExtensionResults: cred.getClientExtensionResults ? cred.getClientExtensionResults() : {},
    });
    return {
        register,
        cancelConditional,
        async conditionalRegister(name = '') {
            if (!window.PublicKeyCredential || !PublicKeyCredential.getClientCapabilities) { return null; }
            const capabilities = await PublicKeyCredential.getClientCapabilities();
            if (!capabilities.conditionalCreate) { return null; }
            return register(name, true);
        },
        async conditionalLogin() {
            if (!window.PublicKeyCredential || !PublicKeyCredential.isConditionalMediationAvailable
                || !await PublicKeyCredential.isConditionalMediationAvailable()) { return null; }
            cancelConditional();
            const controller = new AbortController();
            conditionalController = controller;
            const opts = await postJson('<?= site_url('webauthn/login/options') ?>', { email: null });
            if (controller.signal.aborted) { return null; }
            const cred = await navigator.credentials.get({ publicKey: decodeRequest(opts), mediation: 'conditional', signal: controller.signal });
            if (!cred || controller.signal.aborted) { return null; }
            return post('<?= site_url('webauthn/login/verify') ?>', { credential: encodeAssertion(cred) });
        },
        async syncCredentials() {
            if (!window.PublicKeyCredential || !PublicKeyCredential.signalAllAcceptedCredentials) { return; }
            const response = await fetch('<?= site_url('webauthn/credentials/sync') ?>', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) { return; }
            const data = await response.json();
            await PublicKeyCredential.signalAllAcceptedCredentials({ rpId: data.rpId, userId: data.userId, allAcceptedCredentialIds: data.allAcceptedCredentialIds });
        },
        async login(email) {
            cancelConditional();
            const opts = await postJson('<?= site_url('webauthn/login/options') ?>', { email });
            const cred = await navigator.credentials.get({ publicKey: decodeRequest(opts) });
            return post('<?= site_url('webauthn/login/verify') ?>', { credential: encodeAssertion(cred) });
        },
        async assert(optionsUrl) {
            const opts = await postJson(optionsUrl, {});
            const cred = await navigator.credentials.get({ publicKey: decodeRequest(opts) });
            return encodeAssertion(cred);
        },
        post, postJson, bufToB64url, b64urlToBuf,
        // Expose CSRF accessors so views with their own <form> can sync the
        // hidden csrf_field() after a ceremony POST rotates the token.
        csrfHeader: () => CSRF_HEADER,
        csrfHash: () => CSRF_HASH,
    };
})();
</script>

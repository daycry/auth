<script>
// Minimal base64url <-> ArrayBuffer helpers + ceremony wrappers (vanilla JS).
window.AuthWebAuthn = (function () {
    // CI4 CSRF: send the token on every state-changing request and rotate the
    // cached hash whenever the server returns a fresh one (CI4 regenerates the
    // token per request when csrf.regenerate is enabled).
    const CSRF_HEADER = '<?= csrf_header() ?>';
    let CSRF_HASH = '<?= csrf_hash() ?>';
    const refreshCsrf = (json) => {
        if (json && typeof json.token === 'string') { CSRF_HASH = json.token; }
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
    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            [CSRF_HEADER]: CSRF_HASH,
        },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : null,
    });
    // Parse a JSON response and rotate the cached CSRF hash if a fresh token is present.
    const postJson = async (url, body) => refreshCsrf(await (await post(url, body)).json());
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
        async register(name) {
            // postJson refreshes CSRF_HASH from the options response so the
            // follow-up verify POST carries the rotated token.
            const opts = await postJson('<?= site_url('webauthn/register/options') ?>', { name });
            const cred = await navigator.credentials.create({ publicKey: decodeCreation(opts) });
            return post('<?= site_url('webauthn/register/verify') ?>', { name, credential: encodeAttestation(cred) });
        },
        async login(email) {
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

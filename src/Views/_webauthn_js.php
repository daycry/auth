<script>
// Minimal base64url <-> ArrayBuffer helpers + ceremony wrappers (vanilla JS).
window.AuthWebAuthn = (function () {
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
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : null,
    });
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
            const opts = await (await post('<?= site_url('webauthn/register/options') ?>', { name })).json();
            const cred = await navigator.credentials.create({ publicKey: decodeCreation(opts) });
            return post('<?= site_url('webauthn/register/verify') ?>', { name, credential: encodeAttestation(cred) });
        },
        async login(email) {
            const opts = await (await post('<?= site_url('webauthn/login/options') ?>', { email })).json();
            const cred = await navigator.credentials.get({ publicKey: decodeRequest(opts) });
            return post('<?= site_url('webauthn/login/verify') ?>', { credential: encodeAssertion(cred) });
        },
        async assert(optionsUrl) {
            const opts = await (await post(optionsUrl, {})).json();
            const cred = await navigator.credentials.get({ publicKey: decodeRequest(opts) });
            return encodeAssertion(cred);
        },
        bufToB64url, b64urlToBuf,
    };
})();
</script>

<?= $this->extend(setting('Auth.views')['layout']) ?>
<?= $this->section('main') ?>
<h3><?= lang('Auth.webauthn2faTitle') ?></h3>
<?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif; ?>
<form id="webauthn-2fa-form" action="<?= site_url('auth/a/verify') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="credential" id="webauthn-credential">
    <p><?= lang('Auth.webauthn2faPrompt') ?></p>
    <button type="button" id="webauthn-2fa-start"><?= lang('Auth.webauthn2faStart') ?></button>
</form>
<?= $this->include('\Daycry\Auth\Views\_webauthn_js') ?>
<script>
document.getElementById('webauthn-2fa-start').addEventListener('click', async () => {
    try {
        const assertion = await window.AuthWebAuthn.assert('<?= site_url('webauthn/2fa/options') ?>');
        document.getElementById('webauthn-credential').value = JSON.stringify(assertion);
        document.getElementById('webauthn-2fa-form').submit();
    } catch (e) { alert(e.message); }
});
</script>
<?= $this->endSection() ?>

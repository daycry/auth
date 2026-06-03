<?php
/**
 * @var list<\Daycry\Auth\Entities\WebAuthnCredential> $credentials
 */
?>
<div class="webauthn-setup">
    <h3><?= lang('Auth.webauthnSetupTitle') ?></h3>
    <ul id="webauthn-list">
        <?php foreach ($credentials as $c): ?>
            <li data-uuid="<?= esc($c->uuid) ?>">
                <?= esc($c->name ?: lang('Auth.webauthnUnnamed')) ?>
                <button type="button" class="webauthn-delete" data-uuid="<?= esc($c->uuid) ?>"><?= lang('Auth.webauthnDelete') ?></button>
            </li>
        <?php endforeach; ?>
    </ul>
    <input type="text" id="webauthn-name" placeholder="<?= lang('Auth.webauthnNamePlaceholder') ?>">
    <button type="button" id="webauthn-add"><?= lang('Auth.webauthnAdd') ?></button>
    <p id="webauthn-msg"></p>
</div>
<?= $this->include('\Daycry\Auth\Views\_webauthn_js') ?>
<script>
document.getElementById('webauthn-add').addEventListener('click', async () => {
    const name = document.getElementById('webauthn-name').value;
    try {
        const res = await window.AuthWebAuthn.register(name);
        document.getElementById('webauthn-msg').textContent = res.ok
            ? '<?= lang('Auth.webauthnRegistered') ?>' : '<?= lang('Auth.webauthnVerificationFailed') ?>';
        if (res.ok) { location.reload(); }
    } catch (e) { document.getElementById('webauthn-msg').textContent = e.message; }
});
document.querySelectorAll('.webauthn-delete').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const uuid = btn.getAttribute('data-uuid');
        await fetch('<?= site_url('webauthn/credentials') ?>/' + uuid + '/delete', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        location.reload();
    });
});
</script>

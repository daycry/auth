<?= $this->extend(setting('Auth.views')['layout'] ?? 'Daycry\Auth\Views\layout') ?>

<?= $this->section('title') ?><?= esc(lang('Auth.passwordConfirmTitle')) ?: 'Confirm your password' ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <?= esc(lang('Auth.stepUpTitle')) ?>
            </h5>

            <p class="text-muted small">
                <?= esc(lang('Auth.stepUpDescription')) ?>
            </p>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger mb-3"><?= esc(session('error')) ?></div>
            <?php endif ?>

            <?php if ($hasPassword ?? true) : ?>
            <form action="<?= url_to('password-confirm') ?>" method="post" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="factor" value="password">

                <div class="mb-3">
                    <label for="password" class="form-label">
                        <?= esc(lang('Auth.password')) ?: 'Password' ?>
                    </label>
                    <input type="password" class="form-control" id="password" name="password"
                        autocomplete="current-password" required autofocus>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <?= esc(lang('Auth.confirm')) ?: 'Confirm' ?>
                    </button>
                </div>
            </form>
            <?php endif ?>
            <?php if ($hasTotp ?? false) : ?>
                <form action="<?= url_to('password-confirm') ?>" method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="factor" value="totp">
                    <label class="form-label" for="step-up-totp"><?= esc(lang('Auth.stepUpTotp')) ?></label>
                    <input class="form-control mb-2" id="step-up-totp" name="token" inputmode="numeric"
                           autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                    <button class="btn btn-primary" type="submit"><?= esc(lang('Auth.confirm')) ?></button>
                </form>
            <?php endif ?>
            <?php if ($hasRecovery ?? false) : ?>
                <form action="<?= url_to('password-confirm') ?>" method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="factor" value="recovery">
                    <label class="form-label" for="step-up-recovery"><?= esc(lang('Auth.recoveryCode')) ?></label>
                    <input class="form-control mb-2" id="step-up-recovery" name="token" autocomplete="off" required>
                    <button class="btn btn-primary" type="submit"><?= esc(lang('Auth.confirm')) ?></button>
                </form>
            <?php endif ?>
            <?php if (($hasPasskey ?? false) && route_to('password-confirm-options') !== false) : ?>
                <form id="step-up-passkey" action="<?= url_to('password-confirm') ?>" method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="factor" value="passkey">
                    <input type="hidden" id="step-up-credential" name="credential">
                    <button type="button" id="step-up-passkey-start" class="btn btn-outline-primary"><?= esc(lang('Auth.stepUpPasskey')) ?></button>
                    <p id="step-up-passkey-error" class="text-danger" role="status" aria-live="polite"></p>
                </form>
                <?= $this->include('\Daycry\Auth\Views\_webauthn_js') ?>
                <script>
                document.getElementById('step-up-passkey-start').addEventListener('click', async () => {
                    const button = document.getElementById('step-up-passkey-start');
                    button.disabled = true;
                    try {
                        const assertion = await window.AuthWebAuthn.assert('<?= url_to('password-confirm-options') ?>');
                        document.getElementById('step-up-credential').value = JSON.stringify(assertion);
                        const form = document.getElementById('step-up-passkey');
                        form.querySelector('input[name="<?= csrf_token() ?>"]').value = window.AuthWebAuthn.csrfHash();
                        form.submit();
                    } catch (error) {
                        document.getElementById('step-up-passkey-error').textContent = error.message;
                        button.disabled = false;
                    }
                });
                </script>
            <?php endif ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

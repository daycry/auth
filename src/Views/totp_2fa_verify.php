<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.totpTitle') ?> <?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-5"><?= lang('Auth.totpTitle') ?></h5>

            <p><?= lang('Auth.totpEnterCode') ?></p>

            <?php if (session('error') !== null) : ?>
            <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php endif ?>

            <form action="<?= url_to('auth-action-verify') ?>" method="post">
                <?= csrf_field() ?>

                <!-- TOTP Code -->
                <div class="mb-2">
                    <input type="text" class="form-control" name="token" placeholder="000000"
                        inputmode="text" autocomplete="one-time-code"
                        minlength="6" maxlength="20" required autofocus>
                </div>

                <?php if ((int) (setting('AuthSecurity.trustedDeviceLifetime') ?? 0) > 0) : ?>
                    <div class="form-check small mb-3">
                        <input class="form-check-input" type="checkbox" id="trust_device" name="trust_device" value="1">
                        <label class="form-check-label" for="trust_device">
                            <?= esc(lang('Auth.trustThisDevice')) ?: 'Trust this device for 30 days' ?>
                        </label>
                    </div>
                <?php endif ?>

                <div class="d-grid col-8 mx-auto m-3">
                    <button type="submit" class="btn btn-primary btn-block"><?= lang('Auth.confirm') ?></button>
                </div>

            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

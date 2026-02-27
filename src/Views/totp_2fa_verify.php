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
                    <input type="number" class="form-control" name="token" placeholder="000000"
                        inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
                        minlength="6" maxlength="6" required autofocus>
                </div>

                <div class="d-grid col-8 mx-auto m-3">
                    <button type="submit" class="btn btn-primary btn-block"><?= lang('Auth.confirm') ?></button>
                </div>

            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

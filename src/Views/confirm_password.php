<?= $this->extend(setting('Auth.views')['layout'] ?? 'Daycry\Auth\Views\layout') ?>

<?= $this->section('title') ?><?= esc(lang('Auth.passwordConfirmTitle')) ?: 'Confirm your password' ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <?= esc(lang('Auth.passwordConfirmTitle')) ?: 'Confirm your password' ?>
            </h5>

            <p class="text-muted small">
                <?= esc(lang('Auth.passwordConfirmDescription')) ?:
                    'For your security, please confirm your password to continue.' ?>
            </p>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger mb-3"><?= esc(session('error')) ?></div>
            <?php endif ?>

            <form action="<?= url_to('password-confirm') ?>" method="post" autocomplete="off">
                <?= csrf_field() ?>

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
        </div>
    </div>
</div>
<?= $this->endSection() ?>

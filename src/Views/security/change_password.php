<?php
/**
 * Change password / set a first password (UserSecurityController::changePasswordView).
 *
 * @var bool $hasPassword Whether the account already has a usable password
 */
$title = lang($hasPassword ? 'Auth.changePasswordTitle' : 'Auth.setPasswordTitle');
?>
<?= $this->extend(setting('Auth.views')['layout'] ?? 'Daycry\Auth\Views\layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3"><?= esc($title) ?></h5>

            <?php if (! $hasPassword) : ?>
                <p class="text-muted small"><?= esc(lang('Auth.setPasswordIntro')) ?></p>
            <?php endif ?>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger mb-3"><?= esc(session('error')) ?></div>
            <?php endif ?>

            <form action="<?= url_to('security-change-password-action') ?>" method="post" autocomplete="off">
                <?= csrf_field() ?>

                <?php if ($hasPassword) : ?>
                    <div class="mb-3">
                        <label for="current_password" class="form-label"><?= esc(lang('Auth.changePasswordCurrent')) ?></label>
                        <input type="password" class="form-control" id="current_password" name="current_password"
                            autocomplete="current-password" required autofocus>
                    </div>
                <?php endif ?>

                <div class="mb-3">
                    <label for="new_password" class="form-label"><?= esc(lang('Auth.changePasswordNew')) ?></label>
                    <input type="password" class="form-control" id="new_password" name="new_password"
                        autocomplete="new-password" required>
                </div>

                <div class="mb-3">
                    <label for="new_password_confirm" class="form-label"><?= esc(lang('Auth.changePasswordConfirm')) ?></label>
                    <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm"
                        autocomplete="new-password" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <?= esc(lang($hasPassword ? 'Auth.changePasswordSubmit' : 'Auth.setPasswordSubmit')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

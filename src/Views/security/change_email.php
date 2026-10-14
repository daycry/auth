<?php
/**
 * Request an e-mail address change (UserSecurityController::changeEmailView).
 *
 * @var bool   $hasPassword  Whether to ask for the current password
 * @var string $currentEmail Current address ('' for accounts without one)
 */
?>
<?= $this->extend(setting('Auth.views')['layout'] ?? 'Daycry\Auth\Views\layout') ?>

<?= $this->section('title') ?><?= esc(lang('Auth.changeEmailTitle')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3"><?= esc(lang('Auth.changeEmailTitle')) ?></h5>

            <p class="text-muted small"><?= esc(lang('Auth.changeEmailIntro')) ?></p>

            <?php if ($currentEmail !== '') : ?>
                <p class="small"><?= esc(lang('Auth.email')) ?>: <strong><?= esc($currentEmail) ?></strong></p>
            <?php endif ?>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger mb-3"><?= esc(session('error')) ?></div>
            <?php elseif (session('message') !== null) : ?>
                <div class="alert alert-success mb-3"><?= esc(session('message')) ?></div>
            <?php endif ?>

            <form action="<?= url_to('security-change-email-action') ?>" method="post" autocomplete="off">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="new_email" class="form-label"><?= esc(lang('Auth.changeEmailLabel')) ?></label>
                    <input type="email" class="form-control" id="new_email" name="new_email" autocomplete="email" required autofocus>
                </div>

                <?php if ($hasPassword) : ?>
                    <div class="mb-3">
                        <label for="current_password" class="form-label"><?= esc(lang('Auth.changePasswordCurrent')) ?></label>
                        <input type="password" class="form-control" id="current_password" name="current_password"
                            autocomplete="current-password" required>
                    </div>
                <?php endif ?>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary"><?= esc(lang('Auth.changeEmailSubmit')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

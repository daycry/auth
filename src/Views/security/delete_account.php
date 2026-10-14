<?php
/**
 * Account deletion (UserSecurityController::deleteAccountView).
 *
 * @var bool $hasPassword Password re-auth (immediate deletion)
 * @var bool $hasEmail    Password-less accounts confirm through an e-mailed link
 */
?>
<?= $this->extend(setting('Auth.views')['layout'] ?? 'Daycry\Auth\Views\layout') ?>

<?= $this->section('title') ?><?= esc(lang('Auth.deleteAccountTitle')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm border-danger">
        <div class="card-body">
            <h5 class="card-title mb-3 text-danger"><?= esc(lang('Auth.deleteAccountTitle')) ?></h5>

            <p><?= esc(lang('Auth.deleteAccountIntro')) ?></p>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger mb-3"><?= esc(session('error')) ?></div>
            <?php elseif (session('message') !== null) : ?>
                <div class="alert alert-success mb-3"><?= esc(session('message')) ?></div>
            <?php endif ?>

            <?php if ($hasPassword || $hasEmail) : ?>
                <form action="<?= url_to('security-delete-account-action') ?>" method="post" autocomplete="off">
                    <?= csrf_field() ?>

                    <?php if ($hasPassword) : ?>
                        <div class="mb-3">
                            <label for="current_password" class="form-label"><?= esc(lang('Auth.changePasswordCurrent')) ?></label>
                            <input type="password" class="form-control" id="current_password" name="current_password"
                                autocomplete="current-password" required autofocus>
                        </div>
                    <?php endif ?>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger"><?= esc(lang('Auth.deleteAccountSubmit')) ?></button>
                    </div>
                </form>
            <?php else : ?>
                <div class="alert alert-warning mb-0"><?= esc(lang('Auth.deleteAccountNeedsEmail')) ?></div>
            <?php endif ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

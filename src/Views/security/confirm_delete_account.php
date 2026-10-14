<?php
/**
 * Confirmation step for an e-mailed deletion link (UserSecurityController::confirmDeleteAccountView).
 * GET renders this page; only the POST below deletes the account.
 *
 * @var string $token Raw confirmation token from the link
 */
?>
<?= $this->extend(setting('Auth.views')['layout'] ?? 'Daycry\Auth\Views\layout') ?>

<?= $this->section('title') ?><?= esc(lang('Auth.deleteAccountConfirmTitle')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm border-danger">
        <div class="card-body">
            <h5 class="card-title mb-3 text-danger"><?= esc(lang('Auth.deleteAccountConfirmTitle')) ?></h5>

            <p><?= esc(lang('Auth.deleteAccountConfirmIntro')) ?></p>

            <form action="<?= url_to('security-confirm-delete-action') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">

                <div class="d-grid">
                    <button type="submit" class="btn btn-danger"><?= esc(lang('Auth.deleteAccountButton')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

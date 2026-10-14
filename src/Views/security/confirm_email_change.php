<?php
/**
 * Confirmation step for an e-mailed change link (UserSecurityController::confirmEmailChangeView).
 * GET renders this page; only the POST below applies the change.
 *
 * @var string $token    Raw confirmation token from the link
 * @var string $newEmail Address that will become the login e-mail
 */
?>
<?= $this->extend(setting('Auth.views')['layout'] ?? 'Daycry\Auth\Views\layout') ?>

<?= $this->section('title') ?><?= esc(lang('Auth.changeEmailConfirmTitle')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3"><?= esc(lang('Auth.changeEmailConfirmTitle')) ?></h5>

            <p><?= esc(lang('Auth.changeEmailConfirmIntro', [$newEmail])) ?></p>

            <form action="<?= url_to('security-confirm-email-action') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary"><?= esc(lang('Auth.changeEmailButton')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

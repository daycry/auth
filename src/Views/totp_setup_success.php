<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.totpSetupTitle') ?> <?= $this->endSection() ?>

<?= $this->section('main') ?>

<?php
/** @var list<string> $backupCodes */
$backupCodes = $backupCodes ?? [];
?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-7 shadow-sm">
        <div class="card-body">
            <div class="text-center mb-4" style="font-size: 3rem;">&#10003;</div>
            <h5 class="card-title text-center mb-3"><?= lang('Auth.totpSetupSuccess') ?></h5>
            <p class="text-muted text-center"><?= lang('Auth.totpSetupSuccessDetail') ?></p>

            <?php if ($backupCodes !== []) : ?>
                <hr>
                <h6 class="mt-4"><?= esc(lang('Auth.totpBackupCodesTitle')) ?: 'Backup codes' ?></h6>
                <p class="small text-muted">
                    <?= esc(lang('Auth.totpBackupCodesDescription')) ?: 'Save these one-time codes somewhere safe. Each can be used once if you lose access to your authenticator app. They will not be shown again.' ?>
                </p>
                <div class="bg-light border rounded p-3 mb-3">
                    <div class="row">
                        <?php foreach ($backupCodes as $code) : ?>
                            <div class="col-6 mb-1">
                                <code class="user-select-all"><?= esc($code) ?></code>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
                <div class="alert alert-warning small mb-3">
                    <?= esc(lang('Auth.totpBackupCodesWarning')) ?: 'Store these codes in a password manager or print them. They are equivalent to your password — anyone with one can sign in.' ?>
                </div>
            <?php endif ?>

            <div class="text-center">
                <a href="<?= esc($redirectUrl ?? '/') ?>" class="btn btn-primary mt-3">
                    <?= lang('Auth.totpSetupContinue') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

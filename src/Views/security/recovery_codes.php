<?= $this->extend(setting('Auth.views')['layout']) ?>
<?= $this->section('title') ?><?= esc(lang('Auth.totpBackupCodesTitle')) ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="container py-5">
    <h2><?= esc(lang('Auth.totpBackupCodesTitle')) ?></h2>
    <?php if (($backupCodes ?? []) !== []) : ?>
        <p><?= esc(lang('Auth.totpBackupCodesDescription')) ?></p>
        <ul class="list-unstyled bg-light border rounded p-3">
            <?php foreach ($backupCodes as $code) : ?>
                <li><code class="user-select-all"><?= esc($code) ?></code></li>
            <?php endforeach ?>
        </ul>
        <p class="alert alert-warning"><?= esc(lang('Auth.totpBackupCodesWarning')) ?></p>
    <?php else : ?>
        <p><?= esc(lang('Auth.recoveryCodesRegenerateDescription')) ?></p>
        <form action="<?= url_to('security-regenerate-recovery-codes') ?>" method="post">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit"><?= esc(lang('Auth.recoveryCodesRegenerate')) ?></button>
        </form>
    <?php endif ?>
    <a href="<?= url_to('security') ?>" class="btn btn-link mt-3"><?= esc(lang('Auth.totpSetupContinue')) ?></a>
</div>
<?= $this->endSection() ?>

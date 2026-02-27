<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.totpSetupTitle') ?> <?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body text-center">
            <div class="mb-4" style="font-size: 3rem;">&#10003;</div>
            <h5 class="card-title mb-3"><?= lang('Auth.totpSetupSuccess') ?></h5>
            <p class="text-muted"><?= lang('Auth.totpSetupSuccessDetail') ?></p>
            <a href="<?= esc($redirectUrl ?? '/') ?>" class="btn btn-primary mt-3">
                <?= lang('Auth.totpSetupContinue') ?>
            </a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

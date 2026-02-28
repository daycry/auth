<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.passwordResetTitle') ?> <?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-5"><?= lang('Auth.passwordResetTitle') ?></h5>

            <p><b><?= lang('Auth.checkYourEmail') ?></b></p>

            <p><?= lang('Auth.passwordResetSent') ?></p>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

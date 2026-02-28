<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.passwordResetFormTitle') ?> <?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-5"><?= lang('Auth.passwordResetFormTitle') ?></h5>

                <?php if (session('error') !== null) : ?>
                    <div class="alert alert-danger" role="alert"><?= session('error') ?></div>
                <?php elseif (session('errors') !== null) : ?>
                    <div class="alert alert-danger" role="alert">
                        <?php if (is_array(session('errors'))) : ?>
                            <?php foreach (session('errors') as $error) : ?>
                                <?= $error ?>
                                <br>
                            <?php endforeach ?>
                        <?php else : ?>
                            <?= session('errors') ?>
                        <?php endif ?>
                    </div>
                <?php endif ?>

            <p><?= lang('Auth.passwordResetFormIntro') ?></p>

            <form action="<?= site_url('password-reset/verify') ?>" method="post">
                <?= csrf_field() ?>

                <input type="hidden" name="token" value="<?= esc($token) ?>">

                <!-- New Password -->
                <div class="form-floating mb-2">
                    <input type="password" class="form-control" id="floatingPasswordInput" name="password" autocomplete="new-password" placeholder="<?= lang('Auth.passwordResetNewPassword') ?>" required>
                    <label for="floatingPasswordInput"><?= lang('Auth.passwordResetNewPassword') ?></label>
                </div>

                <!-- Confirm Password -->
                <div class="form-floating mb-2">
                    <input type="password" class="form-control" id="floatingPasswordConfirmInput" name="password_confirm" autocomplete="new-password" placeholder="<?= lang('Auth.passwordResetConfirm') ?>" required>
                    <label for="floatingPasswordConfirmInput"><?= lang('Auth.passwordResetConfirm') ?></label>
                </div>

                <div class="d-grid col-12 col-md-8 mx-auto m-3">
                    <button type="submit" class="btn btn-primary btn-block"><?= lang('Auth.passwordResetSubmit') ?></button>
                </div>

            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.forceResetTitle') ?> <?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-5"><?= lang('Auth.forceResetTitle') ?></h5>

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

            <p><?= lang('Auth.forceResetIntro') ?></p>

            <form action="<?= url_to('force-reset') ?>" method="post">
                <?= csrf_field() ?>

                <!-- Current Password -->
                <div class="form-floating mb-2">
                    <input type="password" class="form-control" id="floatingCurrentPasswordInput" name="current_password" autocomplete="current-password" placeholder="<?= lang('Auth.forceResetCurrentLabel') ?>" required>
                    <label for="floatingCurrentPasswordInput"><?= lang('Auth.forceResetCurrentLabel') ?></label>
                </div>

                <!-- New Password -->
                <div class="form-floating mb-2">
                    <input type="password" class="form-control" id="floatingNewPasswordInput" name="new_password" autocomplete="new-password" placeholder="<?= lang('Auth.forceResetNewLabel') ?>" required>
                    <label for="floatingNewPasswordInput"><?= lang('Auth.forceResetNewLabel') ?></label>
                </div>

                <!-- Confirm New Password -->
                <div class="form-floating mb-2">
                    <input type="password" class="form-control" id="floatingNewPasswordConfirmInput" name="new_password_confirm" autocomplete="new-password" placeholder="<?= lang('Auth.forceResetConfirmLabel') ?>" required>
                    <label for="floatingNewPasswordConfirmInput"><?= lang('Auth.forceResetConfirmLabel') ?></label>
                </div>

                <div class="d-grid col-12 col-md-8 mx-auto m-3">
                    <button type="submit" class="btn btn-primary btn-block"><?= lang('Auth.forceResetSubmit') ?></button>
                </div>

            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.login') ?> <?= $this->endSection() ?>

<?= $this->section('main') ?>

    <div class="container d-flex justify-content-center p-5">
        <div class="card col-12 col-md-5 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-5"><?= lang('Auth.login') ?></h5>

                <?php if (session('error') !== null) : ?>
                    <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
                <?php elseif (session('errors') !== null) : ?>
                    <div class="alert alert-danger" role="alert">
                        <?php if (is_array(session('errors'))) : ?>
                            <?php foreach (session('errors') as $error) : ?>
                                <?= esc($error) ?>
                                <br>
                            <?php endforeach ?>
                        <?php else : ?>
                            <?= esc(session('errors')) ?>
                        <?php endif ?>
                    </div>
                <?php endif ?>

                <?php if (session('message') !== null) : ?>
                    <div class="alert alert-success" role="alert"><?= esc(session('message')) ?></div>
                <?php endif ?>

                <form action="<?= url_to('login') ?>" method="post">
                    <?= csrf_field() ?>

                    <!-- Email -->
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="floatingEmailInput" name="email" inputmode="email" autocomplete="email" placeholder="<?= lang('Auth.email') ?>" value="<?= old('email') ?>" required>
                        <label for="floatingEmailInput"><?= lang('Auth.email') ?></label>
                    </div>

                    <!-- Password -->
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="floatingPasswordInput" name="password" inputmode="text" autocomplete="current-password" placeholder="<?= lang('Auth.password') ?>" required>
                        <label for="floatingPasswordInput"><?= lang('Auth.password') ?></label>
                    </div>

                    <!-- Remember me -->
                    <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" name="remember" class="form-check-input" <?php if (old('remember')): ?> checked<?php endif ?>>
                                <?= lang('Auth.rememberMe') ?>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid col-12 col-md-8 mx-auto m-3">
                        <button type="submit" class="btn btn-primary btn-block"><?= lang('Auth.login') ?></button>
                    </div>

                    <?php if (setting('AuthSecurity.webauthnEnabled')) : ?>
                        <div class="d-grid col-12 col-md-8 mx-auto m-3">
                            <button type="button" id="webauthn-login" class="btn btn-outline-secondary btn-block"><?= lang('Auth.webauthn2faStart') ?></button>
                        </div>
                        <p id="webauthn-login-msg" class="text-center text-danger"></p>
                    <?php endif ?>

                    <?php if (setting('AuthSecurity.allowMagicLinkLogins')) : ?>
                        <p class="text-center"><?= lang('Auth.forgotPassword') ?> <a href="<?= url_to('magic-link') ?>"><?= lang('Auth.useMagicLink') ?></a></p>
                    <?php endif ?>

                    <?php if (setting('Auth.allowRegistration')) : ?>
                        <p class="text-center"><?= lang('Auth.needAccount') ?> <a href="<?= url_to('register') ?>"><?= lang('Auth.register') ?></a></p>
                    <?php endif ?>

                </form>
            </div>
        </div>
    </div>

    <?php if (setting('AuthSecurity.webauthnEnabled')) : ?>
        <?= $this->include('\Daycry\Auth\Views\_webauthn_js') ?>
        <script>
        document.getElementById('webauthn-login').addEventListener('click', async () => {
            const msg = document.getElementById('webauthn-login-msg');
            msg.textContent = '';
            try {
                // Usernameless (discoverable) sign-in: the optional email field
                // narrows allowCredentials when present, but is not required.
                const email = document.getElementById('floatingEmailInput').value || null;
                const res = await window.AuthWebAuthn.login(email);
                const data = await res.json();
                if (res.ok && data.redirect) {
                    window.location = data.redirect;
                } else {
                    msg.textContent = data.message || '<?= lang('Auth.webauthnVerificationFailed') ?>';
                }
            } catch (e) {
                msg.textContent = e.message;
            }
        });
        </script>
    <?php endif ?>

<?= $this->endSection() ?>

<?= $this->extend(setting('Auth.views')['layout']) ?>
<?= $this->section('main') ?>

<h2><?= lang('Auth.magicCodeTitle') ?></h2>

<?php if (session('error')): ?>
    <div class="alert alert-danger"><?= esc(session('error')) ?></div>
<?php endif; ?>

<p><?= lang('Auth.magicCodePrompt') ?></p>

<form action="<?= site_url('login/magic-link/code') ?>" method="post">
    <?= csrf_field() ?>
    <input type="text" name="token" inputmode="numeric" autocomplete="one-time-code"
           pattern="[0-9]*" maxlength="6" required autofocus>
    <button type="submit"><?= lang('Auth.magicCodeSubmit') ?></button>
</form>

<form action="<?= site_url('login/magic-link') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="delivery" value="code">
    <input type="hidden" name="email" value="<?= esc(session('magicCodeEmail')) ?>">
    <button type="submit"><?= lang('Auth.magicCodeResend') ?></button>
</form>

<?= $this->endSection() ?>

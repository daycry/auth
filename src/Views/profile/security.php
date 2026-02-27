<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Account Security<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var list<\Daycry\Auth\Entities\DeviceSession> $sessions
 * @var string                                     $currentSid
 * @var bool                                       $totpEnabled
 */
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">

            <h2 class="mb-4 fw-bold">
                <i class="bi bi-shield-lock me-2"></i>Account Security
            </h2>

            <?php if (session('message') !== null) : ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= esc(session('message')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif ?>
            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= esc(session('error')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif ?>

            <!-- ── TOTP 2FA ──────────────────────────────────────────────── -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-phone fs-5"></i>
                    <span class="fw-semibold">Two-Factor Authentication (TOTP)</span>
                    <?php if ($totpEnabled) : ?>
                        <span class="badge bg-success ms-auto">Enabled</span>
                    <?php else : ?>
                        <span class="badge bg-secondary ms-auto">Disabled</span>
                    <?php endif ?>
                </div>
                <div class="card-body">
                    <?php if ($totpEnabled) : ?>
                        <p class="text-muted mb-3">
                            Your account is protected with a time-based one-time password.
                            Enter your current authenticator code below to disable it.
                        </p>
                        <form action="<?= url_to('totp-disable') ?>" method="post"
                              onsubmit="return confirm('Disable two-factor authentication?')">
                            <?= csrf_field() ?>
                            <div class="input-group" style="max-width:280px">
                                <input type="number" class="form-control" name="token"
                                       placeholder="000000" inputmode="numeric"
                                       minlength="6" maxlength="6" required>
                                <button class="btn btn-outline-danger" type="submit">Disable TOTP</button>
                            </div>
                        </form>
                    <?php else : ?>
                        <p class="text-muted mb-3">
                            Add an extra layer of security using Google Authenticator, Authy, or any TOTP app.
                        </p>
                        <a href="<?= url_to('totp-setup') ?>" class="btn btn-primary">
                            <i class="bi bi-qr-code me-1"></i> Set Up TOTP
                        </a>
                    <?php endif ?>
                </div>
            </div>

            <!-- ── Active Sessions ───────────────────────────────────────── -->
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-laptop fs-5"></i>
                    <span class="fw-semibold">Active Sessions</span>
                    <span class="badge bg-primary ms-auto"><?= count($sessions) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if ($sessions === []) : ?>
                        <p class="text-muted p-4 mb-0">No active sessions tracked.</p>
                    <?php else : ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($sessions as $session) : ?>
                                <?php $isCurrent = ($session->session_id === $currentSid) ?>
                                <li class="list-group-item d-flex align-items-center gap-3 py-3">
                                    <i class="bi bi-display fs-4 text-secondary"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">
                                            <?= esc($session->getDeviceLabel()) ?>
                                            <?php if ($isCurrent) : ?>
                                                <span class="badge bg-success ms-1">Current</span>
                                            <?php endif ?>
                                        </div>
                                        <small class="text-muted">
                                            IP: <?= esc($session->ip_address) ?> &nbsp;&bull;&nbsp;
                                            Last active: <?= esc($session->last_active->humanize()) ?>
                                        </small>
                                    </div>
                                    <?php if (! $isCurrent) : ?>
                                        <form action="<?= url_to('security-revoke-session') ?>" method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="session_id" value="<?= esc($session->session_id) ?>">
                                            <button class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Revoke this session?')">
                                                <i class="bi bi-x-circle"></i> Revoke
                                            </button>
                                        </form>
                                    <?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ul>
                        <?php if (count($sessions) > 1) : ?>
                            <div class="p-3 border-top text-end">
                                <form action="<?= url_to('security-revoke-all') ?>" method="post"
                                      onsubmit="return confirm('Revoke all other sessions?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger btn-sm">
                                        <i class="bi bi-x-octagon me-1"></i> Revoke All Other Sessions
                                    </button>
                                </form>
                            </div>
                        <?php endif ?>
                    <?php endif ?>
                </div>
            </div>

        </div>
    </div>
</div>
<?= $this->endSection() ?>

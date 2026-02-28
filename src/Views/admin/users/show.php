<?= $this->extend('Daycry\Auth\Views\admin\layout') ?>

<?= $this->section('title') ?>User #<?= $user->id ?><?= $this->endSection() ?>
<?= $this->section('pageTitle') ?>User: <?= esc($user->username ?? $user->email ?? '#' . $user->id) ?><?= $this->endSection() ?>

<?= $this->section('topbarActions') ?>
<a href="<?= url_to('admin-user-edit', $user->id) ?>" class="btn btn-sm btn-primary">
    <i class="bi bi-pencil me-1"></i>Edit
</a>
<a href="<?= url_to('admin-users') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back
</a>
<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var \Daycry\Auth\Entities\User             $user
 * @var list<\Daycry\Auth\Entities\DeviceSession> $sessions
 */
$groups  = $user->getGroups();
$perms   = $user->getPermissions();
?>

<div class="row g-3">

    <!-- ── Left column: info + actions ──────────────────────────── -->
    <div class="col-lg-4">

        <!-- Info card -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-person me-2"></i>Account Info</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">ID</span>
                    <span><?= $user->id ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Username</span>
                    <span><?= esc($user->username ?? '—') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Email</span>
                    <span><?= esc($user->email ?? '—') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Active</span>
                    <?php if ($user->active) : ?>
                        <span class="badge bg-success">Yes</span>
                    <?php else : ?>
                        <span class="badge bg-secondary">No</span>
                    <?php endif ?>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Banned</span>
                    <?php if ($user->isBanned()) : ?>
                        <span class="badge bg-danger">Yes</span>
                    <?php else : ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">No</span>
                    <?php endif ?>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">TOTP 2FA</span>
                    <?php if ($user->hasTotpEnabled()) : ?>
                        <span class="badge bg-success">Enabled</span>
                    <?php else : ?>
                        <span class="badge bg-secondary">Disabled</span>
                    <?php endif ?>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Last Active</span>
                    <span class="small"><?= $user->last_active ? esc($user->last_active->humanize()) : 'Never' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Registered</span>
                    <span class="small"><?= $user->created_at ? esc($user->created_at->humanize()) : '—' ?></span>
                </li>
            </ul>
        </div>

        <!-- Quick-action buttons -->
        <div class="card shadow-sm">
            <div class="card-header fw-semibold"><i class="bi bi-lightning me-2"></i>Quick Actions</div>
            <div class="card-body d-flex flex-column gap-2">

                <?php if (! $user->active) : ?>
                    <form action="<?= url_to('admin-user-activate', $user->id) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="btn btn-success btn-sm w-100" type="submit">
                            <i class="bi bi-check-circle me-1"></i>Activate Account
                        </button>
                    </form>
                <?php endif ?>

                <?php if ($user->isBanned()) : ?>
                    <form action="<?= url_to('admin-user-unban', $user->id) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-success btn-sm w-100" type="submit">
                            <i class="bi bi-unlock me-1"></i>Remove Ban
                        </button>
                    </form>
                <?php else : ?>
                    <form action="<?= url_to('admin-user-ban', $user->id) ?>" method="post"
                          onsubmit="return confirm('Ban this user?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reason" value="Admin action">
                        <button class="btn btn-warning btn-sm w-100" type="submit">
                            <i class="bi bi-slash-circle me-1"></i>Ban User
                        </button>
                    </form>
                <?php endif ?>

                <form action="<?= url_to('admin-user-delete', $user->id) ?>" method="post"
                      onsubmit="return confirm('Permanently delete this user? This cannot be undone.')">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger btn-sm w-100" type="submit">
                        <i class="bi bi-trash me-1"></i>Delete User
                    </button>
                </form>

            </div>
        </div>

    </div>

    <!-- ── Right column: groups, perms, sessions ────────────────── -->
    <div class="col-lg-8">

        <!-- Groups -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold d-flex align-items-center">
                <i class="bi bi-collection me-2"></i>Groups
                <span class="badge bg-primary ms-auto"><?= count($groups) ?></span>
            </div>
            <div class="card-body">
                <?php if ($groups === []) : ?>
                    <span class="text-muted small">No groups assigned.</span>
                <?php else : ?>
                    <?php foreach ($groups as $g) : ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1 mb-1">
                            <?= esc($g) ?>
                        </span>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>

        <!-- Permissions -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold d-flex align-items-center">
                <i class="bi bi-key me-2"></i>Direct Permissions
                <span class="badge bg-secondary ms-auto"><?= count($perms) ?></span>
            </div>
            <div class="card-body">
                <?php if ($perms === []) : ?>
                    <span class="text-muted small">No direct permissions.</span>
                <?php else : ?>
                    <?php foreach ($perms as $p) : ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle me-1 mb-1">
                            <?= esc($p) ?>
                        </span>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>

        <!-- Device sessions -->
        <div class="card shadow-sm">
            <div class="card-header fw-semibold d-flex align-items-center">
                <i class="bi bi-laptop me-2"></i>Device Sessions
                <span class="badge bg-info ms-auto"><?= count($sessions) ?></span>
            </div>
            <?php if ($sessions === []) : ?>
                <div class="card-body text-muted small">No active sessions.</div>
            <?php else : ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($sessions as $s) : ?>
                        <li class="list-group-item d-flex align-items-center gap-2 py-2">
                            <i class="bi bi-display text-secondary"></i>
                            <div class="flex-grow-1">
                                <div class="small fw-semibold"><?= esc($s->getDeviceLabel()) ?></div>
                                <div class="text-muted" style="font-size:.78rem">
                                    IP: <?= esc($s->ip_address) ?> &bull;
                                    <?= $s->isActive() ? 'Active' : 'Logged out' ?> &bull;
                                    <?= esc($s->last_active->humanize()) ?>
                                </div>
                            </div>
                            <?php if ($s->isActive()) : ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                            <?php else : ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Ended</span>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>

    </div>
</div>
<?= $this->endSection() ?>

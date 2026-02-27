<?= $this->extend('Daycry\Auth\Views\admin\layout') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>
<?= $this->section('pageTitle') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var int                                          $totalUsers
 * @var int                                          $activeUsers
 * @var int                                          $totalGroups
 * @var int                                          $totalPermissions
 * @var list<\Daycry\Auth\Entities\Log>              $recentLogins
 */
?>

<!-- ── Stat cards ───────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-wrap bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalUsers ?></div>
                    <div class="text-muted small">Total Users</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-wrap bg-success bg-opacity-10 text-success">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $activeUsers ?></div>
                    <div class="text-muted small">Active Users</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-wrap bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-collection-fill"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalGroups ?></div>
                    <div class="text-muted small">Groups / Roles</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-wrap bg-info bg-opacity-10 text-info">
                    <i class="bi bi-key-fill"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalPermissions ?></div>
                    <div class="text-muted small">Permissions</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent login attempts ────────────────────────────────── -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center">
        <i class="bi bi-clock-history me-2 text-muted"></i>
        <span class="fw-semibold">Recent Login Attempts</span>
        <a href="<?= url_to('admin-logs') ?>" class="btn btn-sm btn-outline-secondary ms-auto">
            View all logs
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>User / Email</th>
                <th>IP Address</th>
                <th>Result</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($recentLogins === []) : ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No login attempts recorded.</td></tr>
            <?php else : ?>
                <?php foreach ($recentLogins as $login) : ?>
                    <tr>
                        <td><?= esc($login->identifier ?? '—') ?></td>
                        <td><code><?= esc($login->ip_address ?? '—') ?></code></td>
                        <td>
                            <?php if ($login->success) : ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Success</span>
                            <?php else : ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                            <?php endif ?>
                        </td>
                        <td class="text-muted small">
                            <?= isset($login->date) ? esc($login->date->humanize()) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

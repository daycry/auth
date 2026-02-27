<?= $this->extend('Daycry\Auth\Views\admin\layout') ?>

<?= $this->section('title') ?>Users<?= $this->endSection() ?>
<?= $this->section('pageTitle') ?>Users<?= $this->endSection() ?>

<?= $this->section('topbarActions') ?>
<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var list<\Daycry\Auth\Entities\User>     $users
 * @var \CodeIgniter\Pager\Pager|null        $pager
 * @var string                               $search
 */
?>

<!-- ── Search bar ─────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" class="d-flex gap-2">
            <input type="search" name="q" value="<?= esc($search) ?>"
                   class="form-control form-control-sm" placeholder="Search username or status…"
                   style="max-width:300px">
            <button class="btn btn-sm btn-primary" type="submit">
                <i class="bi bi-search"></i> Search
            </button>
            <?php if ($search !== '') : ?>
                <a href="<?= url_to('admin-users') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif ?>
        </form>
    </div>
</div>

<!-- ── User table ─────────────────────────────────────────────── -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Username</th>
                <th>Email</th>
                <th>Status</th>
                <th>Active</th>
                <th>Last Active</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($users === []) : ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No users found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td class="text-muted small"><?= $user->id ?></td>
                        <td>
                            <a href="<?= url_to('admin-user-show', $user->id) ?>" class="fw-semibold text-decoration-none">
                                <?= esc($user->username ?? '—') ?>
                            </a>
                        </td>
                        <td class="text-muted small"><?= esc($user->email ?? '—') ?></td>
                        <td>
                            <?php if ($user->isBanned()) : ?>
                                <span class="badge bg-danger">Banned</span>
                            <?php else : ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">OK</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if ($user->active) : ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else : ?>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            <?php endif ?>
                        </td>
                        <td class="text-muted small">
                            <?= $user->last_active ? esc($user->last_active->humanize()) : 'Never' ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url_to('admin-user-show', $user->id) ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="<?= url_to('admin-user-edit', $user->id) ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager !== null) : ?>
        <div class="card-footer">
            <?= $pager->links() ?>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>

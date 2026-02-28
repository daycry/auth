<?= $this->extend('Daycry\Auth\Views\admin\layout') ?>

<?= $this->section('title') ?>Permissions<?= $this->endSection() ?>
<?= $this->section('pageTitle') ?>Permissions<?= $this->endSection() ?>

<?= $this->section('topbarActions') ?>
<a href="<?= url_to('admin-permission-create') ?>" class="btn btn-sm btn-primary">
    <i class="bi bi-plus-lg me-1"></i>New Permission
</a>
<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var list<\Daycry\Auth\Entities\Permission> $permissions
 */
?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($permissions === []) : ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No permissions defined yet.</td></tr>
            <?php else : ?>
                <?php foreach ($permissions as $perm) : ?>
                    <tr>
                        <td class="text-muted small"><?= $perm->id ?></td>
                        <td>
                            <code class="small"><?= esc($perm->name) ?></code>
                        </td>
                        <td class="text-muted small">
                            <?= $perm->created_at ? esc($perm->created_at->toDateString()) : '—' ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url_to('admin-permission-edit', $perm->id) ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="<?= url_to('admin-permission-delete', $perm->id) ?>" method="post"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete permission <?= esc($perm->name, 'js') ?>?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

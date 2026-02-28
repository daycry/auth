<?= $this->extend('Daycry\Auth\Views\admin\layout') ?>

<?= $this->section('title') ?>Groups<?= $this->endSection() ?>
<?= $this->section('pageTitle') ?>Groups / Roles<?= $this->endSection() ?>

<?= $this->section('topbarActions') ?>
<a href="<?= url_to('admin-group-create') ?>" class="btn btn-sm btn-primary">
    <i class="bi bi-plus-lg me-1"></i>New Group
</a>
<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var list<\Daycry\Auth\Entities\Group> $groups
 * @var array<int|string, int>            $counts  group_id => member_count
 */
?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th class="text-center">Members</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($groups === []) : ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No groups yet.</td></tr>
            <?php else : ?>
                <?php foreach ($groups as $group) : ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($group->name) ?></td>
                        <td class="text-muted small"><?= esc($group->description ?? '—') ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <?= $counts[$group->id] ?? 0 ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="<?= url_to('admin-group-edit', $group->id) ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="<?= url_to('admin-group-delete', $group->id) ?>" method="post"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete group <?= esc($group->name, 'js') ?>?')">
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

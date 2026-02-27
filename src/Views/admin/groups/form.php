<?= $this->extend('Daycry\Auth\Views\admin\layout') ?>

<?php $isEdit = $group !== null ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit Group' : 'New Group' ?><?= $this->endSection() ?>
<?= $this->section('pageTitle') ?><?= $isEdit ? 'Edit Group: ' . esc($group->name) : 'New Group' ?><?= $this->endSection() ?>

<?= $this->section('topbarActions') ?>
<a href="<?= url_to('admin-groups') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back
</a>
<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var \Daycry\Auth\Entities\Group|null        $group
 * @var list<\Daycry\Auth\Entities\Permission>  $allPerms
 * @var list<int>                               $assigned  permission IDs assigned to this group
 */
$action = $isEdit
    ? url_to('admin-group-update', $group->id)
    : url_to('admin-group-store');
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <form action="<?= $action ?>" method="post">
            <?= csrf_field() ?>

            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">
                    <i class="bi bi-collection me-2"></i><?= $isEdit ? 'Edit Group' : 'Create Group' ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <?php if ($isEdit) : ?>
                            <input type="text" class="form-control" value="<?= esc($group->name) ?>" disabled>
                            <div class="form-text">Group name cannot be changed after creation.</div>
                        <?php else : ?>
                            <input type="text" name="name" class="form-control"
                                   value="<?= esc(old('name') ?? '') ?>" required
                                   placeholder="e.g. admin, editor, moderator">
                        <?php endif ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control"
                               value="<?= esc($isEdit ? ($group->description ?? '') : (old('description') ?? '')) ?>"
                               placeholder="Optional description">
                    </div>
                </div>
            </div>

            <?php if ($allPerms !== []) : ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header fw-semibold"><i class="bi bi-key me-2"></i>Permissions</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php foreach ($allPerms as $perm) : ?>
                                <div class="col-sm-6">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input"
                                               name="permissions[]" value="<?= $perm->id ?>"
                                               id="perm_<?= $perm->id ?>"
                                               <?= in_array($perm->id, $assigned, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="perm_<?= $perm->id ?>">
                                            <?= esc($perm->name) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
            <?php endif ?>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url_to('admin-groups') ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Group' ?>
                </button>
            </div>

        </form>
    </div>
</div>
<?= $this->endSection() ?>

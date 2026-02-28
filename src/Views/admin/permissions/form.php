<?= $this->extend('Daycry\Auth\Views\admin\layout') ?>

<?php $isEdit = $permission !== null ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit Permission' : 'New Permission' ?><?= $this->endSection() ?>
<?= $this->section('pageTitle') ?><?= $isEdit ? 'Edit Permission' : 'New Permission' ?><?= $this->endSection() ?>

<?= $this->section('topbarActions') ?>
<a href="<?= url_to('admin-permissions') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back
</a>
<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var \Daycry\Auth\Entities\Permission|null $permission
 */
$action = $isEdit
    ? url_to('admin-permission-update', $permission->id)
    : url_to('admin-permission-store');
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">
                <i class="bi bi-key me-2"></i><?= $isEdit ? 'Edit Permission' : 'New Permission' ?>
            </div>
            <div class="card-body">
                <form action="<?= $action ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="mb-4">
                        <label class="form-label">Permission Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               value="<?= esc($isEdit ? $permission->name : (old('name') ?? '')) ?>"
                               required placeholder="e.g. admin.access, users.edit, posts.delete">
                        <div class="form-text">Use dot-notation for namespacing (e.g. <code>resource.action</code>).</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url_to('admin-permissions') ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Permission' ?>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

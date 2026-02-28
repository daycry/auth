<?= $this->extend('Daycry\Auth\Views\admin\layout') ?>

<?= $this->section('title') ?>Edit User #<?= $user->id ?><?= $this->endSection() ?>
<?= $this->section('pageTitle') ?>Edit: <?= esc($user->username ?? $user->email ?? '#' . $user->id) ?><?= $this->endSection() ?>

<?= $this->section('topbarActions') ?>
<a href="<?= url_to('admin-user-show', $user->id) ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back
</a>
<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var \Daycry\Auth\Entities\User      $user
 * @var list<\Daycry\Auth\Entities\Group>      $allGroups
 * @var list<\Daycry\Auth\Entities\Permission> $allPerms
 */
$currentGroups = $user->getGroups();
$currentPerms  = $user->getPermissions();
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form action="<?= url_to('admin-user-update', $user->id) ?>" method="post">
            <?= csrf_field() ?>

            <!-- ── Basic info ───────────────────────────────────── -->
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-person me-2"></i>Basic Info</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control"
                               value="<?= esc($user->username ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= esc($user->email ?? '') ?>">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="active" value="1" id="active" class="form-check-input"
                               <?= $user->active ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Account is active</label>
                    </div>
                </div>
            </div>

            <!-- ── Groups ────────────────────────────────────────── -->
            <?php if ($allGroups !== []) : ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header fw-semibold"><i class="bi bi-collection me-2"></i>Groups / Roles</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php foreach ($allGroups as $group) : ?>
                                <div class="col-sm-4 col-md-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input"
                                               name="groups[]" value="<?= esc($group->name) ?>"
                                               id="grp_<?= $group->id ?>"
                                               <?= in_array($group->name, $currentGroups, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="grp_<?= $group->id ?>">
                                            <?= esc($group->name) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
            <?php endif ?>

            <!-- ── Permissions ───────────────────────────────────── -->
            <?php if ($allPerms !== []) : ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header fw-semibold"><i class="bi bi-key me-2"></i>Direct Permissions</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php foreach ($allPerms as $perm) : ?>
                                <div class="col-sm-6 col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input"
                                               name="permissions[]" value="<?= esc($perm->name) ?>"
                                               id="perm_<?= $perm->id ?>"
                                               <?= in_array($perm->name, $currentPerms, true) ? 'checked' : '' ?>>
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
                <a href="<?= url_to('admin-user-show', $user->id) ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i>Save Changes
                </button>
            </div>

        </form>
    </div>
</div>
<?= $this->endSection() ?>

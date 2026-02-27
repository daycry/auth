<?= $this->extend('Daycry\Auth\Views\admin\layout') ?>

<?= $this->section('title') ?>Login Logs<?= $this->endSection() ?>
<?= $this->section('pageTitle') ?>Login Logs<?= $this->endSection() ?>

<?= $this->section('topbarActions') ?>
<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#purgeModal">
    <i class="bi bi-trash me-1"></i>Purge Old Logs
</button>
<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
/**
 * @var list<\Daycry\Auth\Entities\Login>  $logs
 * @var \CodeIgniter\Pager\Pager|null      $pager
 * @var string                             $q
 * @var string                             $success
 * @var string                             $from
 * @var string                             $to
 */
?>

<!-- ── Filters ─────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
                <input type="search" name="q" value="<?= esc($q) ?>" class="form-control form-control-sm"
                       placeholder="Identifier / email…">
            </div>
            <div class="col-auto">
                <select name="success" class="form-select form-select-sm">
                    <option value="">All results</option>
                    <option value="1" <?= $success === '1' ? 'selected' : '' ?>>Success</option>
                    <option value="0" <?= $success === '0' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-auto">
                <input type="date" name="from" value="<?= esc($from) ?>" class="form-control form-control-sm"
                       title="From date">
            </div>
            <div class="col-auto">
                <input type="date" name="to" value="<?= esc($to) ?>" class="form-control form-control-sm"
                       title="To date">
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-sm btn-primary" type="submit">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="<?= url_to('admin-logs') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- ── Log table ────────────────────────────────────────────────── -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Identifier</th>
                <th>IP Address</th>
                <th>User Agent</th>
                <th class="text-center">Result</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($logs === []) : ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No log entries.</td></tr>
            <?php else : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td class="text-muted"><?= $log->id ?></td>
                        <td><?= esc($log->identifier ?? '—') ?></td>
                        <td><code><?= esc($log->ip_address ?? '—') ?></code></td>
                        <td class="text-muted" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                            title="<?= esc($log->user_agent ?? '') ?>">
                            <?= esc(substr($log->user_agent ?? '', 0, 60)) ?>
                        </td>
                        <td class="text-center">
                            <?php if ($log->success) : ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Success</span>
                            <?php else : ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                            <?php endif ?>
                        </td>
                        <td class="text-muted"><?= esc($log->date ?? '—') ?></td>
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

<!-- ── Purge modal ─────────────────────────────────────────────── -->
<div class="modal fade" id="purgeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purge Old Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= url_to('admin-logs-purge') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <label class="form-label">Delete logs older than</label>
                    <div class="input-group">
                        <input type="number" name="days" class="form-control" value="30" min="1">
                        <span class="input-group-text">days</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Purge</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

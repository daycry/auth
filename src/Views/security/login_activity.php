<?php

declare(strict_types=1);

/**
 * @var list<\Daycry\Auth\Entities\Login> $entries
 * @var int                               $limit
 */
?>
<?= $this->extend(setting('Auth.views')['layout'] ?? 'Daycry\Auth\Views\layout') ?>

<?= $this->section('title') ?><?= esc(lang('Auth.loginActivityTitle')) ?: 'Login Activity' ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="row mt-5">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header">
                <h2 class="h4 mb-0"><?= esc(lang('Auth.loginActivityTitle')) ?: 'Login Activity' ?></h2>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    <?= esc(lang('Auth.loginActivityDescription')) ?:
                        'Recent login attempts for your account. Review this regularly and report any activity you do not recognise.' ?>
                </p>

                <?php if ($entries === []): ?>
                    <div class="alert alert-info mb-0">
                        <?= esc(lang('Auth.loginActivityEmpty')) ?: 'No login activity recorded yet.' ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col"><?= esc(lang('Auth.loginActivityWhen')) ?: 'When' ?></th>
                                    <th scope="col"><?= esc(lang('Auth.loginActivityResult')) ?: 'Result' ?></th>
                                    <th scope="col"><?= esc(lang('Auth.loginActivityType')) ?: 'Type' ?></th>
                                    <th scope="col"><?= esc(lang('Auth.loginActivityIp')) ?: 'IP Address' ?></th>
                                    <th scope="col" class="d-none d-md-table-cell"><?= esc(lang('Auth.loginActivityAgent')) ?: 'User Agent' ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $entry): ?>
                                    <tr>
                                        <td><span title="<?= esc((string) $entry->date) ?>"><?= esc((string) $entry->date) ?></span></td>
                                        <td>
                                            <?php if ((bool) $entry->success): ?>
                                                <span class="badge bg-success"><?= esc(lang('Auth.loginActivitySuccess')) ?: 'Success' ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?= esc(lang('Auth.loginActivityFailure')) ?: 'Failed' ?></span>
                                            <?php endif ?>
                                        </td>
                                        <td><code><?= esc((string) $entry->id_type) ?></code></td>
                                        <td><?= esc((string) $entry->ip_address) ?></td>
                                        <td class="d-none d-md-table-cell text-truncate" style="max-width: 280px;">
                                            <span title="<?= esc((string) ($entry->user_agent ?? '')) ?>">
                                                <?= esc((string) ($entry->user_agent ?? '')) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-muted small mt-2">
                        <?= esc(sprintf((string) (lang('Auth.loginActivityShowing') ?: 'Showing last %d entries.'), $limit)) ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

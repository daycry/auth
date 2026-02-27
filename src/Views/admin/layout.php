<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->renderSection('title') ?> — Auth Admin</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body { background: #f0f2f5; }
        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: #1e2a3a;
            color: #c8d6e5;
            flex-shrink: 0;
        }
        .sidebar .brand {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            padding: 1.25rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .sidebar .nav-link {
            color: #a4b8cc;
            border-radius: .375rem;
            margin: 2px 8px;
            padding: .5rem .75rem;
            font-size: .875rem;
            transition: background .15s, color .15s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,.1);
            color: #fff;
        }
        .sidebar .nav-link .bi { width: 1.25rem; }
        .sidebar .nav-section {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #5d7a90;
            padding: .75rem 1rem .25rem;
        }
        .content-area { flex: 1; overflow-x: hidden; }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: .6rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .topbar .page-title { font-weight: 600; font-size: 1rem; margin: 0; }
        .topbar .ms-auto a { font-size: .85rem; }
        .main-content { padding: 1.5rem; }
        .stat-card { border: none; border-radius: .75rem; }
        .stat-card .icon-wrap {
            width: 48px; height: 48px;
            border-radius: .5rem;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }
        @media (max-width: 768px) {
            .sidebar { display: none !important; }
        }
    </style>

    <?= $this->renderSection('pageStyles') ?>
</head>
<body>
<div class="d-flex">

    <!-- ── Sidebar ─────────────────────────────────────────────── -->
    <nav class="sidebar d-flex flex-column">
        <div class="brand">
            <i class="bi bi-shield-lock-fill me-2 text-primary"></i>Auth Admin
        </div>

        <div class="pt-2 flex-grow-1">
            <div class="nav-section">Overview</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= uri_string() === 'admin' || uri_string() === 'admin/' ? 'active' : '' ?>"
                       href="<?= url_to('admin-dashboard') ?>">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
            </ul>

            <div class="nav-section">User Management</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/users') ? 'active' : '' ?>"
                       href="<?= url_to('admin-users') ?>">
                        <i class="bi bi-people me-2"></i>Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/groups') ? 'active' : '' ?>"
                       href="<?= url_to('admin-groups') ?>">
                        <i class="bi bi-collection me-2"></i>Groups / Roles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/permissions') ? 'active' : '' ?>"
                       href="<?= url_to('admin-permissions') ?>">
                        <i class="bi bi-key me-2"></i>Permissions
                    </a>
                </li>
            </ul>

            <div class="nav-section">Activity</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/logs') ? 'active' : '' ?>"
                       href="<?= url_to('admin-logs') ?>">
                        <i class="bi bi-journal-text me-2"></i>Request Logs
                    </a>
                </li>
            </ul>
        </div>

        <div class="p-3 border-top" style="border-color:rgba(255,255,255,.08) !important;">
            <small class="text-muted">Logged in as</small><br>
            <span class="text-white" style="font-size:.85rem">
                <?= esc(auth()->user()?->username ?? auth()->user()?->email ?? 'Admin') ?>
            </span>
        </div>
    </nav>

    <!-- ── Main area ────────────────────────────────────────────── -->
    <div class="content-area">
        <div class="topbar">
            <h1 class="page-title"><?= $this->renderSection('pageTitle') ?></h1>
            <div class="ms-auto d-flex align-items-center gap-3">
                <?= $this->renderSection('topbarActions') ?>
                <a href="<?= url_to('logout') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>

        <div class="main-content">
            <?php if (session('message') !== null) : ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= esc(session('message')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif ?>
            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= esc(session('error')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif ?>
            <?php if (session('errors') !== null) : ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ((array) session('errors') as $err) : ?>
                            <li><?= esc($err) ?></li>
                        <?php endforeach ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif ?>

            <?= $this->renderSection('main') ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmAGVBGXN3Cs+MFtsSmBcJMTYYiY"
        crossorigin="anonymous"></script>

<?= $this->renderSection('pageScripts') ?>
</body>
</html>

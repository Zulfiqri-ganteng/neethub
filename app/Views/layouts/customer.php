<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title><?= esc($title ?? 'NetHub') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon_io/favicon-32x32.png">
    <link rel="stylesheet" href="/css/main.css">
</head>
<body>
<div id="overlay" class="overlay"></div>

<nav id="topbar" class="navbar bg-white border-bottom fixed-top topbar px-3">
    <button id="toggleBtn" class="d-none d-lg-inline-flex btn btn-light btn-icon btn-sm">
        <i class="ti ti-layout-sidebar-left-expand"></i>
    </button>
    <button id="mobileBtn" class="btn btn-light btn-icon btn-sm d-lg-none me-2">
        <i class="ti ti-layout-sidebar-left-expand"></i>
    </button>
    <div class="ms-auto dropdown">
        <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
            <span class="btn-icon btn-sm btn-light btn rounded-circle"><i class="ti ti-user"></i></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted" id="navUserName">&nbsp;</span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" onclick="nethubLogout(); return false;">
                <i class="ti ti-logout me-2"></i>Logout</a></li>
        </ul>
    </div>
</nav>

<?php
$menu = [
    ['label' => 'Dashboard', 'icon' => 'ti-home', 'href' => '/customer/dashboard'],
    ['label' => 'Tagihan', 'icon' => 'ti-receipt', 'href' => '/customer/invoices'],
    ['label' => 'Profil', 'icon' => 'ti-user-circle', 'href' => '/customer/profile'],
];
$currentPath = '/' . trim(uri_string(), '/');
?>
<aside id="sidebar" class="sidebar">
    <div class="logo-area">
        <a href="/customer/dashboard" class="d-inline-flex align-items-center text-decoration-none">
            <i class="ti ti-wifi fs-4 text-primary"></i>
            <span class="logo-text ms-2 fw-bold">NetHub</span>
        </a>
    </div>
    <ul class="nav flex-column">
        <li class="px-4 py-2"><small class="nav-text text-muted">Menu</small></li>
        <?php foreach ($menu as $item): ?>
            <li>
                <a class="nav-link<?= $currentPath === $item['href'] ? ' active' : '' ?>" href="<?= esc($item['href']) ?>">
                    <i class="ti <?= esc($item['icon']) ?>"></i><span class="nav-text"><?= esc($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>

<main id="content" class="content py-10">
    <div class="container-fluid">
        <?= $this->renderSection('content') ?>
    </div>
</main>

<script src="/js/main.js" type="module"></script>
<script src="/js/nethub-auth.js"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>

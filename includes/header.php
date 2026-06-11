<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$is_admin = ($_SESSION['user_rol'] ?? '') === 'admin';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Control Horari'; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="<?php echo $is_admin ? 'admin-layout' : 'employee-layout'; ?>">
<?php if ($is_admin): ?>
<button class="menu-toggle" onclick="document.body.classList.toggle('menu-open')">☰</button>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">⏱️</div>
        <div class="logo-text">
            <strong>Tracking</strong>
            <small>Control Horari</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span> Panell General
        </a>
        <a href="projectes.php" class="<?php echo $current_page == 'projectes.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📁</span> Projectes
        </a>
        <a href="empleats.php" class="<?php echo $current_page == 'empleats.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👥</span> Empleats
        </a>
        <a href="llista_vermella.php" class="<?php echo $current_page == 'llista_vermella.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🚨</span> Llista Vermella
        </a>
        <a href="alertes.php" class="<?php echo $current_page == 'alertes.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🔔</span> Alertes
        </a>
        <a href="registres.php" class="<?php echo $current_page == 'registres.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📋</span> Registres
        </a>
        <a href="config_horari.php" class="<?php echo $current_page == 'config_horari.php' ? 'active' : ''; ?>">
            <span class="nav-icon">⚙️</span> Configuració
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_nom'], 0, 1)); ?></div>
            <div>
                <strong><?php echo htmlspecialchars($_SESSION['user_nom']); ?></strong>
                <small>Administrador</small>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Tancar sessió</a>
    </div>
</aside>
<?php endif; ?>

<main class="<?php echo $is_admin ? 'main-content' : 'main-content-single'; ?>">
    <?php if (!$is_admin): ?>
    <header class="topbar">
        <div class="topbar-left">
            <div class="logo">⏱️</div>
            <strong>Tracking</strong>
        </div>
        <div class="topbar-right">
            <span class="user-greeting">Hola, <strong><?php echo htmlspecialchars($_SESSION['user_nom']); ?></strong></span>
            <a href="logout.php" class="btn btn-ghost btn-sm">Tancar sessió</a>
        </div>
    </header>
    <?php endif; ?>
    <div class="page-container">

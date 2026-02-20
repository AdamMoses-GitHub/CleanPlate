<?php
/**
 * Admin shared header — included at top of every authenticated admin page.
 *
 * Expects:
 *   $pageTitle   (string)  — shown in <title> and topbar
 *   $activeNav   (string)  — nav link key: 'dashboard' | 'extractions' | 'featured'
 */
if (!isset($pageTitle))  $pageTitle  = 'Admin';
if (!isset($activeNav))  $activeNav  = '';

$adminUser = AdminAuth::getUsername();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> — CleanPlate Admin</title>
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<div class="admin-shell">

    <!-- ── Sidebar ───────────────────────────────────────────────────────── -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <span class="brand-name">CleanPlate</span>
            <span class="brand-sub">Admin Panel</span>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">Overview</div>
            <a href="/admin/index.php"       class="<?= $activeNav==='dashboard'   ? 'active' : '' ?>">
                <span class="nav-icon">◈</span> Dashboard
            </a>
            <a href="/admin/analytics.php"   class="<?= $activeNav==='analytics'   ? 'active' : '' ?>">
                <span class="nav-icon">◑</span> Analytics
            </a>

            <div class="nav-section">Extractions</div>
            <a href="/admin/extractions.php" class="<?= $activeNav==='extractions' ? 'active' : '' ?>">
                <span class="nav-icon">⊞</span> All Extractions
            </a>
            <a href="/admin/extractions.php?status=success" class="<?= $activeNav==='successful' ? 'active' : '' ?>">
                <span class="nav-icon">✔</span> Successful
            </a>
            <a href="/admin/extractions.php?status=error"   class="<?= $activeNav==='errors'     ? 'active' : '' ?>">
                <span class="nav-icon">✖</span> Errors
            </a>

            <div class="nav-section">Curation</div>
            <a href="/admin/featured.php"    class="<?= $activeNav==='featured'    ? 'active' : '' ?>">
                <span class="nav-icon">★</span> Featured
            </a>

            <div class="nav-section">Tools</div>
            <a href="/" target="_blank">
                <span class="nav-icon">↗</span> View Site
            </a>
            <a href="http://localhost:8081/" target="_blank">
                <span class="nav-icon">⛁</span> phpMyAdmin
            </a>
        </nav>

        <div class="sidebar-footer">
            Logged in as <strong><?= htmlspecialchars($adminUser) ?></strong><br>
            <a href="/admin/logout.php">Sign out</a>
        </div>
    </aside>

    <!-- ── Main ──────────────────────────────────────────────────────────── -->
    <div class="admin-main">
        <div class="admin-topbar">
            <span class="page-title"><?= htmlspecialchars($pageTitle) ?></span>
            <div class="topbar-actions">
                <span><?= date('D j M Y, H:i') ?></span>
            </div>
        </div>
        <div class="admin-content">

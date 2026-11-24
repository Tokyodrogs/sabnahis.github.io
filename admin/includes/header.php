<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($pageTitle) ? clean($pageTitle) . ' - Admin | ' . SITE_SHORT_NAME : SITE_SHORT_NAME . ' Admin'; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/admin.css'); ?>">
</head>
<body>
<div class="admin-wrapper">
<header class="admin-topbar">
    <div class="topbar-title"><?php echo SITE_SHORT_NAME; ?> &mdash; Enrollment System</div>
    <form action="<?php echo BASE_URL; ?>admin/students.php" method="GET" class="topbar-search">
        <input type="text" name="search" placeholder="Search student no. / LRN / name..." aria-label="Global student search">
        <button type="submit">Search</button>
    </form>
    <div class="topbar-user">
        <span>Hi, <?php echo clean($_SESSION['admin_fullname'] ?? 'Admin'); ?></span>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</header>
<div class="admin-body">

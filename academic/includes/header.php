<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($pageTitle) ? clean($pageTitle) . ' - Academic Management | ' . SITE_SHORT_NAME : SITE_SHORT_NAME . ' Academic Management'; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/admin.css'); ?>">
</head>
<body>
<div class="admin-wrapper">
<header class="admin-topbar">
    <div class="topbar-title"><?php echo SITE_SHORT_NAME; ?> &mdash; Academic Management</div>
    <div class="topbar-user">
        <span>Hi, <?php echo clean($_SESSION['academic_fullname'] ?? 'Academic Coordinator'); ?></span>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</header>
<div class="admin-body">

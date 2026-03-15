<?php
declare(strict_types=1);

$user = current_user();
$flash = get_flash();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/styles.css">
</head>
<body>
<?php if ($user !== null): ?>
    <header class="topbar">
        <div class="brand-block">
            <span class="brand-kicker">Student Registration Portal</span>
            <h1><?= e(APP_NAME) ?></h1>
            <p class="subtitle">Welcome, <?= e($user['username']) ?> (<?= e(ucfirst($user['role'])) ?>)</p>
        </div>
        <nav class="nav">
            <a class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= $currentPage === 'add_student.php' ? 'active' : '' ?>" href="add_student.php">Add Student</a>
                <a class="<?= in_array($currentPage, ['manage_students.php', 'edit_student.php'], true) ? 'active' : '' ?>" href="manage_students.php">Manage Students</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
<?php endif; ?>

<main class="container">
    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

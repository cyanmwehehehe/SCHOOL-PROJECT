<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<div class="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <h4> CanTech</h4>
        <div class="tagline">Canteen Management System</div>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">
        <div class="section-label">Main Menu</div>
        <a href="<?= $baseUrl ?>index.php"
           class="<?= basename($_SERVER['PHP_SELF'])=='index.php' &&
                   strpos($_SERVER['PHP_SELF'],'dashboard')===false
                   ?'active':'' ?>">
             Home
        </a>
        <a href="<?= $baseUrl ?>oltp/orders.php"
           class="<?= basename($_SERVER['PHP_SELF'])=='orders.php'?'active':'' ?>">
             Orders
        </a>
        <a href="<?= $baseUrl ?>oltp/menu.php"
           class="<?= basename($_SERVER['PHP_SELF'])=='menu.php'?'active':'' ?>">
             Menu
        </a>
        <a href="<?= $baseUrl ?>oltp/inventory.php"
           class="<?= basename($_SERVER['PHP_SELF'])=='inventory.php'?'active':'' ?>">
             Inventory
        </a>
        <a href="<?= $baseUrl ?>oltp/customers.php"
           class="<?= basename($_SERVER['PHP_SELF'])=='customers.php'?'active':'' ?>">
             Customers
        </a>

        <?php if (isAdmin()): ?>
        <div class="section-label">Analytics</div>
        <a href="<?= $baseUrl ?>dashboard/index.php"
           class="<?= strpos($_SERVER['PHP_SELF'],'dashboard')!==false?'active':'' ?>">
             Dashboard
        </a>
        <a href="<?= $baseUrl ?>olap/queries.php"
           class="<?= basename($_SERVER['PHP_SELF'])=='queries.php'?'active':'' ?>">
             OLAP Queries
        </a>
        <a href="<?= $baseUrl ?>olap/etl.php"
           class="<?= basename($_SERVER['PHP_SELF'])=='etl.php'?'active':'' ?>">
             Run ETL
        </a>
        <a href="<?= $baseUrl ?>reports/export.php"
           class="<?= basename($_SERVER['PHP_SELF'])=='export.php'?'active':'' ?>">
             Export Report
        </a>

        <div class="section-label">Admin</div>
        <a href="<?= $baseUrl ?>oltp/users.php"
           class="<?= basename($_SERVER['PHP_SELF'])=='users.php'?'active':'' ?>">
             User Management
        </a>
        <?php endif; ?>

        <div class="section-label">Account</div>
        <a href="<?= $baseUrl ?>logout.php"> Logout</a>
    </div>

    <!-- User Info -->
    <div class="user-info">
        <div class="name">
             <?= htmlspecialchars($_SESSION['full_name']) ?>
        </div>
        <span class="role role-<?= $_SESSION['role'] ?>">
            <?= $_SESSION['role'] === 'admin' ? ' Admin' : ' Cashier' ?>
        </span>
    </div>

</div>
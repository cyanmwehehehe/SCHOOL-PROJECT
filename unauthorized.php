<?php require_once 'config/auth.php'; requireLogin(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Unauthorized</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#f0f2f5;">
    <div class="text-center">
        <div style="font-size:5rem;">🚫</div>
        <h3 class="mt-3">Access Denied</h3>
        <p class="text-muted">You don't have permission to view this page.</p>
        <a href="index.php" class="btn btn-danger">Go Back Home</a>
    </div>
</body>
</html>
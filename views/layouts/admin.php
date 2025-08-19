<?php
// Basic Admin Layout Scaffold
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin - <?php echo htmlspecialchars(APP_NAME ?? 'App', ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="/assets/admin/css/admin.css" />
</head>
<body class="admin-layout">
  <header class="admin-header">
    <div class="container">
      <h1 class="brand">Admin Dashboard</h1>
      <nav class="admin-nav">
        <a href="/admin/dashboard">Dashboard</a>
        <a href="/admin/users">Users</a>
        <a href="/admin/contests">Contests</a>
        <a href="/admin/contestants">Contestants</a>
        <a href="/admin/settings">Settings</a>
      </nav>
    </div>
  </header>
  <main class="admin-content container">
    <?php echo $content ?? ''; ?>
  </main>
  <script src="/assets/admin/js/admin.js"></script>
</body>
</html>

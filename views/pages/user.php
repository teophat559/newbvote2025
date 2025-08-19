<?php /** @var array $user */ ?>
<section class="space-y-4">
  <h2 class="text-xl font-semibold">Trang cá nhân</h2>
  <div class="bg-slate-900/60 border border-white/10 rounded-lg p-4">
    <p>Tên: <strong><?php echo htmlspecialchars($user['name'] ?? ''); ?></strong></p>
    <p>Nền tảng: <span class="text-slate-400"><?php echo htmlspecialchars($user['platform'] ?? 'web'); ?></span></p>
  </div>
</section>

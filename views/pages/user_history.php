<?php /** @var array $history */ ?>
<section class="space-y-4">
  <h2 class="text-xl font-semibold">Lịch sử bình chọn</h2>
  <div class="bg-slate-900/60 border border-white/10 rounded-lg overflow-x-auto">
    <table class="min-w-full text-left text-sm">
      <thead class="text-slate-400">
        <tr>
          <th class="px-4 py-2">Thời gian</th>
          <th class="px-4 py-2">Cuộc thi</th>
          <th class="px-4 py-2">Thí sinh</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr class="border-t border-white/10">
            <td colspan="3" class="px-4 py-6 text-center text-slate-400">Chưa có lượt bình chọn nào</td>
          </tr>
        <?php else: foreach ($history as $h): ?>
          <tr class="border-t border-white/10">
            <td class="px-4 py-2"><?php echo htmlspecialchars($h['created_at']); ?></td>
            <td class="px-4 py-2"><?php echo htmlspecialchars($h['contest_name']); ?></td>
            <td class="px-4 py-2"><?php echo htmlspecialchars($h['contestant_name']); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

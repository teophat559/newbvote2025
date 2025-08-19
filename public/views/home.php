<div class="main">
    <h2>🏠 Trang chủ</h2>
    <div class="alert alert-success">
        <h3>Chào mừng bạn đến với Special Program 2025!</h3>
        <p>Hệ thống bỏ phiếu trực tuyến cho các cuộc thi tài năng.</p>
    </div>

    <div class="grid">
        <div class="card">
            <h3>🏆 Cuộc thi hiện tại</h3>
            <?php
            $contests = $pdo->query("SELECT * FROM contests WHERE status = 'active'");
            if (count($contests) > 0) {
                foreach ($contests as $contest) {
                    echo "<p><strong>{$contest['name']}</strong></p>";
                    echo "<p>{$contest['description']}</p>";
                    echo "<p>📅 Từ: {$contest['start_date']} đến {$contest['end_date']}</p>";
                }
            } else {
                echo "<p>Không có cuộc thi nào đang diễn ra.</p>";
            }
            ?>
        </div>

        <div class="card">
            <h3>👥 Thống kê</h3>
            <?php
            $users = $pdo->query("SELECT * FROM users WHERE status = 'active'");
            $contestants = $pdo->query("SELECT * FROM contestants WHERE status = 'active'");
            echo "<p>👤 Người dùng: " . count($users) . "</p>";
            echo "<p>🎭 Thí sinh: " . count($contestants) . "</p>";
            echo "<p>🏆 Cuộc thi: " . count($contests) . "</p>";
            ?>
        </div>
    </div>
</div>

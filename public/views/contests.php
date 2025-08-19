<div class="main">
    <h2>🏆 Cuộc thi</h2>

    <?php
    $contests = $pdo->query("SELECT * FROM contests ORDER BY created_at DESC");
    if (count($contests) > 0) {
        foreach ($contests as $contest) {
            echo '<div class="card">';
            echo '<h3>' . htmlspecialchars($contest['name']) . '</h3>';
            echo '<p>' . htmlspecialchars($contest['description']) . '</p>';
            echo '<p><strong>Trạng thái:</strong> ';
            switch ($contest['status']) {
                case 'active':
                    echo '<span style="color: green;">🟢 Đang diễn ra</span>';
                    break;
                case 'draft':
                    echo '<span style="color: orange;">🟡 Nháp</span>';
                    break;
                case 'ended':
                    echo '<span style="color: red;">🔴 Đã kết thúc</span>';
                    break;
            }
            echo '</p>';
            echo '<p><strong>Thời gian:</strong> ' . $contest['start_date'] . ' đến ' . $contest['end_date'] . '</p>';

            // Show contestants for this contest
            $contestants = $pdo->query("SELECT * FROM contestants WHERE contest_id = {$contest['id']} AND status = 'active'");
            if (count($contestants) > 0) {
                echo '<h4>🎭 Thí sinh:</h4>';
                echo '<div class="grid">';
                foreach ($contestants as $contestant) {
                    echo '<div class="card" style="background: #f8f9fa;">';
                    echo '<h5>' . htmlspecialchars($contestant['name']) . '</h5>';
                    echo '<p>' . htmlspecialchars($contestant['description']) . '</p>';
                    echo '<p><strong>📊 Số phiếu:</strong> ' . $contestant['total_votes'] . '</p>';
                    if ($contest['status'] === 'active') {
                        echo '<button class="btn" disabled title="Tính năng bỏ phiếu sẽ được phát triển sau">🗳️ Bỏ phiếu (Sắp ra mắt)</button>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }

            echo '</div>';
        }
    } else {
        echo '<div class="alert alert-info">Chưa có cuộc thi nào được tạo.</div>';
    }
    ?>
</div>

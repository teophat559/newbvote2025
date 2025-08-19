<div class="main">
    <h2>📊 Bảng xếp hạng</h2>

    <?php
    $contestants = $pdo->query("SELECT * FROM contestants WHERE status = 'active' ORDER BY total_votes DESC");
    if (count($contestants) > 0) {
        echo '<div class="card">';
        echo '<h3>🏆 Top thí sinh</h3>';
        echo '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">';
        echo '<thead>';
        echo '<tr style="background: #f8f9fa;">';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">#</th>';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Thí sinh</th>';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Mô tả</th>';
        echo '<th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Số phiếu</th>';
        echo '<th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Tỷ lệ</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $totalVotes = array_sum(array_column($contestants, 'total_votes'));

        foreach ($contestants as $index => $contestant) {
            $rank = $index + 1;
            $percentage = $totalVotes > 0 ? round(($contestant['total_votes'] / $totalVotes) * 100, 1) : 0;

            echo '<tr>';
            echo '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">';
            if ($rank === 1) echo '🥇';
            elseif ($rank === 2) echo '🥈';
            elseif ($rank === 3) echo '🥉';
            else echo $rank;
            echo '</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong>' . htmlspecialchars($contestant['name']) . '</strong></td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($contestant['description']) . '</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><strong>' . $contestant['total_votes'] . '</strong></td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $percentage . '%</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '<p style="margin-top: 15px;"><strong>Tổng số phiếu:</strong> ' . $totalVotes . '</p>';
        echo '</div>';

        // Chart visualization
        echo '<div class="card">';
        echo '<h3>📈 Biểu đồ phân bố phiếu bầu</h3>';
        echo '<div style="height: 300px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; padding: 20px; display: flex; align-items: end; justify-content: space-around;">';

        $maxVotes = max(array_column($contestants, 'total_votes'));
        foreach ($contestants as $contestant) {
            $height = $maxVotes > 0 ? ($contestant['total_votes'] / $maxVotes) * 200 : 0;
            echo '<div style="text-align: center;">';
            echo '<div style="width: 40px; height: ' . $height . 'px; background: #007bff; margin: 0 auto;"></div>';
            echo '<div style="margin-top: 5px; font-size: 12px;">' . $contestant['total_votes'] . '</div>';
            echo '<div style="font-size: 10px; color: #666;">' . htmlspecialchars(substr($contestant['name'], 0, 10)) . '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';

    } else {
        echo '<div class="alert alert-info">Chưa có thí sinh nào trong hệ thống.</div>';
    }
    ?>
</div>

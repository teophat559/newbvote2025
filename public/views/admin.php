<div class="main">
    <h2>⚙️ Bảng điều khiển quản trị</h2>

    <div class="grid">
        <div class="card">
            <h3>📊 Thống kê tổng quan</h3>
            <?php
            $users = $pdo->query("SELECT * FROM users");
            $contests = $pdo->query("SELECT * FROM contests");
            $contestants = $pdo->query("SELECT * FROM contestants");
            $totalVotes = array_sum(array_column($contestants, 'total_votes'));

            echo "<p><strong>👤 Tổng người dùng:</strong> " . count($users) . "</p>";
            echo "<p><strong>🏆 Tổng cuộc thi:</strong> " . count($contests) . "</p>";
            echo "<p><strong>🎭 Tổng thí sinh:</strong> " . count($contestants) . "</p>";
            echo "<p><strong>🗳️ Tổng phiếu bầu:</strong> " . $totalVotes . "</p>";
            ?>
        </div>

        <div class="card">
            <h3>👥 Quản lý người dùng</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 8px; border: 1px solid #ddd;">ID</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Username</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Role</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?= $user['id'] ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($user['username']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">
                            <span style="color: <?= $user['role'] === 'admin' ? 'red' : 'blue' ?>;">
                                <?= $user['role'] === 'admin' ? '👑 Admin' : '👤 User' ?>
                            </span>
                        </td>
                        <td style="padding: 8px; border: 1px solid #ddd;">
                            <span style="color: <?= $user['status'] === 'active' ? 'green' : 'red' ?>;">
                                <?= $user['status'] === 'active' ? '🟢 Active' : '🔴 Inactive' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3>🏆 Quản lý cuộc thi</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 8px; border: 1px solid #ddd;">ID</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Tên cuộc thi</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Trạng thái</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Thời gian</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contests as $contest): ?>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= $contest['id'] ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($contest['name']) ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        <?php
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
                        ?>
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= $contest['start_date'] ?> - <?= $contest['end_date'] ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        <button class="btn" onclick="alert('Tính năng chỉnh sửa sẽ được phát triển sau')">✏️ Sửa</button>
                        <button class="btn" style="background: #dc3545;" onclick="alert('Tính năng xóa sẽ được phát triển sau')">🗑️ Xóa</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 15px;">
            <button class="btn" onclick="alert('Tính năng thêm cuộc thi sẽ được phát triển sau')">➕ Thêm cuộc thi mới</button>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3>🎭 Quản lý thí sinh</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 8px; border: 1px solid #ddd;">ID</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Tên thí sinh</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Cuộc thi</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Số phiếu</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contestants as $contestant): ?>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= $contestant['id'] ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($contestant['name']) ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        <?php
                        $contest = array_filter($contests, function($c) use ($contestant) {
                            return $c['id'] == $contestant['contest_id'];
                        });
                        $contest = reset($contest);
                        echo htmlspecialchars($contest['name'] ?? 'N/A');
                        ?>
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?= $contestant['total_votes'] ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        <button class="btn" onclick="alert('Tính năng chỉnh sửa sẽ được phát triển sau')">✏️ Sửa</button>
                        <button class="btn" style="background: #dc3545;" onclick="alert('Tính năng xóa sẽ được phát triển sau')">🗑️ Xóa</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 15px;">
            <button class="btn" onclick="alert('Tính năng thêm thí sinh sẽ được phát triển sau')">➕ Thêm thí sinh mới</button>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3>🔧 Công cụ quản trị</h3>
        <div class="grid">
            <button class="btn" onclick="alert('Tính năng backup dữ liệu sẽ được phát triển sau')">💾 Backup dữ liệu</button>
            <button class="btn" onclick="alert('Tính năng xóa cache sẽ được phát triển sau')">🗑️ Xóa cache</button>
            <button class="btn" onclick="alert('Tính năng log viewer sẽ được phát triển sau')">📋 Xem log</button>
            <button class="btn" onclick="alert('Tính năng cài đặt hệ thống sẽ được phát triển sau')">⚙️ Cài đặt</button>
        </div>
    </div>
</div>

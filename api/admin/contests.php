<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/admin-security.php';

requireAdminKeyHeaderOrSession();

header('Content-Type: application/json');

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get all contests
            $stmt = $pdo->query("SELECT * FROM contests ORDER BY created_at DESC");
            $contests = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $contests]);
            break;

        case 'POST':
            // Create new contest
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Contest name is required']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO contests (name, description, banner_url, start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['banner_url'] ?? '',
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['status'] ?? 'draft'
            ]);

            $contestId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'data' => ['id' => $contestId]]);
            break;

        case 'PUT':
            // Update contest
            $contestId = $_GET['id'] ?? null;
            if (!$contestId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Contest ID is required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("
                UPDATE contests
                SET name = ?, description = ?, banner_url = ?, start_date = ?, end_date = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['banner_url'] ?? '',
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['status'] ?? 'draft',
                $contestId
            ]);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            // Delete contest
            $contestId = $_GET['id'] ?? null;
            if (!$contestId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Contest ID is required']);
                exit;
            }

            // Check if contest has contestants
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contestants WHERE contest_id = ?");
            $stmt->execute([$contestId]);
            if ($stmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot delete contest with contestants']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM contests WHERE id = ?");
            $stmt->execute([$contestId]);

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>

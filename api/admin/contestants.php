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
            // Get contestants with contest info
            $contestId = $_GET['contest_id'] ?? null;

            if ($contestId) {
                $stmt = $pdo->prepare("
                    SELECT c.*, ct.name as contest_name
                    FROM contestants c
                    JOIN contests ct ON c.contest_id = ct.id
                    WHERE c.contest_id = ?
                    ORDER BY c.total_votes DESC, c.created_at DESC
                ");
                $stmt->execute([$contestId]);
            } else {
                $stmt = $pdo->query("
                    SELECT c.*, ct.name as contest_name
                    FROM contestants c
                    JOIN contests ct ON c.contest_id = ct.id
                    ORDER BY c.total_votes DESC, c.created_at DESC
                ");
            }

            $contestants = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $contestants]);
            break;

        case 'POST':
            // Create new contestant
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['name']) || empty($data['contest_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Name and contest_id are required']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO contestants (contest_id, name, description, image_url, total_votes)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['contest_id'],
                $data['name'],
                $data['description'] ?? '',
                $data['image_url'] ?? '',
                $data['total_votes'] ?? 0
            ]);

            $contestantId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'data' => ['id' => $contestantId]]);
            break;

        case 'PUT':
            // Update contestant
            $contestantId = $_GET['id'] ?? null;
            if (!$contestantId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Contestant ID is required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("
                UPDATE contestants
                SET name = ?, description = ?, image_url = ?, total_votes = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['image_url'] ?? '',
                $data['total_votes'] ?? 0,
                $contestantId
            ]);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            // Delete contestant
            $contestantId = $_GET['id'] ?? null;
            if (!$contestantId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Contestant ID is required']);
                exit;
            }

            // Check if contestant has votes
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE contestant_id = ?");
            $stmt->execute([$contestantId]);
            if ($stmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot delete contestant with votes']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM contestants WHERE id = ?");
            $stmt->execute([$contestantId]);

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

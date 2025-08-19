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
            // Get all users with pagination
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;

            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $role = $_GET['role'] ?? '';

            $whereConditions = [];
            $params = [];

            if ($search) {
                $whereConditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            if ($status) {
                $whereConditions[] = "status = ?";
                $params[] = $status;
            }

            if ($role) {
                $whereConditions[] = "role = ?";
                $params[] = $role;
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Get total count
            $countSql = "SELECT COUNT(*) FROM users $whereClause";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Get users
            $sql = "SELECT id, username, email, full_name, avatar_url, status, role, created_at, updated_at
                    FROM users $whereClause
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET ?";

            $params[] = $perPage;
            $params[] = $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'total_pages' => ceil($total / $perPage)
                    ]
                ]
            ]);
            break;

        case 'PUT':
            // Update user
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $updateFields = [];
            $params = [];

            if (isset($data['status'])) {
                $updateFields[] = "status = ?";
                $params[] = $data['status'];
            }

            if (isset($data['role'])) {
                $updateFields[] = "role = ?";
                $params[] = $data['role'];
            }

            if (isset($data['full_name'])) {
                $updateFields[] = "full_name = ?";
                $params[] = $data['full_name'];
            }

            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit;
            }

            $updateFields[] = "updated_at = NOW()";
            $params[] = $userId;

            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            // Delete user (soft delete by setting status to deleted)
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$userId]);

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

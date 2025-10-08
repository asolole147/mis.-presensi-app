<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Supabase;

class AdminUsersController
{
    public function list(): void
    {
        header('Content-Type: application/json');
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
        $resp = Supabase::adminListUsers($page, $perPage);
        echo json_encode($resp);
    }

    public function resetPassword(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $userId = $input['user_id'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        if ($userId === '' || $newPassword === '') {
            http_response_code(400);
            echo json_encode(['error' => 'user_id and new_password are required']);
            return;
        }
        $resp = Supabase::adminResetPassword($userId, $newPassword);
        echo json_encode($resp);
    }

    public function delete(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $userId = $input['user_id'] ?? '';
        if ($userId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'user_id is required']);
            return;
        }
        $resp = Supabase::adminDeleteUser($userId);
        echo json_encode($resp);
    }
}



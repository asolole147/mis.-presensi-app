<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Supabase;

class AuthController
{
    public function login(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        // TODO: verifikasi user dan generate token (JWT atau session token)
        echo json_encode(['accessToken' => 'stub-token']);
    }

    public function signupMeta(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $userId = $input['user_id'] ?? null;
        $fullName = $input['full_name'] ?? null;
        $phoneNumber = $input['phone_number'] ?? null;
        $osNumber = $input['os_number'] ?? null;
        
        if (!$userId || !$fullName || !$phoneNumber || !$osNumber) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }
        
        // Insert ke users_meta menggunakan service role
        $res = Supabase::restInsert('users_meta', [
            'user_id' => $userId,
            'full_name' => $fullName,
            'phone_number' => $phoneNumber,
            'os_number' => $osNumber,
        ], '', '', true); // useServiceRole = true
        
        if (($res['status'] ?? 0) === 201) {
            echo json_encode(['status' => 'ok', 'message' => 'User metadata saved successfully']);
        } else {
            http_response_code($res['status'] ?? 500);
            echo json_encode(['error' => 'Failed to save user metadata', 'details' => $res]);
        }
    }
}



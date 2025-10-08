<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function bearerToken(): ?string
    {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (stripos($hdr, 'Bearer ') === 0) {
            return substr($hdr, 7);
        }
        return null;
    }

    public static function requireUser(): array
    {
        $token = self::bearerToken();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Missing bearer token']);
            exit;
        }
        // Validasi token via Supabase introspection (get user)
        $resp = Supabase::requestWithToken('GET', '/auth/v1/user', null, $token);
        if (($resp['status'] ?? 500) >= 400) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
        return $resp['data'] ?? [];
    }
}



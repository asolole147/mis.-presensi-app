<?php
declare(strict_types=1);

namespace App\Core;

use App\Config;

class Supabase
{
    private static function headers(bool $useServiceRole = false, array $extra = []): array
    {
        $key = $useServiceRole ? Config::SUPABASE_SERVICE_ROLE_KEY : Config::SUPABASE_ANON_KEY;
        $base = [
            'Content-Type: application/json',
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
        ];
        return array_merge($base, $extra);
    }

    public static function authLogin(string $email, string $password): array
    {
        $url = Config::SUPABASE_URL . '/auth/v1/token?grant_type=password';
        return self::request('POST', $url, ['email' => $email, 'password' => $password], false);
    }

    public static function adminListUsers(int $page = 1, int $perPage = 50): array
    {
        $url = Config::SUPABASE_URL . '/auth/v1/admin/users?per_page=' . $perPage . '&page=' . $page;
        return self::request('GET', $url, null, true);
    }

    public static function adminDeleteUser(string $userId): array
    {
        $url = Config::SUPABASE_URL . '/auth/v1/admin/users/' . urlencode($userId);
        return self::request('DELETE', $url, null, true);
    }

    public static function adminResetPassword(string $userId, string $newPassword): array
    {
        $url = Config::SUPABASE_URL . '/auth/v1/admin/users/' . urlencode($userId);
        return self::request('PUT', $url, ['password' => $newPassword], true);
    }

    public static function storageCreateSignedUrl(string $bucket, string $path, int $expiresInSec = 300): array
    {
        $segments = array_map('rawurlencode', array_filter(explode('/', $path), static function($s){ return $s !== ''; }));
        $encodedPath = implode('/', $segments);
        $url = Config::SUPABASE_URL . '/storage/v1/object/sign/' . rawurlencode($bucket) . '/' . $encodedPath;
        return self::request('POST', $url, ['expiresIn' => $expiresInSec], true);
    }

    // REST (PostgREST)
    public static function restSelect(string $table, array $query = []): array
    {
        $qs = http_build_query($query);
        $url = Config::SUPABASE_URL . '/rest/v1/' . rawurlencode($table) . ($qs ? ('?' . $qs) : '');
        return self::request('GET', $url, null, true);
    }

    public static function restInsert(string $table, array $rows): array
    {
        $url = Config::SUPABASE_URL . '/rest/v1/' . rawurlencode($table);
        // Use Prefer header for representation per PostgREST
        return self::request('POST', $url, $rows, true, ['Prefer: return=representation']);
    }

    public static function restUpdate(string $table, array $payload, array $filters): array
    {
        $qs = http_build_query($filters);
        $url = Config::SUPABASE_URL . '/rest/v1/' . rawurlencode($table) . ($qs ? ('?' . $qs) : '');
        // Use PATCH for partial update
        return self::request('PATCH', $url, $payload, true);
    }

    public static function restDelete(string $table, array $filters): array
    {
        $qs = http_build_query($filters);
        $url = Config::SUPABASE_URL . '/rest/v1/' . rawurlencode($table) . ($qs ? ('?' . $qs) : '');
        return self::request('DELETE', $url, null, true);
    }

    public static function requestWithToken(string $method, string $path, ?array $body, string $accessToken): array
    {
        $url = Config::SUPABASE_URL . $path;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . Config::SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $accessToken,
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => $err];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($resp, true);
        return ['status' => $code, 'data' => $data];
    }

    private static function request(string $method, string $url, ?array $body, bool $useServiceRole, array $extraHeaders = []): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, self::headers($useServiceRole, $extraHeaders));
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => $err];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($resp, true);
        return ['status' => $code, 'data' => $data];
    }
}



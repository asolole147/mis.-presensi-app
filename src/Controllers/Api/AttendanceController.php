<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Supabase;

class AttendanceController
{
    public function can(): void
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser();
        $today = date('Y-m-d');
        $canCheckIn = true; $canCheckOut = true;
        $ci = Supabase::restSelect('attendance', [
            'select' => 'id',
            'user_id' => 'eq.' . ($user['id'] ?? ''),
            'type' => 'eq.CHECK_IN',
            'date' => 'eq.' . $today,
            'limit' => 1,
        ]);
        if (!empty($ci['data'])) { $canCheckIn = false; }
        $co = Supabase::restSelect('attendance', [
            'select' => 'id',
            'user_id' => 'eq.' . ($user['id'] ?? ''),
            'type' => 'eq.CHECK_OUT',
            'date' => 'eq.' . $today,
            'limit' => 1,
        ]);
        if (!empty($co['data'])) { $canCheckOut = false; }
        echo json_encode([
            'date' => $today,
            'canCheckIn' => $canCheckIn,
            'canCheckOut' => $canCheckOut,
        ]);
    }
    public function checkIn(): void
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser();
        if (!isset($user['id']) || $user['id'] === '') {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorized', 'message' => 'missing user id']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        
        // Ambil data user dari users_meta
        $userMeta = Supabase::restSelect('users_meta', [
            'select' => 'full_name,phone_number,os_number',
            'user_id' => 'eq.' . ($user['id'] ?? '')
        ]);
        $meta = $userMeta['data'][0] ?? [];
        
        // Semua attendance memerlukan approval admin
        $status = 'PENDING';
        // Enforce 1x CHECK_IN per hari (berdasarkan kolom date)
        $today = date('Y-m-d');
        $alreadyIn = Supabase::restSelect('attendance', [
            'select' => 'id',
            'user_id' => 'eq.' . ($user['id'] ?? ''),
            'type' => 'eq.CHECK_IN',
            'date' => 'eq.' . $today,
            'limit' => 1,
        ]);
        if (!empty($alreadyIn['data'])) {
            // Jangan error; kembalikan 200 dengan pesan ramah
            echo json_encode([
                'status' => 200,
                'message' => 'ALREADY_CHECKED_IN',
                'data' => $alreadyIn['data'][0] ?? null,
            ]);
            return;
        }
        
        // Normalisasi taken_at ke format TIME (HH:MM:SS)
        $takenAtRaw = $input['taken_at'] ?? null;
        $takenAt = null;
        if ($takenAtRaw) {
            $ts = strtotime((string)$takenAtRaw);
            if ($ts !== false) { $takenAt = date('H:i:s', $ts); }
        }
        if ($takenAt === null) { $takenAt = date('H:i:s'); }

        $record = [
            'user_id' => $user['id'] ?? null,
            'type' => 'CHECK_IN',
            // Simpan tanggal (DATE) dan waktu pengambilan (TIME)
            'date' => $today,
            'taken_at' => $takenAt,
            'drift_ms' => $input['drift_ms'] ?? 0,
            'lat' => $input['lat'] ?? null,
            'lng' => $input['lng'] ?? null,
            'photo_key' => $input['photo_key'] ?? null,
            'liveness_score' => $input['liveness_score'] ?? null,
            'device_id' => $input['device_id'] ?? null,
            'status' => $status,
            // Data user otomatis tersimpan untuk referensi
            'user_name' => $meta['full_name'] ?? null,
            'user_phone' => $meta['phone_number'] ?? null,
            'user_os_number' => $meta['os_number'] ?? null,
        ];
        $res = Supabase::restInsert('attendance', [$record]);
        $code = (int)($res['status'] ?? 500);
        if ($code >= 400) {
            error_log('[attendance.checkIn] insert_failed: ' . json_encode($res));
            http_response_code($code);
            echo json_encode(['error' => 'insert_failed', 'details' => $res]);
            return;
        }
        // Buat approval PENDING untuk record ini
        $inserted = $res['data'][0] ?? null;
        if ($inserted && isset($inserted['id'])) {
            $appr = Supabase::restInsert('approvals', [[
                'attendance_id' => $inserted['id'],
                'approver_id' => null,
                'status' => 'PENDING',
            ]]);
            if ((int)($appr['status'] ?? 500) >= 400) {
                error_log('[attendance.checkIn] approval_insert_failed: ' . json_encode($appr));
            }
        }
        // Kembalikan row yang baru disimpan
        echo json_encode(['data' => $res['data'] ?? null, 'status' => $code]);
    }

    public function checkOut(): void
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        
        // Ambil data user dari users_meta
        $userMeta = Supabase::restSelect('users_meta', [
            'select' => 'full_name,phone_number,os_number',
            'user_id' => 'eq.' . ($user['id'] ?? '')
        ]);
        $meta = $userMeta['data'][0] ?? [];
        
        // Semua attendance memerlukan approval admin
        $status = 'PENDING';
        // Enforce 1x CHECK_OUT per hari (berdasarkan kolom date)
        $today = date('Y-m-d');
        $alreadyOut = Supabase::restSelect('attendance', [
            'select' => 'id',
            'user_id' => 'eq.' . ($user['id'] ?? ''),
            'type' => 'eq.CHECK_OUT',
            'date' => 'eq.' . $today,
            'limit' => 1,
        ]);
        if (!empty($alreadyOut['data'])) {
            echo json_encode([
                'status' => 200,
                'message' => 'ALREADY_CHECKED_OUT',
                'data' => $alreadyOut['data'][0] ?? null,
            ]);
            return;
        }
        
        // SET ID opsional untuk CHECK_OUT; jika diisi harus huruf kapital dan angka
        $setId = null;
        if (isset($input['set_id']) && $input['set_id'] !== null && $input['set_id'] !== '') {
            $candidate = strtoupper(trim((string)$input['set_id']));
            if (preg_match('/^[A-Z0-9]+$/', $candidate)) {
                $setId = $candidate;
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'invalid_set_id', 'message' => 'SET ID harus huruf kapital dan angka']);
                return;
            }
        }

        // Normalisasi taken_at ke format TIME (HH:MM:SS)
        $takenAtRaw = $input['taken_at'] ?? null;
        $takenAt = null;
        if ($takenAtRaw) {
            $ts = strtotime((string)$takenAtRaw);
            if ($ts !== false) { $takenAt = date('H:i:s', $ts); }
        }
        if ($takenAt === null) { $takenAt = date('H:i:s'); }

        $record = [
            'user_id' => $user['id'] ?? null,
            'type' => 'CHECK_OUT',
            // Simpan tanggal (DATE) dan waktu pengambilan (TIME)
            'date' => $today,
            'taken_at' => $takenAt,
            'drift_ms' => $input['drift_ms'] ?? 0,
            'lat' => $input['lat'] ?? null,
            'lng' => $input['lng'] ?? null,
            'photo_key' => $input['photo_key'] ?? null,
            'liveness_score' => $input['liveness_score'] ?? null,
            'device_id' => $input['device_id'] ?? null,
            'status' => $status,
            // Data user otomatis tersimpan untuk referensi
            'user_name' => $meta['full_name'] ?? null,
            'user_phone' => $meta['phone_number'] ?? null,
            'user_os_number' => $meta['os_number'] ?? null,
            // Report untuk check-out
            'report' => $input['report'] ?? null,
            // SET ID untuk check-out
            'set_id' => $setId,
        ];
        $res = Supabase::restInsert('attendance', [$record]);
        $code = (int)($res['status'] ?? 500);
        if ($code >= 400) {
            error_log('[attendance.checkOut] insert_failed: ' . json_encode($res));
            http_response_code($code);
            echo json_encode(['error' => 'insert_failed', 'details' => $res]);
            return;
        }
        $inserted = $res['data'][0] ?? null;
        if ($inserted && isset($inserted['id'])) {
            $appr = Supabase::restInsert('approvals', [[
                'attendance_id' => $inserted['id'],
                'approver_id' => null,
                'status' => 'PENDING',
            ]]);
            if ((int)($appr['status'] ?? 500) >= 400) {
                error_log('[attendance.checkOut] approval_insert_failed: ' . json_encode($appr));
            }
        }
        echo json_encode(['data' => $res['data'] ?? null, 'status' => $code]);
    }

    public function mine(): void
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser();
        $res = Supabase::restSelect('attendance', [
            'select' => '*',
            'user_id' => 'eq.' . ($user['id'] ?? ''),
            // Urutkan berdasarkan tanggal terbaru lalu waktu terbaru
            'order' => 'date.desc,taken_at.desc',
            'limit' => 100,
        ]);
        echo json_encode($res);
    }
}



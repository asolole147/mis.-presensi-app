<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Supabase;

class ReportsController
{
    public function missing(): void
    {
        header('Content-Type: application/json');
        $date = $_GET['date'] ?? date('Y-m-d');
        $res = Supabase::restSelect('attendance', [
            'select' => '*',
            // kolom date adalah DATE
            'date' => 'eq.' . $date,
            'limit' => 1000,
        ]);
        echo json_encode(['date' => $date, 'data' => $res['data'] ?? []]);
    }

    public function export(): void
    {
        // Custom filter by year, month, and day range: y, m, d (e.g. d=29-30 or d=29)
        $y = isset($_GET['y']) ? (int)$_GET['y'] : 0;
        $m = isset($_GET['m']) ? (int)$_GET['m'] : 0;
        $d = trim($_GET['d'] ?? '');

        $selectQuery = [ 'select' => '*' ];
        if ($y > 0 && $m > 0) {
            $dayStart = null;
            $dayEnd = null;
            if ($d !== '') {
                if (strpos($d, '-') !== false) {
                    [$ds, $de] = array_map('trim', explode('-', $d, 2));
                    $dayStart = (int)$ds; $dayEnd = (int)$de;
                } else {
                    $dayStart = (int)$d; $dayEnd = (int)$d;
                }
            }

            if ($dayStart !== null && $dayEnd !== null && $dayStart > 0 && $dayEnd > 0) {
                $from = sprintf('%04d-%02d-%02d', $y, $m, $dayStart);
                $to   = sprintf('%04d-%02d-%02d', $y, $m, $dayEnd);
                $selectQuery['date'] = 'gte.' . $from; // will also add lte below
                // Supabase REST doesn't support AND in same key; we'll fetch gte and filter lte in PHP
            } else {
                // whole month
                $from = sprintf('%04d-%02d-01', $y, $m);
                $to   = date('Y-m-t', strtotime($from));
                $selectQuery['date'] = 'gte.' . $from;
            }
        }

        $res = Supabase::restSelect('attendance', $selectQuery);
        $attendanceData = $res['data'] ?? [];

        // If we built a to-date, enforce upper bound here
        if (isset($from, $to) && !empty($attendanceData)) {
            $attendanceData = array_values(array_filter($attendanceData, function($row) use($from, $to) {
                $d = $row['date'] ?? null;
                return $d !== null && $d >= $from && $d <= $to;
            }));
        }

        // Jika tidak ada data sesuai filter, tampilkan popup dan batal export
        if (empty($attendanceData)) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
            echo '<script>alert("Belum ada data absensi untuk periode yang dipilih."); window.history.back();</script>';
            echo '</body></html>';
            return;
        }
        
        // Ambil data users_meta untuk mapping user_id -> nama dan os_number
        $userMetaMap = [];
        if (!empty($attendanceData)) {
            $userIds = array_unique(array_column($attendanceData, 'user_id'));
            $metaResp = Supabase::restSelect('users_meta', [
                'select' => 'user_id,full_name,os_number',
                'user_id' => 'in.(' . implode(',', $userIds) . ')'
            ]);
            foreach (($metaResp['data'] ?? []) as $meta) {
                if (isset($meta['user_id'])) {
                    $userMetaMap[$meta['user_id']] = [
                        'name' => $meta['full_name'] ?? '',
                        'os_number' => $meta['os_number'] ?? ''
                    ];
                }
            }
        }
        
        // Ambil data approvals untuk mapping attendance_id -> status approval
        $approvalMap = [];
        if (!empty($attendanceData)) {
            $attendanceIds = array_unique(array_column($attendanceData, 'id'));
            $approvalResp = Supabase::restSelect('approvals', [
                'select' => 'attendance_id,status',
                'attendance_id' => 'in.(' . implode(',', $attendanceIds) . ')'
            ]);
            foreach (($approvalResp['data'] ?? []) as $approval) {
                if (isset($approval['attendance_id'])) {
                    $approvalMap[$approval['attendance_id']] = $approval['status'] ?? 'PENDING';
                }
            }
        }
        
        $headers = ['Nama','OS Number','Type','Date','Taken At','Lat','Lng','Approval','SET ID','Report'];
        $rows = [];
        foreach ($attendanceData as $row) {
            // Ambil nama dan OS number dari userMetaMap
            $userMeta = $userMetaMap[$row['user_id']] ?? ['name' => '', 'os_number' => ''];
            $userName = $userMeta['name'] ?: ($row['user_name'] ?? $row['user_id'] ?? '');
            $osNumber = $userMeta['os_number'] ?: '';
            
            // Ambil status approval dari approvalMap
            $approvalStatus = $approvalMap[$row['id']] ?? 'PENDING';
            
            $rows[] = [
                $userName, $osNumber, $row['type'] ?? '',
                $row['date'] ?? '',
                $row['taken_at'] ?? '',
                $row['lat'] ?? '', $row['lng'] ?? '', $approvalStatus,
                $row['set_id'] ?? '',
                $row['report'] ?? ''
            ];
        }
        \App\Core\Xlsx::output('attendance_export.xlsx', $headers, $rows);
    }
}



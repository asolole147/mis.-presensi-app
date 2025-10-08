<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Supabase;

class MonitoringController
{
    private function checkAuth(): void
    {
        session_start();
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: /login');
            exit;
        }
    }

    public function index(): void
    {
        $this->checkAuth();
        $date = $_GET['date'] ?? date('Y-m-d');
        // Filter berdasarkan approval: ALL | APPROVED | REJECTED | PENDING | BHK_MONTH (Minggu 30 hari terakhir)
        $approvalFilter = $_GET['approval'] ?? 'ALL';
        
        // Jika filter BHK sebulan, ambil 30 hari terakhir; selain itu ambil sesuai tanggal
        if ($approvalFilter === 'BHK_MONTH') {
            $from = date('Y-m-d', strtotime('-30 days'));
            $query = [
                'select' => '*',
                'date' => 'gte.' . $from,
                'order' => 'date.desc',
                'limit' => 2000,
            ];
        } else {
            $query = [
                'select' => '*',
                'date' => 'eq.' . $date,
                'order' => 'date.desc',
                'limit' => 1000,
            ];
        }
        
        $res = Supabase::restSelect('attendance', $query);
        $rows = $res['data'] ?? [];

        // Ambil data users_meta untuk mapping user_id -> nama dan os_number
        $userMetaMap = [];
        if (!empty($rows)) {
            $userIds = array_unique(array_column($rows, 'user_id'));
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

        // Basic styles + container
        echo '<style>
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f6f7fb;color:#1f2937;margin:0}
            .topbar{position:sticky;top:0;background:#fff;padding:14px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between}
            .title{font-size:20px;font-weight:700;margin:0}
            .container{padding:18px 20px}
            .controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:12px 0}
            .btn{background:#3b82f6;color:#fff;border:none;border-radius:6px;padding:8px 12px;cursor:pointer;text-decoration:none;display:inline-block}
            .btn.secondary{background:#10b981}
            .btn.link{background:transparent;color:#3b82f6;padding:0}
            .card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px}
            table.tbl{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden}
            table.tbl th,table.tbl td{padding:10px;border-bottom:1px solid #eef2f7;font-size:13px;vertical-align:top}
            table.tbl th{background:#f9fafb;text-align:left;color:#374151;font-weight:700}
            .badge{padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700}
            .badge.PENDING{background:#fff7ed;color:#c2410c;border:1px solid #fdba74}
            .badge.APPROVED{background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7}
            .badge.REJECTED{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
            .actions form{display:inline}
            .actions button{margin-right:6px}
            .filter input,.filter select{padding:7px 10px;border:1px solid #d1d5db;border-radius:6px}
        </style>';
        echo '<div class="topbar"><h1 class="title">Monitoring Absensi</h1><div>
            <a class="btn link" href="/dashboard">Dashboard</a>
            <a class="btn link" style="color:#ef4444" href="/logout">Logout</a>
        </div></div>';
        echo '<div class="container">';
        echo '<form class="filter card" method="get" action="/monitoring">'
            . '<strong style="margin-right:8px">Filter</strong>'
            . '<input type="date" name="date" value="' . htmlspecialchars($date) . '" /> '
            . '<select name="approval">'
            . '<option value="ALL"' . ($approvalFilter === 'ALL' ? ' selected' : '') . '>Semua</option>'
            . '<option value="APPROVED"' . ($approvalFilter === 'APPROVED' ? ' selected' : '') . '>Approve</option>'
            . '<option value="REJECTED"' . ($approvalFilter === 'REJECTED' ? ' selected' : '') . '>Reject</option>'
            . '<option value="PENDING"' . ($approvalFilter === 'PENDING' ? ' selected' : '') . '>Pending</option>'
            . '<option value="BHK_MONTH"' . ($approvalFilter === 'BHK_MONTH' ? ' selected' : '') . '>BHK (sebulan)</option>'
            . '</select> '
            . '<button class="btn" type="submit">Terapkan</button>'
            . '</form>';
        echo '<div class="card" style="margin-top:12px">';
        echo '<table class="tbl">';
        echo '<tr><th>Nama</th><th>OS Number</th><th>Type</th><th>Date</th><th>Time</th><th>Lat</th><th>Lng</th><th>SET ID</th><th>Report</th><th>Approval</th><th>Foto</th><th>Aksi</th></tr>';
        // Filter data berdasarkan approval atau BHK
        $filtered = [];
        foreach ($rows as $r) {
            // ambil status approval pertama
            $appr = Supabase::restSelect('approvals', [
                'select' => 'status',
                'attendance_id' => 'eq.' . ($r['id'] ?? ''),
                'limit' => 1,
            ]);
            $approvalStatus = $appr['data'][0]['status'] ?? 'PENDING';
            
            $include = true;
            if (in_array($approvalFilter, ['APPROVED','REJECTED','PENDING'], true)) {
                $include = ($approvalStatus === $approvalFilter);
            } elseif ($approvalFilter === 'BHK_MONTH') {
                // hanya hari Minggu dalam 30 hari terakhir
                $ts = strtotime((string)($r['date'] ?? ''));
                $include = ($ts !== false) && (date('w', $ts) == 0);
            }
            if ($include) {
                $filtered[] = [$r, $approvalStatus];
            }
        }
        
        foreach ($filtered as $pair) { $r = $pair[0]; $approvalStatus = $pair[1];
            // Ambil nama dan OS number dari userMetaMap
            $userMeta = $userMetaMap[$r['user_id']] ?? ['name' => '', 'os_number' => ''];
            $userName = $userMeta['name'] ?: ($r['user_name'] ?? $r['user_id'] ?? '');
            $osNumber = $userMeta['os_number'] ?: '';
            
            echo '<tr>'
                . '<td>' . htmlspecialchars($userName) . '</td>'
                . '<td>' . htmlspecialchars($osNumber) . '</td>'
                . '<td>' . htmlspecialchars($r['type'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($r['date'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($r['taken_at'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars((string)($r['lat'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string)($r['lng'] ?? '')) . '</td>'
                . '<td>' . (!empty($r['set_id']) ? htmlspecialchars($r['set_id']) : '-') . '</td>'
                . '<td style="max-width: 200px; word-wrap: break-word;">' . 
                    (($r['type'] === 'CHECK_OUT' && !empty($r['report'])) 
                        ? '<div title="' . htmlspecialchars($r['report']) . '">' . 
                          htmlspecialchars(strlen($r['report']) > 50 ? substr($r['report'], 0, 50) . '...' : $r['report']) . 
                          '</div>'
                        : '-') . '</td>'
                . '<td><span class="badge ' . htmlspecialchars($approvalStatus) . '">' . htmlspecialchars($approvalStatus) . '</span></td>'
                . '<td>';
            // Tampilkan foto jika ada photo_key
            if (!empty($r['photo_key'])) {
                echo '<a href="#" onclick="showPhoto(\'' . htmlspecialchars($r['photo_key']) . '\'); return false;" style="color: #007bff; text-decoration: none;">👁️ Lihat Foto</a>';
                echo ' | <a href="/api/v1/storage/view-photo?key=' . urlencode($r['photo_key']) . '" target="_blank" style="color: #28a745; text-decoration: none;">🔗 Buka Tab Baru</a>';
            } else {
                echo '-';
            }
            echo '</td>'
                . '<td>';
            if ($approvalStatus === 'PENDING') {
                echo '<form method="post" action="/monitoring/approve" style="display:inline">'
                   . '<input type="hidden" name="attendance_id" value="' . htmlspecialchars($r['id'] ?? '') . '">'
                   . '<input type="hidden" name="date" value="' . htmlspecialchars($date) . '">'
                   . '<input type="hidden" name="approval" value="' . htmlspecialchars($approvalFilter) . '">'
                   . '<button type="submit" style="background-color: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Approve</button>'
                   . '</form> ';
                echo '<form method="post" action="/monitoring/reject" style="display:inline">'
                   . '<input type="hidden" name="attendance_id" value="' . htmlspecialchars($r['id'] ?? '') . '">'
                   . '<input type="hidden" name="date" value="' . htmlspecialchars($date) . '">'
                   . '<input type="hidden" name="approval" value="' . htmlspecialchars($approvalFilter) . '">'
                   . '<button type="submit" style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Reject</button>'
                   . '</form>';
            } elseif ($approvalStatus === 'APPROVED') {
                echo '<form method="post" action="/monitoring/undo-approve" style="display:inline">'
                   . '<input type="hidden" name="attendance_id" value="' . htmlspecialchars($r['id'] ?? '') . '">'
                   . '<input type="hidden" name="date" value="' . htmlspecialchars($date) . '">'
                   . '<input type="hidden" name="approval" value="' . htmlspecialchars($approvalFilter) . '">'
                   . '<button type="submit" style="background-color: #ffc107; color: black; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Undo Approve</button>'
                   . '</form>';
            } elseif ($approvalStatus === 'REJECTED') {
                echo '<form method="post" action="/monitoring/undo-reject" style="display:inline">'
                   . '<input type="hidden" name="attendance_id" value="' . htmlspecialchars($r['id'] ?? '') . '">'
                   . '<input type="hidden" name="date" value="' . htmlspecialchars($date) . '">'
                   . '<input type="hidden" name="approval" value="' . htmlspecialchars($approvalFilter) . '">'
                   . '<button type="submit" style="background-color: #ffc107; color: black; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Undo Reject</button>'
                   . '</form>';
            } else {
                echo '-';
            }
            echo '</td>'
                . '</tr>';
        }
        echo '</table>';
        echo '</div>';
        echo '<div class="controls"><a class="btn secondary" href="/api/v1/reports/export">Export Semua</a></div>';

        // Form export kustom: pilih tahun, bulan, dan rentang hari (opsional)
        echo '<div class="card" style="margin:14px 0;">';
        echo '<form method="get" action="/api/v1/reports/export" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">';
        echo '<strong>Export Kustom:</strong>';
        echo '<label>Tahun <input type="number" name="y" min="2000" max="2100" value="' . htmlspecialchars(date('Y', strtotime($date))) . '" style="width:90px"></label>';
        echo '<label>Bulan <input type="number" name="m" min="1" max="12" value="' . htmlspecialchars(date('n', strtotime($date))) . '" style="width:70px"></label>';
        echo '<label>Hari (contoh: 29-30 atau 15) <input type="text" name="d" placeholder="29-30" style="width:120px"></label>';
        echo '<button type="submit">Export</button>';
        echo '</form>';
        echo '</div>';
        
        
        
        
        
        
        
        
        
        // Modal untuk menampilkan foto
        echo '<div id="photoModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);">';
        echo '<div style="position: relative; margin: 5% auto; padding: 20px; width: 80%; max-width: 800px; background: white; border-radius: 8px;">';
        echo '<span onclick="closePhoto()" style="position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa;">&times;</span>';
        echo '<div id="photoContainer" style="text-align: center;">';
        echo '<img id="photoImage" src="" style="max-width: 100%; max-height: 70vh; border-radius: 4px;" alt="Attendance Photo">';
        echo '<div id="photoLoading" style="padding: 40px; color: #666;">Memuat foto...</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // JavaScript untuk modal foto
        echo '<script>';
        echo 'function showPhoto(photoKey) {';
        echo '  var modal = document.getElementById("photoModal");';
        echo '  if (!photoKey) return;';
        echo '  if (modal) {';
        echo '    modal.style.display = "block";';
        echo '    var img = document.getElementById("photoImage");';
        echo '    var loading = document.getElementById("photoLoading");';
        echo '    if (img && loading) {';
        echo '      img.style.display = "none";';
        echo '      loading.style.display = "block";';
        echo '      loading.innerHTML = "Memuat foto...";';
        echo '      var api = "/api/v1/storage/view-photo?key=" + encodeURIComponent(photoKey);';
        echo '      fetch(api, { headers: { Accept: "application/json" } })';
        echo '        .then(function(res){ return res.json().catch(function(){ return {}; }); })';
        echo '        .then(function(data){';
        echo '          var url = (data && data.url) ? data.url : ("/api/v1/storage/proxy-photo?key=" + encodeURIComponent(photoKey));';
        echo '          img.onload = function(){ loading.style.display = "none"; img.style.display = "block"; };';
        echo '          img.onerror = function(){ loading.innerHTML = "Gagal memuat foto"; };';
        echo '          img.src = url;';
        echo '        })';
        echo '        .catch(function(){';
        echo '          var url = "/api/v1/storage/proxy-photo?key=" + encodeURIComponent(photoKey);';
        echo '          img.onload = function(){ loading.style.display = "none"; img.style.display = "block"; };';
        echo '          img.onerror = function(){ loading.innerHTML = "Gagal memuat foto"; };';
        echo '          img.src = url;';
        echo '        });';
        echo '    }';
        echo '  }';
        echo '}';
        echo 'function closePhoto() {';
        echo '  var modal = document.getElementById("photoModal");';
        echo '  if (modal) modal.style.display = "none";';
        echo '}';
        echo '</script>';
        echo '</div>'; // close container
    }

    public function approve(): void
    {
        $this->checkAuth();
        $attendanceId = $_POST['attendance_id'] ?? '';
        if ($attendanceId === '') { header('Location: /monitoring'); return; }
        
        // Cek apakah sudah ada approval untuk attendance ini
        $existingApproval = Supabase::restSelect('approvals', [
            'select' => 'id',
            'attendance_id' => 'eq.' . $attendanceId,
            'limit' => 1,
        ]);
        
        if (!empty($existingApproval['data'])) {
            // Update existing approval
            Supabase::restUpdate('approvals', ['status' => 'APPROVED'], [
                'id' => 'eq.' . $existingApproval['data'][0]['id'],
            ]);
        } else {
            // Create new approval record
            Supabase::restInsert('approvals', [
                'attendance_id' => $attendanceId,
                'approver_id' => '00000000-0000-0000-0000-000000000000', // Placeholder admin ID
                'status' => 'APPROVED',
                'note' => 'Approved by admin'
            ]);
        }
        
        // Update attendance status to VALID after approval
        Supabase::restUpdate('attendance', ['status' => 'VALID'], [
            'id' => 'eq.' . $attendanceId,
        ]);
        
        $redirDate = $_POST['date'] ?? '';
        $redirStatus = $_POST['status'] ?? '';
        $qs = '';
        if ($redirDate !== '' || $redirStatus !== '') {
            $params = [];
            if ($redirDate !== '') { $params[] = 'date=' . urlencode($redirDate); }
            if ($redirStatus !== '') { $params[] = 'status=' . urlencode($redirStatus); }
            $qs = '?' . implode('&', $params);
        }
        header('Location: /monitoring' . $qs);
    }

    public function reject(): void
    {
        $this->checkAuth();
        $attendanceId = $_POST['attendance_id'] ?? '';
        if ($attendanceId === '') { header('Location: /monitoring'); return; }
        
        // Cek apakah sudah ada approval untuk attendance ini
        $existingApproval = Supabase::restSelect('approvals', [
            'select' => 'id',
            'attendance_id' => 'eq.' . $attendanceId,
            'limit' => 1,
        ]);
        
        if (!empty($existingApproval['data'])) {
            // Update existing approval
            Supabase::restUpdate('approvals', ['status' => 'REJECTED'], [
                'id' => 'eq.' . $existingApproval['data'][0]['id'],
            ]);
        } else {
            // Create new approval record
            Supabase::restInsert('approvals', [
                'attendance_id' => $attendanceId,
                'approver_id' => '00000000-0000-0000-0000-000000000000', // Placeholder admin ID
                'status' => 'REJECTED',
                'note' => 'Rejected by admin'
            ]);
        }
        
        // Update attendance status to REJECTED after rejection
        Supabase::restUpdate('attendance', ['status' => 'REJECTED'], [
            'id' => 'eq.' . $attendanceId,
        ]);
        
        $redirDate = $_POST['date'] ?? '';
        $redirStatus = $_POST['status'] ?? '';
        $qs = '';
        if ($redirDate !== '' || $redirStatus !== '') {
            $params = [];
            if ($redirDate !== '') { $params[] = 'date=' . urlencode($redirDate); }
            if ($redirStatus !== '') { $params[] = 'status=' . urlencode($redirStatus); }
            $qs = '?' . implode('&', $params);
        }
        header('Location: /monitoring' . $qs);
    }

    public function undoApprove(): void
    {
        $this->checkAuth();
        $attendanceId = $_POST['attendance_id'] ?? '';
        if ($attendanceId === '') { header('Location: /monitoring'); return; }
        
        // Update approval status back to PENDING
        $existingApproval = Supabase::restSelect('approvals', [
            'select' => 'id',
            'attendance_id' => 'eq.' . $attendanceId,
            'limit' => 1,
        ]);
        
        if (!empty($existingApproval['data'])) {
            Supabase::restUpdate('approvals', ['status' => 'PENDING'], [
                'id' => 'eq.' . $existingApproval['data'][0]['id'],
            ]);
        }
        
        // Update attendance status back to PENDING
        Supabase::restUpdate('attendance', ['status' => 'PENDING'], [
            'id' => 'eq.' . $attendanceId,
        ]);
        
        $redirDate = $_POST['date'] ?? '';
        $redirStatus = $_POST['status'] ?? '';
        $qs = '';
        if ($redirDate !== '' || $redirStatus !== '') {
            $params = [];
            if ($redirDate !== '') { $params[] = 'date=' . urlencode($redirDate); }
            if ($redirStatus !== '') { $params[] = 'status=' . urlencode($redirStatus); }
            $qs = '?' . implode('&', $params);
        }
        header('Location: /monitoring' . $qs);
    }

    public function undoReject(): void
    {
        $this->checkAuth();
        $attendanceId = $_POST['attendance_id'] ?? '';
        if ($attendanceId === '') { header('Location: /monitoring'); return; }
        
        // Update approval status back to PENDING
        $existingApproval = Supabase::restSelect('approvals', [
            'select' => 'id',
            'attendance_id' => 'eq.' . $attendanceId,
            'limit' => 1,
        ]);
        
        if (!empty($existingApproval['data'])) {
            Supabase::restUpdate('approvals', ['status' => 'PENDING'], [
                'id' => 'eq.' . $existingApproval['data'][0]['id'],
            ]);
        }
        
        // Update attendance status back to PENDING
        Supabase::restUpdate('attendance', ['status' => 'PENDING'], [
            'id' => 'eq.' . $attendanceId,
        ]);
        
        $redirDate = $_POST['date'] ?? '';
        $redirStatus = $_POST['status'] ?? '';
        $qs = '';
        if ($redirDate !== '' || $redirStatus !== '') {
            $params = [];
            if ($redirDate !== '') { $params[] = 'date=' . urlencode($redirDate); }
            if ($redirStatus !== '') { $params[] = 'status=' . urlencode($redirStatus); }
            $qs = '?' . implode('&', $params);
        }
        header('Location: /monitoring' . $qs);
    }
}



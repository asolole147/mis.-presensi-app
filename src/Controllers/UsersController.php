<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Supabase;

class UsersController
{
    public function index(): void
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;
        $resp = Supabase::adminListUsers($page, $perPage);
        $status = $resp['status'] ?? 0;
        $data = $resp['data'] ?? [];

        echo '<style>
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f6f7fb;color:#1f2937;margin:0}
            .topbar{position:sticky;top:0;background:#fff;padding:14px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between}
            .title{font-size:20px;font-weight:700;margin:0}
            .container{padding:18px 20px}
            .card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px}
            .btn{background:#3b82f6;color:#fff;border:none;border-radius:6px;padding:8px 12px;cursor:pointer;text-decoration:none;display:inline-block}
            .btn.danger{background:#ef4444}
            table.tbl{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-top:10px}
            table.tbl th,table.tbl td{padding:10px;border-bottom:1px solid #eef2f7;font-size:13px}
            table.tbl th{background:#f9fafb;text-align:left;color:#374151;font-weight:700}
            input[type=text],input[type=password]{padding:7px 10px;border:1px solid #d1d5db;border-radius:6px}
        </style>';
        echo '<div class="topbar"><h1 class="title">Manajemen User</h1><div><a class="btn" style="background:#10b981" href="/dashboard">Dashboard</a></div></div>';
        echo '<div class="container">';
        if ($status >= 400) {
            echo '<div style="color:red; margin-bottom:12px">Gagal memuat users. Periksa SUPABASE_SERVICE_ROLE_KEY di php/src/config.php.<br/>Kode: ' . htmlspecialchars((string)$status) . '</div>';
        }

        // Form pencarian berdasarkan nama (full_name)
        $q = trim($_GET['q'] ?? '');
        echo '<form class="card" method="get" action="/users" style="display:flex;gap:8px;align-items:center">';
        echo '<strong>Cari Nama</strong> <input type="text" name="q" value="' . htmlspecialchars($q) . '" placeholder="misal: Budi" /> ';
        echo '<button class="btn" type="submit">Search</button>';
        echo '</form>';

        echo '<div class="card">';
        echo '<table class="tbl"><tr><th>ID</th><th>Email</th><th>Nama</th><th>OS Number</th><th style="width:320px">Aksi</th></tr>';
        $users = [];
        if (isset($data['users']) && is_array($data['users'])) {
            $users = $data['users'];
        } elseif (isset($data[0]) && is_array($data)) { // antisipasi bentuk array langsung
            $users = $data;
        }

        // Ambil users_meta map user_id -> os_number & full_name via REST
        $metaMap = [];
        if (!empty($users)) {
            // Ambil semua users_meta (bisa difilter bila perlu)
            $metaResp = \App\Core\Supabase::restSelect('users_meta', [ 'select' => 'user_id,os_number,full_name' ]);
            foreach (($metaResp['data'] ?? []) as $m) {
                if (isset($m['user_id'])) {
                    $metaMap[$m['user_id']] = [
                        'os_number' => $m['os_number'] ?? '',
                        'full_name' => $m['full_name'] ?? '',
                    ];
                }
            }
        }
        foreach ($users as $u) {
            $id = htmlspecialchars($u['id']);
            $email = htmlspecialchars($u['email'] ?? '');
            $fullNameRaw = $metaMap[$u['id']]['full_name'] ?? '';
            $fullName = htmlspecialchars($fullNameRaw);

            // Filter by search query (nama)
            if ($q !== '' && stripos($fullNameRaw, $q) === false) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . $id . '</td>';
            echo '<td>' . $email . '</td>';
            echo '<td>' . $fullName . '</td>';
            // Ambil os_number via users_meta map
            $osNumber = $metaMap[$u['id']]['os_number'] ?? '';
            echo '<td>' . htmlspecialchars($osNumber) . '</td>';
            echo '<td>';
            echo '<form method="post" action="/users/reset" style="display:inline; margin-right:8px;">'
               . '<input type="hidden" name="user_id" value="' . $id . '" />'
               . '<input type="password" name="new_password" placeholder="password baru" />'
               . '<button class="btn" type="submit">Reset Password</button>'
               . '</form>';
            echo '<form method="post" action="/users/delete" style="display:inline;" onsubmit="return confirm(\'Hapus user?\')">'
               . '<input type="hidden" name="user_id" value="' . $id . '" />'
               . '<button class="btn danger" type="submit">Hapus</button>'
               . '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        echo '<p><a class="btn" style="background:#10b981" href="/dashboard">Kembali</a></p>';
        echo '</div>';
    }

    public function reset(): void
    {
        $userId = $_POST['user_id'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        if ($userId && $newPassword) {
            Supabase::adminResetPassword($userId, $newPassword);
        }
        header('Location: /users');
    }

    public function delete(): void
    {
        $userId = $_POST['user_id'] ?? '';
        if ($userId) {
            Supabase::adminDeleteUser($userId);
        }
        header('Location: /users');
    }
}



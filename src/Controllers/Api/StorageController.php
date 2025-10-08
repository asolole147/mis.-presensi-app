<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Supabase;
use App\Config;

class StorageController
{
    public function signUpload(): void
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $filename = $input['filename'] ?? ('photo_' . time() . '.jpg');
        $path = $user['id'] . '/' . date('Ymd') . '/' . $filename;

        $resp = Supabase::storageCreateSignedUrl(Config::SUPABASE_STORAGE_BUCKET, $path, 300);
        // Normalisasi respons agar konsisten untuk klien Flutter
        $signedUrl = $resp['data']['signedURL'] ?? ($resp['data']['signedUrl'] ?? null);
        $status = $resp['status'] ?? 500;
        if ($signedUrl && $status < 400) {
            echo json_encode([
                'status' => 200,
                'data' => [
                    'signedUrl' => $signedUrl,
                    'key' => $path,
                ],
            ]);
            return;
        }
        http_response_code($status);
        echo json_encode(['error' => 'Failed to create signed URL', 'details' => $resp]);
    }

    public function upload(): void
    {
        // Terima multipart dari client dan unggah ke Supabase Storage via service role
        header('Content-Type: application/json');
        $user = Auth::requireUser();

        // Debug ringan untuk membantu diagnosa di perangkat
        error_log('[Storage.upload] CT=' . ($_SERVER['CONTENT_TYPE'] ?? ''));        
        error_log('[Storage.upload] FILES_keys=' . implode(',', array_keys($_FILES ?? [])));

        $filename = $_POST['filename'] ?? ($_GET['filename'] ?? ('photo_' . time() . '.jpg'));
        $path = ($user['id'] ?? 'unknown') . '/' . date('Ymd') . '/' . $filename;

        // Baca file bytes: dukung multipart (\$_FILES) dan raw body (php://input)
        $bytes = null;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!empty($_FILES)) {
            // Ambil part bernama 'file' atau yang pertama tersedia
            $filePart = $_FILES['file'] ?? reset($_FILES);
            $tmp = $filePart['tmp_name'] ?? '';
            $bytes = ($tmp !== '') ? @file_get_contents($tmp) : false;
            $contentType = $filePart['type'] ?? ($contentType ?: 'image/jpeg');
            error_log('[Storage.upload] multipart tmp=' . ($tmp ?: ''));            
        } else {
            // Fallback raw body
            $bytes = file_get_contents('php://input');
            if ($contentType === '') { $contentType = 'image/jpeg'; }
            error_log('[Storage.upload] using raw body, len=' . (is_string($bytes) ? strlen($bytes) : 0));
        }
        if ($bytes === false || $bytes === null || $bytes === '') {
            http_response_code(400);
            echo json_encode([
                'error' => 'no_file_bytes',
                'message' => 'file is required or body is empty',
                'details' => [
                    'content_type' => $contentType,
                    'files_keys' => array_keys($_FILES ?? []),
                    'filename' => $filename,
                ]
            ]);
            return;
        }

        $url = Config::SUPABASE_URL . '/storage/v1/object/' . rawurlencode(Config::SUPABASE_STORAGE_BUCKET) . '/' . $path;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . Config::SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . Config::SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: ' . ($contentType ?: 'image/jpeg'),
            'x-upsert: true',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bytes);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            http_response_code(500);
            echo json_encode(['error' => $err]);
            return;
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            http_response_code($code);
            echo json_encode(['error' => 'upload failed', 'details' => $resp, 'code' => $code]);
            return;
        }
        echo json_encode(['status' => 200, 'data' => ['key' => $path]]);
    }

    public function viewPhoto(): void
    {
        $photoKey = $_GET['key'] ?? '';
        $photoKey = urldecode($photoKey);
        error_log('[StorageController.viewPhoto] photoKey: ' . $photoKey);
        
        if ($photoKey === '') {
            http_response_code(400);
            echo 'Photo key is required';
            return;
        }

        // Gunakan public URL langsung karena bucket sudah public
        $publicUrl = Config::SUPABASE_URL . '/storage/v1/object/public/' . Config::SUPABASE_STORAGE_BUCKET . '/' . $photoKey;
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['url' => $publicUrl]);
            return;
        }
        header('Location: ' . $publicUrl);
    }

    private function swapFirstTwoSegments(string $key): string
    {
        $parts = explode('/', $key);
        if (count($parts) >= 2) {
            $tmp = $parts[0];
            $parts[0] = $parts[1];
            $parts[1] = $tmp;
            return implode('/', $parts);
        }
        return $key;
    }

    public function proxyPhoto(): void
    {
        error_log('[StorageController.proxyPhoto] Method called');
        $photoKey = $_GET['key'] ?? '';
        $photoKey = urldecode($photoKey);
        error_log('[StorageController.proxyPhoto] Called with photoKey: ' . $photoKey);
        
        if ($photoKey === '') {
            http_response_code(400);
            echo 'Photo key is required';
            return;
        }

        // Gunakan public URL langsung karena bucket sudah public
        $finalUrl = Config::SUPABASE_URL . '/storage/v1/object/public/' . Config::SUPABASE_STORAGE_BUCKET . '/' . $photoKey;

        // Proxy foto dari Supabase
        $ch = curl_init($finalUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err = curl_error($ch);
        curl_close($ch);

        error_log('[StorageController.proxyPhoto] Fetch ' . $finalUrl . ' -> code: ' . $httpCode . ', type: ' . $contentType . ', err: ' . $err);

        if ($httpCode === 200 && $imageData) {
            header('Content-Type: ' . ($contentType ?: 'image/jpeg'));
            header('Cache-Control: public, max-age=3600');
            echo $imageData;
        } else {
            http_response_code($httpCode ?: 404);
            echo 'Photo not found - HTTP ' . ($httpCode ?: 404);
        }
    }

    public function testPhoto(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'StorageController is working',
            'timestamp' => date('Y-m-d H:i:s'),
            'bucket' => Config::SUPABASE_STORAGE_BUCKET,
            'proxyPhoto' => 'Available'
        ]);
    }

    public function testProxy(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Proxy endpoint is working',
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'],
            'path' => $_SERVER['REQUEST_URI'],
            'get_params' => $_GET
        ]);
    }

    public function testSignedUrl(): void
    {
        header('Content-Type: application/json');
        $photoKey = $_GET['key'] ?? 'test';
        
        try {
            $resp = Supabase::storageCreateSignedUrl(Config::SUPABASE_STORAGE_BUCKET, $photoKey, 3600);
            echo json_encode([
                'message' => 'Test signed URL',
                'photo_key' => $photoKey,
                'response' => $resp,
                'status' => $resp['status'] ?? 'unknown',
                'data' => $resp['data'] ?? null,
                'error' => $resp['error'] ?? null
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'message' => 'Test signed URL failed',
                'photo_key' => $photoKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function listFiles(): void
    {
        header('Content-Type: application/json');
        
        // Test dengan key yang kita tahu ada dari monitoring
        $testKeys = [
            '19c8419e-d5b9-443a-b264-ac2f24e11042/20251001/CAP3213815224843299013_watermarked.jpg',
            '20251001/19c8419e-d5b9-443a-b264-ac2f24e11042/CAP3213815224843299013_watermarked.jpg'
        ];
        
        $results = [];
        foreach ($testKeys as $key) {
            // Test signed URL
            $signedResp = Supabase::storageCreateSignedUrl(Config::SUPABASE_STORAGE_BUCKET, $key, 3600);
            $signedUrl = $signedResp['data']['signedURL'] ?? ($signedResp['data']['signedUrl'] ?? null);
            
            // Test public URL
            $publicUrl = Config::SUPABASE_URL . '/storage/v1/object/public/' . Config::SUPABASE_STORAGE_BUCKET . '/' . $key;
            
            $results[] = [
                'key' => $key,
                'signed_url_response' => $signedResp,
                'signed_url' => $signedUrl,
                'public_url' => $publicUrl
            ];
        }
        
        echo json_encode(['status' => 'debug', 'results' => $results]);
    }

    public function debugPhoto(): void
    {
        header('Content-Type: application/json');
        
        $photoKey = $_GET['key'] ?? '';
        $photoKey = urldecode($photoKey);
        
        echo json_encode([
            'method' => 'debugPhoto',
            'raw_key' => $_GET['key'] ?? '',
            'decoded_key' => $photoKey,
            'server_request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'server_query_string' => $_SERVER['QUERY_STRING'] ?? ''
        ]);
    }

    public function testProxyPhoto(): void
    {
        header('Content-Type: application/json');
        
        $photoKey = $_GET['key'] ?? '';
        $photoKey = urldecode($photoKey);
        
        if ($photoKey === '') {
            echo json_encode(['error' => 'Photo key is required']);
            return;
        }
        
        // Test signed URL
        $resp = Supabase::storageCreateSignedUrl(Config::SUPABASE_STORAGE_BUCKET, $photoKey, 3600);
        $signedUrl = $resp['data']['signedURL'] ?? ($resp['data']['signedUrl'] ?? null);
        
        if (($resp['status'] ?? 500) < 400 && $signedUrl) {
            if (strpos($signedUrl, 'http') !== 0) { 
                $signedUrl = Config::SUPABASE_URL . $signedUrl; 
            }
            
            // Test fetch the image
            $ch = curl_init($signedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo json_encode([
                'status' => 'success',
                'key' => $photoKey,
                'signed_url' => $signedUrl,
                'image_fetch_code' => $httpCode,
                'image_size' => strlen($imageData)
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'key' => $photoKey,
                'signed_url_response' => $resp
            ]);
        }
    }

    public function scanBucket(): void
    {
        header('Content-Type: application/json');
        
        // Test dengan key yang benar dari screenshot
        $correctKey = '20251001/CAP3213815224843299013_watermarked.jpg';
        
        // Test signed URL
        $resp = Supabase::storageCreateSignedUrl(Config::SUPABASE_STORAGE_BUCKET, $correctKey, 3600);
        $signedUrl = $resp['data']['signedURL'] ?? ($resp['data']['signedUrl'] ?? null);
        
        // Test public URL
        $publicUrl = Config::SUPABASE_URL . '/storage/v1/object/public/' . Config::SUPABASE_STORAGE_BUCKET . '/' . $correctKey;
        
        // Test fetch signed URL
        $signedResult = null;
        if (($resp['status'] ?? 500) < 400 && $signedUrl) {
            if (strpos($signedUrl, 'http') !== 0) { 
                $signedUrl = Config::SUPABASE_URL . $signedUrl; 
            }
            
            $ch = curl_init($signedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $signedResult = [
                'url' => $signedUrl,
                'http_code' => $httpCode,
                'image_size' => strlen($imageData),
                'success' => $httpCode === 200
            ];
        }
        
        // Test fetch public URL
        $ch = curl_init($publicUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $publicData = curl_exec($ch);
        $publicCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo json_encode([
            'key' => $correctKey,
            'signed_url_response' => $resp,
            'signed_result' => $signedResult,
            'public_url' => $publicUrl,
            'public_result' => [
                'http_code' => $publicCode,
                'data_size' => strlen($publicData),
                'success' => $publicCode === 200
            ]
        ]);
    }

    public function checkBucket(): void
    {
        header('Content-Type: application/json');
        
        // Cek apakah bucket ada
        $url = Config::SUPABASE_URL . '/storage/v1/bucket/' . Config::SUPABASE_STORAGE_BUCKET;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . Config::SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . Config::SUPABASE_SERVICE_ROLE_KEY,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log('[StorageController.checkBucket] Bucket check - Code: ' . $code . ', Response: ' . $resp);
        
        if ($code === 200) {
            $bucketData = json_decode($resp, true);
            echo json_encode([
                'status' => 'exists', 
                'message' => 'Bucket exists', 
                'bucket_data' => $bucketData,
                'is_public' => $bucketData['public'] ?? false
            ]);
        } else {
            // Coba buat bucket
            $createUrl = Config::SUPABASE_URL . '/storage/v1/bucket';
            $ch = curl_init($createUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . Config::SUPABASE_SERVICE_ROLE_KEY,
                'apikey: ' . Config::SUPABASE_SERVICE_ROLE_KEY,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'id' => Config::SUPABASE_STORAGE_BUCKET,
                'name' => Config::SUPABASE_STORAGE_BUCKET,
                'public' => true
            ]));
            $createResp = curl_exec($ch);
            $createCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log('[StorageController.checkBucket] Bucket create - Code: ' . $createCode . ', Response: ' . $createResp);
            
            echo json_encode([
                'status' => $createCode === 200 ? 'created' : 'error',
                'message' => $createCode === 200 ? 'Bucket created' : 'Failed to create bucket',
                'response' => $createResp
            ]);
        }
    }
}



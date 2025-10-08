<?php
declare(strict_types=1);

namespace App;

class Config
{
    public const DB_DSN = 'mysql:host=localhost;dbname=presensi;charset=utf8mb4';
    public const DB_USER = 'root';
    public const DB_PASS = '';
    public const APP_KEY = 'change-me-secret-key';

    // Supabase
    public const SUPABASE_URL = 'https://vtzekxhxwrebjpwktmat.supabase.co';
    public const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZ0emVreGh4d3JlYmpwd2t0bWF0Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTg4NzA1MDYsImV4cCI6MjA3NDQ0NjUwNn0.hHWENv3wR-YrOWwq1SRF3WeiEODuczelPnWm5mMY_sc';
    public const SUPABASE_SERVICE_ROLE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZ0emVreGh4d3JlYmpwd2t0bWF0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1ODg3MDUwNiwiZXhwIjoyMDc0NDQ2NTA2fQ.j4AUtvnGjwj5jEQfRDkhCk_RF-eLlt75vXyqHUm_glY';
    public const SUPABASE_STORAGE_BUCKET = 'attendance-photos';
}



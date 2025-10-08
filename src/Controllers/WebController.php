<?php
declare(strict_types=1);

namespace App\Controllers;

class WebController
{
    public function dashboard(): void
    {
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .profile-circle { 
            width: 40px; height: 40px; border-radius: 50%; background: #007bff; 
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: bold;
        }
        .profile-popup { 
            position: absolute; top: 60px; right: 20px; background: white; 
            border: 1px solid #ddd; border-radius: 8px; padding: 15px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; z-index: 1000;
            min-width: 200px;
        }
        .profile-popup.show { display: block; }
        .profile-info { margin-bottom: 10px; }
        .profile-photo { width: 60px; height: 60px; border-radius: 50%; background: #f0f0f0; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard</h1>
        <div style="position: relative;">
            <div class="profile-circle" onclick="toggleProfile()">👤</div>
            <div class="profile-popup" id="profilePopup">
                <div class="profile-photo"></div>
                <div class="profile-info"><strong>Nama:</strong> <span id="userName">Loading...</span></div>
                <div class="profile-info"><strong>Email:</strong> <span id="userEmail">Loading...</span></div>
                <div class="profile-info"><strong>No HP:</strong> <span id="userPhone">Loading...</span></div>
                <div class="profile-info"><strong>OS Number:</strong> <span id="userOS">Loading...</span></div>
                <hr>
                <a href="/logout">Logout</a>
            </div>
        </div>
    </div>
    
    <p><a href="/users">Kelola Users</a> | <a href="/monitoring">Monitoring</a></p>
    
    <script>
        function toggleProfile() {
            const popup = document.getElementById("profilePopup");
            popup.classList.toggle("show");
            
            // Load user data if not loaded yet
            if (!popup.dataset.loaded) {
                loadUserProfile();
                popup.dataset.loaded = "true";
            }
        }
        
        function loadUserProfile() {
            // Mock data - replace with actual API call
            document.getElementById("userName").textContent = "John Doe";
            document.getElementById("userEmail").textContent = "john@example.com";
            document.getElementById("userPhone").textContent = "08123456789";
            document.getElementById("userOS").textContent = "EMP001";
        }
        
        // Close popup when clicking outside
        document.addEventListener("click", function(e) {
            const popup = document.getElementById("profilePopup");
            const circle = document.querySelector(".profile-circle");
            if (!popup.contains(e.target) && !circle.contains(e.target)) {
                popup.classList.remove("show");
            }
        });
    </script>
</body>
</html>';
    }
}



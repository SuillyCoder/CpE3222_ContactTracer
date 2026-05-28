<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Default XAMPP MySQL user
define('DB_PASS', '');            // Default XAMPP MySQL password (empty)
define('DB_NAME', 'cpe_contact_tracing');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // If this is an AJAX/API request, return JSON; otherwise show a friendly page error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
    }
    die('<div style="font-family:sans-serif;padding:40px;color:#b94040;">
        <h2>⚠️ Database Unavailable</h2>
        <p>Could not connect to MySQL. Please make sure MySQL is running in XAMPP.</p>
        <p style="color:#999;font-size:12px;">Error: ' . htmlspecialchars($conn->connect_error) . '</p>
    </div>');
}

// Set charset to UTF-8 for proper encoding
$conn->set_charset('utf8mb4');
?>
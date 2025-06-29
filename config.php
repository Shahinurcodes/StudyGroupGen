<?php
// Database Configuration
// Modify these values according to your MySQL setup

// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Empty password after MySQL reset
define('DB_NAME', 'studygroupgen');

// Create database connection
function getConnection() {
    try {
        $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Create database if it doesn't exist
        $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
        if ($conn->query($sql) === FALSE) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        // Select the database
        if (!$conn->select_db(DB_NAME)) {
            throw new Exception("Error selecting database: " . $conn->error);
        }
        
        // Set charset to utf8mb4
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw $e;
    }
}

// CSRF Protection Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

function getCSRFTokenField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// Test connection function
function testConnection() {
    try {
        $conn = getConnection();
        $result = "Database connection successful!";
        $conn->close();
        return $result;
    } catch (Exception $e) {
        return "Database connection failed: " . $e->getMessage();
    }
}

// Validate database tables
function validateDatabase() {
    try {
        $conn = getConnection();
        
        $required_tables = [
            'students', 'faculty', 'courses', 'groups', 'group_members',
            'student_courses', 'messages', 'sessions', 'notes', 'session_attendance',
            'announcements', 'study_materials', 'group_progress'
        ];
        
        $missing_tables = [];
        foreach ($required_tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                $missing_tables[] = $table;
            }
        }
        
        $conn->close();
        
        if (empty($missing_tables)) {
            return "All required tables exist.";
        } else {
            return "Missing tables: " . implode(', ', $missing_tables);
        }
    } catch (Exception $e) {
        return "Database validation failed: " . $e->getMessage();
    }
}
?> 
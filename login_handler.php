<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'config.php';

// Set JSON header
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'An unexpected error occurred.'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate input
        if (!isset($_POST['email']) || !isset($_POST['password'])) {
            $response['message'] = 'Email and password are required.';
            echo json_encode($response);
            exit();
        }
        
        // Get database connection
        $conn = getConnection();
        
        $email = $conn->real_escape_string(trim($_POST['email']));
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $response['message'] = 'Email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid email format.';
        } else {
            $user = null;
            $user_type = '';

            // Check students table first
            $stmt = $conn->prepare("SELECT id, full_name, password, department, trimester, cgpa FROM students WHERE email = ? AND email IS NOT NULL");
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                if (password_verify($password, $row['password'])) {
                    $user = $row;
                    $user_type = 'student';
                }
            }
            $stmt->close();

            // If not found in students, check faculty
            if (!$user) {
                $stmt = $conn->prepare("SELECT id, first_name, last_name, password, department FROM faculty WHERE email = ? AND email IS NOT NULL");
                if (!$stmt) {
                    throw new Exception("Database error: " . $conn->error);
                }
                
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $row = $result->fetch_assoc();
                    if (password_verify($password, $row['password'])) {
                        $user = $row;
                        $user_type = 'faculty';
                    }
                }
                $stmt->close();
            }

            if ($user) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user_type;
                $_SESSION['user_department'] = $user['department'];
                
                if ($user_type === 'student') {
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['student_trimester'] = $user['trimester'];
                    $_SESSION['student_cgpa'] = $user['cgpa'];
                } else {
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                }

                // Update last login time
                $update_stmt = $conn->prepare("UPDATE " . ($user_type === 'student' ? 'students' : 'faculty') . " SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("i", $user['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }

                $response['status'] = 'success';
                $response['message'] = 'Login successful! Redirecting...';
                $response['user_type'] = $user_type;
                $response['user_name'] = $_SESSION['user_name'];
            } else {
                $response['message'] = 'Invalid email or password.';
            }
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?> 
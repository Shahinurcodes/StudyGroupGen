<?php
// Start session at the very top
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'config.php';

// Default response
$response = ['status' => 'error', 'message' => 'An unexpected error occurred.'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set JSON header for AJAX requests
    header('Content-Type: application/json');
    try {
        // Validate required fields
        $required_fields = ['full_name', 'student_id', 'email', 'password', 'confirmPassword', 'department', 'trimester', 'cgpa'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                $response['message'] = "Field '$field' is required.";
                echo json_encode($response);
                exit();
            }
        }
        
        // Get database connection
        $conn = getConnection();
        
        // Sanitize and retrieve form data (no need for real_escape_string with prepared statements)
        $full_name = trim($_POST['full_name']);
        $student_id = trim($_POST['student_id']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];
        $department = trim($_POST['department']);
        $trimester = (int)$_POST['trimester'];
        $cgpa = (float)$_POST['cgpa'];
        $enrollment_date = date('Y-m-d');

        // Server-side validation
        if (strlen($full_name) < 2 || strlen($full_name) > 100) {
            $response['message'] = 'Full name must be between 2 and 100 characters.';
        } elseif (strlen($student_id) < 3 || strlen($student_id) > 20) {
            $response['message'] = 'Student ID must be between 3 and 20 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid email format provided.';
        } elseif (strlen($password) < 6) {
            $response['message'] = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $response['message'] = 'Passwords do not match.';
        } elseif (!in_array($department, ['cse', 'eee', 'bba', 'eco', 'eng'])) {
            $response['message'] = 'Invalid department selected.';
        } elseif ($trimester < 1 || $trimester > 12) {
            $response['message'] = 'Trimester must be between 1 and 12.';
        } elseif ($cgpa < 0 || $cgpa > 4) {
            $response['message'] = 'CGPA must be a value between 0.00 and 4.00.';
        } else {
            // Check for existing student ID or email
            $stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ? OR email = ?");
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param("ss", $student_id, $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $response['message'] = 'An account with this Student ID or Email already exists.';
            } else {
                // Hash the password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new student record
                $insert_stmt = $conn->prepare("INSERT INTO students (full_name, student_id, email, password, department, trimester, cgpa, enrollment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$insert_stmt) {
                    throw new Exception("Database error: " . $conn->error);
                }
                
                $insert_stmt->bind_param("sssssids", $full_name, $student_id, $email, $hashed_password, $department, $trimester, $cgpa, $enrollment_date);

                if ($insert_stmt->execute()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Registration successful! You can now log in.';
                } else {
                    $response['message'] = 'Failed to register. Please try again later.';
                }
                $insert_stmt->close();
            }
            $stmt->close();
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Student registration error: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }

    // Return JSON response
    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | Study Group Generator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --primary: #4a6bff;
            --secondary: #6c757d;
            --dark: #343a40;
            --light: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Logo & Title */
        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 2.5rem;
        }

        h1 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 30px;
            text-align: center;
        }

        /* Registration Form */
        .register-form {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-header p {
            color: var(--secondary);
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        .form-group {
            flex: 1;
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.2);
        }

        .form-footer {
            margin-top: 30px;
            text-align: center;
        }

        /* Buttons */
        .btn {
            padding: 12px 30px;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #3a5bef;
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        /* Message Display */
        .message-container {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
            display: none;
            font-weight: 500;
        }

        .message-container.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .message-container.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        /* Login Link */
        .login-link {
            margin-top: 20px;
            color: var(--secondary);
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Logo -->
    <div class="logo">
        <i class="fas fa-user-graduate"></i>
    </div>

    <!-- Registration Form -->
    <div class="register-form">
        <div class="form-header">
            <h1>Student Registration</h1>
            <p>Create your account to join study groups</p>
        </div>
        
        <div id="message" class="message-container"></div>
        
        <form id="registrationForm" action="student_register.php" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" name="full_name" placeholder="Enter your full name" required minlength="2" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="studentId">Student ID *</label>
                    <input type="text" id="studentId" name="student_id" placeholder="Enter your student ID" required minlength="3" maxlength="20">
                </div>
            </div>

            <div class="form-group">
                <label for="email">University Email *</label>
                <input type="email" id="email" name="email" placeholder="Enter your university email" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="department">Department *</label>
                    <select id="department" name="department" required>
                        <option value="">Select your department</option>
                        <option value="cse">Computer Science & Engineering</option>
                        <option value="eee">Electrical & Electronic Engineering</option>
                        <option value="bba">Business Administration</option>
                        <option value="eco">Economics</option>
                        <option value="eng">English</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="trimester">Current Trimester *</label>
                    <select id="trimester" name="trimester" required>
                        <option value="">Select trimester</option>
                        <option value="1">Trimester 1</option>
                        <option value="2">Trimester 2</option>
                        <option value="3">Trimester 3</option>
                        <option value="4">Trimester 4</option>
                        <option value="5">Trimester 5</option>
                        <option value="6">Trimester 6</option>
                        <option value="7">Trimester 7</option>
                        <option value="8">Trimester 8</option>
                        <option value="9">Trimester 9</option>
                        <option value="10">Trimester 10</option>
                        <option value="11">Trimester 11</option>
                        <option value="12">Trimester 12</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="cgpa">Current CGPA *</label>
                <input type="number" id="cgpa" name="cgpa" min="0" max="4" step="0.01" placeholder="Enter your CGPA" required>
            </div>

            <!-- Password Fields -->
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Create Password *</label>
                    <input type="password" id="password" name="password" placeholder="Create a password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password *</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required minlength="6">
                </div>
            </div>

            <div class="form-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Register as Student
                </button>
                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const cgpa = parseFloat(document.getElementById('cgpa').value);
        const messageDiv = document.getElementById('message');
        
        // Clear previous messages
        messageDiv.style.display = 'none';
        messageDiv.innerHTML = '';
        messageDiv.className = 'message-container';
        
        // Client-side validation
        if (password !== confirmPassword) {
            messageDiv.style.display = 'block';
            messageDiv.className = 'message-container error';
            messageDiv.innerHTML = 'Passwords do not match!';
            return;
        }
        
        if (isNaN(cgpa) || cgpa < 0 || cgpa > 4) {
            messageDiv.style.display = 'block';
            messageDiv.className = 'message-container error';
            messageDiv.innerHTML = 'Please enter a valid CGPA between 0.00 and 4.00';
            return;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
        submitBtn.disabled = true;
        
        const formData = new FormData(this);
        fetch('student_register.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            messageDiv.style.display = 'block';
            messageDiv.className = 'message-container ' + (data.status === 'success' ? 'success' : 'error');
            messageDiv.innerHTML = data.message;
            
            if (data.status === 'success') {
                setTimeout(() => { 
                    window.location.href = 'login.php'; 
                }, 1500);
            }
        })
        .catch(error => {
            messageDiv.style.display = 'block';
            messageDiv.className = 'message-container error';
            messageDiv.innerHTML = 'An error occurred during registration. Please try again.';
            console.error('Error:', error);
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Test button functionality
    console.log('Registration form loaded successfully');
    </script>
</body>
</html> 
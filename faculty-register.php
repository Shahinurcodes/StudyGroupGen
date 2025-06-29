<?php
// Start session at the very top
session_start();

// Include database configuration
require_once 'config.php';

// Default response
$response = ['status' => 'error', 'message' => 'An unexpected error occurred.'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set JSON header for AJAX requests
    header('Content-Type: application/json');
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'faculty_id', 'email', 'password', 'confirmPassword', 'department'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                $response['message'] = "Field '$field' is required.";
                echo json_encode($response);
                exit();
            }
        }
        
        // Get database connection
        $conn = getConnection();
        
        // Sanitize and retrieve form data
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $faculty_id = trim($_POST['faculty_id']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];
        $department = trim($_POST['department']);
        $specializations = (isset($_POST['specializations']) && is_array($_POST['specializations'])) ? implode(', ', $_POST['specializations']) : '';
        $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';

        // Server-side validation
        if (strlen($first_name) < 2 || strlen($first_name) > 50) {
            $response['message'] = 'First name must be between 2 and 50 characters.';
        } elseif (strlen($last_name) < 2 || strlen($last_name) > 50) {
            $response['message'] = 'Last name must be between 2 and 50 characters.';
        } elseif (strlen($faculty_id) < 3 || strlen($faculty_id) > 20) {
            $response['message'] = 'Faculty ID must be between 3 and 20 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid email format provided.';
        } elseif (strlen($password) < 6) {
            $response['message'] = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $response['message'] = 'Passwords do not match.';
        } elseif (!in_array($department, ['cse', 'eee', 'bba', 'eco', 'eng', 'mat', 'phy'])) {
            $response['message'] = 'Invalid department selected.';
        } else {
            // Check for existing faculty ID or email
            $stmt = $conn->prepare("SELECT id FROM faculty WHERE faculty_id = ? OR email = ?");
            if (!$stmt) {
                throw new Exception("Database error occurred");
            }
            
            $stmt->bind_param("ss", $faculty_id, $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $response['message'] = 'An account with this Faculty ID or Email already exists.';
            } else {
                // Hash the password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new faculty record
                $insert_stmt = $conn->prepare("INSERT INTO faculty (first_name, last_name, faculty_id, email, password, department, specializations, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$insert_stmt) {
                    throw new Exception("Database error occurred");
                }
                
                $insert_stmt->bind_param("ssssssss", $first_name, $last_name, $faculty_id, $email, $hashed_password, $department, $specializations, $bio);

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
        error_log("Faculty registration error: " . $e->getMessage());
        $response['message'] = 'An error occurred during registration. Please try again.';
    }

    // Return JSON response
    echo json_encode($response);
    exit();
}

// Display registration message if exists
$registration_message = '';
$registration_status = '';
if (isset($_SESSION['registration_message'])) {
    $registration_message = $_SESSION['registration_message'];
    $registration_status = $_SESSION['registration_status'];
    unset($_SESSION['registration_message']);
    unset($_SESSION['registration_status']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Registration | Study Group Generator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --primary: #28a745;
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

        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
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
            background: #218838;
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

        /* Checkbox styles */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
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
        <i class="fas fa-chalkboard-teacher"></i>
    </div>

    <!-- Registration Form -->
    <div class="register-form">
        <div class="form-header">
            <h1>Faculty Registration</h1>
            <p>Create your faculty account to manage study groups</p>
        </div>
        
        <div id="message" class="message-container"></div>
        
        <form id="registrationForm" action="faculty-register.php" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name *</label>
                    <input type="text" id="firstName" name="first_name" placeholder="Enter your first name" required minlength="2" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name *</label>
                    <input type="text" id="lastName" name="last_name" placeholder="Enter your last name" required minlength="2" maxlength="50">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="facultyId">Faculty ID *</label>
                    <input type="text" id="facultyId" name="faculty_id" placeholder="Enter your faculty ID" required minlength="3" maxlength="20">
                </div>
                <div class="form-group">
                    <label for="email">University Email *</label>
                    <input type="email" id="email" name="email" placeholder="Enter your university email" required>
                </div>
            </div>

            <div class="form-group">
                <label for="department">Department *</label>
                <select id="department" name="department" required>
                    <option value="">Select your department</option>
                    <option value="cse">Computer Science & Engineering</option>
                    <option value="eee">Electrical & Electronic Engineering</option>
                    <option value="bba">Business Administration</option>
                    <option value="eco">Economics</option>
                    <option value="eng">English</option>
                    <option value="mat">Mathematics</option>
                    <option value="phy">Physics</option>
                </select>
            </div>

            <div class="form-group">
                <label>Specializations</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="spec1" name="specializations[]" value="Algorithms">
                        <label for="spec1">Algorithms</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="spec2" name="specializations[]" value="Data Structures">
                        <label for="spec2">Data Structures</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="spec3" name="specializations[]" value="Database Systems">
                        <label for="spec3">Database Systems</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="spec4" name="specializations[]" value="Software Engineering">
                        <label for="spec4">Software Engineering</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="spec5" name="specializations[]" value="Artificial Intelligence">
                        <label for="spec5">Artificial Intelligence</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="spec6" name="specializations[]" value="Machine Learning">
                        <label for="spec6">Machine Learning</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="spec7" name="specializations[]" value="Web Development">
                        <label for="spec7">Web Development</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="spec8" name="specializations[]" value="Mobile Development">
                        <label for="spec8">Mobile Development</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio" rows="4" placeholder="Tell us about your teaching experience and expertise"></textarea>
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
                    <i class="fas fa-user-plus"></i> Register as Faculty
                </button>
                <div class="login-link">
                    Already have an account? <a href="faculty_login.php">Login here</a>
                </div>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
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
        
        if (password.length < 6) {
            messageDiv.style.display = 'block';
            messageDiv.className = 'message-container error';
            messageDiv.innerHTML = 'Password must be at least 6 characters long!';
            return;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
        submitBtn.disabled = true;
        
        const formData = new FormData(this);
        fetch('faculty-register.php', {
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
                    window.location.href = 'faculty_login.php'; 
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
    console.log('Faculty registration form loaded successfully');
    </script>
</body>
</html>
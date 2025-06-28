<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Group Generator | Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Login page specific styles */
        :root {
            --primary: #4a6bff;
            --primary-dark: #3a5bef;
            --white: #ffffff;
            --gray-200: #e9ecef;
            --gray-600: #6c757d;
            --gray-900: #212529;
            --spacing-xs: 4px;
            --spacing-sm: 8px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            --radius-full: 50%;
            --radius-lg: 12px;
            --font-size-sm: 0.875rem;
            --font-size-4xl: 2.25rem;
            --font-size-3xl: 1.875rem;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --transition-base: 0.3s ease;
        }

        body {
            background: var(--white);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: var(--spacing-lg);
            text-align: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto var(--spacing-lg);
            background: var(--primary);
            border-radius: var(--radius-full);
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--white);
            font-size: var(--font-size-4xl);
        }

        h1 {
            font-size: var(--font-size-4xl);
            color: var(--gray-900);
            margin-bottom: var(--spacing-lg);
        }

        .login-form {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .form-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
        }

        .form-group {
            margin-bottom: var(--spacing-md);
        }

        .form-label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--gray-900);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.2);
        }

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
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-lg {
            padding: 15px 40px;
            font-size: 1.1rem;
        }

        .forgot-password {
            display: block;
            text-align: right;
            margin-top: var(--spacing-xs);
            color: var(--primary);
            text-decoration: none;
            font-size: var(--font-size-sm);
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .register-link {
            margin-top: var(--spacing-lg);
            color: var(--gray-600);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .student-link {
            margin-top: var(--spacing-md);
            color: var(--gray-600);
        }

        .student-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .student-link a:hover {
            text-decoration: underline;
        }

        /* Message Display */
        .message-container {
            position: fixed;
            top: var(--spacing-lg);
            right: var(--spacing-lg);
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            color: var(--white);
            z-index: 1000;
            box-shadow: var(--shadow-lg);
            font-weight: 500;
            max-width: 300px;
            word-wrap: break-word;
            opacity: 0;
            transform: translateX(100%);
            transition: all var(--transition-base);
        }

        .message-container.show {
            opacity: 1;
            transform: translateX(0);
        }

        .message-container.success {
            background: rgba(40, 167, 69, 0.95);
        }

        .message-container.error {
            background: rgba(220, 53, 69, 0.95);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            h1 {
                font-size: var(--font-size-3xl);
            }
            
            .login-form {
                padding: 0 var(--spacing-md);
            }

            .message-container {
                right: var(--spacing-md);
                left: var(--spacing-md);
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <!-- Logo -->
    <div class="logo">
        <i class="fas fa-users"></i>
    </div>

    <!-- Title -->
    <h1>University Study Group Generator</h1>

    <!-- Login Form -->
    <div class="login-form">
        <div class="form-card">
            <div id="message" style="display:none; margin-bottom:20px; padding: 10px; border-radius: 5px;"></div>
            <form id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>
        
        <div class="register-link">
            Don't have an account? <a href="student_register.php">Register here</a>
        </div>
        
        <div class="student-link">
            Are you a faculty member? <a href="faculty_login.php">Faculty login here</a>
        </div>
    </div>

    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const messageDiv = document.getElementById('message');
        
        // Clear previous messages
        messageDiv.style.display = 'none';
        messageDiv.innerHTML = '';
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        submitBtn.disabled = true;
        
        fetch('login_handler.php', {
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
            messageDiv.style.color = data.status === 'success' ? 'white' : 'white';
            messageDiv.style.backgroundColor = data.status === 'success' ? '#28a745' : '#dc3545';
            messageDiv.innerHTML = data.message;
            
            if (data.status === 'success') {
                setTimeout(() => {
                    if (data.user_type === 'student') {
                        window.location.href = 'dashboard.php';
                    } else if (data.user_type === 'faculty') {
                        window.location.href = 'faculty_dashboard.php';
                    }
                }, 1500);
            }
        })
        .catch(error => {
            messageDiv.style.display = 'block';
            messageDiv.style.color = 'white';
            messageDiv.style.backgroundColor = '#dc3545';
            messageDiv.innerHTML = 'An error occurred. Please try again.';
            console.error('Error:', error);
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Test button functionality
    console.log('Login form loaded successfully');
    </script>
</body>
</html>
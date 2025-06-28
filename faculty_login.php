<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Login | Study Group Generator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --primary: #28a745;
            --secondary: #6c757d;
            --dark: #343a40;
            --light: #f8f9fa;
            --error: #dc3545;
            --faculty-primary: #28a745;
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
            text-align: center;
        }

        /* Logo & Title */
        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: var(--faculty-primary);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 2.5rem;
        }

        h1 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .subtitle {
            color: var(--secondary);
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        /* Login Form */
        .login-form {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus {
            border-color: var(--faculty-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
        }

        .forgot-password {
            display: block;
            text-align: right;
            margin-top: 5px;
            color: var(--faculty-primary);
            text-decoration: none;
            font-size: 0.9rem;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin: 20px 0;
        }

        .btn-primary {
            background: var(--faculty-primary);
            color: white;
        }

        .btn-primary:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--faculty-primary);
            border: 2px solid var(--faculty-primary);
        }

        .btn-outline:hover {
            background: rgba(40, 167, 69, 0.1);
            transform: translateY(-2px);
        }

        .register-link {
            margin-top: 20px;
            color: var(--secondary);
        }

        .register-link a {
            color: var(--faculty-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .student-link {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .student-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        /* Message Display */
        .message-container {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-weight: 500;
            max-width: 300px;
            word-wrap: break-word;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
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
                font-size: 2rem;
            }
            
            .login-form {
                padding: 0 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Logo -->
    <div class="logo">
        <i class="fas fa-chalkboard-teacher"></i>
    </div>

    <!-- Title -->
    <h1>Faculty Login</h1>
    <p class="subtitle">Access your faculty dashboard</p>

    <!-- Login Form -->
    <div class="login-form">
        <div id="message" style="display:none; margin-bottom:20px; padding:15px; border-radius:5px;"></div>
        <form id="facultyLoginForm">
            <div class="form-group">
                <label for="email">Faculty Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your faculty email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <a href="#" class="forgot-password">Forgot password?</a>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Faculty Login
            </button>
        </form>
        
        <div class="register-link">
            Don't have a faculty account? <a href="faculty-register.php">Register here</a>
        </div>
        
        <div class="student-link">
            Are you a student? <a href="login.php">Student login here</a>
        </div>
    </div>

    <script>
        document.getElementById('facultyLoginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById('message');
            
            messageDiv.style.display = 'none';
            messageDiv.innerHTML = '';

            fetch('login_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.style.display = 'block';
                if (data.status === 'success') {
                    messageDiv.style.backgroundColor = 'rgba(40, 167, 69, 0.1)';
                    messageDiv.style.color = 'green';
                    messageDiv.style.border = '1px solid rgba(40, 167, 69, 0.2)';
                    messageDiv.innerHTML = data.message;
                    setTimeout(() => {
                        if (data.user_type === 'faculty') {
                            window.location.href = 'faculty_dashboard.php';
                        } else {
                            messageDiv.style.backgroundColor = 'rgba(220, 53, 69, 0.1)';
                            messageDiv.style.color = 'red';
                            messageDiv.style.border = '1px solid rgba(220, 53, 69, 0.2)';
                            messageDiv.innerHTML = 'This login is for faculty members only. Please use the student login.';
                        }
                    }, 1500);
                } else {
                    messageDiv.style.backgroundColor = 'rgba(220, 53, 69, 0.1)';
                    messageDiv.style.color = 'red';
                    messageDiv.style.border = '1px solid rgba(220, 53, 69, 0.2)';
                    messageDiv.innerHTML = data.message;
                }
            })
            .catch(error => {
                messageDiv.style.display = 'block';
                messageDiv.style.backgroundColor = 'rgba(220, 53, 69, 0.1)';
                messageDiv.style.color = 'red';
                messageDiv.style.border = '1px solid rgba(220, 53, 69, 0.2)';
                messageDiv.innerHTML = 'An error occurred. Please try again.';
            });
        });
    </script>
</body>
</html> 
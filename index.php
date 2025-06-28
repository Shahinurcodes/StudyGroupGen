<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Group Generator | Welcome</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Landing page specific styles */
        body {
            background: var(--white);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: var(--spacing-lg);
            text-align: center;
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
            margin-bottom: var(--spacing-sm);
        }

        .quote {
            font-size: var(--font-size-xl);
            color: var(--gray-600);
            margin-bottom: var(--spacing-xl);
            font-style: italic;
            position: relative;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .quote::before, .quote::after {
            content: '"';
            color: var(--primary);
            font-size: var(--font-size-2xl);
        }

        .login-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-lg);
            margin: var(--spacing-xl) 0;
            max-width: 600px;
            width: 100%;
        }

        .login-card {
            background: var(--white);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            transition: all var(--transition-base);
            cursor: pointer;
        }

        .login-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .login-card.faculty:hover {
            border-color: var(--faculty-primary);
        }

        .login-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-full);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto var(--spacing-md);
            font-size: var(--font-size-2xl);
            color: var(--white);
        }

        .login-icon.student {
            background: var(--primary);
        }

        .login-icon.faculty {
            background: var(--faculty-primary);
        }

        .login-title {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-sm);
        }

        .login-description {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
            margin-bottom: var(--spacing-lg);
        }

        .registration-section {
            margin-top: var(--spacing-xl);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--gray-200);
            width: 100%;
            max-width: 600px;
        }

        .registration-text {
            color: var(--gray-600);
            margin-bottom: var(--spacing-md);
        }

        .registration-buttons {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
            flex-wrap: wrap;
        }

        .footer {
            margin-top: var(--spacing-2xl);
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            h1 {
                font-size: var(--font-size-3xl);
            }

            .login-options {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }

            .registration-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                max-width: 250px;
            }
        }

        /* Animation */
        body {
            animation: fadeIn 0.8s ease-out;
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

    <!-- Quote -->
    <p class="quote">Collaborate. Learn. Succeed.</p>

    <!-- Login Options -->
    <div class="login-options">
        <!-- Student Login -->
        <div class="login-card" onclick="window.location.href='login.php'">
            <div class="login-icon student">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="login-title">Student Login</div>
            <div class="login-description">Access your student dashboard to join study groups and view courses</div>
            <button class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login as Student
            </button>
        </div>

        <!-- Faculty Login -->
        <div class="login-card faculty" onclick="window.location.href='faculty_login.php'">
            <div class="login-icon faculty">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="login-title">Faculty Login</div>
            <div class="login-description">Access your faculty dashboard to mentor study groups</div>
            <button class="btn btn-success">
                <i class="fas fa-sign-in-alt"></i> Login as Faculty
            </button>
        </div>
    </div>

    <!-- Registration Section -->
    <div class="registration-section">
        <p class="registration-text">Don't have an account? Register here:</p>
        <div class="registration-buttons">
            <button class="btn btn-outline" onclick="window.location.href='student_register.php'">
                <i class="fas fa-user-plus"></i> Student Registration
            </button>
            <button class="btn btn-outline-success" onclick="window.location.href='faculty-register.php'">
                <i class="fas fa-user-plus"></i> Faculty Registration
            </button>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>© 2024 Study Group Generator. All rights reserved.</p>
    </div>
</body>
</html>
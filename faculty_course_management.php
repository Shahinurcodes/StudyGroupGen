<?php
session_start();

// Redirect to login if not logged in or not a faculty member
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'faculty') {
    header('Location: login.php');
    exit();
}

$faculty_name = htmlspecialchars($_SESSION['user_name']);
$faculty_initials = '';
if (!empty($faculty_name)) {
    $name_parts = explode(' ', $faculty_name);
    if (count($name_parts) >= 2) {
        $faculty_initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
    } else {
        $faculty_initials = strtoupper(substr($faculty_name, 0, 2));
    }
}

// Fetch course management data
require_once 'config.php';
try {
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get faculty info
    $stmt = $conn->prepare("SELECT first_name, last_name, department FROM faculty WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $faculty = $result->fetch_assoc();
    $stmt->close();
    
<<<<<<< HEAD
    // DEBUG: Show error if faculty or department is missing
    if (!$faculty || empty($faculty['department'])) {
        echo "<div style='color:red; font-weight:bold; padding:10px; background:#fff3cd; border:1px solid #ffeeba; margin:20px 0;'>Faculty department not set or faculty not found. Please check your faculty record in the database.</div>";
    }
    
=======
>>>>>>> 30b4f3b1a93181c970c99738e50718b7b04b4735
    // Get faculty's assigned courses
    $stmt = $conn->prepare("SELECT c.*, 
                           (SELECT COUNT(*) FROM groups WHERE course_id = c.id) as group_count
                           FROM courses c 
                           WHERE c.faculty_id = ? AND c.is_active = 1 
                           ORDER BY c.trimester, c.course_code");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assigned_courses = [];
    while ($row = $result->fetch_assoc()) {
        $assigned_courses[] = $row;
    }
    $stmt->close();
    
    // Get available courses that faculty can assign themselves to (unassigned courses in their department)
    $stmt = $conn->prepare("SELECT c.*, 
                           (SELECT COUNT(*) FROM groups WHERE course_id = c.id) as group_count
                           FROM courses c 
                           WHERE c.faculty_id IS NULL AND c.department = ? AND c.is_active = 1 
                           ORDER BY c.trimester, c.course_code");
    $stmt->bind_param("s", $faculty['department']);
    $stmt->execute();
    $result = $stmt->get_result();
    $available_courses = [];
    while ($row = $result->fetch_assoc()) {
        $available_courses[] = $row;
    }
    $stmt->close();
    
    // Get all courses in faculty's department for statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as total_courses FROM courses WHERE department = ? AND is_active = 1");
    $stmt->bind_param("s", $faculty['department']);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_courses = $result->fetch_assoc()['total_courses'];
    $stmt->close();
    
    $conn->close();
    
} catch (Exception $e) {
    $error_message = "Database connection error. Please try again later.";
    $assigned_courses = [];
    $available_courses = [];
    $total_courses = 0;
    $faculty = null;
}

// Calculate statistics
$assigned_count = count($assigned_courses);
$available_count = count($available_courses);
$total_groups = 0;
foreach ($assigned_courses as $course) {
    $total_groups += $course['group_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management | Study Group Generator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #28a745;
            --secondary: #6c757d;
            --dark: #343a40;
            --light: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --bg: #f5f7fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: var(--bg);
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 1.2rem;
        }

        .logo-text {
            font-weight: 600;
            color: var(--dark);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
        }

        .user-role {
            font-size: 0.9rem;
            color: var(--secondary);
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c82333;
        }

        /* Navigation */
        .nav {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 0 30px;
        }

        .nav-list {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        .nav-item a {
            display: block;
            padding: 15px 0;
            color: var(--secondary);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .nav-item a:hover,
        .nav-item a.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .message.success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .message.error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        /* Course Sections */
        .course-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            color: var(--dark);
            font-size: 1.5rem;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .course-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            transition: box-shadow 0.3s;
        }

        .course-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .course-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .course-code {
            color: var(--primary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .course-meta {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .course-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .course-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn:hover {
            background: #218838;
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .course-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="logo-text">Course Management</div>
        </div>
        <div class="user-info">
            <div class="user-details">
                <div class="user-name"><?php echo $faculty_name; ?></div>
                <div class="user-role">Faculty Member</div>
            </div>
            <div class="user-avatar"><?php echo $faculty_initials; ?></div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="faculty_dashboard.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="faculty_course_management.php" class="active">Course Management</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="container">
        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-number"><?php echo $assigned_count; ?></div>
                <div class="stat-label">Assigned Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="stat-number"><?php echo $available_count; ?></div>
                <div class="stat-label">Available Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_groups; ?></div>
                <div class="stat-label">Total Groups</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-number"><?php echo $total_courses; ?></div>
                <div class="stat-label">Department Courses</div>
            </div>
        </div>

        <!-- Assigned Courses Section -->
        <section class="course-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-check-circle"></i> My Assigned Courses
                </h2>
            </div>
            
            <?php if (empty($assigned_courses)): ?>
                <p style="text-align: center; color: var(--secondary); padding: 40px;">
                    <i class="fas fa-book" style="font-size: 3rem; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                    You haven't been assigned to any courses yet. Assign yourself to courses below!
                </p>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($assigned_courses as $course): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <div>
                                    <div class="course-title"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                </div>
                            </div>
                            
                            <div class="course-meta">
                                <div>Department: <?php echo strtoupper($course['department']); ?></div>
                                <div>Trimester: <?php echo $course['trimester']; ?> | Credits: <?php echo $course['credits']; ?></div>
                                <div>Year: <?php echo $course['year']; ?></div>
                            </div>
                            
                            <div class="course-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $course['group_count']; ?> groups</span>
                                </div>
                            </div>
                            
                            <div class="course-actions">
                                <button class="btn btn-danger" onclick="unassignCourse(<?php echo $course['id']; ?>)">
                                    <i class="fas fa-times"></i> Unassign
                                </button>
                                <a href="faculty_dashboard.php" class="btn btn-outline">
                                    <i class="fas fa-eye"></i> View Groups
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Available Courses Section -->
        <section class="course-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-plus-circle"></i> Available Courses to Assign
                </h2>
            </div>
            
            <?php if (empty($available_courses)): ?>
                <p style="text-align: center; color: var(--secondary); padding: 40px;">
                    <i class="fas fa-check" style="font-size: 3rem; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                    No available courses in your department to assign.
                </p>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($available_courses as $course): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <div>
                                    <div class="course-title"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                </div>
                            </div>
                            
                            <div class="course-meta">
                                <div>Department: <?php echo strtoupper($course['department']); ?></div>
                                <div>Trimester: <?php echo $course['trimester']; ?> | Credits: <?php echo $course['credits']; ?></div>
                                <div>Year: <?php echo $course['year']; ?></div>
                            </div>
                            
                            <div class="course-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $course['group_count']; ?> groups</span>
                                </div>
                            </div>
                            
                            <div class="course-actions">
                                <button class="btn btn-success" onclick="assignCourse(<?php echo $course['id']; ?>)">
                                    <i class="fas fa-plus"></i> Assign to Me
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // Course assignment functions
        function assignCourse(courseId) {
            if (confirm('Are you sure you want to assign this course to yourself?')) {
                const formData = new FormData();
                formData.append('course_id', courseId);
                formData.append('action', 'assign');
                
                fetch('assign_course.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showMessage(data.message, data.status === 'success' ? 'success' : 'error');
                    if (data.status === 'success') {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred. Please try again.', 'error');
                });
            }
        }

        function unassignCourse(courseId) {
            if (confirm('Are you sure you want to unassign yourself from this course? This will remove you as the faculty for this course.')) {
                const formData = new FormData();
                formData.append('course_id', courseId);
                formData.append('action', 'unassign');
                
                fetch('assign_course.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showMessage(data.message, data.status === 'success' ? 'success' : 'error');
                    if (data.status === 'success') {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred. Please try again.', 'error');
                });
            }
        }

        // Message display function
        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = `
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
                background: ${type === 'success' ? 'rgba(40, 167, 69, 0.1)' : 'rgba(220, 53, 69, 0.1)'};
                border: 1px solid ${type === 'success' ? 'var(--success)' : 'var(--danger)'};
                color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};
            `;
            messageDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${message}
            `;
            
            const container = document.querySelector('.container');
            container.insertBefore(messageDiv, container.firstChild);
            
            // Remove message after 5 seconds
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html> 
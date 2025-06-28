<?php
session_start();

// Redirect to login if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = htmlspecialchars($_SESSION['user_name']);

// Fetch student data from database
require_once 'config.php';
try {
    $conn = getConnection();
    
    // Get student details
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    // Get student's courses
    $stmt = $conn->prepare("SELECT c.*, sc.grade FROM courses c 
                           JOIN student_courses sc ON c.id = sc.course_id 
                           WHERE sc.student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
    
    // Get student's study groups
    $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name, f.first_name, f.last_name 
                           FROM groups g 
                           JOIN group_members gm ON g.id = gm.group_id 
                           JOIN courses c ON g.course_id = c.id 
                           LEFT JOIN faculty f ON g.faculty_mentor_id = f.id 
                           WHERE gm.student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
    $stmt->close();
    
    $conn->close();
    
} catch (Exception $e) {
    // Show error message instead of hardcoded data
    $error_message = "Database connection error. Please try again later.";
    $student = null;
    $courses = [];
    $groups = [];
}

// Generate initials for avatar
$initials = '';
if (!empty($student['full_name'])) {
    $name_parts = explode(' ', $student['full_name']);
    if (count($name_parts) >= 2) {
        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
    } else {
        $initials = strtoupper(substr($student['full_name'], 0, 2));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | Study Group Generator</title>
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
            background: #f5f7fa;
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

        .back-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #3a5bef;
            transform: translateY(-1px);
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            height: fit-content;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 15px;
        }

        .profile-name {
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .profile-id {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .cgpa-badge {
            background: var(--success);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }

        .profile-details {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            color: var(--secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            color: var(--dark);
            font-weight: 500;
        }

        /* Profile Content */
        .profile-content {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 20px;
            border-bottom: 2px solid var(--light);
            padding-bottom: 10px;
        }

        .courses-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .course-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .course-card:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(74, 107, 255, 0.1);
        }

        .course-code {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .course-name {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .course-meta {
            display: flex;
            justify-content: space-between;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        /* Groups Table */
        .groups-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .groups-table th {
            background: var(--primary);
            color: white;
            padding: 12px;
            text-align: left;
        }

        .groups-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .groups-table tr:hover {
            background: rgba(74, 107, 255, 0.05);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }

        .timeline-dot {
            position: absolute;
            left: -22px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid white;
        }

        .timeline-date {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .timeline-content {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
        }

        .timeline-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        /* Edit Button */
        .edit-profile {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .edit-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 20px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            
            .courses-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="logo-text">Student Profile</div>
        </div>
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </header>

    <!-- Main Content -->
    <div class="container">
        <?php if (isset($error_message)): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; grid-column: 1 / -1;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($student): ?>
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar"><?php echo $initials; ?></div>
                <h2 class="profile-name"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                <div class="profile-id">ID: <?php echo htmlspecialchars($student['student_id']); ?></div>
                <div class="cgpa-badge">CGPA: <?php echo number_format($student['cgpa'], 2); ?></div>
            </div>

            <div class="profile-details">
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-envelope"></i> Email
                    </div>
                    <div class="detail-value"><?php echo htmlspecialchars($student['email']); ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-graduation-cap"></i> Department
                    </div>
                    <div class="detail-value"><?php echo htmlspecialchars($student['department']); ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-layer-group"></i> Trimester
                    </div>
                    <div class="detail-value"><?php echo $student['trimester']; ?>th Trimester</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-calendar-alt"></i> Enrollment Date
                    </div>
                    <div class="detail-value"><?php echo date('F Y', strtotime($student['enrollment_date'])); ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-phone"></i> Contact
                    </div>
                    <div class="detail-value"><?php echo htmlspecialchars($student['contact'] ?? 'Not provided'); ?></div>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <h2 class="section-title">Current Courses</h2>
            <div class="courses-list">
                <?php if (empty($courses)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; color: var(--secondary); padding: 40px;">
                        <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                        <p>No courses enrolled yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                            <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="course-meta">
                                <span>Grade: <?php echo htmlspecialchars($course['grade'] ?? 'N/A'); ?></span>
                                <span>Credits: <?php echo $course['credits']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2 class="section-title">Study Groups</h2>
            <?php if (empty($groups)): ?>
                <div style="text-align: center; color: var(--secondary); padding: 40px;">
                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                    <p>Not enrolled in any study groups yet.</p>
                </div>
            <?php else: ?>
                <table class="groups-table">
                    <thead>
                        <tr>
                            <th>Group Name</th>
                            <th>Course</th>
                            <th>Members</th>
                            <th>Status</th>
                            <th>Faculty Mentor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $group): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($group['group_name']); ?></td>
                                <td><?php echo htmlspecialchars($group['course_code']); ?></td>
                                <td><?php echo $group['current_members']; ?>/<?php echo $group['max_members']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $group['status']; ?>">
                                        <?php echo ucfirst($group['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($group['first_name'])): ?>
                                        <?php echo htmlspecialchars($group['first_name'] . ' ' . $group['last_name']); ?>
                                    <?php else: ?>
                                        Not assigned
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 class="section-title">Academic History</h2>
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-date"><?php echo date('Y'); ?> - Present</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Bachelor of Science in <?php echo htmlspecialchars($student['department']); ?></div>
                        <p>University of Technology • Current CGPA: <?php echo number_format($student['cgpa'], 2); ?></p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-date"><?php echo date('Y', strtotime($student['enrollment_date'])) - 4; ?> - <?php echo date('Y', strtotime($student['enrollment_date'])); ?></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Higher Secondary Certificate</div>
                        <p>SPSC • GPA: 5.00/5.00</p>
                    </div>
                </div>
            </div>

            <div class="edit-profile">
                <button class="edit-btn">
                    <i class="fas fa-edit"></i> Edit Profile
                </button>
            </div>
        </div>
        <?php else: ?>
        <!-- Error State -->
        <div style="grid-column: 1 / -1; text-align: center; padding: 50px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: var(--danger); margin-bottom: 20px;"></i>
            <h2>Unable to Load Profile</h2>
            <p>There was an error loading your profile data. Please try refreshing the page.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 
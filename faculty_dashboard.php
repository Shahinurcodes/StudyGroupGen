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

// Handle group leader assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_leader') {
    try {
        require_once 'config.php';
        $conn = getConnection();
        
        $group_id = intval($_POST['group_id']);
        $student_id = intval($_POST['student_id']);
        
        // Verify faculty owns this group
        $stmt = $conn->prepare("SELECT id FROM groups WHERE id = ? AND faculty_mentor_id = ?");
        $stmt->bind_param("ii", $group_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Remove current leader
            $stmt = $conn->prepare("UPDATE group_members SET role = 'member' WHERE group_id = ? AND role = 'leader'");
            $stmt->bind_param("i", $group_id);
            $stmt->execute();
            $stmt->close();
            
            // Assign new leader
            $stmt = $conn->prepare("UPDATE group_members SET role = 'leader' WHERE group_id = ? AND student_id = ?");
            $stmt->bind_param("ii", $group_id, $student_id);
            if ($stmt->execute()) {
                $success_message = "Group leader assigned successfully!";
            } else {
                $error_message = "Failed to assign group leader.";
            }
            $stmt->close();
        } else {
            $error_message = "Group not found or you don't have permission to manage it.";
        }
        
        $conn->close();
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Fetch dashboard data
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
    
    // Get faculty's mentored groups with detailed member info
    $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name, 
                           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND role = 'leader') as leader_count
                           FROM groups g 
                           JOIN courses c ON g.course_id = c.id 
                           WHERE g.faculty_mentor_id = ? 
                           ORDER BY g.status DESC, g.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        // Get members for each group
        $member_stmt = $conn->prepare("SELECT s.id, s.full_name, s.student_id, s.cgpa, s.trimester, gm.role, gm.joined_at
                                      FROM group_members gm 
                                      JOIN students s ON gm.student_id = s.id 
                                      WHERE gm.group_id = ?
                                      ORDER BY gm.role DESC, s.full_name");
        $member_stmt->bind_param("i", $row['id']);
        $member_stmt->execute();
        $member_result = $member_stmt->get_result();
        $members = [];
        while ($member = $member_result->fetch_assoc()) {
            $members[] = $member;
        }
        $member_stmt->close();
        
        $row['members'] = $members;
        $groups[] = $row;
    }
    $stmt->close();
    
    // Get total students in faculty's groups
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT gm.student_id) as total_students 
                           FROM groups g 
                           JOIN group_members gm ON g.id = gm.group_id 
                           WHERE g.faculty_mentor_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_students = $result->fetch_assoc()['total_students'];
    $stmt->close();
    
    // Get faculty's courses
    $stmt = $conn->prepare("SELECT COUNT(*) as total_courses FROM courses WHERE faculty_id = ? AND is_active = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_courses = $result->fetch_assoc()['total_courses'];
    $stmt->close();
    
    // Get faculty's courses for dropdown
    $stmt = $conn->prepare("SELECT id, course_code, course_name, department FROM courses WHERE faculty_id = ? AND is_active = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $faculty_courses = [];
    $faculty_course_ids = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['department'] === $faculty['department']) {
            $faculty_courses[] = $row;
            $faculty_course_ids[] = $row['id'];
        }
    }
    $stmt->close();
    
    // Get groups in faculty's courses not mentored by this faculty
    $other_groups = [];
    if (!empty($faculty_course_ids)) {
        $in = implode(',', array_fill(0, count($faculty_course_ids), '?'));
        $types = str_repeat('i', count($faculty_course_ids));
        $params = $faculty_course_ids;
        $query = "SELECT g.*, c.course_code, c.course_name, f.first_name, f.last_name FROM groups g JOIN courses c ON g.course_id = c.id LEFT JOIN faculty f ON g.faculty_mentor_id = f.id WHERE g.course_id IN ($in) AND (g.faculty_mentor_id IS NULL OR g.faculty_mentor_id != ?)";
        $stmt = $conn->prepare($query);
        $bind_params = array_merge($params, [$user_id]);
        $stmt->bind_param($types . 'i', ...$bind_params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $other_groups[] = $row;
        }
        $stmt->close();
    }
    
    // Calculate statistics
    $total_groups = count($groups);
    $active_groups = 0;
    $total_leaders = 0;
    $average_cgpa = 0;
    $all_cgpas = [];
    
    foreach ($groups as $group) {
        if ($group['status'] === 'active') {
            $active_groups++;
        }
        foreach ($group['members'] as $member) {
            $all_cgpas[] = $member['cgpa'];
            if ($member['role'] === 'leader') {
                $total_leaders++;
            }
        }
    }
    
    if (!empty($all_cgpas)) {
        $average_cgpa = array_sum($all_cgpas) / count($all_cgpas);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    // Show error message instead of hardcoded data
    $error_message = "Database connection error. Please try again later.";
    $groups = [];
    $total_students = 0;
    $total_courses = 0;
    $faculty = null;
    $faculty_courses = [];
    $total_groups = 0;
    $active_groups = 0;
    $total_leaders = 0;
    $average_cgpa = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard | Study Group Generator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Faculty dashboard specific styles */
        body {
            background: var(--bg-primary);
        }

        .main-content {
            padding: var(--spacing-xl) 0;
        }

        .welcome-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
        }

        .welcome-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .welcome-avatar {
            width: 80px;
            height: 80px;
            background: var(--faculty-primary);
            border-radius: var(--radius-full);
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--white);
            font-size: var(--font-size-2xl);
            font-weight: 600;
        }

        .welcome-text h2 {
            margin-bottom: var(--spacing-xs);
            color: var(--gray-900);
        }

        .welcome-text p {
            color: var(--gray-600);
            margin-bottom: 0;
        }

        .actions-section {
            background: var(--white);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--spacing-xl);
        }

        .actions-header {
            margin-bottom: var(--spacing-lg);
        }

        .actions-header h2 {
            color: var(--gray-900);
            margin-bottom: 0;
        }

        .actions-grid {
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }

        .groups-section {
            background: var(--white);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
        }

        .section-title {
            color: var(--gray-900);
            font-size: var(--font-size-2xl);
            margin-bottom: 0;
        }

        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: var(--spacing-lg);
        }

        .group-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            transition: all var(--transition-base);
        }

        .group-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }

        .group-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
        }

        .group-meta {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }

        .group-status {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: 500;
        }

        .status-active {
            background: var(--success-light);
            color: var(--success);
        }

        .status-inactive {
            background: var(--gray-200);
            color: var(--gray-600);
        }

        .group-actions {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
        }

        .member-item {
            background: var(--gray-50);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-sm);
        }

        .member-avatar {
            width: 35px;
            height: 35px;
            border-radius: var(--radius-full);
            background: var(--faculty-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--white);
            font-size: var(--font-size-sm);
            font-weight: bold;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .member-details {
            font-size: var(--font-size-xs);
            color: var(--gray-600);
        }

        .member-role {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-md);
            font-size: var(--font-size-xs);
            font-weight: 500;
        }

        .role-leader {
            background: var(--warning-light);
            color: var(--warning);
        }

        .role-member {
            background: var(--gray-200);
            color: var(--gray-600);
        }

        .member-actions {
            display: flex;
            gap: var(--spacing-xs);
        }

        .empty-state {
            text-align: center;
            padding: var(--spacing-2xl);
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: var(--font-size-4xl);
            margin-bottom: var(--spacing-md);
            display: block;
            opacity: 0.5;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
        }

        .modal-title {
            color: var(--gray-900);
            font-size: var(--font-size-xl);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: var(--font-size-2xl);
            cursor: pointer;
            color: var(--gray-600);
        }

        .modal-footer {
            display: flex;
            gap: var(--spacing-md);
            justify-content: flex-end;
            margin-top: var(--spacing-lg);
        }

        @media (max-width: 768px) {
            .welcome-header {
                flex-direction: column;
                text-align: center;
            }

            .groups-grid {
                grid-template-columns: 1fr;
            }

            .group-actions {
                flex-direction: column;
            }

            .member-item {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
            }

            .member-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .actions-grid {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="faculty-theme">
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="logo-text">Faculty Dashboard</div>
            </div>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-details">
                        <div class="user-name"><?php echo $faculty_name; ?></div>
                        <div class="user-role">Faculty Member</div>
                    </div>
                    <div class="user-avatar"><?php echo $faculty_initials; ?></div>
                </div>
                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <div class="nav-container">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="faculty_dashboard.php" class="active">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="faculty_course_management.php">Course Management</a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Welcome Section -->
            <section class="welcome-section">
                <div class="welcome-header">
                    <div class="welcome-avatar"><?php echo $faculty_initials; ?></div>
                    <div class="welcome-text">
                        <h2>Welcome back, <?php echo $faculty_name; ?>!</h2>
                        <p>Manage your study groups and mentor students effectively.</p>
                    </div>
                </div>
            </section>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_groups; ?></div>
                    <div class="stat-label">Total Groups</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $active_groups; ?></div>
                    <div class="stat-label">Active Groups</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_courses; ?></div>
                    <div class="stat-label">Assigned Courses</div>
                </div>
            </div>

            <!-- Actions -->
            <section class="actions-section">
                <div class="actions-header">
                    <h2>Quick Actions</h2>
                </div>
                <div class="actions-grid">
                    <button class="btn btn-success" id="createGroupBtn">
                        <i class="fas fa-plus"></i> Create Study Group
                    </button>
                    <a href="faculty_course_management.php" class="btn btn-outline">
                        <i class="fas fa-book"></i> Manage Courses
                    </a>
                </div>
            </section>

            <!-- Groups Section -->
            <section class="groups-section">
                <div class="section-header">
                    <h2 class="section-title">My Study Groups</h2>
                </div>
                
                <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No study groups created yet. Create your first group to get started!</p>
                    </div>
                <?php else: ?>
                    <div class="groups-grid">
                        <?php foreach ($groups as $group): ?>
                            <div class="group-card">
                                <div class="group-header">
                                    <div>
                                        <div class="group-title"><?php echo htmlspecialchars($group['group_name']); ?></div>
                                        <div class="group-meta">
                                            <?php echo htmlspecialchars($group['course_code']); ?> - <?php echo htmlspecialchars($group['course_name']); ?> | 
                                            <?php echo count($group['members']); ?> members
                                        </div>
                                    </div>
                                    <span class="group-status <?php echo $group['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($group['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="group-actions">
                                    <?php if ($group['status'] === 'active'): ?>
                                        <button class="btn btn-warning btn-sm" onclick="deactivateGroup(<?php echo $group['id']; ?>)">
                                            <i class="fas fa-pause"></i> Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-sm" onclick="activateGroup(<?php echo $group['id']; ?>)">
                                            <i class="fas fa-play"></i> Activate
                                        </button>
                                    <?php endif; ?>
                                    <a href="group_details.php?id=<?php echo $group['id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>

                                <div style="margin-top: var(--spacing-md);">
                                    <h4 style="margin-bottom: var(--spacing-md); color: var(--gray-900);">
                                        <i class="fas fa-users"></i> Members
                                    </h4>
                                    <?php if (empty($group['members'])): ?>
                                        <p style="color: var(--gray-600); font-style: italic;">
                                            No members yet
                                        </p>
                                    <?php else: ?>
                                        <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                                            <?php foreach ($group['members'] as $member): ?>
                                                <div class="member-item">
                                                    <div class="member-avatar">
                                                        <?php echo strtoupper(substr($member['full_name'], 0, 2)); ?>
                                                    </div>
                                                    <div class="member-info">
                                                        <div class="member-name">
                                                            <?php echo htmlspecialchars($member['full_name']); ?>
                                                            <span class="member-role <?php echo $member['role'] === 'leader' ? 'role-leader' : 'role-member'; ?>">
                                                                <?php echo ucfirst($member['role']); ?>
                                                            </span>
                                                        </div>
                                                        <div class="member-details">
                                                            ID: <?php echo $member['student_id']; ?> | CGPA: <?php echo number_format($member['cgpa'], 2); ?>
                                                        </div>
                                                    </div>
                                                    <div class="member-actions">
                                                        <?php if ($member['role'] !== 'leader'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="assign_leader">
                                                                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                                <input type="hidden" name="student_id" value="<?php echo $member['id']; ?>">
                                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Assign <?php echo htmlspecialchars($member['full_name']); ?> as group leader?')">
                                                                    <i class="fas fa-crown"></i> Make Leader
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="btn btn-outline btn-sm" style="cursor: default; opacity: 0.7;">
                                                                <i class="fas fa-crown"></i> Current Leader
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <!-- NEW: Other Groups in My Courses -->
            <?php if (!empty($other_groups)): ?>
            <section class="groups-section" style="margin-top: 2em;">
                <div class="section-header">
                    <h2 class="section-title">Groups in My Courses (Not Mentored by Me)</h2>
                </div>
                <div class="groups-grid">
                    <?php foreach ($other_groups as $group): ?>
                        <div class="group-card">
                            <div class="group-header">
                                <div>
                                    <div class="group-title"><?php echo htmlspecialchars($group['group_name']); ?></div>
                                    <div class="group-meta">
                                        <?php echo htmlspecialchars($group['course_code']); ?> - <?php echo htmlspecialchars($group['course_name']); ?> |
                                        Mentor: <?php echo $group['first_name'] ? htmlspecialchars($group['first_name'] . ' ' . $group['last_name']) : 'None'; ?>
                                    </div>
                                </div>
                                <span class="group-status <?php echo $group['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo ucfirst($group['status']); ?>
                                </span>
                            </div>
                            <div class="group-actions">
                                <form method="POST" action="assign_group_mentor.php" style="display:inline;">
                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Assign yourself as mentor to this group?')">
                                        <i class="fas fa-user-plus"></i> Assign Myself as Mentor
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <!-- Create Group Modal -->
    <div class="modal" id="createGroupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create New Study Group</h3>
                <button class="close-modal" id="closeModal">&times;</button>
            </div>
            <form id="groupForm">
                <?php echo getCSRFTokenField(); ?>
                <div class="form-group">
                    <label for="groupName" class="form-label">Group Name</label>
                    <input type="text" id="groupName" class="form-control" placeholder="Enter group name" required>
                </div>
                <div class="form-group">
                    <label for="groupCourse" class="form-label">Course</label>
                    <select id="groupCourse" class="form-control" required>
                        <option value="">Select course</option>
                        <?php if (empty($faculty_courses)): ?>
                            <option value="" disabled>No courses assigned to you</option>
                        <?php else: ?>
                            <?php foreach ($faculty_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ': ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($faculty_courses)): ?>
                        <small style="color: #dc3545; display: block; margin-top: 5px;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            You need to have courses assigned to you in your department to create study groups. 
                            <a href="debug_faculty.php" style="color: #007bff;">Click here to debug</a>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="maxMembers" class="form-label">Maximum Members</label>
                    <input type="number" id="maxMembers" class="form-control" min="4" max="6" value="6" required>
                </div>
                <div class="form-group">
                    <label for="groupDescription" class="form-label">Description (Optional)</label>
                    <textarea id="groupDescription" class="form-control" rows="3" placeholder="Brief description of the group's focus"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelCreate">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Group</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('createGroupModal');
        const createBtn = document.getElementById('createGroupBtn');
        const closeBtn = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelCreate');

        createBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
        });

        closeBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        cancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Form submission
        document.getElementById('groupForm').addEventListener('submit', (e) => {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('group_name', document.getElementById('groupName').value);
            formData.append('course_id', document.getElementById('groupCourse').value);
            formData.append('max_members', document.getElementById('maxMembers').value);
            formData.append('description', document.getElementById('groupDescription').value);
            
            // Add CSRF token
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            formData.append('csrf_token', csrfToken);
            
            fetch('create_group.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage(data.message, 'success');
                    modal.style.display = 'none';
                    document.getElementById('groupForm').reset();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            });
        });

        // Group management functions
        function activateGroup(groupId) {
            if (confirm('Are you sure you want to activate this group?')) {
                updateGroupStatus(groupId, 'active');
            }
        }

        function deactivateGroup(groupId) {
            if (confirm('Are you sure you want to deactivate this group?')) {
                updateGroupStatus(groupId, 'inactive');
            }
        }

        function updateGroupStatus(groupId, status) {
            const formData = new FormData();
            formData.append('group_id', groupId);
            formData.append('status', status);
            
            fetch('update_group_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            });
        }

        // Message display function
        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
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
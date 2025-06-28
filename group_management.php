<?php
session_start();

// Check if user is logged in and is a faculty member
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'faculty') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Faculty';

// Handle group leader assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once 'config.php';
        $conn = getConnection();
        
        if (isset($_POST['action']) && $_POST['action'] === 'assign_leader') {
            $group_id = intval($_POST['group_id']);
            $student_id = intval($_POST['student_id']);
            
            // Verify faculty owns this group
            $stmt = $conn->prepare("SELECT id FROM groups WHERE id = ? AND faculty_mentor_id = ?");
            $stmt->bind_param("ii", $group_id, $user_id);
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
        }
        
        $conn->close();
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Fetch faculty's groups with members
try {
    require_once 'config.php';
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name, c.department,
                           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
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
    
    $conn->close();
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $groups = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Management | Faculty Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #28a745;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
            --white: #ffffff;
            --border: #e9ecef;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.15);
            --gradient-primary: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Navigation */
        .navbar {
            background: var(--white);
            box-shadow: var(--shadow);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .nav-brand {
            font-size: 1.5rem;
            font-weight: bold;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-menu a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px 16px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-menu a:hover {
            background: var(--gradient-primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: var(--white);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .page-header h1 {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 15px;
            font-weight: 700;
        }

        .page-header p {
            color: var(--secondary);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Message Display */
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

        /* Group Cards */
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }

        .group-card {
            background: var(--white);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .group-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary);
        }

        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .group-info h3 {
            color: var(--dark);
            font-size: 1.3rem;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .group-meta {
            color: var(--secondary);
            font-size: 0.9rem;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .group-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-inactive {
            background: rgba(108, 117, 125, 0.1);
            color: var(--secondary);
        }

        /* Members Section */
        .members-section {
            margin-top: 20px;
        }

        .members-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .members-title {
            font-size: 1.1rem;
            color: var(--dark);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .member-count {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .members-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .member-item {
            background: var(--light);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .member-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .member-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .member-name {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .member-details {
            font-size: 0.85rem;
            color: var(--secondary);
            display: flex;
            gap: 15px;
        }

        .member-role {
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-leader {
            background: var(--gradient-success);
            color: white;
        }

        .role-member {
            background: rgba(108, 117, 125, 0.1);
            color: var(--secondary);
        }

        .member-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 6px 12px;
            border-radius: 15px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .no-groups {
            text-align: center;
            color: var(--secondary);
            padding: 60px;
            font-style: italic;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .no-groups i {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
            color: var(--secondary);
            opacity: 0.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .groups-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                padding: 30px 20px;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .group-card {
                padding: 20px;
            }

            .group-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .member-item {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .member-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="faculty_dashboard.php" class="nav-brand">
                <i class="fas fa-chalkboard-teacher"></i> Faculty Dashboard
            </a>
            <ul class="nav-menu">
                <li><a href="faculty_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="faculty_course_management.php"><i class="fas fa-book"></i> Course Management</a></li>
                <li><a href="group_management.php" style="background: var(--gradient-primary); color: white;"><i class="fas fa-users"></i> Group Management</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Group Management</h1>
            <p>Manage your study groups and assign group leaders. Group leaders can help coordinate activities and manage group communications.</p>
        </div>

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

        <!-- Groups Section -->
        <?php if (empty($groups)): ?>
            <div class="no-groups">
                <i class="fas fa-users"></i>
                <p>You don't have any study groups yet.</p>
                <p style="font-size: 0.9rem; margin-top: 10px;">Create groups from your faculty dashboard to start managing them here.</p>
            </div>
        <?php else: ?>
            <div class="groups-grid">
                <?php foreach ($groups as $group): ?>
                    <div class="group-card">
                        <div class="group-header">
                            <div class="group-info">
                                <h3><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                <div class="group-meta">
                                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($group['course_code']); ?> - <?php echo htmlspecialchars($group['course_name']); ?></span>
                                    <span><i class="fas fa-graduation-cap"></i> <?php echo ucfirst($group['department']); ?> Department</span>
                                    <span><i class="fas fa-calendar"></i> Created: <?php echo date('M d, Y', strtotime($group['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="group-status <?php echo $group['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($group['status']); ?>
                            </div>
                        </div>

                        <div class="members-section">
                            <div class="members-header">
                                <h4 class="members-title">
                                    <i class="fas fa-users"></i> Group Members
                                </h4>
                                <span class="member-count"><?php echo count($group['members']); ?> members</span>
                            </div>

                            <div class="members-list">
                                <?php foreach ($group['members'] as $member): ?>
                                    <div class="member-item">
                                        <div class="member-info">
                                            <div class="member-name">
                                                <?php echo htmlspecialchars($member['full_name']); ?>
                                                <span class="member-role <?php echo $member['role'] === 'leader' ? 'role-leader' : 'role-member'; ?>">
                                                    <?php echo ucfirst($member['role']); ?>
                                                </span>
                                            </div>
                                            <div class="member-details">
                                                <span><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($member['student_id']); ?></span>
                                                <span><i class="fas fa-star"></i> CGPA: <?php echo number_format($member['cgpa'], 2); ?></span>
                                                <span><i class="fas fa-calendar-alt"></i> T<?php echo $member['trimester']; ?></span>
                                            </div>
                                        </div>
                                        <div class="member-actions">
                                            <?php if ($member['role'] !== 'leader'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="assign_leader">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                    <input type="hidden" name="student_id" value="<?php echo $member['id']; ?>">
                                                    <button type="submit" class="btn btn-success" onclick="return confirm('Assign <?php echo htmlspecialchars($member['full_name']); ?> as group leader?')">
                                                        <i class="fas fa-crown"></i> Make Leader
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="btn btn-outline" style="cursor: default;">
                                                    <i class="fas fa-crown"></i> Current Leader
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.opacity = '0';
                setTimeout(() => {
                    message.remove();
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html> 
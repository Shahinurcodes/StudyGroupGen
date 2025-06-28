<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['student', 'faculty'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_type = $_SESSION['user_type'];

// Check if a specific group is requested (for faculty managing their groups)
$specific_group_id = $_GET['group_id'] ?? null;

// Fetch all active groups with their members
$groups_with_members = [];
$stats = [
    'total_groups' => 0,
    'total_members' => 0,
    'total_leaders' => 0,
    'average_cgpa' => 0
];

try {
    require_once 'config.php';
    $conn = getConnection();
    
    if ($user_type === 'faculty') {
        // For faculty: show only their mentored groups
        if ($specific_group_id) {
            // Show specific group if faculty owns it
            $stmt = $conn->prepare("SELECT g.id as group_id, g.group_name, g.max_members, g.status,
                                   c.course_code, c.course_name,
                                   f.first_name, f.last_name,
                                   (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                                   FROM groups g 
                                   JOIN courses c ON g.course_id = c.id 
                                   LEFT JOIN faculty f ON g.faculty_mentor_id = f.id 
                                   WHERE g.id = ? AND g.faculty_mentor_id = ?
                                   ORDER BY c.course_code, g.group_name");
            $stmt->bind_param("ii", $specific_group_id, $user_id);
        } else {
            // Show all faculty's groups
            $stmt = $conn->prepare("SELECT g.id as group_id, g.group_name, g.max_members, g.status,
                                   c.course_code, c.course_name,
                                   f.first_name, f.last_name,
                                   (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                                   FROM groups g 
                                   JOIN courses c ON g.course_id = c.id 
                                   LEFT JOIN faculty f ON g.faculty_mentor_id = f.id 
                                   WHERE g.faculty_mentor_id = ?
                                   ORDER BY g.status DESC, c.course_code, g.group_name");
            $stmt->bind_param("i", $user_id);
        }
    } else {
        // For students: show all active groups (original logic)
        $stmt = $conn->prepare("SELECT g.id as group_id, g.group_name, g.max_members, g.status,
                               c.course_code, c.course_name,
                               f.first_name, f.last_name,
                               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                               FROM groups g 
                               JOIN courses c ON g.course_id = c.id 
                               LEFT JOIN faculty f ON g.faculty_mentor_id = f.id 
                               WHERE g.status = 'active'
                               ORDER BY c.course_code, g.group_name");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($group = $result->fetch_assoc()) {
        // Fetch members for this group
        $member_stmt = $conn->prepare("SELECT s.id, s.full_name, s.student_id, s.cgpa, s.trimester, gm.role
                                      FROM group_members gm 
                                      JOIN students s ON gm.student_id = s.id 
                                      WHERE gm.group_id = ?
                                      ORDER BY gm.role DESC, s.full_name");
        $member_stmt->bind_param("i", $group['group_id']);
        $member_stmt->execute();
        $member_result = $member_stmt->get_result();
        
        $members = [];
        while ($member = $member_result->fetch_assoc()) {
            $members[] = $member;
        }
        $member_stmt->close();
        
        $group['members'] = $members;
        $groups_with_members[] = $group;
    }
    $stmt->close();
    
    // Calculate overall statistics
    if (!empty($groups_with_members)) {
        $stats['total_groups'] = count($groups_with_members);
        $all_members = [];
        $all_cgpas = [];
        $total_leaders = 0;
        
        foreach ($groups_with_members as $group) {
            $stats['total_members'] += count($group['members']);
            foreach ($group['members'] as $member) {
                $all_members[] = $member;
                $all_cgpas[] = $member['cgpa'];
                if ($member['role'] === 'leader') {
                    $total_leaders++;
                }
            }
        }
        
        $stats['total_leaders'] = $total_leaders;
        $stats['average_cgpa'] = !empty($all_cgpas) ? array_sum($all_cgpas) / count($all_cgpas) : 0;
    }
    
    $conn->close();
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Group Generator | Group Members</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --primary: <?php echo $user_type === 'faculty' ? '#28a745' : '#4a6bff'; ?>;
            --primary-light: <?php echo $user_type === 'faculty' ? '#6c8cff' : '#6c8cff'; ?>;
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
            --gradient-primary: <?php echo $user_type === 'faculty' ? 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; ?>;
            --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            display: flex;
            flex-direction: column;
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

        .stats-section {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .stats-section h2 {
            margin-bottom: 25px;
            color: var(--dark);
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            text-align: center;
            padding: 25px 20px;
            border-radius: 15px;
            background: var(--light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .search-section {
            background: var(--white);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid var(--border);
            border-radius: 25px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.1);
        }

        .search-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .groups-container {
            display: grid;
            gap: 30px;
        }

        .group-card {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .group-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-success);
        }

        .group-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }

        .group-info h2 {
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .group-meta {
            color: var(--secondary);
            font-size: 1rem;
            line-height: 1.5;
        }

        .group-meta i {
            margin-right: 8px;
            color: var(--primary);
        }

        .group-stats {
            text-align: right;
        }

        .member-count {
            font-size: 1.4rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .group-status {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: var(--gradient-success);
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .member-card {
            background: var(--light);
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .member-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .member-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary);
        }

        .member-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .member-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(74, 107, 255, 0.3);
        }

        .member-info h3 {
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .member-role {
            background: var(--gradient-warning);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .member-details {
            display: grid;
            gap: 10px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.95rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--secondary);
            font-weight: 500;
        }

        .detail-value {
            color: var(--dark);
            font-weight: 600;
        }

        .no-members {
            text-align: center;
            color: var(--secondary);
            padding: 40px;
            font-style: italic;
            background: var(--light);
            border-radius: 15px;
            border: 2px dashed var(--border);
        }

        .no-members i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
            color: var(--secondary);
            opacity: 0.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .members-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .nav-menu {
                gap: 1rem;
            }
            
            .group-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .group-stats {
                text-align: left;
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

            .member-card {
                padding: 15px;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .group-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .group-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .group-card:nth-child(3) {
            animation-delay: 0.2s;
        }

        .group-card:nth-child(4) {
            animation-delay: 0.3s;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?php echo $user_type === 'faculty' ? 'faculty_dashboard.php' : 'dashboard.php'; ?>" class="nav-brand">
                <i class="fas fa-users"></i> Study Group Generator
            </a>
            <ul class="nav-menu">
                <li><a href="<?php echo $user_type === 'faculty' ? 'faculty_dashboard.php' : 'dashboard.php'; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
                <?php if ($user_type === 'student'): ?>
                    <li><a href="student_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <?php endif; ?>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <?php if ($user_type === 'faculty'): ?>
                    <?php if ($specific_group_id): ?>
                        <i class="fas fa-users"></i> Group Members: <?php echo htmlspecialchars($groups_with_members[0]['group_name'] ?? 'Group'); ?>
                    <?php else: ?>
                        <i class="fas fa-chalkboard-teacher"></i> My Study Groups & Members
                    <?php endif; ?>
                <?php else: ?>
                    <i class="fas fa-users"></i> Study Groups & Members
                <?php endif; ?>
            </h1>
            <p>
                <?php if ($user_type === 'faculty'): ?>
                    <?php if ($specific_group_id): ?>
                        Manage and view members of your study group with detailed information and statistics
                    <?php else: ?>
                        View all your mentored study groups and their members with comprehensive analytics
                    <?php endif; ?>
                <?php else: ?>
                    Explore all active study groups and their members organized by group with detailed insights
                <?php endif; ?>
            </p>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form class="search-form">
                <input type="text" class="search-input" placeholder="Search groups, courses, or member names...">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <!-- Statistics -->
        <div class="stats-section">
            <h2><i class="fas fa-chart-bar"></i> Overall Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_groups']; ?></div>
                    <div class="stat-label"><i class="fas fa-layer-group"></i> Active Groups</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_members']; ?></div>
                    <div class="stat-label"><i class="fas fa-users"></i> Total Members</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_leaders']; ?></div>
                    <div class="stat-label"><i class="fas fa-crown"></i> Group Leaders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['average_cgpa'], 2); ?></div>
                    <div class="stat-label"><i class="fas fa-star"></i> Average CGPA</div>
                </div>
            </div>
        </div>

        <!-- Groups Container -->
        <div class="groups-container">
            <?php if (empty($groups_with_members)): ?>
                <div class="no-members">
                    <i class="fas fa-users-slash"></i>
                    <p>No active study groups found.</p>
                    <p style="font-size: 0.9rem; margin-top: 10px;">Groups will appear here once they are created and activated.</p>
                </div>
            <?php else: ?>
                <?php foreach ($groups_with_members as $group): ?>
                    <div class="group-card">
                        <div class="group-header">
                            <div class="group-info">
                                <h2><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($group['group_name']); ?></h2>
                                <div class="group-meta">
                                    <i class="fas fa-book"></i> <?php echo htmlspecialchars($group['course_code'] . ': ' . $group['course_name']); ?>
                                    <?php if (!empty($group['first_name'])): ?>
                                        <br><i class="fas fa-chalkboard-teacher"></i> Mentored by <?php echo htmlspecialchars($group['first_name'] . ' ' . $group['last_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="group-stats">
                                <div class="member-count">
                                    <i class="fas fa-users"></i> <?php echo $group['member_count']; ?>/<?php echo $group['max_members']; ?>
                                </div>
                                <div class="group-status">
                                    <i class="fas fa-circle"></i> <?php echo ucfirst($group['status']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="members-grid">
                            <?php if (empty($group['members'])): ?>
                                <div class="no-members">
                                    <i class="fas fa-user-plus"></i>
                                    <p>No members yet</p>
                                    <p style="font-size: 0.9rem; margin-top: 5px;">Students can join this group to start collaborating</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($group['members'] as $member): ?>
                                    <div class="member-card">
                                        <div class="member-header">
                                            <div class="member-avatar"><?php echo strtoupper(substr($member['full_name'], 0, 2)); ?></div>
                                            <div class="member-info">
                                                <h3><?php echo htmlspecialchars($member['full_name']); ?></h3>
                                                <?php if ($member['role'] === 'leader'): ?>
                                                    <span class="member-role"><i class="fas fa-crown"></i> Group Leader</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="member-details">
                                            <div class="detail-row">
                                                <span class="detail-label"><i class="fas fa-id-card"></i> Student ID:</span>
                                                <span class="detail-value"><?php echo $member['student_id']; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label"><i class="fas fa-star"></i> CGPA:</span>
                                                <span class="detail-value"><?php echo number_format($member['cgpa'], 2); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label"><i class="fas fa-graduation-cap"></i> Trimester:</span>
                                                <span class="detail-value"><?php echo $member['trimester']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search functionality
        document.querySelector('.search-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const searchTerm = document.querySelector('.search-input').value.toLowerCase();
            const groupCards = document.querySelectorAll('.group-card');
            
            groupCards.forEach(groupCard => {
                const groupName = groupCard.querySelector('h2').textContent.toLowerCase();
                const courseInfo = groupCard.querySelector('.group-meta').textContent.toLowerCase();
                const memberCards = groupCard.querySelectorAll('.member-card');
                
                let hasMatch = groupName.includes(searchTerm) || courseInfo.includes(searchTerm);
                
                // Check member names
                memberCards.forEach(memberCard => {
                    const memberName = memberCard.querySelector('h3').textContent.toLowerCase();
                    const studentId = memberCard.querySelector('.detail-value').textContent.toLowerCase();
                    
                    if (memberName.includes(searchTerm) || studentId.includes(searchTerm)) {
                        hasMatch = true;
                    }
                });
                
                if (hasMatch) {
                    groupCard.style.display = 'block';
                } else {
                    groupCard.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
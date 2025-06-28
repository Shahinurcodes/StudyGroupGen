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

// Get group ID from URL parameter
$group_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['group_id']) ? intval($_GET['group_id']) : 0);

// Fetch group details from database
$group = null;
$members = [];
$is_member = false;
$error_message = null;

try {
    require_once 'config.php';
    $conn = getConnection();
    
    if ($user_type === 'faculty') {
        // For faculty: check if they own this group
        $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name, c.department, f.first_name, f.last_name,
                               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                               FROM groups g 
                               JOIN courses c ON g.course_id = c.id 
                               LEFT JOIN faculty f ON g.faculty_mentor_id = f.id 
                               WHERE g.id = ? AND g.faculty_mentor_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
    } else {
        // For students: check if group is active and they can view it
        $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name, c.department, f.first_name, f.last_name,
                               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND student_id = ?) as is_member
                               FROM groups g 
                               JOIN courses c ON g.course_id = c.id 
                               LEFT JOIN faculty f ON g.faculty_mentor_id = f.id 
                               WHERE g.id = ? AND g.status = 'active'");
        $stmt->bind_param("ii", $user_id, $group_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $group = $result->fetch_assoc();
        if ($user_type === 'student') {
            $is_member = $group['is_member'] > 0;
        } else {
            $is_member = true; // Faculty can always view their groups
        }
    } else {
        $error_message = "Group not found or you don't have permission to view it.";
    }
    $stmt->close();
    
    // Fetch group members if group exists
    if ($group) {
        $stmt = $conn->prepare("SELECT s.id, s.full_name, s.cgpa, s.trimester, gm.role,
                               (SELECT COUNT(*) FROM group_members WHERE student_id = s.id) as total_groups
                               FROM group_members gm 
                               JOIN students s ON gm.student_id = s.id 
                               WHERE gm.group_id = ?
                               ORDER BY gm.role DESC, s.full_name");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
        
        // Fetch group messages
        $stmt = $conn->prepare("SELECT m.*, 
                               CASE 
                                   WHEN m.sender_type = 'student' THEN s.full_name
                                   WHEN m.sender_type = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                                   ELSE 'Unknown'
                               END as sender_name
                               FROM messages m 
                               LEFT JOIN students s ON m.sender_id = s.id AND m.sender_type = 'student'
                               LEFT JOIN faculty f ON m.sender_id = f.id AND m.sender_type = 'faculty'
                               WHERE m.group_id = ?
                               ORDER BY m.sent_at ASC
                               LIMIT 50");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
        
        // Fetch group notes
        $stmt = $conn->prepare("SELECT n.*, 
                               CASE 
                                   WHEN n.student_id IS NOT NULL THEN s.full_name
                                   WHEN n.faculty_id IS NOT NULL THEN CONCAT(f.first_name, ' ', f.last_name)
                                   ELSE 'Unknown'
                               END as author_name
                               FROM notes n 
                               LEFT JOIN students s ON n.student_id = s.id
                               LEFT JOIN faculty f ON n.faculty_id = f.id
                               WHERE n.group_id = ?
                               ORDER BY n.created_at DESC
                               LIMIT 20");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $notes = [];
        while ($row = $result->fetch_assoc()) {
            $notes[] = $row;
        }
        $stmt->close();
        
        // Fetch study sessions for the group
        $stmt = $conn->prepare("SELECT s.*, f.first_name, f.last_name
                               FROM sessions s 
                               LEFT JOIN faculty f ON s.created_by = f.id
                               WHERE s.group_id = ?
                               ORDER BY s.scheduled_at ASC
                               LIMIT 10");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $sessions = [];
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
        $stmt->close();
    }
    
    $conn->close();
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Calculate average CGPA for the group
$average_cgpa = 0;
if (!empty($members)) {
    $total_cgpa = 0;
    foreach ($members as $member) {
        $total_cgpa += $member['cgpa'];
    }
    $average_cgpa = $total_cgpa / count($members);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Group Generator | Group Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --primary: <?php echo $user_type === 'faculty' ? '#28a745' : '#4a6bff'; ?>;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
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
            display: flex;
            flex-direction: column;
        }

        /* Navigation */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
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
            color: var(--primary);
            text-decoration: none;
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
            transition: color 0.3s ease;
        }

        .nav-menu a:hover {
            color: var(--primary);
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .group-header {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            position: relative;
        }

        .group-header h1 {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .group-meta {
            display: flex;
            gap: 20px;
            color: var(--secondary);
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .enroll-btn {
            position: absolute;
            top: 25px;
            right: 25px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .enroll-btn:hover {
            background: #3a5bef;
            transform: translateY(-2px);
        }

        .enrolled-btn {
            background: var(--success);
        }

        .join-btn {
            background: var(--primary);
        }

        /* Group Info Sections */
        .group-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 900px) {
            .group-sections {
                grid-template-columns: 1fr;
            }
        }

        .group-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        /* Members Section */
        .members-list {
            display: grid;
            gap: 15px;
        }

        .member-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .member-card:hover {
            background: rgba(74, 107, 255, 0.05);
        }

        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #eee;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            color: var(--primary);
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 500;
            color: var(--dark);
        }

        .member-meta {
            display: flex;
            gap: 10px;
            color: var(--secondary);
            font-size: 0.8rem;
        }

        .leader-badge {
            background: var(--warning);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .you-badge {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Chat Section */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 400px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
        }

        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            max-width: 80%;
        }

        .message-sent {
            background: rgba(74, 107, 255, 0.1);
            margin-left: auto;
            border-bottom-right-radius: 0;
        }

        .message-received {
            background: #f1f1f1;
            margin-right: auto;
            border-bottom-left-radius: 0;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.8rem;
        }

        .message-sender {
            font-weight: 500;
            color: var(--dark);
        }

        .message-time {
            color: var(--secondary);
        }

        .message-content {
            color: var(--dark);
        }

        .chat-input {
            display: flex;
            gap: 10px;
        }

        .chat-input-field {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .chat-send-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 0 20px;
            cursor: pointer;
        }

        /* Notes Section */
        .notes-form {
            margin-top: 20px;
        }

        .notes-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            min-height: 100px;
        }

        .notes-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .submit-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
        }

        .notes-list {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
            max-height: 300px;
            overflow-y: auto;
        }

        .note-item {
            padding: 10px;
            border-radius: 5px;
            background: #f9f9f9;
            margin-bottom: 10px;
        }

        .note-author {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .note-date {
            color: var(--secondary);
            font-size: 0.8rem;
            margin-bottom: 5px;
        }

        .note-content {
            color: var(--dark);
        }

        /* Calendar Section */
        .calendar {
            width: 100%;
            border-collapse: collapse;
        }

        .calendar th {
            background: var(--primary);
            color: white;
            padding: 8px;
            text-align: center;
        }

        .calendar td {
            border: 1px solid #eee;
            padding: 10px;
            text-align: center;
            height: 60px;
            vertical-align: top;
        }

        .calendar-day {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .study-session {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            margin-top: 3px;
        }

        .faculty-available {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            margin-top: 3px;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 15px;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .tab.active {
            border-bottom: 2px solid var(--primary);
            color: var(--primary);
            font-weight: 500;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .group-meta {
                flex-direction: column;
                gap: 5px;
            }
            
            .enroll-btn {
                position: static;
                margin-top: 15px;
            }
            
            .nav-menu {
                gap: 1rem;
            }
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
    <main class="container">
        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                <br><br>
                <a href="<?php echo $user_type === 'faculty' ? 'faculty_dashboard.php' : 'dashboard.php'; ?>" style="color: var(--primary); text-decoration: none;">← Back to Dashboard</a>
            </div>
        <?php elseif ($group): ?>
            <!-- Group Header -->
            <section class="group-header">
                <h1><?php echo htmlspecialchars($group['group_name']); ?></h1>
                <p>Study group for <?php echo htmlspecialchars($group['course_code'] . ': ' . $group['course_name']); ?></p>
                <div class="group-meta">
                    <span><i class="fas fa-users"></i> <?php echo $group['member_count']; ?>/<?php echo $group['max_members']; ?> members</span>
                    <?php if (!empty($group['first_name'])): ?>
                        <span><i class="fas fa-chalkboard-teacher"></i> Mentored by <?php echo htmlspecialchars($group['first_name'] . ' ' . $group['last_name']); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-star"></i> Average CGPA: <?php echo number_format($average_cgpa, 2); ?></span>
                    <span class="status-active"><i class="fas fa-circle"></i> <?php echo ucfirst($group['status']); ?></span>
                </div>
                <?php if ($is_member): ?>
                    <button class="enroll-btn enrolled-btn">
                        <i class="fas fa-check"></i> Enrolled
                    </button>
                    <a href="real_time_chat.php?group_id=<?php echo $group_id; ?>" class="enroll-btn" style="background: var(--success); margin-left: 10px; text-decoration: none; display: inline-flex; align-items: center;">
                        <i class="fas fa-comments"></i> Real-time Chat
                    </a>
                <?php else: ?>
                    <button class="enroll-btn join-btn" onclick="joinGroup(<?php echo $group_id; ?>, '<?php echo htmlspecialchars($group['group_name']); ?>')">
                        <i class="fas fa-plus"></i> Join Group
                    </button>
                <?php endif; ?>
            </section>

            <!-- Group Sections -->
            <div class="group-sections">
                <!-- Members Section -->
                <section class="group-section">
                    <h2 class="section-title">Group Members</h2>
                    <div class="members-list">
                        <?php if (empty($members)): ?>
                            <div style="text-align: center; color: var(--secondary); padding: 20px;">
                                <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                <p>No members yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($members as $member): ?>
                                <div class="member-card">
                                    <div class="member-avatar"><?php echo strtoupper(substr($member['full_name'], 0, 2)); ?></div>
                                    <div class="member-info">
                                        <div class="member-name"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                        <div class="member-meta">
                                            <span>CGPA: <?php echo number_format($member['cgpa'], 2); ?></span>
                                            <span>Trimester <?php echo $member['trimester']; ?></span>
                                            <?php if ($member['role'] === 'leader'): ?>
                                                <span class="leader-badge">Group Leader</span>
                                            <?php endif; ?>
                                            <?php if ($member['id'] == $user_id): ?>
                                                <span class="you-badge">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Calendar Section -->
                <section class="group-section">
                    <h2 class="section-title">Study Schedule</h2>
                    <?php if (empty($sessions)): ?>
                        <div style="text-align: center; color: var(--secondary); padding: 20px;">
                            <i class="fas fa-calendar" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                            <p>No study sessions scheduled yet.</p>
                            <?php if ($user_type === 'faculty'): ?>
                                <p>Create study sessions to help students prepare for exams.</p>
                            <?php else: ?>
                                <p>Check back later for scheduled study sessions.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="sessions-list">
                            <?php foreach ($sessions as $session): ?>
                                <div class="session-item" style="background: var(--light); border-radius: 8px; padding: 15px; margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div>
                                            <h4 style="color: var(--dark); margin-bottom: 5px;"><?php echo htmlspecialchars($session['title']); ?></h4>
                                            <p style="color: var(--secondary); font-size: 0.9rem; margin-bottom: 5px;">
                                                <?php echo htmlspecialchars($session['description']); ?>
                                            </p>
                                            <div style="display: flex; gap: 15px; font-size: 0.8rem; color: var(--secondary);">
                                                <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($session['scheduled_at'])); ?></span>
                                                <span><i class="fas fa-hourglass-half"></i> <?php echo $session['duration']; ?> minutes</span>
                                                <?php if (!empty($session['location'])): ?>
                                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($session['location']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($session['first_name'])): ?>
                                            <div style="text-align: right; font-size: 0.8rem; color: var(--secondary);">
                                                <i class="fas fa-chalkboard-teacher"></i><br>
                                                <?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Notes & Chat Section -->
                <section class="group-section">
                    <div class="tabs">
                        <div class="tab active" data-tab="notes">Group Notes</div>
                        <div class="tab" data-tab="chat">Group Chat</div>
                    </div>

                    <!-- Notes Tab Content -->
                    <div class="tab-content active" id="notes-content">
                        <form class="notes-form">
                            <div style="margin-bottom: 15px;">
                                <input type="text" class="notes-title" placeholder="Note title (optional)" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px;">
                                <textarea class="notes-input" placeholder="Share your notes with the group..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px; resize: vertical;"></textarea>
                            </div>
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-share"></i> Share Note
                            </button>
                        </form>
                        <div class="notes-list">
                            <?php if (empty($notes)): ?>
                                <div style="text-align: center; color: var(--secondary); padding: 20px;">
                                    <i class="fas fa-sticky-note" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                    <p>No notes yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notes as $note): ?>
                                    <div class="note-item">
                                        <div class="note-author"><?php echo htmlspecialchars($note['author_name']); ?></div>
                                        <div class="note-date"><?php echo date('M j, Y', strtotime($note['created_at'])); ?></div>
                                        <?php if (!empty($note['title'])): ?>
                                            <div class="note-title" style="font-weight: 600; color: var(--dark); margin-bottom: 5px;"><?php echo htmlspecialchars($note['title']); ?></div>
                                        <?php endif; ?>
                                        <div class="note-content"><?php echo htmlspecialchars($note['content']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Tab Content -->
                    <div class="tab-content" id="chat-content">
                        <div class="chat-container">
                            <div class="chat-messages">
                                <?php if (empty($messages)): ?>
                                    <div style="text-align: center; color: var(--secondary); padding: 20px;">
                                        <i class="fas fa-comments" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                        <p>No messages yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message message-received">
                                            <div class="message-header">
                                                <span class="message-sender"><?php echo htmlspecialchars($message['sender_name']); ?></span>
                                                <span class="message-time"><?php echo date('M j, Y, g:i A', strtotime($message['sent_at'])); ?></span>
                                            </div>
                                            <div class="message-content"><?php echo htmlspecialchars($message['content']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="chat-input">
                                <input type="text" class="chat-input-field" placeholder="Type your message...">
                                <button class="chat-send-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <?php if ($group && $group['status'] === 'active'): ?>
                <section class="group-notes-section">
                    <h3>Group Notes</h3>
                    <?php if (!empty($notes)): ?>
                        <ul class="group-notes-list">
                            <?php foreach ($notes as $note): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($note['title']); ?></strong> - <?php echo htmlspecialchars($note['author_name']); ?> <br>
                                    <span><?php echo nl2br(htmlspecialchars($note['content'])); ?></span>
                                    <small>Posted on <?php echo date('M j, Y', strtotime($note['created_at'])); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">No notes have been posted yet.</div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>
        // Join group functionality
        function joinGroup(groupId, groupName) {
            if (!confirm('Are you sure you want to join "' + groupName + '"?')) {
                return;
            }

            const formData = new FormData();
            formData.append('group_id', groupId);

            fetch('join_group.php', {
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

        // Tab functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Show corresponding content
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId + '-content').classList.add('active');
            });
        });

        // Chat functionality
        document.querySelector('.chat-send-btn')?.addEventListener('click', function() {
            sendMessage();
        });

        // Allow Enter key to send message
        document.querySelector('.chat-input-field')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });

        function sendMessage() {
            const input = document.querySelector('.chat-input-field');
            const message = input.value.trim();
            
            if (!message) return;
            
            const formData = new FormData();
            formData.append('group_id', <?php echo $group_id; ?>);
            formData.append('content', message);
            
            fetch('post_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Clear input and refresh page to show new message
                    input.value = '';
                    showMessage('Message sent successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            });
        }

        // Notes functionality
        document.querySelector('.notes-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const titleInput = document.querySelector('.notes-title');
            const contentInput = document.querySelector('.notes-input');
            const title = titleInput.value.trim();
            const note = contentInput.value.trim();
            
            if (!note) return;
            
            const formData = new FormData();
            formData.append('group_id', <?php echo $group_id; ?>);
            formData.append('content', note);
            formData.append('title', title || 'Note'); // Use provided title or default
            
            fetch('post_note.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Clear inputs and refresh page to show new note
                    titleInput.value = '';
                    contentInput.value = '';
                    showMessage('Note shared successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            });
        });

        // Message display function
        function showMessage(message, type) {
            // Remove any existing messages
            const existingMessages = document.querySelectorAll('.inline-message');
            existingMessages.forEach(msg => msg.remove());
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'inline-message';
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 10px;
                background: ${type === 'success' ? 'rgba(40, 167, 69, 0.95)' : 'rgba(220, 53, 69, 0.95)'};
                color: white;
                z-index: 1000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                font-weight: 500;
                max-width: 300px;
                word-wrap: break-word;
            `;
            messageDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${message}
            `;
            
            document.body.appendChild(messageDiv);
            
            // Remove message after 3 seconds
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>

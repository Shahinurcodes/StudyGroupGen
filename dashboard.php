<?php
session_start();

// Redirect to login if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_name = htmlspecialchars($_SESSION['user_name']);
$student_initials = '';
if (!empty($student_name)) {
    $name_parts = explode(' ', $student_name);
    if (count($name_parts) >= 2) {
        $student_initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
    } else {
        $student_initials = strtoupper(substr($student_name, 0, 2));
    }
}

// Fetch dashboard data
require_once 'config.php';
try {
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get student info
    $stmt = $conn->prepare("SELECT full_name, department, trimester, cgpa FROM students WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    // Get available courses for student's department with group counts
    $stmt = $conn->prepare("SELECT c.*, f.first_name, f.last_name,
                           (SELECT COUNT(*) FROM groups WHERE course_id = c.id AND status = 'active') as group_count
                           FROM courses c 
                           LEFT JOIN faculty f ON c.faculty_id = f.id 
                           WHERE c.department = ? AND c.is_active = 1 
                           ORDER BY c.trimester, c.course_code LIMIT 6");
    $stmt->bind_param("s", $student['department']);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
    
    // Get student's enrolled groups
    $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name, gm.role,
                           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                           FROM group_members gm 
                           JOIN groups g ON gm.group_id = g.id 
                           JOIN courses c ON g.course_id = c.id 
                           WHERE gm.student_id = ? AND g.status = 'active'
                           ORDER BY g.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrolled_groups = [];
    while ($row = $result->fetch_assoc()) {
        $enrolled_groups[] = $row;
    }
    $stmt->close();
    
    // Get statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as total_courses FROM courses WHERE department = ? AND is_active = 1");
    $stmt->bind_param("s", $student['department']);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_courses = $result->fetch_assoc()['total_courses'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_groups FROM groups g 
                           JOIN courses c ON g.course_id = c.id 
                           WHERE c.department = ? AND g.status = 'active'");
    $stmt->bind_param("s", $student['department']);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_groups = $result->fetch_assoc()['total_groups'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_faculty FROM faculty WHERE department = ?");
    $stmt->bind_param("s", $student['department']);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_faculty = $result->fetch_assoc()['total_faculty'];
    $stmt->close();
    
    // Fetch all active groups for the student's department
    $available_groups = [];
    $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name, (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as current_members, (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND student_id = ?) as is_member FROM groups g JOIN courses c ON g.course_id = c.id WHERE c.department = ? AND g.status = 'active' ORDER BY g.created_at DESC");
    $stmt->bind_param("is", $user_id, $student['department']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $available_groups[] = $row;
    }
    $stmt->close();
    
    $conn->close();
    
} catch (Exception $e) {
    // Show error message instead of hardcoded data
    $error_message = "Database connection error. Please try again later.";
    $courses = [];
    $enrolled_groups = [];
    $total_courses = 0;
    $total_groups = 0;
    $total_faculty = 0;
    $student = null;
    $available_groups = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Study Group Generator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Student dashboard specific styles */
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
            background: var(--primary);
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

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--spacing-md);
        }

        .quick-stat {
            text-align: center;
            padding: var(--spacing-md);
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }

        .quick-stat-number {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--primary);
            margin-bottom: var(--spacing-xs);
        }

        .quick-stat-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }

        .courses-section, .groups-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
        }

        .section-title {
            font-size: var(--font-size-2xl);
            color: var(--gray-900);
            margin-bottom: 0;
        }

        .courses-grid, .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-lg);
        }

        .course-card, .group-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            transition: all var(--transition-base);
        }

        .course-card:hover, .group-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .course-header, .group-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }

        .course-info h3, .group-info h3 {
            font-size: var(--font-size-lg);
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
        }

        .course-code, .group-course {
            color: var(--primary);
            font-weight: 500;
            font-size: var(--font-size-sm);
        }

        .course-faculty, .group-meta {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
            margin-bottom: var(--spacing-md);
        }

        .course-meta, .group-stats {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }

        .course-actions, .group-actions {
            display: flex;
            gap: var(--spacing-sm);
        }

        .group-role {
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
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: var(--spacing-md);
        }

        .modal-title {
            color: var(--gray-900);
            font-size: var(--font-size-xl);
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: var(--font-size-2xl);
            cursor: pointer;
            color: var(--gray-600);
        }

        .modal-groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-lg);
        }

        @media (max-width: 768px) {
            .welcome-header {
                flex-direction: column;
                text-align: center;
            }

            .courses-grid,
            .groups-grid,
            .modal-groups-grid {
                grid-template-columns: 1fr;
            }

            .course-actions,
            .group-actions {
                flex-direction: column;
            }

            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="logo-text">Student Dashboard</div>
            </div>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-details">
                        <div class="user-name"><?php echo $student_name; ?></div>
                        <div class="user-role">Student</div>
                    </div>
                    <div class="user-avatar"><?php echo $student_initials; ?></div>
                </div>
                <a href="student_profile.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <?php if (isset($error_message)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Welcome Section -->
            <section class="welcome-section">
                <div class="welcome-header">
                    <div class="welcome-avatar"><?php echo $student_initials; ?></div>
                    <div class="welcome-text">
                        <h2>Welcome back, <?php echo $student_name; ?>!</h2>
                        <p><?php echo strtoupper($student['department']); ?> Department • <?php echo $student['trimester']; ?>th Trimester • CGPA: <?php echo number_format($student['cgpa'], 2); ?></p>
                    </div>
                </div>
                
                <div class="quick-stats">
                    <div class="quick-stat">
                        <div class="quick-stat-number"><?php echo $total_courses; ?></div>
                        <div class="quick-stat-label">Available Courses</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-number"><?php echo $total_groups; ?></div>
                        <div class="quick-stat-label">Study Groups</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-number"><?php echo $total_faculty; ?></div>
                        <div class="quick-stat-label">Faculty Members</div>
                    </div>
                </div>
            </section>

            <!-- My Study Groups Section -->
            <?php if (!empty($enrolled_groups)): ?>
            <section class="groups-section">
                <div class="section-header">
                    <h2 class="section-title">My Study Groups</h2>
                </div>
                
                <div class="groups-grid">
                    <?php foreach ($enrolled_groups as $group): ?>
                        <div class="group-card">
                            <div class="group-header">
                                <div class="group-info">
                                    <h3><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                    <div class="group-course"><?php echo htmlspecialchars($group['course_code'] . ': ' . $group['course_name']); ?></div>
                                </div>
                                <span class="group-role <?php echo $group['role'] === 'leader' ? 'role-leader' : 'role-member'; ?>">
                                    <?php echo ucfirst($group['role']); ?>
                                </span>
                            </div>
                            
                            <div class="group-meta">
                                <?php echo htmlspecialchars($group['description'] ?? 'No description available'); ?>
                            </div>
                            
                            <div class="group-stats">
                                <span><i class="fas fa-users"></i> <?php echo $group['member_count']; ?>/<?php echo $group['max_members']; ?> members</span>
                                <span><i class="fas fa-calendar"></i> Created <?php echo date('M j, Y', strtotime($group['created_at'])); ?></span>
                            </div>
                            
                            <div class="group-actions">
                                <a href="group_details.php?id=<?php echo $group['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Available Study Groups Section -->
            <section class="groups-section">
                <div class="section-header">
                    <h2 class="section-title">Available Study Groups</h2>
                </div>
                <?php if (empty($available_groups)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No study groups available in your department yet.</p>
                    </div>
                <?php else: ?>
                    <div class="groups-grid">
                        <?php foreach ($available_groups as $group): ?>
                            <div class="group-card">
                                <div class="group-header">
                                    <div class="group-info">
                                        <h3><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                        <div class="group-course"><?php echo htmlspecialchars($group['course_code'] . ': ' . $group['course_name']); ?></div>
                                    </div>
                                </div>
                                <div class="group-meta">
                                    <?php echo htmlspecialchars($group['description'] ?? 'No description available'); ?>
                                </div>
                                <div class="group-stats">
                                    <span><i class="fas fa-users"></i> <?php echo $group['current_members']; ?>/<?php echo $group['max_members']; ?> members</span>
                                    <span><i class="fas fa-calendar"></i> Created <?php echo date('M j, Y', strtotime($group['created_at'])); ?></span>
                                </div>
                                <div class="group-actions">
                                    <a href="group_details.php?id=<?php echo $group['id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($group['is_member'] > 0): ?>
                                        <span class="btn btn-success btn-sm" style="cursor:default;"><i class="fas fa-check"></i> Joined</span>
                                    <?php else: ?>
                                        <button class="btn btn-primary btn-sm" onclick="joinGroup(<?php echo $group['id']; ?>)"><i class="fas fa-plus"></i> Join</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Available Courses Section -->
            <section class="courses-section">
                <div class="section-header">
                    <h2 class="section-title">Available Courses</h2>
                </div>
                
                <?php if (empty($courses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <p>No courses available in your department at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="courses-grid">
                        <?php foreach ($courses as $course): ?>
                            <div class="course-card">
                                <div class="course-header">
                                    <div class="course-info">
                                        <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                        <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="course-faculty">
                                    <?php if (!empty($course['first_name'])): ?>
                                        Faculty: <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                    <?php else: ?>
                                        Faculty: Not assigned
                                    <?php endif; ?>
                                </div>
                                
                                <div class="course-meta">
                                    <span>Trimester: <?php echo $course['trimester']; ?></span>
                                    <span>Credits: <?php echo $course['credits']; ?></span>
                                    <span>Year: <?php echo $course['year']; ?></span>
                                    <span><i class="fas fa-users"></i> <?php echo $course['group_count']; ?> groups</span>
                                </div>
                                
                                <div class="course-actions">
                                    <button class="btn btn-primary btn-sm" onclick="viewCourseGroups(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_code'] . ': ' . $course['course_name']); ?>')">
                                        <i class="fas fa-users"></i> View Groups
                                    </button>
                                </div>
                                <div class="course-groups-preview" id="course-groups-preview-<?php echo $course['id']; ?>">
                                    <!-- Groups preview will be loaded here by JS -->
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Course Groups Modal -->
    <div class="modal" id="courseGroupsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Study Groups</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalGroupsContent">
                <!-- Groups will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function viewCourseGroups(courseId, courseName) {
            const modal = document.getElementById('courseGroupsModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalContent = document.getElementById('modalGroupsContent');
            
            modalTitle.textContent = `Study Groups for ${escapeHTML(courseName)}`;
            modalContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin fa-2x"></i><p style="margin-top: 1rem;">Loading groups...</p></div>';
            modal.style.display = 'flex';
            
            fetch('get_course_groups.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'course_id=' + courseId
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    displayGroupsInModal(data.groups);
                } else {
                    modalContent.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>${escapeHTML(data.message)}</p></div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalContent.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading groups. Please try again.</p></div>';
            });
        }
        
        function displayGroupsInModal(groups) {
            const modalContent = document.getElementById('modalGroupsContent');
            if (groups.length === 0) {
                modalContent.innerHTML = '<div class="empty-state"><i class="fas fa-users"></i><p>No study groups available for this course yet.</p></div>';
                return;
            }
            
            let html = '<div class="modal-groups-grid">';
            groups.forEach(group => {
                html += `
                    <div class="group-card">
                        <div class="group-header">
                            <div class="group-info">
                                <h3>${escapeHTML(group.group_name)}</h3>
                                <div class="group-course">${escapeHTML(group.course_code)}</div>
                            </div>
                        </div>
                        <div class="group-meta" style="min-height: 40px;">
                            ${escapeHTML(group.description) || 'No description available'}
                        </div>
                        <div class="group-stats">
                            <span><i class="fas fa-users"></i> ${group.current_members}/${group.max_members}</span>
                        </div>
                        <div class="group-actions">
                            <a href="group_details.php?id=${group.id}" class="btn btn-outline btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                            ${group.is_member
                                ? `<span class="btn btn-success btn-sm" style="cursor:default;"><i class="fas fa-check"></i> Joined</span>`
                                : `<button class="btn btn-primary btn-sm" onclick="joinGroup(${group.id})"><i class="fas fa-plus"></i> Join</button>`
                            }
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            modalContent.innerHTML = html;
        }

        function joinGroup(groupId) {
            if (!confirm('Are you sure you want to join this study group?')) return;
            
            const formData = new FormData();
            formData.append('group_id', groupId);
            
            fetch('join_group.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        }
        
        function closeModal() {
            document.getElementById('courseGroupsModal').style.display = 'none';
        }
        
        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            const p = document.createElement("p");
            p.textContent = str;
            return p.innerHTML;
        }

        window.addEventListener('click', (event) => {
            const modal = document.getElementById('courseGroupsModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        // For each course, fetch and show a preview of available groups
        window.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($courses as $course): ?>
            fetch('get_course_groups.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'course_id=<?php echo $course['id']; ?>'
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('course-groups-preview-<?php echo $course['id']; ?>');
                if (data.status === 'success' && data.groups.length > 0) {
                    let html = '<div class="groups-preview-list">';
                    data.groups.slice(0,2).forEach(group => {
                        html += `<div class='group-preview-item'>
                            <span><b>${escapeHTML(group.group_name)}</b> (${group.current_members}/${group.max_members})</span>
                            <a href='group_details.php?id=${group.id}' class='btn btn-outline btn-xs' style='margin-left:10px;'>View</a>
                            ${group.is_member ? `<span class='btn btn-success btn-xs' style='margin-left:5px;'>Joined</span>` : `<button class='btn btn-primary btn-xs' style='margin-left:5px;' onclick='joinGroup(${group.id})'>Join</button>`}
                        </div>`;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="empty-state" style="font-size:0.9em;color:#888;">No groups yet.</div>';
                }
            });
            <?php endforeach; ?>
        });
    </script>
</body>
</html>
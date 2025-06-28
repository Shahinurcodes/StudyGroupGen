<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    $conn = getConnection();
    $data = [];

    if ($user_type === 'student') {
        // Get student-specific data
        $data = getStudentDashboardData($conn, $user_id);
    } elseif ($user_type === 'faculty') {
        // Get faculty-specific data
        $data = getFacultyDashboardData($conn, $user_id);
    }

    $conn->close();
    
    header('Content-Type: application/json');
    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function getStudentDashboardData($conn, $student_id) {
    $data = [];
    
    // Get student info
    $stmt = $conn->prepare("SELECT full_name, department, trimester, cgpa FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    $data['student'] = $student;
    
    // Get available courses for student's department
    $stmt = $conn->prepare("SELECT c.*, f.first_name, f.last_name FROM courses c 
                           LEFT JOIN faculty f ON c.faculty_id = f.id 
                           WHERE c.department = ? AND c.is_active = 1 
                           ORDER BY c.trimester, c.course_code");
    $stmt->bind_param("s", $student['department']);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
    
    $data['courses'] = $courses;
    
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
    
    $data['groups'] = $groups;
    
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
    
    $data['stats'] = [
        'available_courses' => $total_courses,
        'active_groups' => $total_groups,
        'available_faculty' => $total_faculty
    ];
    
    return $data;
}

function getFacultyDashboardData($conn, $faculty_id) {
    $data = [];
    
    // Get faculty info
    $stmt = $conn->prepare("SELECT first_name, last_name, department FROM faculty WHERE id = ?");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $faculty = $result->fetch_assoc();
    $stmt->close();
    
    $data['faculty'] = $faculty;
    
    // Get faculty's mentored groups
    $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name, 
                           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                           FROM groups g 
                           JOIN courses c ON g.course_id = c.id 
                           WHERE g.faculty_mentor_id = ? 
                           ORDER BY g.status DESC, g.created_at DESC");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
    $stmt->close();
    
    $data['groups'] = $groups;
    
    // Get total students in faculty's groups
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT gm.student_id) as total_students 
                           FROM groups g 
                           JOIN group_members gm ON g.id = gm.group_id 
                           WHERE g.faculty_mentor_id = ?");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_students = $result->fetch_assoc()['total_students'];
    $stmt->close();
    
    // Get faculty's courses
    $stmt = $conn->prepare("SELECT COUNT(*) as total_courses FROM courses WHERE faculty_id = ? AND is_active = 1");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_courses = $result->fetch_assoc()['total_courses'];
    $stmt->close();
    
    $data['stats'] = [
        'assigned_groups' => count($groups),
        'active_students' => $total_students,
        'courses' => $total_courses,
        'upcoming_sessions' => 2 // This would be calculated from a sessions table
    ];
    
    return $data;
}
?> 
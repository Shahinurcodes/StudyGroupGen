<?php
session_start();

// Only allow POST and faculty
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'faculty') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: faculty_dashboard.php');
    exit();
}

$faculty_id = $_SESSION['user_id'];
$group_id = intval($_POST['group_id'] ?? 0);

if ($group_id <= 0) {
    $_SESSION['error_message'] = 'Invalid group ID.';
    header('Location: faculty_dashboard.php');
    exit();
}

require_once 'config.php';
try {
    $conn = getConnection();
    // Check if group exists and is in a course assigned to this faculty
    $stmt = $conn->prepare('SELECT g.id, g.course_id, c.faculty_id FROM groups g JOIN courses c ON g.course_id = c.id WHERE g.id = ?');
    $stmt->bind_param('i', $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = 'Group not found.';
        $stmt->close();
        $conn->close();
        header('Location: faculty_dashboard.php');
        exit();
    }
    $group = $result->fetch_assoc();
    $stmt->close();
    if ($group['faculty_id'] != $faculty_id) {
        $_SESSION['error_message'] = 'You can only mentor groups in your assigned courses.';
        $conn->close();
        header('Location: faculty_dashboard.php');
        exit();
    }
    // Assign this faculty as mentor
    $stmt = $conn->prepare('UPDATE groups SET faculty_mentor_id = ? WHERE id = ?');
    $stmt->bind_param('ii', $faculty_id, $group_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'You are now the mentor for this group.';
    } else {
        $_SESSION['error_message'] = 'Failed to assign yourself as mentor.';
    }
    $stmt->close();
    $conn->close();
    header('Location: faculty_dashboard.php');
    exit();
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    header('Location: faculty_dashboard.php');
    exit();
} 
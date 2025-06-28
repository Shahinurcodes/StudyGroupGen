<?php
session_start();

// Check if user is logged in and is a faculty member
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'faculty') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                echo json_encode(['status' => 'success', 'message' => 'Group leader assigned successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to assign group leader.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Group not found or you don\'t have permission to manage it.']);
        }
        
        $conn->close();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?> 
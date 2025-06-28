<?php
// Database Check and Fix Script
echo "<h2>Study Group Generator - Database Check & Fix</h2>";

// Include database configuration
require_once 'config.php';

try {
    echo "<p>Connecting to MySQL...</p>";
    
    // Get connection
    $conn = getConnection();
    
    echo "<p>✓ Database connection successful!</p>";
    
    // Check if database exists
    $result = $conn->query("SHOW DATABASES LIKE 'studygroupgen'");
    if ($result->num_rows > 0) {
        echo "<p>✓ Database 'studygroupgen' exists</p>";
    } else {
        echo "<p>✗ Database 'studygroupgen' does not exist - creating...</p>";
        $conn->query("CREATE DATABASE studygroupgen");
        echo "<p>✓ Database created successfully!</p>";
    }
    
    // Select the database
    $conn->select_db('studygroupgen');
    
    // Check tables
    $tables = ['students', 'faculty', 'courses', 'groups', 'group_members', 'student_courses', 'messages'];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo "<p>✓ All required tables exist</p>";
    } else {
        echo "<p>✗ Missing tables: " . implode(', ', $missing_tables) . "</p>";
        echo "<p>Setting up database schema...</p>";
        
        // Read and execute schema
        if (file_exists('complete_database_schema.sql')) {
            $sql_content = file_get_contents('complete_database_schema.sql');
            
            // Remove CREATE DATABASE and USE statements
            $sql_content = preg_replace('/CREATE DATABASE.*?;/i', '', $sql_content);
            $sql_content = preg_replace('/USE.*?;/i', '', $sql_content);
            
            // Split and execute statements
            $statements = array_filter(array_map('trim', explode(';', $sql_content)));
            
            $success_count = 0;
            $error_count = 0;
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^(--|\/\*)/', $statement)) {
                    if ($conn->query($statement) === TRUE) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                }
            }
            
            echo "<p>✓ Executed $success_count statements successfully</p>";
            if ($error_count > 0) {
                echo "<p>⚠ $error_count statements had errors (may be normal if tables exist)</p>";
            }
        } else {
            echo "<p>✗ Schema file 'complete_database_schema.sql' not found!</p>";
        }
    }
    
    // Check sample data
    $result = $conn->query("SELECT COUNT(*) as count FROM students");
    $student_count = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM faculty");
    $faculty_count = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM courses");
    $course_count = $result->fetch_assoc()['count'];
    
    echo "<h3>Sample Data Count:</h3>";
    echo "<p>Students: $student_count</p>";
    echo "<p>Faculty: $faculty_count</p>";
    echo "<p>Courses: $course_count</p>";
    
    // Test login functionality
    echo "<h3>Testing Login:</h3>";
    $email = 'john.smith@university.edu';
    $password = 'password';
    
    $stmt = $conn->prepare("SELECT id, first_name, last_name, password FROM faculty WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            echo "<p>✓ Sample faculty login works correctly</p>";
        } else {
            echo "<p>✗ Password verification failed</p>";
        }
    } else {
        echo "<p>✗ Sample faculty account not found</p>";
    }
    
    $stmt->close();
    $conn->close();
    
    echo "<h3>✅ Database Check Complete!</h3>";
    echo "<p><a href='index.php'>Go to Home Page</a></p>";
    echo "<p><a href='login.php'>Test Student Login</a></p>";
    echo "<p><a href='faculty_login.php'>Test Faculty Login</a></p>";
    
} catch (Exception $e) {
    echo "<p>✗ Error: " . $e->getMessage() . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ol>";
    echo "<li>Make sure MySQL is running</li>";
    echo "<li>Check if you need to set a password for the root user in config.php</li>";
    echo "<li>Verify that the MySQL user has privileges to create databases</li>";
    echo "</ol>";
}
?> 
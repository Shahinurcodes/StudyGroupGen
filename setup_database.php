<?php
// Database Setup Script
// Run this file once to set up your database

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Study Group Generator - Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        .step { margin: 10px 0; padding: 10px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .step.success { border-left-color: #28a745; background: #d4edda; }
        .step.error { border-left-color: #dc3545; background: #f8d7da; }
        .step.warning { border-left-color: #ffc107; background: #fff3cd; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Study Group Generator - Database Setup</h1>";

try {
    echo "<div class='step info'>Step 1: Checking PHP environment...</div>";
    
    // Check PHP version
    $php_version = phpversion();
    if (version_compare($php_version, '7.0.0', '>=')) {
        echo "<div class='step success'>✓ PHP Version: $php_version (OK)</div>";
    } else {
        echo "<div class='step error'>✗ PHP Version: $php_version (Requires 7.0+)</div>";
        throw new Exception("PHP version too old");
    }
    
    // Check required extensions
    $required_extensions = ['mysqli', 'json', 'session'];
    foreach ($required_extensions as $ext) {
        if (extension_loaded($ext)) {
            echo "<div class='step success'>✓ $ext extension loaded</div>";
        } else {
            echo "<div class='step error'>✗ $ext extension not loaded</div>";
            throw new Exception("Required extension $ext not loaded");
        }
    }
    
    echo "<div class='step info'>Step 2: Including database configuration...</div>";
    
    // Include database configuration
    if (!file_exists('config.php')) {
        echo "<div class='step error'>✗ config.php not found!</div>";
        throw new Exception("config.php file missing");
    }
    
    require_once 'config.php';
    echo "<div class='step success'>✓ config.php loaded successfully</div>";
    
    echo "<div class='step info'>Step 3: Testing database connection...</div>";
    
    // Test connection
    $conn = getConnection();
    echo "<div class='step success'>✓ Database connection successful!</div>";
    echo "<div class='step success'>✓ Database 'studygroupgen' created/selected successfully!</div>";
    
    echo "<div class='step info'>Step 4: Reading and executing database schema...</div>";
    
    // Read and execute the SQL schema
    $sql_file = 'complete_database_schema.sql';
    
    if (!file_exists($sql_file)) {
        echo "<div class='step error'>✗ $sql_file not found!</div>";
        throw new Exception("SQL schema file not found");
    }
    
    echo "<div class='step success'>✓ Reading database schema from $sql_file...</div>";
    
    $sql_content = file_get_contents($sql_file);
    if ($sql_content === false) {
        echo "<div class='step error'>✗ Failed to read SQL file!</div>";
        throw new Exception("Failed to read SQL file");
    }
    
    // Split the SQL file into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    echo "<div class='step info'>Executing SQL statements...</div>";
    
    foreach ($statements as $index => $statement) {
        if (!empty($statement) && !preg_match('/^(--|\/\*)/', $statement)) {
            try {
                if ($conn->query($statement) === TRUE) {
                    $success_count++;
                    if ($success_count <= 5) { // Show first 5 successful statements
                        echo "<div class='step success'>✓ Executed: " . substr($statement, 0, 50) . "...</div>";
                    }
                } else {
                    $error_count++;
                    $error_msg = $conn->error;
                    $errors[] = "Statement " . ($index + 1) . ": " . $error_msg;
                    echo "<div class='step error'>✗ Error: " . htmlspecialchars($error_msg) . "</div>";
                }
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Statement " . ($index + 1) . ": " . $e->getMessage();
                echo "<div class='step error'>✗ Exception: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
    
    echo "<div class='step info'>Step 5: Validating database setup...</div>";
    
    // Validate the setup
    $validation_result = validateDatabase();
    echo "<div class='step success'>✓ $validation_result</div>";
    
    // Check sample data
    $result = $conn->query("SELECT COUNT(*) as count FROM students");
    $student_count = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM faculty");
    $faculty_count = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM courses");
    $course_count = $result->fetch_assoc()['count'];
    
    echo "<div class='step success'>✓ Sample data loaded: $student_count students, $faculty_count faculty, $course_count courses</div>";
    
    $conn->close();
    
    echo "<div class='step success'>
        <h3>Setup Complete!</h3>
        <p>✓ Successfully executed $success_count statements</p>";
    
    if ($error_count > 0) {
        echo "<p class='warning'>⚠ $error_count statements had errors (this might be normal if tables already exist)</p>";
        if (count($errors) > 0) {
            echo "<details><summary>View Errors</summary><pre>" . htmlspecialchars(implode("\n", $errors)) . "</pre></details>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='step info'>
        <h3>Next Steps:</h3>
        <ol>
            <li>Make sure your MySQL server is running</li>
            <li>If you have a password for your MySQL root user, update it in config.php</li>
            <li>Test the application by visiting: <a href='index.php' class='btn'>Go to Homepage</a></li>
            <li>Try registering a student at: <a href='student_register.php' class='btn'>Student Registration</a></li>
            <li>Try faculty registration at: <a href='faculty-register.php' class='btn'>Faculty Registration</a></li>
        </ol>
    </div>";
    
    echo "<div class='step info'>
        <h3>Test Accounts:</h3>
        <p>You can test with these faculty accounts (password: 'password'):</p>
        <ul>
            <li>john.smith@university.edu</li>
            <li>sarah.johnson@university.edu</li>
            <li>michael.williams@university.edu</li>
        </ul>
        <p>Or register new accounts using the registration forms.</p>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='step error'>
        <h3>Setup Failed!</h3>
        <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
    
    echo "<div class='step warning'>
        <h3>Troubleshooting:</h3>
        <ol>
            <li>Make sure MySQL is running</li>
            <li>Check if you need to set a password for the root user in config.php</li>
            <li>Verify that the MySQL user has privileges to create databases</li>
            <li>Check that all required files exist in the project directory</li>
            <li>Ensure PHP has write permissions to the project directory</li>
        </ol>
    </div>";
}

echo "</div></body></html>";
?> 
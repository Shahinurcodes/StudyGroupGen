-- Complete Study Group Generator Database Schema
-- This file contains all tables, indexes, and sample data needed for the full system

-- Create the database
CREATE DATABASE IF NOT EXISTS studygroupgen;
USE studygroupgen;

-- Drop existing tables if they exist (for fresh installation)
DROP TABLE IF EXISTS session_attendance;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS study_materials;
DROP TABLE IF EXISTS group_progress;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS group_members;
DROP TABLE IF EXISTS student_courses;
DROP TABLE IF EXISTS groups;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS faculty;

-- Students table
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department ENUM('cse', 'eee', 'bba', 'eco', 'eng') NOT NULL,
    trimester INT NOT NULL,
    cgpa DECIMAL(3,2) NOT NULL,
    contact VARCHAR(20),
    profile_picture VARCHAR(255),
    enrollment_date DATE DEFAULT CURRENT_DATE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Faculty table
CREATE TABLE faculty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    faculty_id VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department ENUM('cse', 'eee', 'bba', 'eco', 'eng', 'mat', 'phy') NOT NULL,
    specializations TEXT,
    bio TEXT,
    profile_picture VARCHAR(255),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Courses table
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(10) NOT NULL UNIQUE,
    course_name VARCHAR(200) NOT NULL,
    department ENUM('cse', 'eee', 'bba', 'eco', 'eng', 'mat', 'phy') NOT NULL,
    credits INT DEFAULT 3,
    trimester INT NOT NULL,
    year INT NOT NULL,
    faculty_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE SET NULL
);

-- Groups table
CREATE TABLE groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    description TEXT,
    course_id INT NOT NULL,
    max_members INT DEFAULT 6,
    current_members INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    faculty_mentor_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_mentor_id) REFERENCES faculty(id) ON DELETE SET NULL
);

-- Group members table
CREATE TABLE group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    student_id INT NOT NULL,
    role ENUM('member', 'leader') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (group_id, student_id)
);

-- Student courses table
CREATE TABLE student_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    grade VARCHAR(2),
    status ENUM('enrolled', 'completed', 'dropped') DEFAULT 'enrolled',
    credits INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id)
);

-- Messages table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_type ENUM('student', 'faculty') NOT NULL,
    content TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Sessions table for study group meetings
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    scheduled_at DATETIME NOT NULL,
    duration INT DEFAULT 120, -- minutes
    location VARCHAR(100),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES faculty(id) ON DELETE CASCADE
);

-- Notes table for group notes
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    student_id INT,
    faculty_id INT,
    title VARCHAR(100),
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    CHECK (student_id IS NOT NULL OR faculty_id IS NOT NULL)
);

-- Session attendance table
CREATE TABLE session_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('attended', 'absent', 'late') DEFAULT 'attended',
    joined_at TIMESTAMP NULL,
    left_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (session_id, student_id)
);

-- Group announcements table
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_by INT NOT NULL,
    created_by_type ENUM('student', 'faculty') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Study materials table
CREATE TABLE study_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    file_type VARCHAR(50),
    file_size INT,
    uploaded_by INT NOT NULL,
    uploaded_by_type ENUM('student', 'faculty') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Group progress tracking
CREATE TABLE group_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    topic VARCHAR(200) NOT NULL,
    completion_percentage INT DEFAULT 0,
    notes TEXT,
    updated_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_groups_course ON groups(course_id);
CREATE INDEX idx_groups_faculty ON groups(faculty_mentor_id);
CREATE INDEX idx_group_members_group ON group_members(group_id);
CREATE INDEX idx_group_members_student ON group_members(student_id);
CREATE INDEX idx_messages_group ON messages(group_id);
CREATE INDEX idx_sessions_group ON sessions(group_id);
CREATE INDEX idx_notes_group ON notes(group_id);
CREATE INDEX idx_student_courses_student ON student_courses(student_id);
CREATE INDEX idx_student_courses_course ON student_courses(course_id);
CREATE INDEX idx_session_attendance_session ON session_attendance(session_id);
CREATE INDEX idx_session_attendance_student ON session_attendance(student_id);
CREATE INDEX idx_announcements_group ON announcements(group_id);
CREATE INDEX idx_study_materials_group ON study_materials(group_id);
CREATE INDEX idx_group_progress_group ON group_progress(group_id);

-- Insert sample faculty data
INSERT INTO faculty (first_name, last_name, faculty_id, email, password, department, specializations, bio) VALUES
('John', 'Smith', 'FAC001', 'john.smith@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cse', 'Algorithms, Data Structures', 'Experienced professor with 15 years of teaching experience'),
('Sarah', 'Johnson', 'FAC002', 'sarah.johnson@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cse', 'Database Systems, Software Engineering', 'Database expert with industry experience'),
('Michael', 'Williams', 'FAC003', 'michael.williams@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cse', 'Artificial Intelligence, Machine Learning', 'AI researcher with multiple publications'),
('Didar', 'Hossain', 'FAC004', 'didar.hossain@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cse', 'Data Structures, Algorithms', 'Assistant Professor specializing in algorithm design'),
('Mamun', 'Rahman', 'FAC005', 'mamun.rahman@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cse', 'Database Systems, Web Development', 'Senior Lecturer with expertise in modern web technologies');

-- Insert sample courses
INSERT INTO courses (course_code, course_name, department, credits, trimester, year, faculty_id, is_active) VALUES
('CSE-101', 'Introduction to Programming', 'cse', 3, 1, 2024, 1, TRUE),
('CSE-203', 'Data Structures & Algorithms', 'cse', 3, 3, 2024, 1, TRUE),
('CSE-205', 'Database Systems', 'cse', 3, 2, 2024, 2, TRUE),
('CSE-301', 'Operating Systems', 'cse', 3, 4, 2024, 3, TRUE),
('CSE-305', 'Artificial Intelligence', 'cse', 3, 5, 2024, 3, TRUE),
('CSE-410', 'Machine Learning', 'cse', 3, 6, 2024, NULL, TRUE),
('CSE-201', 'Object Oriented Programming', 'cse', 3, 2, 2024, 4, TRUE),
('CSE-207', 'Software Engineering', 'cse', 3, 3, 2024, 5, TRUE),
('CSE-401', 'Computer Networks', 'cse', 3, 7, 2024, NULL, TRUE),
('CSE-403', 'Software Testing', 'cse', 3, 8, 2024, NULL, TRUE),
('CSE-405', 'Web Development', 'cse', 3, 6, 2024, NULL, TRUE),
('CSE-407', 'Mobile App Development', 'cse', 3, 7, 2024, NULL, TRUE);

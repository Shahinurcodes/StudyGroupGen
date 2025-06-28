<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$user_name = $_SESSION['user_name'];
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

if ($group_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Verify user has access to this group
try {
    $conn = getConnection();
    
    if ($user_type === 'student') {
        $stmt = $conn->prepare("
            SELECT g.*, c.course_code, c.course_name
            FROM group_members gm
            JOIN groups g ON gm.group_id = g.id
            JOIN courses c ON g.course_id = c.id
            WHERE gm.student_id = ? AND g.id = ? AND g.status = 'active'
        ");
        $stmt->bind_param("ii", $user_id, $group_id);
    } else {
        $stmt = $conn->prepare("
            SELECT g.*, c.course_code, c.course_name
            FROM groups g
            JOIN courses c ON g.course_id = c.id
            WHERE g.faculty_mentor_id = ? AND g.id = ?
        ");
        $stmt->bind_param("ii", $user_id, $group_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: dashboard.php');
        exit();
    }
    
    $group = $result->fetch_assoc();
    $stmt->close();
    
    // Get group members
    $stmt = $conn->prepare("
        SELECT s.id, s.full_name, s.student_id, gm.role
        FROM group_members gm
        JOIN students s ON gm.student_id = s.id
        WHERE gm.group_id = ?
        ORDER BY gm.role DESC, s.full_name
    ");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    $stmt->close();
    
    $conn->close();
} catch (Exception $e) {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collaborative Whiteboard | <?php echo htmlspecialchars($group['group_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .whiteboard-container {
            display: flex;
            height: calc(100vh - 120px);
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        
        .whiteboard-sidebar {
            width: 300px;
            background: var(--gray-50);
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
        }
        
        .whiteboard-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .whiteboard-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--gray-200);
            background: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .whiteboard-title {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .whiteboard-subtitle {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }
        
        .whiteboard-canvas-container {
            flex: 1;
            position: relative;
            background: var(--white);
            overflow: hidden;
        }
        
        #whiteboardCanvas {
            position: absolute;
            top: 0;
            left: 0;
            cursor: crosshair;
            background: var(--white);
        }
        
        .toolbar {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--gray-200);
            background: var(--white);
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
            flex-wrap: wrap;
        }
        
        .tool-group {
            display: flex;
            gap: var(--spacing-xs);
            align-items: center;
            padding: var(--spacing-sm);
            border-radius: var(--radius-md);
            background: var(--gray-100);
        }
        
        .tool-btn {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: var(--radius-md);
            background: var(--white);
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: var(--font-size-lg);
            color: var(--gray-700);
            transition: all var(--transition-base);
        }
        
        .tool-btn:hover {
            background: var(--gray-200);
        }
        
        .tool-btn.active {
            background: var(--primary);
            color: var(--white);
        }
        
        .color-picker {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            background: var(--primary);
        }
        
        .size-slider {
            width: 100px;
            height: 6px;
            border-radius: var(--radius-full);
            background: var(--gray-300);
            outline: none;
            -webkit-appearance: none;
        }
        
        .size-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: var(--radius-full);
            background: var(--primary);
            cursor: pointer;
        }
        
        .size-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: var(--radius-full);
            background: var(--primary);
            cursor: pointer;
            border: none;
        }
        
        .action-btn {
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: var(--font-size-sm);
            transition: all var(--transition-base);
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .action-btn.primary {
            background: var(--primary);
            color: var(--white);
        }
        
        .action-btn.secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .action-btn.danger {
            background: var(--danger);
            color: var(--white);
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .participants-list {
            flex: 1;
            padding: var(--spacing-lg);
            overflow-y: auto;
        }
        
        .participants-header {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .participant-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-sm);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-sm);
        }
        
        .participant-avatar {
            width: 35px;
            height: 35px;
            border-radius: var(--radius-full);
            background: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--white);
            font-weight: 600;
            font-size: var(--font-size-sm);
        }
        
        .participant-info {
            flex: 1;
        }
        
        .participant-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: var(--font-size-sm);
        }
        
        .participant-status {
            color: var(--gray-600);
            font-size: var(--font-size-xs);
        }
        
        .online-indicator {
            width: 10px;
            height: 10px;
            border-radius: var(--radius-full);
            background: var(--success);
        }
        
        .chat-section {
            border-top: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            height: 200px;
        }
        
        .chat-messages {
            flex: 1;
            padding: var(--spacing-md);
            overflow-y: auto;
            background: var(--gray-50);
            font-size: var(--font-size-sm);
        }
        
        .chat-input {
            padding: var(--spacing-md);
            border-top: 1px solid var(--gray-200);
            background: var(--white);
        }
        
        .chat-input-form {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .chat-input-field {
            flex: 1;
            padding: var(--spacing-sm);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
        }
        
        .chat-send-btn {
            padding: var(--spacing-sm) var(--spacing-md);
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: var(--font-size-sm);
        }
        
        .zoom-controls {
            position: absolute;
            bottom: var(--spacing-lg);
            right: var(--spacing-lg);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
            background: var(--white);
            padding: var(--spacing-sm);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }
        
        .zoom-btn {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: var(--radius-md);
            background: var(--gray-200);
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: var(--font-size-lg);
            color: var(--gray-700);
        }
        
        .zoom-btn:hover {
            background: var(--gray-300);
        }
        
        .cursor-info {
            position: absolute;
            top: var(--spacing-sm);
            left: var(--spacing-sm);
            background: rgba(0, 0, 0, 0.7);
            color: var(--white);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-md);
            font-size: var(--font-size-xs);
            display: none;
        }
        
        .saved-sketches {
            padding: var(--spacing-lg);
            border-top: 1px solid var(--gray-200);
        }
        
        .saved-sketches h3 {
            margin-bottom: var(--spacing-md);
            color: var(--gray-900);
            font-size: var(--font-size-base);
        }
        
        .sketch-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }
        
        .sketch-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm);
            border-radius: var(--radius-md);
            background: var(--white);
            cursor: pointer;
            transition: all var(--transition-base);
        }
        
        .sketch-item:hover {
            background: var(--gray-100);
        }
        
        .sketch-thumbnail {
            width: 40px;
            height: 30px;
            border-radius: var(--radius-sm);
            background: var(--gray-200);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: var(--font-size-xs);
            color: var(--gray-600);
        }
        
        .sketch-info {
            flex: 1;
        }
        
        .sketch-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: var(--font-size-sm);
        }
        
        .sketch-date {
            color: var(--gray-600);
            font-size: var(--font-size-xs);
        }
        
        @media (max-width: 768px) {
            .whiteboard-container {
                flex-direction: column;
                height: calc(100vh - 80px);
            }
            
            .whiteboard-sidebar {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 1px solid var(--gray-200);
            }
            
            .toolbar {
                flex-wrap: wrap;
                gap: var(--spacing-sm);
            }
            
            .tool-group {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="group_details.php?group_id=<?php echo $group_id; ?>" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-arrow-left"></i>
                </div>
                <span class="logo-text">Back to Group</span>
            </a>
            <div class="user-menu">
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="user-role"><?php echo ucfirst($user_type); ?></span>
                </div>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            </div>
        </div>
    </header>

    <!-- Whiteboard Container -->
    <div class="container" style="padding: var(--spacing-lg) 0;">
        <div class="whiteboard-container">
            <!-- Whiteboard Sidebar -->
            <div class="whiteboard-sidebar">
                <div class="whiteboard-header">
                    <div>
                        <div class="whiteboard-title"><?php echo htmlspecialchars($group['group_name']); ?></div>
                        <div class="whiteboard-subtitle"><?php echo htmlspecialchars($group['course_code'] . ' - ' . $group['course_name']); ?></div>
                    </div>
                </div>
                
                <div class="participants-list">
                    <div class="participants-header">
                        <i class="fas fa-users"></i> Participants (<?php echo count($members); ?>)
                    </div>
                    <?php foreach ($members as $member): ?>
                        <div class="participant-item">
                            <div class="participant-avatar">
                                <?php echo strtoupper(substr($member['full_name'], 0, 2)); ?>
                            </div>
                            <div class="participant-info">
                                <div class="participant-name"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                <div class="participant-status"><?php echo ucfirst($member['role']); ?></div>
                            </div>
                            <div class="online-indicator"></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="chat-section">
                    <div class="chat-messages" id="chatMessages">
                        <!-- Chat messages will appear here -->
                    </div>
                    <div class="chat-input">
                        <form class="chat-input-form" id="chatForm">
                            <input type="text" class="chat-input-field" id="chatInput" placeholder="Type a message...">
                            <button type="submit" class="chat-send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="saved-sketches">
                    <h3>Saved Sketches</h3>
                    <div class="sketch-list" id="sketchList">
                        <!-- Saved sketches will appear here -->
                    </div>
                </div>
            </div>

            <!-- Whiteboard Main -->
            <div class="whiteboard-main">
                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="tool-group">
                        <button class="tool-btn active" data-tool="pen" title="Pen">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button class="tool-btn" data-tool="eraser" title="Eraser">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <button class="tool-btn" data-tool="line" title="Line">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button class="tool-btn" data-tool="rectangle" title="Rectangle">
                            <i class="fas fa-square"></i>
                        </button>
                        <button class="tool-btn" data-tool="circle" title="Circle">
                            <i class="fas fa-circle"></i>
                        </button>
                        <button class="tool-btn" data-tool="text" title="Text">
                            <i class="fas fa-font"></i>
                        </button>
                    </div>

                    <div class="tool-group">
                        <input type="color" class="color-picker" id="colorPicker" value="#000000" title="Color">
                        <input type="range" class="size-slider" id="sizeSlider" min="1" max="50" value="5" title="Size">
                        <span id="sizeValue">5px</span>
                    </div>

                    <div class="tool-group">
                        <button class="action-btn secondary" id="clearBtn" title="Clear Canvas">
                            <i class="fas fa-trash"></i> Clear
                        </button>
                        <button class="action-btn primary" id="saveBtn" title="Save Sketch">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button class="action-btn secondary" id="undoBtn" title="Undo">
                            <i class="fas fa-undo"></i> Undo
                        </button>
                        <button class="action-btn secondary" id="redoBtn" title="Redo">
                            <i class="fas fa-redo"></i> Redo
                        </button>
                    </div>
                </div>

                <!-- Canvas Container -->
                <div class="whiteboard-canvas-container">
                    <canvas id="whiteboardCanvas"></canvas>
                    <div class="cursor-info" id="cursorInfo"></div>
                    
                    <!-- Zoom Controls -->
                    <div class="zoom-controls">
                        <button class="zoom-btn" id="zoomInBtn" title="Zoom In">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="zoom-btn" id="zoomOutBtn" title="Zoom Out">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button class="zoom-btn" id="resetZoomBtn" title="Reset Zoom">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Canvas setup
        const canvas = document.getElementById('whiteboardCanvas');
        const ctx = canvas.getContext('2d');
        
        // Canvas state
        let isDrawing = false;
        let currentTool = 'pen';
        let currentColor = '#000000';
        let currentSize = 5;
        let zoom = 1;
        let offsetX = 0;
        let offsetY = 0;
        let isDragging = false;
        let lastX = 0;
        let lastY = 0;
        
        // History for undo/redo
        let history = [];
        let historyIndex = -1;
        let maxHistory = 50;
        
        // Initialize canvas
        function initializeCanvas() {
            resizeCanvas();
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            saveState();
            
            // Event listeners
            window.addEventListener('resize', resizeCanvas);
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);
            canvas.addEventListener('wheel', handleZoom);
            canvas.addEventListener('mousedown', startPan);
            canvas.addEventListener('mousemove', pan);
            canvas.addEventListener('mouseup', stopPan);
        }
        
        // Resize canvas
        function resizeCanvas() {
            const container = canvas.parentElement;
            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight;
            redraw();
        }
        
        // Start drawing
        function startDrawing(e) {
            if (e.button !== 0) return; // Only left mouse button
            
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            lastX = (e.clientX - rect.left) / zoom - offsetX;
            lastY = (e.clientY - rect.top) / zoom - offsetY;
            
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        }
        
        // Draw
        function draw(e) {
            if (!isDrawing) return;
            
            const rect = canvas.getBoundingClientRect();
            const x = (e.clientX - rect.left) / zoom - offsetX;
            const y = (e.clientY - rect.top) / zoom - offsetY;
            
            ctx.strokeStyle = currentColor;
            ctx.lineWidth = currentSize;
            
            switch (currentTool) {
                case 'pen':
                    ctx.lineTo(x, y);
                    ctx.stroke();
                    break;
                case 'eraser':
                    ctx.globalCompositeOperation = 'destination-out';
                    ctx.lineTo(x, y);
                    ctx.stroke();
                    ctx.globalCompositeOperation = 'source-over';
                    break;
                case 'line':
                    redraw();
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(x, y);
                    ctx.stroke();
                    break;
                case 'rectangle':
                    redraw();
                    const width = x - lastX;
                    const height = y - lastY;
                    ctx.strokeRect(lastX, lastY, width, height);
                    break;
                case 'circle':
                    redraw();
                    const radius = Math.sqrt(Math.pow(x - lastX, 2) + Math.pow(y - lastY, 2));
                    ctx.beginPath();
                    ctx.arc(lastX, lastY, radius, 0, 2 * Math.PI);
                    ctx.stroke();
                    break;
            }
            
            lastX = x;
            lastY = y;
        }
        
        // Stop drawing
        function stopDrawing() {
            if (isDrawing) {
                isDrawing = false;
                if (currentTool !== 'pen' && currentTool !== 'eraser') {
                    saveState();
                }
            }
        }
        
        // Handle zoom
        function handleZoom(e) {
            e.preventDefault();
            
            const rect = canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            
            const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
            const newZoom = Math.max(0.1, Math.min(5, zoom * zoomFactor));
            
            if (newZoom !== zoom) {
                offsetX = mouseX / newZoom - (mouseX / zoom - offsetX);
                offsetY = mouseY / newZoom - (mouseY / zoom - offsetY);
                zoom = newZoom;
                redraw();
            }
        }
        
        // Start panning
        function startPan(e) {
            if (e.button === 1 || (e.button === 0 && e.altKey)) { // Middle mouse or Alt+Left
                e.preventDefault();
                isDragging = true;
                lastX = e.clientX;
                lastY = e.clientY;
            }
        }
        
        // Pan
        function pan(e) {
            if (isDragging) {
                const deltaX = e.clientX - lastX;
                const deltaY = e.clientY - lastY;
                
                offsetX += deltaX / zoom;
                offsetY += deltaY / zoom;
                
                lastX = e.clientX;
                lastY = e.clientY;
                
                redraw();
            }
        }
        
        // Stop panning
        function stopPan() {
            isDragging = false;
        }
        
        // Redraw canvas
        function redraw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.save();
            ctx.translate(offsetX * zoom, offsetY * zoom);
            ctx.scale(zoom, zoom);
            
            // Redraw from history
            if (historyIndex >= 0) {
                ctx.drawImage(history[historyIndex], 0, 0);
            }
            
            ctx.restore();
        }
        
        // Save state for undo/redo
        function saveState() {
            // Remove any states after current index
            history = history.slice(0, historyIndex + 1);
            
            // Create new state
            const imageData = canvas.toDataURL();
            history.push(imageData);
            historyIndex++;
            
            // Limit history size
            if (history.length > maxHistory) {
                history.shift();
                historyIndex--;
            }
        }
        
        // Undo
        function undo() {
            if (historyIndex > 0) {
                historyIndex--;
                redraw();
            }
        }
        
        // Redo
        function redo() {
            if (historyIndex < history.length - 1) {
                historyIndex++;
                redraw();
            }
        }
        
        // Clear canvas
        function clearCanvas() {
            if (confirm('Are you sure you want to clear the canvas?')) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                saveState();
            }
        }
        
        // Save sketch
        function saveSketch() {
            const sketchName = prompt('Enter a name for this sketch:');
            if (sketchName) {
                const imageData = canvas.toDataURL();
                const sketch = {
                    name: sketchName,
                    data: imageData,
                    date: new Date().toISOString(),
                    group_id: <?php echo $group_id; ?>
                };
                
                // Save to localStorage for demo (in real app, save to server)
                const sketches = JSON.parse(localStorage.getItem('sketches') || '[]');
                sketches.push(sketch);
                localStorage.setItem('sketches', JSON.stringify(sketches));
                
                loadSketches();
                alert('Sketch saved successfully!');
            }
        }
        
        // Load sketches
        function loadSketches() {
            const sketchList = document.getElementById('sketchList');
            const sketches = JSON.parse(localStorage.getItem('sketches') || '[]');
            const groupSketches = sketches.filter(s => s.group_id == <?php echo $group_id; ?>);
            
            sketchList.innerHTML = '';
            
            groupSketches.forEach((sketch, index) => {
                const item = document.createElement('div');
                item.className = 'sketch-item';
                item.innerHTML = `
                    <div class="sketch-thumbnail">
                        <i class="fas fa-image"></i>
                    </div>
                    <div class="sketch-info">
                        <div class="sketch-name">${sketch.name}</div>
                        <div class="sketch-date">${new Date(sketch.date).toLocaleDateString()}</div>
                    </div>
                    <button class="action-btn secondary" onclick="loadSketch(${index})">
                        <i class="fas fa-download"></i>
                    </button>
                `;
                sketchList.appendChild(item);
            });
        }
        
        // Load sketch
        function loadSketch(index) {
            const sketches = JSON.parse(localStorage.getItem('sketches') || '[]');
            const groupSketches = sketches.filter(s => s.group_id == <?php echo $group_id; ?>);
            const sketch = groupSketches[index];
            
            if (sketch) {
                const img = new Image();
                img.onload = function() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0);
                    saveState();
                };
                img.src = sketch.data;
            }
        }
        
        // Tool selection
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentTool = this.dataset.tool;
            });
        });
        
        // Color picker
        document.getElementById('colorPicker').addEventListener('change', function() {
            currentColor = this.value;
        });
        
        // Size slider
        document.getElementById('sizeSlider').addEventListener('input', function() {
            currentSize = this.value;
            document.getElementById('sizeValue').textContent = this.value + 'px';
        });
        
        // Action buttons
        document.getElementById('clearBtn').addEventListener('click', clearCanvas);
        document.getElementById('saveBtn').addEventListener('click', saveSketch);
        document.getElementById('undoBtn').addEventListener('click', undo);
        document.getElementById('redoBtn').addEventListener('click', redo);
        
        // Zoom buttons
        document.getElementById('zoomInBtn').addEventListener('click', () => {
            zoom = Math.min(5, zoom * 1.2);
            redraw();
        });
        
        document.getElementById('zoomOutBtn').addEventListener('click', () => {
            zoom = Math.max(0.1, zoom / 1.2);
            redraw();
        });
        
        document.getElementById('resetZoomBtn').addEventListener('click', () => {
            zoom = 1;
            offsetX = 0;
            offsetY = 0;
            redraw();
        });
        
        // Chat functionality
        document.getElementById('chatForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (message) {
                addChatMessage('<?php echo addslashes($user_name); ?>', message, 'own');
                input.value = '';
            }
        });
        
        // Add chat message
        function addChatMessage(sender, content, type = 'received') {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            
            const time = new Date().toLocaleTimeString();
            
            messageDiv.innerHTML = `
                <div class="message-header">
                    <span class="message-sender">${sender}</span>
                    <span class="message-time">${time}</span>
                </div>
                <div class="message-content">${content}</div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCanvas();
            loadSketches();
        });
    </script>
</body>
</html> 
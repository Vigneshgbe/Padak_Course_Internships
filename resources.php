<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { 
    header('Location: login.php'); 
    exit; 
}

$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'resources';

// =============================================
// UPLOADS DIRECTORY SETUP
// =============================================
$uploadsDir = __DIR__ . '/uploads/resources';
$uploadsUrl = 'uploads/resources';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// =============================================
// ADMIN AUTHENTICATION
// =============================================
$isAdmin = false;
$adminPassword = 'vigneshg091002';

if (isset($_POST['admin_auth'])) {
    if ($_POST['admin_password'] === $adminPassword) {
        $_SESSION['resource_admin'] = true;
        $_SESSION['resource_admin_time'] = time();
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid password']);
        exit;
    }
}

// Check admin session (expires after 30 minutes)
if (isset($_SESSION['resource_admin']) && 
    isset($_SESSION['resource_admin_time']) && 
    (time() - $_SESSION['resource_admin_time']) < 1800) {
    $isAdmin = true;
    $_SESSION['resource_admin_time'] = time(); // Refresh timeout
}

if (isset($_POST['admin_logout'])) {
    unset($_SESSION['resource_admin']);
    unset($_SESSION['resource_admin_time']);
    echo json_encode(['success' => true]);
    exit;
}

// =============================================
// DATABASE SCHEMA CHECK & CREATION
// =============================================
$tableCheck = $db->query("SHOW TABLES LIKE 'resource_documents'");
if ($tableCheck->num_rows === 0) {
    $db->query("
        CREATE TABLE resource_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(100) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_size BIGINT NOT NULL,
            file_type VARCHAR(50) NOT NULL,
            uploaded_by INT NOT NULL,
            download_count INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES internship_students(id),
            INDEX idx_category (category),
            INDEX idx_active (is_active),
            INDEX idx_created (created_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->query("
        CREATE TABLE resource_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            icon VARCHAR(50) DEFAULT 'fa-folder',
            color VARCHAR(20) DEFAULT '#f97316',
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert default categories
    $db->query("
        INSERT INTO resource_categories (name, icon, color, display_order) VALUES
        ('Programming', 'fa-code', '#3b82f6', 1),
        ('Design', 'fa-palette', '#8b5cf6', 2),
        ('Documentation', 'fa-file-alt', '#10b981', 3),
        ('Tutorials', 'fa-graduation-cap', '#f59e0b', 4),
        ('Templates', 'fa-copy', '#ec4899', 5),
        ('Tools', 'fa-wrench', '#6366f1', 6),
        ('Research', 'fa-flask', '#14b8a6', 7),
        ('Other', 'fa-folder', '#64748b', 8)
    ");
}

// =============================================
// AJAX HANDLERS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();

    // --------------------------------------------------
    // UPLOAD DOCUMENT (Any authenticated user)
    // --------------------------------------------------
    if ($_POST['action'] === 'upload_document') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        
        if (!$title || !$category) {
            echo json_encode(['success' => false, 'error' => 'Title and category required']);
            exit;
        }
        
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'File upload error']);
            exit;
        }
        
        $file = $_FILES['document'];
        $fileName = basename($file['name']);
        $fileSize = $file['size'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Allowed file types (50MB max)
        $allowedTypes = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'zip', 'rar', '7z',
            'jpg', 'jpeg', 'png', 'gif', 'svg',
            'mp4', 'avi', 'mkv', 'mov',
            'mp3', 'wav',
            'html', 'css', 'js', 'json', 'xml',
            'py', 'java', 'cpp', 'c', 'php', 'sql'
        ];
        
        if (!in_array($fileExt, $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => "File type '$fileExt' not allowed"]);
            exit;
        }
        
        if ($fileSize > 50 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File too large (max 50MB)']);
            exit;
        }
        
        // Generate unique filename
        $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
        $destPath = $uploadsDir . '/' . $newFileName;
        
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save file']);
            exit;
        }
        
        // Determine MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $destPath);
        finfo_close($finfo);
        
        // Insert into database
        $stmt = $db->prepare("
            INSERT INTO resource_documents 
            (title, description, category, file_path, file_name, file_size, file_type, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $filePath = $uploadsUrl . '/' . $newFileName;
        $stmt->bind_param(
            "sssssisi", 
            $title, 
            $description, 
            $category, 
            $filePath, 
            $fileName, 
            $fileSize, 
            $mimeType, 
            $sid
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'id' => $db->insert_id,
                'message' => 'Document uploaded successfully'
            ]);
        } else {
            unlink($destPath); // Clean up file on DB error
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
        }
        
        $stmt->close();
        exit;
    }
    
    // --------------------------------------------------
    // DOWNLOAD DOCUMENT (Increment counter)
    // --------------------------------------------------
    if ($_POST['action'] === 'download_document') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        if ($docId) {
            $stmt = $db->prepare("UPDATE resource_documents SET download_count = download_count + 1 WHERE id = ?");
            $stmt->bind_param("i", $docId);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
        }
        exit;
    }
    
    // --------------------------------------------------
    // ADMIN: UPDATE DOCUMENT
    // --------------------------------------------------
    if ($_POST['action'] === 'admin_update_document' && $isAdmin) {
        $docId       = (int)($_POST['doc_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        
        if (!$docId || !$title || !$category) {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
            exit;
        }
        
        $stmt = $db->prepare("
            UPDATE resource_documents 
            SET title = ?, description = ?, category = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("sssi", $title, $description, $category, $docId);
        
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }
    
    // --------------------------------------------------
    // ADMIN: DELETE DOCUMENT
    // --------------------------------------------------
    if ($_POST['action'] === 'admin_delete_document' && $isAdmin) {
        $docId = (int)($_POST['doc_id'] ?? 0);
        if (!$docId) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            exit;
        }
        
        // Get file path first
        $stmt = $db->prepare("SELECT file_path FROM resource_documents WHERE id = ?");
        $stmt->bind_param("i", $docId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result) {
            // Delete file from filesystem
            $fullPath = __DIR__ . '/' . $result['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Delete from database
            $stmt = $db->prepare("DELETE FROM resource_documents WHERE id = ?");
            $stmt->bind_param("i", $docId);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Document not found']);
        }
        exit;
    }
    
    // --------------------------------------------------
    // ADMIN: TOGGLE DOCUMENT STATUS
    // --------------------------------------------------
    if ($_POST['action'] === 'admin_toggle_status' && $isAdmin) {
        $docId = (int)($_POST['doc_id'] ?? 0);
        if ($docId) {
            $stmt = $db->prepare("UPDATE resource_documents SET is_active = NOT is_active WHERE id = ?");
            $stmt->bind_param("i", $docId);
            echo json_encode(['success' => $stmt->execute()]);
            $stmt->close();
        }
        exit;
    }
    
    // --------------------------------------------------
    // ADMIN: ADD CATEGORY
    // --------------------------------------------------
    if ($_POST['action'] === 'admin_add_category' && $isAdmin) {
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-folder');
        $color = trim($_POST['color'] ?? '#f97316');
        
        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'Category name required']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO resource_categories (name, icon, color) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $icon, $color);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }
    
    // --------------------------------------------------
    // ADMIN: DELETE CATEGORY
    // --------------------------------------------------
    if ($_POST['action'] === 'admin_delete_category' && $isAdmin) {
        $catId = (int)($_POST['cat_id'] ?? 0);
        if ($catId) {
            // Move documents to "Other" category first
            $db->query("UPDATE resource_documents SET category = 'Other' WHERE category = (SELECT name FROM resource_categories WHERE id = $catId)");
            
            $stmt = $db->prepare("DELETE FROM resource_categories WHERE id = ?");
            $stmt->bind_param("i", $catId);
            echo json_encode(['success' => $stmt->execute()]);
            $stmt->close();
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// =============================================
// FETCH DATA FOR PAGE
// =============================================

// Get categories
$categoriesResult = $db->query("
    SELECT c.*, COUNT(d.id) as doc_count 
    FROM resource_categories c 
    LEFT JOIN resource_documents d ON d.category = c.name AND d.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.display_order, c.name
");
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row;
}

// Get documents
$documentsQuery = "
    SELECT d.*, s.full_name as uploader_name, s.profile_photo as uploader_photo
    FROM resource_documents d
    LEFT JOIN internship_students s ON s.id = d.uploaded_by
    WHERE d.is_active = 1
    ORDER BY d.created_at DESC
";

if ($isAdmin) {
    // Admins see all documents including inactive
    $documentsQuery = "
        SELECT d.*, s.full_name as uploader_name, s.profile_photo as uploader_photo
        FROM resource_documents d
        LEFT JOIN internship_students s ON s.id = d.uploaded_by
        ORDER BY d.created_at DESC
    ";
}

$documentsResult = $db->query($documentsQuery);
$documents = [];
while ($row = $documentsResult->fetch_assoc()) {
    $documents[] = $row;
}

// Statistics
$stats = [
    'total_documents' => count($documents),
    'total_categories' => count($categories),
    'total_downloads' => array_sum(array_column($documents, 'download_count')),
    'total_size' => array_sum(array_column($documents, 'file_size'))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Resource Library</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --sbw:258px;
    --o5:#f97316;--o4:#fb923c;--o6:#ea580c;
    --bg:#f8fafc;--card:#fff;
    --text:#0f172a;--text2:#475569;--text3:#94a3b8;
    --border:#e2e8f0;
    --success:#10b981;--danger:#ef4444;--warning:#f59e0b;--info:#3b82f6;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;}

.page-wrap{margin-left:var(--sbw);min-height:100vh;padding:20px 24px;}

/* Header */
.page-header{background:linear-gradient(135deg,var(--o5),var(--o4));padding:32px;border-radius:16px;margin-bottom:28px;box-shadow:0 10px 40px rgba(249,115,22,0.25);position:relative;overflow:hidden;}
.page-header::before{content:'';position:absolute;top:-50%;right:-10%;width:300px;height:300px;background:rgba(255,255,255,0.1);border-radius:50%;pointer-events:none;}
.page-header h1{font-size:2rem;font-weight:900;color:#fff;margin-bottom:8px;display:flex;align-items:center;gap:14px;}
.page-header p{color:rgba(255,255,255,0.95);font-size:1rem;font-weight:500;}

/* Admin Icon */
.admin-trigger{position:absolute;top:20px;right:20px;width:44px;height:44px;background:rgba(255,255,255,0.2);border:2px solid rgba(255,255,255,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .3s;backdrop-filter:blur(10px);}
.admin-trigger:hover{background:rgba(255,255,255,0.3);transform:scale(1.1) rotate(15deg);}
.admin-trigger i{color:#fff;font-size:1.2rem;}
.admin-trigger.active{background:var(--success);border-color:var(--success);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(16,185,129,0.7);}50%{box-shadow:0 0 0 12px rgba(16,185,129,0);}}

/* Stats Grid */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:28px;}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;display:flex;align-items:center;gap:16px;transition:all .3s;}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(0,0,0,0.12);}
.stat-icon{width:56px;height:56px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;flex-shrink:0;}
.stat-icon.documents{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.stat-icon.categories{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.stat-icon.downloads{background:linear-gradient(135deg,#10b981,#059669);}
.stat-icon.size{background:linear-gradient(135deg,#f59e0b,#d97706);}
.stat-info{flex:1;}
.stat-label{font-size:0.8rem;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;}
.stat-value{font-size:1.7rem;font-weight:900;color:var(--text);}

/* Controls Bar */
.controls-bar{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.search-box{flex:1;min-width:280px;position:relative;}
.search-input{width:100%;padding:12px 16px 12px 44px;border:2px solid var(--border);border-radius:10px;font-size:0.95rem;font-family:inherit;outline:none;transition:all .2s;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23475569' viewBox='0 0 24 24'%3E%3Cpath d='M15.5 14h-.79l-.28-.27a6.5 6.5 0 001.48-5.34c-.47-2.78-2.79-5-5.59-5.34a6.505 6.505 0 00-7.27 7.27c.34 2.8 2.56 5.12 5.34 5.59a6.5 6.5 0 005.34-1.48l.27.28v.79l4.25 4.25c.41.41 1.08.41 1.49 0 .41-.41.41-1.08 0-1.49L15.5 14zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E") no-repeat 14px center/20px;}
.search-input:focus{border-color:var(--o5);background-color:#fff;}

.filter-select{padding:12px 16px;border:2px solid var(--border);border-radius:10px;font-size:0.9rem;font-family:inherit;outline:none;cursor:pointer;background:var(--card);transition:all .2s;min-width:160px;}
.filter-select:focus,.filter-select:hover{border-color:var(--o5);}

.btn{padding:12px 20px;border:none;border-radius:10px;font-size:0.9rem;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px;transition:all .3s;text-decoration:none;white-space:nowrap;}
.btn-primary{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;box-shadow:0 4px 14px rgba(249,115,22,0.3);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(249,115,22,0.4);}
.btn-secondary{background:var(--bg);color:var(--text2);border:2px solid var(--border);}
.btn-secondary:hover{background:var(--card);border-color:var(--o5);color:var(--o5);}

/* Categories Pills */
.categories-filter{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;}
.category-pill{padding:10px 18px;border-radius:20px;font-size:0.85rem;font-weight:600;cursor:pointer;border:2px solid transparent;transition:all .2s;display:inline-flex;align-items:center;gap:8px;background:var(--card);color:var(--text2);border-color:var(--border);}
.category-pill:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.category-pill.active{color:#fff;border-color:transparent;box-shadow:0 6px 16px rgba(0,0,0,0.2);}
.category-badge{background:rgba(255,255,255,0.3);color:#fff;padding:2px 8px;border-radius:10px;font-size:0.75rem;font-weight:800;}
.category-pill:not(.active) .category-badge{background:var(--bg);color:var(--text3);}

/* Documents Grid */
.documents-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-bottom:40px;}
.doc-card{background:var(--card);border:2px solid var(--border);border-radius:14px;overflow:hidden;transition:all .3s;cursor:pointer;position:relative;}
.doc-card:hover{transform:translateY(-6px);box-shadow:0 12px 32px rgba(0,0,0,0.15);border-color:var(--o5);}
.doc-card.inactive{opacity:0.6;border-style:dashed;}
.doc-card.inactive::after{content:'INACTIVE';position:absolute;top:12px;right:12px;background:var(--danger);color:#fff;padding:4px 12px;border-radius:6px;font-size:0.7rem;font-weight:800;}

.doc-header{padding:20px;background:linear-gradient(135deg,#f8fafc,#e2e8f0);border-bottom:2px solid var(--border);position:relative;}
.doc-file-icon{width:64px;height:64px;margin:0 auto 12px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;position:relative;}
.doc-type-badge{position:absolute;bottom:-6px;right:-6px;background:var(--card);border:2px solid var(--border);padding:2px 8px;border-radius:6px;font-size:0.65rem;font-weight:800;text-transform:uppercase;color:var(--text2);}

.doc-body{padding:20px;}
.doc-title{font-size:1.05rem;font-weight:700;color:var(--text);margin-bottom:8px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.doc-description{font-size:0.85rem;color:var(--text2);line-height:1.6;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
.doc-meta{display:flex;align-items:center;gap:12px;font-size:0.75rem;color:var(--text3);margin-bottom:14px;flex-wrap:wrap;}
.doc-meta-item{display:flex;align-items:center;gap:4px;}

.doc-uploader{display:flex;align-items:center;gap:8px;padding:12px;background:var(--bg);border-radius:8px;margin-bottom:14px;}
.uploader-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.85rem;}
.uploader-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.uploader-info{flex:1;}
.uploader-name{font-size:0.8rem;font-weight:600;color:var(--text);}
.uploader-date{font-size:0.7rem;color:var(--text3);}

.doc-actions{display:flex;gap:8px;}
.btn-doc{padding:10px;border:none;border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:600;flex:1;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .2s;}
.btn-download{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;}
.btn-download:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(249,115,22,0.3);}
.btn-edit{background:var(--info);color:#fff;}
.btn-delete{background:var(--danger);color:#fff;}
.btn-toggle{background:var(--warning);color:#fff;}

/* Empty State */
.empty-state{text-align:center;padding:80px 20px;color:var(--text3);}
.empty-state i{font-size:4rem;margin-bottom:20px;opacity:0.3;}
.empty-state h3{font-size:1.3rem;font-weight:700;margin-bottom:8px;color:var(--text2);}
.empty-state p{font-size:0.95rem;}

/* Modals */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal{background:var(--card);border-radius:16px;width:100%;max-width:550px;max-height:90vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:modalSlideUp 0.3s ease;}
@keyframes modalSlideUp{from{transform:translateY(50px);opacity:0;}to{transform:translateY(0);opacity:1;}}

.modal-header{padding:24px 28px;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:space-between;}
.modal-header h3{font-size:1.3rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:8px;cursor:pointer;font-size:1.4rem;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,0.3);transform:rotate(90deg);}

.modal-body{padding:28px;max-height:calc(90vh - 160px);overflow-y:auto;}
.form-group{margin-bottom:20px;}
.form-label{display:block;font-size:0.85rem;font-weight:700;color:var(--text);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;}
.form-input,.form-textarea,.form-select{width:100%;padding:12px 14px;border:2px solid var(--border);border-radius:10px;font-size:0.95rem;font-family:inherit;outline:none;transition:all .2s;background:var(--bg);}
.form-input:focus,.form-textarea:focus,.form-select:focus{border-color:var(--o5);background:var(--card);}
.form-textarea{resize:vertical;min-height:100px;}

.file-upload-area{border:3px dashed var(--border);border-radius:12px;padding:40px 20px;text-align:center;cursor:pointer;transition:all .3s;background:var(--bg);}
.file-upload-area:hover,.file-upload-area.drag-over{border-color:var(--o5);background:rgba(249,115,22,0.05);}
.file-upload-area i{font-size:3rem;color:var(--o5);margin-bottom:12px;display:block;}
.file-upload-area p{color:var(--text2);font-size:0.9rem;margin-bottom:6px;}
.file-upload-area small{color:var(--text3);font-size:0.8rem;}
.file-selected{background:rgba(16,185,129,0.1);border-color:var(--success);}
.file-selected .file-name{color:var(--success);font-weight:700;margin-top:8px;font-size:0.9rem;}

.modal-footer{padding:20px 28px;border-top:2px solid var(--border);display:flex;gap:12px;justify-content:flex-end;}

/* Admin Panel */
.admin-panel{position:fixed;top:0;right:0;width:360px;height:100vh;background:var(--card);box-shadow:-4px 0 30px rgba(0,0,0,0.15);transform:translateX(100%);transition:transform .3s;z-index:10000;display:flex;flex-direction:column;}
.admin-panel.open{transform:translateX(0);}
.admin-panel-header{padding:20px;background:linear-gradient(135deg,var(--o6),var(--o5));color:#fff;display:flex;align-items:center;justify-content:space-between;}
.admin-panel-header h3{font-size:1.1rem;font-weight:800;}
.admin-panel-body{flex:1;overflow-y:auto;padding:20px;}
.admin-section{margin-bottom:24px;padding-bottom:24px;border-bottom:2px solid var(--border);}
.admin-section:last-child{border-bottom:none;}
.admin-section-title{font-size:0.8rem;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;}

.category-list-item{display:flex;align-items:center;gap:10px;padding:12px;background:var(--bg);border-radius:8px;margin-bottom:8px;}
.category-color-dot{width:28px;height:28px;border-radius:50%;flex-shrink:0;}
.category-list-info{flex:1;min-width:0;}
.category-list-name{font-size:0.85rem;font-weight:700;color:var(--text);}
.category-list-count{font-size:0.7rem;color:var(--text3);}
.btn-category-delete{background:var(--danger);color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer;font-size:0.7rem;font-weight:700;}

@media(max-width:768px){
    .page-wrap{margin-left:0;padding:16px;}
    .page-header{padding:24px 20px;}
    .page-header h1{font-size:1.5rem;}
    .stats-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
    .controls-bar{flex-direction:column;align-items:stretch;}
    .search-box{min-width:100%;}
    .filter-select{width:100%;}
    .documents-grid{grid-template-columns:1fr;gap:16px;}
    .admin-panel{width:100%;}
    .doc-actions{flex-direction:column;}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="page-wrap">
    <!-- Header with Admin Icon -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-book-open"></i> Resource Library</h1>
            <p>Access and share valuable learning materials, documents, and tools</p>
        </div>
        <div class="admin-trigger <?php echo $isAdmin ? 'active' : ''; ?>" onclick="<?php echo $isAdmin ? 'toggleAdminPanel()' : 'openAdminAuth()'; ?>" title="<?php echo $isAdmin ? 'Admin Panel' : 'Admin Login'; ?>">
            <i class="fas fa-<?php echo $isAdmin ? 'shield-alt' : 'lock'; ?>"></i>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon documents"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Documents</div>
                <div class="stat-value"><?php echo $stats['total_documents']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon categories"><i class="fas fa-folder-open"></i></div>
            <div class="stat-info">
                <div class="stat-label">Categories</div>
                <div class="stat-value"><?php echo $stats['total_categories']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon downloads"><i class="fas fa-download"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Downloads</div>
                <div class="stat-value"><?php echo number_format($stats['total_downloads']); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon size"><i class="fas fa-database"></i></div>
            <div class="stat-info">
                <div class="stat-label">Storage Used</div>
                <div class="stat-value"><?php echo formatBytes($stats['total_size']); ?></div>
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="controls-bar">
        <div class="search-box">
            <input type="text" class="search-input" id="searchInput" placeholder="Search documents by title, description, or uploader...">
        </div>
        <select class="filter-select" id="sortSelect">
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
            <option value="downloads">Most Downloaded</option>
            <option value="title">Title A-Z</option>
        </select>
        <button class="btn btn-primary" onclick="openUploadModal()">
            <i class="fas fa-cloud-upload-alt"></i> Upload Document
        </button>
    </div>

    <!-- Category Filter Pills -->
    <div class="categories-filter">
        <div class="category-pill active" data-category="all" onclick="filterByCategory('all')" style="background:linear-gradient(135deg,var(--o5),var(--o4));">
            <i class="fas fa-grip-horizontal"></i>
            <span>All Documents</span>
            <span class="category-badge"><?php echo $stats['total_documents']; ?></span>
        </div>
        <?php foreach ($categories as $cat): ?>
        <div class="category-pill" data-category="<?php echo htmlspecialchars($cat['name']); ?>" onclick="filterByCategory('<?php echo htmlspecialchars($cat['name']); ?>')" style="--cat-color:<?php echo htmlspecialchars($cat['color']); ?>;">
            <i class="fas <?php echo htmlspecialchars($cat['icon']); ?>"></i>
            <span><?php echo htmlspecialchars($cat['name']); ?></span>
            <span class="category-badge"><?php echo $cat['doc_count']; ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Documents Grid -->
    <div class="documents-grid" id="documentsGrid">
        <?php if (empty($documents)): ?>
        <div class="empty-state" style="grid-column:1/-1;">
            <i class="fas fa-inbox"></i>
            <h3>No Documents Yet</h3>
            <p>Be the first to upload a valuable resource for the community!</p>
        </div>
        <?php else: ?>
        <?php foreach ($documents as $doc): 
            $fileIcon = getFileIcon($doc['file_name']);
            $fileColor = getFileColor($doc['file_name']);
        ?>
        <div class="doc-card <?php echo $doc['is_active'] ? '' : 'inactive'; ?>" 
             data-category="<?php echo htmlspecialchars($doc['category']); ?>"
             data-title="<?php echo htmlspecialchars(strtolower($doc['title'])); ?>"
             data-description="<?php echo htmlspecialchars(strtolower($doc['description'] ?? '')); ?>"
             data-uploader="<?php echo htmlspecialchars(strtolower($doc['uploader_name'] ?? '')); ?>"
             data-date="<?php echo strtotime($doc['created_at']); ?>"
             data-downloads="<?php echo $doc['download_count']; ?>">
            
            <div class="doc-header">
                <div class="doc-file-icon" style="background:<?php echo $fileColor; ?>;">
                    <i class="<?php echo $fileIcon; ?>"></i>
                    <div class="doc-type-badge"><?php echo strtoupper(pathinfo($doc['file_name'], PATHINFO_EXTENSION)); ?></div>
                </div>
            </div>

            <div class="doc-body">
                <div class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></div>
                <?php if ($doc['description']): ?>
                <div class="doc-description"><?php echo nl2br(htmlspecialchars($doc['description'])); ?></div>
                <?php endif; ?>

                <div class="doc-meta">
                    <div class="doc-meta-item">
                        <i class="fas fa-folder"></i>
                        <span><?php echo htmlspecialchars($doc['category']); ?></span>
                    </div>
                    <div class="doc-meta-item">
                        <i class="fas fa-hdd"></i>
                        <span><?php echo formatBytes($doc['file_size']); ?></span>
                    </div>
                    <div class="doc-meta-item">
                        <i class="fas fa-download"></i>
                        <span><?php echo number_format($doc['download_count']); ?> downloads</span>
                    </div>
                </div>

                <div class="doc-uploader">
                    <div class="uploader-avatar">
                        <?php if ($doc['uploader_photo']): ?>
                            <img src="<?php echo htmlspecialchars($doc['uploader_photo']); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(substr($doc['uploader_name'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="uploader-info">
                        <div class="uploader-name"><?php echo htmlspecialchars($doc['uploader_name'] ?? 'Unknown'); ?></div>
                        <div class="uploader-date"><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></div>
                    </div>
                </div>

                <div class="doc-actions">
                    <button class="btn-doc btn-download" onclick="downloadDocument(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['file_path']); ?>', '<?php echo htmlspecialchars($doc['file_name']); ?>')">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <?php if ($isAdmin): ?>
                    <button class="btn-doc btn-edit" onclick="editDocument(<?php echo htmlspecialchars(json_encode($doc)); ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-doc btn-toggle" onclick="toggleDocumentStatus(<?php echo $doc['id']; ?>)">
                        <i class="fas fa-<?php echo $doc['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                    </button>
                    <button class="btn-doc btn-delete" onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-cloud-upload-alt"></i> Upload Document</h3>
            <button class="modal-close" onclick="closeUploadModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="uploadForm">
                <div class="form-group">
                    <label class="form-label">Document File *</label>
                    <input type="file" id="documentFile" style="display:none;" onchange="handleFileSelect(this)" required>
                    <div class="file-upload-area" onclick="document.getElementById('documentFile').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to browse or drag and drop</p>
                        <small>Max 50MB • PDF, DOC, XLS, PPT, Images, Videos, Archives, Code files</small>
                        <div class="file-name" id="fileName"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Document Title *</label>
                    <input type="text" class="form-input" id="documentTitle" placeholder="Enter a descriptive title..." required>
                </div>

                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select class="form-select" id="documentCategory" required>
                        <option value="">Select a category...</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea class="form-textarea" id="documentDescription" placeholder="Add a brief description to help others understand what this document contains..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
            <button class="btn btn-primary" id="uploadBtn" onclick="uploadDocument()">
                <i class="fas fa-upload"></i> Upload Document
            </button>
        </div>
    </div>
</div>

<!-- Admin Auth Modal -->
<div class="modal-overlay" id="adminAuthModal">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3><i class="fas fa-shield-alt"></i> Admin Access</h3>
            <button class="modal-close" onclick="closeAdminAuth()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Admin Password</label>
                <input type="password" class="form-input" id="adminPasswordInput" placeholder="Enter admin password...">
            </div>
            <p style="font-size:0.85rem;color:var(--text3);margin-top:10px;">
                <i class="fas fa-info-circle"></i> Enter the admin password to access management features.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeAdminAuth()">Cancel</button>
            <button class="btn btn-primary" onclick="authenticateAdmin()">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </div>
    </div>
</div>

<!-- Edit Document Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Document</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editDocId">
            <div class="form-group">
                <label class="form-label">Document Title *</label>
                <input type="text" class="form-input" id="editDocTitle" required>
            </div>
            <div class="form-group">
                <label class="form-label">Category *</label>
                <select class="form-select" id="editDocCategory" required>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" id="editDocDescription"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveDocumentEdit()">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- Admin Panel (Sidebar) -->
<div class="admin-panel" id="adminPanel">
    <div class="admin-panel-header">
        <h3><i class="fas fa-shield-alt"></i> Admin Panel</h3>
        <button class="modal-close" onclick="toggleAdminPanel()">&times;</button>
    </div>
    <div class="admin-panel-body">
        <!-- Quick Actions -->
        <div class="admin-section">
            <div class="admin-section-title">Quick Actions</div>
            <button class="btn btn-primary" style="width:100%;margin-bottom:10px;" onclick="openUploadModal()">
                <i class="fas fa-upload"></i> Upload Document
            </button>
            <button class="btn btn-secondary" style="width:100%;" onclick="logoutAdmin()">
                <i class="fas fa-sign-out-alt"></i> Logout from Admin
            </button>
        </div>

        <!-- Category Management -->
        <div class="admin-section">
            <div class="admin-section-title">Manage Categories</div>
            
            <div style="margin-bottom:14px;">
                <input type="text" class="form-input" id="newCategoryName" placeholder="New category name..." style="margin-bottom:8px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                    <input type="text" class="form-input" id="newCategoryIcon" placeholder="fa-folder" style="margin:0;">
                    <input type="color" class="form-input" id="newCategoryColor" value="#f97316" style="margin:0;padding:4px;">
                </div>
                <button class="btn btn-primary" style="width:100%;" onclick="addCategory()">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>

            <div id="categoryList">
                <?php foreach ($categories as $cat): ?>
                <div class="category-list-item">
                    <div class="category-color-dot" style="background:<?php echo htmlspecialchars($cat['color']); ?>;"></div>
                    <div class="category-list-info">
                        <div class="category-list-name">
                            <i class="fas <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </div>
                        <div class="category-list-count"><?php echo $cat['doc_count']; ?> documents</div>
                    </div>
                    <button class="btn-category-delete" onclick="deleteCategory(<?php echo $cat['id']; ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="admin-section">
            <div class="admin-section-title">Statistics Overview</div>
            <div style="font-size:0.85rem;color:var(--text2);line-height:1.8;">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span>Total Documents:</span>
                    <strong><?php echo $stats['total_documents']; ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span>Total Downloads:</span>
                    <strong><?php echo number_format($stats['total_downloads']); ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span>Storage Used:</span>
                    <strong><?php echo formatBytes($stats['total_size']); ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span>Categories:</span>
                    <strong><?php echo $stats['total_categories']; ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedFile = null;
let currentFilter = 'all';
let currentSort = 'newest';

// ══════════════════════════════════════════════════════════════
// FILE UPLOAD
// ══════════════════════════════════════════════════════════════
function openUploadModal() {
    document.getElementById('uploadModal').classList.add('open');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('open');
    document.getElementById('uploadForm').reset();
    selectedFile = null;
    document.querySelector('.file-upload-area').classList.remove('file-selected');
    document.getElementById('fileName').textContent = '';
}

function handleFileSelect(input) {
    if (!input.files || !input.files[0]) return;
    selectedFile = input.files[0];
    document.getElementById('fileName').textContent = selectedFile.name;
    document.querySelector('.file-upload-area').classList.add('file-selected');
}

function uploadDocument() {
    const title = document.getElementById('documentTitle').value.trim();
    const category = document.getElementById('documentCategory').value;
    const description = document.getElementById('documentDescription').value.trim();

    if (!selectedFile) {
        alert('Please select a file to upload');
        return;
    }

    if (!title || !category) {
        alert('Please fill in all required fields');
        return;
    }

    const btn = document.getElementById('uploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

    const fd = new FormData();
    fd.append('action', 'upload_document');
    fd.append('title', title);
    fd.append('category', category);
    fd.append('description', description);
    fd.append('document', selectedFile);

    fetch('resources.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('✓ Document uploaded successfully!');
            location.reload();
        } else {
            alert('✗ Upload failed: ' + (d.error || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('✗ Upload error: ' + err.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload"></i> Upload Document';
    });
}

// Drag and drop
const uploadArea = document.querySelector('.file-upload-area');
if (uploadArea) {
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('drag-over');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('documentFile').files = files;
            handleFileSelect(document.getElementById('documentFile'));
        }
    });
}

// ══════════════════════════════════════════════════════════════
// DOWNLOAD
// ══════════════════════════════════════════════════════════════
function downloadDocument(docId, filePath, fileName) {
    // Increment download counter
    const fd = new FormData();
    fd.append('action', 'download_document');
    fd.append('doc_id', docId);
    
    fetch('resources.php', {
        method: 'POST',
        body: fd
    }).catch(() => {}); // Silent fail

    // Trigger download
    const a = document.createElement('a');
    a.href = filePath;
    a.download = fileName;
    a.click();
}

// ══════════════════════════════════════════════════════════════
// ADMIN AUTHENTICATION
// ══════════════════════════════════════════════════════════════
function openAdminAuth() {
    document.getElementById('adminAuthModal').classList.add('open');
}

function closeAdminAuth() {
    document.getElementById('adminAuthModal').classList.remove('open');
    document.getElementById('adminPasswordInput').value = '';
}

function authenticateAdmin() {
    const password = document.getElementById('adminPasswordInput').value;
    
    const fd = new FormData();
    fd.append('admin_auth', '1');
    fd.append('admin_password', password);

    fetch('resources.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            location.reload();
        } else {
            alert('✗ Invalid password');
        }
    })
    .catch(() => {
        alert('✗ Authentication error');
    });
}

function logoutAdmin() {
    if (!confirm('Logout from admin mode?')) return;
    
    const fd = new FormData();
    fd.append('admin_logout', '1');
    
    fetch('resources.php', {
        method: 'POST',
        body: fd
    })
    .then(() => location.reload());
}

function toggleAdminPanel() {
    document.getElementById('adminPanel').classList.toggle('open');
}

// ══════════════════════════════════════════════════════════════
// ADMIN FUNCTIONS
// ══════════════════════════════════════════════════════════════
function editDocument(doc) {
    document.getElementById('editDocId').value = doc.id;
    document.getElementById('editDocTitle').value = doc.title;
    document.getElementById('editDocCategory').value = doc.category;
    document.getElementById('editDocDescription').value = doc.description || '';
    document.getElementById('editModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

function saveDocumentEdit() {
    const docId = document.getElementById('editDocId').value;
    const title = document.getElementById('editDocTitle').value.trim();
    const category = document.getElementById('editDocCategory').value;
    const description = document.getElementById('editDocDescription').value.trim();

    if (!title || !category) {
        alert('Title and category are required');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'admin_update_document');
    fd.append('doc_id', docId);
    fd.append('title', title);
    fd.append('category', category);
    fd.append('description', description);

    fetch('resources.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('✓ Document updated');
            location.reload();
        } else {
            alert('✗ Update failed');
        }
    });
}

function deleteDocument(docId) {
    if (!confirm('Delete this document permanently? This cannot be undone.')) return;

    const fd = new FormData();
    fd.append('action', 'admin_delete_document');
    fd.append('doc_id', docId);

    fetch('resources.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('✓ Document deleted');
            location.reload();
        } else {
            alert('✗ Delete failed');
        }
    });
}

function toggleDocumentStatus(docId) {
    const fd = new FormData();
    fd.append('action', 'admin_toggle_status');
    fd.append('doc_id', docId);

    fetch('resources.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            location.reload();
        }
    });
}

function addCategory() {
    const name = document.getElementById('newCategoryName').value.trim();
    const icon = document.getElementById('newCategoryIcon').value.trim() || 'fa-folder';
    const color = document.getElementById('newCategoryColor').value;

    if (!name) {
        alert('Category name required');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'admin_add_category');
    fd.append('name', name);
    fd.append('icon', icon);
    fd.append('color', color);

    fetch('resources.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('✓ Category added');
            location.reload();
        } else {
            alert('✗ Failed to add category');
        }
    });
}

function deleteCategory(catId) {
    if (!confirm('Delete this category? Documents will be moved to "Other".')) return;

    const fd = new FormData();
    fd.append('action', 'admin_delete_category');
    fd.append('cat_id', catId);

    fetch('resources.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('✓ Category deleted');
            location.reload();
        }
    });
}

// ══════════════════════════════════════════════════════════════
// FILTERING & SORTING
// ══════════════════════════════════════════════════════════════
function filterByCategory(category) {
    currentFilter = category;
    
    // Update pill styles
    document.querySelectorAll('.category-pill').forEach(pill => {
        pill.classList.remove('active');
        if (pill.dataset.category === category) {
            pill.classList.add('active');
            const color = pill.style.getPropertyValue('--cat-color');
            if (color) {
                pill.style.background = `linear-gradient(135deg, ${color}, ${color}dd)`;
            }
        } else {
            pill.style.background = '';
        }
    });

    applyFilters();
}

function applyFilters() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.doc-card');

    cards.forEach(card => {
        const category = card.dataset.category;
        const title = card.dataset.title;
        const description = card.dataset.description;
        const uploader = card.dataset.uploader;

        const matchesCategory = currentFilter === 'all' || category === currentFilter;
        const matchesSearch = !searchTerm || 
            title.includes(searchTerm) || 
            description.includes(searchTerm) || 
            uploader.includes(searchTerm);

        card.style.display = (matchesCategory && matchesSearch) ? '' : 'none';
    });

    // Show empty state if no results
    const visibleCards = Array.from(cards).filter(c => c.style.display !== 'none');
    let emptyState = document.querySelector('.empty-state');
    
    if (visibleCards.length === 0) {
        if (!emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'empty-state';
            emptyState.style.gridColumn = '1/-1';
            emptyState.innerHTML = `
                <i class="fas fa-search"></i>
                <h3>No Documents Found</h3>
                <p>Try adjusting your search or filters</p>
            `;
            document.getElementById('documentsGrid').appendChild(emptyState);
        }
    } else if (emptyState) {
        emptyState.remove();
    }
}

// Search input listener
document.getElementById('searchInput').addEventListener('input', applyFilters);

// Sort functionality
document.getElementById('sortSelect').addEventListener('change', function() {
    currentSort = this.value;
    const grid = document.getElementById('documentsGrid');
    const cards = Array.from(document.querySelectorAll('.doc-card'));

    cards.sort((a, b) => {
        switch (currentSort) {
            case 'newest':
                return parseInt(b.dataset.date) - parseInt(a.dataset.date);
            case 'oldest':
                return parseInt(a.dataset.date) - parseInt(b.dataset.date);
            case 'downloads':
                return parseInt(b.dataset.downloads) - parseInt(a.dataset.downloads);
            case 'title':
                return a.dataset.title.localeCompare(b.dataset.title);
            default:
                return 0;
        }
    });

    cards.forEach(card => grid.appendChild(card));
});

// Close modals on outside click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });
});

// Close admin panel on outside click
document.addEventListener('click', function(e) {
    const panel = document.getElementById('adminPanel');
    const trigger = document.querySelector('.admin-trigger');
    
    if (panel.classList.contains('open') && 
        !panel.contains(e.target) && 
        !trigger.contains(e.target)) {
        panel.classList.remove('open');
    }
});
</script>
</body>
</html>

<?php
// ══════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ══════════════════════════════════════════════════════════════
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        // Documents
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'txt' => 'fas fa-file-alt',
        'csv' => 'fas fa-file-csv',
        
        // Archives
        'zip' => 'fas fa-file-archive',
        'rar' => 'fas fa-file-archive',
        '7z' => 'fas fa-file-archive',
        
        // Images
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image',
        'svg' => 'fas fa-file-image',
        
        // Videos
        'mp4' => 'fas fa-file-video',
        'avi' => 'fas fa-file-video',
        'mkv' => 'fas fa-file-video',
        'mov' => 'fas fa-file-video',
        
        // Audio
        'mp3' => 'fas fa-file-audio',
        'wav' => 'fas fa-file-audio',
        
        // Code
        'html' => 'fas fa-file-code',
        'css' => 'fas fa-file-code',
        'js' => 'fas fa-file-code',
        'json' => 'fas fa-file-code',
        'xml' => 'fas fa-file-code',
        'py' => 'fas fa-file-code',
        'java' => 'fas fa-file-code',
        'cpp' => 'fas fa-file-code',
        'c' => 'fas fa-file-code',
        'php' => 'fas fa-file-code',
        'sql' => 'fas fa-file-code',
    ];
    
    return $icons[$ext] ?? 'fas fa-file';
}

function getFileColor($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $colors = [
        'pdf' => 'linear-gradient(135deg, #dc2626, #b91c1c)',
        'doc' => 'linear-gradient(135deg, #2563eb, #1d4ed8)',
        'docx' => 'linear-gradient(135deg, #2563eb, #1d4ed8)',
        'xls' => 'linear-gradient(135deg, #16a34a, #15803d)',
        'xlsx' => 'linear-gradient(135deg, #16a34a, #15803d)',
        'ppt' => 'linear-gradient(135deg, #ea580c, #c2410c)',
        'pptx' => 'linear-gradient(135deg, #ea580c, #c2410c)',
        'zip' => 'linear-gradient(135deg, #9333ea, #7e22ce)',
        'rar' => 'linear-gradient(135deg, #9333ea, #7e22ce)',
        'jpg' => 'linear-gradient(135deg, #0891b2, #0e7490)',
        'jpeg' => 'linear-gradient(135deg, #0891b2, #0e7490)',
        'png' => 'linear-gradient(135deg, #0891b2, #0e7490)',
        'mp4' => 'linear-gradient(135deg, #db2777, #be185d)',
        'mp3' => 'linear-gradient(135deg, #7c3aed, #6d28d9)',
        'html' => 'linear-gradient(135deg, #f97316, #ea580c)',
        'css' => 'linear-gradient(135deg, #3b82f6, #2563eb)',
        'js' => 'linear-gradient(135deg, #eab308, #ca8a04)',
        'py' => 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
        'php' => 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
    ];
    
    return $colors[$ext] ?? 'linear-gradient(135deg, #64748b, #475569)';
}
?>
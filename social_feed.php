<?php
// OUTPUT BUFFERING FIRST - prevents any PHP warnings/notices from corrupting JSON
ob_start();

session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { 
    ob_end_clean();
    header('Location: login.php'); 
    exit; 
}
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'social';

// Mark social feed as viewed
$sid = (int)$_SESSION['student_id'];
$db = getPadakDB();
$db->query("INSERT INTO student_feed_views (student_id, last_viewed_at) 
           VALUES ($sid, NOW()) 
           ON DUPLICATE KEY UPDATE last_viewed_at=NOW()");

// =============================================
// UPLOADS DIRECTORY SETUP
// =============================================
$uploadsDir = __DIR__ . '/uploads/social_posts';
$uploadsUrl = 'uploads/social_posts';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Update online status
$db->query("UPDATE internship_students SET is_online=1, last_seen=NOW() WHERE id=$sid");

// =============================================
// AJAX HANDLERS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // --------------------------------------------------
    // CREATE POST
    // --------------------------------------------------
    if ($_POST['action'] === 'create_post') {
        $content = trim($_POST['content'] ?? '');
        $hasFile = isset($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE;

        if (!$content && !$hasFile) {
            echo json_encode(['success' => false, 'error' => 'Post content or media required']);
            exit;
        }

        $mediaPath = null;
        $mediaType = null;

        // Handle file upload
        if ($hasFile) {
            $fileError = $_FILES['media']['error'];
            if ($fileError !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit',
                    UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit',
                    UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'No temp directory on server',
                    UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
                ];
                echo json_encode(['success' => false, 'error' => $errorMessages[$fileError] ?? "Upload error"]);
                exit;
            }

            if (!is_writable($uploadsDir)) {
                echo json_encode(['success' => false, 'error' => 'Uploads folder not writable']);
                exit;
            }

            $file = $_FILES['media'];
            $fileName = basename($file['name']);
            $fileTmp = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // File size limit: 10MB
            if ($fileSize > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File too large (max 10MB)']);
                exit;
            }

            $allowedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedVideos = ['mp4', 'webm', 'mov'];

            if (in_array($fileExt, $allowedImages)) {
                $mediaType = 'image';
                $newFileName = uniqid() . '_' . time() . '.jpg';
                $optimizedPath = $uploadsDir . '/' . $newFileName;

                // Optimize image with GD if available
                if (extension_loaded('gd')) {
                    $image = null;
                    switch ($fileExt) {
                        case 'jpg':
                        case 'jpeg': $image = @imagecreatefromjpeg($fileTmp); break;
                        case 'png':  $image = @imagecreatefrompng($fileTmp);  break;
                        case 'gif':  $image = @imagecreatefromgif($fileTmp);  break;
                        case 'webp': $image = @imagecreatefromwebp($fileTmp); break;
                    }

                    if ($image) {
                        $width = imagesx($image);
                        $height = imagesy($image);
                        $maxWidth = 1200;

                        if ($width > $maxWidth) {
                            $ratio = $maxWidth / $width;
                            $newW = $maxWidth;
                            $newH = (int)($height * $ratio);
                            $resized = imagecreatetruecolor($newW, $newH);
                            imagealphablending($resized, false);
                            imagesavealpha($resized, true);
                            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
                            imagejpeg($resized, $optimizedPath, 85);
                            imagedestroy($resized);
                        } else {
                            imagejpeg($image, $optimizedPath, 85);
                        }
                        imagedestroy($image);
                        $mediaPath = $uploadsUrl . '/' . $newFileName;
                    } else {
                        move_uploaded_file($fileTmp, $optimizedPath);
                        $mediaPath = $uploadsUrl . '/' . $newFileName;
                    }
                } else {
                    $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
                    $destPath = $uploadsDir . '/' . $newFileName;
                    move_uploaded_file($fileTmp, $destPath);
                    $mediaPath = $uploadsUrl . '/' . $newFileName;
                }

            } elseif (in_array($fileExt, $allowedVideos)) {
                $mediaType = 'video';
                $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
                $destPath = $uploadsDir . '/' . $newFileName;
                if (move_uploaded_file($fileTmp, $destPath)) {
                    $mediaPath = $uploadsUrl . '/' . $newFileName;
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to save video']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'error' => "File type '$fileExt' not allowed"]);
                exit;
            }
        }

        // Insert post
        $stmt = $db->prepare("INSERT INTO social_feed (student_id, content, media_path, media_type) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $sid, $content, $mediaPath, $mediaType);

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $stmt->error]);
            exit;
        }

        $postId = $db->insert_id;
        echo json_encode([
            'success' => true,
            'post_id' => $postId,
            'time' => date('M d, Y \a\t h:i A')
        ]);
        exit;
    }

    // --------------------------------------------------
    // FETCH POSTS (with pagination)
    // --------------------------------------------------
    if ($_POST['action'] === 'fetch_posts') {
        $offset = (int)($_POST['offset'] ?? 0);
        $limit = 10;

        $stmt = $db->prepare(
            "SELECT sf.*, s.full_name, s.profile_photo, s.domain_interest,
                (SELECT COUNT(*) FROM social_feed WHERE parent_id=sf.id AND item_type='like') as likes_count,
                (SELECT COUNT(*) FROM social_feed WHERE parent_id=sf.id AND item_type='comment') as comments_count,
                (SELECT COUNT(*) > 0 FROM social_feed WHERE parent_id=sf.id AND item_type='like' AND student_id=?) as has_liked
             FROM social_feed sf
             JOIN internship_students s ON s.id = sf.student_id
             WHERE sf.item_type='post' AND sf.is_deleted=0
             ORDER BY sf.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param("iii", $sid, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();

        $posts = [];
        while ($r = $res->fetch_assoc()) {
            $posts[] = $r;
        }

        echo json_encode(['success' => true, 'posts' => $posts]);
        exit;
    }

    // --------------------------------------------------
    // TOGGLE LIKE
    // --------------------------------------------------
    if ($_POST['action'] === 'toggle_like') {
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if (!$postId) {
            echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
            exit;
        }

        // Check if already liked
        $checkStmt = $db->prepare("SELECT id FROM social_feed WHERE parent_id=? AND student_id=? AND item_type='like'");
        $checkStmt->bind_param("ii", $postId, $sid);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;

        if ($exists) {
            // Unlike
            $delStmt = $db->prepare("UPDATE social_feed SET is_deleted=1 WHERE parent_id=? AND student_id=? AND item_type='like'");
            $delStmt->bind_param("ii", $postId, $sid);
            $delStmt->execute();
            $action = 'unliked';
        } else {
            // Like
            $insStmt = $db->prepare("INSERT INTO social_feed (parent_id, student_id, item_type) VALUES (?,?,'like')");
            $insStmt->bind_param("ii", $postId, $sid);
            $insStmt->execute();
            $action = 'liked';
        }

        // Get updated count
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM social_feed WHERE parent_id=? AND item_type='like' AND is_deleted=0");
        $countStmt->bind_param("i", $postId);
        $countStmt->execute();
        $count = $countStmt->get_result()->fetch_assoc()['count'];

        echo json_encode(['success' => true, 'action' => $action, 'likes_count' => $count]);
        exit;
    }

    // --------------------------------------------------
    // ADD COMMENT
    // --------------------------------------------------
    if ($_POST['action'] === 'add_comment') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if (!$postId || !$comment) {
            echo json_encode(['success' => false, 'error' => 'Post ID and comment required']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO social_feed (parent_id, student_id, item_type, content) VALUES (?,?,'comment',?)");
        $stmt->bind_param("iis", $postId, $sid, $comment);

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'DB error']);
            exit;
        }

        $commentId = $db->insert_id;

        // Get comment details
        $detailStmt = $db->prepare(
            "SELECT sf.*, s.full_name, s.profile_photo 
             FROM social_feed sf 
             JOIN internship_students s ON s.id=sf.student_id 
             WHERE sf.id=?"
        );
        $detailStmt->bind_param("i", $commentId);
        $detailStmt->execute();
        $commentData = $detailStmt->get_result()->fetch_assoc();

        // Get updated count
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM social_feed WHERE parent_id=? AND item_type='comment' AND is_deleted=0");
        $countStmt->bind_param("i", $postId);
        $countStmt->execute();
        $count = $countStmt->get_result()->fetch_assoc()['count'];

        echo json_encode([
            'success' => true,
            'comment' => $commentData,
            'comments_count' => $count
        ]);
        exit;
    }

    // --------------------------------------------------
    // FETCH COMMENTS
    // --------------------------------------------------
    if ($_POST['action'] === 'fetch_comments') {
        $postId = (int)($_POST['post_id'] ?? 0);

        if (!$postId) {
            echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
            exit;
        }

        $stmt = $db->prepare(
            "SELECT sf.*, s.full_name, s.profile_photo 
             FROM social_feed sf 
             JOIN internship_students s ON s.id=sf.student_id 
             WHERE sf.parent_id=? AND sf.item_type='comment' AND sf.is_deleted=0 
             ORDER BY sf.created_at ASC"
        );
        $stmt->bind_param("i", $postId);
        $stmt->execute();
        $res = $stmt->get_result();

        $comments = [];
        while ($r = $res->fetch_assoc()) {
            $comments[] = $r;
        }

        echo json_encode(['success' => true, 'comments' => $comments]);
        exit;
    }

    // --------------------------------------------------
    // EDIT POST
    // --------------------------------------------------
    if ($_POST['action'] === 'edit_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $hasFile = isset($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE;

        if (!$postId) {
            echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
            exit;
        }

        // Verify ownership
        $checkStmt = $db->prepare("SELECT media_path FROM social_feed WHERE id=? AND student_id=? AND item_type='post'");
        $checkStmt->bind_param("ii", $postId, $sid);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Not authorized']);
            exit;
        }

        $existingPost = $result->fetch_assoc();
        $oldMediaPath = $existingPost['media_path'];
        $mediaPath = $oldMediaPath;
        $mediaType = null;

        // Handle new file upload
        if ($hasFile) {
            $fileError = $_FILES['media']['error'];
            if ($fileError !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'Upload error']);
                exit;
            }

            if (!is_writable($uploadsDir)) {
                echo json_encode(['success' => false, 'error' => 'Uploads folder not writable']);
                exit;
            }

            $file = $_FILES['media'];
            $fileName = basename($file['name']);
            $fileTmp = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileSize > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File too large (max 10MB)']);
                exit;
            }

            $allowedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedVideos = ['mp4', 'webm', 'mov'];

            if (in_array($fileExt, $allowedImages)) {
                $mediaType = 'image';
                $newFileName = uniqid() . '_' . time() . '.jpg';
                $optimizedPath = $uploadsDir . '/' . $newFileName;

                if (extension_loaded('gd')) {
                    $image = null;
                    switch ($fileExt) {
                        case 'jpg':
                        case 'jpeg': $image = @imagecreatefromjpeg($fileTmp); break;
                        case 'png':  $image = @imagecreatefrompng($fileTmp);  break;
                        case 'gif':  $image = @imagecreatefromgif($fileTmp);  break;
                        case 'webp': $image = @imagecreatefromwebp($fileTmp); break;
                    }

                    if ($image) {
                        $width = imagesx($image);
                        $height = imagesy($image);
                        $maxWidth = 1200;

                        if ($width > $maxWidth) {
                            $ratio = $maxWidth / $width;
                            $newW = $maxWidth;
                            $newH = (int)($height * $ratio);
                            $resized = imagecreatetruecolor($newW, $newH);
                            imagealphablending($resized, false);
                            imagesavealpha($resized, true);
                            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
                            imagejpeg($resized, $optimizedPath, 85);
                            imagedestroy($resized);
                        } else {
                            imagejpeg($image, $optimizedPath, 85);
                        }
                        imagedestroy($image);
                        $mediaPath = $uploadsUrl . '/' . $newFileName;
                    } else {
                        move_uploaded_file($fileTmp, $optimizedPath);
                        $mediaPath = $uploadsUrl . '/' . $newFileName;
                    }
                } else {
                    $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
                    $destPath = $uploadsDir . '/' . $newFileName;
                    move_uploaded_file($fileTmp, $destPath);
                    $mediaPath = $uploadsUrl . '/' . $newFileName;
                }

                // Delete old media file
                if ($oldMediaPath && file_exists($oldMediaPath)) {
                    @unlink($oldMediaPath);
                }

            } elseif (in_array($fileExt, $allowedVideos)) {
                $mediaType = 'video';
                $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
                $destPath = $uploadsDir . '/' . $newFileName;
                if (move_uploaded_file($fileTmp, $destPath)) {
                    $mediaPath = $uploadsUrl . '/' . $newFileName;
                    // Delete old media file
                    if ($oldMediaPath && file_exists($oldMediaPath)) {
                        @unlink($oldMediaPath);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to save video']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'error' => "File type '$fileExt' not allowed"]);
                exit;
            }
        }

        // Update post
        if ($hasFile) {
            $stmt = $db->prepare("UPDATE social_feed SET content=?, media_path=?, media_type=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("sssi", $content, $mediaPath, $mediaType, $postId);
        } else {
            $stmt = $db->prepare("UPDATE social_feed SET content=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("si", $content, $postId);
        }

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Failed to update post']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'media_path' => $mediaPath,
            'media_type' => $mediaType ?: ($oldMediaPath ? 'image' : null)
        ]);
        exit;
    }

    // --------------------------------------------------
    // DELETE POST
    // --------------------------------------------------
    if ($_POST['action'] === 'delete_post') {
        $postId = (int)($_POST['post_id'] ?? 0);

        if (!$postId) {
            echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
            exit;
        }

        // Verify ownership
        $checkStmt = $db->prepare("SELECT id FROM social_feed WHERE id=? AND student_id=? AND item_type='post'");
        $checkStmt->bind_param("ii", $postId, $sid);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Not authorized']);
            exit;
        }

        // Soft delete
        $delStmt = $db->prepare("UPDATE social_feed SET is_deleted=1 WHERE id=?");
        $delStmt->bind_param("i", $postId);
        $delStmt->execute();

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// =============================================
// PAGE DATA LOADING
// =============================================
// Fetch initial posts
$posts = [];
$res = $db->query(
    "SELECT sf.*, s.full_name, s.profile_photo, s.domain_interest,
        (SELECT COUNT(*) FROM social_feed WHERE parent_id=sf.id AND item_type='like' AND is_deleted=0) as likes_count,
        (SELECT COUNT(*) FROM social_feed WHERE parent_id=sf.id AND item_type='comment' AND is_deleted=0) as comments_count,
        (SELECT COUNT(*) > 0 FROM social_feed WHERE parent_id=sf.id AND item_type='like' AND student_id=$sid AND is_deleted=0) as has_liked
     FROM social_feed sf
     JOIN internship_students s ON s.id = sf.student_id
     WHERE sf.item_type='post' AND sf.is_deleted=0
     ORDER BY sf.created_at DESC
     LIMIT 10"
);

if ($res) {
    while ($r = $res->fetch_assoc()) {
        $posts[] = $r;
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=5.0">
<title>Social Feed</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════════════════
   RESET & BASE STYLES
   ═══════════════════════════════════════════════════════════ */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}

:root{
    /* Sidebar width */
    --sbw:258px;
    
    /* Colors */
    --o5:#f97316;
    --o4:#fb923c;
    --bg:#f8fafc;
    --card:#fff;
    --text:#0f172a;
    --text2:#475569;
    --text3:#94a3b8;
    --border:#e2e8f0;
    --green:#22c55e;
    --blue:#3b82f6;
    
    /* Responsive spacing */
    --page-padding:24px;
    --card-padding:22px;
    --card-radius:14px;
    --card-gap:24px;
}

body{
    font-family:'Inter',sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
    overflow-x:hidden;
}

/* ═══════════════════════════════════════════════════════════
   LAYOUT - DESKTOP FIRST
   ═══════════════════════════════════════════════════════════ */
.page-wrap{
    margin-left:var(--sbw);
    min-height:100vh;
    display:flex;
    flex-direction:column;
    transition:margin-left 0.3s ease;
}

.topbar{
    background:rgba(248,250,252,0.95);
    backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
    padding:12px 20px;
    display:flex;
    align-items:center;
    gap:12px;
    position:sticky;
    top:0;
    z-index:100;
    transition:padding 0.3s ease;
}

.topbar-hamburger{
    display:none;
    background:none;
    border:none;
    cursor:pointer;
    color:var(--text2);
    padding:8px;
    border-radius:8px;
    font-size:1.3rem;
    transition:background 0.2s;
}

.topbar-hamburger:hover{
    background:var(--bg);
}

.topbar-hamburger:active{
    transform:scale(0.95);
}

.topbar-title{
    font-size:1.05rem;
    font-weight:700;
    color:var(--text);
    flex:1;
    display:flex;
    align-items:center;
    gap:8px;
}

.topbar-title i{
    color:var(--o5);
}

/* ═══════════════════════════════════════════════════════════
   FEED CONTAINER
   ═══════════════════════════════════════════════════════════ */
.feed-container{
    max-width:900px;
    margin:0 auto;
    padding:var(--page-padding);
    flex:1;
    width:100%;
}

/* ═══════════════════════════════════════════════════════════
   CREATE POST CARD
   ═══════════════════════════════════════════════════════════ */
.create-post-card{
    background:var(--card);
    border-radius:var(--card-radius);
    padding:var(--card-padding);
    margin-bottom:var(--card-gap);
    box-shadow:0 1px 3px rgba(0,0,0,0.08);
    border:1px solid var(--border);
    transition:box-shadow 0.3s ease;
}

.create-post-card:hover{
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

.create-post-header{
    display:flex;
    align-items:flex-start;
    gap:14px;
    margin-bottom:14px;
}

.user-avatar{
    width:48px;
    height:48px;
    border-radius:50%;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
    font-size:1.05rem;
    flex-shrink:0;
}

.user-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:50%;
}

.create-post-input{
    flex:1;
    padding:14px 18px;
    border:1.5px solid var(--border);
    border-radius:16px;
    font-size:0.92rem;
    font-family:inherit;
    outline:none;
    resize:none;
    transition:all 0.2s;
    background:var(--bg);
    min-height:80px;
    max-height:200px;
    line-height:1.5;
}

.create-post-input:focus{
    border-color:var(--o5);
    background:var(--card);
    box-shadow:0 0 0 3px rgba(249,115,22,0.1);
}

.create-post-actions{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-top:12px;
    padding-top:12px;
    border-top:1px solid var(--border);
    flex-wrap:wrap;
    gap:10px;
}

.media-preview{
    display:none;
    margin:12px 0;
    position:relative;
    border-radius:12px;
    overflow:hidden;
}

.media-preview.active{
    display:block;
}

.media-preview img, .media-preview video{
    width:100%;
    max-height:300px;
    object-fit:cover;
    border-radius:12px;
    display:block;
}

.media-preview-remove{
    position:absolute;
    top:10px;
    right:10px;
    background:rgba(0,0,0,0.7);
    border:none;
    color:#fff;
    width:36px;
    height:36px;
    border-radius:50%;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1rem;
    transition:background 0.2s;
    z-index:1;
}

.media-preview-remove:hover{
    background:rgba(0,0,0,0.85);
}

.post-actions-left{
    display:flex;
    gap:6px;
    align-items:center;
    flex-wrap:wrap;
}

.post-action-btn{
    background:none;
    border:none;
    cursor:pointer;
    padding:8px 14px;
    border-radius:8px;
    font-size:0.88rem;
    color:var(--text2);
    display:flex;
    align-items:center;
    gap:6px;
    transition:all 0.2s;
    font-weight:500;
    white-space:nowrap;
}

.post-action-btn:hover{
    background:var(--bg);
    color:var(--text);
}

.post-action-btn:active{
    transform:scale(0.97);
}

.post-action-btn i{
    font-size:1rem;
}

/* ═══════════════════════════════════════════════════════════
   EMOJI PICKER
   ═══════════════════════════════════════════════════════════ */
.emoji-picker-container{
    position:relative;
}

.emoji-picker-popup{
    display:none;
    position:absolute;
    bottom:100%;
    left:0;
    margin-bottom:8px;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:12px;
    padding:12px;
    box-shadow:0 8px 24px rgba(0,0,0,0.15);
    z-index:1000;
    width:320px;
    max-height:280px;
    overflow-y:auto;
}

.emoji-picker-popup.active{
    display:block;
}

.emoji-category{
    margin-bottom:14px;
}

.emoji-category:last-child{
    margin-bottom:0;
}

.emoji-category-title{
    font-size:0.72rem;
    font-weight:700;
    color:var(--text3);
    margin-bottom:8px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}

.emoji-grid{
    display:grid;
    grid-template-columns:repeat(8,1fr);
    gap:4px;
}

.emoji-item{
    font-size:1.5rem;
    cursor:pointer;
    padding:6px;
    border-radius:6px;
    transition:all 0.15s;
    text-align:center;
    user-select:none;
}

.emoji-item:hover{
    background:var(--bg);
    transform:scale(1.2);
}

.emoji-item:active{
    transform:scale(1.1);
}

/* ═══════════════════════════════════════════════════════════
   POST BUTTON
   ═══════════════════════════════════════════════════════════ */
.btn-post{
    background:linear-gradient(135deg,var(--o5),var(--o4));
    border:none;
    color:#fff;
    padding:9px 24px;
    border-radius:24px;
    cursor:pointer;
    font-size:0.88rem;
    font-weight:600;
    display:flex;
    align-items:center;
    gap:6px;
    transition:all 0.2s;
    box-shadow:0 2px 8px rgba(249,115,22,0.3);
    white-space:nowrap;
}

.btn-post:hover{
    opacity:0.9;
    transform:translateY(-1px);
    box-shadow:0 4px 12px rgba(249,115,22,0.4);
}

.btn-post:active{
    transform:translateY(0);
}

.btn-post:disabled{
    opacity:0.5;
    cursor:not-allowed;
    transform:none;
}

/* ═══════════════════════════════════════════════════════════
   POST CARD
   ═══════════════════════════════════════════════════════════ */
.post-card{
    background:var(--card);
    border-radius:var(--card-radius);
    padding:var(--card-padding);
    margin-bottom:20px;
    box-shadow:0 1px 3px rgba(0,0,0,0.08);
    border:1px solid var(--border);
    transition:box-shadow 0.3s ease;
}

.post-card:hover{
    box-shadow:0 4px 12px rgba(0,0,0,0.1);
}

.post-header{
    display:flex;
    align-items:center;
    gap:14px;
    margin-bottom:16px;
}

.post-avatar{
    width:48px;
    height:48px;
    border-radius:50%;
    background:linear-gradient(135deg,var(--blue),#60a5fa);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
    font-size:1rem;
    flex-shrink:0;
}

.post-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:50%;
}

.post-author-info{
    flex:1;
    min-width:0;
}

.post-author-name{
    font-size:0.92rem;
    font-weight:700;
    color:var(--text);
    margin-bottom:2px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.post-meta{
    font-size:0.75rem;
    color:var(--text3);
    display:flex;
    align-items:center;
    gap:4px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.post-options{
    background:none;
    border:none;
    cursor:pointer;
    color:var(--text3);
    padding:6px;
    border-radius:8px;
    font-size:1.1rem;
    transition:all 0.2s;
    flex-shrink:0;
}

.post-options:hover{
    background:var(--bg);
    color:var(--text);
}

.post-content{
    font-size:0.95rem;
    line-height:1.65;
    color:var(--text);
    margin-bottom:14px;
    white-space:pre-wrap;
    word-break:break-word;
}

.post-media{
    margin-bottom:16px;
    border-radius:12px;
    overflow:hidden;
}

.post-media img{
    width:100%;
    max-height:600px;
    object-fit:cover;
    cursor:pointer;
    display:block;
    transition:transform 0.3s ease;
}

.post-media img:hover{
    transform:scale(1.02);
}

.post-media video{
    width:100%;
    max-height:600px;
    border-radius:10px;
    display:block;
}

.post-stats{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:10px 0;
    border-bottom:1px solid var(--border);
    margin-bottom:10px;
    font-size:0.82rem;
    color:var(--text3);
    gap:10px;
    flex-wrap:wrap;
}

.stat-item{
    display:flex;
    align-items:center;
    gap:4px;
    cursor:pointer;
    transition:color 0.2s;
}

.stat-item:hover{
    color:var(--text2);
}

.post-interactions{
    display:flex;
    align-items:center;
    gap:4px;
    padding-bottom:10px;
    border-bottom:1px solid var(--border);
}

.interaction-btn{
    flex:1;
    background:none;
    border:none;
    cursor:pointer;
    padding:10px;
    border-radius:8px;
    font-size:0.88rem;
    color:var(--text2);
    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    transition:all 0.2s;
    font-weight:600;
}

.interaction-btn:hover{
    background:var(--bg);
}

.interaction-btn:active{
    transform:scale(0.97);
}

.interaction-btn.liked{
    color:var(--o5);
}

.interaction-btn.liked i{
    animation:likeAnim 0.4s ease;
}

@keyframes likeAnim{
    0%{transform:scale(1);}
    50%{transform:scale(1.3);}
    100%{transform:scale(1);}
}

/* ═══════════════════════════════════════════════════════════
   COMMENTS SECTION
   ═══════════════════════════════════════════════════════════ */
.comments-section{
    margin-top:12px;
    display:none;
}

.comments-section.active{
    display:block;
}

.comment-input-wrap{
    display:flex;
    gap:10px;
    margin-bottom:14px;
}

.comment-avatar{
    width:34px;
    height:34px;
    border-radius:50%;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
    font-size:0.85rem;
    flex-shrink:0;
}

.comment-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:50%;
}

.comment-input-form{
    flex:1;
    display:flex;
    gap:8px;
    align-items:flex-start;
}

.comment-input{
    flex:1;
    padding:10px 14px;
    border:1.5px solid var(--border);
    border-radius:20px;
    font-size:0.85rem;
    font-family:inherit;
    outline:none;
    resize:none;
    transition:all 0.2s;
    min-height:40px;
    max-height:120px;
}

.comment-input:focus{
    border-color:var(--o5);
    box-shadow:0 0 0 3px rgba(249,115,22,0.1);
}

.btn-comment{
    background:var(--o5);
    border:none;
    color:#fff;
    padding:0 18px;
    height:40px;
    border-radius:20px;
    cursor:pointer;
    font-size:0.85rem;
    font-weight:600;
    transition:all 0.2s;
    white-space:nowrap;
    flex-shrink:0;
}

.btn-comment:hover{
    opacity:0.9;
    transform:translateY(-1px);
}

.btn-comment:active{
    transform:translateY(0);
}

.btn-comment:disabled{
    opacity:0.5;
    cursor:not-allowed;
}

.comments-list{
    display:flex;
    flex-direction:column;
    gap:12px;
}

.comment-item{
    display:flex;
    gap:10px;
}

.comment-content{
    flex:1;
    background:var(--bg);
    padding:10px 14px;
    border-radius:12px;
    min-width:0;
}

.comment-author{
    font-size:0.82rem;
    font-weight:700;
    color:var(--text);
    margin-bottom:4px;
}

.comment-text{
    font-size:0.85rem;
    line-height:1.5;
    color:var(--text2);
    word-break:break-word;
}

.comment-time{
    font-size:0.72rem;
    color:var(--text3);
    margin-top:4px;
}

/* ═══════════════════════════════════════════════════════════
   OPTIONS MENU
   ═══════════════════════════════════════════════════════════ */
.options-menu{
    display:none;
    position:absolute;
    top:40px;
    right:0;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:8px;
    box-shadow:0 4px 12px rgba(0,0,0,0.15);
    min-width:160px;
    z-index:100;
    overflow:hidden;
}

.options-menu.active{
    display:block;
}

.option-item{
    padding:10px 16px;
    cursor:pointer;
    font-size:0.88rem;
    color:var(--text);
    transition:background 0.15s;
    display:flex;
    align-items:center;
    gap:10px;
}

.option-item:hover{
    background:var(--bg);
}

.option-item:active{
    transform:scale(0.98);
}

.option-item.danger{
    color:#ef4444;
}

.option-item.danger:hover{
    background:#fef2f2;
}

.option-item i{
    width:16px;
    text-align:center;
}

/* ═══════════════════════════════════════════════════════════
   MODALS
   ═══════════════════════════════════════════════════════════ */
.edit-modal, .image-modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.6);
    z-index:9998;
    align-items:center;
    justify-content:center;
    padding:20px;
    backdrop-filter:blur(4px);
}

.edit-modal.open, .image-modal.open{
    display:flex;
}

.edit-modal-content{
    background:var(--card);
    border-radius:16px;
    padding:24px;
    width:100%;
    max-width:600px;
    max-height:90vh;
    overflow-y:auto;
    box-shadow:0 20px 60px rgba(0,0,0,0.3);
}

.edit-modal-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:20px;
    padding-bottom:16px;
    border-bottom:1px solid var(--border);
}

.edit-modal-title{
    font-size:1.15rem;
    font-weight:700;
    color:var(--text);
    display:flex;
    align-items:center;
    gap:10px;
}

.edit-modal-title i{
    color:var(--o5);
}

.edit-modal-close{
    background:none;
    border:none;
    cursor:pointer;
    color:var(--text3);
    font-size:1.3rem;
    padding:4px;
    width:32px;
    height:32px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:all 0.2s;
}

.edit-modal-close:hover{
    background:var(--bg);
    color:var(--text);
}

.edit-modal-body{
    margin-bottom:20px;
}

.edit-textarea{
    width:100%;
    padding:14px 16px;
    border:1.5px solid var(--border);
    border-radius:12px;
    font-size:0.92rem;
    font-family:inherit;
    outline:none;
    resize:vertical;
    min-height:120px;
    max-height:300px;
    transition:border-color 0.2s;
    margin-bottom:16px;
    line-height:1.5;
}

.edit-textarea:focus{
    border-color:var(--o5);
    box-shadow:0 0 0 3px rgba(249,115,22,0.1);
}

.edit-media-section{
    margin-bottom:16px;
}

.edit-current-media{
    border-radius:12px;
    overflow:hidden;
    margin-bottom:12px;
    position:relative;
}

.edit-current-media img{
    width:100%;
    max-height:300px;
    object-fit:cover;
    display:block;
}

.edit-current-media video{
    width:100%;
    max-height:300px;
    border-radius:12px;
}

.edit-remove-media{
    position:absolute;
    top:10px;
    right:10px;
    background:rgba(0,0,0,0.7);
    border:none;
    color:#fff;
    width:36px;
    height:36px;
    border-radius:50%;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1rem;
    transition:background 0.2s;
}

.edit-remove-media:hover{
    background:rgba(0,0,0,0.85);
}

.edit-media-label{
    display:inline-block;
    padding:10px 18px;
    background:var(--bg);
    border:1.5px solid var(--border);
    border-radius:8px;
    cursor:pointer;
    font-size:0.88rem;
    font-weight:600;
    color:var(--text);
    transition:all 0.2s;
}

.edit-media-label:hover{
    border-color:var(--o5);
    background:var(--card);
}

.edit-media-label i{
    margin-right:6px;
    color:var(--o5);
}

.edit-modal-footer{
    display:flex;
    gap:10px;
    justify-content:flex-end;
    padding-top:16px;
    border-top:1px solid var(--border);
}

.edit-modal-btn{
    padding:10px 24px;
    border-radius:8px;
    border:none;
    cursor:pointer;
    font-size:0.9rem;
    font-weight:600;
    font-family:inherit;
    transition:all 0.2s;
    display:flex;
    align-items:center;
    gap:6px;
}

.edit-modal-btn.cancel{
    background:var(--bg);
    border:1.5px solid var(--border);
    color:var(--text2);
}

.edit-modal-btn.cancel:hover{
    background:var(--card);
    border-color:var(--text3);
}

.edit-modal-btn.save{
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;
    box-shadow:0 2px 8px rgba(249,115,22,0.3);
}

.edit-modal-btn.save:hover{
    opacity:0.9;
    transform:translateY(-1px);
}

.edit-modal-btn:active{
    transform:translateY(0);
}

.edit-modal-btn:disabled{
    opacity:0.5;
    cursor:not-allowed;
}

/* Image Modal */
.image-modal img{
    max-width:90%;
    max-height:90vh;
    border-radius:8px;
    box-shadow:0 8px 32px rgba(0,0,0,0.5);
}

.image-modal-close{
    position:absolute;
    top:20px;
    right:20px;
    background:rgba(255,255,255,0.2);
    border:none;
    color:#fff;
    width:44px;
    height:44px;
    border-radius:50%;
    cursor:pointer;
    font-size:1.3rem;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:background 0.2s;
}

.image-modal-close:hover{
    background:rgba(255,255,255,0.3);
}

/* ═══════════════════════════════════════════════════════════
   LOADING & EMPTY STATES
   ═══════════════════════════════════════════════════════════ */
.loading-posts{
    text-align:center;
    padding:40px 20px;
    color:var(--text3);
}

.loading-spinner{
    display:inline-block;
    width:32px;
    height:32px;
    border:3px solid var(--border);
    border-top-color:var(--o5);
    border-radius:50%;
    animation:spin 1s linear infinite;
}

@keyframes spin{
    to{transform:rotate(360deg);}
}

.no-posts{
    text-align:center;
    padding:60px 20px;
    color:var(--text3);
}

.no-posts i{
    font-size:3.5rem;
    opacity:0.15;
    margin-bottom:16px;
    display:block;
}

.no-posts h3{
    font-size:1.1rem;
    font-weight:600;
    margin-bottom:6px;
    color:var(--text2);
}

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE DESIGN - TABLET (768px - 1024px)
   ═══════════════════════════════════════════════════════════ */
@media(max-width:1024px) and (min-width:769px){
    :root{
        --page-padding:20px;
        --card-padding:18px;
        --card-gap:20px;
    }
    
    .feed-container{
        max-width:750px;
    }
}

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE DESIGN - MOBILE (≤768px)
   ═══════════════════════════════════════════════════════════ */
@media(max-width:768px){
    :root{
        --sbw:0;
        --page-padding:14px;
        --card-padding:16px;
        --card-radius:12px;
        --card-gap:16px;
    }
    
    body{
        font-size:15px;
    }
    
    /* Layout adjustments */
    .page-wrap{
        margin-left:0;
    }
    
    .topbar{
        padding:10px 14px;
    }
    
    .topbar-hamburger{
        display:flex;
    }
    
    .topbar-title{
        font-size:1rem;
    }
    
    /* Feed container */
    .feed-container{
        padding:var(--page-padding);
        max-width:100%;
    }
    
    /* Create post adjustments */
    .create-post-card, .post-card{
        border-radius:var(--card-radius);
        padding:var(--card-padding);
        margin-bottom:var(--card-gap);
    }
    
    .create-post-header, .post-header{
        gap:12px;
    }
    
    .user-avatar, .post-avatar{
        width:42px;
        height:42px;
        font-size:0.95rem;
    }
    
    .create-post-input{
        font-size:0.9rem;
        padding:12px 16px;
        min-height:70px;
    }
    
    /* Action buttons - stack on very small screens */
    .create-post-actions{
        gap:8px;
    }
    
    .post-action-btn{
        padding:7px 12px;
        font-size:0.85rem;
    }
    
    .post-action-btn span{
        display:none;
    }
    
    .btn-post{
        padding:8px 20px;
        font-size:0.85rem;
    }
    
    /* Media preview */
    .media-preview img, .media-preview video{
        max-height:250px;
    }
    
    /* Post content */
    .post-content{
        font-size:0.9rem;
        line-height:1.6;
    }
    
    .post-media img, .post-media video{
        max-height:400px;
    }
    
    /* Interaction buttons */
    .interaction-btn{
        padding:8px;
        font-size:0.85rem;
        gap:5px;
    }
    
    /* Comments */
    .comment-avatar{
        width:32px;
        height:32px;
        font-size:0.82rem;
    }
    
    .comment-input{
        font-size:0.82rem;
        padding:9px 12px;
    }
    
    .btn-comment{
        font-size:0.82rem;
        padding:0 16px;
    }
    
    /* Emoji picker - adjust for mobile */
    .emoji-picker-popup{
        width:calc(100vw - 40px);
        max-width:320px;
        max-height:240px;
    }
    
    .emoji-grid{
        grid-template-columns:repeat(6,1fr);
    }
    
    .emoji-item{
        font-size:1.3rem;
        padding:5px;
    }
    
    /* Edit modal */
    .edit-modal-content{
        padding:20px;
        max-width:calc(100vw - 32px);
    }
    
    .edit-modal-title{
        font-size:1rem;
    }
    
    .edit-textarea{
        font-size:0.9rem;
        padding:12px 14px;
    }
    
    .edit-modal-footer{
        flex-wrap:wrap;
    }
    
    .edit-modal-btn{
        flex:1;
        min-width:calc(50% - 5px);
        justify-content:center;
    }
}

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE DESIGN - SMALL MOBILE (≤480px)
   ═══════════════════════════════════════════════════════════ */
@media(max-width:480px){
    :root{
        --page-padding:10px;
        --card-padding:14px;
        --card-radius:10px;
        --card-gap:12px;
    }
    
    body{
        font-size:14px;
    }
    
    .topbar{
        padding:8px 12px;
    }
    
    .topbar-title{
        font-size:0.95rem;
    }
    
    .topbar-hamburger{
        font-size:1.2rem;
        padding:6px;
    }
    
    /* Avatars */
    .user-avatar, .post-avatar{
        width:38px;
        height:38px;
        font-size:0.9rem;
    }
    
    .comment-avatar{
        width:30px;
        height:30px;
        font-size:0.8rem;
    }
    
    /* Create post */
    .create-post-header, .post-header{
        gap:10px;
    }
    
    .create-post-input{
        padding:10px 14px;
        font-size:0.88rem;
        min-height:60px;
    }
    
    .post-actions-left{
        gap:4px;
    }
    
    .post-action-btn{
        padding:6px 10px;
        font-size:0.82rem;
        gap:4px;
    }
    
    .btn-post{
        padding:7px 18px;
        font-size:0.82rem;
        gap:5px;
    }
    
    /* Media */
    .media-preview img, .media-preview video{
        max-height:200px;
    }
    
    .post-media img, .post-media video{
        max-height:300px;
    }
    
    /* Typography */
    .post-author-name{
        font-size:0.88rem;
    }
    
    .post-meta{
        font-size:0.72rem;
    }
    
    .post-content{
        font-size:0.88rem;
        line-height:1.55;
    }
    
    .post-stats{
        font-size:0.78rem;
    }
    
    /* Interactions */
    .interaction-btn{
        padding:7px;
        font-size:0.82rem;
        gap:4px;
    }
    
    /* Comments */
    .comment-input-wrap{
        gap:8px;
    }
    
    .comment-input-form{
        gap:6px;
    }
    
    .comment-input{
        font-size:0.8rem;
        padding:8px 12px;
    }
    
    .btn-comment{
        font-size:0.8rem;
        padding:0 14px;
        height:36px;
    }
    
    .comment-author{
        font-size:0.8rem;
    }
    
    .comment-text{
        font-size:0.82rem;
    }
    
    .comment-time{
        font-size:0.7rem;
    }
    
    /* Emoji picker */
    .emoji-picker-popup{
        width:calc(100vw - 24px);
        max-height:200px;
    }
    
    .emoji-grid{
        grid-template-columns:repeat(5,1fr);
        gap:3px;
    }
    
    .emoji-item{
        font-size:1.2rem;
        padding:4px;
    }
    
    /* Modals */
    .edit-modal, .image-modal{
        padding:12px;
    }
    
    .edit-modal-content{
        padding:16px;
    }
    
    .edit-modal-header{
        margin-bottom:16px;
        padding-bottom:12px;
    }
    
    .edit-modal-title{
        font-size:0.95rem;
    }
    
    .edit-textarea{
        font-size:0.88rem;
        padding:10px 12px;
        min-height:100px;
    }
    
    .edit-modal-footer{
        padding-top:12px;
    }
    
    .edit-modal-btn{
        padding:9px 20px;
        font-size:0.85rem;
    }
    
    .image-modal img{
        max-width:95%;
        max-height:85vh;
    }
    
    .image-modal-close{
        top:12px;
        right:12px;
        width:40px;
        height:40px;
        font-size:1.2rem;
    }
}

/* ═══════════════════════════════════════════════════════════
   LANDSCAPE MODE ADJUSTMENTS (Mobile)
   ═══════════════════════════════════════════════════════════ */
@media(max-width:896px) and (orientation:landscape){
    .feed-container{
        padding:12px 20px;
    }
    
    .post-media img, .post-media video{
        max-height:50vh;
    }
    
    .media-preview img, .media-preview video{
        max-height:30vh;
    }
    
    .edit-modal-content{
        max-height:85vh;
    }
}

/* ═══════════════════════════════════════════════════════════
   ACCESSIBILITY & TOUCH IMPROVEMENTS
   ═══════════════════════════════════════════════════════════ */
@media(hover:none){
    /* Larger touch targets for mobile */
    .post-action-btn,
    .interaction-btn,
    .post-options,
    .topbar-hamburger{
        min-height:44px;
        min-width:44px;
    }
    
    .emoji-item{
        min-width:40px;
        min-height:40px;
    }
}

/* ═══════════════════════════════════════════════════════════
   SMOOTH SCROLLING
   ═══════════════════════════════════════════════════════════ */
html{
    scroll-behavior:smooth;
}

/* ═══════════════════════════════════════════════════════════
   WEBKIT SCROLLBAR STYLING
   ═══════════════════════════════════════════════════════════ */
::-webkit-scrollbar{
    width:8px;
    height:8px;
}

::-webkit-scrollbar-track{
    background:var(--bg);
}

::-webkit-scrollbar-thumb{
    background:var(--border);
    border-radius:4px;
}

::-webkit-scrollbar-thumb:hover{
    background:var(--text3);
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">
            <i class="fas fa-rss"></i>
            <span>Social Feed</span> 
        </div>
    </div>

    <div class="feed-container">
        <!-- Create Post Card -->
        <div class="create-post-card">
            <div class="create-post-header">
                <div class="user-avatar" aria-label="Your profile">
                    <?php if ($student['profile_photo']): ?>
                        <img src="<?php echo htmlspecialchars($student['profile_photo']); ?>" alt="Your profile">
                    <?php else: ?>
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <textarea 
                    class="create-post-input" 
                    id="postContent" 
                    placeholder="What's on your mind, <?php echo htmlspecialchars(explode(' ', $student['full_name'])[0]); ?>?"
                    rows="1"
                    aria-label="Post content"></textarea>
            </div>
            
            <div class="media-preview" id="mediaPreview">
                <img id="previewImage" src="" alt="Preview" style="display:none;">
                <video id="previewVideo" controls style="display:none;"></video>
                <button class="media-preview-remove" onclick="removeMediaPreview()" aria-label="Remove media">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="create-post-actions">
                <div class="post-actions-left">
                    <input type="file" id="mediaInput" style="display:none;" accept="image/*,video/*" onchange="handleMediaSelect(this)" aria-label="Upload media">
                    <button class="post-action-btn" onclick="document.getElementById('mediaInput').click()" aria-label="Add photo or video">
                        <i class="fas fa-image"></i>
                        <span>Photo/Video</span>
                    </button>
                    <div class="emoji-picker-container">
                        <button class="post-action-btn" onclick="toggleEmojiPicker()" aria-label="Add emoji">
                            <i class="fas fa-smile"></i>
                            <span>Emoji</span>
                        </button>
                        <div class="emoji-picker-popup" id="emojiPicker" role="dialog" aria-label="Emoji picker">
                            <div class="emoji-category">
                                <div class="emoji-category-title">Smileys</div>
                                <div class="emoji-grid">
                                    <span class="emoji-item" onclick="insertEmoji('😀')" role="button" tabindex="0">😀</span>
                                    <span class="emoji-item" onclick="insertEmoji('😃')" role="button" tabindex="0">😃</span>
                                    <span class="emoji-item" onclick="insertEmoji('😄')" role="button" tabindex="0">😄</span>
                                    <span class="emoji-item" onclick="insertEmoji('😁')" role="button" tabindex="0">😁</span>
                                    <span class="emoji-item" onclick="insertEmoji('😅')" role="button" tabindex="0">😅</span>
                                    <span class="emoji-item" onclick="insertEmoji('😂')" role="button" tabindex="0">😂</span>
                                    <span class="emoji-item" onclick="insertEmoji('🤣')" role="button" tabindex="0">🤣</span>
                                    <span class="emoji-item" onclick="insertEmoji('😊')" role="button" tabindex="0">😊</span>
                                    <span class="emoji-item" onclick="insertEmoji('😇')" role="button" tabindex="0">😇</span>
                                    <span class="emoji-item" onclick="insertEmoji('🙂')" role="button" tabindex="0">🙂</span>
                                    <span class="emoji-item" onclick="insertEmoji('🙃')" role="button" tabindex="0">🙃</span>
                                    <span class="emoji-item" onclick="insertEmoji('😉')" role="button" tabindex="0">😉</span>
                                    <span class="emoji-item" onclick="insertEmoji('😌')" role="button" tabindex="0">😌</span>
                                    <span class="emoji-item" onclick="insertEmoji('😍')" role="button" tabindex="0">😍</span>
                                    <span class="emoji-item" onclick="insertEmoji('🥰')" role="button" tabindex="0">🥰</span>
                                    <span class="emoji-item" onclick="insertEmoji('😘')" role="button" tabindex="0">😘</span>
                                </div>
                            </div>
                            <div class="emoji-category">
                                <div class="emoji-category-title">Gestures</div>
                                <div class="emoji-grid">
                                    <span class="emoji-item" onclick="insertEmoji('👍')" role="button" tabindex="0">👍</span>
                                    <span class="emoji-item" onclick="insertEmoji('👎')" role="button" tabindex="0">👎</span>
                                    <span class="emoji-item" onclick="insertEmoji('👏')" role="button" tabindex="0">👏</span>
                                    <span class="emoji-item" onclick="insertEmoji('🙌')" role="button" tabindex="0">🙌</span>
                                    <span class="emoji-item" onclick="insertEmoji('👊')" role="button" tabindex="0">👊</span>
                                    <span class="emoji-item" onclick="insertEmoji('✊')" role="button" tabindex="0">✊</span>
                                    <span class="emoji-item" onclick="insertEmoji('🤝')" role="button" tabindex="0">🤝</span>
                                    <span class="emoji-item" onclick="insertEmoji('🙏')" role="button" tabindex="0">🙏</span>
                                    <span class="emoji-item" onclick="insertEmoji('💪')" role="button" tabindex="0">💪</span>
                                    <span class="emoji-item" onclick="insertEmoji('👌')" role="button" tabindex="0">👌</span>
                                    <span class="emoji-item" onclick="insertEmoji('✌️')" role="button" tabindex="0">✌️</span>
                                    <span class="emoji-item" onclick="insertEmoji('🤞')" role="button" tabindex="0">🤞</span>
                                    <span class="emoji-item" onclick="insertEmoji('🤙')" role="button" tabindex="0">🤙</span>
                                    <span class="emoji-item" onclick="insertEmoji('👋')" role="button" tabindex="0">👋</span>
                                    <span class="emoji-item" onclick="insertEmoji('🤚')" role="button" tabindex="0">🤚</span>
                                    <span class="emoji-item" onclick="insertEmoji('✋')" role="button" tabindex="0">✋</span>
                                </div>
                            </div>
                            <div class="emoji-category">
                                <div class="emoji-category-title">Hearts & Symbols</div>
                                <div class="emoji-grid">
                                    <span class="emoji-item" onclick="insertEmoji('❤️')" role="button" tabindex="0">❤️</span>
                                    <span class="emoji-item" onclick="insertEmoji('🧡')" role="button" tabindex="0">🧡</span>
                                    <span class="emoji-item" onclick="insertEmoji('💛')" role="button" tabindex="0">💛</span>
                                    <span class="emoji-item" onclick="insertEmoji('💚')" role="button" tabindex="0">💚</span>
                                    <span class="emoji-item" onclick="insertEmoji('💙')" role="button" tabindex="0">💙</span>
                                    <span class="emoji-item" onclick="insertEmoji('💜')" role="button" tabindex="0">💜</span>
                                    <span class="emoji-item" onclick="insertEmoji('🖤')" role="button" tabindex="0">🖤</span>
                                    <span class="emoji-item" onclick="insertEmoji('🤍')" role="button" tabindex="0">🤍</span>
                                    <span class="emoji-item" onclick="insertEmoji('🤎')" role="button" tabindex="0">🤎</span>
                                    <span class="emoji-item" onclick="insertEmoji('💔')" role="button" tabindex="0">💔</span>
                                    <span class="emoji-item" onclick="insertEmoji('❣️')" role="button" tabindex="0">❣️</span>
                                    <span class="emoji-item" onclick="insertEmoji('💕')" role="button" tabindex="0">💕</span>
                                    <span class="emoji-item" onclick="insertEmoji('💞')" role="button" tabindex="0">💞</span>
                                    <span class="emoji-item" onclick="insertEmoji('💓')" role="button" tabindex="0">💓</span>
                                    <span class="emoji-item" onclick="insertEmoji('💗')" role="button" tabindex="0">💗</span>
                                    <span class="emoji-item" onclick="insertEmoji('💖')" role="button" tabindex="0">💖</span>
                                </div>
                            </div>
                            <div class="emoji-category">
                                <div class="emoji-category-title">Activities</div>
                                <div class="emoji-grid">
                                    <span class="emoji-item" onclick="insertEmoji('⚽')" role="button" tabindex="0">⚽</span>
                                    <span class="emoji-item" onclick="insertEmoji('🏀')" role="button" tabindex="0">🏀</span>
                                    <span class="emoji-item" onclick="insertEmoji('🏈')" role="button" tabindex="0">🏈</span>
                                    <span class="emoji-item" onclick="insertEmoji('⚾')" role="button" tabindex="0">⚾</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎾')" role="button" tabindex="0">🎾</span>
                                    <span class="emoji-item" onclick="insertEmoji('🏐')" role="button" tabindex="0">🏐</span>
                                    <span class="emoji-item" onclick="insertEmoji('🏓')" role="button" tabindex="0">🏓</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎯')" role="button" tabindex="0">🎯</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎮')" role="button" tabindex="0">🎮</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎲')" role="button" tabindex="0">🎲</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎨')" role="button" tabindex="0">🎨</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎭')" role="button" tabindex="0">🎭</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎪')" role="button" tabindex="0">🎪</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎬')" role="button" tabindex="0">🎬</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎤')" role="button" tabindex="0">🎤</span>
                                    <span class="emoji-item" onclick="insertEmoji('🎧')" role="button" tabindex="0">🎧</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="btn-post" id="btnCreatePost" onclick="createPost()">
                    <i class="fas fa-paper-plane fa-sm"></i> Post
                </button>
            </div>
        </div>

        <!-- Posts Feed -->
        <div id="postsContainer">
            <?php if (empty($posts)): ?>
            <div class="no-posts">
                <i class="fas fa-comments"></i>
                <h3>No posts yet</h3>
                <p>Be the first to share something!</p>
            </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php renderPost($post, $sid, $student); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="loading-posts" id="loadingMore" style="display:none;">
            <div class="loading-spinner"></div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="image-modal" id="imageModal" onclick="closeImageModal()" role="dialog" aria-label="Image preview">
    <button class="image-modal-close" aria-label="Close image preview"><i class="fas fa-times"></i></button>
    <img id="imageModalImg" src="" alt="Full size image">
</div>

<!-- Edit Post Modal -->
<div class="edit-modal" id="editModal" role="dialog" aria-labelledby="editModalTitle">
    <div class="edit-modal-content">
        <div class="edit-modal-header">
            <div class="edit-modal-title" id="editModalTitle">
                <i class="fas fa-edit"></i>
                Edit Post
            </div>
            <button class="edit-modal-close" onclick="closeEditModal()" aria-label="Close edit modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="edit-modal-body">
            <textarea class="edit-textarea" id="editContent" placeholder="What's on your mind?" aria-label="Edit post content"></textarea>
            <div class="edit-media-section">
                <div class="edit-current-media" id="editCurrentMedia" style="display:none;">
                    <img id="editMediaImg" src="" alt="Current media" style="display:none;">
                    <video id="editMediaVideo" controls style="display:none;"></video>
                    <button class="edit-remove-media" onclick="removeEditMedia()" aria-label="Remove media">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <input type="file" id="editMediaInput" style="display:none;" accept="image/*,video/*" onchange="handleEditMediaSelect(this)">
                <label for="editMediaInput" class="edit-media-label">
                    <i class="fas fa-image"></i>
                    Change Photo/Video
                </label>
            </div>
        </div>
        <div class="edit-modal-footer">
            <button class="edit-modal-btn cancel" onclick="closeEditModal()">Cancel</button>
            <button class="edit-modal-btn save" id="btnSaveEdit" onclick="saveEditPost()">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<script>
const MY_ID = <?php echo $sid; ?>;
let selectedMedia = null;
let currentOffset = <?php echo count($posts); ?>;
let isLoadingMore = false;
let hasMorePosts = true;
let editingPostId = null;
let editMediaToRemove = false;

// Auto-resize textarea
const postContent = document.getElementById('postContent');
postContent.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 200) + 'px';
});

// ═══════════════════════════════════════════════════════════
// EMOJI PICKER
// ═══════════════════════════════════════════════════════════
function toggleEmojiPicker() {
    const picker = document.getElementById('emojiPicker');
    picker.classList.toggle('active');
    
    if (picker.classList.contains('active')) {
        setTimeout(() => {
            document.addEventListener('click', closeEmojiPickerOutside);
        }, 100);
    }
}

function closeEmojiPickerOutside(e) {
    const picker = document.getElementById('emojiPicker');
    const btn = document.querySelector('.emoji-picker-container .post-action-btn');
    
    if (!picker.contains(e.target) && !btn.contains(e.target)) {
        picker.classList.remove('active');
        document.removeEventListener('click', closeEmojiPickerOutside);
    }
}

function insertEmoji(emoji) {
    const textarea = document.getElementById('postContent');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    textarea.value = text.substring(0, start) + emoji + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
    textarea.focus();
    
    // Trigger resize
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
    
    // Close picker on mobile after selection
    if (window.innerWidth <= 768) {
        const picker = document.getElementById('emojiPicker');
        picker.classList.remove('active');
    }
}

// ═══════════════════════════════════════════════════════════
// MEDIA HANDLING
// ═══════════════════════════════════════════════════════════
function handleMediaSelect(input) {
    if (!input.files || !input.files[0]) return;
    selectedMedia = input.files[0];
    
    const preview = document.getElementById('mediaPreview');
    const previewImg = document.getElementById('previewImage');
    const previewVid = document.getElementById('previewVideo');
    
    if (selectedMedia.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
            previewVid.style.display = 'none';
            preview.classList.add('active');
        };
        reader.readAsDataURL(selectedMedia);
    } else if (selectedMedia.type.startsWith('video/')) {
        const url = URL.createObjectURL(selectedMedia);
        previewVid.src = url;
        previewVid.style.display = 'block';
        previewImg.style.display = 'none';
        preview.classList.add('active');
    }
}

function removeMediaPreview() {
    selectedMedia = null;
    document.getElementById('mediaInput').value = '';
    document.getElementById('mediaPreview').classList.remove('active');
    document.getElementById('previewImage').src = '';
    document.getElementById('previewVideo').src = '';
}

// ═══════════════════════════════════════════════════════════
// CREATE POST
// ═══════════════════════════════════════════════════════════
function createPost() {
    const content = postContent.value.trim();
    if (!content && !selectedMedia) {
        alert('Please write something or add media');
        return;
    }

    const btn = document.getElementById('btnCreatePost');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';

    const fd = new FormData();
    fd.append('action', 'create_post');
    fd.append('content', content);
    if (selectedMedia) fd.append('media', selectedMedia);

    fetch('social_feed.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Error: ' + (d.error || 'Failed to create post'));
            }
        })
        .catch(err => {
            alert('Error creating post: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane fa-sm"></i> Post';
        });
}

// ═══════════════════════════════════════════════════════════
// POST INTERACTIONS
// ═══════════════════════════════════════════════════════════
function toggleLike(postId) {
    const btn = document.querySelector(`[data-post-id="${postId}"] .interaction-btn.like-btn`);
    const countEl = btn.querySelector('.like-count');
    
    const fd = new FormData();
    fd.append('action', 'toggle_like');
    fd.append('post_id', postId);

    fetch('social_feed.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                countEl.textContent = d.likes_count;
                if (d.action === 'liked') {
                    btn.classList.add('liked');
                } else {
                    btn.classList.remove('liked');
                }
            }
        })
        .catch(() => {});
}

function toggleComments(postId) {
    const section = document.getElementById('comments-' + postId);
    const wasActive = section.classList.contains('active');
    
    if (wasActive) {
        section.classList.remove('active');
    } else {
        section.classList.add('active');
        if (!section.dataset.loaded) {
            loadComments(postId);
        }
    }
}

function loadComments(postId) {
    const section = document.getElementById('comments-' + postId);
    const list = section.querySelector('.comments-list');
    
    list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text3);"><i class="fas fa-spinner fa-spin"></i></div>';
    
    const fd = new FormData();
    fd.append('action', 'fetch_comments');
    fd.append('post_id', postId);

    fetch('social_feed.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                section.dataset.loaded = 'true';
                if (d.comments.length === 0) {
                    list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text3);font-size:.85rem;">No comments yet</div>';
                } else {
                    list.innerHTML = d.comments.map(c => renderComment(c)).join('');
                }
            }
        })
        .catch(() => {
            list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text3);">Failed to load comments</div>';
        });
}

function addComment(postId) {
    const input = document.getElementById('comment-input-' + postId);
    const comment = input.value.trim();
    
    if (!comment) return;

    const btn = input.nextElementSibling;
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'add_comment');
    fd.append('post_id', postId);
    fd.append('comment', comment);

    fetch('social_feed.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const list = document.querySelector(`#comments-${postId} .comments-list`);
                const noComments = list.querySelector('div[style*="No comments"]');
                if (noComments) noComments.remove();
                
                list.insertAdjacentHTML('beforeend', renderComment(d.comment));
                input.value = '';
                
                const countEl = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
                if (countEl) countEl.textContent = d.comments_count + (d.comments_count === 1 ? ' Comment' : ' Comments');
            } else {
                alert('Error: ' + (d.error || 'Failed to add comment'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
        });
}

function renderComment(c) {
    const time = formatTime(c.created_at);
    const initial = c.full_name ? c.full_name[0].toUpperCase() : '?';
    const profileImg = c.profile_photo 
        ? `<img src="${escHtml(c.profile_photo)}" alt="${escHtml(c.full_name)}">`
        : initial;
    
    return `
        <div class="comment-item">
            <div class="comment-avatar">${profileImg}</div>
            <div class="comment-content">
                <div class="comment-author">${escHtml(c.full_name)}</div>
                <div class="comment-text">${escHtml(c.content)}</div>
                <div class="comment-time">${time}</div>
            </div>
        </div>
    `;
}

// ═══════════════════════════════════════════════════════════
// POST OPTIONS
// ═══════════════════════════════════════════════════════════
function togglePostOptions(btn, postId, isOwner) {
    const menu = btn.nextElementSibling;
    const wasActive = menu.classList.contains('active');
    
    document.querySelectorAll('.options-menu').forEach(m => m.classList.remove('active'));
    
    if (!wasActive) {
        menu.classList.add('active');
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target) && !btn.contains(e.target)) {
                    menu.classList.remove('active');
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 100);
    }
}

// ═══════════════════════════════════════════════════════════
// EDIT POST
// ═══════════════════════════════════════════════════════════
function openEditModal(postId) {
    editingPostId = postId;
    const postCard = document.querySelector(`[data-post-id="${postId}"]`);
    const content = postCard.querySelector('.post-content')?.textContent || '';
    const mediaEl = postCard.querySelector('.post-media img, .post-media video');
    
    document.getElementById('editContent').value = content.trim();
    
    const currentMedia = document.getElementById('editCurrentMedia');
    const editImg = document.getElementById('editMediaImg');
    const editVid = document.getElementById('editMediaVideo');
    
    if (mediaEl) {
        if (mediaEl.tagName === 'IMG') {
            editImg.src = mediaEl.src;
            editImg.style.display = 'block';
            editVid.style.display = 'none';
        } else if (mediaEl.tagName === 'VIDEO') {
            editVid.querySelector('source').src = mediaEl.querySelector('source').src;
            editVid.load();
            editVid.style.display = 'block';
            editImg.style.display = 'none';
        }
        currentMedia.style.display = 'block';
    } else {
        currentMedia.style.display = 'none';
    }
    
    editMediaToRemove = false;
    document.getElementById('editModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
    document.getElementById('editContent').value = '';
    document.getElementById('editMediaInput').value = '';
    document.getElementById('editCurrentMedia').style.display = 'none';
    editingPostId = null;
    editMediaToRemove = false;
    document.body.style.overflow = '';
}

function handleEditMediaSelect(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const currentMedia = document.getElementById('editCurrentMedia');
    const editImg = document.getElementById('editMediaImg');
    const editVid = document.getElementById('editMediaVideo');
    
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
            editImg.src = e.target.result;
            editImg.style.display = 'block';
            editVid.style.display = 'none';
            currentMedia.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else if (file.type.startsWith('video/')) {
        const url = URL.createObjectURL(file);
        editVid.querySelector('source').src = url;
        editVid.load();
        editVid.style.display = 'block';
        editImg.style.display = 'none';
        currentMedia.style.display = 'block';
    }
}

function removeEditMedia() {
    document.getElementById('editCurrentMedia').style.display = 'none';
    document.getElementById('editMediaInput').value = '';
    editMediaToRemove = true;
}

function saveEditPost() {
    const content = document.getElementById('editContent').value.trim();
    const fileInput = document.getElementById('editMediaInput');
    
    if (!content && !fileInput.files[0] && editMediaToRemove) {
        alert('Post must have content or media');
        return;
    }

    const btn = document.getElementById('btnSaveEdit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const fd = new FormData();
    fd.append('action', 'edit_post');
    fd.append('post_id', editingPostId);
    fd.append('content', content);
    
    if (fileInput.files[0]) {
        fd.append('media', fileInput.files[0]);
    }

    fetch('social_feed.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const postCard = document.querySelector(`[data-post-id="${editingPostId}"]`);
                const contentEl = postCard.querySelector('.post-content');
                
                if (content) {
                    if (contentEl) {
                        contentEl.textContent = content;
                    } else {
                        const mediaEl = postCard.querySelector('.post-media');
                        const newContent = document.createElement('div');
                        newContent.className = 'post-content';
                        newContent.textContent = content;
                        if (mediaEl) {
                            mediaEl.parentNode.insertBefore(newContent, mediaEl);
                        } else {
                            postCard.querySelector('.post-header').after(newContent);
                        }
                    }
                } else if (contentEl) {
                    contentEl.remove();
                }
                
                if (d.media_path) {
                    let mediaEl = postCard.querySelector('.post-media');
                    if (!mediaEl) {
                        mediaEl = document.createElement('div');
                        mediaEl.className = 'post-media';
                        const contentDiv = postCard.querySelector('.post-content');
                        if (contentDiv) {
                            contentDiv.after(mediaEl);
                        } else {
                            postCard.querySelector('.post-header').after(mediaEl);
                        }
                    }
                    
                    if (d.media_type === 'image') {
                        mediaEl.innerHTML = `<img src="${escHtml(d.media_path)}" alt="Post image" onclick="openImageModal('${escHtml(d.media_path)}')">`;
                    } else if (d.media_type === 'video') {
                        mediaEl.innerHTML = `<video controls><source src="${escHtml(d.media_path)}" type="video/mp4"></video>`;
                    }
                } else if (editMediaToRemove) {
                    const mediaEl = postCard.querySelector('.post-media');
                    if (mediaEl) mediaEl.remove();
                }
                
                closeEditModal();
            } else {
                alert('Error: ' + (d.error || 'Failed to update post'));
            }
        })
        .catch(err => {
            alert('Error updating post: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        });
}

function deletePost(postId) {
    if (!confirm('Delete this post?')) return;

    const fd = new FormData();
    fd.append('action', 'delete_post');
    fd.append('post_id', postId);

    fetch('social_feed.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.querySelector(`[data-post-id="${postId}"]`).remove();
                if (!document.querySelectorAll('.post-card').length) {
                    document.getElementById('postsContainer').innerHTML = `
                        <div class="no-posts">
                            <i class="fas fa-comments"></i>
                            <h3>No posts yet</h3>
                            <p>Be the first to share something!</p>
                        </div>
                    `;
                }
            } else {
                alert('Error: ' + (d.error || 'Failed to delete post'));
            }
        })
        .catch(() => alert('Error deleting post'));
}

// ═══════════════════════════════════════════════════════════
// IMAGE MODAL
// ═══════════════════════════════════════════════════════════
function openImageModal(src) {
    document.getElementById('imageModalImg').src = src;
    document.getElementById('imageModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('open');
    document.body.style.overflow = '';
}

// ═══════════════════════════════════════════════════════════
// INFINITE SCROLL
// ═══════════════════════════════════════════════════════════
window.addEventListener('scroll', () => {
    if (isLoadingMore || !hasMorePosts) return;
    
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const scrollHeight = document.documentElement.scrollHeight;
    const clientHeight = document.documentElement.clientHeight;
    
    if (scrollTop + clientHeight >= scrollHeight - 300) {
        loadMorePosts();
    }
});

function loadMorePosts() {
    isLoadingMore = true;
    document.getElementById('loadingMore').style.display = 'block';

    const fd = new FormData();
    fd.append('action', 'fetch_posts');
    fd.append('offset', currentOffset);

    fetch('social_feed.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success && d.posts.length > 0) {
                d.posts.forEach(post => {
                    document.getElementById('postsContainer').insertAdjacentHTML('beforeend', renderPostHTML(post));
                });
                currentOffset += d.posts.length;
                if (d.posts.length < 10) hasMorePosts = false;
            } else {
                hasMorePosts = false;
            }
        })
        .catch(() => {})
        .finally(() => {
            isLoadingMore = false;
            document.getElementById('loadingMore').style.display = 'none';
        });
}

function renderPostHTML(post) {
    const time = formatTime(post.created_at);
    const initial = post.full_name ? post.full_name[0].toUpperCase() : '?';
    const profileImg = post.profile_photo 
        ? `<img src="${escHtml(post.profile_photo)}" alt="${escHtml(post.full_name)}">`
        : initial;
    const isOwner = post.student_id == MY_ID;
    const likedClass = post.has_liked ? 'liked' : '';
    
    let mediaHTML = '';
    if (post.media_path) {
        if (post.media_type === 'image') {
            mediaHTML = `<div class="post-media"><img src="${escHtml(post.media_path)}" alt="Post image" onclick="openImageModal('${escHtml(post.media_path)}')"></div>`;
        } else if (post.media_type === 'video') {
            mediaHTML = `<div class="post-media"><video controls><source src="${escHtml(post.media_path)}" type="video/mp4"></video></div>`;
        }
    }

    return `
        <div class="post-card" data-post-id="${post.id}">
            <div class="post-header">
                <div class="post-avatar">${profileImg}</div>
                <div class="post-author-info">
                    <div class="post-author-name">${escHtml(post.full_name)}</div>
                    <div class="post-meta">
                        ${post.domain_interest ? escHtml(post.domain_interest) + ' • ' : ''}${time}
                    </div>
                </div>
                <div style="position:relative;">
                    <button class="post-options" onclick="togglePostOptions(this, ${post.id}, ${isOwner})" aria-label="Post options">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <div class="options-menu">
                        ${isOwner ? `
                            <div class="option-item" onclick="openEditModal(${post.id})"><i class="fas fa-edit"></i> Edit Post</div>
                            <div class="option-item danger" onclick="deletePost(${post.id})"><i class="fas fa-trash"></i> Delete Post</div>
                        ` : ''}
                    </div>
                </div>
            </div>
            ${post.content ? `<div class="post-content">${escHtml(post.content)}</div>` : ''}
            ${mediaHTML}
            <div class="post-stats">
                <div class="stat-item">
                    <i class="fas fa-heart" style="color:var(--o5);"></i>
                    ${post.likes_count} ${post.likes_count === 1 ? 'Like' : 'Likes'}
                </div>
                <div class="stat-item comment-count">
                    ${post.comments_count} ${post.comments_count === 1 ? 'Comment' : 'Comments'}
                </div>
            </div>
            <div class="post-interactions">
                <button class="interaction-btn like-btn ${likedClass}" onclick="toggleLike(${post.id})" aria-label="Like post">
                    <i class="fas fa-heart"></i>
                    <span class="like-count">${post.likes_count}</span>
                </button>
                <button class="interaction-btn" onclick="toggleComments(${post.id})" aria-label="Comment on post">
                    <i class="fas fa-comment"></i>
                    Comment
                </button>
            </div>
            <div class="comments-section" id="comments-${post.id}">
                <div class="comment-input-wrap">
                    <div class="comment-avatar"><?php echo $student['profile_photo'] ? '<img src="'.htmlspecialchars($student['profile_photo']).'" alt="">' : strtoupper(substr($student['full_name'], 0, 1)); ?></div>
                    <div class="comment-input-form">
                        <textarea class="comment-input" id="comment-input-${post.id}" placeholder="Write a comment..." rows="1" aria-label="Comment input"></textarea>
                        <button class="btn-comment" onclick="addComment(${post.id})">Post</button>
                    </div>
                </div>
                <div class="comments-list"></div>
            </div>
        </div>
    `;
}

// ═══════════════════════════════════════════════════════════
// UTILITY FUNCTIONS
// ═══════════════════════════════════════════════════════════
function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatTime(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
}

function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}

// Prevent modal close propagation
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

// Keyboard accessibility for emoji picker
document.querySelectorAll('.emoji-item').forEach(item => {
    item.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.click();
        }
    });
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('editModal').classList.contains('open')) {
            closeEditModal();
        }
        if (document.getElementById('imageModal').classList.contains('open')) {
            closeImageModal();
        }
        if (document.getElementById('emojiPicker').classList.contains('active')) {
            document.getElementById('emojiPicker').classList.remove('active');
        }
    }
});
</script>
</body>
</html>

<?php
// Helper function to render a post
function renderPost($post, $sid, $student) {
    $time = formatTimeAgo($post['created_at']);
    $initial = strtoupper(substr($post['full_name'], 0, 1));
    $profileImg = $post['profile_photo'] 
        ? '<img src="' . htmlspecialchars($post['profile_photo']) . '" alt="' . htmlspecialchars($post['full_name']) . '">'
        : $initial;
    $isOwner = $post['student_id'] == $sid;
    $likedClass = $post['has_liked'] ? 'liked' : '';
    
    echo '<div class="post-card" data-post-id="' . $post['id'] . '">';
    echo '<div class="post-header">';
    echo '<div class="post-avatar">' . $profileImg . '</div>';
    echo '<div class="post-author-info">';
    echo '<div class="post-author-name">' . htmlspecialchars($post['full_name']) . '</div>';
    echo '<div class="post-meta">';
    if ($post['domain_interest']) echo htmlspecialchars($post['domain_interest']) . ' • ';
    echo $time . '</div>';
    echo '</div>';
    echo '<div style="position:relative;">';
    echo '<button class="post-options" onclick="togglePostOptions(this, ' . $post['id'] . ', ' . ($isOwner ? 'true' : 'false') . ')" aria-label="Post options">';
    echo '<i class="fas fa-ellipsis-h"></i></button>';
    echo '<div class="options-menu">';
    if ($isOwner) {
        echo '<div class="option-item" onclick="openEditModal(' . $post['id'] . ')"><i class="fas fa-edit"></i> Edit Post</div>';
        echo '<div class="option-item danger" onclick="deletePost(' . $post['id'] . ')"><i class="fas fa-trash"></i> Delete Post</div>';
    }
    echo '</div></div></div>';
    
    if ($post['content']) {
        echo '<div class="post-content">' . nl2br(htmlspecialchars($post['content'])) . '</div>';
    }
    
    if ($post['media_path']) {
        echo '<div class="post-media">';
        if ($post['media_type'] === 'image') {
            echo '<img src="' . htmlspecialchars($post['media_path']) . '" alt="Post image" onclick="openImageModal(\'' . htmlspecialchars($post['media_path']) . '\')">';
        } elseif ($post['media_type'] === 'video') {
            echo '<video controls><source src="' . htmlspecialchars($post['media_path']) . '" type="video/mp4"></video>';
        }
        echo '</div>';
    }
    
    echo '<div class="post-stats">';
    echo '<div class="stat-item"><i class="fas fa-heart" style="color:var(--o5);"></i> ';
    echo $post['likes_count'] . ' ' . ($post['likes_count'] == 1 ? 'Like' : 'Likes') . '</div>';
    echo '<div class="stat-item comment-count">' . $post['comments_count'] . ' ' . ($post['comments_count'] == 1 ? 'Comment' : 'Comments') . '</div>';
    echo '</div>';
    
    echo '<div class="post-interactions">';
    echo '<button class="interaction-btn like-btn ' . $likedClass . '" onclick="toggleLike(' . $post['id'] . ')" aria-label="Like post">';
    echo '<i class="fas fa-heart"></i> <span class="like-count">' . $post['likes_count'] . '</span></button>';
    echo '<button class="interaction-btn" onclick="toggleComments(' . $post['id'] . ')" aria-label="Comment on post">';
    echo '<i class="fas fa-comment"></i> Comment</button>';
    echo '</div>';
    
    // Comments section
    echo '<div class="comments-section" id="comments-' . $post['id'] . '">';
    echo '<div class="comment-input-wrap">';
    echo '<div class="comment-avatar">';
    if ($student['profile_photo']) {
        echo '<img src="' . htmlspecialchars($student['profile_photo']) . '" alt="Your profile">';
    } else {
        echo strtoupper(substr($student['full_name'], 0, 1));
    }
    echo '</div>';
    echo '<div class="comment-input-form">';
    echo '<textarea class="comment-input" id="comment-input-' . $post['id'] . '" placeholder="Write a comment..." rows="1" aria-label="Comment input"></textarea>';
    echo '<button class="btn-comment" onclick="addComment(' . $post['id'] . ')">Post</button>';
    echo '</div></div>';
    echo '<div class="comments-list"></div>';
    echo '</div>';
    
    echo '</div>';
}

function formatTimeAgo($dateStr) {
    $date = new DateTime($dateStr);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $date->getTimestamp();
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    
    return $date->format('M d, Y');
}
?>
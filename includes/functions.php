<?php
// =====================================================================
// CORE HELPER FUNCTIONS
// =====================================================================

require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------------
// Output & redirects
// ---------------------------------------------------------------
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/' . ltrim($path, '/'));
    exit;
}

// ---------------------------------------------------------------
// Flash messages (one-shot)
// ---------------------------------------------------------------
function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function show_flash() {
    $types = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $html = '';
    foreach ($types as $key => $bootstrap) {
        if ($m = flash($key)) {
            $html .= '<div class="alert alert-' . $bootstrap . ' alert-dismissible fade show" role="alert">'
                   . e($m)
                   . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }
    return $html;
}

// ---------------------------------------------------------------
// Currency formatting
// ---------------------------------------------------------------
function money($amount) {
    return 'L$ ' . number_format((float)$amount, 2);
}

// ---------------------------------------------------------------
// Input sanitizers
// ---------------------------------------------------------------
function post($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}
function get($key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

// ---------------------------------------------------------------
// Session user helpers
// ---------------------------------------------------------------
function current_user() {
    return $_SESSION['user'] ?? null;
}
function current_role() {
    return $_SESSION['user']['role'] ?? null;
}
function is_logged_in() {
    return isset($_SESSION['user']);
}
function user_id() {
    return $_SESSION['user']['id'] ?? null;
}

// ---------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function verify_csrf() {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        flash('error', 'Invalid or expired form. Please try again.');
        redirect('index.php');
    }
}

// ---------------------------------------------------------------
// Settings
// ---------------------------------------------------------------
function get_settings() {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
    }
    return $cache;
}

// ---------------------------------------------------------------
// Activity log
// ---------------------------------------------------------------
function log_activity($action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, role, action, details, ip_address)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            user_id(),
            current_role(),
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // swallow silently; never break user flow over logging
    }
}

// ---------------------------------------------------------------
// Grade utilities
// ---------------------------------------------------------------
function score_to_letter($score) {
    if ($score === null || $score === '') return null;
    $score = (float)$score;
    foreach (GRADE_SCALE as $letter => $range) {
        if ($score >= $range[0] && $score <= $range[1]) {
            return $letter;
        }
    }
    return 'F';
}

/**
 * Recompute sem1_avg, sem2_avg, yearly_avg, grade_letter for one student+subject+session.
 */
function recalc_student_subject($studentId, $subjectId, $session) {
    global $pdo;

    // Fetch class id from grades (or students)
    $rows = $pdo->prepare("SELECT marking_period, score FROM grades
                           WHERE student_id=? AND subject_id=? AND session=?");
    $rows->execute([$studentId, $subjectId, $session]);
    $data = [];
    foreach ($rows->fetchAll() as $r) {
        $data[$r['marking_period']] = $r['score'] !== null ? (float)$r['score'] : null;
    }

    $sem1 = _avg_periods($data, SEMESTER_1_PERIODS);
    $sem2 = _avg_periods($data, SEMESTER_2_PERIODS);

    $yearly = null;
    if ($sem1 !== null && $sem2 !== null) {
        $yearly = round(($sem1 + $sem2) / 2, 2);
    }

    $letter = score_to_letter($yearly);

    // Need a class_id — take from student record
    $cid = $pdo->prepare("SELECT class_id FROM students WHERE id=? LIMIT 1");
    $cid->execute([$studentId]);
    $classId = $cid->fetchColumn();
    if (!$classId) return;

    $sql = "INSERT INTO computed_grades
              (student_id, subject_id, class_id, session, sem1_avg, sem2_avg, yearly_avg, grade_letter, computed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
              sem1_avg=VALUES(sem1_avg),
              sem2_avg=VALUES(sem2_avg),
              yearly_avg=VALUES(yearly_avg),
              grade_letter=VALUES(grade_letter),
              computed_at=NOW()";
    $pdo->prepare($sql)->execute([
        $studentId, $subjectId, $classId, $session, $sem1, $sem2, $yearly, $letter
    ]);
}

function _avg_periods($data, $periods) {
    $sum = 0; $count = 0;
    foreach ($periods as $p) {
        if (isset($data[$p]) && $data[$p] !== null) {
            $sum += $data[$p];
            $count++;
        }
    }
    return $count > 0 ? round($sum / $count, 2) : null;
}

// ---------------------------------------------------------------
// ID generators
// ---------------------------------------------------------------
function next_student_id() {
    global $pdo;
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(["STU/$year/%"]);
    $last = $stmt->fetchColumn();
    $n = $last ? ((int)substr($last, -4)) + 1 : 1;
    return sprintf('STU/%s/%04d', $year, $n);
}

function next_teacher_id() {
    global $pdo;
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT staff_id FROM teachers WHERE staff_id LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(["TCH/$year/%"]);
    $last = $stmt->fetchColumn();
    $n = $last ? ((int)substr($last, -4)) + 1 : 1;
    return sprintf('TCH/%s/%04d', $year, $n);
}

// ---------------------------------------------------------------
// File upload helper
// ---------------------------------------------------------------
function upload_file($fieldName, $subfolder, $allowedTypes) {
    if (empty($_FILES[$fieldName]['name'])) return null;
    $f = $_FILES[$fieldName];
    if ($f['error'] !== UPLOAD_ERR_OK) return null;
    if ($f['size'] > MAX_UPLOAD_SIZE)  return null;

    $mime = mime_content_type($f['tmp_name']);
    if (!in_array($mime, $allowedTypes, true)) return null;

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $name = uniqid('u_', true) . '.' . $ext;
    $dir = UPLOAD_PATH . $subfolder . '/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) return null;

    return $subfolder . '/' . $name;
}

// ======= Helper function to delete a file from the uploads directory =======
// ---------------------------------------------------------------
// Pagination helper
// ---------------------------------------------------------------
function paginate($total, $perPage, $currentPage) {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return ['total' => $total, 'pages' => $totalPages, 'page' => $currentPage, 'offset' => $offset, 'per_page' => $perPage];
}

// ---------------------------------------------------------------
// Flash-then-redirect shortcut
// ---------------------------------------------------------------
function flash_redirect($type, $msg, $path) {
    flash($type, $msg);
    redirect($path);
}

// ---------------------------------------------------------------
// Get the logged-in student record
// ---------------------------------------------------------------
function current_student() {
    global $pdo;
    $uid = user_id();
    $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id=? LIMIT 1");
    $stmt->execute([$uid]);
    return $stmt->fetch();
}

// ---------------------------------------------------------------
// Get the logged-in teacher record
// ---------------------------------------------------------------
function current_teacher() {
    global $pdo;
    $uid = user_id();
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
    $stmt->execute([$uid]);
    return $stmt->fetch();
}
<?php
// =====================================================================
// GLOBAL CONSTANTS
// =====================================================================

// --- Base URL (adjust if your folder name differs) ---
define('BASE_URL', 'http://localhost:8888/');
define('APP_NAME', 'HSMS — Liberian High School');

// --- Filesystem paths ---
define('ROOT_PATH',    dirname(__DIR__));
define('UPLOAD_PATH',  ROOT_PATH . '/assets/uploads/');
define('UPLOAD_URL',   BASE_URL . 'assets/uploads/');

// --- File upload limits ---
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);
define('ALLOWED_DOC_TYPES',   ['application/pdf','image/jpeg','image/png']);

// --- Session cookie name ---
define('SESSION_NAME', 'HSMS_SESSION');

// --- Marking periods (ordered) ---
define('MARKING_PERIODS', [
    'MP1'   => '1st Marking Period',
    'MP2'   => '2nd Marking Period',
    'MP3'   => '3rd Marking Period',
    'EXAM1' => 'Semester 1 Exam',
    'MP4'   => '4th Marking Period',
    'MP5'   => '5th Marking Period',
    'MP6'   => '6th Marking Period',
    'EXAM2' => 'Semester 2 Exam',
]);

// Which marking periods belong to which semester
define('SEMESTER_1_PERIODS', ['MP1','MP2','MP3','EXAM1']);
define('SEMESTER_2_PERIODS', ['MP4','MP5','MP6','EXAM2']);

// --- Grade boundaries (score min => letter) ---
define('GRADE_SCALE', [
    'A' => [90, 100],
    'B' => [80, 89.99],
    'C' => [70, 79.99],
    'D' => [60, 69.99],
    'E' => [50, 59.99],
    'F' => [0,  49.99],
]);

// --- Grade edit lock window (hours) ---
define('GRADE_LOCK_HOURS', 24);

// --- Liberia's 15 counties ---
define('LIBERIA_COUNTIES', [
    'Bomi','Bong','Gbarpolu','Grand Bassa','Grand Cape Mount',
    'Grand Gedeh','Grand Kru','Lofa','Margibi','Maryland',
    'Montserrado','Nimba','River Cess','River Gee','Sinoe'
]);

// --- Timezone ---
date_default_timezone_set('Africa/Monrovia');
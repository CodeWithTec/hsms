<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) redirect_dashboard();
redirect('auth/login.php');
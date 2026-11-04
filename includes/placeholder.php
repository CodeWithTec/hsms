<?php
// Reusable "coming soon" page — used by all placeholder pages
// Expects: $pageTitle and $section set by the caller
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/header.php';

$role = current_role();
require_once __DIR__ . '/sidebar_' . $role . '.php';
?>
<div class="col-lg-10 p-4">
  <h4 class="mb-1"><?= e($pageTitle ?? 'Page') ?></h4>
  <p class="text-muted small mb-4"><?= e($section ?? '') ?></p>
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="bi bi-tools text-warning" style="font-size:3rem;"></i>
      <h5 class="mt-3">Under Construction</h5>
      <p class="text-muted mb-0">This module will be built in the next phase.</p>
    </div>
  </div>





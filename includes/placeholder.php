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
    <div class="card-body text-center py-0">
      <!-- <i class="bi bi-tools text-warning" style="font-size:3rem;"></i>
      <h5 class="mt-3">Under Construction</h5>
      <p class="text-muted mb-0">This module will be built in the next phase.</p> -->
       <!-- LETTERHEAD -->
    <div class="letterhead">

        <!-- SCHOOL HEADER -->
        <div class="school-header">

            <div class="row align-items-center">

                <!-- Logo -->
                <div class="col-md-2 text-center">
                    <img src="../assets/uploads/school/school_logo.png"
                         alt="School Logo"
                         class="school-logo">
                </div>

                <!-- School Information -->
                <div class="col-md-8 text-center">

                    <h1 class="school-name">
                             <?= e($__settings['school_name']) ?>
                    </h1>

                    <div class="school-info">
                        <span>
                            <i class="bi bi-geo-alt-fill"></i>
                            <!-- School Address, Monrovia, Liberia -->
                            <?= e($__settings['school_address']) ?>
                        </span>
                        <br>
                        <span>
                            <i class="bi bi-telephone-fill"></i>
                            <!-- +231 771 502 727 -->
                            <?= e($__settings['school_phone']) ?>
                        </span>
                        &nbsp; | &nbsp;
                        <span>
                            <i class="bi bi-envelope-fill"></i>
                            <!-- info@yourschool.com -->
                            <?= e($__settings['school_email']) ?>
                        </span>
                    </div>
                  <p class="school-motto">
                        <!-- "Education is the Key to Success" -->
                        <?= e($__settings['school_motto'] ?? 'Education is the Key to Success') ?>
                    </p>
                </div>

                <!-- Optional Right Logo -->
                <div class="col-md-2 text-center">
                    <img src="../assets/uploads/school/school_logo.png"
                         alt="School Logo"
                         class="school-logo">
                </div>

            </div>

        </div>

    </div>
  </div>





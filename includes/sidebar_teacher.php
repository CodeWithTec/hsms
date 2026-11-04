<div class="col-lg-2 d-none d-lg-block bg-light min-vh-100 p-0 border-end">
  <ul class="nav flex-column p-2 gap-3 sidebar">
    <li><a class="nav-link" href="<?= BASE_URL ?>teacher/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>teacher/my_classes.php"><i class="bi bi-easel"></i> My Classes</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>teacher/submit_grades.php"><i class="bi bi-clipboard-data"></i> Grades</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>teacher/assignments.php"><i class="bi bi-journal-check"></i> Assignments</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>teacher/reports_against_me.php"><i class="bi bi-flag"></i> Reports Against Me</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>teacher/profile.php"><i class="bi bi-person"></i> Profile</a></li>
  </ul>
</div>
<div class="offcanvas offcanvas-start" id="mobileSidebar">
  <div class="offcanvas-header"><h5 class="offcanvas-title">Menu</h5>
    <button class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body p-0">
    <ul class="nav flex-column p-2">
      <li><a class="nav-link" href="<?= BASE_URL ?>teacher/dashboard.php">Dashboard</a></li>
      <li><a class="nav-link" href="<?= BASE_URL ?>teacher/submit_grades.php">Grades</a></li>
      <li><a class="nav-link" href="<?= BASE_URL ?>teacher/assignments.php">Assignments</a></li>
    </ul>
  </div>
</div>
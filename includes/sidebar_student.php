<div class="col-lg-2 d-none d-lg-block bg-light min-vh-100 p-0 border-end">
  <ul class="nav flex-column p-2 gap-3 sidebar">
    <li><a class="nav-link" href="<?= BASE_URL ?>student/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>student/grades.php"><i class="bi bi-clipboard-data"></i> My Grades</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>student/assignments.php"><i class="bi bi-journal-check"></i> Assignments</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>student/excuses.php"><i class="bi bi-file-earmark-text"></i> Submit Excuse</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>student/report_teacher.php"><i class="bi bi-flag"></i> Report Teacher</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>student/fees.php"><i class="bi bi-cash-coin"></i> My Fees</a></li>
    <li><a class="nav-link" href="<?= BASE_URL ?>student/profile.php"><i class="bi bi-person"></i> Profile</a></li>
  </ul>
</div>
<div class="offcanvas offcanvas-start" id="mobileSidebar">
  <div class="offcanvas-header"><h5 class="offcanvas-title">Menu</h5>
    <button class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body p-0">
    <ul class="nav flex-column p-2">
      <li><a class="nav-link" href="<?= BASE_URL ?>student/dashboard.php">Dashboard</a></li>
      <li><a class="nav-link" href="<?= BASE_URL ?>student/grades.php">My Grades</a></li>
      <li><a class="nav-link" href="<?= BASE_URL ?>student/excuses.php">Submit Excuse</a></li>
    </ul>
  </div>
</div>
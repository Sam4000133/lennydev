<?php
// Inizia la sessione
session_start();

// Controlla se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Qui possiamo essere sicuri che l'utente è autenticato
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? $username;
$role_id = $_SESSION['role_id'];
$permissions = isset($_SESSION['permissions']) ? $_SESSION['permissions'] : [];
?>
<!doctype html>
<html
  lang="it"
  class="layout-wide customizer-hide"
  dir="ltr"
  data-skin="default"
  data-assets-path="../../../assets/"
  data-template="vertical-menu-template"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Dashboard | Lenny Admin Panel</title>

    <meta name="description" content="Lenny Admin Panel Dashboard" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../../../assets/vendor/js/helpers.js"></script>
    <script src="../../../assets/vendor/js/template-customizer.js"></script>
    <script src="../../../assets/js/config.js"></script>
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <!-- Menu -->
        <?php include('sidebar.php'); ?>
        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->
          <nav
            class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
            id="layout-navbar">
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
              <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                <i class="icon-base ti tabler-menu-2"></i>
              </a>
            </div>

            <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
              <div class="navbar-nav align-items-center">
                <div class="nav-item d-flex align-items-center">
                  <h4 class="mb-0">Benvenuto, <?php echo htmlspecialchars($full_name); ?>!</h4>
                </div>
              </div>

              <ul class="navbar-nav flex-row align-items-center ms-auto">
                <!-- User -->
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                      <span class="avatar-initial rounded-circle bg-label-primary"><?php echo strtoupper(substr($full_name, 0, 1)); ?></span>
                    </div>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="#">
                        <div class="d-flex">
                          <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-online">
                              <span class="avatar-initial rounded-circle bg-label-primary"><?php echo strtoupper(substr($full_name, 0, 1)); ?></span>
                            </div>
                          </div>
                          <div class="flex-grow-1">
                            <span class="fw-medium d-block"><?php echo htmlspecialchars($full_name); ?></span>
                            <small class="text-muted">Role ID: <?php echo $role_id; ?></small>
                          </div>
                        </div>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a class="dropdown-item" href="profile.php">
                        <i class="icon-base ti tabler-user me-2"></i>
                        <span class="align-middle">My Profile</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="sicurezza.php">
                        <i class="icon-base ti tabler-settings me-2"></i>
                        <span class="align-middle">Settings</span>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a class="dropdown-item" href="logout.php">
                        <i class="icon-base ti tabler-logout me-2"></i>
                        <span class="align-middle">Log Out</span>
                      </a>
                    </li>
                  </ul>
                </li>
                <!--/ User -->
              </ul>
            </div>
          </nav>
          <!-- / Navbar -->

          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->
            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="row">
                <div class="col-12">
                  <div class="card">
                    <div class="card-header">
                      <h5 class="card-title">Dashboard</h5>
                    </div>
                    <div class="card-body">
                      <div class="alert alert-success mb-4">
                        <h4 class="alert-heading">Login Avvenuto con Successo!</h4>
                        <p>Sei connesso come <strong><?php echo htmlspecialchars($full_name); ?></strong> (<?php echo htmlspecialchars($username); ?>).</p>
                        <p>User ID: <?php echo $user_id; ?> | Role ID: <?php echo $role_id; ?></p>
                      </div>
                      
                      <h5>I tuoi permessi:</h5>
                      <?php if (empty($permissions)): ?>
                        <p>Nessun permesso trovato per il tuo ruolo.</p>
                      <?php else: ?>
                        <div class="table-responsive">
                          <table class="table table-bordered">
                            <thead>
                              <tr>
                                <th>Nome</th>
                                <th>Categoria</th>
                                <th>Lettura</th>
                                <th>Scrittura</th>
                                <th>Creazione</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($permissions as $name => $details): ?>
                                <tr>
                                  <td><?php echo htmlspecialchars($name); ?></td>
                                  <td><?php echo htmlspecialchars($details['category']); ?></td>
                                  <td><?php echo $details['can_read'] ? '✅' : '❌'; ?></td>
                                  <td><?php echo $details['can_write'] ? '✅' : '❌'; ?></td>
                                  <td><?php echo $details['can_create'] ? '✅' : '❌'; ?></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- / Content -->

            <!-- Footer -->
            <footer class="content-footer footer bg-footer-theme">
              <div class="container-xxl">
                <div class="footer-container d-flex align-items-center justify-content-between py-3 flex-md-row flex-column">
                  <div>
                    © <?php echo date('Y'); ?> Lenny Admin Panel
                  </div>
                </div>
              </div>
            </footer>
            <!-- / Footer -->
            <div class="content-backdrop fade"></div>
          </div>
          <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>

      <!-- Drag Target Area To SlideIn Menu On Small Screens -->
      <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <script src="../../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../../assets/vendor/libs/node-waves/node-waves.js"></script>
    <script src="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../../assets/vendor/js/menu.js"></script>

    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>
  </body>
</html>
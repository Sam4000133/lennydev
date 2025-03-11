<?php
// Abilita visualizzazione errori per debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db_connection.php';

// Check if db_setup.php has been run
$check_tables = $conn->query("SHOW TABLES LIKE 'roles'");
if ($check_tables->num_rows == 0) {
    require_once 'db_setup.php';
    echo "<script>console.log('Database setup completed successfully!');</script>";
}

// NON chiudere la connessione qui!
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Ruoli & permessi</title>
    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/@form-validation/form-validation.css" />

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
            <?php include 'sidebar.php'; ?>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <?php include 'navbar.php'; ?>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="mb-1">Lista dei ruoli</h4>
                        <p class="mb-6">
                            Un ruolo definisce quali menu e funzioni specifiche sono disponibili a un utente. <br />
                            In base al ruolo assegnato, ogni utente potrà accedere solo alle parti del sistema di cui ha effettivamente bisogno.
                        </p>
                        
                        <!-- Dynamic Role Cards Container -->
                        <div class="row g-6">
                            <!-- Add new role card (Template for JavaScript) -->
                            <div class="col-xl-4 col-lg-6 col-md-6">
                                <div class="card h-100">
                                    <div class="row h-100">
                                        <div class="col-sm-5">
                                            <div class="d-flex align-items-end h-100 justify-content-center mt-sm-0 mt-4">
                                                <img src="../../../assets/img/illustrations/add-new-roles.png" 
                                                     class="img-fluid" alt="Image" width="83" />
                                            </div>
                                        </div>
                                        <div class="col-sm-7">
                                            <div class="card-body text-sm-end text-center ps-sm-0">
                                                <button
    data-bs-target="#addRoleModal"
    data-bs-toggle="modal"
    class="btn btn-sm btn-primary mb-4 text-nowrap add-new-role w-100">
    Aggiungi nuovo ruolo
</button>
<a href="users.php" class="btn btn-sm btn-primary text-nowrap w-100">
    Lista Utenti
</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--/ Role cards -->

                        <!-- Add Role Modal -->
                        <div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-role">
                                <div class="modal-content">
                                    <div class="modal-body">
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        <div class="text-center mb-6">
                                            <h4 class="role-title">Aggiungi nuovo ruolo</h4>
                                            <p class="text-body-secondary">Imposta i permessi per il ruolo</p>
                                        </div>
                                        
                                        <!-- Add role form -->
                                        <form id="addRoleForm" class="row g-3">
                                            <div class="col-12 form-control-validation mb-3">
                                                <label class="form-label" for="modalRoleName">Nome Ruolo</label>
                                                <input type="text" id="modalRoleName" name="modalRoleName"
                                                    class="form-control" placeholder="Inserisci un nome per il ruolo" required />
                                            </div>
                                            <div class="col-12">
                                                <h5 class="mb-6">Permessi del Ruolo</h5>
                                                
                                                <!-- Permission table -->
                                                <div class="table-responsive">
                                                    <table class="table table-flush-spacing">
                                                        <tbody>
                                                            <tr>
                                                                <td class="text-nowrap fw-medium">
                                                                    Accesso Amministratore
                                                                    <i class="icon-base ti tabler-info-circle icon-xs"
                                                                       data-bs-toggle="tooltip" data-bs-placement="top"
                                                                       title="Abilita l'accesso all'intero sistema"></i>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex justify-content-end">
                                                                        <div class="form-check mb-0">
                                                                            <input class="form-check-input" type="checkbox" id="selectAll" />
                                                                            <label class="form-check-label" for="selectAll"> Seleziona Tutti </label>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                            $permissionsQuery = "SELECT DISTINCT category FROM permissions ORDER BY category";
                                                            $categories = $conn->query($permissionsQuery);
                                                            if ($categories) {
                                                                while ($category = $categories->fetch_assoc()) {
                                                                    $categoryName = $category['category'];
                                                                    $categoryId = preg_replace('/[^a-z0-9]/', '', strtolower($categoryName));
                                                                    echo '
                                                                    <tr>
                                                                        <td class="text-nowrap fw-medium text-heading">' . htmlspecialchars($categoryName) . '</td>
                                                                        <td>
                                                                            <div class="d-flex justify-content-end">
                                                                                <div class="form-check mb-0 me-4 me-lg-12">
                                                                                    <input class="form-check-input" type="checkbox" id="' . $categoryId . 'Read" />
                                                                                    <label class="form-check-label" for="' . $categoryId . 'Read"> Lettura </label>
                                                                                </div>
                                                                                <div class="form-check mb-0 me-4 me-lg-12">
                                                                                    <input class="form-check-input" type="checkbox" id="' . $categoryId . 'Write" />
                                                                                    <label class="form-check-label" for="' . $categoryId . 'Write"> Scrittura </label>
                                                                                </div>
                                                                                <div class="form-check mb-0">
                                                                                    <input class="form-check-input" type="checkbox" id="' . $categoryId . 'Create" />
                                                                                    <label class="form-check-label" for="' . $categoryId . 'Create"> Creazione </label>
                                                                                </div>
                                                                            </div>
                                                                        </td>
                                                                    </tr>';
                                                                }
                                                            }
                                                            ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <!--/ Permission table -->
                                            </div>
                                            <div class="col-12 text-center">
                                                <button type="submit" class="btn btn-primary me-sm-4 me-1">Salva</button>
                                                <button type="reset" class="btn btn-label-secondary"
                                                    data-bs-dismiss="modal" aria-label="Close">
                                                    Annulla
                                                </button>
                                            </div>
                                        </form>
                                        <!--/ Add role form -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--/ Add Role Modal -->
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                                <div class="text-body">
                                    © <script>document.write(new Date().getFullYear());</script>, made with ❤️ by 
                                    <a href="https://hydra-dev.xyz" target="_blank" class="footer-link">Hydra Dev</a>
                                </div>
                                <div class="d-none d-lg-inline-block"><a>Version 1.0.0 Alpha</a></div>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->
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

    <!-- Roles Manager JS -->
    <script src="roles_manager.js"></script>

</body>
</html>
<?php
$conn->close();
?>
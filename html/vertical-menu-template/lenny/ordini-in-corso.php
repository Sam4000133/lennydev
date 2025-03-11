<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Abilita log per debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug in ambiente di sviluppo
if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    error_log("=== ordini-in-corso.php ===");
    error_log("User ID: " . $_SESSION['user_id'] . ", Role ID: " . ($_SESSION['role_id'] ?? 'N/A'));
    
    if (isset($_SESSION['permissions'])&&is_array($_SESSION['permissions'])) {
        $permission_found = false;
        foreach ($_SESSION['permissions'] as $permName => $permDetails) {
            if (strcasecmp($permName, 'Ordini in corso') === 0) {
                $permission_found = true;
                error_log("Found permission '$permName': read=" . 
                         ($permDetails['can_read'] ? 'yes' : 'no') . ", write=" . 
                         ($permDetails['can_write'] ? 'yes' : 'no') . ", create=" . 
                         ($permDetails['can_create'] ? 'yes' : 'no'));
            }
        }
        if (!$permission_found) {
            error_log("'Ordini in corso' permission not found in session");
        }
    }
}

// Verifica l'accesso specifico a questa pagina
// Gli amministratori (role_id = 1) hanno sempre accesso
$hasAccess = ($_SESSION['role_id'] == 1);

if (!$hasAccess) {
    // Per gli altri ruoli, verifica i permessi specifici
    $permissions = $_SESSION['permissions'] ?? [];
    
    // Verifica diretta con case-sensitivity
    if (isset($permissions['Ordini in corso'])) {
        $perm = $permissions['Ordini in corso'];
        $hasAccess = ($perm['can_read'] || $perm['can_write'] || $perm['can_create']);
    } else {
        // Verifica case-insensitive
        foreach ($permissions as $permName => $permDetails) {
            if (strcasecmp($permName, 'Ordini in corso') === 0) {
                $hasAccess = ($permDetails['can_read'] || $permDetails['can_write'] || $permDetails['can_create']);
                break;
            }
        }
    }
}

// Se non ha accesso, reindirizza
if (!$hasAccess) {
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        error_log("Access denied to Ordini in corso for user " . $_SESSION['username']);
    }
    header("Location: access-denied.php");
    exit;
}

// Include database connection
require_once 'db_connection.php';
?>
<!doctype html>
<html lang="it" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Ordini In Corso | Lenny</title>
    <meta name="description" content="Gestione ordini in corso" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css" />

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
                        <?php 
                        // Mostra informazioni di debug in ambiente di sviluppo
                        if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])): 
                        ?>
                        <div class="alert alert-primary alert-dismissible d-flex align-items-center mb-4" role="alert">
                            <i class="ti tabler-info-circle me-2"></i>
                            <div>
                                Ambiente di sviluppo: Hai accesso a questa pagina come 
                                <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                                (Role ID: <?php echo $_SESSION['role_id']; ?>)
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <h4 class="fw-bold py-3 mb-4">
                            <i class="ti tabler-truck-delivery me-2"></i>
                            Ordini In Corso
                        </h4>
                        
                        <!-- Filtri -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Filtri</h5>
                                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse" aria-expanded="false" aria-controls="filtersCollapse">
                                    <i class="ti tabler-filter me-1"></i> Mostra/Nascondi
                                </button>
                            </div>
                            <div class="collapse" id="filtersCollapse">
                                <div class="card-body">
                                    <form id="filtersForm">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Data ordine</label>
                                                <div class="input-group">
                                                    <input type="date" class="form-control" placeholder="Da" id="dateFrom">
                                                    <span class="input-group-text">a</span>
                                                    <input type="date" class="form-control" placeholder="A" id="dateTo">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Stato ordine</label>
                                                <select class="form-select" id="orderStatus">
                                                    <option value="">Tutti</option>
                                                    <option value="new">Nuovi</option>
                                                    <option value="preparing">In preparazione</option>
                                                    <option value="ready">Pronti</option>
                                                    <option value="delivering">In consegna</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Tipo ordine</label>
                                                <select class="form-select" id="orderType">
                                                    <option value="">Tutti</option>
                                                    <option value="delivery">Consegna</option>
                                                    <option value="pickup">Ritiro</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary w-100">Applica</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tabella Ordini -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Ordini in corso</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary me-1">
                                        <i class="ti tabler-refresh"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="text-center py-5">
                                    <img src="../../../assets/img/illustrations/work-in-progress.png" 
                                         class="img-fluid mb-4" style="max-width: 250px;" alt="In costruzione">
                                    <h2 class="mb-3">Pagina in costruzione</h2>
                                    <p class="mb-3">
                                        La funzionalità di visualizzazione degli ordini in corso è in fase di sviluppo.
                                        Sarà disponibile nella prossima versione.
                                    </p>
                                    <button class="btn btn-primary" disabled>
                                        <i class="ti tabler-clock me-1"></i> Disponibile a breve
                                    </button>
                                </div>
                                
                                <!-- Table placeholder - sostituire con tabella reale in futuro -->
                                <div class="table-responsive d-none">
                                    <table class="table table-striped border-top" id="orderTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Cliente</th>
                                                <th>Data e Ora</th>
                                                <th>Totale</th>
                                                <th>Tipo</th>
                                                <th>Stato</th>
                                                <th>Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- I dati verranno caricati via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
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
    
    <!-- Vendors JS -->
    <script src="../../../assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
    
    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>
    
    <!-- Menu Activation Script -->
    <script src="../../../assets/js/menu_accordion.js"></script>
    
    <!-- Page JS -->
    <script>
    $(document).ready(function() {
        // Imposta la data odierna come valore predefinito per il campo "A"
        const today = new Date().toISOString().split('T')[0];
        $('#dateTo').val(today);
        
        // Imposta come data "Da" una settimana fa
        const lastWeek = new Date();
        lastWeek.setDate(lastWeek.getDate() - 7);
        $('#dateFrom').val(lastWeek.toISOString().split('T')[0]);
        
        // Form di filtro
        $('#filtersForm').on('submit', function(e) {
            e.preventDefault();
            // Qui andrà la logica per filtrare i dati
            alert('La funzionalità di filtro sarà disponibile nella prossima versione.');
        });
        
        // Questa parte verrà attivata quando la tabella sarà implementata
        /*
        // Inizializza DataTable
        const orderTable = $('#orderTable').DataTable({
            dom: '<"card-header d-flex justify-content-between align-items-center flex-wrap"<"mb-0"<"d-flex align-items-center"f>>>t<"d-flex justify-content-between mx-2 row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                search: '',
                searchPlaceholder: "Cerca ordini...",
                paginate: {
                    previous: '&nbsp;',
                    next: '&nbsp;'
                }
            },
            responsive: true,
            lengthChange: false,
            pageLength: 10
        });
        */
    });
    </script>
</body>
</html>
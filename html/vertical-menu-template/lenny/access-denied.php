<?php
// Inizia la sessione se non è già stata avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug information (only for development environment)
$is_development = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);
$referer = $_SERVER['HTTP_REFERER'] ?? 'Unknown';
$request_page = basename($referer);

// Includi la connessione al database per navbar.php se necessario
if (!isset($conn)&&file_exists('db_connection.php')) {
    require_once 'db_connection.php';
}
?>
<!doctype html>
<html lang="it" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Accesso Negato | Lenny</title>
    <meta name="description" content="Pagina di accesso negato" />

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

    <!-- Helpers -->
    <script src="../../../assets/vendor/js/helpers.js"></script>
    <script src="../../../assets/vendor/js/template-customizer.js"></script>
    <script src="../../../assets/js/config.js"></script>
    
    <style>
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            color: #ea5455;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        .debug-container {
            max-height: 400px;
            overflow-y: auto;
        }
        @media (max-width: 768px) {
            .error-code {
                font-size: 5rem;
            }
        }
    </style>
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
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <div class="row justify-content-center">
                                    <div class="col-lg-6">
                                        <div class="error-code mb-4">403</div>
                                        <h1 class="display-4 text-primary mb-2"><i class="ti tabler-lock me-2"></i>Accesso Negato</h1>
                                        <p class="mb-4 fs-4 text-muted">Non hai i permessi necessari per accedere a questa pagina.</p>
                                        
                                        <div class="d-flex justify-content-center gap-3">
                                            <a href="index.php" class="btn btn-primary">
                                                <i class="ti tabler-home me-1"></i> Torna alla Dashboard
                                            </a>
                                            <button class="btn btn-outline-primary" onclick="window.history.back()">
                                                <i class="ti tabler-arrow-left me-1"></i> Torna indietro
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($is_development): ?>
                                <!-- Debug Information - Solo in ambiente di sviluppo -->
                                <div class="row mt-5">
                                    <div class="col-12">
                                        <div class="alert alert-warning mb-0">
                                            <h5><i class="ti tabler-bug me-1"></i> Informazioni di Debug</h5>
                                            <p>Queste informazioni sono visibili solo in ambiente di sviluppo.</p>
                                            
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Informazioni Utente</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <table class="table table-sm mb-0">
                                                                <tr>
                                                                    <th>ID Utente</th>
                                                                    <td><?php echo $_SESSION['user_id'] ?? 'Non disponibile'; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Username</th>
                                                                    <td><?php echo $_SESSION['username'] ?? 'Non disponibile'; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Nome completo</th>
                                                                    <td><?php echo $_SESSION['full_name'] ?? 'Non disponibile'; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>ID Ruolo</th>
                                                                    <td><?php echo $_SESSION['role_id'] ?? 'Non disponibile'; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Pagina richiesta</th>
                                                                    <td><?php echo $request_page; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Referrer completo</th>
                                                                    <td><small><?php echo $referer; ?></small></td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header d-flex justify-content-between">
                                                            <h6 class="mb-0">Permessi</h6>
                                                            
                                                            <?php 
                                                            $permission_count = 0;
                                                            if (isset($_SESSION['permissions'])&&is_array($_SESSION['permissions'])) {
                                                                $permission_count = count($_SESSION['permissions']);
                                                            }
                                                            ?>
                                                            <span class="badge bg-label-info"><?php echo $permission_count; ?> trovati</span>
                                                        </div>
                                                        <div class="card-body debug-container p-0">
                                                            <?php if (isset($_SESSION['permissions'])&&!empty($_SESSION['permissions'])): ?>
                                                                <table class="table table-sm table-striped mb-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Permesso</th>
                                                                            <th class="text-center">Lettura</th>
                                                                            <th class="text-center">Scrittura</th>
                                                                            <th class="text-center">Creazione</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php 
                                                                        // Estrai il nome del permesso richiesto dal referer
                                                                        $requested_permission = '';
                                                                        if (strpos($request_page, 'ordini-in-corso') !== false) {
                                                                            $requested_permission = 'Ordini in corso';
                                                                        } elseif (strpos($request_page, 'cronologia-ordini') !== false) {
                                                                            $requested_permission = 'Cronologia ordini';
                                                                        }
                                                                        // e così via...
                                                                        
                                                                        foreach ($_SESSION['permissions'] as $name => $details): 
                                                                            $highlight = (strcasecmp($name, $requested_permission) === 0) ? 'class="table-warning"' : '';
                                                                        ?>
                                                                        <tr <?php echo $highlight; ?>>
                                                                            <td><?php echo htmlspecialchars($name); ?></td>
                                                                            <td class="text-center"><?php echo $details['can_read'] ? '<i class="ti tabler-check text-success"></i>' : '<i class="ti tabler-x text-danger"></i>'; ?></td>
                                                                            <td class="text-center"><?php echo $details['can_write'] ? '<i class="ti tabler-check text-success"></i>' : '<i class="ti tabler-x text-danger"></i>'; ?></td>
                                                                            <td class="text-center"><?php echo $details['can_create'] ? '<i class="ti tabler-check text-success"></i>' : '<i class="ti tabler-x text-danger"></i>'; ?></td>
                                                                        </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            <?php else: ?>
                                                                <div class="p-3 text-center">
                                                                    <i class="ti tabler-alert-triangle text-warning fs-1"></i>
                                                                    <p>Nessun permesso trovato nella sessione.</p>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Suggerimenti per risolvere -->
                                            <div class="mt-3">
                                                <h6>Suggerimenti:</h6>
                                                <ul class="mb-0 text-start">
                                                    <li>Verifica che l'utente abbia il permesso <strong><?php echo $requested_permission; ?></strong> con almeno un diritto (lettura, scrittura o creazione)</li>
                                                    <li>Controlla se ci sono problemi di case-sensitivity nel nome del permesso</li>
                                                    <li>Se sei un amministratore, controlla se il role_id è impostato correttamente (dovrebbe essere 1)</li>
                                                    <li>Prova a effettuare il logout e il login per aggiornare i permessi nella sessione</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
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

    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>
    
    <!-- Menu Accordion Script -->
    <script src="../../../assets/js/menu_accordion.js"></script>
</body>
</html>
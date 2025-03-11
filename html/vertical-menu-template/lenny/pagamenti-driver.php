<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Verifica l'accesso specifico a questa pagina
if (!userHasAccess('Ordiniincorso')) {
    // Reindirizza alla pagina di accesso negato
    header("Location: access-denied.php");
    exit;
}

// Resto del codice della pagina...
// Abilita visualizzazione errori per debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db_connection.php';

// NON chiudere la connessione qui!
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Ordini in corso</title>
    <meta name="description" content="Gestione pagamenti driver" />

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
    <!-- Aggiunti CSS per DataTables -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/sweetalert2/sweetalert2.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/dropzone/dropzone.css" />

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
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <img src="../../../assets/img/illustrations/construction.png" 
                                             alt="Work in Progress" class="img-fluid mb-4" style="max-width: 300px;">
                                        <h2 class="mb-3">Pagina in costruzione</h2>
                                        <p class="mb-4 text-muted">Stiamo lavorando alla realizzazione di questa funzionalità. Sarà disponibile a breve.</p>
                                        <div class="progress mb-4" style="height: 8px;">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                 role="progressbar" 
                                                 style="width: 70%" 
                                                 aria-valuenow="70" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <p class="text-muted">Completamento stimato: 70%</p>
                                        <div class="mt-4">
                                            <a href="index.php" class="btn btn-primary me-2">
                                                <i class="ti tabler-home me-1"></i> Torna alla Dashboard
                                            </a>
                                            <button class="btn btn-outline-primary">
                                                <i class="ti tabler-bell me-1"></i> Ricevi notifica al completamento
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sezione funzionalità previste -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title m-0">Funzionalità in arrivo</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-4">
                                            <div class="col-md-4">
                                                <div class="card shadow-none border h-100">
                                                    <div class="card-body text-center">
                                                        <i class="ti tabler-package text-primary" style="font-size: 2.5rem;"></i>
                                                        <h5 class="mt-3">Monitoraggio ordini in tempo reale</h5>
                                                        <p class="mb-0 text-muted small">Tracciamento completo dello stato degli ordini con notifiche automatiche.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card shadow-none border h-100">
                                                    <div class="card-body text-center">
                                                        <i class="ti tabler-truck-delivery text-primary" style="font-size: 2.5rem;"></i>
                                                        <h5 class="mt-3">Gestione spedizioni</h5>
                                                        <p class="mb-0 text-muted small">Integrazione con corrieri e generazione documenti di trasporto.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card shadow-none border h-100">
                                                    <div class="card-body text-center">
                                                        <i class="ti tabler-report-analytics text-primary" style="font-size: 2.5rem;"></i>
                                                        <h5 class="mt-3">Analisi e reportistica</h5>
                                                        <p class="mb-0 text-muted small">Dashboard con statistiche su tempi di evasione e performance.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
    <script src="../../../assets/vendor/libs/sweetalert2/sweetalert2.js"></script>

    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>

    <!-- Script personalizzato -->
    <script>
    $(document).ready(function() {
      'use strict';
      
      // Gestione pulsante notifica
      $('.btn-outline-primary').on('click', function() {
        Swal.fire({
          title: 'Notifica impostata',
          text: 'Riceverai una notifica quando questa funzionalità sarà disponibile.',
          icon: 'success',
          confirmButtonText: 'OK',
          customClass: {
            confirmButton: 'btn btn-primary'
          },
          buttonsStyling: false
        });
      });
      
      // Simulazione progresso
      let progress = 70;
      const progressBar = $('.progress-bar');
      const progressText = $('.text-muted:contains("Completamento")');
      
      // Incrementa leggermente il progresso ogni 10 secondi
      setInterval(function() {
        if (progress < 99) {
          progress += Math.random() * 0.5;
          if (progress > 99) progress = 99;
          
          const newProgress = Math.round(progress * 10) / 10;
          progressBar.css('width', newProgress + '%');
          progressBar.attr('aria-valuenow', newProgress);
          progressText.text('Completamento stimato: ' + newProgress + '%');
        }
      }, 10000);
    });
    </script>
    
    <!-- Menu Accordion Script -->
    <script src="../../../assets/js/menu_accordion.js"></script>
</body>
</html>
<?php
$conn->close();
?>
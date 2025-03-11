<?php
// Script per creare pagine PHP vuote basate sul menu

// Funzione per estrarre nomi di file dal menu HTML
function extractFilenames($menuHtml) {
    $pattern = '/href="([^"]+\.php)"/';
    preg_match_all($pattern, $menuHtml, $matches);
    
    $filenames = [];
    $menuStructure = [];
    
    // Array per memorizzare la struttura padre-figlio
    $currentParent = null;
    
    // Analizza il contenuto per estrarre la struttura del menu
    $lines = explode("\n", $menuHtml);
    foreach ($lines as $line) {
        // Controlla se è un elemento padre (menu-toggle)
        if (strpos($line, 'menu-toggle') !== false&&preg_match('/href="([^"]+)"/', $line, $parentMatch)) {
            if ($parentMatch[1] !== 'javascript:void(0);') {
                $currentParent = $parentMatch[1];
            } else {
                // Se il parent non ha un href diretto, cerca il data-i18n per il nome
                if (preg_match('/data-i18n="([^"]+)"/', $line, $nameMatch)) {
                    $currentParent = $nameMatch[1];
                } else {
                    $currentParent = null;
                }
            }
        }
        
        // Controlla se è un elemento figlio
        if (strpos($line, 'menu-link') !== false&&strpos($line, 'menu-toggle') === false&&preg_match('/href="([^"]+\.php)"/', $line, $childMatch)) {
            $filename = $childMatch[1];
            
            if (!in_array($filename, $filenames)&&$filename !== 'javascript:void(0);') {
                $filenames[] = $filename;
                
                // Salva anche la relazione padre-figlio
                if ($currentParent) {
                    $menuStructure[$filename] = ['parent' => $currentParent];
                    
                    // Estrai anche il nome del menu per questo elemento
                    if (preg_match('/data-i18n="([^"]+)"/', $line, $menuNameMatch)) {
                        $menuStructure[$filename]['name'] = $menuNameMatch[1];
                    }
                }
            }
        }
    }
    
    return ['files' => $filenames, 'structure' => $menuStructure];
}

// Funzione per generare un titolo da un nome di file
function generateTitle($filename) {
    // Rimuovi estensione file
    $name = pathinfo($filename, PATHINFO_FILENAME);
    
    // Sostituisci trattini con spazi
    $name = str_replace(['-', '_'], ' ', $name);
    
    // Capitalizza la prima lettera di ogni parola
    $name = ucwords($name);
    
    return $name;
}

// Ottieni lo script php per attivare il menu corrispondente
function getMenuActivationScript($filename, $menuStructure) {
    if (!isset($menuStructure[$filename])) {
        return "<!-- Nessuna struttura menu trovata per questa pagina -->";
    }
    
    $parent = $menuStructure[$filename]['parent'];
    $menuName = isset($menuStructure[$filename]['name']) ? $menuStructure[$filename]['name'] : null;
    
    $script = <<<PHP
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Attiva la voce di menu corrente
    const menuItems = document.querySelectorAll('.menu-item');
    
    menuItems.forEach(function(item) {
        // Controlla i link dei sotto-menu
        const menuLinks = item.querySelectorAll('.menu-link');
        menuLinks.forEach(function(link) {
            if (link.getAttribute('href') === '$filename') {
                // Attiva l'elemento corrente
                link.parentElement.classList.add('active');
                
                // Attiva anche il parent se esiste
                const parentMenu = link.closest('.menu-sub');
                if (parentMenu) {
                    const parentItem = parentMenu.parentElement;
                    parentItem.classList.add('open', 'active');
                }
            }
        });
    });
});
</script>
PHP;

    return $script;
}

// Template per le pagine vuote
function generatePageTemplate($filename, $menuStructure) {
    $title = generateTitle($filename);
    $menuScript = getMenuActivationScript($filename, $menuStructure);
    
    return <<<HTML
<?php
// Abilita visualizzazione errori per debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db_connection.php';
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>$title</title>
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
                        <h4 class="mb-4">$title</h4>
                        
                        <!-- Pagina in costruzione -->
                        <div class="card">
                            <div class="card-body">
                                <div class="text-center py-5">
                                    <h1 class="display-4 text-primary">$title</h1>
                                    <p class="mb-4">Questa pagina è in fase di sviluppo</p>
                                    <img src="../../../assets/img/illustrations/work-in-progress.png" 
                                         class="img-fluid w-50 mb-4" style="max-width: 300px;" alt="In costruzione">
                                </div>
                            </div>
                        </div>
                        <!-- / Pagina in costruzione -->
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
    
    <!-- Menu Activation Script -->
    $menuScript
</body>
</html>
HTML;
}

// Menu HTML da analizzare (fornito dall'utente)
$menuHtml = <<<'HTML'
<aside id="layout-menu" class="layout-menu menu-vertical menu">
  <div class="app-brand demo">
    <a href="index.php" class="app-brand-link">
      <span class="app-brand-logo demo">
        
      </span>
      <div class="d-flex align-items-center">
        <div class="rounded-circle bg-primary p-2 me-2">
          <i class="ti tabler-tools-kitchen-2 text-white"></i>
        </div>
        <span class="app-brand-text demo menu-text fw-bold">Lenny</span>
      </div>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
      <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
      <i class="icon-base ti tabler-x d-block d-xl-none"></i>
    </a>
  </div>

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    <!-- Dashboard -->
    <li class="menu-item">
      <a href="index.php" class="menu-link">
        <i class="menu-icon icon-base ti tabler-smart-home"></i>
        <div data-i18n="Panoramica">Panoramica</div>
      </a>
    </li>

    <!-- Gestione Ordini -->
    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-shopping-bag"></i>
        <div data-i18n="Gestione Ordini">Gestione Ordini</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="ordini-in-corso.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-truck-delivery"></i>
            <div data-i18n="Ordini in corso">Ordini in corso</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="cronologia-ordini.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-history"></i>
            <div data-i18n="Cronologia ordini">Cronologia ordini</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="resi-rimborsi.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-exchange"></i>
            <div data-i18n="Resi e rimborsi">Resi e rimborsi</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- Gestione Ristorante -->
    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-building-store"></i>
        <div data-i18n="Gestione Ristorante">Gestione Ristorante</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="informazioni-base.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-info-circle"></i>
            <div data-i18n="Informazioni Base">Informazioni Base</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="indirizzo-consegna.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-map-pin"></i>
            <div data-i18n="Indirizzo e Consegna">Indirizzo e Consegna</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="orari-apertura.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-clock"></i>
            <div data-i18n="Orari di Apertura">Orari di Apertura</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="menu.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-tools-kitchen"></i>
            <div data-i18n="Menu">Menu</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="dati-operativi.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-settings"></i>
            <div data-i18n="Dati Operativi">Dati Operativi</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="pagamenti.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-credit-card"></i>
            <div data-i18n="Pagamenti">Pagamenti</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="commissioni.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-percentage"></i>
            <div data-i18n="Commissioni">Commissioni</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="notifiche.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-bell"></i>
            <div data-i18n="Notifiche">Notifiche</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="integrazione-ia.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-robot"></i>
            <div data-i18n="Integrazione IA">Integrazione IA</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="documenti.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-file-text"></i>
            <div data-i18n="Documenti">Documenti</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="promozioni.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-discount"></i>
            <div data-i18n="Promozioni">Promozioni</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- Gestione Driver -->
    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-motorbike"></i>
        <div data-i18n="Gestione Driver">Gestione Driver</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="registrazione.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-user-plus"></i>
            <div data-i18n="Registrazione driver">Registrazione driver</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="assegnazione-ordini.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-clipboard-list"></i>
            <div data-i18n="Assegnazione ordini">Assegnazione ordini</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="tracking-gps.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-map"></i>
            <div data-i18n="Tracking GPS">Tracking GPS</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="pagamenti-driver.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-cash"></i>
            <div data-i18n="Pagamenti driver">Pagamenti driver</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- Analytics -->
    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-chart-bar"></i>
        <div data-i18n="Analytics">Analytics</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="report-vendite.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-report-money"></i>
            <div data-i18n="Report vendite">Report vendite</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="performance.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-chart-line"></i>
            <div data-i18n="Performance">Performance</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="statistiche-prodotti.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-chart-pie"></i>
            <div data-i18n="Statistiche prodotti">Statistiche prodotti</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- CRM -->
    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-users"></i>
        <div data-i18n="CRM">CRM</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="clienti.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-user-circle"></i>
            <div data-i18n="Clienti">Clienti</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="recensioni.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-star"></i>
            <div data-i18n="Recensioni">Recensioni</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="reclami.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-alert-triangle"></i>
            <div data-i18n="Reclami">Reclami</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- Marketplace -->
    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-building-store"></i>
        <div data-i18n="Marketplace">Marketplace</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="elenco-ristoranti.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-building-store"></i>
            <div data-i18n="Elenco ristoranti">Elenco ristoranti</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="filtri-ricerca.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-search"></i>
            <div data-i18n="Filtri e ricerca">Filtri e ricerca</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="categorie.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-category"></i>
            <div data-i18n="Categorie">Categorie</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- Comunicazioni -->
    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-message-circle"></i>
        <div data-i18n="Comunicazioni">Comunicazioni</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="email-automatiche.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-mail"></i>
            <div data-i18n="Email automatiche">Email automatiche</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="sms.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-device-mobile-message"></i>
            <div data-i18n="SMS">SMS</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="chat-supporto.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-message-dots"></i>
            <div data-i18n="Chat supporto">Chat supporto</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- Abbonamenti -->
    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-award"></i>
        <div data-i18n="Abbonamenti">Abbonamenti</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="piani-membership.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-crown"></i>
            <div data-i18n="Piani membership">Piani membership</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="fatturazione.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-receipt"></i>
            <div data-i18n="Fatturazione">Fatturazione</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- Impostazioni Sistema -->
    <li class="menu-item open active">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-settings-2"></i>
        <div data-i18n="Sistema">Sistema</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="impostazioni.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-settings"></i>
            <div data-i18n="Impostazioni">Impostazioni</div>
          </a>
        </li>
        <li class="menu-item active">
          <a href="ruoli-permessi.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-key"></i>
            <div data-i18n="Ruoli&permessi">Ruoli&permessi</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="integrazioni.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-plug"></i>
            <div data-i18n="Integrazioni">Integrazioni</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="sicurezza.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-shield-check"></i>
            <div data-i18n="Sicurezza">Sicurezza</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="privacy.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-user-shield"></i>
            <div data-i18n="Privacy">Privacy</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="backup.php" class="menu-link">
            <i class="menu-icon icon-base ti tabler-database"></i>
            <div data-i18n="Backup">Backup</div>
          </a>
        </li>
      </ul>
    </li>
  </ul>
</aside>
HTML;

// Estrai i nomi dei file dal menu e la struttura
$result = extractFilenames($menuHtml);
$filenames = $result['files'];
$menuStructure = $result['structure'];

// File da escludere (già esistenti)
$excludeFiles = ['navbar.php', 'sidebar.php', 'db_connection.php'];

// Filtra i nomi dei file escludendo quelli già esistenti
$filenames = array_filter($filenames, function($filename) use ($excludeFiles) {
    return !in_array($filename, $excludeFiles);
});

// Crea il log dell'operazione
$logFile = 'page_creation_log.txt';
file_put_contents($logFile, "Inizio creazione pagine: " . date('Y-m-d H:i:s') . "\n");

// Crea le pagine
$createdCount = 0;
$skippedCount = 0;

foreach ($filenames as $filename) {
    // Controlla se il file esiste già
    if (file_exists($filename)) {
        file_put_contents($logFile, "Saltato (esiste già): $filename\n", FILE_APPEND);
        $skippedCount++;
    } else {
        // Crea il file
        $content = generatePageTemplate($filename, $menuStructure);
        if (file_put_contents($filename, $content) !== false) {
            file_put_contents($logFile, "Creato: $filename\n", FILE_APPEND);
            $createdCount++;
        } else {
            file_put_contents($logFile, "ERRORE nella creazione: $filename\n", FILE_APPEND);
        }
    }
}

// Completa il log
file_put_contents($logFile, "Fine creazione pagine: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents($logFile, "Pagine create: $createdCount, saltate: $skippedCount\n", FILE_APPEND);

// Output finale
echo "Processo completato!\n";
echo "Pagine create: $createdCount\n";
echo "Pagine saltate (già esistenti): $skippedCount\n";
echo "Log salvato in: $logFile\n";
echo "\nElenco delle pagine create:\n";
foreach ($filenames as $filename) {
    if (!file_exists($filename) || in_array($filename, $excludeFiles)) continue;
    echo "- $filename\n";
}
?>
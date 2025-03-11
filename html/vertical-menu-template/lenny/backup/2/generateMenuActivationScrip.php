<?php
// Script per creare pagine PHP e aggiornare l'attivazione del menu

// Funzione per estrarre nomi di file e struttura del menu dal menu HTML
function extractMenuStructure($menuHtml) {
    $structure = [];
    $lines = explode("\n", $menuHtml);
    $currentParentName = null;
    $currentParentId = null;
    
    foreach ($lines as $line) {
        // Cerca un elemento padre (menu-toggle)
        if (strpos($line, 'menu-toggle') !== false) {
            if (preg_match('/data-i18n="([^"]+)"/', $line, $nameMatch)) {
                $currentParentName = $nameMatch[1];
                $currentParentId = sanitizeId($currentParentName);
            }
        }
        
        // Cerca un elemento figlio con un link a un file PHP
        if (strpos($line, '.php') !== false&&preg_match('/href="([^"]+\.php)"/', $line, $linkMatch)) {
            $filename = $linkMatch[1];
            
            // Estrai il nome dell'elemento dal data-i18n
            $itemName = '';
            if (preg_match('/data-i18n="([^"]+)"/', $line, $itemNameMatch)) {
                $itemName = $itemNameMatch[1];
            }
            
            // Aggiungi alla struttura
            $structure[$filename] = [
                'parent_name' => $currentParentName,
                'parent_id' => $currentParentId,
                'name' => $itemName
            ];
        }
    }
    
    return $structure;
}

// Funzione per sanitizzare un ID
function sanitizeId($text) {
    return preg_replace('/[^a-z0-9]/', '', strtolower($text));
}

// Funzione per generare un titolo da un nome di file
function generateTitle($filename, $menuStructure) {
    if (isset($menuStructure[$filename])&&!empty($menuStructure[$filename]['name'])) {
        return $menuStructure[$filename]['name'];
    }
    
    // Fallback: genera titolo dal nome del file
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = str_replace(['-', '_'], ' ', $name);
    return ucwords($name);
}

// Funzione per generare lo script di attivazione del menu aggiornato con comportamento a fisarmonica
function generateMenuActivationScript($filename, $menuStructure) {
    $parentName = isset($menuStructure[$filename]['parent_name']) ? $menuStructure[$filename]['parent_name'] : '';
    
    return <<<JAVASCRIPT

JAVASCRIPT;
}

// Funzione per generare il contenuto completo della pagina
function generatePageContent($filename, $menuStructure) {
    $title = generateTitle($filename, $menuStructure);
    $menuActivationScript = generateMenuActivationScript($filename, $menuStructure);
    
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
    $menuActivationScript
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
    <li class="menu-item">
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
        <li class="menu-item">
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

// Estrai la struttura del menu
$menuStructure = extractMenuStructure($menuHtml);

// File da escludere dalla creazione (navbar.php, sidebar.php, etc.)
$excludeFiles = ['navbar.php', 'sidebar.php', 'db_connection.php', 'users_api.php', 'menu_activator.php'];

// Array per tenere traccia dei file da creare
$filesToCreate = [];

// Aggiungi i file dal menu alla lista dei file da creare
foreach ($menuStructure as $filename => $data) {
    if (!in_array($filename, $excludeFiles)) {
        $filesToCreate[] = $filename;
    }
}

// Contatori
$createdCount = 0;
$updatedCount = 0;
$skippedCount = 0;

// Creazione/aggiornamento dei file
foreach ($filesToCreate as $filename) {
    $content = generatePageContent($filename, $menuStructure);
    
    // Controlla se il file esiste già
    if (file_exists($filename)) {
        // Per i file esistenti, aggiorna lo script di attivazione del menu
        $currentContent = file_get_contents($filename);
        
        // Trova e rimuovi eventuali script di attivazione del menu esistenti
        $updatedContent = preg_replace('/<script>\s*document\.addEventListener\(\'DOMContentLoaded\', function\(\) \{\s*.*?}<\/script>/s', '', $currentContent);
        
        // Genera il nuovo script
        $newScript = generateMenuActivationScript($filename, $menuStructure);
        
        // Inserisci il nuovo script prima di </body>
        $bodyPos = strrpos($updatedContent, '</body>');
        if ($bodyPos !== false) {
            $updatedContent = substr($updatedContent, 0, $bodyPos) . "\n    " . $newScript . "\n" . substr($updatedContent, $bodyPos);
            
            // Salva il file aggiornato
            if (file_put_contents($filename, $updatedContent)) {
                echo "Aggiornato: $filename (aggiornato script di attivazione del menu)\n";
                $updatedCount++;
            } else {
                echo "ERRORE nell'aggiornamento: $filename\n";
            }
        } else {
            echo "ERRORE: Impossibile trovare </body> in $filename\n";
            $skippedCount++;
        }
    } else {
        // Crea un nuovo file
        if (file_put_contents($filename, $content)) {
            echo "Creato: $filename\n";
            $createdCount++;
        } else {
            echo "ERRORE nella creazione: $filename\n";
        }
    }
}

// Output finale
echo "\nOperazione completata!\n";
echo "File creati: $createdCount\n";
echo "File aggiornati: $updatedCount\n";
echo "File saltati: $skippedCount\n";
?>
    <script src="../../../assets/js/menu_accordion.js"></script>

<?php
// sidebar.php - Menu dinamico basato sui permessi utente
// Assicuriamoci che l'utente sia autenticato
if (!isset($_SESSION['user_id'])) {
    return;
}

// Recupera i permessi dalla sessione
$permissions = isset($_SESSION['permissions']) ? $_SESSION['permissions'] : [];
$role_id = $_SESSION['role_id'] ?? 0;

// Debug - Stampare i permessi in console (solo in ambiente di sviluppo)
if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    echo '<script>console.log("Role ID:", ' . json_encode($role_id) . ');</script>';
    echo '<script>console.log("Permissions:", ' . json_encode($permissions) . ');</script>';
}

// Funzione per verificare se l'utente ha un determinato permesso
function hasMenuPermission($permissionName, $permissions, $role_id) {
    // Gli amministratori (role_id = 1) hanno tutti i permessi
    if ($role_id == 1) {
        return true;
    }
    
    // Se non è richiesto alcun permesso specifico, concedi l'accesso
    if ($permissionName === null) {
        return true;
    }
    
    // Controlla se l'utente ha il permesso specificato
    if (isset($permissions[$permissionName])) {
        return $permissions[$permissionName]['can_read'] || 
               $permissions[$permissionName]['can_write'] || 
               $permissions[$permissionName]['can_create'];
    }
    
    return false;
}

// Funzione per determinare se un menu è attivo
function isMenuItemActive($page, $currentPage = null) {
    if ($currentPage === null) {
        $currentPage = basename($_SERVER['SCRIPT_NAME']);
    }
    return basename($page) === $currentPage;
}

// Ottieni la pagina corrente
$currentPage = basename($_SERVER['SCRIPT_NAME']);

// Definisci la struttura del menu
$menu_structure = [
    // Dashboard - sempre visibile
    'index.php' => [
        'title' => 'Panoramica', 
        'icon' => 'tabler-smart-home', 
        'permission' => null,  // null significa sempre visibile
        'submenu' => []
    ],
    
    // Gestione Ordini
    'ordini' => [
        'title' => 'Gestione Ordini', 
        'icon' => 'tabler-shopping-bag', 
        'permission' => 'Gestione Ordini',
        'submenu' => [
            'ordini-in-corso.php' => ['title' => 'Ordini in corso', 'icon' => 'tabler-truck-delivery', 'permission' => 'Ordini in corso'],
            'cronologia-ordini.php' => ['title' => 'Cronologia ordini', 'icon' => 'tabler-history', 'permission' => 'Cronologia ordini'],
            'resi-rimborsi.php' => ['title' => 'Resi e rimborsi', 'icon' => 'tabler-exchange', 'permission' => 'Resi e rimborsi']
        ]
    ],
    
    // Gestione Ristorante
    'gestione_ristorante' => [
        'title' => 'Gestione Ristorante', 
        'icon' => 'tabler-building-store', 
        'permission' => 'Gestione Ristorante',
        'submenu' => [
            'informazioni-base.php' => ['title' => 'Informazioni Base', 'icon' => 'tabler-info-circle', 'permission' => 'Informazioni base'],
            'indirizzo-consegna.php' => ['title' => 'Indirizzo e Consegna', 'icon' => 'tabler-map-pin', 'permission' => 'Indirizzo e consegna'],
            'orari-apertura.php' => ['title' => 'Orari di Apertura', 'icon' => 'tabler-clock', 'permission' => 'Orari di apertura'],
            'menu.php' => ['title' => 'Menu', 'icon' => 'tabler-tools-kitchen', 'permission' => 'Menu'],
            'dati-operativi.php' => ['title' => 'Dati Operativi', 'icon' => 'tabler-settings', 'permission' => 'Dati operativi'],
            'pagamenti.php' => ['title' => 'Pagamenti', 'icon' => 'tabler-credit-card', 'permission' => 'Pagamenti'],
            'commissioni.php' => ['title' => 'Commissioni', 'icon' => 'tabler-percentage', 'permission' => 'Commissioni'],
            'notifiche.php' => ['title' => 'Notifiche', 'icon' => 'tabler-bell', 'permission' => 'Notifiche'],
            'integrazione-ia.php' => ['title' => 'Integrazione IA', 'icon' => 'tabler-robot', 'permission' => 'Integrazione IA'],
            'documenti.php' => ['title' => 'Documenti', 'icon' => 'tabler-file-text', 'permission' => 'Documenti'],
            'promozioni.php' => ['title' => 'Promozioni', 'icon' => 'tabler-discount', 'permission' => 'Promozioni']
        ]
    ],
    
    // Gestione Driver
    'gestione_driver' => [
        'title' => 'Gestione Driver', 
        'icon' => 'tabler-motorbike', 
        'permission' => 'Gestione Driver',
        'submenu' => [
            'registrazione.php' => ['title' => 'Registrazione driver', 'icon' => 'tabler-user-plus', 'permission' => 'Registrazione driver'],
            'assegnazione-ordini.php' => ['title' => 'Assegnazione ordini', 'icon' => 'tabler-clipboard-list', 'permission' => 'Assegnazione ordini'],
            'tracking-gps.php' => ['title' => 'Tracking GPS', 'icon' => 'tabler-map', 'permission' => 'Tracking GPS'],
            'pagamenti-driver.php' => ['title' => 'Pagamenti driver', 'icon' => 'tabler-cash', 'permission' => 'Pagamenti driver']
        ]
    ],
    
    // Analytics
    'analytics' => [
        'title' => 'Analytics', 
        'icon' => 'tabler-chart-bar', 
        'permission' => 'Analytics',
        'submenu' => [
            'report-vendite.php' => ['title' => 'Report vendite', 'icon' => 'tabler-report-money', 'permission' => 'Report vendite'],
            'performance.php' => ['title' => 'Performance', 'icon' => 'tabler-chart-line', 'permission' => 'Performance'],
            'statistiche-prodotti.php' => ['title' => 'Statistiche prodotti', 'icon' => 'tabler-chart-pie', 'permission' => 'Statistiche prodotti']
        ]
    ],
    
    // CRM
    'crm' => [
        'title' => 'CRM', 
        'icon' => 'tabler-users', 
        'permission' => 'CRM',
        'submenu' => [
            'clienti.php' => ['title' => 'Clienti', 'icon' => 'tabler-user-circle', 'permission' => 'Clienti'],
            'recensioni.php' => ['title' => 'Recensioni', 'icon' => 'tabler-star', 'permission' => 'Recensioni'],
            'reclami.php' => ['title' => 'Reclami', 'icon' => 'tabler-alert-triangle', 'permission' => 'Reclami']
        ]
    ],
    
    // Marketplace
    'marketplace' => [
        'title' => 'Marketplace', 
        'icon' => 'tabler-building-store', 
        'permission' => 'Marketplace',
        'submenu' => [
            'elenco-ristoranti.php' => ['title' => 'Elenco ristoranti', 'icon' => 'tabler-building-store', 'permission' => 'Elenco ristoranti'],
            'filtri-ricerca.php' => ['title' => 'Filtri e ricerca', 'icon' => 'tabler-search', 'permission' => 'Filtri e ricerca'],
            'categorie.php' => ['title' => 'Categorie', 'icon' => 'tabler-category', 'permission' => 'Categorie']
        ]
    ],
    
    // Comunicazioni
    'comunicazioni' => [
        'title' => 'Comunicazioni', 
        'icon' => 'tabler-message-circle', 
        'permission' => 'Comunicazioni',
        'submenu' => [
            'email-automatiche.php' => ['title' => 'Email automatiche', 'icon' => 'tabler-mail', 'permission' => 'Email automatiche'],
            'sms.php' => ['title' => 'SMS', 'icon' => 'tabler-device-mobile-message', 'permission' => 'SMS'],
            'chat-supporto.php' => ['title' => 'Chat supporto', 'icon' => 'tabler-message-dots', 'permission' => 'Chat supporto']
        ]
    ],
    
    // Abbonamenti
    'abbonamenti' => [
        'title' => 'Abbonamenti', 
        'icon' => 'tabler-award', 
        'permission' => 'Abbonamenti',
        'submenu' => [
            'piani-membership.php' => ['title' => 'Piani membership', 'icon' => 'tabler-crown', 'permission' => 'Piani membership'],
            'fatturazione.php' => ['title' => 'Fatturazione', 'icon' => 'tabler-receipt', 'permission' => 'Fatturazione']
        ]
    ],
    
    // Sistema
    'sistema' => [
        'title' => 'Sistema', 
        'icon' => 'tabler-settings-2', 
        'permission' => 'Sistema',
        'submenu' => [
            'impostazioni.php' => ['title' => 'Impostazioni', 'icon' => 'tabler-settings', 'permission' => 'Impostazioni'],
            'ruoli-permessi.php' => ['title' => 'Ruoli&permessi', 'icon' => 'tabler-key', 'permission' => 'Ruoli&permessi'],
            'integrazioni.php' => ['title' => 'Integrazioni', 'icon' => 'tabler-plug', 'permission' => 'Integrazioni'],
            'sicurezza.php' => ['title' => 'Sicurezza', 'icon' => 'tabler-shield-check', 'permission' => 'Sicurezza'],
            'privacy.php' => ['title' => 'Privacy', 'icon' => 'tabler-user-shield', 'permission' => 'Privacy'],
            'backup.php' => ['title' => 'Backup', 'icon' => 'tabler-database', 'permission' => 'Backup']
        ]
    ],
];
?>

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
    <?php
    // Itera attraverso la struttura del menu
    foreach ($menu_structure as $page => $details) {
        $title = $details['title'];
        $icon = $details['icon'];
        $permission = $details['permission'];
        $hasSubmenu = !empty($details['submenu']);
        
        // Controlla i permessi
        if (!hasMenuPermission($permission, $permissions, $role_id)) {
            continue; // Salta questa voce di menu
        }
        
        // Determina se questo menu è attivo
        $isActive = false;
        
        if ($hasSubmenu) {
            // Per menu con sottomenu, controlla se una delle sottopagine è attiva
            foreach ($details['submenu'] as $subPage => $subDetails) {
                if (isMenuItemActive($subPage, $currentPage)) {
                    $isActive = true;
                    break;
                }
            }
        } else {
            // Per menu senza sottomenu
            $isActive = isMenuItemActive($page, $currentPage);
        }
        
        // Menu con sottovoci
        if ($hasSubmenu) {
            // Verifica se almeno una sottovoce è accessibile
            $hasAccessibleSubmenu = false;
            foreach ($details['submenu'] as $subPage => $subDetails) {
                if (hasMenuPermission($subDetails['permission'], $permissions, $role_id)) {
                    $hasAccessibleSubmenu = true;
                    break;
                }
            }
            
            // Se nessuna sottovoce è accessibile, salta questo menu
            if (!$hasAccessibleSubmenu) {
                continue;
            }
            
            ?>
            <li class="menu-item <?php echo $isActive ? 'active open' : ''; ?>">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base ti <?php echo $icon; ?>"></i>
                    <div data-i18n="<?php echo $title; ?>"><?php echo $title; ?></div>
                </a>
                <ul class="menu-sub">
                    <?php foreach ($details['submenu'] as $subPage => $subDetails) {
                        // Controlla i permessi per la sottovoce
                        if (!hasMenuPermission($subDetails['permission'], $permissions, $role_id)) {
                            continue; // Salta questa sottovoce
                        }
                        
                        $isSubActive = isMenuItemActive($subPage, $currentPage);
                        $subIcon = $subDetails['icon'] ?? '';
                    ?>
                    <li class="menu-item <?php echo $isSubActive ? 'active' : ''; ?>">
                        <a href="<?php echo $subPage; ?>" class="menu-link">
                            <?php if (!empty($subIcon)): ?>
                            <i class="menu-icon icon-base ti <?php echo $subIcon; ?>"></i>
                            <?php endif; ?>
                            <div data-i18n="<?php echo $subDetails['title']; ?>"><?php echo $subDetails['title']; ?></div>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
            </li>
            <?php
        } 
        // Menu senza sottovoci
        else {
            ?>
            <li class="menu-item <?php echo $isActive ? 'active' : ''; ?>">
                <a href="<?php echo $page; ?>" class="menu-link">
                    <i class="menu-icon icon-base ti <?php echo $icon; ?>"></i>
                    <div data-i18n="<?php echo $title; ?>"><?php echo $title; ?></div>
                </a>
            </li>
            <?php
        }
    }
    ?>
    
    <!-- Menu utente sempre visibile -->
    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">Utente</span>
    </li>
    <li class="menu-item">
      <a href="profile.php" class="menu-link">
        <i class="menu-icon icon-base ti tabler-user"></i>
        <div data-i18n="Profilo">Profilo</div>
      </a>
    </li>
    <li class="menu-item">
      <a href="logout.php" class="menu-link">
        <i class="menu-icon icon-base ti tabler-logout"></i>
        <div data-i18n="Logout">Logout</div>
      </a>
    </li>
  </ul>
</aside>
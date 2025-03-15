<?php
// navbar.php - versione con connessione al database
// Assicuriamoci che la sessione sia avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definisci il percorso base per assets solo se non è già definito
if (!defined('BASE_ASSETS_PATH')) {
    define('BASE_ASSETS_PATH', '../../../');
}

// Ottieni i dati dell'utente dalla sessione
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Utente');
$user_role = isset($_SESSION['role_name']) ? $_SESSION['role_name'] : 'Utente';
$user_initial = strtoupper(substr($user_name, 0, 1));
$user_id = $_SESSION['user_id'] ?? 0;
$role_id = $_SESSION['role_id'] ?? 0;

// Imposta variabili predefinite per le notifiche
$notification_count = 0;
$notifications = [];

// Verifica se la connessione al database è già stata stabilita
if (!isset($conn) || $conn === null) {
    // Tenta di includere la connessione al database
    try {
        require_once 'db_connection.php';
        $db_connected = true;
    } catch (Exception $e) {
        $db_connected = false;
        error_log('Errore nella connessione al database nella navbar: ' . $e->getMessage());
    }
}

// Carica le notifiche solo se la connessione al database è disponibile
if (isset($conn)&&$conn&&$user_id > 0) {
    try {
        // Verifica se esiste una tabella di notifiche nel database
        $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
        
        if ($table_check&&$table_check->num_rows > 0) {
            // Ottieni il conteggio delle notifiche non lette
            $notification_query = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            
            if ($notification_query) {
                $notification_query->bind_param("i", $user_id);
                $notification_query->execute();
                $result = $notification_query->get_result();
                if ($row = $result->fetch_assoc()) {
                    $notification_count = $row['count'];
                }
                $notification_query->close();
                
                // Ottieni le ultime 5 notifiche
                if ($notification_count > 0) {
                    $recent_query = $conn->prepare("
                        SELECT id, title, message, created_at, is_read, type 
                        FROM notifications 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    
                    if ($recent_query) {
                        $recent_query->bind_param("i", $user_id);
                        $recent_query->execute();
                        $result = $recent_query->get_result();
                        
                        while ($row = $result->fetch_assoc()) {
                            $notifications[] = $row;
                        }
                        
                        $recent_query->close();
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Errore nel caricamento delle notifiche: ' . $e->getMessage());
        // Continua con la navbar senza notifiche
    }
}

// Ottieni l'avatar dell'utente se disponibile dal database
$user_avatar = null;
if (isset($conn)&&$conn&&$user_id > 0) {
    try {
        $avatar_query = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
        if ($avatar_query) {
            $avatar_query->bind_param("i", $user_id);
            $avatar_query->execute();
            $result = $avatar_query->get_result();
            if ($row = $result->fetch_assoc()) {
                $user_avatar = $row['avatar'];
            }
            $avatar_query->close();
        }
    } catch (Exception $e) {
        // Ignora errori nell'ottenere l'avatar
    }
}

// Funzione per formattare la data relativa
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'anno', 'm' => 'mese', 'w' => 'settimana',
        'd' => 'giorno', 'h' => 'ora', 'i' => 'minuto',
        's' => 'secondo',
    );
    
    foreach ($string as $k =>&$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 'i' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' fa' : 'proprio ora';
}

// Imposta un avatar predefinito basato sull'ID utente
$avatar_id = $user_id % 14 + 1;
$avatar_path = BASE_ASSETS_PATH . "assets/img/avatars/{$avatar_id}.png";
?>

<nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
      <i class="icon-base ti tabler-menu-2 icon-md"></i>
    </a>
  </div>

  <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
    <!-- Search -->
    <div class="navbar-nav align-items-center">
      <div class="nav-item navbar-search-wrapper px-md-0 px-2 mb-0">
        <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
          <span class="d-inline-block text-body-secondary fw-normal" id="autocomplete"></span>
        </a>
      </div>
    </div>
    <!-- /Search -->

    <ul class="navbar-nav flex-row align-items-center ms-md-auto">
      <!-- Style Switcher -->
      <li class="nav-item dropdown">
        <a
          class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
          id="nav-theme"
          href="javascript:void(0);"
          data-bs-toggle="dropdown">
          <i class="icon-base ti tabler-sun icon-22px theme-icon-active text-heading"></i>
          <span class="d-none ms-2" id="nav-theme-text">Toggle theme</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text">
          <li>
            <button
              type="button"
              class="dropdown-item align-items-center active"
              data-bs-theme-value="light"
              aria-pressed="false">
              <span><i class="icon-base ti tabler-sun icon-22px me-3" data-icon="sun"></i>Light</span>
            </button>
          </li>
          <li>
            <button
              type="button"
              class="dropdown-item align-items-center"
              data-bs-theme-value="dark"
              aria-pressed="true">
              <span><i class="icon-base ti tabler-moon-stars icon-22px me-3" data-icon="moon-stars"></i>Dark</span>
            </button>
          </li>
          <li>
            <button
              type="button"
              class="dropdown-item align-items-center"
              data-bs-theme-value="system"
              aria-pressed="false">
              <span><i class="icon-base ti tabler-device-desktop-analytics icon-22px me-3" data-icon="device-desktop-analytics"></i>System</span>
            </button>
          </li>
        </ul>
      </li>
      <!-- / Style Switcher-->

      <!-- Quick links (solo se collegati al database) -->
      <?php if (isset($db_connected)&&$db_connected): ?>
      <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown">
        <a
          class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
          href="javascript:void(0);"
          data-bs-toggle="dropdown"
          data-bs-auto-close="outside"
          aria-expanded="false">
          <i class="icon-base ti tabler-layout-grid-add icon-22px text-heading"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end p-0">
          <div class="dropdown-menu-header border-bottom">
            <div class="dropdown-header d-flex align-items-center py-3">
              <h6 class="mb-0 me-auto">Collegamenti rapidi</h6>
            </div>
          </div>
          <div class="dropdown-shortcuts-list scrollable-container">
            <div class="row row-bordered overflow-visible g-0">
              <div class="dropdown-shortcuts-item col">
                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                  <i class="icon-base ti tabler-smart-home icon-26px text-heading"></i>
                </span>
                <a href="index.php" class="stretched-link">Dashboard</a>
                <small>Panoramica</small>
              </div>
              <div class="dropdown-shortcuts-item col">
                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                  <i class="icon-base ti tabler-shopping-bag icon-26px text-heading"></i>
                </span>
                <a href="ordini-in-corso.php" class="stretched-link">Ordini</a>
                <small>Gestione Ordini</small>
              </div>
            </div>
            <div class="row row-bordered overflow-visible g-0">
              <div class="dropdown-shortcuts-item col">
                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                  <i class="icon-base ti tabler-tools-kitchen icon-26px text-heading"></i>
                </span>
                <a href="menu.php" class="stretched-link">Menu</a>
                <small>Gestione Menu</small>
              </div>
              <div class="dropdown-shortcuts-item col">
                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                  <i class="icon-base ti tabler-settings icon-26px text-heading"></i>
                </span>
                <a href="impostazioni.php" class="stretched-link">Impostazioni</a>
                <small>Configurazione</small>
              </div>
            </div>
          </div>
        </div>
      </li>
      <?php endif; ?>
      <!-- Quick links -->

      <!-- Notification -->
      <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-2">
        <a
          class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
          href="javascript:void(0);"
          data-bs-toggle="dropdown"
          data-bs-auto-close="outside"
          aria-expanded="false">
          <span class="position-relative">
            <i class="icon-base ti tabler-bell icon-22px text-heading"></i>
            <?php if ($notification_count > 0): ?>
            <span class="badge rounded-pill bg-danger badge-dot badge-notifications border"></span>
            <?php endif; ?>
          </span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end p-0">
          <li class="dropdown-menu-header border-bottom">
            <div class="dropdown-header d-flex align-items-center py-3">
              <h6 class="mb-0 me-auto">Notifiche</h6>
              <div class="d-flex align-items-center h6 mb-0">
                <?php if ($notification_count > 0): ?>
                <span class="badge bg-label-primary me-2"><?php echo $notification_count; ?> nuove</span>
                <?php endif; ?>
                <a
                  href="javascript:void(0)"
                  class="dropdown-notifications-all p-2 btn btn-icon"
                  data-bs-toggle="tooltip"
                  data-bs-placement="top"
                  title="Segna tutte come lette"><i class="icon-base ti tabler-mail-opened text-heading"></i>
                </a>
              </div>
            </div>
          </li>
          <li class="dropdown-notifications-list scrollable-container">
            <ul class="list-group list-group-flush">
              <?php if (empty($notifications)): ?>
              <li class="list-group-item list-group-item-action dropdown-notifications-item text-center py-4">
                <div class="text-body-secondary">Non ci sono notifiche da visualizzare</div>
              </li>
              <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <li class="list-group-item list-group-item-action dropdown-notifications-item<?php echo $notification['is_read'] ? ' marked-as-read' : ''; ?>">
                  <div class="d-flex">
                    <div class="flex-shrink-0 me-3">
                      <div class="avatar">
                        <?php
                        // Determina l'icona in base al tipo di notifica
                        $icon_class = 'tabler-info-circle';
                        $bg_class = 'bg-label-primary';
                        
                        switch($notification['type']) {
                            case 'order':
                                $icon_class = 'tabler-shopping-cart';
                                $bg_class = 'bg-label-success';
                                break;
                            case 'message':
                                $icon_class = 'tabler-message';
                                $bg_class = 'bg-label-info';
                                break;
                            case 'alert':
                                $icon_class = 'tabler-alert-triangle';
                                $bg_class = 'bg-label-warning';
                                break;
                            case 'error':
                                $icon_class = 'tabler-alert-circle';
                                $bg_class = 'bg-label-danger';
                                break;
                        }
                        ?>
                        <span class="avatar-initial rounded-circle <?php echo $bg_class; ?>">
                          <i class="icon-base ti <?php echo $icon_class; ?>"></i>
                        </span>
                      </div>
                    </div>
                    <div class="flex-grow-1">
                      <h6 class="small mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                      <small class="mb-1 d-block text-body"><?php echo htmlspecialchars($notification['message']); ?></small>
                      <small class="text-body-secondary"><?php echo time_elapsed_string($notification['created_at']); ?></small>
                    </div>
                    <div class="flex-shrink-0 dropdown-notifications-actions">
                      <a href="javascript:void(0)" class="dropdown-notifications-read"><span class="badge badge-dot"></span></a>
                      <a href="javascript:void(0)" class="dropdown-notifications-archive"><span class="icon-base ti tabler-x"></span></a>
                    </div>
                  </div>
                </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </li>
          <li class="border-top">
            <div class="d-grid p-4">
              <a class="btn btn-primary btn-sm d-flex" href="notifiche.php">
                <small class="align-middle">Vedi tutte le notifiche</small>
              </a>
            </div>
          </li>
        </ul>
      </li>
      <!--/ Notification -->

      <!-- User -->
      <li class="nav-item navbar-dropdown dropdown-user dropdown">
        <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
          <div class="avatar avatar-online">
            <?php if (!empty($user_avatar)): ?>
              <img src="<?php echo BASE_ASSETS_PATH . htmlspecialchars($user_avatar); ?>" alt="<?php echo htmlspecialchars($user_name); ?>" class="rounded-circle" />
            <?php elseif (file_exists($avatar_path)): ?>
              <img src="<?php echo $avatar_path; ?>" alt="<?php echo htmlspecialchars($user_name); ?>" class="rounded-circle" />
            <?php else: ?>
              <span class="avatar-initial rounded-circle bg-label-primary"><?php echo $user_initial; ?></span>
            <?php endif; ?>
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <a class="dropdown-item mt-0" href="profile.php">
              <div class="d-flex align-items-center">
                <div class="flex-shrink-0 me-2">
                  <div class="avatar avatar-online">
                    <?php if (!empty($user_avatar)): ?>
                      <img src="<?php echo BASE_ASSETS_PATH . htmlspecialchars($user_avatar); ?>" alt="<?php echo htmlspecialchars($user_name); ?>" class="rounded-circle" />
                    <?php elseif (file_exists($avatar_path)): ?>
                      <img src="<?php echo $avatar_path; ?>" alt="<?php echo htmlspecialchars($user_name); ?>" class="rounded-circle" />
                    <?php else: ?>
                      <span class="avatar-initial rounded-circle bg-label-primary"><?php echo $user_initial; ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="flex-grow-1">
                  <h6 class="mb-0"><?php echo htmlspecialchars($user_name); ?></h6>
                  <small class="text-body-secondary"><?php echo htmlspecialchars($user_role); ?></small>
                </div>
              </div>
            </a>
          </li>
          <li>
            <div class="dropdown-divider my-1 mx-n2"></div>
          </li>
          <li>
            <a class="dropdown-item" href="profile.php">
              <i class="icon-base ti tabler-user me-3 icon-md"></i>
              <span class="align-middle">Profilo</span>
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="sicurezza.php">
              <i class="icon-base ti tabler-settings me-3 icon-md"></i>
              <span class="align-middle">Impostazioni</span>
            </a>
          </li>
          <?php if ($role_id == 1): ?>
          <li>
            <a class="dropdown-item" href="ruoli-permessi.php">
              <i class="icon-base ti tabler-key me-3 icon-md"></i>
              <span class="align-middle">Ruoli e Permessi</span>
            </a>
          </li>
          <?php endif; ?>
          <li>
            <div class="dropdown-divider my-1 mx-n2"></div>
          </li>
          <li>
            <div class="d-grid px-2 pt-2 pb-1">
              <a class="btn btn-sm btn-danger d-flex" href="logout.php">
                <small class="align-middle">Logout</small>
                <i class="icon-base ti tabler-logout ms-2 icon-14px"></i>
              </a>
            </div>
          </li>
        </ul>
      </li>
      <!--/ User -->
    </ul>
  </div>
</nav>
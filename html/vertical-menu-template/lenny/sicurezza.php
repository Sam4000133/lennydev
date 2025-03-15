<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Verifica l'accesso specifico a questa pagina
if (!userHasAccess('Sicurezza')) {
    // Reindirizza alla pagina di accesso negato
    header("Location: access-denied.php");
    exit;
}

// Ottieni i permessi specifici dell'utente per questa funzionalità
$canRead = userHasPermission('Sicurezza', 'read');
$canWrite = userHasPermission('Sicurezza', 'write');
$canCreate = userHasPermission('Sicurezza', 'create');

// Se l'utente non ha nemmeno i permessi di lettura, reindirizza
if (!$canRead) {
    header("Location: access-denied.php");
    exit;
}

// Percorso certificato SSL per le richieste cURL
$CERT_PATH = realpath(dirname(__FILE__) . '/../../../cacert.pem');

// Abilita visualizzazione errori per debug in ambiente di sviluppo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db_connection.php';

// Funzione per verificare i permessi specifici dell'utente
function userHasPermission($permissionName, $action = 'read') {
    global $conn;
    
    // Ottieni l'ID del ruolo dalla sessione
    $roleId = $_SESSION['role_id'];
    
    // Traduci l'azione in colonna del database
    $column = 'can_read';
    if ($action === 'write') {
        $column = 'can_write';
    } elseif ($action === 'create') {
        $column = 'can_create';
    }
    
    // Query per verificare il permesso
    $query = "
        SELECT rp.$column
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.name = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $roleId, $permissionName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (bool)$row[$column];
    }
    
    return false;
}

// Funzione per registrare un'azione nei log di sistema
function logSystemAction($level, $message, $userId = null, $ipAddress = null) {
    global $conn;
    
    // Usa l'ID utente dalla sessione se non specificato
    if ($userId === null&&isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    // Usa l'IP remoto se non specificato
    if ($ipAddress === null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    }
    
    $query = "INSERT INTO system_logs (level, message, user_id, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssis", $level, $message, $userId, $ipAddress);
    return $stmt->execute();
}

// Funzione per ottenere le impostazioni di sicurezza dal database
function getSecuritySettings() {
    global $conn;
    
    $query = "SELECT * FROM system_settings WHERE section = 'security'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row;
    }
    
    return $settings;
}

// Funzione per aggiornare una singola impostazione di sicurezza
function updateSecuritySetting($key, $value) {
    global $conn;
    
    // Controlla prima se l'impostazione esiste
    $check = "SELECT COUNT(*) AS count FROM system_settings WHERE setting_key = ? AND section = 'security'";
    $stmt = $conn->prepare($check);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        // Aggiorna l'impostazione esistente
        $query = "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ? AND section = 'security'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $value, $key);
    } else {
        // Crea una nuova impostazione
        $query = "INSERT INTO system_settings (section, setting_key, setting_value, created_at, updated_at) VALUES ('security', ?, ?, NOW(), NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $key, $value);
    }
    
    return $stmt->execute();
}

// Funzione per ottenere i log di sistema recenti
function getRecentSystemLogs($limit = 10, $level = null) {
    global $conn;
    
    $query = "SELECT * FROM system_logs";
    if ($level) {
        $query .= " WHERE level = ?";
    }
    $query .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($query);
    
    if ($level) {
        $stmt->bind_param("si", $level, $limit);
    } else {
        $stmt->bind_param("i", $limit);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

// Funzione per ottenere gli utenti con stato specifico
function getUsersByStatus($status, $limit = 10) {
    global $conn;
    
    $query = "SELECT u.*, r.name as role_name 
              FROM users u 
              LEFT JOIN roles r ON u.role_id = r.id 
              WHERE u.status = ? 
              ORDER BY u.updated_at DESC 
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}

// Funzione per ottenere informazioni su uno specifico utente
function getUserInfo($userId) {
    global $conn;
    
    $query = "SELECT u.*, r.name as role_name 
              FROM users u 
              LEFT JOIN roles r ON u.role_id = r.id 
              WHERE u.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Funzione per sbloccare o riattivare un utente
function updateUserStatus($userId, $status = 'active') {
    global $conn;
    
    $query = "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $userId);
    
    if ($stmt->execute()) {
        $user = getUserInfo($userId);
        $username = $user ? $user['username'] : "ID $userId";
        logSystemAction('info', "Stato utente $username aggiornato a: $status");
        return true;
    }
    
    return false;
}

// Funzione per ottenere gli utenti con password scaduta o in scadenza
function getUsersWithPasswordStatus($expired = true, $limit = 10) {
    global $conn;
    
    $daysThreshold = 7; // Considera "in scadenza" se mancano meno di 7 giorni
    
    if ($expired) {
        // Utenti con password già scaduta
        $query = "SELECT u.*, r.name as role_name 
                  FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE u.password_expires = 1 
                  AND DATE_ADD(u.password_changed_at, INTERVAL 
                    (SELECT setting_value FROM system_settings WHERE setting_key = 'password_expiry' AND section = 'security') DAY) < NOW()
                  ORDER BY u.password_changed_at ASC 
                  LIMIT ?";
    } else {
        // Utenti con password in scadenza nei prossimi giorni
        $query = "SELECT u.*, r.name as role_name, 
                  DATEDIFF(DATE_ADD(u.password_changed_at, INTERVAL 
                    (SELECT setting_value FROM system_settings WHERE setting_key = 'password_expiry' AND section = 'security') DAY), NOW()) as days_remaining
                  FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE u.password_expires = 1 
                  AND DATEDIFF(DATE_ADD(u.password_changed_at, INTERVAL 
                    (SELECT setting_value FROM system_settings WHERE setting_key = 'password_expiry' AND section = 'security') DAY), NOW()) BETWEEN 0 AND ?
                  ORDER BY days_remaining ASC 
                  LIMIT ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($expired) {
        $stmt->bind_param("i", $limit);
    } else {
        $stmt->bind_param("ii", $daysThreshold, $limit);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

// Gestione degli aggiornamenti delle impostazioni di sicurezza
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['action'])) {
    // Verifica i permessi (eccetto per refresh_logs che richiede solo canRead)
    if (!$canWrite) {
        echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per modificare le impostazioni']);
        exit;
    }
    
    $action = $_POST['action'];
    
    // Aggiorna impostazioni di sicurezza
    if ($action === 'update_security_settings'&&isset($_POST['settings'])) {
        $settings = $_POST['settings'];
        $success = true;
        $message = 'Impostazioni di sicurezza aggiornate con successo';
        
        foreach ($settings as $key => $value) {
            if (!updateSecuritySetting($key, $value)) {
                $success = false;
                $message = 'Errore durante l\'aggiornamento delle impostazioni';
                break;
            }
        }
        
        // Registra l'operazione nei log
        $level = $success ? 'info' : 'error';
        $logMessage = $success ? 'Impostazioni di sicurezza aggiornate' : 'Errore aggiornamento impostazioni di sicurezza';
        logSystemAction($level, $logMessage);
        
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    
    // Sblocca o riattiva un utente
    if ($action === 'update_user_status'&&isset($_POST['user_id'])&&isset($_POST['status'])) {
        $userId = intval($_POST['user_id']);
        $status = $_POST['status'];
        
        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            echo json_encode(['success' => false, 'message' => 'Stato non valido']);
            exit;
        }
        
        if (updateUserStatus($userId, $status)) {
            echo json_encode(['success' => true, 'message' => 'Stato utente aggiornato con successo']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento dello stato utente']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta']);
    exit;
}

// Carica le impostazioni di sicurezza
$securitySettings = getSecuritySettings();

// Ottieni utenti bloccati o sospesi
$suspendedUsers = getUsersByStatus('suspended', 5);
$inactiveUsers = getUsersByStatus('inactive', 5);

// Ottieni log recenti
$errorLogs = getRecentSystemLogs(5, 'error');
$warningLogs = getRecentSystemLogs(5, 'warning');
$infoLogs = getRecentSystemLogs(5, 'info');

// Ottieni informazioni sulla scadenza password
$expiredPasswordUsers = getUsersWithPasswordStatus(true, 5);
$expiringPasswordUsers = getUsersWithPasswordStatus(false, 5);
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Sicurezza</title>
    <meta name="description" content="Gestione delle impostazioni di sicurezza del sistema" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/sweetalert2/sweetalert2.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />

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
                        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Sistema /</span> Sicurezza</h4>
                        
                        <!-- Flash Messages per operazioni CRUD -->
                        <div id="alert-container"></div>
                        
                        <!-- Stats Cards -->
                        <div class="row g-6 mb-6">
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Utenti Bloccati</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $suspendedUsers->num_rows + $inactiveUsers->num_rows; ?></h4>
                                                </div>
                                                <small class="mb-0">Utenti inattivi o sospesi</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-danger">
                                                    <i class="icon-base ti tabler-lock-exclamation icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">2FA</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo ($securitySettings['two_factor_auth']['setting_value'] ?? '1') == '1' ? 'Attiva' : 'Disattiva'; ?></h4>
                                                </div>
                                                <small class="mb-0">Autenticazione a due fattori</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-success">
                                                    <i class="icon-base ti tabler-shield-check icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Password</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $expiredPasswordUsers->num_rows; ?></h4>
                                                </div>
                                                <small class="mb-0">Password scadute</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-warning">
                                                    <i class="icon-base ti tabler-key icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Errori</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $errorLogs->num_rows; ?></h4>
                                                </div>
                                                <small class="mb-0">Errori di sistema recenti</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-info">
                                                    <i class="icon-base ti tabler-alert-triangle icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- / Stats Cards -->
                        
                        <!-- Tabs di configurazione sicurezza -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Impostazioni di Sicurezza</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="nav nav-tabs" role="tablist">
                                            <li class="nav-item">
                                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                                                    <i class="icon-base ti tabler-shield me-1"></i> Generale
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-controls="password" aria-selected="false">
                                                    <i class="icon-base ti tabler-key me-1"></i> Password
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link" id="2fa-tab" data-bs-toggle="tab" data-bs-target="#twoFactor" type="button" role="tab" aria-controls="twoFactor" aria-selected="false">
                                                    <i class="icon-base ti tabler-device-mobile me-1"></i> 2FA
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab" aria-controls="logs" aria-selected="false">
                                                    <i class="icon-base ti tabler-activity me-1"></i> Attività
                                                </button>
                                            </li>
                                        </ul>
                                        <div class="tab-content">
                                            <!-- TAB GENERALE -->
                                            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                                                <div class="p-4">
                                                    <form id="generalSecurityForm" class="security-form">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Blocco Account</label>
                                                                <div class="form-check form-switch mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="accountLocking" name="account_locking" <?php echo ($securitySettings['account_locking']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="accountLocking">Abilita blocco account</label>
                                                                </div>
                                                                <small class="text-muted">Blocca gli account dopo un certo numero di tentativi di accesso falliti.</small>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Two-Factor Authentication</label>
                                                                <div class="form-check form-switch mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="twoFactorAuth" name="two_factor_auth" <?php echo ($securitySettings['two_factor_auth']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="twoFactorAuth">Abilita 2FA per gli amministratori</label>
                                                                </div>
                                                                <small class="text-muted">Richiedi l'autenticazione a due fattori per tutti gli utenti con privilegi amministrativi.</small>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="maxLoginAttempts">Tentativi di Accesso Massimi</label>
                                                                <input type="number" class="form-control" id="maxLoginAttempts" name="max_login_attempts" value="<?php echo htmlspecialchars($securitySettings['max_login_attempts']['setting_value'] ?? '5'); ?>" min="1" max="10" />
                                                                <small class="text-muted">Numero di tentativi falliti prima del blocco dell'account.</small>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="lockoutTime">Tempo di Blocco (minuti)</label>
                                                                <input type="number" class="form-control" id="lockoutTime" name="lockout_time" value="<?php echo htmlspecialchars($securitySettings['lockout_time']['setting_value'] ?? '30'); ?>" min="5" max="1440" />
                                                                <small class="text-muted">Durata del blocco dell'account dopo i tentativi falliti.</small>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="sessionTimeout">Timeout Sessione (minuti)</label>
                                                                <input type="number" class="form-control" id="sessionTimeout" name="session_timeout" value="<?php echo htmlspecialchars($securitySettings['session_timeout']['setting_value'] ?? '30'); ?>" min="5" max="240" />
                                                                <small class="text-muted">Tempo di inattività dopo il quale la sessione scade.</small>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Limitazione IP</label>
                                                                <div class="form-check form-switch mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="ipRestriction" name="ip_restriction" <?php echo ($securitySettings['ip_restriction']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="ipRestriction">Abilita restrizioni IP</label>
                                                                </div>
                                                                <small class="text-muted">Limita l'accesso a determinate reti IP.</small>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3 ip-restriction-settings" <?php echo ($securitySettings['ip_restriction']['setting_value'] ?? '0') != '1' ? 'style="display:none"' : ''; ?>>
                                                            <div class="col-md-12">
                                                                <label class="form-label" for="allowedIps">IP/Reti Consentiti</label>
                                                                <textarea class="form-control" id="allowedIps" name="allowed_ips" rows="3" placeholder="Inserisci un indirizzo IP o range per riga (es. 192.168.1.0/24)"><?php echo htmlspecialchars($securitySettings['allowed_ips']['setting_value'] ?? ''); ?></textarea>
                                                                <small class="text-muted">Lascia vuoto per consentire tutti gli IP. Usa la notazione CIDR per le reti.</small>
                                                            </div>
                                                        </div>
                                                        <div class="mt-4">
                                                            <button type="submit" class="btn btn-primary me-2">Salva Impostazioni</button>
                                                            <button type="reset" class="btn btn-outline-secondary">Annulla</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <!-- TAB PASSWORD -->
                                            <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                                                <div class="p-4">
                                                    <form id="passwordPolicyForm" class="security-form">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="minPasswordLength">Lunghezza Minima Password</label>
                                                                <input type="number" class="form-control" id="minPasswordLength" name="min_password_length" value="<?php echo htmlspecialchars($securitySettings['min_password_length']['setting_value'] ?? '8'); ?>" min="6" max="32" />
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="passwordExpiry">Scadenza Password (giorni)</label>
                                                                <input type="number" class="form-control" id="passwordExpiry" name="password_expiry" value="<?php echo htmlspecialchars($securitySettings['password_expiry']['setting_value'] ?? '90'); ?>" min="0" max="365" />
                                                                <small class="text-muted">0 = Non scade mai</small>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-12">
                                                                <label class="form-label">Requisiti Password</label>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="requireUppercase" name="require_uppercase" <?php echo ($securitySettings['require_uppercase']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="requireUppercase">Richiedi almeno una lettera maiuscola</label>
                                                                </div>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="requireLowercase" name="require_lowercase" <?php echo ($securitySettings['require_lowercase']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="requireLowercase">Richiedi almeno una lettera minuscola</label>
                                                                </div>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="requireNumber" name="require_number" <?php echo ($securitySettings['require_number']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="requireNumber">Richiedi almeno un numero</label>
                                                                </div>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="requireSpecial" name="require_special" <?php echo ($securitySettings['require_special']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="requireSpecial">Richiedi almeno un carattere speciale</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="passwordHistory">Cronologia Password</label>
                                                                <input type="number" class="form-control" id="passwordHistory" name="password_history" value="<?php echo htmlspecialchars($securitySettings['password_history']['setting_value'] ?? '5'); ?>" min="0" max="20" />
                                                                <small class="text-muted">Numero di password precedenti che non possono essere riutilizzate. 0 = Disattivato</small>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Opzioni Avanzate</label>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="passwordInfo" name="password_info" <?php echo ($securitySettings['password_info']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="passwordInfo">Mostra indicatore forza password</label>
                                                                </div>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="passwordReset" name="password_reset_login" <?php echo ($securitySettings['password_reset_login']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="passwordReset">Richiedi cambio password al primo accesso</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <div class="d-flex">
                                                                <i class="icon-base ti tabler-info-circle me-2 mt-1"></i>
                                                                <div>
                                                                    <h6>Esempio di password valida</h6>
                                                                    <p class="mb-0">In base alle impostazioni attuali, un esempio di password valida potrebbe essere: <code>Passw0rd!23</code></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mt-4">
                                                            <button type="submit" class="btn btn-primary me-2">Salva Policy Password</button>
                                                            <button type="reset" class="btn btn-outline-secondary">Annulla</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <!-- TAB 2FA -->
                                            <div class="tab-pane fade" id="twoFactor" role="tabpanel" aria-labelledby="2fa-tab">
                                                <div class="p-4">
                                                    <div class="alert alert-info mb-4">
                                                        <h6 class="alert-heading fw-bold mb-1">Autenticazione a Due Fattori (2FA)</h6>
                                                        <p class="mb-0">La 2FA aggiunge un ulteriore livello di sicurezza richiedendo un secondo fattore di autenticazione oltre alla password.</p>
                                                    </div>
                                                    
                                                    <form id="twoFactorSettingsForm" class="security-form">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Configurazione 2FA</label>
                                                                <div class="form-check form-switch mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="enable2fa" name="two_factor_auth" <?php echo ($securitySettings['two_factor_auth']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="enable2fa">Abilita 2FA per gli amministratori</label>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Metodi 2FA Consentiti</label>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="methodApp" name="method_app" <?php echo ($securitySettings['method_app']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="methodApp">App di autenticazione (Google, Microsoft, ecc.)</label>
                                                                </div>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="methodSms" name="method_sms" <?php echo ($securitySettings['method_sms']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="methodSms">SMS</label>
                                                                </div>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="methodEmail" name="method_email" <?php echo ($securitySettings['method_email']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="methodEmail">Email</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="otpExpiry">Durata OTP (secondi)</label>
                                                                <input type="number" class="form-control" id="otpExpiry" name="otp_expiry" value="<?php echo htmlspecialchars($securitySettings['otp_expiry']['setting_value'] ?? '300'); ?>" min="30" max="900" />
                                                                <small class="text-muted">Durata di validità dei codici OTP inviati via SMS/Email</small>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="backupCodes">Codici di Backup</label>
                                                                <input type="number" class="form-control" id="backupCodes" name="backup_codes" value="<?php echo htmlspecialchars($securitySettings['backup_codes']['setting_value'] ?? '5'); ?>" min="1" max="10" />
                                                                <small class="text-muted">Numero di codici di backup che gli utenti possono generare</small>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-12">
                                                                <label class="form-label">Opzioni Avanzate</label>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="rememberDevice" name="remember_device" <?php echo ($securitySettings['remember_device']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="rememberDevice">Consenti "Ricorda questo dispositivo"</label>
                                                                </div>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" id="force2faAdmin" name="force_2fa_admin" <?php echo ($securitySettings['force_2fa_admin']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="force2faAdmin">Forza 2FA per tutte le funzioni amministrative</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mt-4">
                                                            <button type="submit" class="btn btn-primary me-2">Salva Impostazioni 2FA</button>
                                                            <button type="reset" class="btn btn-outline-secondary">Annulla</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <!-- TAB LOGS -->
                                            <div class="tab-pane fade" id="logs" role="tabpanel" aria-labelledby="logs-tab">
                                                <div class="p-4">
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <div class="nav-align-top mb-4">
                                                                <ul class="nav nav-tabs" role="tablist">
                                                                    <li class="nav-item" role="presentation">
                                                                        <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-system-logs" aria-controls="navs-system-logs" aria-selected="true">
                                                                            <i class="icon-base ti tabler-alert me-1"></i> Errori di Sistema
                                                                        </button>
                                                                    </li>
                                                                    <li class="nav-item" role="presentation">
                                                                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-users-status" aria-controls="navs-users-status" aria-selected="false">
                                                                            <i class="icon-base ti tabler-user me-1"></i> Stato Utenti
                                                                        </button>
                                                                    </li>
                                                                    <li class="nav-item" role="presentation">
                                                                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-passwords" aria-controls="navs-passwords" aria-selected="false">
                                                                            <i class="icon-base ti tabler-key me-1"></i> Scadenza Password
                                                                        </button>
                                                                    </li>
                                                                </ul>
                                                                <div class="tab-content">
                                                                    <div class="tab-pane fade show active" id="navs-system-logs" role="tabpanel">
                                                                        <h6 class="mb-3">Ultimi Errori di Sistema</h6>
                                                                        <div class="table-responsive">
                                                                            <table class="table table-hover" id="errorLogsTable">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Data/Ora</th>
                                                                                        <th>Livello</th>
                                                                                        <th>Messaggio</th>
                                                                                        <th>Utente</th>
                                                                                        <th>IP</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    <?php 
                                                                                    if ($errorLogs->num_rows > 0) {
                                                                                        while ($log = $errorLogs->fetch_assoc()): 
                                                                                    ?>
                                                                                    <tr>
                                                                                        <td><small><?php echo date('d M Y, H:i:s', strtotime($log['created_at'])); ?></small></td>
                                                                                        <td><span class="badge bg-danger">Error</span></td>
                                                                                        <td><?php echo htmlspecialchars($log['message']); ?></td>
                                                                                        <td><?php echo $log['user_id'] ? 'user_' . $log['user_id'] : 'system'; ?></td>
                                                                                        <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                                                                    </tr>
                                                                                    <?php 
                                                                                        endwhile;
                                                                                    } else {
                                                                                        echo '<tr><td colspan="5" class="text-center">Nessun errore recente.</td></tr>';
                                                                                    }
                                                                                    ?>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        
                                                                        <h6 class="mt-4 mb-3">Ultimi Avvisi di Sistema</h6>
                                                                        <div class="table-responsive">
                                                                            <table class="table table-hover" id="warningLogsTable">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Data/Ora</th>
                                                                                        <th>Livello</th>
                                                                                        <th>Messaggio</th>
                                                                                        <th>Utente</th>
                                                                                        <th>IP</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    <?php 
                                                                                    if ($warningLogs->num_rows > 0) {
                                                                                        while ($log = $warningLogs->fetch_assoc()): 
                                                                                    ?>
                                                                                    <tr>
                                                                                        <td><small><?php echo date('d M Y, H:i:s', strtotime($log['created_at'])); ?></small></td>
                                                                                        <td><span class="badge bg-warning">Warning</span></td>
                                                                                        <td><?php echo htmlspecialchars($log['message']); ?></td>
                                                                                        <td><?php echo $log['user_id'] ? 'user_' . $log['user_id'] : 'system'; ?></td>
                                                                                        <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                                                                    </tr>
                                                                                    <?php 
                                                                                        endwhile;
                                                                                    } else {
                                                                                        echo '<tr><td colspan="5" class="text-center">Nessun avviso recente.</td></tr>';
                                                                                    }
                                                                                    ?>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                    <div class="tab-pane fade" id="navs-users-status" role="tabpanel">
                                                                        <h6 class="mb-3">Utenti Sospesi</h6>
                                                                        <div class="table-responsive">
                                                                            <table class="table table-hover" id="suspendedUsersTable">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Utente</th>
                                                                                        <th>Email</th>
                                                                                        <th>Ruolo</th>
                                                                                        <th>Stato</th>
                                                                                        <th>Azioni</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    <?php 
                                                                                    if ($suspendedUsers->num_rows > 0) {
                                                                                        while ($user = $suspendedUsers->fetch_assoc()): 
                                                                                    ?>
                                                                                    <tr data-id="<?php echo $user['id']; ?>">
                                                                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                                                        <td><?php echo htmlspecialchars($user['role_name'] ?? '-'); ?></td>
                                                                                        <td><span class="badge bg-danger">Sospeso</span></td>
                                                                                        <td>
                                                                                            <button type="button" class="btn btn-sm btn-primary activate-user" data-status="active">Riattiva</button>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <?php 
                                                                                        endwhile;
                                                                                    } else {
                                                                                        echo '<tr><td colspan="5" class="text-center">Nessun utente sospeso.</td></tr>';
                                                                                    }
                                                                                    ?>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        
                                                                        <h6 class="mt-4 mb-3">Utenti Inattivi</h6>
                                                                        <div class="table-responsive">
                                                                            <table class="table table-hover" id="inactiveUsersTable">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Utente</th>
                                                                                        <th>Email</th>
                                                                                        <th>Ruolo</th>
                                                                                        <th>Stato</th>
                                                                                        <th>Azioni</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    <?php 
                                                                                    if ($inactiveUsers->num_rows > 0) {
                                                                                        while ($user = $inactiveUsers->fetch_assoc()): 
                                                                                    ?>
                                                                                    <tr data-id="<?php echo $user['id']; ?>">
                                                                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                                                        <td><?php echo htmlspecialchars($user['role_name'] ?? '-'); ?></td>
                                                                                        <td><span class="badge bg-secondary">Inattivo</span></td>
                                                                                        <td>
                                                                                            <button type="button" class="btn btn-sm btn-primary activate-user" data-status="active">Riattiva</button>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <?php 
                                                                                        endwhile;
                                                                                    } else {
                                                                                        echo '<tr><td colspan="5" class="text-center">Nessun utente inattivo.</td></tr>';
                                                                                    }
                                                                                    ?>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                    <div class="tab-pane fade" id="navs-passwords" role="tabpanel">
                                                                        <h6 class="mb-3">Password Scadute</h6>
                                                                        <div class="table-responsive">
                                                                            <table class="table table-hover" id="expiredPasswordTable">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Utente</th>
                                                                                        <th>Email</th>
                                                                                        <th>Ruolo</th>
                                                                                        <th>Ultima Modifica</th>
                                                                                        <th>Stato</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    <?php 
                                                                                    if ($expiredPasswordUsers->num_rows > 0) {
                                                                                        while ($user = $expiredPasswordUsers->fetch_assoc()): 
                                                                                    ?>
                                                                                    <tr data-id="<?php echo $user['id']; ?>">
                                                                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                                                        <td><?php echo htmlspecialchars($user['role_name'] ?? '-'); ?></td>
                                                                                        <td><?php echo date('d M Y', strtotime($user['password_changed_at'])); ?></td>
                                                                                        <td><span class="badge bg-danger">Scaduta</span></td>
                                                                                    </tr>
                                                                                    <?php 
                                                                                        endwhile;
                                                                                    } else {
                                                                                        echo '<tr><td colspan="5" class="text-center">Nessuna password scaduta.</td></tr>';
                                                                                    }
                                                                                    ?>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        
                                                                        <h6 class="mt-4 mb-3">Password in Scadenza</h6>
                                                                        <div class="table-responsive">
                                                                            <table class="table table-hover" id="expiringPasswordTable">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Utente</th>
                                                                                        <th>Email</th>
                                                                                        <th>Ruolo</th>
                                                                                        <th>Ultima Modifica</th>
                                                                                        <th>Giorni Rimanenti</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    <?php 
                                                                                    if ($expiringPasswordUsers->num_rows > 0) {
                                                                                        while ($user = $expiringPasswordUsers->fetch_assoc()): 
                                                                                    ?>
                                                                                    <tr data-id="<?php echo $user['id']; ?>">
                                                                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                                                        <td><?php echo htmlspecialchars($user['role_name'] ?? '-'); ?></td>
                                                                                        <td><?php echo date('d M Y', strtotime($user['password_changed_at'])); ?></td>
                                                                                        <td>
                                                                                            <span class="badge bg-warning"><?php echo intval($user['days_remaining']); ?> giorni</span>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <?php 
                                                                                        endwhile;
                                                                                    } else {
                                                                                        echo '<tr><td colspan="5" class="text-center">Nessuna password in scadenza.</td></tr>';
                                                                                    }
                                                                                    ?>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
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
    <script src="../../../assets/vendor/libs/datatables/jquery.dataTables.js"></script>
    <script src="../../../assets/vendor/libs/datatables-bs5/datatables.bootstrap5.js"></script>
    <script src="../../../assets/vendor/libs/datatables-responsive/datatables.responsive.js"></script>
    <script src="../../../assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js"></script>

    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>
    
    <!-- Passaggio dei permessi al JavaScript -->
    <script>
        var userPermissions = {
            canRead: <?php echo $canRead ? 'true' : 'false'; ?>,
            canWrite: <?php echo $canWrite ? 'true' : 'false'; ?>,
            canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>
        };
    </script>
    
    <!-- Script personalizzato per le impostazioni di sicurezza -->
    <script>
    $(document).ready(function() {
        'use strict';
        
        // Configurazione colori e tema
        let borderColor, bodyBg, headingColor;
        borderColor = config.colors.borderColor;
        bodyBg = config.colors.bodyBg;
        headingColor = config.colors.headingColor;
        
        // Inizializzazione DataTables
        const dataTableConfig = {
            responsive: true,
            language: {
                search: '',
                searchPlaceholder: 'Cerca...',
                info: 'Mostra da _START_ a _END_ di _TOTAL_ record',
                paginate: {
                    first: 'Prima',
                    previous: 'Precedente',
                    next: 'Successiva',
                    last: 'Ultima'
                },
                lengthMenu: 'Mostra _MENU_ record per pagina',
                zeroRecords: 'Nessun risultato trovato',
                infoEmpty: 'Nessun record disponibile',
                infoFiltered: '(filtrato da _MAX_ record totali)'
            }
        };
        
        $('#errorLogsTable').DataTable(dataTableConfig);
        $('#warningLogsTable').DataTable(dataTableConfig);
        $('#suspendedUsersTable').DataTable(dataTableConfig);
        $('#inactiveUsersTable').DataTable(dataTableConfig);
        $('#expiredPasswordTable').DataTable(dataTableConfig);
        $('#expiringPasswordTable').DataTable(dataTableConfig);
        
        // Funzione per mostrare messaggi di alert
        function showAlert(message, type = 'success') {
            const alertHTML = `
                <div class="alert alert-${type} alert-dismissible mb-4" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('#alert-container').html(alertHTML);
            
            // Auto-nascondi dopo 5 secondi
            setTimeout(function() {
                $('.alert').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Gestione form delle impostazioni di sicurezza
        $('.security-form').on('submit', function(e) {
            e.preventDefault();
            
            if (!userPermissions.canWrite) {
                showAlert('Non hai i permessi per modificare queste impostazioni.', 'danger');
                return;
            }
            
            const formId = $(this).attr('id');
            const settings = {};
            
            // Raccogli i valori del form
            $(this).find('input, select, textarea').each(function() {
                let name = $(this).attr('name');
                let value;
                
                if ($(this).attr('type') === 'checkbox') {
                    value = $(this).is(':checked') ? '1' : '0';
                } else if ($(this).attr('type') === 'radio') {
                    if ($(this).is(':checked')) {
                        value = $(this).val();
                    } else {
                        return; // Salta questo elemento se il radio button non è selezionato
                    }
                } else {
                    value = $(this).val();
                }
                
                if (name) {
                    settings[name] = value;
                }
            });
            
            // Mostra indicatore di caricamento
            const loadingBtn = $(this).find('button[type="submit"]');
            const originalText = loadingBtn.html();
            loadingBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvataggio...');
            loadingBtn.prop('disabled', true);
            
            // Invia al server via AJAX
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'update_security_settings',
                    settings: settings
                },
                dataType: 'json',
                success: function(response) {
                    // Ripristina pulsante
                    loadingBtn.html(originalText);
                    loadingBtn.prop('disabled', false);
                    
                    if (response.success) {
                        showAlert(response.message);
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    // Ripristina pulsante
                    loadingBtn.html(originalText);
                    loadingBtn.prop('disabled', false);
                    
                    showAlert('Si è verificato un errore durante la comunicazione con il server.', 'danger');
                }
            });
        });
        
        // Gestione pulsante per attivare o disattivare utenti
        $(document).on('click', '.activate-user, .deactivate-user, .suspend-user', function() {
            if (!userPermissions.canWrite) {
                showAlert('Non hai i permessi per cambiare lo stato degli utenti.', 'danger');
                return;
            }
            
            const row = $(this).closest('tr');
            const userId = row.data('id');
            const username = row.find('td:first').text();
            const status = $(this).data('status');
            
            // Determinare titolo e testo in base all'azione
            let title, text;
            if (status === 'active') {
                title = 'Riattivare l\'utente?';
                text = `Vuoi davvero riattivare l'utente ${username}?`;
            } else if (status === 'inactive') {
                title = 'Disattivare l\'utente?';
                text = `Vuoi davvero disattivare l'utente ${username}?`;
            } else {
                title = 'Sospendere l\'utente?';
                text = `Vuoi davvero sospendere l'utente ${username}?`;
            }
            
            // Conferma azione
            Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sì, procedi',
                cancelButtonText: 'Annulla',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-outline-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostra indicatore di caricamento
                    Swal.fire({
                        title: 'Aggiornamento in corso...',
                        html: 'Aggiornamento dello stato dell\'utente...',
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });
                    
                    // Aggiorna lo stato dell'utente via AJAX
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'update_user_status',
                            user_id: userId,
                            status: status
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Completato!',
                                    text: response.message,
                                    icon: 'success',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    },
                                    buttonsStyling: false
                                });
                                
                                // Rimuovi la riga dalla tabella corrente
                                row.fadeOut(400, function() {
                                    $(this).remove();
                                    
                                    // Controlla se la tabella è vuota
                                    const tableId = row.closest('table').attr('id');
                                    if ($(`#${tableId} tbody tr`).length === 0) {
                                        $(`#${tableId} tbody`).html('<tr><td colspan="5" class="text-center">Nessun utente trovato.</td></tr>');
                                    }
                                });
                            } else {
                                Swal.fire({
                                    title: 'Errore!',
                                    text: response.message,
                                    icon: 'error',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    },
                                    buttonsStyling: false
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                title: 'Errore!',
                                text: 'Si è verificato un errore durante la comunicazione con il server.',
                                icon: 'error',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                },
                                buttonsStyling: false
                            });
                        }
                    });
                }
            });
        });
        
        // Gestione toggle restrizioni IP
        $('#ipRestriction').on('change', function() {
            if ($(this).is(':checked')) {
                $('.ip-restriction-settings').slideDown();
            } else {
                $('.ip-restriction-settings').slideUp();
            }
        });
        
        // Verifica permessi all'avvio
        if (!userPermissions.canWrite) {
            // Disabilita input e pulsanti di salvataggio per chi non può scrivere
            $('input, select, textarea, .form-check-input').prop('disabled', true);
            $('button[type="submit"]').prop('disabled', true).addClass('disabled');
            $('.activate-user, .deactivate-user, .suspend-user').prop('disabled', true).addClass('disabled');
            
            showAlert('Hai accesso in sola lettura a questa pagina. Le modifiche sono disabilitate.', 'info');
        }
    });
    </script>
    
    <!-- Menu Accordion Script -->
    <script src="../../../assets/js/menu_accordion.js"></script>
</body>
</html>
<?php
$conn->close();
?>
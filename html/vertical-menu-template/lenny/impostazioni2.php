<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Verifica l'accesso specifico a questa pagina
if (!userHasAccess('Impostazioni')) {
    // Reindirizza alla pagina di accesso negato
    header("Location: access-denied.php");
    exit;
}

// Ottieni i permessi specifici dell'utente per questa funzionalità
$canRead = userHasPermission('Impostazioni', 'read');
$canWrite = userHasPermission('Impostazioni', 'write');
$canCreate = userHasPermission('Impostazioni', 'create');

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

// Funzione per ottenere le impostazioni dal database
function getSettings($section = null) {
    global $conn;
    
    $query = "SELECT * FROM system_settings";
    if ($section) {
        $query .= " WHERE section = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $section);
    } else {
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row;
    }
    
    return $settings;
}

// Funzione per aggiornare una singola impostazione
function updateSetting($key, $value) {
    global $conn;
    
    $query = "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $value, $key);
    return $stmt->execute();
}

// Funzione per ottenere i log di sistema
function getSystemLogs($limit = 5) {
    global $conn;
    
    $query = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    return $stmt->get_result();
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

// Funzione per inviare un'email utilizzando PHPMailer
function sendEmail($to, $subject, $body, $isHtml = true) {
    global $mailSettings;
    
    // Se non abbiamo già caricato le impostazioni email, lo facciamo ora
    if (!isset($mailSettings)) {
        $mailSettings = getSettings('mail');
    }
    
    // Verifica che PHPMailer sia disponibile
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php')) {
        logSystemAction('error', "PHPMailer non trovato. Verifica l'installazione di Composer.");
        return false;
    }
    
    // Includi PHPMailer (utilizza il percorso assoluto per maggiore sicurezza)
    require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
    
    // Crea una nuova istanza di PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configurazione server
        $driver = $mailSettings['mail_driver']['setting_value'] ?? 'smtp';
        
        if ($driver == 'smtp') {
            $mail->isSMTP();
            $mail->Host = $mailSettings['mail_host']['setting_value'] ?? 'smtp.mailtrap.io';
            $mail->SMTPAuth = true;
            $mail->Username = $mailSettings['mail_username']['setting_value'] ?? '';
            $mail->Password = $mailSettings['mail_password']['setting_value'] ?? '';
            $mail->Port = $mailSettings['mail_port']['setting_value'] ?? 2525;
            
            // Configurazione sicurezza
            $encryption = $mailSettings['mail_encryption']['setting_value'] ?? '';
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
        } elseif ($driver == 'sendmail') {
            $mail->isSendmail();
        } elseif ($driver == 'mailgun') {
            // Per Mailgun potresti dover implementare una logica specifica o utilizzare la loro API
            logSystemAction('info', "L'invio tramite Mailgun richiede l'utilizzo della loro API specifica.");
            // Qui potresti usare la loro API REST invece di PHPMailer
        } elseif ($driver == 'ses') {
            // Per Amazon SES potresti voler utilizzare AWS SDK
            logSystemAction('info', "L'invio tramite Amazon SES richiede l'utilizzo dell'SDK AWS.");
            // Qui potresti usare AWS SDK per PHP
        }
        
        // Disabilita la verifica SSL in ambiente di sviluppo (rimuovi in produzione)
        if ($mailSettings['mail_debug']['setting_value'] ?? '0' == '1') {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Attiva il debug in ambiente di sviluppo
            $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
        }
        
        // Impostazioni mittente e destinatario
        $fromAddress = $mailSettings['mail_from_address']['setting_value'] ?? 'no-reply@example.com';
        $fromName = $mailSettings['mail_from_name']['setting_value'] ?? 'Sistema';
        
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($fromAddress, $fromName);
        
        // Impostazioni contenuto
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Se l'email è in HTML, imposta anche una versione in testo semplice
        if ($isHtml) {
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\r\n", $body));
        }
        
        // Imposta la codifica dei caratteri
        $mail->CharSet = 'UTF-8';
        
        // Invia l'email
        if ($mail->send()) {
            // Registra l'invio nei log
            logSystemAction('info', "Email inviata con successo a $to");
            return true;
        } else {
            // Registra l'errore nei log
            logSystemAction('error', "Errore nell'invio email a $to: " . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        // Registra l'eccezione nei log
        logSystemAction('error', "Eccezione nell'invio email a $to: " . $e->getMessage());
        return false;
    }
}

// Gestione degli aggiornamenti delle impostazioni e altre operazioni AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['action'])) {
    // Verifica i permessi (eccetto per refresh_logs che richiede solo canRead)
    if (($_POST['action'] !== 'refresh_logs')&&!$canWrite) {
        echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per modificare le impostazioni']);
        exit;
    }
    
    $action = $_POST['action'];
    
    // Aggiorna impostazioni generali
    if ($action === 'update_settings'&&isset($_POST['settings'])&&isset($_POST['section'])) {
        $settings = $_POST['settings'];
        $section = $_POST['section'];
        $success = true;
        $message = 'Impostazioni ' . ucfirst($section) . ' aggiornate con successo';
        
        foreach ($settings as $key => $value) {
            if (!updateSetting($key, $value)) {
                $success = false;
                $message = 'Errore durante l\'aggiornamento delle impostazioni';
                break;
            }
        }
        
        // Registra l'operazione nei log
        $level = $success ? 'info' : 'error';
        $logMessage = $success ? 'Impostazioni ' . $section . ' aggiornate' : 'Errore aggiornamento impostazioni ' . $section;
        logSystemAction($level, $logMessage);
        
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    
    // Invia email di test
    if ($action === 'send_test_email'&&isset($_POST['email'])) {
        if (!$canCreate) {
            echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per inviare email di test']);
            exit;
        }
        
        $email = $_POST['email'];
        
        try {
            // Verifica che l'indirizzo email sia valido
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Indirizzo email non valido");
            }
            
            // Prepara il contenuto dell'email di test
            $subject = 'Email di test dal sistema';
            $body = '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                        .content { padding: 20px; }
                        .footer { margin-top: 20px; font-size: 12px; text-align: center; color: #777; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>Email di Test</h2>
                        </div>
                        <div class="content">
                            <p>Questa è un\'email di test inviata dal sistema di amministrazione.</p>
                            <p>Se stai ricevendo questa email, la configurazione del sistema di posta elettronica funziona correttamente.</p>
                            <p>Data e ora di invio: ' . date('d/m/Y H:i:s') . '</p>
                        </div>
                        <div class="footer">
                            <p>Questo è un messaggio automatico, si prega di non rispondere.</p>
                        </div>
                    </div>
                </body>
                </html>
            ';
            
            // Invia l'email
            if (!sendEmail($email, $subject, $body)) {
                throw new Exception("Impossibile inviare l'email. Verifica la configurazione del server di posta.");
            }
            
            // Registra nei log
            logSystemAction('info', "Email di test inviata a {$email}");
            
            echo json_encode(['success' => true, 'message' => "Email di test inviata con successo a {$email}"]);
        } catch (Exception $e) {
            logSystemAction('error', "Errore invio email di test a {$email}: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Aggiorna i log
    if ($action === 'refresh_logs') {
        if (!$canRead) {
            echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per visualizzare i log']);
            exit;
        }
        
        try {
            // Registra la richiesta di aggiornamento nei log
            logSystemAction('info', 'Log aggiornati manualmente');
            
            // Ottieni i log aggiornati
            $logs = getSystemLogs(10); // Aumentiamo il numero di log da visualizzare
            $logsArray = [];
            
            while ($log = $logs->fetch_assoc()) {
                $logsArray[] = $log;
            }
            
            echo json_encode(['success' => true, 'logs' => $logsArray]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta']);
    exit;
}

// Carica le impostazioni dalle sezioni principali
$generalSettings = getSettings('general');
$mailSettings = getSettings('mail');

// Ottieni log recenti
$logs = getSystemLogs(10);

// Ottieni informazioni sul server
$serverInfo = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'mysql_version' => $conn->server_info ?? 'Unknown',
    'disk_total' => disk_total_space('/'),
    'disk_free' => disk_free_space('/'),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time') . 's',
];

// Calcola percentuale di utilizzo disco
$diskUsagePercent = 0;
if ($serverInfo['disk_total'] > 0) {
    $diskUsagePercent = round(($serverInfo['disk_total'] - $serverInfo['disk_free']) / $serverInfo['disk_total'] * 100);
}

// Formattazione bytes in formato leggibile
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Determina se il server è locale o remoto
$isLocalServer = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || 
                 strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || 
                 strpos($_SERVER['SERVER_NAME'], '.local') !== false;

// Tipo di ambiente
$environment = $isLocalServer ? 'Sviluppo (Locale)' : 'Produzione';

// Cache usage simulated (in a real system, you would get this from your caching system like Redis or Memcached)
$cacheUsagePercent = rand(70, 95); // Simulated value
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Impostazioni</title>
    <meta name="description" content="Gestione impostazioni di sistema" />

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
    <link rel="stylesheet" href="../../../assets/vendor/libs/@form-validation/umd/styles/index.min.css" />
    <!-- DataTable e Select2 CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/select2/select2.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/sweetalert2/sweetalert2.css" />

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
                        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Sistema /</span> Impostazioni</h4>
                        
                        <!-- Flash Messages per operazioni CRUD -->
                        <div id="alert-container"></div>
                        
                        <!-- Stats Cards -->
                        <div class="row g-6 mb-6">
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Server</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2">Online</h4>
                                                </div>
                                                <small class="mb-0"><?php echo htmlspecialchars($serverInfo['server_software']); ?></small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-success">
                                                    <i class="icon-base ti tabler-server icon-26px"></i>
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
                                                <span class="text-heading">Ambiente</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $environment; ?></h4>
                                                </div>
                                                <small class="mb-0">PHP <?php echo phpversion(); ?></small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-primary">
                                                    <i class="icon-base ti <?php echo $isLocalServer ? 'tabler-device-laptop' : 'tabler-cloud'; ?> icon-26px"></i>
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
                                                <span class="text-heading">Cache</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $cacheUsagePercent; ?>%</h4>
                                                </div>
                                                <small class="mb-0">Utilizzo memoria cache</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-info">
                                                    <i class="icon-base ti tabler-refresh icon-26px"></i>
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
                                                <span class="text-heading">Storage</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $diskUsagePercent; ?>%</h4>
                                                </div>
                                                <small class="mb-0"><?php echo formatBytes($serverInfo['disk_free']); ?> liberi / <?php echo formatBytes($serverInfo['disk_total']); ?></small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-warning">
                                                    <i class="icon-base ti tabler-database icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- / Stats Cards -->
                        
                        <!-- Tabs di configurazione -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Configurazione Sistema</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="nav nav-tabs" role="tablist">
                                            <li class="nav-item">
                                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                                                    <i class="icon-base ti tabler-settings me-1"></i> Generale
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link" id="mail-tab" data-bs-toggle="tab" data-bs-target="#mail" type="button" role="tab" aria-controls="mail" aria-selected="false">
                                                    <i class="icon-base ti tabler-mail me-1"></i> Email
                                                </button>
                                            </li>
                                        </ul>
                                        <div class="tab-content">
                                            <!-- TAB GENERALE -->
                                            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                                                <div class="p-4">
                                                    <form id="generalSettingsForm" class="settings-form" data-section="general">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="siteName">Nome Sito</label>
                                                                <input type="text" class="form-control" id="siteName" name="site_name" value="<?php echo htmlspecialchars($generalSettings['site_name']['setting_value'] ?? 'Admin Dashboard'); ?>" />
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="siteUrl">URL Sito</label>
                                                                <input type="text" class="form-control" id="siteUrl" name="site_url" value="<?php echo htmlspecialchars($generalSettings['site_url']['setting_value'] ?? 'https://admin.example.com'); ?>" />
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="timezone">Fuso Orario</label>
                                                                <select class="form-select" id="timezone" name="timezone">
                                                                    <?php
                                                                    $timezones = array(
                                                                        'UTC' => 'UTC',
                                                                        'Europe/Rome' => 'Europe/Rome',
                                                                        'Europe/London' => 'Europe/London',
                                                                        'America/New_York' => 'America/New_York'
                                                                    );
                                                                    $selectedTimezone = $generalSettings['timezone']['setting_value'] ?? 'UTC';
                                                                    foreach ($timezones as $value => $label) {
                                                                        echo '<option value="' . $value . '"' . ($selectedTimezone == $value ? ' selected' : '') . '>' . $label . '</option>';
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="dateFormat">Formato Data</label>
                                                                <select class="form-select" id="dateFormat" name="date_format">
                                                                    <?php
                                                                    $dateFormats = array(
                                                                        'Y-m-d' => 'YYYY-MM-DD',
                                                                        'd/m/Y' => 'DD/MM/YYYY',
                                                                        'm/d/Y' => 'MM/DD/YYYY'
                                                                    );
                                                                    $selectedFormat = $generalSettings['date_format']['setting_value'] ?? 'Y-m-d';
                                                                    foreach ($dateFormats as $value => $label) {
                                                                        echo '<option value="' . $value . '"' . ($selectedFormat == $value ? ' selected' : '') . '>' . $label . '</option>';
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-12">
                                                                <label class="form-label" for="maintenance">Modalità Manutenzione</label>
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="maintenance" name="maintenance_mode" <?php echo ($generalSettings['maintenance_mode']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?> />
                                                                    <label class="form-check-label" for="maintenance">Attiva modalità manutenzione</label>
                                                                </div>
                                                                <small class="text-muted">La modalità manutenzione renderà il sito inaccessibile agli utenti normali.</small>
                                                            </div>
                                                        </div>
                                                        <div class="mt-4">
                                                            <button type="submit" class="btn btn-primary me-2">Salva Impostazioni</button>
                                                            <button type="reset" class="btn btn-outline-secondary">Annulla</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <!-- TAB EMAIL -->
                                            <div class="tab-pane fade" id="mail" role="tabpanel" aria-labelledby="mail-tab">
                                                <div class="p-4">
                                                    <form id="mailSettingsForm" class="settings-form" data-section="mail">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="mailDriver">Driver Email</label>
                                                                <select class="form-select" id="mailDriver" name="mail_driver">
                                                                    <?php
                                                                    $mailDrivers = array(
                                                                        'smtp' => 'SMTP',
                                                                        'sendmail' => 'Sendmail',
                                                                        'mailgun' => 'Mailgun',
                                                                        'ses' => 'Amazon SES'
                                                                    );
                                                                    $selectedDriver = $mailSettings['mail_driver']['setting_value'] ?? 'smtp';
                                                                    foreach ($mailDrivers as $value => $label) {
                                                                        echo '<option value="' . $value . '"' . ($selectedDriver == $value ? ' selected' : '') . '>' . $label . '</option>';
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="mailHost">Host SMTP</label>
                                                                <input type="text" class="form-control" id="mailHost" name="mail_host" value="<?php echo htmlspecialchars($mailSettings['mail_host']['setting_value'] ?? 'smtp.mailtrap.io'); ?>" />
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="mailPort">Porta SMTP</label>
                                                                <input type="text" class="form-control" id="mailPort" name="mail_port" value="<?php echo htmlspecialchars($mailSettings['mail_port']['setting_value'] ?? '2525'); ?>" />
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="mailEncryption">Crittografia</label>
                                                                <select class="form-select" id="mailEncryption" name="mail_encryption">
                                                                    <?php
                                                                    $encryptionTypes = array(
                                                                        'tls' => 'TLS',
                                                                        'ssl' => 'SSL',
                                                                        '' => 'Nessuna'
                                                                    );
                                                                    $selectedEncryption = $mailSettings['mail_encryption']['setting_value'] ?? 'tls';
                                                                    foreach ($encryptionTypes as $value => $label) {
                                                                        echo '<option value="' . $value . '"' . ($selectedEncryption == $value ? ' selected' : '') . '>' . $label . '</option>';
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="mailUsername">Username</label>
                                                                <input type="text" class="form-control" id="mailUsername" name="mail_username" value="<?php echo htmlspecialchars($mailSettings['mail_username']['setting_value'] ?? 'your_username'); ?>" />
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="mailPassword">Password</label>
                                                                <input type="password" class="form-control" id="mailPassword" name="mail_password" value="<?php echo htmlspecialchars($mailSettings['mail_password']['setting_value'] ?? 'your_password'); ?>" />
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="mailFromAddress">Indirizzo Mittente</label>
                                                                <input type="email" class="form-control" id="mailFromAddress" name="mail_from_address" value="<?php echo htmlspecialchars($mailSettings['mail_from_address']['setting_value'] ?? 'no-reply@example.com'); ?>" />
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="mailFromName">Nome Mittente</label>
                                                                <input type="text" class="form-control" id="mailFromName" name="mail_from_name" value="<?php echo htmlspecialchars($mailSettings['mail_from_name']['setting_value'] ?? 'Admin Dashboard'); ?>" />
                                                            </div>
                                                        </div>
                                                        <div class="mt-4">
                                                            <button type="submit" class="btn btn-primary me-2">Salva Impostazioni</button>
                                                            <button type="button" class="btn btn-info me-2" id="sendTestEmail">Invia Test</button>
                                                            <button type="reset" class="btn btn-outline-secondary">Annulla</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Info Server -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Informazioni Server</h5>
                                        <button type="button" class="btn btn-primary btn-sm" id="refreshServerInfo">
                                            <i class="icon-base ti tabler-refresh me-1"></i> Aggiorna
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <h6>Dettagli Sistema</h6>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">Sistema:</span>
                                                            <span><?php echo php_uname('s') . ' ' . php_uname('r'); ?></span>
                                                        </li>
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">Server Web:</span>
                                                            <span><?php echo htmlspecialchars($serverInfo['server_software']); ?></span>
                                                        </li>
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">PHP Version:</span>
                                                            <span><?php echo phpversion(); ?></span>
                                                        </li>
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">MySQL Version:</span>
                                                            <span><?php echo htmlspecialchars($serverInfo['mysql_version']); ?></span>
                                                        </li>
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">Ambiente:</span>
                                                            <span><?php echo $environment; ?></span>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <h6>Limiti&Risorse</h6>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">Memoria PHP:</span>
                                                            <span><?php echo $serverInfo['memory_limit']; ?></span>
                                                        </li>
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">Upload max:</span>
                                                            <span><?php echo $serverInfo['upload_max_filesize']; ?></span>
                                                        </li>
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">POST max:</span>
                                                            <span><?php echo $serverInfo['post_max_size']; ?></span>
                                                        </li>
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">Tempo esecuzione:</span>
                                                            <span><?php echo $serverInfo['max_execution_time']; ?></span>
                                                        </li>
                                                        <li class="d-flex align-items-center mb-2">
                                                            <span class="fw-bold me-2 text-muted">Spazio disco:</span>
                                                            <span><?php echo formatBytes($serverInfo['disk_free']); ?> liberi / <?php echo formatBytes($serverInfo['disk_total']); ?> totali</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Disk Usage Progress Bar -->
                                        <div class="mt-4">
                                            <h6>Utilizzo Spazio Disco</h6>
                                            <div class="d-flex mb-1 align-items-center">
                                                <span><?php echo $diskUsagePercent; ?>%</span>
                                                <span class="ms-auto"><?php echo formatBytes($serverInfo['disk_total'] - $serverInfo['disk_free']); ?> / <?php echo formatBytes($serverInfo['disk_total']); ?></span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-<?php 
                                                    echo $diskUsagePercent > 90 ? 'danger' : ($diskUsagePercent > 70 ? 'warning' : 'primary'); 
                                                ?>" role="progressbar" style="width: <?php echo $diskUsagePercent; ?>%" aria-valuenow="<?php echo $diskUsagePercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Logs del sistema -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Log di Sistema</h5>
                                        <div class="card-text">
                                            <button type="button" class="btn btn-primary btn-sm" id="refreshLogs">
                                                <i class="icon-base ti tabler-refresh me-1"></i> Aggiorna
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="logsTable">
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
                                                    if ($logs->num_rows > 0) {
                                                        while ($log = $logs->fetch_assoc()): 
                                                    ?>
                                                    <tr>
                                                        <td><small><?php echo date('d M Y, H:i:s', strtotime($log['created_at'])); ?></small></td>
                                                        <td><span class="badge bg-<?php 
                                                            echo $log['level'] === 'error' ? 'danger' : 
                                                                ($log['level'] === 'warning' ? 'warning' : 
                                                                ($log['level'] === 'debug' ? 'success' : 'info')); 
                                                        ?>"><?php echo ucfirst($log['level']); ?></span></td>
                                                        <td><?php echo htmlspecialchars($log['message']); ?></td>
                                                        <td><?php echo $log['user_id'] ? 'user_' . $log['user_id'] : 'system'; ?></td>
                                                        <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                                    </tr>
                                                    <?php 
                                                        endwhile;
                                                    } else {
                                                        echo '<tr><td colspan="5" class="text-center">Nessun log trovato.</td></tr>';
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
    <script src="../../../assets/vendor/libs/moment/moment.js"></script>
    <script src="../../../assets/vendor/libs/sweetalert2/sweetalert2.js"></script>

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
    
    <!-- Script personalizzato per le impostazioni -->
    <script>
    $(document).ready(function() {
        'use strict';
        
        // Configurazione colori e tema
        let borderColor, bodyBg, headingColor;
        borderColor = config.colors.borderColor;
        bodyBg = config.colors.bodyBg;
        headingColor = config.colors.headingColor;
        
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
        
        // Gestione form delle impostazioni (generico per tutti i form)
        $('.settings-form').on('submit', function(e) {
            e.preventDefault();
            
            if (!userPermissions.canWrite) {
                showAlert('Non hai i permessi per modificare queste impostazioni.', 'danger');
                return;
            }
            
            const section = $(this).data('section');
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
                    action: 'update_settings',
                    settings: settings,
                    section: section
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
        
        // Pulsante Aggiorna Log
        $('#refreshLogs').on('click', function() {
            if (!userPermissions.canRead) {
                showAlert('Non hai i permessi per aggiornare i log.', 'danger');
                return;
            }
            
            // Cambio l'aspetto del pulsante durante il caricamento
            const btn = $(this);
            const originalText = btn.html();
            btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Aggiornamento...');
            btn.prop('disabled', true);
            
            // Aggiorna log via AJAX
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'refresh_logs'
                },
                dataType: 'json',
                success: function(response) {
                    // Ripristina pulsante
                    btn.html(originalText);
                    btn.prop('disabled', false);
                    
                    if (response.success) {
                        // Aggiorna la tabella dei log
                        let logsHtml = '';
                        
                        if (response.logs.length > 0) {
                            response.logs.forEach(function(log) {
                                const date = new Date(log.created_at).toLocaleString('en-GB', {
                                    day: '2-digit',
                                    month: 'short',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    second: '2-digit'
                                });
                                
                                const badgeClass = log.level === 'error' ? 'danger' : 
                                                (log.level === 'warning' ? 'warning' : 
                                                (log.level === 'debug' ? 'success' : 'info'));
                                
                                logsHtml += `
                                    <tr>
                                        <td><small>${date}</small></td>
                                        <td><span class="badge bg-${badgeClass}">${log.level.charAt(0).toUpperCase() + log.level.slice(1)}</span></td>
                                        <td>${log.message}</td>
                                        <td>${log.user_id ? 'user_' + log.user_id : 'system'}</td>
                                        <td>${log.ip_address || '-'}</td>
                                    </tr>
                                `;
                            });
                        } else {
                            logsHtml = '<tr><td colspan="5" class="text-center">Nessun log trovato.</td></tr>';
                        }
                        
                        $('#logsTable tbody').html(logsHtml);
                        showAlert('Log aggiornati con successo.', 'success');
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    // Ripristina pulsante
                    btn.html(originalText);
                    btn.prop('disabled', false);
                    
                    showAlert('Si è verificato un errore durante la comunicazione con il server.', 'danger');
                }
            });
        });
        
        // Invia email di test
        $('#sendTestEmail').on('click', function() {
            if (!userPermissions.canCreate) {
                showAlert('Non hai i permessi per inviare email di test.', 'danger');
                return;
            }
            
            // Visualizza un popup di richiesta dell'indirizzo email
            Swal.fire({
                title: 'Invia email di test',
                input: 'email',
                inputLabel: 'Indirizzo email',
                inputPlaceholder: 'Inserisci l\'indirizzo email di destinazione',
                showCancelButton: true,
                confirmButtonText: 'Invia',
                cancelButtonText: 'Annulla',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline-secondary'
                },
                buttonsStyling: false,
                preConfirm: (email) => {
                    if (!email) {
                        Swal.showValidationMessage('L\'indirizzo email è obbligatorio');
                    }
                    return email;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Invia email di test via AJAX
                    Swal.fire({
                        title: 'Invio in corso...',
                        text: 'Stiamo inviando l\'email di test',
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });
                    
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'send_test_email',
                            email: result.value
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Email inviata!',
                                    text: response.message,
                                    icon: 'success',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    },
                                    buttonsStyling: false
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

        // Aggiorna informazioni server
        $('#refreshServerInfo').on('click', function() {
            // In una vera implementazione, questo potrebbe fare una chiamata AJAX per aggiornare i dati
            location.reload();
        });
        
        // Verifica permessi all'avvio
        if (!userPermissions.canWrite) {
            // Disabilita input e pulsanti di salvataggio per chi non può scrivere
            $('input, select, textarea, .form-check-input').prop('disabled', true);
            $('button[type="submit"], .btn-info:contains("Invia Test")').prop('disabled', true).addClass('disabled');
            
            showAlert('Hai accesso in sola lettura a questa pagina. Le modifiche sono disabilitate.', 'info');
        }
        
        if (!userPermissions.canCreate) {
            // Disabilita pulsanti di azione avanzata per chi non può creare
            $('#sendTestEmail').prop('disabled', true).addClass('disabled');
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
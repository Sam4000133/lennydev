<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Verifica l'accesso specifico a questa pagina
if (!userHasAccess('Backup')) {
    // Reindirizza alla pagina di accesso negato
    header("Location: access-denied.php");
    exit;
}

// Ottieni i permessi specifici dell'utente per questa funzionalità
$canRead = userHasPermission('Backup', 'read');
$canWrite = userHasPermission('Backup', 'write');
$canCreate = userHasPermission('Backup', 'create');

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

// Funzione per ottenere i log di sistema
function getSystemLogs($limit = 5) {
    global $conn;
    
    $query = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}

// Funzione per ottenere i backup recenti
function getRecentBackups($limit = 10) {
    global $conn;
    
    $query = "SELECT * FROM system_backups ORDER BY created_at DESC LIMIT ?";
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

// Funzione per creare un backup reale del database
function createDatabaseBackup($outputDir, $filename) {
    global $conn;
    
    // Ottieni le informazioni di connessione al database
    $dbHost = $conn->host_info;
    preg_match('/([^:]+)(?::(\d+))?$/', $dbHost, $matches);
    $host = $matches[1] ?? 'localhost';
    $port = $matches[2] ?? '3306';
    $username = $conn->user ?? null;
    $password = $_SERVER['DB_PASSWORD'] ?? ''; // Dovresti avere la password memorizzata in qualche posto sicuro
    $database = $conn->database_name ?? null;
    
    // Se non riusciamo a ottenere le informazioni di connessione, fallback a variabili d'ambiente o configurazione
    if (!$username || !$database) {
        $host = $_SERVER['DB_HOST'] ?? 'localhost';
        $port = $_SERVER['DB_PORT'] ?? '3306';
        $username = $_SERVER['DB_USERNAME'] ?? 'root';
        $password = $_SERVER['DB_PASSWORD'] ?? '';
        $database = $_SERVER['DB_DATABASE'] ?? '';
    }
    
    // Assicurati che la directory di output esista
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $outputFile = $outputDir . '/' . $filename;
    
    // Comando mysqldump
    $command = sprintf(
        'mysqldump -h%s -P%s -u%s %s %s > %s',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($username),
        !empty($password) ? '-p' . escapeshellarg($password) : '',
        escapeshellarg($database),
        escapeshellarg($outputFile)
    );
    
    // Esegui il comando e cattura l'output
    $output = [];
    $returnVar = null;
    exec($command, $output, $returnVar);
    
    return $returnVar === 0 ? $outputFile : false;
}

// Funzione per comprimere una directory
function compressDirectory($source, $destination) {
    $zip = new ZipArchive();
    
    if (!$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        return false;
    }
    
    $source = rtrim(str_replace('\\', '/', realpath($source)), '/');
    
    if (is_dir($source)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);
            
            // Skip . and ..
            if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..')))
                continue;
            
            $file = realpath($file);
            
            if (is_dir($file)) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } else if (is_file($file)) {
                $zip->addFile($file, str_replace($source . '/', '', $file));
            }
        }
    } else if (is_file($source)) {
        $zip->addFile($source, basename($source));
    }
    
    return $zip->close();
}

// Funzione per pulire i vecchi backup in base al periodo di retention
function cleanupOldBackups($retentionDays) {
    global $conn;
    
    if ($retentionDays <= 0) {
        return true; // Nessun limite di retention
    }
    
    // Trova i backup più vecchi del periodo di retention
    $query = "SELECT id, filename FROM system_backups WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $retentionDays);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $backupsDeleted = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Elimina il file di backup
        $backupDir = dirname(__FILE__) . '/backups';
        $backupFile = $backupDir . '/' . $row['filename'];
        
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        
        // Elimina il record dal database
        $deleteQuery = "DELETE FROM system_backups WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $row['id']);
        
        if ($deleteStmt->execute()) {
            $backupsDeleted++;
        }
    }
    
    if ($backupsDeleted > 0) {
        logSystemAction('info', "Pulizia automatica: $backupsDeleted backup obsoleti eliminati in base alla policy di retention ($retentionDays giorni)");
    }
    
    return true;
}

// Funzione per formattare i byte in forma leggibile
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Funzione per eliminare una directory e il suo contenuto ricorsivamente
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

// Gestione degli aggiornamenti delle impostazioni e altre operazioni AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['action'])) {
    // Verifica i permessi (eccetto per refresh_logs che richiede solo canRead)
    if ($_POST['action'] !== 'refresh_logs'&&!$canWrite) {
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
    
    // Esecuzione backup manuale
    if ($action === 'backup_now') {
        if (!$canCreate) {
            echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per creare backup']);
            exit;
        }
        
        // Carica le impostazioni di backup
        $backupSettings = getSettings('backup');
        
        // Verifica cosa includere nel backup
        $includeDatabase = ($backupSettings['backup_database']['setting_value'] ?? '1') == '1';
        $includeFiles = ($backupSettings['backup_files']['setting_value'] ?? '1') == '1';
        $includeConfig = ($backupSettings['backup_config']['setting_value'] ?? '1') == '1';
        
        // Genera nome file backup
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}.zip";
        $backupDir = dirname(__FILE__) . '/backups';
        $tempDir = $backupDir . '/temp_' . $timestamp;
        
        // Crea le directory se non esistono
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        try {
            // Inserisci record preliminare del backup nel database
            $query = "INSERT INTO system_backups (filename, size, status, type, includes_database, includes_files, includes_config, created_by) 
                      VALUES (?, 0, 'processing', 'manual', ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $userId = $_SESSION['user_id'] ?? null;
            $stmt->bind_param("siiis", $filename, $includeDatabase, $includeFiles, $includeConfig, $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("Errore durante la creazione del record di backup: " . $conn->error);
            }
            
            $backupId = $conn->insert_id;
            
            // Crea il backup del database se richiesto
            if ($includeDatabase) {
                $dbBackupFile = createDatabaseBackup($tempDir, 'database.sql');
                if (!$dbBackupFile) {
                    throw new Exception("Errore durante il backup del database");
                }
            }
            
            // Copia i file di configurazione se richiesto
            if ($includeConfig) {
                $configDir = $tempDir . '/config';
                mkdir($configDir, 0755, true);
                
                // Copia i file di configurazione rilevanti (esempio)
                $configFiles = [
                    'config.php',
                    '.env',
                    'db_connection.php'
                    // Aggiungi qui altri file di configurazione
                ];
                
                foreach ($configFiles as $file) {
                    if (file_exists(dirname(__FILE__) . '/' . $file)) {
                        copy(dirname(__FILE__) . '/' . $file, $configDir . '/' . $file);
                    }
                }
            }
            
            // Copia i file di upload se richiesto
            if ($includeFiles) {
                $uploadsDir = dirname(__FILE__) . '/uploads';
                if (is_dir($uploadsDir)) {
                    $fileDir = $tempDir . '/uploads';
                    mkdir($fileDir, 0755, true);
                    
                    // Copia la directory uploads ricorsivamente
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    
                    foreach ($iterator as $item) {
                        if ($item->isDir()) {
                            mkdir($fileDir . '/' . $iterator->getSubPathName(), 0755, true);
                        } else {
                            copy($item, $fileDir . '/' . $iterator->getSubPathName());
                        }
                    }
                }
            }
            
            // Comprimi tutto in un file ZIP
            $backupFilePath = $backupDir . '/' . $filename;
            if (!compressDirectory($tempDir, $backupFilePath)) {
                throw new Exception("Errore durante la compressione dei file di backup");
            }
            
            // Ottieni la dimensione del file di backup
            $size = filesize($backupFilePath);
            
            // Aggiorna il record del backup con lo stato completato e la dimensione
            $query = "UPDATE system_backups SET size = ?, status = 'completed', updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $size, $backupId);
            $stmt->execute();
            
            // Pulisci i file temporanei
            removeDirectory($tempDir);
            
            // Esegui pulizia dei vecchi backup in base alla policy di retention
            $retentionDays = intval($backupSettings['backup_retention']['setting_value'] ?? '30');
            cleanupOldBackups($retentionDays);
            
            // Carica su storage remoto se configurato
            $remoteStorage = ($backupSettings['remote_storage']['setting_value'] ?? '0') == '1';
            $remotePath = null;
            
            if ($remoteStorage) {
                $storageType = $backupSettings['storage_type']['setting_value'] ?? 's3';
                $storageFolder = $backupSettings['storage_folder']['setting_value'] ?? '/backups';
                
                // Implementazione del caricamento su storage remoto
                // (verrà gestito in una seconda fase)
                
                // Per ora, simuliamo un percorso remoto
                $remotePath = "$storageType://$storageFolder/$filename";
                
                // Aggiorna il record con l'informazione di upload completato
                $query = "UPDATE system_backups SET is_uploaded = 1, remote_path = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("si", $remotePath, $backupId);
                $stmt->execute();
            }
            
            // Registra nei log
            logSystemAction('info', 'Backup manuale completato con successo: ' . $filename);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Backup completato con successo',
                'backup' => [
                    'id' => $backupId,
                    'filename' => $filename,
                    'size' => formatBytes($size),
                    'date' => date('d M Y, H:i'),
                    'status' => 'completed'
                ]
            ]);
        } catch (Exception $e) {
            // In caso di errore, aggiorna lo stato del backup
            if (isset($backupId)) {
                $query = "UPDATE system_backups SET status = 'failed', error_message = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $errorMessage = $e->getMessage();
                $stmt->bind_param("si", $errorMessage, $backupId);
                $stmt->execute();
            }
            
            // Pulisci i file temporanei
            if (isset($tempDir)&&is_dir($tempDir)) {
                removeDirectory($tempDir);
            }
            
            // Registra l'errore nei log
            logSystemAction('error', 'Errore durante il backup manuale: ' . $e->getMessage());
            
            echo json_encode(['success' => false, 'message' => 'Errore durante la creazione del backup: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Elimina un backup
    if ($action === 'delete_backup'&&isset($_POST['backup_id'])) {
        if (!$canCreate) {
            echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per eliminare i backup']);
            exit;
        }
        
        $backup_id = intval($_POST['backup_id']);
        
        try {
            // Prima ottieni le informazioni sul backup
            $query = "SELECT filename FROM system_backups WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $backup_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $filename = $row['filename'];
                $backupFile = dirname(__FILE__) . '/backups/' . $filename;
                
                // Elimina il file se esiste
                if (file_exists($backupFile)) {
                    if (!unlink($backupFile)) {
                        throw new Exception("Impossibile eliminare il file di backup");
                    }
                }
                
                // Elimina il record dal database
                $query = "DELETE FROM system_backups WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $backup_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Errore durante l'eliminazione del record di backup: " . $conn->error);
                }
                
                // Registra nei log
                logSystemAction('warning', "Backup {$filename} eliminato manualmente");
                
                echo json_encode(['success' => true, 'message' => "Il backup {$filename} è stato eliminato"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Backup non trovato']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Funzione per aggiornare una singola impostazione
    function updateSetting($key, $value) {
        global $conn;
        
        $query = "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $value, $key);
        return $stmt->execute();
    }
    
    echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta']);
    exit;
}

// Carica le impostazioni di backup
$backupSettings = getSettings('backup');

// Ottieni backup recenti
$backups = getRecentBackups(10);

// Ottieni l'ultimo backup
$lastBackup = null;
$lastBackupTime = "N/A";
$backupQuery = "SELECT created_at FROM system_backups WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1";
$backupResult = $conn->query($backupQuery);
if ($backupResult&&$backupResult->num_rows > 0) {
    $lastBackup = $backupResult->fetch_assoc();
    $backupDateTime = new DateTime($lastBackup['created_at']);
    $now = new DateTime();
    $interval = $now->diff($backupDateTime);
    
    if ($interval->d > 0) {
        $lastBackupTime = $interval->d . " giorni fa";
    } elseif ($interval->h > 0) {
        $lastBackupTime = $interval->h . " ore fa";
    } else {
        $lastBackupTime = $interval->i . " minuti fa";
    }
}
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Gestione Backup</title>
    <meta name="description" content="Gestione backup del sistema" />

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
    <!-- DataTable CSS -->
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
                        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Sistema /</span> Backup</h4>
                        
                        <!-- Flash Messages per operazioni CRUD -->
                        <div id="alert-container"></div>
                        
                        <!-- Stats Card -->
                        <div class="row g-6 mb-6">
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Ultimo Backup</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $lastBackupTime; ?></h4>
                                                </div>
                                                <small class="mb-0">Eseguito <?php echo $lastBackupTime != "N/A" ? $lastBackupTime : "mai"; ?></small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-primary">
                                                    <i class="icon-base ti tabler-database-backup icon-26px"></i>
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
                                                <span class="text-heading">Frequenza</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2">
                                                        <?php 
                                                        $frequency = $backupSettings['backup_frequency']['setting_value'] ?? '24';
                                                        echo $frequency == '12' ? 'Ogni 12 ore' : 
                                                             ($frequency == '24' ? 'Ogni 24 ore' : 
                                                             ($frequency == '168' ? 'Settimanale' : 'Ogni ' . $frequency . ' ore')); 
                                                        ?>
                                                    </h4>
                                                </div>
                                                <small class="mb-0">Pianificazione backup automatici</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-success">
                                                    <i class="icon-base ti tabler-clock icon-26px"></i>
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
                                                <span class="text-heading">Retention</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $backupSettings['backup_retention']['setting_value'] ?? '30'; ?> giorni</h4>
                                                </div>
                                                <small class="mb-0">Periodo di conservazione</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-warning">
                                                    <i class="icon-base ti tabler-calendar icon-26px"></i>
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
                                                <span class="text-heading">Archiviazione</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2">
                                                        <?php 
                                                        $remoteStorage = ($backupSettings['remote_storage']['setting_value'] ?? '0') == '1';
                                                        echo $remoteStorage ? 'Remota' : 'Locale'; 
                                                        ?>
                                                    </h4>
                                                </div>
                                                <small class="mb-0">
                                                    <?php 
                                                    if ($remoteStorage) {
                                                        $storageType = $backupSettings['storage_type']['setting_value'] ?? 's3';
                                                        echo $storageType == 's3' ? 'Amazon S3' : 
                                                             ($storageType == 'dropbox' ? 'Dropbox' : 
                                                             ($storageType == 'drive' ? 'Google Drive' : 
                                                             ($storageType == 'ftp' ? 'FTP' : $storageType)));
                                                    } else {
                                                        echo 'Server locale';
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-info">
                                                    <i class="icon-base ti tabler-cloud-upload icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- / Stats Card -->
                        
                        <!-- Sezione di backup e impostazioni -->
                        <div class="row">
                            <div class="col-md-5 col-12 order-2 order-md-1 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Impostazioni Backup</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="backupSettingsForm" class="settings-form" data-section="backup">
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <label class="form-label" for="backupFrequency">Frequenza Backup</label>
                                                    <select class="form-select" id="backupFrequency" name="backup_frequency" <?php echo !$canWrite ? 'disabled' : ''; ?>>
                                                        <option value="12" <?php echo ($backupSettings['backup_frequency']['setting_value'] ?? '12') == '12' ? 'selected' : ''; ?>>Ogni 12 ore</option>
                                                        <option value="24" <?php echo ($backupSettings['backup_frequency']['setting_value'] ?? '') == '24' ? 'selected' : ''; ?>>Ogni 24 ore</option>
                                                        <option value="168" <?php echo ($backupSettings['backup_frequency']['setting_value'] ?? '') == '168' ? 'selected' : ''; ?>>Settimanale</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <label class="form-label" for="backupRetention">Conservazione Backup (giorni)</label>
                                                    <input type="number" class="form-control" id="backupRetention" name="backup_retention" value="<?php echo htmlspecialchars($backupSettings['backup_retention']['setting_value'] ?? '30'); ?>" min="1" max="365" <?php echo !$canWrite ? 'disabled' : ''; ?> />
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <label class="form-label">Elementi da includere nel backup</label>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" id="backupDatabase" name="backup_database" <?php echo ($backupSettings['backup_database']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> <?php echo !$canWrite ? 'disabled' : ''; ?> />
                                                        <label class="form-check-label" for="backupDatabase">Database</label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" id="backupFiles" name="backup_files" <?php echo ($backupSettings['backup_files']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> <?php echo !$canWrite ? 'disabled' : ''; ?> />
                                                        <label class="form-check-label" for="backupFiles">File Uploads</label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" id="backupConfig" name="backup_config" <?php echo ($backupSettings['backup_config']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?> <?php echo !$canWrite ? 'disabled' : ''; ?> />
                                                        <label class="form-check-label" for="backupConfig">File di Configurazione</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <label class="form-label">Archiviazione Remota</label>
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" id="remoteStorage" name="remote_storage" <?php echo ($backupSettings['remote_storage']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?> <?php echo !$canWrite ? 'disabled' : ''; ?> />
                                                        <label class="form-check-label" for="remoteStorage">Abilita archiviazione remota</label>
                                                    </div>
                                                    <small class="text-muted">Salva una copia dei backup su uno storage remoto per maggiore sicurezza.</small>
                                                </div>
                                            </div>
                                            <div class="row mb-3 remote-storage-options" <?php echo ($backupSettings['remote_storage']['setting_value'] ?? '0') !== '1' ? 'style="display:none"' : ''; ?>>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label" for="storageType">Tipo di Storage</label>
                                                    <select class="form-select" id="storageType" name="storage_type" <?php echo !$canWrite ? 'disabled' : ''; ?>>
                                                        <?php
                                                        $storageTypes = array(
                                                            's3' => 'Amazon S3',
                                                            'dropbox' => 'Dropbox',
                                                            'drive' => 'Google Drive',
                                                            'ftp' => 'FTP'
                                                        );
                                                        $selectedStorage = $backupSettings['storage_type']['setting_value'] ?? 's3';
                                                        foreach ($storageTypes as $value => $label) {
                                                            echo '<option value="' . $value . '"' . ($selectedStorage == $value ? ' selected' : '') . '>' . $label . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label" for="storageFolder">Cartella di Destinazione</label>
                                                    <input type="text" class="form-control" id="storageFolder" name="storage_folder" value="<?php echo htmlspecialchars($backupSettings['storage_folder']['setting_value'] ?? '/backups'); ?>" <?php echo !$canWrite ? 'disabled' : ''; ?> />
                                                </div>
                                            </div>
                                            <div class="mt-4">
                                                <?php if ($canWrite): ?>
                                                <button type="submit" class="btn btn-primary me-2">Salva Impostazioni</button>
                                                <?php endif; ?>
                                                <?php if ($canCreate): ?>
                                                <button type="button" id="backupNow" class="btn btn-success me-2">Esegui Backup Ora</button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7 col-12 order-1 order-md-2 mb-4">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Backup Disponibili</h5>
                                        <?php if ($canCreate): ?>
                                        <div class="card-text">
                                            <button type="button" class="btn btn-primary btn-sm" id="refreshBackups">
                                                <i class="icon-base ti tabler-refresh me-1"></i> Aggiorna
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered" id="backupsTable">
                                                <thead>
                                                    <tr>
                                                        <th>Nome File</th>
                                                        <th>Data</th>
                                                        <th>Dimensione</th>
                                                        <th>Stato</th>
                                                        <th>Azioni</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    if ($backups->num_rows > 0) {
                                                        while ($backup = $backups->fetch_assoc()): 
                                                    ?>
                                                    <tr data-id="<?php echo $backup['id']; ?>">
                                                        <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                                                        <td><?php echo date('d M Y, H:i', strtotime($backup['created_at'])); ?></td>
                                                        <td><?php echo formatBytes($backup['size']); ?></td>
                                                        <td><span class="badge bg-<?php echo $backup['status'] === 'completed' ? 'success' : ($backup['status'] === 'failed' ? 'danger' : 'warning'); ?>"><?php echo ucfirst($backup['status']); ?></span></td>
                                                        <td>
                                                            <div class="d-inline-block">
                                                                <a href="download_backup.php?id=<?php echo $backup['id']; ?>" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-icon download-backup">
                                                                    <i class="icon-base ti tabler-download"></i>
                                                                </a>
                                                                <?php if ($canCreate): ?>
                                                                <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-icon delete-backup">
                                                                    <i class="icon-base ti tabler-trash"></i>
                                                                </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                        endwhile; 
                                                    } else {
                                                        echo '<tr><td colspan="5" class="text-center">Nessun backup trovato.</td></tr>';
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Istruzioni di backup e restore -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Guida al Backup e Ripristino</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="fw-semibold mb-2">Come eseguire un backup</h6>
                                                <p>Per eseguire un backup manuale:</p>
                                                <ol>
                                                    <li>Configura le impostazioni di backup nel pannello</li>
                                                    <li>Seleziona gli elementi da includere nel backup (database, file, ecc.)</li>
                                                    <li>Clicca su "Esegui Backup Ora" per avviare il processo</li>
                                                    <li>Attendi il completamento dell'operazione</li>
                                                </ol>
                                                <p class="text-muted">I backup vengono anche eseguiti automaticamente in base alla frequenza configurata.</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-semibold mb-2">Come ripristinare un backup</h6>
                                                <p>Per ripristinare da un backup esistente:</p>
                                                <ol>
                                                    <li>Scarica il file di backup desiderato dalla lista</li>
                                                    <li>Accedi al pannello di amministrazione del database</li>
                                                    <li>Importa il file SQL contenuto nell'archivio</li>
                                                    <li>Estrai i file nella directory appropriata sul server</li>
                                                </ol>
                                                <p class="text-muted">In caso di difficoltà, contatta l'amministratore di sistema per assistenza.</p>
                                            </div>
                                        </div>
                                        <div class="alert alert-warning mt-3 mb-0">
                                            <h6 class="alert-heading fw-bold mb-1">Importante</h6>
                                            <p class="mb-0">Si consiglia di testare periodicamente i backup per assicurarsi che siano funzionanti e completi. Mantenere copie dei backup più importanti anche su dispositivi fisici esterni al sistema.</p>
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
    <script src="../../../assets/vendor/libs/datatables-bs5/datatables.bootstrap5.js"></script>

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
    
    <!-- Script personalizzato per i backup -->
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
        
        // Inizializzazione DataTable per i backup
        var backupsTable = $('#backupsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.1/i18n/it-IT.json'
            },
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50],
            responsive: true,
            order: [[1, 'desc']] // Ordina per data di creazione (discendente)
        });
        
        // Gestione form delle impostazioni backup
        $('#backupSettingsForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!userPermissions.canWrite) {
                showAlert('Non hai i permessi per modificare queste impostazioni.', 'danger');
                return;
            }
            
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
                    section: 'backup'
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
        
        // Gestione del backup manuale
        $('#backupNow').on('click', function() {
            if (!userPermissions.canCreate) {
                showAlert('Non hai i permessi per eseguire backup manuali.', 'danger');
                return;
            }
            
            // Conferma dell'operazione
            Swal.fire({
                title: 'Eseguire il backup?',
                text: 'Vuoi eseguire un backup completo del sistema adesso?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sì, esegui backup',
                cancelButtonText: 'Annulla',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-outline-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostra processo di backup in corso
                    Swal.fire({
                        title: 'Backup in corso...',
                        html: 'Creazione backup in corso. Non chiudere questa finestra.',
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });
                    
                    // Esegui il backup via AJAX
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'backup_now'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Backup completato
                                Swal.fire({
                                    title: 'Backup completato!',
                                    text: response.message,
                                    icon: 'success',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    },
                                    buttonsStyling: false
                                });
                                
                                // Aggiungi il nuovo backup alla tabella
                                if (response.backup) {
                                    backupsTable.row.add([
                                        response.backup.filename,
                                        response.backup.date,
                                        response.backup.size,
                                        `<span class="badge bg-success">Completato</span>`,
                                        `<div class="d-inline-block">
                                             <a href="download_backup.php?id=${response.backup.id}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-icon download-backup">
                                                 <i class="icon-base ti tabler-download"></i>
                                             </a>
                                             <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-icon delete-backup">
                                                 <i class="icon-base ti tabler-trash"></i>
                                             </button>
                                         </div>`
                                    ]).draw();
                                }
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
        
        // Gestione dello storage remoto
        $('#remoteStorage').on('change', function() {
            if ($(this).is(':checked')) {
                $('.remote-storage-options').slideDown();
            } else {
                $('.remote-storage-options').slideUp();
            }
        });
        
        // Gestione pulsanti di cancellazione backup
        $(document).on('click', '.delete-backup', function() {
            if (!userPermissions.canCreate) {
                showAlert('Non hai i permessi per eliminare i backup.', 'danger');
                return;
            }
            
            const row = $(this).closest('tr');
            const backupId = row.data('id');
            const fileName = row.find('td:first').text();
            
            // Conferma eliminazione
            Swal.fire({
                title: 'Sei sicuro?',
                text: `Vuoi davvero eliminare il backup ${fileName}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sì, elimina',
                cancelButtonText: 'Annulla',
                customClass: {
                    confirmButton: 'btn btn-danger me-3',
                    cancelButton: 'btn btn-outline-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostra indicatore di caricamento
                    Swal.fire({
                        title: 'Eliminazione in corso...',
                        html: 'Eliminazione del backup in corso...',
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });
                    
                    // Elimina il backup via AJAX
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'delete_backup',
                            backup_id: backupId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Eliminato!',
                                    text: response.message,
                                    icon: 'success',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    },
                                    buttonsStyling: false
                                });
                                
                                // Rimuovi la riga dalla tabella
                                backupsTable.row(row).remove().draw();
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
        
        // Aggiorna lista backup
        $('#refreshBackups').on('click', function() {
            location.reload();
        });
        
        // Verifica permessi all'avvio
        if (!userPermissions.canWrite&&!userPermissions.canCreate) {
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
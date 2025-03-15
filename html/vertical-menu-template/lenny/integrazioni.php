<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Verifica l'accesso specifico a questa pagina
if (!userHasAccess('Integrazioni')) {
    // Reindirizza alla pagina di accesso negato
    header("Location: access-denied.php");
    exit;
}

// Ottieni i permessi specifici dell'utente per questa funzionalità
$canRead = userHasPermission('Integrazioni', 'read');
$canWrite = userHasPermission('Integrazioni', 'write');
$canCreate = userHasPermission('Integrazioni', 'create');

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

// Verifica se la tabella integrations esiste e creala se necessario
function ensureIntegrationsTableExists() {
    global $conn;
    
    // Controlla se la tabella esiste
    $checkTable = $conn->query("SHOW TABLES LIKE 'integrations'");
    
    if ($checkTable->num_rows == 0) {
        // La tabella non esiste, creala
        $createTableQuery = "
            CREATE TABLE `integrations` (
              `id` int NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL,
              `type` varchar(50) NOT NULL,
              `description` text,
              `config` text,
              `status` enum('active','inactive') NOT NULL DEFAULT 'inactive',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              `created_by` int DEFAULT NULL,
              `updated_by` int DEFAULT NULL,
              `last_tested_at` timestamp NULL DEFAULT NULL,
              `is_built_in` tinyint(1) NOT NULL DEFAULT '0',
              PRIMARY KEY (`id`),
              KEY `type_index` (`type`),
              KEY `status_index` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
        ";
        
        if ($conn->query($createTableQuery)) {
            logSystemAction('info', 'Tabella integrations creata con successo');
            
            // Inserisci alcune integrazioni di esempio
            $exampleIntegrations = [
                [
                    'name' => 'Stripe',
                    'type' => 'payment',
                    'description' => 'Integrazione per pagamenti con Stripe',
                    'config' => json_encode([
                        'api_key' => '',
                        'api_secret' => '',
                        'mode' => 'test',
                        'currency' => 'EUR',
                        'webhook_url' => ''
                    ]),
                    'status' => 'inactive',
                    'is_built_in' => 1
                ],
                [
                    'name' => 'PayPal',
                    'type' => 'payment',
                    'description' => 'Integrazione per pagamenti con PayPal',
                    'config' => json_encode([
                        'client_id' => '',
                        'client_secret' => '',
                        'mode' => 'sandbox',
                        'currency' => 'EUR',
                        'webhook_url' => ''
                    ]),
                    'status' => 'inactive',
                    'is_built_in' => 1
                ],
                [
                    'name' => 'Firebase',
                    'type' => 'notification',
                    'description' => 'Integrazione per notifiche push con Firebase',
                    'config' => json_encode([
                        'api_key' => '',
                        'project_id' => '',
                        'app_id' => '',
                        'sender_id' => '',
                        'server_key' => ''
                    ]),
                    'status' => 'inactive',
                    'is_built_in' => 1
                ],
                [
                    'name' => 'Facebook',
                    'type' => 'social',
                    'description' => 'Integrazione per login e condivisioni con Facebook',
                    'config' => json_encode([
                        'app_id' => '',
                        'app_secret' => '',
                        'redirect_url' => '',
                        'scopes' => 'email,public_profile'
                    ]),
                    'status' => 'inactive',
                    'is_built_in' => 1
                ],
                [
                    'name' => 'Google',
                    'type' => 'social',
                    'description' => 'Integrazione per login e servizi Google',
                    'config' => json_encode([
                        'client_id' => '',
                        'client_secret' => '',
                        'redirect_url' => '',
                        'scopes' => 'email,profile'
                    ]),
                    'status' => 'inactive',
                    'is_built_in' => 1
                ],
                [
                    'name' => 'Twilio',
                    'type' => 'sms',
                    'description' => 'Integrazione per invio SMS con Twilio',
                    'config' => json_encode([
                        'account_sid' => '',
                        'auth_token' => '',
                        'from_number' => '',
                        'verify_service_sid' => ''
                    ]),
                    'status' => 'inactive',
                    'is_built_in' => 1
                ]
            ];
            
            $insertQuery = "INSERT INTO integrations (name, type, description, config, status, is_built_in) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            
            foreach ($exampleIntegrations as $integration) {
                $stmt->bind_param("sssssi", 
                    $integration['name'],
                    $integration['type'],
                    $integration['description'],
                    $integration['config'],
                    $integration['status'],
                    $integration['is_built_in']
                );
                $stmt->execute();
            }
            
            logSystemAction('info', 'Integrazioni di esempio inserite');
        } else {
            logSystemAction('error', 'Errore nella creazione della tabella integrations: ' . $conn->error);
        }
    }
}

// Assicurati che la tabella integrations esista
ensureIntegrationsTableExists();

// Funzione per ottenere le integrazioni attive
function getActiveIntegrations() {
    global $conn;
    
    $query = "SELECT * FROM integrations WHERE status = 'active' ORDER BY name ASC";
    $result = $conn->query($query);
    
    $integrations = [];
    if ($result&&$result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $integrations[] = $row;
        }
    }
    
    return $integrations;
}

// Funzione per ottenere tutte le integrazioni
function getAllIntegrations() {
    global $conn;
    
    $query = "SELECT * FROM integrations ORDER BY name ASC";
    $result = $conn->query($query);
    
    $integrations = [];
    if ($result&&$result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $integrations[] = $row;
        }
    }
    
    return $integrations;
}

// Funzione per ottenere un'integrazione specifica
function getIntegration($id) {
    global $conn;
    
    $query = "SELECT * FROM integrations WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result&&$result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Funzione per aggiornare lo stato di un'integrazione
function updateIntegrationStatus($id, $status) {
    global $conn;
    
    $query = "UPDATE integrations SET status = ?, updated_at = NOW(), updated_by = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $userId = $_SESSION['user_id'] ?? null;
    $stmt->bind_param("sii", $status, $userId, $id);
    return $stmt->execute();
}

// Funzione per aggiornare la configurazione di un'integrazione
function updateIntegrationConfig($id, $config) {
    global $conn;
    
    $configJson = json_encode($config);
    $query = "UPDATE integrations SET config = ?, updated_at = NOW(), updated_by = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $userId = $_SESSION['user_id'] ?? null;
    $stmt->bind_param("sii", $configJson, $userId, $id);
    return $stmt->execute();
}

// Funzione per aggiungere una nuova integrazione
function addIntegration($name, $type, $description, $config, $status = 'inactive') {
    global $conn;
    
    $configJson = json_encode($config);
    $query = "INSERT INTO integrations (name, type, description, config, status, created_by, updated_by) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $userId = $_SESSION['user_id'] ?? null;
    $stmt->bind_param("sssssii", $name, $type, $description, $configJson, $status, $userId, $userId);
    return $stmt->execute() ? $conn->insert_id : false;
}

// Funzione per eliminare un'integrazione
function deleteIntegration($id) {
    global $conn;
    
    // Prima verifica se è un'integrazione built-in
    $query = "SELECT is_built_in FROM integrations WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result&&$result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['is_built_in'] == 1) {
            // Non permettere l'eliminazione di integrazioni built-in
            return false;
        }
    }
    
    $query = "DELETE FROM integrations WHERE id = ? AND is_built_in = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Funzione per verificare una connessione API
function testApiConnection($url, $method = 'GET', $headers = [], $data = null, $timeout = 10) {
    global $CERT_PATH;
    
    // Inizializza cURL
    $ch = curl_init();
    
    // Imposta l'URL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    
    // Imposta il metodo HTTP
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }
    } elseif ($method != 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data&&($method == 'PUT' || $method == 'PATCH')) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }
    }
    
    // Imposta gli headers
    if (!empty($headers)) {
        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
    }
    
    // Configurazione SSL sicura
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    if (file_exists($CERT_PATH)) {
        curl_setopt($ch, CURLOPT_CAINFO, $CERT_PATH);
    }
    
    // Esegui la richiesta
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    
    curl_close($ch);
    
    return [
        'success' => ($httpCode >= 200&&$httpCode < 300),
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error,
        'info' => $info
    ];
}

// Aggiorna data ultimo test di un'integrazione
function updateIntegrationLastTested($id) {
    global $conn;
    
    $query = "UPDATE integrations SET last_tested_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Gestione delle richieste AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['action'])) {
    // Verifica i permessi (eccetto per refresh_integrations che richiede solo canRead)
    if ($_POST['action'] !== 'refresh_integrations'&&!$canWrite) {
        echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per modificare le integrazioni']);
        exit;
    }
    
    $action = $_POST['action'];
    
    // Aggiorna impostazioni di integrazione
    if ($action === 'update_integration_settings'&&isset($_POST['settings'])&&isset($_POST['section'])) {
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
    
    // Aggiorna stato integrazione
    if ($action === 'update_integration_status'&&isset($_POST['integration_id'])&&isset($_POST['status'])) {
        $integrationId = intval($_POST['integration_id']);
        $status = $_POST['status'] === 'true' ? 'active' : 'inactive';
        
        // Recupera l'integrazione
        $integration = getIntegration($integrationId);
        if (!$integration) {
            echo json_encode(['success' => false, 'message' => 'Integrazione non trovata']);
            exit;
        }
        
        // Aggiorna lo stato
        if (updateIntegrationStatus($integrationId, $status)) {
            // Log dell'azione
            $statusText = $status === 'active' ? 'attivata' : 'disattivata';
            logSystemAction('info', "Integrazione {$integration['name']} $statusText");
            
            echo json_encode(['success' => true, 'message' => "Integrazione {$integration['name']} $statusText con successo"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento dello stato dell\'integrazione']);
        }
        exit;
    }
    
    // Salva configurazione integrazione
    if ($action === 'save_integration_config'&&isset($_POST['integration_id'])&&isset($_POST['config'])) {
        $integrationId = intval($_POST['integration_id']);
        $config = $_POST['config'];
        
        // Recupera l'integrazione
        $integration = getIntegration($integrationId);
        if (!$integration) {
            echo json_encode(['success' => false, 'message' => 'Integrazione non trovata']);
            exit;
        }
        
        // Aggiorna la configurazione
        if (updateIntegrationConfig($integrationId, $config)) {
            // Log dell'azione
            logSystemAction('info', "Configurazione dell'integrazione {$integration['name']} aggiornata");
            
            echo json_encode(['success' => true, 'message' => "Configurazione dell'integrazione {$integration['name']} salvata con successo"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore durante il salvataggio della configurazione']);
        }
        exit;
    }
    
    // Aggiungi nuova integrazione
    if ($action === 'add_integration'&&isset($_POST['name'])&&isset($_POST['type'])&&isset($_POST['config'])) {
        if (!$canCreate) {
            echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per aggiungere integrazioni']);
            exit;
        }
        
        $name = $_POST['name'];
        $type = $_POST['type'];
        $description = $_POST['description'] ?? '';
        $config = $_POST['config'];
        $status = $_POST['status'] ?? 'inactive';
        
        // Verifica che il nome non sia vuoto
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Il nome dell\'integrazione è obbligatorio']);
            exit;
        }
        
        // Aggiungi l'integrazione
        $newId = addIntegration($name, $type, $description, $config, $status);
        if ($newId) {
            // Log dell'azione
            logSystemAction('info', "Nuova integrazione '$name' di tipo '$type' aggiunta");
            
            echo json_encode(['success' => true, 'message' => "Integrazione '$name' aggiunta con successo", 'id' => $newId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiunta dell\'integrazione']);
        }
        exit;
    }
    
    // Elimina integrazione
    if ($action === 'delete_integration'&&isset($_POST['integration_id'])) {
        if (!$canCreate) {
            echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per eliminare integrazioni']);
            exit;
        }
        
        $integrationId = intval($_POST['integration_id']);
        
        // Recupera l'integrazione
        $integration = getIntegration($integrationId);
        if (!$integration) {
            echo json_encode(['success' => false, 'message' => 'Integrazione non trovata']);
            exit;
        }
        
        // Controlla se è un'integrazione built-in
        if ($integration['is_built_in'] == 1) {
            echo json_encode(['success' => false, 'message' => 'Le integrazioni predefinite non possono essere eliminate']);
            exit;
        }
        
        // Elimina l'integrazione
        if (deleteIntegration($integrationId)) {
            // Log dell'azione
            logSystemAction('warning', "Integrazione '{$integration['name']}' eliminata");
            
            echo json_encode(['success' => true, 'message' => "Integrazione '{$integration['name']}' eliminata con successo"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione dell\'integrazione']);
        }
        exit;
    }
    
    // Test connessione API
    if ($action === 'test_api_connection'&&isset($_POST['url'])) {
        $url = $_POST['url'];
        $method = $_POST['method'] ?? 'GET';
        $headers = isset($_POST['headers']) ? json_decode($_POST['headers'], true) : [];
        $data = isset($_POST['data']) ? json_decode($_POST['data'], true) : null;
        $integrationId = isset($_POST['integration_id']) ? intval($_POST['integration_id']) : null;
        
        // Aggiungi eventuali credenziali dalla configurazione
        if (isset($_POST['auth_type'])&&isset($_POST['auth_user'])&&isset($_POST['auth_pass'])) {
            $authType = $_POST['auth_type'];
            $authUser = $_POST['auth_user'];
            $authPass = $_POST['auth_pass'];
            
            if ($authType === 'basic') {
                $headers['Authorization'] = 'Basic ' . base64_encode("$authUser:$authPass");
            } elseif ($authType === 'bearer') {
                $headers['Authorization'] = 'Bearer ' . $authPass;
            }
        }
        
        // Esegui il test
        $result = testApiConnection($url, $method, $headers, $data);
        
        // Aggiorna timestamp dell'ultimo test
        if ($integrationId) {
            updateIntegrationLastTested($integrationId);
        }
        
        // Prepara la risposta
        $response = [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Connessione riuscita' : 'Errore di connessione: ' . $result['error'],
            'http_code' => $result['http_code'],
            'response' => is_string($result['response']) ? $result['response'] : json_encode($result['response'])
        ];
        
        // Log del test
        $logLevel = $result['success'] ? 'info' : 'warning';
        logSystemAction($logLevel, "Test API a $url: " . ($result['success'] ? 'successo' : 'fallito') . " (HTTP {$result['http_code']})");
        
        echo json_encode($response);
        exit;
    }
    
    // Test webhook
    if ($action === 'test_webhook'&&isset($_POST['url'])) {
        $url = $_POST['url'];
        $method = $_POST['method'] ?? 'POST';
        $contentType = $_POST['content_type'] ?? 'application/json';
        $integrationId = isset($_POST['integration_id']) ? intval($_POST['integration_id']) : null;
        
        // Prepara i dati di test
        $testData = [
            'event' => 'test_webhook',
            'timestamp' => date('Y-m-d H:i:s'),
            'test_id' => uniqid('test_'),
            'source' => 'admin_panel'
        ];
        
        // Prepara gli headers
        $headers = [
            'Content-Type' => $contentType,
            'X-Webhook-Test' => 'true'
        ];
        
        // Converti i dati in base al content type
        $postData = $testData;
        if ($contentType === 'application/json') {
            $postData = json_encode($testData);
        } elseif ($contentType === 'application/x-www-form-urlencoded') {
            $postData = http_build_query($testData);
        }
        
        // Esegui il test
        $result = testApiConnection($url, $method, $headers, $postData);
        
        // Aggiorna timestamp dell'ultimo test
        if ($integrationId) {
            updateIntegrationLastTested($integrationId);
        }
        
        // Prepara la risposta
        $response = [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Webhook inviato con successo' : 'Errore invio webhook: ' . $result['error'],
            'http_code' => $result['http_code'],
            'response' => is_string($result['response']) ? $result['response'] : json_encode($result['response']),
            'data_sent' => $testData
        ];
        
        // Log del test
        $logLevel = $result['success'] ? 'info' : 'warning';
        logSystemAction($logLevel, "Test webhook a $url: " . ($result['success'] ? 'successo' : 'fallito') . " (HTTP {$result['http_code']})");
        
        echo json_encode($response);
        exit;
    }
    
    // Aggiorna tutte le integrazioni
    if ($action === 'refresh_integrations') {
        $integrations = getAllIntegrations();
        echo json_encode(['success' => true, 'integrations' => $integrations]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta']);
    exit;
}

// Carica le impostazioni delle integrazioni
$socialSettings = getSettings('social');
$paymentSettings = getSettings('payment');
$notificationSettings = getSettings('notifications');

// Carica tutte le integrazioni disponibili
$integrations = getAllIntegrations();

// Conta le integrazioni attive
$activeIntegrations = array_filter($integrations, function($item) {
    return $item['status'] === 'active';
});
$activeCount = count($activeIntegrations);
$totalCount = count($integrations);

// Ottieni le integrazioni per categoria
$socialIntegrations = array_filter($integrations, function($item) {
    return $item['type'] === 'social';
});

$paymentIntegrations = array_filter($integrations, function($item) {
    return $item['type'] === 'payment';
});

$notificationIntegrations = array_filter($integrations, function($item) {
    return $item['type'] === 'notification';
});

$apiIntegrations = array_filter($integrations, function($item) {
    return $item['type'] === 'api';
});

$smsIntegrations = array_filter($integrations, function($item) {
    return $item['type'] === 'sms';
});

$otherIntegrations = array_filter($integrations, function($item) {
    return !in_array($item['type'], ['social', 'payment', 'notification', 'api', 'sms']);
});
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Integrazioni</title>
    <meta name="description" content="Gestione integrazioni con servizi esterni" />

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
    <link rel="stylesheet" href="../../../assets/vendor/libs/formvalidation/dist/css/formValidation.min.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/select2/select2.css" />

    <!-- Helpers -->
    <script src="../../../assets/vendor/js/helpers.js"></script>
    <script src="../../../assets/vendor/js/template-customizer.js"></script>
    <script src="../../../assets/js/config.js"></script>
    
    <style>
        .integration-card {
            transition: all 0.3s ease;
        }
        
        .integration-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .integration-logo {
            height: 60px;
            width: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .integration-badge {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        
        .config-json {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', 'source-code-pro', monospace;
            font-size: 12px;
        }
        
        .built-in-badge {
            position: absolute;
            top: 16px;
            right: 90px;
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
                        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Sistema /</span> Integrazioni</h4>
                        
                        <!-- Flash Messages per operazioni CRUD -->
                        <div id="alert-container"></div>
                        
                        <!-- Stats Cards -->
                        <div class="row g-6 mb-6">
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Integrazioni Attive</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $activeCount; ?>/<?php echo $totalCount; ?></h4>
                                                </div>
                                                <small class="mb-0">Servizi collegati</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-primary">
                                                    <i class="icon-base ti tabler-puzzle icon-26px"></i>
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
                                                <span class="text-heading">Pagamenti</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo count($paymentIntegrations); ?></h4>
                                                </div>
                                                <small class="mb-0">Gateway di pagamento</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-success">
                                                    <i class="icon-base ti tabler-credit-card icon-26px"></i>
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
                                                <span class="text-heading">Social</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo count($socialIntegrations); ?></h4>
                                                </div>
                                                <small class="mb-0">Connessioni social media</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-warning">
                                                    <i class="icon-base ti tabler-share icon-26px"></i>
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
                                                <span class="text-heading">Notifiche</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo count($notificationIntegrations); ?></h4>
                                                </div>
                                                <small class="mb-0">Servizi di notifica</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-info">
                                                    <i class="icon-base ti tabler-bell icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- / Stats Cards -->
                        
                        <!-- Tutte le integrazioni -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Tutte le Integrazioni</h5>
                                <?php if ($canCreate): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addIntegrationModal">
                                    <i class="icon-base ti tabler-plus me-1"></i> Aggiungi Integrazione
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item">
                                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">
                                            Tutte
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab" aria-controls="payment" aria-selected="false">
                                            Pagamenti
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" type="button" role="tab" aria-controls="social" aria-selected="false">
                                            Social
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" id="notification-tab" data-bs-toggle="tab" data-bs-target="#notification" type="button" role="tab" aria-controls="notification" aria-selected="false">
                                            Notifiche
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" id="sms-tab" data-bs-toggle="tab" data-bs-target="#sms" type="button" role="tab" aria-controls="sms" aria-selected="false">
                                            SMS
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" id="api-tab" data-bs-toggle="tab" data-bs-target="#api" type="button" role="tab" aria-controls="api" aria-selected="false">
                                            API
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" id="other-tab" data-bs-toggle="tab" data-bs-target="#other" type="button" role="tab" aria-controls="other" aria-selected="false">
                                            Altro
                                        </button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content pt-4">
                                    <!-- Tutte le integrazioni -->
                                    <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">
                                            <?php if (count($integrations) > 0): ?>
                                                <?php foreach ($integrations as $integration): ?>
                                                    <div class="col">
                                                        <div class="card integration-card h-100">
                                                            <div class="card-body">
                                                                <span class="badge integration-badge bg-label-<?php echo $integration['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $integration['status'] === 'active' ? 'Attiva' : 'Inattiva'; ?>
                                                                </span>
                                                                <?php if ($integration['is_built_in']): ?>
                                                                <span class="badge built-in-badge bg-label-primary">Predefinita</span>
                                                                <?php endif; ?>
                                                                <div class="d-flex flex-column align-items-start">
                                                                    <div class="integration-logo rounded bg-label-<?php 
                                                                        echo $integration['type'] === 'social' ? 'warning' : 
                                                                            ($integration['type'] === 'payment' ? 'success' : 
                                                                            ($integration['type'] === 'notification' ? 'info' : 
                                                                            ($integration['type'] === 'api' ? 'primary' : 
                                                                            ($integration['type'] === 'sms' ? 'danger' : 'dark')))); 
                                                                    ?>">
                                                                        <i class="icon-base ti <?php 
                                                                            echo $integration['type'] === 'social' ? 'tabler-share' : 
                                                                                ($integration['type'] === 'payment' ? 'tabler-credit-card' : 
                                                                                ($integration['type'] === 'notification' ? 'tabler-bell' : 
                                                                                ($integration['type'] === 'api' ? 'tabler-api-app' : 
                                                                                ($integration['type'] === 'sms' ? 'tabler-message-2' : 'tabler-puzzle')))); 
                                                                        ?>"></i>
                                                                    </div>
                                                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($integration['name']); ?></h5>
                                                                    <p class="card-text mb-0 text-muted"><?php echo htmlspecialchars($integration['description']); ?></p>
                                                                </div>
                                                                <div class="d-flex align-items-center pt-3 mt-3 border-top">
                                                                    <small class="text-muted">
                                                                        <?php if ($integration['last_tested_at']): ?>
                                                                        <i class="icon-base ti tabler-clock-check me-1"></i> 
                                                                        Testato: <?php echo date('d/m/Y H:i', strtotime($integration['last_tested_at'])); ?>
                                                                        <?php else: ?>
                                                                        <i class="icon-base ti tabler-clock-hour-3 me-1"></i> 
                                                                        Non ancora testato
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <div class="ms-auto">
                                                                        <?php if ($canWrite): ?>
                                                                        <div class="form-check form-switch me-3 d-inline-block">
                                                                            <input class="form-check-input integration-toggle" type="checkbox" 
                                                                                data-id="<?php echo $integration['id']; ?>" 
                                                                                id="integration-toggle-<?php echo $integration['id']; ?>" 
                                                                                <?php echo $integration['status'] === 'active' ? 'checked' : ''; ?>>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-primary btn-sm view-integration" data-id="<?php echo $integration['id']; ?>">
                                                                            <i class="icon-base ti tabler-settings me-1"></i> Configura
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12 text-center py-5">
                                                    <div class="mb-4">
                                                        <i class="icon-base ti tabler-puzzle-off" style="font-size: 48px;"></i>
                                                    </div>
                                                    <h5>Nessuna integrazione configurata</h5>
                                                    <p class="text-muted">Aggiungi nuove integrazioni per connettere il sistema a servizi esterni</p>
                                                    <?php if ($canCreate): ?>
                                                    <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addIntegrationModal">
                                                        <i class="icon-base ti tabler-plus me-1"></i> Aggiungi la prima integrazione
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Pagamenti -->
                                    <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
                                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">
                                            <?php if (count($paymentIntegrations) > 0): ?>
                                                <?php foreach ($paymentIntegrations as $integration): ?>
                                                    <div class="col">
                                                        <div class="card integration-card h-100">
                                                            <div class="card-body">
                                                                <span class="badge integration-badge bg-label-<?php echo $integration['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $integration['status'] === 'active' ? 'Attiva' : 'Inattiva'; ?>
                                                                </span>
                                                                <?php if ($integration['is_built_in']): ?>
                                                                <span class="badge built-in-badge bg-label-primary">Predefinita</span>
                                                                <?php endif; ?>
                                                                <div class="d-flex flex-column align-items-start">
                                                                    <div class="integration-logo rounded bg-label-success">
                                                                        <i class="icon-base ti tabler-credit-card"></i>
                                                                    </div>
                                                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($integration['name']); ?></h5>
                                                                    <p class="card-text mb-0 text-muted"><?php echo htmlspecialchars($integration['description']); ?></p>
                                                                </div>
                                                                <div class="d-flex align-items-center pt-3 mt-3 border-top">
                                                                    <small class="text-muted">
                                                                        <?php if ($integration['last_tested_at']): ?>
                                                                        <i class="icon-base ti tabler-clock-check me-1"></i> 
                                                                        Testato: <?php echo date('d/m/Y H:i', strtotime($integration['last_tested_at'])); ?>
                                                                        <?php else: ?>
                                                                        <i class="icon-base ti tabler-clock-hour-3 me-1"></i> 
                                                                        Non ancora testato
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <div class="ms-auto">
                                                                        <?php if ($canWrite): ?>
                                                                        <div class="form-check form-switch me-3 d-inline-block">
                                                                            <input class="form-check-input integration-toggle" type="checkbox" 
                                                                                data-id="<?php echo $integration['id']; ?>" 
                                                                                id="integration-toggle-payment-<?php echo $integration['id']; ?>" 
                                                                                <?php echo $integration['status'] === 'active' ? 'checked' : ''; ?>>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-primary btn-sm view-integration" data-id="<?php echo $integration['id']; ?>">
                                                                            <i class="icon-base ti tabler-settings me-1"></i> Configura
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12 text-center py-5">
                                                    <div class="mb-4">
                                                        <i class="icon-base ti tabler-credit-card" style="font-size: 48px;"></i>
                                                    </div>
                                                    <h5>Nessun gateway di pagamento configurato</h5>
                                                    <p class="text-muted">Aggiungi gateway di pagamento per accettare transazioni online</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Social -->
                                    <div class="tab-pane fade" id="social" role="tabpanel" aria-labelledby="social-tab">
                                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">
                                            <?php if (count($socialIntegrations) > 0): ?>
                                                <?php foreach ($socialIntegrations as $integration): ?>
                                                    <div class="col">
                                                        <div class="card integration-card h-100">
                                                            <div class="card-body">
                                                                <span class="badge integration-badge bg-label-<?php echo $integration['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $integration['status'] === 'active' ? 'Attiva' : 'Inattiva'; ?>
                                                                </span>
                                                                <?php if ($integration['is_built_in']): ?>
                                                                <span class="badge built-in-badge bg-label-primary">Predefinita</span>
                                                                <?php endif; ?>
                                                                <div class="d-flex flex-column align-items-start">
                                                                    <div class="integration-logo rounded bg-label-warning">
                                                                        <i class="icon-base ti tabler-share"></i>
                                                                    </div>
                                                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($integration['name']); ?></h5>
                                                                    <p class="card-text mb-0 text-muted"><?php echo htmlspecialchars($integration['description']); ?></p>
                                                                </div>
                                                                <div class="d-flex align-items-center pt-3 mt-3 border-top">
                                                                    <small class="text-muted">
                                                                        <?php if ($integration['last_tested_at']): ?>
                                                                        <i class="icon-base ti tabler-clock-check me-1"></i> 
                                                                        Testato: <?php echo date('d/m/Y H:i', strtotime($integration['last_tested_at'])); ?>
                                                                        <?php else: ?>
                                                                        <i class="icon-base ti tabler-clock-hour-3 me-1"></i> 
                                                                        Non ancora testato
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <div class="ms-auto">
                                                                        <?php if ($canWrite): ?>
                                                                        <div class="form-check form-switch me-3 d-inline-block">
                                                                            <input class="form-check-input integration-toggle" type="checkbox" 
                                                                                data-id="<?php echo $integration['id']; ?>" 
                                                                                id="integration-toggle-social-<?php echo $integration['id']; ?>" 
                                                                                <?php echo $integration['status'] === 'active' ? 'checked' : ''; ?>>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-primary btn-sm view-integration" data-id="<?php echo $integration['id']; ?>">
                                                                            <i class="icon-base ti tabler-settings me-1"></i> Configura
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12 text-center py-5">
                                                    <div class="mb-4">
                                                        <i class="icon-base ti tabler-share" style="font-size: 48px;"></i>
                                                    </div>
                                                    <h5>Nessuna integrazione social configurata</h5>
                                                    <p class="text-muted">Aggiungi integrazioni con i social network per migliorare l'esperienza utente</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Notifiche -->
                                    <div class="tab-pane fade" id="notification" role="tabpanel" aria-labelledby="notification-tab">
                                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">
                                            <?php if (count($notificationIntegrations) > 0): ?>
                                                <?php foreach ($notificationIntegrations as $integration): ?>
                                                    <div class="col">
                                                        <div class="card integration-card h-100">
                                                            <div class="card-body">
                                                                <span class="badge integration-badge bg-label-<?php echo $integration['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $integration['status'] === 'active' ? 'Attiva' : 'Inattiva'; ?>
                                                                </span>
                                                                <?php if ($integration['is_built_in']): ?>
                                                                <span class="badge built-in-badge bg-label-primary">Predefinita</span>
                                                                <?php endif; ?>
                                                                <div class="d-flex flex-column align-items-start">
                                                                    <div class="integration-logo rounded bg-label-info">
                                                                        <i class="icon-base ti tabler-bell"></i>
                                                                    </div>
                                                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($integration['name']); ?></h5>
                                                                    <p class="card-text mb-0 text-muted"><?php echo htmlspecialchars($integration['description']); ?></p>
                                                                </div>
                                                                <div class="d-flex align-items-center pt-3 mt-3 border-top">
                                                                    <small class="text-muted">
                                                                        <?php if ($integration['last_tested_at']): ?>
                                                                        <i class="icon-base ti tabler-clock-check me-1"></i> 
                                                                        Testato: <?php echo date('d/m/Y H:i', strtotime($integration['last_tested_at'])); ?>
                                                                        <?php else: ?>
                                                                        <i class="icon-base ti tabler-clock-hour-3 me-1"></i> 
                                                                        Non ancora testato
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <div class="ms-auto">
                                                                        <?php if ($canWrite): ?>
                                                                        <div class="form-check form-switch me-3 d-inline-block">
                                                                            <input class="form-check-input integration-toggle" type="checkbox" 
                                                                                data-id="<?php echo $integration['id']; ?>" 
                                                                                id="integration-toggle-notification-<?php echo $integration['id']; ?>" 
                                                                                <?php echo $integration['status'] === 'active' ? 'checked' : ''; ?>>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-primary btn-sm view-integration" data-id="<?php echo $integration['id']; ?>">
                                                                            <i class="icon-base ti tabler-settings me-1"></i> Configura
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12 text-center py-5">
                                                    <div class="mb-4">
                                                        <i class="icon-base ti tabler-bell" style="font-size: 48px;"></i>
                                                    </div>
                                                    <h5>Nessuna integrazione per notifiche configurata</h5>
                                                    <p class="text-muted">Aggiungi servizi di notifica per migliorare la comunicazione con gli utenti</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- SMS -->
                                    <div class="tab-pane fade" id="sms" role="tabpanel" aria-labelledby="sms-tab">
                                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">
                                            <?php if (count($smsIntegrations) > 0): ?>
                                                <?php foreach ($smsIntegrations as $integration): ?>
                                                    <div class="col">
                                                        <div class="card integration-card h-100">
                                                            <div class="card-body">
                                                                <span class="badge integration-badge bg-label-<?php echo $integration['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $integration['status'] === 'active' ? 'Attiva' : 'Inattiva'; ?>
                                                                </span>
                                                                <?php if ($integration['is_built_in']): ?>
                                                                <span class="badge built-in-badge bg-label-primary">Predefinita</span>
                                                                <?php endif; ?>
                                                                <div class="d-flex flex-column align-items-start">
                                                                    <div class="integration-logo rounded bg-label-danger">
                                                                        <i class="icon-base ti tabler-message-2"></i>
                                                                    </div>
                                                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($integration['name']); ?></h5>
                                                                    <p class="card-text mb-0 text-muted"><?php echo htmlspecialchars($integration['description']); ?></p>
                                                                </div>
                                                                <div class="d-flex align-items-center pt-3 mt-3 border-top">
                                                                    <small class="text-muted">
                                                                        <?php if ($integration['last_tested_at']): ?>
                                                                        <i class="icon-base ti tabler-clock-check me-1"></i> 
                                                                        Testato: <?php echo date('d/m/Y H:i', strtotime($integration['last_tested_at'])); ?>
                                                                        <?php else: ?>
                                                                        <i class="icon-base ti tabler-clock-hour-3 me-1"></i> 
                                                                        Non ancora testato
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <div class="ms-auto">
                                                                        <?php if ($canWrite): ?>
                                                                        <div class="form-check form-switch me-3 d-inline-block">
                                                                            <input class="form-check-input integration-toggle" type="checkbox" 
                                                                                data-id="<?php echo $integration['id']; ?>" 
                                                                                id="integration-toggle-sms-<?php echo $integration['id']; ?>" 
                                                                                <?php echo $integration['status'] === 'active' ? 'checked' : ''; ?>>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-primary btn-sm view-integration" data-id="<?php echo $integration['id']; ?>">
                                                                            <i class="icon-base ti tabler-settings me-1"></i> Configura
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12 text-center py-5">
                                                    <div class="mb-4">
                                                        <i class="icon-base ti tabler-message-2" style="font-size: 48px;"></i>
                                                    </div>
                                                    <h5>Nessuna integrazione SMS configurata</h5>
                                                    <p class="text-muted">Aggiungi servizi SMS per inviare messaggi di testo ai tuoi utenti</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- API -->
                                    <div class="tab-pane fade" id="api" role="tabpanel" aria-labelledby="api-tab">
                                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">
                                            <?php if (count($apiIntegrations) > 0): ?>
                                                <?php foreach ($apiIntegrations as $integration): ?>
                                                    <div class="col">
                                                        <div class="card integration-card h-100">
                                                            <div class="card-body">
                                                                <span class="badge integration-badge bg-label-<?php echo $integration['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $integration['status'] === 'active' ? 'Attiva' : 'Inattiva'; ?>
                                                                </span>
                                                                <?php if ($integration['is_built_in']): ?>
                                                                <span class="badge built-in-badge bg-label-primary">Predefinita</span>
                                                                <?php endif; ?>
                                                                <div class="d-flex flex-column align-items-start">
                                                                    <div class="integration-logo rounded bg-label-primary">
                                                                        <i class="icon-base ti tabler-api-app"></i>
                                                                    </div>
                                                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($integration['name']); ?></h5>
                                                                    <p class="card-text mb-0 text-muted"><?php echo htmlspecialchars($integration['description']); ?></p>
                                                                </div>
                                                                <div class="d-flex align-items-center pt-3 mt-3 border-top">
                                                                    <small class="text-muted">
                                                                        <?php if ($integration['last_tested_at']): ?>
                                                                        <i class="icon-base ti tabler-clock-check me-1"></i> 
                                                                        Testato: <?php echo date('d/m/Y H:i', strtotime($integration['last_tested_at'])); ?>
                                                                        <?php else: ?>
                                                                        <i class="icon-base ti tabler-clock-hour-3 me-1"></i> 
                                                                        Non ancora testato
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <div class="ms-auto">
                                                                        <?php if ($canWrite): ?>
                                                                        <div class="form-check form-switch me-3 d-inline-block">
                                                                            <input class="form-check-input integration-toggle" type="checkbox" 
                                                                                data-id="<?php echo $integration['id']; ?>" 
                                                                                id="integration-toggle-api-<?php echo $integration['id']; ?>" 
                                                                                <?php echo $integration['status'] === 'active' ? 'checked' : ''; ?>>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-primary btn-sm view-integration" data-id="<?php echo $integration['id']; ?>">
                                                                            <i class="icon-base ti tabler-settings me-1"></i> Configura
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12 text-center py-5">
                                                    <div class="mb-4">
                                                        <i class="icon-base ti tabler-api-app" style="font-size: 48px;"></i>
                                                    </div>
                                                    <h5>Nessuna integrazione API configurata</h5>
                                                    <p class="text-muted">Aggiungi API esterne per estendere le funzionalità del sistema</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Altro -->
                                    <div class="tab-pane fade" id="other" role="tabpanel" aria-labelledby="other-tab">
                                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">
                                            <?php if (count($otherIntegrations) > 0): ?>
                                                <?php foreach ($otherIntegrations as $integration): ?>
                                                    <div class="col">
                                                        <div class="card integration-card h-100">
                                                            <div class="card-body">
                                                                <span class="badge integration-badge bg-label-<?php echo $integration['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $integration['status'] === 'active' ? 'Attiva' : 'Inattiva'; ?>
                                                                </span>
                                                                <?php if ($integration['is_built_in']): ?>
                                                                <span class="badge built-in-badge bg-label-primary">Predefinita</span>
                                                                <?php endif; ?>
                                                                <div class="d-flex flex-column align-items-start">
                                                                    <div class="integration-logo rounded bg-label-dark">
                                                                        <i class="icon-base ti tabler-puzzle"></i>
                                                                    </div>
                                                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($integration['name']); ?></h5>
                                                                    <p class="card-text mb-0 text-muted"><?php echo htmlspecialchars($integration['description']); ?></p>
                                                                </div>
                                                                <div class="d-flex align-items-center pt-3 mt-3 border-top">
                                                                    <small class="text-muted">
                                                                        <?php if ($integration['last_tested_at']): ?>
                                                                        <i class="icon-base ti tabler-clock-check me-1"></i> 
                                                                        Testato: <?php echo date('d/m/Y H:i', strtotime($integration['last_tested_at'])); ?>
                                                                        <?php else: ?>
                                                                        <i class="icon-base ti tabler-clock-hour-3 me-1"></i> 
                                                                        Non ancora testato
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <div class="ms-auto">
                                                                        <?php if ($canWrite): ?>
                                                                        <div class="form-check form-switch me-3 d-inline-block">
                                                                            <input class="form-check-input integration-toggle" type="checkbox" 
                                                                                data-id="<?php echo $integration['id']; ?>" 
                                                                                id="integration-toggle-other-<?php echo $integration['id']; ?>" 
                                                                                <?php echo $integration['status'] === 'active' ? 'checked' : ''; ?>>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-primary btn-sm view-integration" data-id="<?php echo $integration['id']; ?>">
                                                                            <i class="icon-base ti tabler-settings me-1"></i> Configura
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12 text-center py-5">
                                                    <div class="mb-4">
                                                        <i class="icon-base ti tabler-puzzle" style="font-size: 48px;"></i>
                                                    </div>
                                                    <h5>Nessuna integrazione aggiuntiva configurata</h5>
                                                    <p class="text-muted">Aggiungi altri tipi di integrazioni per espandere il sistema</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Guide per sviluppatori -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Guide per Sviluppatori</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-4 mb-md-0">
                                        <div class="card bg-lighter h-100">
                                            <div class="card-body">
                                                <h6 class="fw-semibold mb-2">API Documentation</h6>
                                                <p class="mb-3">Scopri come integrare le tue applicazioni con le nostre API RESTful.</p>
                                                <a href="#" class="btn btn-primary btn-sm">Esplora API Docs</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4 mb-md-0">
                                        <div class="card bg-lighter h-100">
                                            <div class="card-body">
                                                <h6 class="fw-semibold mb-2">Webhook Setup</h6>
                                                <p class="mb-3">Configura webhook per ricevere aggiornamenti in tempo reale dal sistema.</p>
                                                <a href="#" class="btn btn-primary btn-sm">Guida Webhook</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-lighter h-100">
                                            <div class="card-body">
                                                <h6 class="fw-semibold mb-2">Plugin Development</h6>
                                                <p class="mb-3">Sviluppa plugin personalizzati per estendere la funzionalità del sistema.</p>
                                                <a href="#" class="btn btn-primary btn-sm">Documentazione Plugin</a>
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
    
    <!-- Modal Visualizza/Modifica Integrazione -->
    <div class="modal fade" id="integrationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="integrationModalTitle">Configura Integrazione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="configureIntegrationForm">
                        <input type="hidden" id="integration_id" name="integration_id" value="">
                        <input type="hidden" id="integration_is_built_in" name="integration_is_built_in" value="0">
                        
                        <div class="mb-3">
                            <label class="form-label" for="integration_name">Nome</label>
                            <input type="text" class="form-control" id="integration_name" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="integration_type">Tipo</label>
                            <input type="text" class="form-control" id="integration_type" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="integration_description">Descrizione</label>
                            <textarea class="form-control" id="integration_description" disabled rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="integration_status">Stato</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="integration_status" <?php echo !$canWrite ? 'disabled' : ''; ?>>
                                <label class="form-check-label" for="integration_status">Attiva/Disattiva integrazione</label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label" for="integration_config">Configurazione</label>
                            <div class="alert alert-info small mb-2">
                                <i class="icon-base ti tabler-info-circle me-1"></i>
                                Configura i parametri richiesti per questa integrazione. I campi variano in base al tipo di servizio.
                            </div>
                            <div id="config_fields_container">
                                <!-- I campi di configurazione dinamici saranno inseriti qui -->
                            </div>
                        </div>
                        
                        <div class="mb-0">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span>Test Connessione</span>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="testApiButton">
                                        Test API
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-1" id="testWebhookButton">
                                        Test Webhook
                                    </button>
                                </div>
                            </label>
                            <div id="test_result" class="alert d-none mt-2"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <?php if ($canCreate): ?>
                    <button type="button" class="btn btn-danger me-auto" id="deleteIntegrationBtn">Elimina</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
                    <?php if ($canWrite): ?>
                    <button type="button" class="btn btn-primary" id="saveIntegrationBtn">Salva Configurazione</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Aggiungi Integrazione -->
    <div class="modal fade" id="addIntegrationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Nuova Integrazione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addIntegrationForm">
                        <div class="mb-3">
                            <label class="form-label" for="new_integration_name">Nome Integrazione <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_integration_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="new_integration_type">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select" id="new_integration_type" name="type" required>
                                <option value="">Seleziona tipo...</option>
                                <option value="api">API</option>
                                <option value="payment">Pagamento</option>
                                <option value="social">Social</option>
                                <option value="notification">Notifica</option>
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                                <option value="analytics">Analytics</option>
                                <option value="crm">CRM</option>
                                <option value="other">Altro</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="new_integration_description">Descrizione</label>
                            <textarea class="form-control" id="new_integration_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="new_integration_status">Stato</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="new_integration_status" checked>
                                <label class="form-check-label" for="new_integration_status">Attiva/Disattiva integrazione</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="new_integration_config">Configurazione Iniziale (JSON)</label>
                            <div class="alert alert-info small mb-2">
                                <i class="icon-base ti tabler-info-circle me-1"></i>
                                Inserisci le configurazioni iniziali in formato JSON. Potrai modificarle successivamente.
                            </div>
                            <textarea class="form-control config-json" id="new_integration_config" name="config" rows="5">{}</textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="addIntegrationBtn">Aggiungi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="../../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../../assets/vendor/libs/node-waves/node-waves.js"></script>
    <script src="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../../assets/vendor/js/menu.js"></script>

    <!-- Vendors JS -->
    <script src="../../../assets/vendor/libs/sweetalert2/sweetalert2.js"></script>
    <script src="../../../assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js"></script>
    <script src="../../../assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js"></script>
    <script src="../../../assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js"></script>
    <script src="../../../assets/vendor/libs/select2/select2.js"></script>

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
    
    <!-- Script personalizzato per integrazioni -->
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
        
        // Gestione click su visualizza integrazione
        $(document).on('click', '.view-integration', function() {
            const integrationId = $(this).data('id');
            
            // Cerca l'integrazione nelle integrazioni disponibili
            const integration = <?php echo json_encode($integrations); ?>.find(item => item.id == integrationId);
            
            if (integration) {
                // Riempi i campi del modal
                $('#integration_id').val(integration.id);
                $('#integration_name').val(integration.name);
                $('#integration_type').val(integration.type);
                $('#integration_description').val(integration.description);
                $('#integration_status').prop('checked', integration.status === 'active');
                $('#integration_is_built_in').val(integration.is_built_in);
                
                // Nascondi o mostra il pulsante Elimina in base a is_built_in
                if (integration.is_built_in == 1) {
                    $('#deleteIntegrationBtn').hide();
                } else {
                    $('#deleteIntegrationBtn').show();
                }
                
                // Genera campi di configurazione in base al tipo di integrazione
                generateConfigFields(integration);
                
                // Mostra il modal
                $('#integrationModal').modal('show');
            } else {
                showAlert('Errore nel caricamento dell\'integrazione', 'danger');
            }
        });
        
        // Funzione per generare i campi di configurazione
        function generateConfigFields(integration) {
            const configContainer = $('#config_fields_container');
            configContainer.empty();
            
            const config = typeof integration.config === 'string' ? 
                          JSON.parse(integration.config) : integration.config;
            
            // Crea i campi in base al tipo di integrazione
            switch (integration.type) {
                case 'api':
                    configContainer.append(`
                        <div class="mb-3">
                            <label class="form-label" for="api_url">API URL</label>
                            <input type="text" class="form-control" id="api_url" name="config[api_url]" value="${config.api_url || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="api_key">API Key</label>
                            <input type="password" class="form-control" id="api_key" name="config[api_key]" value="${config.api_key || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="api_secret">API Secret</label>
                            <input type="password" class="form-control" id="api_secret" name="config[api_secret]" value="${config.api_secret || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="api_version">API Version</label>
                            <input type="text" class="form-control" id="api_version" name="config[api_version]" value="${config.api_version || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="api_timeout">Timeout (seconds)</label>
                            <input type="number" class="form-control" id="api_timeout" name="config[api_timeout]" value="${config.api_timeout || '30'}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                    `);
                    break;
                    
                case 'payment':
                    configContainer.append(`
                        <div class="mb-3">
                            <label class="form-label" for="payment_api_key">API Key / Client ID</label>
                            <input type="password" class="form-control" id="payment_api_key" name="config[api_key]" value="${config.api_key || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="payment_api_secret">API Secret / Client Secret</label>
                            <input type="password" class="form-control" id="payment_api_secret" name="config[api_secret]" value="${config.api_secret || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="payment_mode">Modalità</label>
                            <select class="form-select" id="payment_mode" name="config[mode]" ${!userPermissions.canWrite ? 'disabled' : ''}>
                                <option value="test" ${(config.mode || 'test') === 'test' ? 'selected' : ''}>Test / Sandbox</option>
                                <option value="live" ${(config.mode || '') === 'live' ? 'selected' : ''}>Live / Production</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="payment_currency">Valuta predefinita</label>
                            <select class="form-select" id="payment_currency" name="config[currency]" ${!userPermissions.canWrite ? 'disabled' : ''}>
                                <option value="EUR" ${(config.currency || 'EUR') === 'EUR' ? 'selected' : ''}>Euro (EUR)</option>
                                <option value="USD" ${(config.currency || '') === 'USD' ? 'selected' : ''}>Dollaro USA (USD)</option>
                                <option value="GBP" ${(config.currency || '') === 'GBP' ? 'selected' : ''}>Sterlina (GBP)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="payment_webhook">URL Webhook</label>
                            <input type="text" class="form-control" id="payment_webhook" name="config[webhook_url]" value="${config.webhook_url || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                    `);
                    break;
                    
                case 'social':
                    configContainer.append(`
                        <div class="mb-3">
                            <label class="form-label" for="social_app_id">App ID / Client ID</label>
                            <input type="text" class="form-control" id="social_app_id" name="config[app_id]" value="${config.app_id || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="social_app_secret">App Secret / Client Secret</label>
                            <input type="password" class="form-control" id="social_app_secret" name="config[app_secret]" value="${config.app_secret || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="social_redirect_url">URL di Reindirizzamento</label>
                            <input type="text" class="form-control" id="social_redirect_url" name="config[redirect_url]" value="${config.redirect_url || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="social_scopes">Scopes (separati da virgola)</label>
                            <input type="text" class="form-control" id="social_scopes" name="config[scopes]" value="${config.scopes || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                    `);
                    break;
                    
                case 'notification':
                    configContainer.append(`
                        <div class="mb-3">
                            <label class="form-label" for="notification_api_key">API Key</label>
                            <input type="password" class="form-control" id="notification_api_key" name="config[api_key]" value="${config.api_key || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notification_project_id">Project ID</label>
                            <input type="text" class="form-control" id="notification_project_id" name="config[project_id]" value="${config.project_id || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notification_sender_id">Sender ID</label>
                            <input type="text" class="form-control" id="notification_sender_id" name="config[sender_id]" value="${config.sender_id || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notification_server_key">Server Key</label>
                            <input type="password" class="form-control" id="notification_server_key" name="config[server_key]" value="${config.server_key || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Canali</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="notification_email" name="config[channels][email]" ${config.channels&&config.channels.email ? 'checked' : ''} ${!userPermissions.canWrite ? 'disabled' : ''}>
                                <label class="form-check-label" for="notification_email">Email</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="notification_sms" name="config[channels][sms]" ${config.channels&&config.channels.sms ? 'checked' : ''} ${!userPermissions.canWrite ? 'disabled' : ''}>
                                <label class="form-check-label" for="notification_sms">SMS</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="notification_push" name="config[channels][push]" ${config.channels&&config.channels.push ? 'checked' : ''} ${!userPermissions.canWrite ? 'disabled' : ''}>
                                <label class="form-check-label" for="notification_push">Push</label>
                            </div>
                        </div>
                    `);
                    break;
                    
                case 'sms':
                    configContainer.append(`
                        <div class="mb-3">
                            <label class="form-label" for="sms_account_sid">Account SID</label>
                            <input type="text" class="form-control" id="sms_account_sid" name="config[account_sid]" value="${config.account_sid || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="sms_auth_token">Auth Token</label>
                            <input type="password" class="form-control" id="sms_auth_token" name="config[auth_token]" value="${config.auth_token || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="sms_from_number">Numero Mittente</label>
                            <input type="text" class="form-control" id="sms_from_number" name="config[from_number]" value="${config.from_number || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="sms_verify_service_sid">Verify Service SID</label>
                            <input type="text" class="form-control" id="sms_verify_service_sid" name="config[verify_service_sid]" value="${config.verify_service_sid || ''}" ${!userPermissions.canWrite ? 'disabled' : ''}>
                            <small class="text-muted">Per verifiche SMS a due fattori (2FA)</small>
                        </div>
                    `);
                    break;
                
                default:
                    // Per altri tipi, mostra un campo JSON generico
                    configContainer.append(`
                        <div class="mb-3">
                            <div class="alert alert-warning small mb-2">
                                <i class="icon-base ti tabler-alert-triangle me-1"></i>
                                Configurazione avanzata per integrazione di tipo "${integration.type}". Modifica il JSON direttamente.
                            </div>
                            <textarea class="form-control config-json" id="config_json" name="config_json" rows="10" ${!userPermissions.canWrite ? 'disabled' : ''}>${JSON.stringify(config, null, 2)}</textarea>
                        </div>
                    `);
                    break;
            }
        }
        
        // Salva configurazione
        $('#saveIntegrationBtn').on('click', function() {
            if (!userPermissions.canWrite) {
                showAlert('Non hai i permessi per modificare le integrazioni.', 'danger');
                return;
            }
            
            const integrationId = $('#integration_id').val();
            const status = $('#integration_status').is(':checked');
            
            // Raccogli la configurazione
            let config = {};
            
            // Verifica se stiamo utilizzando il campo JSON generico
            if ($('#config_json').length) {
                try {
                    config = JSON.parse($('#config_json').val());
                } catch (e) {
                    showAlert('Errore nel formato JSON della configurazione', 'danger');
                    return;
                }
            } else {
                // Raccogli i valori dai campi del form
                $('#config_fields_container input, #config_fields_container select, #config_fields_container textarea').each(function() {
                    const name = $(this).attr('name');
                    if (name&&name.startsWith('config[')) {
                        // Estrai il nome del campo dalla notazione config[field]
                        const fieldMatch = name.match(/config\[([^\]]+)\]/);
                        if (fieldMatch&&fieldMatch[1]) {
                            const fieldName = fieldMatch[1];
                            
                            // Se è un checkbox, usa il valore checked
                            if ($(this).attr('type') === 'checkbox') {
                                // Per i campi con struttura annidata (es. config[channels][email])
                                const nestedMatch = fieldName.match(/([^\[]+)\[([^\]]+)\]/);
                                if (nestedMatch) {
                                    const parentField = nestedMatch[1];
                                    const childField = nestedMatch[2];
                                    
                                    if (!config[parentField]) {
                                        config[parentField] = {};
                                    }
                                    
                                    config[parentField][childField] = $(this).is(':checked');
                                } else {
                                    config[fieldName] = $(this).is(':checked');
                                }
                            } else {
                                config[fieldName] = $(this).val();
                            }
                        }
                    }
                });
            }
            
            // Salva la configurazione al server
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'save_integration_config',
                    integration_id: integrationId,
                    config: config
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Aggiorna anche lo stato
                        $.ajax({
                            url: window.location.href,
                            type: 'POST',
                            data: {
                                action: 'update_integration_status',
                                integration_id: integrationId,
                                status: status
                            },
                            dataType: 'json',
                            success: function(statusResponse) {
                                // Nascondi il modal
                                $('#integrationModal').modal('hide');
                                
                                // Mostra messaggio di successo
                                showAlert(response.message, 'success');
                                
                                // Aggiorna la pagina dopo un breve ritardo
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            },
                            error: function() {
                                showAlert('Errore di comunicazione con il server durante l\'aggiornamento dello stato', 'danger');
                            }
                        });
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Errore di comunicazione con il server', 'danger');
                }
            });
        });
        
        // Toggle integrazione (switch)
        $(document).on('change', '.integration-toggle', function() {
            if (!userPermissions.canWrite) {
                $(this).prop('checked', !$(this).prop('checked')); // Ripristina lo stato precedente
                showAlert('Non hai i permessi per modificare le integrazioni.', 'danger');
                return;
            }
            
            const integrationId = $(this).data('id');
            const isActive = $(this).is(':checked');
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'update_integration_status',
                    integration_id: integrationId,
                    status: isActive
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                    } else {
                        showAlert(response.message, 'danger');
                        // Ripristina lo stato precedente dello switch
                        $(this).prop('checked', !isActive);
                    }
                },
                error: function() {
                    showAlert('Errore di comunicazione con il server', 'danger');
                    // Ripristina lo stato precedente dello switch
                    $(this).prop('checked', !isActive);
                }
            });
        });
        
        // Elimina integrazione
        $('#deleteIntegrationBtn').on('click', function() {
            if (!userPermissions.canCreate) {
                showAlert('Non hai i permessi per eliminare le integrazioni.', 'danger');
                return;
            }
            
            const integrationId = $('#integration_id').val();
            const integrationName = $('#integration_name').val();
            const isBuiltIn = $('#integration_is_built_in').val() === "1";
            
            // Non permettere l'eliminazione di integrazioni predefinite
            if (isBuiltIn) {
                showAlert('Le integrazioni predefinite non possono essere eliminate', 'warning');
                return;
            }
            
            // Chiedi conferma
            Swal.fire({
                title: 'Sei sicuro?',
                text: `Vuoi davvero eliminare l'integrazione "${integrationName}"?`,
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
                    // Elimina l'integrazione
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'delete_integration',
                            integration_id: integrationId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Nascondi il modal
                                $('#integrationModal').modal('hide');
                                
                                // Mostra messaggio di successo
                                showAlert(response.message, 'success');
                                
                                // Aggiorna la pagina dopo un breve ritardo
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            } else {
                                showAlert(response.message, 'danger');
                            }
                        },
                        error: function() {
                            showAlert('Errore di comunicazione con il server', 'danger');
                        }
                    });
                }
            });
        });
        
        // Aggiungi integrazione
        $('#addIntegrationBtn').on('click', function() {
            if (!userPermissions.canCreate) {
                showAlert('Non hai i permessi per aggiungere integrazioni.', 'danger');
                return;
            }
            
            const name = $('#new_integration_name').val();
            const type = $('#new_integration_type').val();
            const description = $('#new_integration_description').val();
            const status = $('#new_integration_status').is(':checked') ? 'active' : 'inactive';
            let config = {};
            
            // Verifica i campi obbligatori
            if (!name || !type) {
                showAlert('Nome e tipo sono campi obbligatori', 'danger');
                return;
            }
            
            // Prova a parsare la configurazione JSON
            try {
                config = JSON.parse($('#new_integration_config').val() || '{}');
            } catch (e) {
                showAlert('Errore nel formato JSON della configurazione', 'danger');
                return;
            }
            
            // Aggiungi l'integrazione
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'add_integration',
                    name: name,
                    type: type,
                    description: description,
                    status: status,
                    config: config
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Nascondi il modal
                        $('#addIntegrationModal').modal('hide');
                        
                        // Mostra messaggio di successo
                        showAlert(response.message, 'success');
                        
                        // Aggiorna la pagina dopo un breve ritardo
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Errore di comunicazione con il server', 'danger');
                }
            });
        });
        
        // Test API
        $('#testApiButton').on('click', function() {
            // Ottieni i dati di configurazione per il test
            let config = {};
            let url = '';
            let headers = {};
            let method = 'GET';
            let data = null;
            const integrationId = $('#integration_id').val();
            
            // Verifica se stiamo utilizzando il campo JSON generico
            if ($('#config_json').length) {
                try {
                    config = JSON.parse($('#config_json').val());
                } catch (e) {
                    showAlert('Errore nel formato JSON della configurazione', 'danger');
                    return;
                }
                
                url = config.api_url || '';
                
                // Aggiungi autorizzazione se presente
                if (config.api_key) {
                    headers['Authorization'] = `Bearer ${config.api_key}`;
                } else if (config.api_key&&config.api_secret) {
                    headers['Authorization'] = 'Basic ' + btoa(`${config.api_key}:${config.api_secret}`);
                }
            } else {
                // Raccogli i valori dai campi del form
                url = $('#api_url').val() || $('#payment_api_key').val() || '';
                const apiKey = $('#api_key').val() || $('#payment_api_key').val() || $('#social_app_id').val() || $('#notification_api_key').val() || '';
                const apiSecret = $('#api_secret').val() || $('#payment_api_secret').val() || $('#social_app_secret').val() || $('#notification_api_secret').val() || '';
                
                if (apiKey&&apiSecret) {
                    headers['Authorization'] = 'Basic ' + btoa(`${apiKey}:${apiSecret}`);
                } else if (apiKey) {
                    headers['Authorization'] = `Bearer ${apiKey}`;
                }
            }
            
            // Verifica che l'URL sia specificato
            if (!url) {
                $('#test_result').removeClass('d-none').addClass('alert-danger').html('<i class="icon-base ti tabler-alert-triangle me-1"></i> URL API non specificato. Inserisci un URL valido per il test.');
                return;
            }
            
            // Mostra indicatore di caricamento
            $('#test_result').removeClass('d-none alert-success alert-danger').addClass('alert-info').html('<i class="icon-base ti tabler-loader me-1"></i> Test in corso...');
            
            // Esegui il test
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'test_api_connection',
                    url: url,
                    method: method,
                    headers: JSON.stringify(headers),
                    data: JSON.stringify(data),
                    integration_id: integrationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#test_result').removeClass('alert-info alert-danger').addClass('alert-success')
                            .html(`<i class="icon-base ti tabler-check me-1"></i> ${response.message}<br>
                                  <small>HTTP Status: ${response.http_code}</small>
                                  <pre class="mt-2 mb-0" style="max-height: 150px; overflow-y: auto;">${response.response}</pre>`);
                    } else {
                        $('#test_result').removeClass('alert-info alert-success').addClass('alert-danger')
                            .html(`<i class="icon-base ti tabler-alert-triangle me-1"></i> ${response.message}<br>
                                  <small>HTTP Status: ${response.http_code}</small>`);
                    }
                },
                error: function() {
                    $('#test_result').removeClass('alert-info alert-success').addClass('alert-danger')
                        .html('<i class="icon-base ti tabler-alert-triangle me-1"></i> Errore di comunicazione con il server');
                }
            });
        });
        
        // Test Webhook
        $('#testWebhookButton').on('click', function() {
            // Ottieni i dati di configurazione per il test
            let webhookUrl = '';
            const integrationId = $('#integration_id').val();
            
            // Verifica se stiamo utilizzando il campo JSON generico
            if ($('#config_json').length) {
                try {
                    const config = JSON.parse($('#config_json').val());
                    webhookUrl = config.webhook_url || config.callback_url || '';
                } catch (e) {
                    showAlert('Errore nel formato JSON della configurazione', 'danger');
                    return;
                }
            } else {
                // Cerca in vari campi possibili per l'URL del webhook
                webhookUrl = $('#payment_webhook').val() || '';
            }
            
            // Se non troviamo un URL, chiedi all'utente
            if (!webhookUrl) {
                Swal.fire({
                    title: 'URL Webhook',
                    input: 'url',
                    inputLabel: 'Inserisci l\'URL del webhook da testare',
                    inputPlaceholder: 'https://example.com/webhook',
                    showCancelButton: true,
                    confirmButtonText: 'Testa',
                    cancelButtonText: 'Annulla',
                    customClass: {
                        confirmButton: 'btn btn-primary me-3',
                        cancelButton: 'btn btn-outline-secondary'
                    },
                    buttonsStyling: false,
                    inputValidator: (value) => {
                        if (!value) {
                            return 'Devi inserire un URL valido';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeWebhookTest(result.value, integrationId);
                    }
                });
            } else {
                executeWebhookTest(webhookUrl, integrationId);
            }
        });
        
        // Funzione per eseguire il test webhook
        function executeWebhookTest(url, integrationId) {
            // Mostra indicatore di caricamento
            $('#test_result').removeClass('d-none alert-success alert-danger').addClass('alert-info').html('<i class="icon-base ti tabler-loader me-1"></i> Invio webhook di test in corso...');
            
            // Esegui il test
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'test_webhook',
                    url: url,
                    integration_id: integrationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#test_result').removeClass('alert-info alert-danger').addClass('alert-success')
                            .html(`<i class="icon-base ti tabler-check me-1"></i> ${response.message}<br>
                                  <small>HTTP Status: ${response.http_code}</small>
                                  <div class="mt-2">
                                    <strong>Dati inviati:</strong>
                                    <pre class="mt-1 mb-0" style="max-height: 100px; overflow-y: auto;">${JSON.stringify(response.data_sent, null, 2)}</pre>
                                  </div>
                                  <div class="mt-2">
                                    <strong>Risposta ricevuta:</strong>
                                    <pre class="mt-1 mb-0" style="max-height: 100px; overflow-y: auto;">${response.response}</pre>
                                  </div>`);
                    } else {
                        $('#test_result').removeClass('alert-info alert-success').addClass('alert-danger')
                            .html(`<i class="icon-base ti tabler-alert-triangle me-1"></i> ${response.message}<br>
                                  <small>HTTP Status: ${response.http_code}</small>`);
                    }
                },
                error: function() {
                    $('#test_result').removeClass('alert-info alert-success').addClass('alert-danger')
                        .html('<i class="icon-base ti tabler-alert-triangle me-1"></i> Errore di comunicazione con il server');
                }
            });
        }
        
        // Reset form aggiungi integrazione quando si chiude il modal
        $('#addIntegrationModal').on('hidden.bs.modal', function () {
            $('#addIntegrationForm')[0].reset();
            $('#new_integration_config').val('{}');
        });
        
        // Reset risultati test quando si chiude il modal di configurazione
        $('#integrationModal').on('hidden.bs.modal', function () {
            $('#test_result').addClass('d-none').removeClass('alert-success alert-danger alert-info').html('');
        });
        
        // Verifica permessi all'avvio
        if (!userPermissions.canWrite) {
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
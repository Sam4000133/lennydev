<?php
// Abilita la visualizzazione degli errori per il debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Percorso al certificato CA
$cacertPath = __DIR__ . '/../../../../cacert.pem';
// Verifico che il file esista
$certificateExists = file_exists($cacertPath);

// Percorso per salvare gli avatar
$avatarPath = "../../../../assets/img/avatars/";
// Assicurati che la directory esista
if (!is_dir($avatarPath)) {
    mkdir($avatarPath, 0755, true);
}

// Funzione per il debug
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/google_auth_debug_' . date('Y-m-d') . '.log';
    $log = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $log .= "\n" . print_r($data, true);
    }
    $log .= "\n--------------------\n";
    file_put_contents($logFile, $log, FILE_APPEND);
}

// Inizia la sessione
session_start();

// Log iniziale
debugLog("Script inizializzato", [
    'REQUEST_URI' => $_SERVER['REQUEST_URI'],
    'GET' => $_GET,
    'SESSION' => isset($_SESSION) ? $_SESSION : 'Non impostata',
    'Certificate Path' => $cacertPath,
    'Certificate Exists' => $certificateExists ? 'Yes' : 'No'
]);

// Includi la connessione al database
require_once '../db_connection.php';
debugLog("Connessione al database inclusa");

// Funzione per registrare evento nel log di sistema
function logSystemEvent($conn, $level, $message, $userId = null, $ipAddress = null, $userAgent = null, $context = null) {
    $sql = "INSERT INTO system_logs (level, message, user_id, ip_address, user_agent, context, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    // Converti il contesto in JSON se necessario
    if ($context !== null&&is_array($context)) {
        $context = json_encode($context);
    }
    $stmt->bind_param("ssssss", $level, $message, $userId, $ipAddress, $userAgent, $context);
    $stmt->execute();
}

// Funzione per ottenere un'impostazione dal database
function getSetting($conn, $key, $default = null) {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return $default;
}

// Funzione per scaricare e salvare l'immagine del profilo
function downloadProfileImage($url, $path, $filename) {
    $fullPath = $path . $filename;
    $ch = curl_init($url);
    $fp = fopen($fullPath, 'wb');
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $success = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    
    if ($success) {
        return $filename;
    }
    
    return null;
}

// Funzione per inviare codice di verifica tramite Twilio
function sendTwilioVerificationCode($conn, $phoneNumber) {
    // Recupera le impostazioni Twilio dal database
    $accountSid = getSetting($conn, 'twilio_account_sid');
    $authToken = getSetting($conn, 'twilio_auth_token');
    $serviceSid = getSetting($conn, 'twilio_verify_service_sid');
    
    if (empty($accountSid) || empty($authToken) || empty($serviceSid)) {
        return [
            'success' => false,
            'message' => 'Configurazione Twilio mancante'
        ];
    }
    
    // URL dell'API Twilio Verify
    $url = "https://verify.twilio.com/v2/Services/$serviceSid/Verifications";
    
    // Prepara i dati per la richiesta
    $data = [
        'To' => $phoneNumber,
        'Channel' => 'sms'
    ];
    
    // Inizializza cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$accountSid:$authToken");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Esegui la richiesta
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Gestisci eventuali errori cURL
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'success' => false,
            'message' => "Errore cURL: $error"
        ];
    }
    
    curl_close($ch);
    
    // Analizza la risposta
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200&&$httpCode < 300) {
        return [
            'success' => true,
            'sid' => $responseData['sid'],
            'status' => $responseData['status']
        ];
    } else {
        return [
            'success' => false,
            'message' => isset($responseData['message']) ? $responseData['message'] : "Errore HTTP: $httpCode"
        ];
    }
}

// Verifica se la colonna registration_method esiste nella tabella users
function checkIfColumnExists($conn, $table, $column) {
    $sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Ottieni i valori consentiti per il campo status
function getEnumValues($conn, $table, $column) {
    $sql = "SHOW COLUMNS FROM {$table} LIKE '{$column}'";
    $result = $conn->query($sql);
    if ($result&&$row = $result->fetch_assoc()) {
        $type = $row['Type'];
        preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
        if (isset($matches[1])) {
            return explode("','", $matches[1]);
        }
    }
    return ['active']; // Default fallback
}

// Verifica se la colonna registration_method esiste
$hasRegistrationMethodColumn = checkIfColumnExists($conn, 'users', 'registration_method');
debugLog("Verifica colonna registration_method", [
    'exists' => $hasRegistrationMethodColumn ? 'Yes' : 'No'
]);

// Verifica se la colonna phone esiste
$hasPhoneColumn = checkIfColumnExists($conn, 'users', 'phone');
debugLog("Verifica colonna phone", [
    'exists' => $hasPhoneColumn ? 'Yes' : 'No'
]);

// Ottieni i valori consentiti per il campo status
$statusValues = getEnumValues($conn, 'users', 'status');
debugLog("Valori enum per status", ['values' => $statusValues]);

// Status predefinito per i nuovi utenti
$defaultStatus = 'active';
if (!in_array($defaultStatus, $statusValues)) {
    $defaultStatus = $statusValues[0]; // Usa il primo valore consentito
}

// Se la colonna registration_method non esiste, prova ad aggiungerla
if (!$hasRegistrationMethodColumn) {
    try {
        $sql = "ALTER TABLE users 
                ADD COLUMN registration_method VARCHAR(20) DEFAULT 'local' 
                COMMENT 'Metodo di registrazione (local, google, facebook, etc.)'";
        $conn->query($sql);
        debugLog("Colonna registration_method aggiunta con successo");
        $hasRegistrationMethodColumn = true;
    } catch (Exception $e) {
        debugLog("Errore nell'aggiungere la colonna registration_method", [
            'error' => $e->getMessage()
        ]);
        // Continua anche se non riesce ad aggiungere la colonna
    }
}

// Recupera le impostazioni di Google dal database
$googleEnabled = getSetting($conn, 'google_enabled', '0');
$googleClientId = getSetting($conn, 'google_client_id', '');
$googleClientSecret = getSetting($conn, 'google_client_secret', '');
$googleRedirectUrl = getSetting($conn, 'google_redirect_url', '');

// Log delle impostazioni
debugLog("Impostazioni recuperate", [
    'Google enabled' => $googleEnabled,
    'Client ID' => substr($googleClientId, 0, 10) . '...',
    'Redirect URL' => $googleRedirectUrl
]);

// Verifica se Google OAuth è abilitato
if ($googleEnabled != '1') {
    debugLog("Google OAuth non abilitato");
    $_SESSION['auth_error'] = "L'autenticazione tramite Google non è attualmente abilitata.";
    header('Location: ../login.php');
    exit;
}

// Verifica se sono presenti le configurazioni necessarie
if (empty($googleClientId) || empty($googleClientSecret)) {
    debugLog("Configurazione Google OAuth incompleta");
    $_SESSION['auth_error'] = "Configurazione per l'autenticazione Google incompleta. Contattare l'amministratore.";
    header('Location: ../login.php');
    exit;
}

// Test delle credenziali
if (isset($_GET['test_auth'])) {
    echo "<h1>Test di autenticazione Google</h1>";
    echo "<p>Script funzionante.</p>";
    echo "<p>Impostazioni recuperate dal database:</p>";
    echo "<ul>";
    echo "<li>Google enabled: " . $googleEnabled . "</li>";
    echo "<li>Client ID: " . substr($googleClientId, 0, 10) . "...</li>";
    echo "<li>Redirect URL: " . htmlspecialchars($googleRedirectUrl) . "</li>";
    echo "</ul>";
    echo "<p><a href='google.php'>Inizia autenticazione Google</a></p>";
    exit;
}

// Verifica del codice di verifica del telefono
if (isset($_POST['verify_code'])&&isset($_SESSION['phone_verification'])) {
    $phoneNumber = $_SESSION['phone_verification']['phone'];
    $verificationSid = $_SESSION['phone_verification']['sid'];
    $googleUserData = $_SESSION['google_user_data'];
    $code = $_POST['verify_code'];
    
    // Recupera le credenziali Twilio
    $accountSid = getSetting($conn, 'twilio_account_sid');
    $authToken = getSetting($conn, 'twilio_auth_token');
    $serviceSid = getSetting($conn, 'twilio_verify_service_sid');
    
    // URL per verificare il codice
    $url = "https://verify.twilio.com/v2/Services/$serviceSid/VerificationCheck";
    
    // Dati per la richiesta
    $data = [
        'To' => $phoneNumber,
        'Code' => $code
    ];
    
    // Inizializza cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$accountSid:$authToken");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Esegui la richiesta
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Chiudi cURL
    curl_close($ch);
    
    // Analizza la risposta
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200&&$httpCode < 300&&isset($responseData['status'])&&$responseData['status'] === 'approved') {
        // Codice verificato, procedi con la creazione dell'utente
        createGoogleUser($conn, $googleUserData, $phoneNumber);
    } else {
        // Codice errato, mostra un errore
        $_SESSION['verify_error'] = "Codice di verifica non valido. Riprova.";
        
        // Ritorna alla pagina di verifica
        include 'phone_verification.php';
        exit;
    }
}

// Gestione del modulo per la raccolta del numero di telefono
if (isset($_POST['phone_number'])&&isset($_SESSION['google_user_data'])) {
    $phoneNumber = $_POST['phone_number'];
    
    // Formatta il numero di telefono (aggiungi prefisso internazionale se necessario)
    if (substr($phoneNumber, 0, 1) !== '+') {
        $phoneNumber = '+39' . $phoneNumber; // Prefisso italiano predefinito
    }
    
    // Invia il codice di verifica
    $verificationResult = sendTwilioVerificationCode($conn, $phoneNumber);
    
    if ($verificationResult['success']) {
        // Salva i dati di verifica in sessione
        $_SESSION['phone_verification'] = [
            'phone' => $phoneNumber,
            'sid' => $verificationResult['sid']
        ];
        
        // Mostra il modulo per inserire il codice di verifica
        include 'verify_code.php';
        exit;
    } else {
        // Errore nell'invio del codice
        $_SESSION['phone_error'] = "Errore nell'invio del codice di verifica: " . $verificationResult['message'];
        
        // Ritorna al modulo del numero di telefono
        include 'phone_verification.php';
        exit;
    }
}

// Funzione per creare un nuovo utente con i dati di Google
function createGoogleUser($conn, $userData, $phoneNumber = null) {
    global $hasRegistrationMethodColumn, $hasPhoneColumn, $defaultStatus, $avatarPath;
    
    // Estrai i dati utente
    $email = $userData['email'];
    $name = $userData['name'] ?? '';
    $given_name = $userData['given_name'] ?? '';
    $family_name = $userData['family_name'] ?? '';
    $picture = $userData['picture'] ?? '';
    
    debugLog("Creazione utente Google", [
        'Email' => $email,
        'Name' => $name,
        'Phone' => $phoneNumber
    ]);
    
    // Genera un username basato sull'email
    $username = strtolower(explode('@', $email)[0]);
    $base_username = $username;
    $counter = 1;
    
    // Verifica se l'username è già in uso
    $sql = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Se l'username è già in uso, aggiungi un numero
    while ($result->num_rows > 0) {
        $username = $base_username . $counter;
        $counter++;
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    // Genera una password casuale
    $password = bin2hex(random_bytes(8));
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Prepara il nome completo
    $full_name = trim("$given_name $family_name");
    if (empty($full_name)) {
        $full_name = $name;
    }
    if (empty($full_name)) {
        $full_name = $username;
    }
    
    // Salva la foto del profilo come avatar se disponibile
    $avatar = null;
    if (!empty($picture)) {
        $avatarFileName = 'google_' . time() . '_' . md5($email) . '.jpg';
        
        // Scarica e salva l'immagine
        $avatar = downloadProfileImage($picture, $avatarPath, $avatarFileName);
        if ($avatar) {
            $avatar = $avatarPath . $avatar;
        }
    }
    
    // Imposta il ruolo predefinito a 3 (utente standard)
    $role_id = 3;
    
    try {
        // Crea la variabile per il metodo di registrazione
        $registration_method = 'google';
        
        // Prepara la query base con o senza colonna del telefono
        if ($hasRegistrationMethodColumn&&$hasPhoneColumn&&$phoneNumber) {
            $sql = "INSERT INTO users (username, email, password, full_name, avatar, role_id, status, phone, created_at, updated_at, registration_method) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssisss", $username, $email, $hashed_password, $full_name, $avatar, $role_id, $defaultStatus, $phoneNumber, $registration_method);
        } 
        elseif ($hasRegistrationMethodColumn) {
            $sql = "INSERT INTO users (username, email, password, full_name, avatar, role_id, status, created_at, updated_at, registration_method) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssiss", $username, $email, $hashed_password, $full_name, $avatar, $role_id, $defaultStatus, $registration_method);
        } 
        else {
            $sql = "INSERT INTO users (username, email, password, full_name, avatar, role_id, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssis", $username, $email, $hashed_password, $full_name, $avatar, $role_id, $defaultStatus);
        }

        debugLog("Query SQL per inserimento:", [
            'SQL' => $sql,
            'Username' => $username,
            'Email' => $email,
            'Phone' => $phoneNumber,
            'Role_id' => $role_id,
            'Status' => $defaultStatus
        ]);

        if ($stmt->execute()) {
            $new_user_id = $conn->insert_id;
            
            debugLog("Nuovo utente creato", [
                'User ID' => $new_user_id,
                'Username' => $username,
                'Auth Method' => 'Google'
            ]);
            
            // Imposta la sessione utente
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role_id'] = $role_id;
            $_SESSION['auth_method'] = 'google'; // Memorizza il metodo di autenticazione in sessione
            
            // Cancella i dati temporanei di sessione
            unset($_SESSION['google_user_data']);
            unset($_SESSION['phone_verification']);
            
            // Recupera i permessi per il ruolo
            $sql = "SELECT p.category, p.can_read, p.can_write, p.can_create 
                    FROM permissions p 
                    JOIN role_permissions rp ON p.id = rp.permission_id 
                    WHERE rp.role_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $role_id);
            $stmt->execute();
            $permissions_result = $stmt->get_result();
            
            $permissions = [];
            while ($perm = $permissions_result->fetch_assoc()) {
                $permissions[$perm['category']] = [
                    'category' => $perm['category'],
                    'can_read' => $perm['can_read'],
                    'can_write' => $perm['can_write'],
                    'can_create' => $perm['can_create']
                ];
            }
            
            $_SESSION['permissions'] = $permissions;
            
            // Registra il login nel log
            $notes = "Registrazione e login tramite Google OAuth";
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $sql = "INSERT INTO login_logs (user_id, ip_address, user_agent, success, notes, created_at) 
                    VALUES (?, ?, ?, 1, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $new_user_id, $ipAddress, $userAgent, $notes);
            $stmt->execute();
            
            // Log di sistema
            logSystemEvent($conn, 'info', 'Nuovo utente registrato tramite Google OAuth', $new_user_id, $ipAddress, $userAgent);
            
            // Reindirizza alla dashboard o alla pagina principale
            header('Location: ../index.php');
            exit;
        } else {
            debugLog("Errore nella creazione del nuovo utente", [
                'SQL Error' => $conn->error
            ]);
            
            // Log di sistema
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            logSystemEvent($conn, 'error', 'Errore nella creazione utente via Google OAuth: ' . $conn->error, null, $ipAddress, $userAgent);
            
            $_SESSION['auth_error'] = "Errore durante la creazione dell'account. Riprova più tardi.";
            header('Location: ../login.php');
            exit;
        }
    } catch (Exception $e) {
        debugLog("Eccezione durante la creazione dell'utente", [
            'Exception' => $e->getMessage(),
            'Code' => $e->getCode(),
            'Trace' => $e->getTraceAsString()
        ]);
        
        // Log di sistema
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        logSystemEvent($conn, 'error', 'Eccezione durante la creazione utente via Google OAuth: ' . $e->getMessage(), null, $ipAddress, $userAgent);
        
        $_SESSION['auth_error'] = "Errore durante la creazione dell'account. Riprova più tardi.";
        header('Location: ../login.php');
        exit;
    }
}

// Registra evento di inizio autenticazione
$ipAddress = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (!isset($_GET['code'])) {
    logSystemEvent($conn, 'info', 'Iniziato processo di autenticazione Google OAuth', null, $ipAddress, $userAgent);
}

// Determina se stiamo elaborando una callback OAuth o iniziando il processo di autenticazione
if (isset($_GET['code'])) {
    // Stiamo elaborando la callback OAuth da Google
    debugLog("Ricevuto codice di autorizzazione", [
        'code' => substr($_GET['code'], 0, 10) . '...'
    ]);
    
    // Scambia il codice per un token di accesso
    $token_url = 'https://oauth2.googleapis.com/token';
    
    $data = [
        'code' => $_GET['code'],
        'client_id' => $googleClientId,
        'client_secret' => $googleClientSecret,
        'redirect_uri' => $googleRedirectUrl,
        'grant_type' => 'authorization_code'
    ];
    
    debugLog("Parametri per scambio token", [
        'redirect_uri' => $googleRedirectUrl
    ]);
    
    // Usa cURL per effettuare la richiesta
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    // Usa il certificato CA se esiste, altrimenti disabilita la verifica SSL
    if ($certificateExists) {
        curl_setopt($ch, CURLOPT_CAINFO, $cacertPath);
        debugLog("Usando certificato CA dal percorso: $cacertPath");
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        debugLog("Certificato CA non trovato, verifica SSL disabilitata");
    }
    
    // Esegui la richiesta
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    debugLog("Risposta token exchange", [
        'HTTP Code' => $httpCode,
        'Response' => $response
    ]);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        debugLog("cURL error durante lo scambio token", [
            'Error' => $error
        ]);
        $_SESSION['auth_error'] = "Errore di connessione durante l'autenticazione. Riprova più tardi.";
        header('Location: ../login.php');
        exit;
    }
    
    curl_close($ch);
    
    // Continua con l'elaborazione della risposta del token
    if ($httpCode >= 200&&$httpCode < 300) {
        $token_data = json_decode($response, true);
        
        if (!isset($token_data['access_token'])) {
            debugLog("Token di accesso non presente nella risposta", [
                'Response' => $response
            ]);
            $_SESSION['auth_error'] = "Risposta non valida da Google. Riprova più tardi.";
            header('Location: ../login.php');
            exit;
        }
        
        // Usa il token per ottenere le informazioni dell'utente
        $access_token = $token_data['access_token'];
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $ch = curl_init($user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
        
        // Usa il certificato CA se esiste, altrimenti disabilita la verifica SSL
        if ($certificateExists) {
            curl_setopt($ch, CURLOPT_CAINFO, $cacertPath);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        
        $user_info_json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        debugLog("Risposta info utente", [
            'HTTP Code' => $httpCode,
            'Response' => $user_info_json
        ]);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            debugLog("cURL error durante il recupero info utente", [
                'Error' => $error
            ]);
            $_SESSION['auth_error'] = "Errore di connessione durante l'autenticazione. Riprova più tardi.";
            header('Location: ../login.php');
            exit;
        }
        
        curl_close($ch);
        
        if ($httpCode >= 200&&$httpCode < 300) {
            $user_info = json_decode($user_info_json, true);
            
            // Verifica e processa i dati utente (login o registrazione)
            if (!isset($user_info['email'])) {
                debugLog("Email non presente nelle info utente Google", [
                    'User Info' => $user_info
                ]);
                $_SESSION['auth_error'] = "Non è stato possibile ottenere l'email dal tuo account Google.";
                header('Location: ../login.php');
                exit;
            }
            
            // Gestisci il login o la registrazione con i dati dell'utente
            $email = $user_info['email'];
            
            debugLog("Informazioni utente recuperate", [
                'Email' => $email,
                'Name' => $user_info['name'] ?? 'N/A'
            ]);
            
            // Verifica se l'utente esiste già nel sistema
            $sql = "SELECT * FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Utente esistente - effettua login
                $user = $result->fetch_assoc();
                
                debugLog("Utente esistente trovato", [
                    'User ID' => $user['id']
                ]);
                
                // Aggiorna i dati dell'utente se necessario
                if ($hasRegistrationMethodColumn&&$user['registration_method'] === 'local') {
                    // Aggiorna il metodo di registrazione solo se era 'local'
                    $sql = "UPDATE users SET updated_at = NOW(), registration_method = 'google' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                }
                
                // Aggiorna l'avatar se è vuoto e c'è un'immagine del profilo di Google
                if (empty($user['avatar'])&&!empty($user_info['picture'])) {
                    $avatarFileName = 'google_' . time() . '_' . $user['id'] . '.jpg';
                    $avatar = downloadProfileImage($user_info['picture'], $avatarPath, $avatarFileName);
                    
                    if ($avatar) {
                        $avatarPath = $avatarPath . $avatar;
                        $sql = "UPDATE users SET avatar = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $avatarPath, $user['id']);
                        $stmt->execute();
                    }
                }
                
                // Imposta la sessione utente
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['auth_method'] = 'google'; // Memorizza il metodo di autenticazione in sessione
                
                // Recupera i permessi dell'utente
                $sql = "SELECT p.category, p.can_read, p.can_write, p.can_create 
                        FROM permissions p 
                        JOIN role_permissions rp ON p.id = rp.permission_id 
                        WHERE rp.role_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user['role_id']);
                $stmt->execute();
                $permissions_result = $stmt->get_result();
                
                $permissions = [];
                while ($perm = $permissions_result->fetch_assoc()) {
                    $permissions[$perm['category']] = [
                        'category' => $perm['category'],
                        'can_read' => $perm['can_read'],
                        'can_write' => $perm['can_write'],
                        'can_create' => $perm['can_create']
                    ];
                }
                
                $_SESSION['permissions'] = $permissions;
                
                // Registra il login nel log
                $notes = "Login tramite Google OAuth";
                
                $sql = "INSERT INTO login_logs (user_id, ip_address, user_agent, success, notes, created_at) 
                        VALUES (?, ?, ?, 1, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isss", $user['id'], $ipAddress, $userAgent, $notes);
                $stmt->execute();
                
                // Log di sistema
                logSystemEvent($conn, 'info', 'Login completato con Google OAuth', $user['id'], $ipAddress, $userAgent);
                
                // Reindirizza alla dashboard o alla pagina principale
                header('Location: ../index.php');
                exit;
            } else {
                // Nuovo utente - verifica se abbiamo il numero di telefono
                debugLog("Nuovo utente, verifica telefono");
                
                // Salva i dati Google per utilizzo futuro
                $_SESSION['google_user_data'] = $user_info;
                
                // Controlla se il numero di telefono è disponibile
                $phoneNumber = null;
                
                // Se non abbiamo un numero di telefono, mostra il modulo per richiederlo
                if (empty($phoneNumber)&&$hasPhoneColumn) {
                    include 'phone_verification.php';
                    exit;
                } else {
                    // Procedi con la creazione dell'utente
                    createGoogleUser($conn, $user_info, $phoneNumber);
                }
            }
        } else {
            debugLog("Errore nel recupero info utente", [
                'HTTP Code' => $httpCode,
                'Response' => $user_info_json
            ]);
            
            $_SESSION['auth_error'] = "Errore nel recupero delle informazioni utente. Riprova più tardi.";
            header('Location: ../login.php');
            exit;
        }
    } else {
        debugLog("Errore nello scambio token", [
            'HTTP Code' => $httpCode,
            'Response' => $response
        ]);
        
        $_SESSION['auth_error'] = "Errore durante l'autenticazione con Google. Riprova più tardi.";
        header('Location: ../login.php');
        exit;
    }
} else {
    // Inizia il processo di autenticazione reindirizzando a Google
    debugLog("Nessun codice trovato, inizia autenticazione");
    
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';
    $params = [
        'client_id' => $googleClientId,
        'redirect_uri' => $googleRedirectUrl,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
        'prompt' => 'select_account',
        'access_type' => 'online'
    ];
    
    $full_auth_url = $auth_url . '?' . http_build_query($params);
    debugLog("Reindirizzamento a URL di autenticazione", [
        'URL' => $full_auth_url
    ]);
    
    // Reindirizza a Google per l'autenticazione
    header('Location: ' . $full_auth_url);
    exit;
}
?>
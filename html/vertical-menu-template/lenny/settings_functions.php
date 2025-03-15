<?php
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

// Funzione per ottenere i backup recenti
function getRecentBackups($limit = 3) {
    global $conn;
    
    $query = "SELECT * FROM system_backups ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}

// Funzione per elaborare le impostazioni come array di opzioni
function parseOptions($optionsJson) {
    if (empty($optionsJson)) return [];
    
    $options = json_decode($optionsJson, true);
    return is_array($options) ? $options : [];
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
    
    // Includi PHPMailer
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
        }
        
        // Disabilita la verifica SSL in ambiente di sviluppo
        if (($mailSettings['mail_debug']['setting_value'] ?? '0') == '1') {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Attiva il debug
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
        
        // Se l'email è in HTML, imposta anche versione testo
        if ($isHtml) {
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\r\n", $body));
        }
        
        // Imposta la codifica
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

// Funzione per creare un backup reale del database
function createDatabaseBackup($outputDir, $filename) {
    global $conn;
    
    // Ottieni le informazioni di connessione al database
    $dbHost = $conn->host_info;
    preg_match('/([^:]+)(?::(\d+))?$/', $dbHost, $matches);
    $host = $matches[1] ?? 'localhost';
    $port = $matches[2] ?? '3306';
    $username = $conn->user ?? null;
    $password = $_SERVER['DB_PASSWORD'] ?? ''; 
    $database = $conn->database_name ?? null;
    
    // Se non riusciamo a ottenere le informazioni di connessione, fallback a variabili d'ambiente
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

// Funzione per inviare notifica push con Firebase Cloud Messaging
function sendPushNotification($title, $body, $topic = null, $token = null) {
    global $notificationSettings;
    global $CERT_PATH;
    
    // Se non abbiamo già caricato le impostazioni delle notifiche, lo facciamo ora
    if (!isset($notificationSettings)) {
        $notificationSettings = getSettings('notifications');
    }
    
    // Verifica che Firebase sia abilitato
    if (($notificationSettings['firebase_enabled']['setting_value'] ?? '0') !== '1') {
        return [
            'success' => false,
            'message' => 'Firebase non è abilitato nelle impostazioni'
        ];
    }
    
    try {
        // Recupera il JSON delle credenziali Firebase
        $serviceAccountJson = $notificationSettings['firebase_service_account_json']['setting_value'] ?? null;
        
        if (!$serviceAccountJson) {
            return [
                'success' => false,
                'message' => 'File di configurazione Firebase non trovato nelle impostazioni'
            ];
        }
        
        // Inizializza Firebase (versione semplificata usando curl)
        $projectId = $notificationSettings['firebase_project_id']['setting_value'] ?? '';
        if (empty($projectId)) {
            // Estrai il project_id dal JSON se non specificato direttamente
            $serviceAccountData = json_decode($serviceAccountJson, true);
            $projectId = $serviceAccountData['project_id'] ?? '';
        }
        
        if (empty($projectId)) {
            return [
                'success' => false,
                'message' => 'ID progetto Firebase non trovato nel file di configurazione'
            ];
        }
        
        // Ottieni token OAuth per Firebase usando la service account
        $accessToken = getFirebaseAccessToken($serviceAccountJson);
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Impossibile ottenere token di accesso per Firebase'
            ];
        }
        
        // Configura la notifica
        $notification = [
            'title' => $title,
            'body' => $body,
        ];
        
        // Impostazioni aggiuntive per la notifica
        $data = [
            'title' => $title,
            'message' => $body,
            'time' => date('Y-m-d H:i:s')
        ];
        
        // Configurazione per l'icona e altre impostazioni
        $message = [
            'notification' => array_merge($notification, [
                'icon' => $notificationSettings['notification_icon']['setting_value'] ?? '/assets/icons/notification-icon.png',
                'click_action' => $notificationSettings['notification_click_action']['setting_value'] ?? '/dashboard',
                'sound' => ($notificationSettings['notification_sound']['setting_value'] ?? '1') == '1' ? 'default' : null
            ]),
            'data' => $data,
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'sound' => ($notificationSettings['notification_sound']['setting_value'] ?? '1') == '1' ? 'default' : null,
                    'click_action' => $notificationSettings['notification_click_action']['setting_value'] ?? '/dashboard'
                ]
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => ($notificationSettings['notification_sound']['setting_value'] ?? '1') == '1' ? 'default' : null
                    ]
                ]
            ]
        ];
        
        // Aggiungi il target (topic o token)
        if ($token) {
            $message['token'] = $token;
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        } elseif ($topic) {
            $message['topic'] = $topic;
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        } else {
            // Se non è specificato né token né topic, invia a tutti (topic 'all')
            $message['topic'] = 'all';
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        }
        
        // Prepara la richiesta POST a FCM
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => $message]));
        
        // Configurazione SSL
        if (file_exists($CERT_PATH)) {
            curl_setopt($ch, CURLOPT_CAINFO, $CERT_PATH);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Non utilizzare in produzione
        }
        
        // Esegui la richiesta
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($status >= 200&&$status < 300) {
            $responseData = json_decode($response, true);
            
            // Target per il messaggio di log
            $targetMsg = $token ? "token: $token" : ($topic ? "topic: $topic" : "topic: all");
            logSystemAction('info', "Notifica push inviata con successo a $targetMsg");
            
            return [
                'success' => true,
                'message' => 'Notifica inviata con successo',
                'data' => $responseData
            ];
        } else {
            $targetMsg = $token ? "token: $token" : ($topic ? "topic: $topic" : "topic: all");
            logSystemAction('error', "Errore nell'invio della notifica push a $targetMsg: $error (HTTP: $status)");
            
            return [
                'success' => false,
                'message' => 'Errore Firebase: ' . ($error ?: "HTTP $status"),
                'response' => $response
            ];
        }
    } catch (Exception $e) {
        $errorMsg = "Errore nell'invio della notifica push: " . $e->getMessage();
        logSystemAction('error', $errorMsg);
        
        return [
            'success' => false,
            'message' => $errorMsg,
            'error' => $e->getMessage()
        ];
    }
}

// Funzione per ottenere token OAuth per Firebase
function getFirebaseAccessToken($serviceAccountJson) {
    global $CERT_PATH;
    
    try {
        $serviceAccount = json_decode($serviceAccountJson, true);
        if (!$serviceAccount) {
            throw new Exception("Impossibile decodificare il JSON dell'account di servizio");
        }
        
        // Verifica che le chiavi necessarie esistano
        if (!isset($serviceAccount['private_key']) || !isset($serviceAccount['client_email'])) {
            throw new Exception("Account di servizio Firebase mancante di chiavi richieste (private_key o client_email)");
        }
        
        // Crea JWT per ottenere il token OAuth
        $now = time();
        
        $jwt = [
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600, // Token valido per 1 ora
        ];
        
        // Codifica header e payload
        $base64Header = url_safe_base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $base64Payload = url_safe_base64_encode(json_encode($jwt));
        
        // Prepara la stringa da firmare
        $dataToSign = $base64Header . '.' . $base64Payload;
        
        // Firma con la chiave privata
        $signature = '';
        if (!openssl_sign($dataToSign, $signature, $serviceAccount['private_key'], 'SHA256')) {
            throw new Exception("Errore durante la firma del JWT: " . openssl_error_string());
        }
        
        // Codifica la firma
        $base64Signature = url_safe_base64_encode($signature);
        
        // Completa il JWT
        $jwtToken = $dataToSign . '.' . $base64Signature;
        
        // Scambia il JWT per un token OAuth
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwtToken
        ]));
        
        // Configurazione SSL
        if (file_exists($CERT_PATH)) {
            curl_setopt($ch, CURLOPT_CAINFO, $CERT_PATH);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Non utilizzare in produzione
        }
        
        // Esegui la richiesta
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($status != 200) {
            throw new Exception("Errore durante l'ottenimento del token OAuth: $error (HTTP: $status)");
        }
        
        $responseData = json_decode($response, true);
        if (!isset($responseData['access_token'])) {
            throw new Exception("Risposta OAuth non valida: " . $response);
        }
        
        return $responseData['access_token'];
    } catch (Exception $e) {
        logSystemAction('error', "Errore nell'ottenimento del token Firebase: " . $e->getMessage());
        return null;
    }
}

// Supporto per la codifica base64 URL-safe
function url_safe_base64_encode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

// Funzione per inviare SMS (interfaccia generale)
function sendSms($to, $message, $provider = null) {
    global $smsSettings;
    
    // Se non abbiamo già caricato le impostazioni SMS, lo facciamo ora
    if (!isset($smsSettings)) {
        $smsSettings = getSettings('sms');
    }
    
    // Se il provider non è specificato, usa quello predefinito
    if ($provider === null) {
        $provider = $smsSettings['default_sms_provider']['setting_value'] ?? 'firebase';
    }
    
    // Verifica che il provider sia abilitato
    if (($smsSettings[$provider . '_enabled']['setting_value'] ?? '0') !== '1') {
        return [
            'success' => false,
            'message' => "Provider $provider non è abilitato"
        ];
    }
    
    // Gestione diversa in base al provider
    switch ($provider) {
        case 'firebase':
            return sendSmsWithFirebase($to, $message);
            
        case 'twilio':
            return sendSmsWithTwilio($to, $message);
            
        default:
            return [
                'success' => false,
                'message' => "Provider $provider non implementato"
            ];
    }
}

// Funzione per inviare SMS con Firebase Authentication
function sendSmsWithFirebase($phoneNumber, $message) {
    global $smsSettings, $CERT_PATH;
    
    // Se non abbiamo già caricato le impostazioni SMS, lo facciamo ora
    if (!isset($smsSettings)) {
        $smsSettings = getSettings('sms');
    }
    
    // Verifica che Firebase SMS sia abilitato
    if (($smsSettings['firebase_sms_enabled']['setting_value'] ?? '0') !== '1') {
        return [
            'success' => false,
            'message' => 'Firebase SMS non è abilitato'
        ];
    }
    
    try {
        // Verifica se è configurata un'URL per la Cloud Function
        $cloudFunctionUrl = $smsSettings['firebase_cloud_function_url']['setting_value'] ?? null;
        
        if (!$cloudFunctionUrl) {
            // Se non è configurato, invia un errore
            return [
                'success' => false,
                'message' => 'URL Cloud Function Firebase non configurata per l\'invio di SMS'
            ];
        }
        
        // Usa la Cloud Function configurata
        // Prepara i dati per la Cloud Function
        $data = [
            'phoneNumber' => $phoneNumber,
            'message' => $message,
            'api_key' => $smsSettings['firebase_sms_api_key']['setting_value'] ?? null
        ];
        
        // Inizializza cURL per chiamare la Cloud Function
        $ch = curl_init($cloudFunctionUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Configurazione SSL
        if (file_exists($CERT_PATH)) {
            curl_setopt($ch, CURLOPT_CAINFO, $CERT_PATH);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Non utilizzare in produzione
        }
        
        // Esegui la richiesta
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($status >= 200&&$status < 300) {
            $responseData = json_decode($response, true);
            logSystemAction('info', "SMS inviato con successo a $phoneNumber tramite Firebase Cloud Function");
            
            return [
                'success' => true,
                'message' => 'SMS inviato con successo',
                'data' => $responseData
            ];
        } else {
            logSystemAction('error', "Errore nell'invio SMS a $phoneNumber tramite Firebase: $error (HTTP: $status)");
            
            return [
                'success' => false,
                'message' => 'Errore Firebase: ' . ($error ?: "HTTP $status"),
                'response' => $response
            ];
        }
    } catch (Exception $e) {
        $errorMsg = "Errore nell'invio SMS con Firebase: " . $e->getMessage();
        logSystemAction('error', $errorMsg);
        
        return [
            'success' => false,
            'message' => $errorMsg,
            'error' => $e->getMessage()
        ];
    }
}

// Funzione per inviare SMS con Twilio
function sendSmsWithTwilio($to, $message) {
    global $smsSettings, $CERT_PATH;
    
    // Se non abbiamo già caricato le impostazioni SMS, lo facciamo ora
    if (!isset($smsSettings)) {
        $smsSettings = getSettings('sms');
    }
    
    // Verifica che Twilio sia abilitato
    if (($smsSettings['twilio_enabled']['setting_value'] ?? '0') !== '1') {
        return [
            'success' => false,
            'message' => 'Twilio non è abilitato'
        ];
    }
    
    // Ottieni le credenziali Twilio
    $accountSid = $smsSettings['twilio_account_sid']['setting_value'] ?? null;
    $authToken = $smsSettings['twilio_auth_token']['setting_value'] ?? null;
    $fromNumber = $smsSettings['twilio_phone_number']['setting_value'] ?? null;
    
    if (!$accountSid || !$authToken || !$fromNumber) {
        return [
            'success' => false,
            'message' => 'Configurazione Twilio incompleta'
        ];
    }
    
    // Verifica se la libreria Twilio è installata
    if (class_exists('Twilio\Rest\Client')) {
        try {
            // Usa l'SDK Twilio
            $client = new \Twilio\Rest\Client($accountSid, $authToken);
            
            $result = $client->messages->create(
                $to,
                [
                    'from' => $fromNumber,
                    'body' => $message
                ]
            );
            
            logSystemAction('info', "SMS inviato con successo a $to tramite Twilio (SID: {$result->sid})");
            
            return [
                'success' => true,
                'message' => 'SMS inviato con successo',
                'data' => [
                    'sid' => $result->sid,
                    'status' => $result->status
                ]
            ];
        } catch (Exception $e) {
            logSystemAction('error', "Errore nell'invio SMS a $to tramite Twilio: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Errore Twilio: ' . $e->getMessage()
            ];
        }
    } else {
        // Fallback a richiesta API diretta se l'SDK non è installato
        // Configurazione dell'endpoint Twilio
        $url = "https://api.twilio.com/2010-04-01/Accounts/$accountSid/Messages.json";
        
        // Preparazione dei dati
        $data = [
            'To' => $to,
            'From' => $fromNumber,
            'Body' => $message
        ];
        
        // Inizializzazione cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "$accountSid:$authToken");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Configurazione sicura per produzione con certificato locale
        if (file_exists($CERT_PATH)) {
            curl_setopt($ch, CURLOPT_CAINFO, $CERT_PATH);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Non utilizzare in produzione
        }
        
        // Esecuzione della richiesta
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200&&$httpCode < 300) {
            // Successo
            $responseData = json_decode($response, true);
            logSystemAction('info', "SMS inviato con successo a $to tramite Twilio (SID: {$responseData['sid']})");
            return [
                'success' => true,
                'message' => 'SMS inviato con successo',
                'data' => $responseData
            ];
        } else {
            // Errore
            logSystemAction('error', "Errore nell'invio SMS a $to tramite Twilio: $error ($httpCode)");
            return [
                'success' => false,
                'message' => "Errore Twilio: " . ($error ?: "HTTP $httpCode")
            ];
        }
    }
}

// Funzione per verificare la connessione a un servizio di storage cloud
function testCloudStorageConnection($type, $settings) {
    global $CERT_PATH;
    
    try {
        switch ($type) {
            case 's3':
                // Verifica connessione a Amazon S3
                
                // Include AWS SDK (devi averlo installato via Composer)
                if (!class_exists('Aws\S3\S3Client')) {
                    return [
                        'success' => false,
                        'message' => 'AWS SDK non installato. Installa il pacchetto aws/aws-sdk-php tramite Composer.'
                    ];
                }
                
                // Estrai le credenziali AWS
                $accessKey = $settings['s3_access_key']['setting_value'] ?? null;
                $secretKey = $settings['s3_secret_key']['setting_value'] ?? null;
                $region = $settings['s3_region']['setting_value'] ?? 'eu-west-1';
                $bucket = $settings['s3_bucket']['setting_value'] ?? null;
                
                if (!$accessKey || !$secretKey || !$bucket) {
                    return [
                        'success' => false,
                        'message' => 'Credenziali S3 incomplete'
                    ];
                }
                
                // Crea un client S3
                $s3Client = new Aws\S3\S3Client([
                    'version' => 'latest',
                    'region'  => $region,
                    'credentials' => [
                        'key'    => $accessKey,
                        'secret' => $secretKey,
                    ],
                    'http' => [
                        'verify' => $CERT_PATH // Usa il certificato locale per le connessioni HTTPS
                    ]
                ]);
                
                // Verifica se il bucket esiste
                if ($s3Client->doesBucketExist($bucket)) {
                    return [
                        'success' => true,
                        'message' => 'Connessione a Amazon S3 riuscita. Il bucket esiste.'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => "Il bucket '$bucket' non esiste o non è accessibile."
                    ];
                }
                break;
                
            case 'gcloud':
                // Verifica connessione a Google Cloud Storage
                
                // Include Google Cloud Storage SDK (devi averlo installato via Composer)
                if (!class_exists('Google\Cloud\Storage\StorageClient')) {
                    return [
                        'success' => false,
                        'message' => 'Google Cloud Storage SDK non installato. Installa il pacchetto google/cloud-storage tramite Composer.'
                    ];
                }
                
                // Estrai le credenziali Google Cloud
                $projectId = $settings['gcloud_project_id']['setting_value'] ?? null;
                $credentials = $settings['gcloud_credentials']['setting_value'] ?? null;
                $bucket = $settings['gcloud_bucket']['setting_value'] ?? null;
                
                if (!$projectId || !$credentials || !$bucket) {
                    return [
                        'success' => false,
                        'message' => 'Credenziali Google Cloud incomplete'
                    ];
                }
                
                // Crea un client Google Cloud Storage
                $storage = new Google\Cloud\Storage\StorageClient([
                    'projectId' => $projectId,
                    'keyFile' => json_decode($credentials, true)
                ]);
                
                // Verifica se il bucket esiste
                try {
                    $bucket = $storage->bucket($bucket);
                    $bucket->info(); // Questa operazione fallirà se il bucket non esiste o non è accessibile
                    
                    return [
                        'success' => true,
                        'message' => 'Connessione a Google Cloud Storage riuscita. Il bucket esiste.'
                    ];
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => "Errore Google Cloud Storage: " . $e->getMessage()
                    ];
                }
                break;
                
            default:
                return [
                    'success' => false,
                    'message' => "Tipo di storage '$type' non supportato."
                ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Errore di connessione: " . $e->getMessage()
        ];
    }
}
?>
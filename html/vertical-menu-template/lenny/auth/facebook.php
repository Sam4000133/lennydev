<?php
// File: auth/facebook.php

// Abilita la visualizzazione degli errori per il debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inizia la sessione
session_start();

// Includi la connessione al database
require_once '../db_connection.php';

// Funzione per ottenere un'impostazione dal database
function getSetting($conn, $key, $default = null) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result&&$result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['setting_value'];
        }
        
        $stmt->close();
    }
    
    return $default;
}

// Funzione per registrare un log di sistema
function logSystemEvent($conn, $level, $message, $user_id = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $conn->prepare("INSERT INTO system_logs (level, message, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssiss", $level, $message, $user_id, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

// Recupera le impostazioni di Facebook dal database
$facebook_enabled = getSetting($conn, 'facebook_enabled', '0');
$facebook_app_id = getSetting($conn, 'facebook_app_id', '');
$facebook_app_secret = getSetting($conn, 'facebook_app_secret', '');
$facebook_redirect_url = getSetting($conn, 'facebook_redirect_url', '');
$site_url = getSetting($conn, 'site_url', 'http://localhost');

// Recupera le impostazioni Twilio per la verifica telefonica
$twilio_enabled = getSetting($conn, 'twilio_enabled', '0');
$twilio_account_sid = getSetting($conn, 'twilio_account_sid', '');
$twilio_auth_token = getSetting($conn, 'twilio_auth_token', '');
$twilio_verify_service_sid = getSetting($conn, 'twilio_verify_service_sid', '');
$twilio_phone_number = getSetting($conn, 'twilio_phone_number', '');

// Controlla se Facebook login è abilitato
if ($facebook_enabled != '1') {
    $_SESSION['auth_error'] = "Il login con Facebook non è attualmente abilitato.";
    logSystemEvent($conn, 'warning', 'Tentativo di accesso con Facebook mentre il servizio è disabilitato', null);
    header("Location: ../login.php");
    exit;
}

// Controlla se le credenziali sono impostate
if (empty($facebook_app_id) || empty($facebook_app_secret)) {
    $_SESSION['auth_error'] = "Configurazione Facebook incompleta. Contatta l'amministratore.";
    logSystemEvent($conn, 'error', 'Configurazione Facebook incompleta per l\'autenticazione', null);
    header("Location: ../login.php");
    exit;
}

// Se il redirect URL non è impostato, usa l'URL corrente
if (empty($facebook_redirect_url)) {
    $protocol = isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $facebook_redirect_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
}

// Funzioni per Facebook OAuth

/**
 * Ottieni l'URL di autorizzazione Facebook
 */
function getFacebookAuthUrl($app_id, $redirect_url) {
    $state = bin2hex(random_bytes(16)); // Genera un token di stato per sicurezza
    $_SESSION['fb_state'] = $state;
    
    // Definisci gli scope (permessi) richiesti
    $scope = 'email,public_profile';
    
    return "https://www.facebook.com/v12.0/dialog/oauth?" . http_build_query([
        'client_id' => $app_id,
        'redirect_uri' => $redirect_url,
        'state' => $state,
        'scope' => $scope,
        'response_type' => 'code'
    ]);
}

/**
 * Scambia il codice di autorizzazione con un token di accesso
 */
function getAccessToken($conn, $app_id, $app_secret, $redirect_url, $code) {
    $token_url = "https://graph.facebook.com/v12.0/oauth/access_token";
    $params = [
        'client_id' => $app_id,
        'client_secret' => $app_secret,
        'redirect_uri' => $redirect_url,
        'code' => $code
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Controlla se esiste un file di certificato
    $cert_path = realpath(__DIR__ . '/../../cacert.pem');
    if (file_exists($cert_path)) {
        curl_setopt($ch, CURLOPT_CAINFO, $cert_path);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        logSystemEvent($conn, 'error', 'Errore CURL in Facebook Auth: ' . curl_error($ch));
        return null;
    }
    
    curl_close($ch);
    
    if ($http_code != 200) {
        logSystemEvent($conn, 'error', "Errore nella richiesta token Facebook. HTTP Code: $http_code, Response: $response");
        return null;
    }
    
    $token_data = json_decode($response, true);
    return $token_data['access_token'] ?? null;
}

/**
 * Ottieni i dati utente da Facebook
 */
function getUserProfile($conn, $access_token) {
    $profile_url = "https://graph.facebook.com/v12.0/me";
    $params = [
        'fields' => 'id,name,email,first_name,last_name,picture.type(large)',
        'access_token' => $access_token
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $profile_url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Controlla se esiste un file di certificato
    $cert_path = realpath(__DIR__ . '/../../cacert.pem');
    if (file_exists($cert_path)) {
        curl_setopt($ch, CURLOPT_CAINFO, $cert_path);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        logSystemEvent($conn, 'error', 'Errore CURL in Facebook Profile: ' . curl_error($ch));
        return null;
    }
    
    curl_close($ch);
    
    if ($http_code != 200) {
        logSystemEvent($conn, 'error', "Errore nella richiesta profilo Facebook. HTTP Code: $http_code, Response: $response");
        return null;
    }
    
    return json_decode($response, true);
}

/**
 * Salva l'avatar dell'utente
 */
function saveAvatar($url, $filename) {
    // Directory per gli avatars
    $avatar_dir = '../../../../assets/img/avatars/';
    
    // Assicurati che la directory esista
    if (!file_exists($avatar_dir)) {
        mkdir($avatar_dir, 0755, true);
    }
    
    // Estensione del file
    $extension = 'jpg'; // Facebook di solito restituisce JPG
    
    // Percorso completo del file
    $filepath = $avatar_dir . $filename . '.' . $extension;
    $relative_path = 'assets/img/avatars/' . $filename . '.' . $extension;
    
    // Scarica l'immagine
    $image_data = file_get_contents($url);
    if ($image_data === false) {
        return null;
    }
    
    // Salva l'immagine
    if (file_put_contents($filepath, $image_data) === false) {
        return null;
    }
    
    // Restituisci il percorso relativo
    return '../../../../' . $relative_path;
}

/**
 * Invia SMS di verifica tramite Twilio
 */
function sendTwilioVerification($conn, $phone, $twilio_account_sid, $twilio_auth_token, $twilio_verify_service_sid) {
    // Formatta il numero di telefono
    $phone = formatPhoneNumber($phone);
    
    logSystemEvent($conn, 'info', "Tentativo di invio SMS a $phone utilizzando Twilio Verify");
    
    // Log dettagliato per debug
    logSystemEvent($conn, 'debug', "Inizializzazione Twilio Verify - Account SID: " . substr($twilio_account_sid, 0, 6) . "..., Auth Token: impostato, ServiceSID: " . substr($twilio_verify_service_sid, 0, 5) . "..., Phone: $phone");
    
    // URL per l'API Twilio Verify
    $url = "https://verify.twilio.com/v2/Services/$twilio_verify_service_sid/Verifications";
    
    // Parametri della richiesta
    $params = [
        'To' => $phone,
        'Channel' => 'sms'
    ];
    
    // Log dei parametri (nascondendo dati sensibili)
    logSystemEvent($conn, 'debug', "URL Endpoint Twilio Verify: $url");
    logSystemEvent($conn, 'debug', "Parametri richiesta Verify: " . json_encode($params));
    
    // Prepara la richiesta cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Autenticazione HTTP Basic per Twilio
    curl_setopt($ch, CURLOPT_USERPWD, "$twilio_account_sid:$twilio_auth_token");
    
    // Controlla se esiste un file di certificato
    $cert_path = realpath(__DIR__ . '/../../cacert.pem');
    if (file_exists($cert_path)) {
        curl_setopt($ch, CURLOPT_CAINFO, $cert_path);
        logSystemEvent($conn, 'debug', "Utilizzo certificato SSL: $cert_path");
    }
    
    // Esegui la richiesta
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    
    // Log delle informazioni cURL
    logSystemEvent($conn, 'debug', "Curl Info: " . json_encode($info));
    
    // Gestisci gli errori cURL
    if (curl_errno($ch)) {
        logSystemEvent($conn, 'error', "Errore cURL in Twilio Verify: " . curl_error($ch));
        curl_close($ch);
        return [false, "Errore di connessione al servizio di verifica telefonica"];
    }
    
    curl_close($ch);
    
    // Log della risposta
    logSystemEvent($conn, 'debug', "HTTP Code: {$info['http_code']}, Risposta Twilio Verify: " . $response);
    
    // Analizza la risposta
    $response_data = json_decode($response, true);
    
    // Controlla se la richiesta è andata a buon fine
    if ($info['http_code'] == 201) {
        // Verifica inviata
        $sid = $response_data['sid'] ?? '';
        logSystemEvent($conn, 'info', "SMS Verify inviato con successo a $phone (SID: $sid)");
        return [true, $sid];
    } else {
        // Errore
        $error_message = $response_data['message'] ?? 'Errore sconosciuto';
        logSystemEvent($conn, 'error', "Errore nell'invio Verify a $phone: $error_message. HTTP Code: {$info['http_code']}");
        return [false, $error_message];
    }
}

/**
 * Verifica il codice SMS Twilio
 */
function verifyTwilioCode($conn, $phone, $code, $twilio_account_sid, $twilio_auth_token, $twilio_verify_service_sid) {
    // Formatta il numero di telefono
    $phone = formatPhoneNumber($phone);
    
    logSystemEvent($conn, 'debug', "Verifica codice Twilio Verify - Phone: $phone, Code: $code");
    
    // URL per verificare il codice
    $url = "https://verify.twilio.com/v2/Services/$twilio_verify_service_sid/VerificationCheck";
    
    // Parametri della richiesta
    $params = [
        'To' => $phone,
        'Code' => $code
    ];
    
    // Prepara la richiesta cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Autenticazione HTTP Basic per Twilio
    curl_setopt($ch, CURLOPT_USERPWD, "$twilio_account_sid:$twilio_auth_token");
    
    // Controlla se esiste un file di certificato
    $cert_path = realpath(__DIR__ . '/../../cacert.pem');
    if (file_exists($cert_path)) {
        curl_setopt($ch, CURLOPT_CAINFO, $cert_path);
    }
    
    // Esegui la richiesta
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    
    // Gestisci gli errori cURL
    if (curl_errno($ch)) {
        logSystemEvent($conn, 'error', "Errore cURL in verifica codice Twilio: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // Log della risposta
    logSystemEvent($conn, 'debug', "HTTP Code: {$info['http_code']}, Risposta Twilio Verify Check: " . $response);
    
    // Analizza la risposta
    $response_data = json_decode($response, true);
    
    // Controlla se il codice è valido
    if ($info['http_code'] == 200&&isset($response_data['status'])&&$response_data['status'] == 'approved') {
        logSystemEvent($conn, 'info', "Codice verificato con successo per $phone");
        return true;
    } else {
        $error_message = $response_data['message'] ?? 'Codice non valido';
        logSystemEvent($conn, 'warning', "Verifica codice fallita per $phone: $error_message");
        return false;
    }
}

/**
 * Formatta il numero di telefono in formato internazionale
 */
function formatPhoneNumber($phone) {
    // Rimuovi spazi, trattini e altre formattazioni
    $phone = preg_replace('/\s+/', '', $phone);
    $phone = preg_replace('/[^0-9\+]/', '', $phone);
    
    // Assicurati che inizi con +
    if (strpos($phone, '+') !== 0) {
        // Aggiungi il prefisso italiano se manca
        if (strpos($phone, '39') === 0) {
            $phone = '+' . $phone;
        } else {
            $phone = '+39' . $phone;
        }
    }
    
    return $phone;
}

/**
 * Trova o crea un utente in base ai dati Facebook
 */
function findOrCreateUser($conn, $profile_data) {
    // Estrai i dati dal profilo
    $facebook_id = $profile_data['id'] ?? null;
    $email = $profile_data['email'] ?? null;
    $full_name = $profile_data['name'] ?? null;
    $first_name = $profile_data['first_name'] ?? null;
    $last_name = $profile_data['last_name'] ?? null;
    $profile_pic = $profile_data['picture']['data']['url'] ?? null;
    
    // Se non abbiamo un'email, non possiamo procedere
    if (empty($email)) {
        logSystemEvent($conn, 'warning', "L'accesso Facebook non ha fornito un'email", null);
        return [false, "L'accesso Facebook non ha fornito un'email. Impossibile procedere."];
    }
    
    // Cerca un utente esistente con questa email
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Utente esistente
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Se l'utente è sospeso o inattivo
        if ($user['status'] !== 'active') {
            logSystemEvent($conn, 'warning', "Tentativo di accesso con Facebook a un account non attivo: $email", $user['id']);
            return [false, "Il tuo account non è attivo. Contatta l'amministratore."];
        }
        
        // Aggiorna il profilo se necessario (solo se il metodo è facebook o non è specificato)
        if ($user['registration_method'] == 'facebook' || $user['registration_method'] == 'local') {
            $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, registration_method = 'facebook' WHERE id = ?");
            $update_stmt->bind_param("si", $full_name, $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Aggiorna l'avatar se fornito e se non esistente o già un avatar Facebook
            if (!empty($profile_pic)&&(empty($user['avatar']) || strpos($user['avatar'], 'facebook') !== false)) {
                // Salva l'immagine avatar
                $avatar_path = saveAvatar($profile_pic, 'facebook_' . time() . '_' . md5($email));
                
                if ($avatar_path) {
                    $avatar_stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $avatar_stmt->bind_param("si", $avatar_path, $user['id']);
                    $avatar_stmt->execute();
                    $avatar_stmt->close();
                }
            }
        }
        
        // Se l'utente non ha un numero di telefono, deve verificarlo
        if (empty($user['phone'])) {
            // Crea l'ID temporaneo di sessione per l'utente
            $_SESSION['fb_user_id'] = $user['id'];
            $_SESSION['fb_need_phone_verification'] = true;
            
            logSystemEvent($conn, 'info', "Utente Facebook esistente necessita verifica numero: $email", $user['id']);
            return [true, $user, true]; // Il terzo parametro indica che è necessaria la verifica telefonica
        }
        
        logSystemEvent($conn, 'info', "Accesso effettuato con Facebook per l'utente: $email", $user['id']);
        return [true, $user];
    } else {
        // Crea un nuovo utente (registrazione temporanea, sarà completata dopo la verifica telefonica)
        $stmt->close();
        
        // Genera un username unico basato sul nome
        $username_base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name . ($last_name ? substr($last_name, 0, 1) : '')));
        if (empty($username_base)) {
            $username_base = 'fb_user';
        }
        
        $username = $username_base;
        $counter = 1;
        
        // Verifica se l'username esiste già
        do {
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $exists = $check_result->num_rows > 0;
            $check_stmt->close();
            
            if ($exists) {
                $username = $username_base . $counter++;
            }
        } while ($exists);
        
        // Genera una password casuale (non verrà mai usata direttamente, ma è necessaria)
        $password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Ruolo predefinito per i nuovi utenti (3 = "Users")
        $default_role_id = 3;
        
        // Salva l'avatar se fornito
        $avatar_path = null;
        if (!empty($profile_pic)) {
            $avatar_path = saveAvatar($profile_pic, 'facebook_' . time() . '_' . md5($email));
        }
        
        // Crea un utente temporaneo senza numero di telefono
        $insert_stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role_id, status, avatar, registration_method) VALUES (?, ?, ?, ?, ?, 'active', ?, 'facebook')");
        $insert_stmt->bind_param("ssssis", $username, $hashed_password, $email, $full_name, $default_role_id, $avatar_path);
        
        if (!$insert_stmt->execute()) {
            logSystemEvent($conn, 'error', "Errore nella creazione dell'utente Facebook: " . $insert_stmt->error);
            $insert_stmt->close();
            return [false, "Errore nella creazione dell'account. Riprova più tardi."];
        }
        
        $new_user_id = $insert_stmt->insert_id;
        $insert_stmt->close();
        
        // Ottieni l'utente appena creato
        $get_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $get_stmt->bind_param("i", $new_user_id);
        $get_stmt->execute();
        $user = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        logSystemEvent($conn, 'info', "Nuovo utente registrato temporaneamente tramite Facebook: $email", $new_user_id);
        
        // Richiedi la verifica del numero di telefono
        $_SESSION['fb_user_id'] = $new_user_id;
        $_SESSION['fb_need_phone_verification'] = true;
        
        return [true, $user, true]; // Il terzo parametro indica che è necessaria la verifica telefonica
    }
}

/**
 * Procedi con l'accesso dopo la verifica
 */
function proceedWithLogin($conn, $user) {
    // Ottieni i permessi del ruolo
    $permissions = [];
    $permStmt = $conn->prepare("
        SELECT p.name, p.category, rp.can_read, rp.can_write, rp.can_create 
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ?
    ");
    
    if ($permStmt) {
        $permStmt->bind_param("i", $user['role_id']);
        $permStmt->execute();
        $permResult = $permStmt->get_result();
        
        while ($perm = $permResult->fetch_assoc()) {
            $permissions[$perm['name']] = [
                'category' => $perm['category'],
                'can_read' => $perm['can_read'],
                'can_write' => $perm['can_write'],
                'can_create' => $perm['can_create']
            ];
        }
        $permStmt->close();
    }
    
    // Imposta i dati di sessione
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['permissions'] = $permissions;
    
    // Log dell'accesso
    logSystemEvent($conn, 'info', "Accesso completato con Facebook OAuth per l'utente: " . $user['email'], $user['id']);
    
    // Registra il login nei log se la tabella esiste
    $has_login_logs_table = false;
    $result = $conn->query("SHOW TABLES LIKE 'login_logs'");
    if ($result&&$result->num_rows > 0) {
        $has_login_logs_table = true;
        $log_ip = $_SERVER['REMOTE_ADDR'];
        $log_agent = $_SERVER['HTTP_USER_AGENT'];
        $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, success, notes, created_at) VALUES (?, ?, ?, 1, 'Login via Facebook OAuth', NOW())");
        
        if ($log_stmt) {
            $log_stmt->bind_param("iss", $user['id'], $log_ip, $log_agent);
            $log_stmt->execute();
            $log_stmt->close();
        }
    }
    
    // Reimposta le variabili di sessione relative a Facebook
    unset($_SESSION['fb_user_id']);
    unset($_SESSION['fb_user_data']);
    unset($_SESSION['fb_need_phone_verification']);
    unset($_SESSION['fb_phone']);
    unset($_SESSION['fb_verification_sid']);
    
    // Messaggio di successo
    $_SESSION['auth_success'] = "Accesso effettuato con successo tramite Facebook!";
    
    // Redirect alla dashboard
    header("Location: ../index.php");
    exit;
}

// Verifica se stiamo elaborando una verifica telefonica per un utente Facebook
if (isset($_POST['verify_phone_action'])&&isset($_SESSION['fb_user_id'])) {
    $user_id = $_SESSION['fb_user_id'];
    $phone = $_POST['phone'] ?? '';
    
    // Rimuovi tutto tranne numeri e + dal numero di telefono
    $phone = preg_replace('/[^0-9\+]/', '', $phone);
    
    if (empty($phone)) {
        $_SESSION['auth_error'] = "Inserisci un numero di telefono valido.";
        header("Location: verify-phone.php");
        exit;
    }
    
    // Formatta il numero di telefono
    $phone = formatPhoneNumber($phone);
    
    // Invia il codice di verifica
    list($success, $result) = sendTwilioVerification($conn, $phone, $twilio_account_sid, $twilio_auth_token, $twilio_verify_service_sid);
    
    if ($success) {
        // Memorizza il numero di telefono nella sessione
        $_SESSION['fb_phone'] = $phone;
        $_SESSION['fb_verification_sid'] = $result;
        
        // Reindirizza alla pagina di verifica del codice
        header("Location: verify-phone-code.php");
        exit;
    } else {
        $_SESSION['auth_error'] = "Errore nell'invio del codice di verifica: $result";
        header("Location: verify-phone.php");
        exit;
    }
}

// Verifica se stiamo elaborando la verifica del codice SMS
if (isset($_POST['verify_code_action'])&&isset($_SESSION['fb_user_id'])&&isset($_SESSION['fb_phone'])) {
    $user_id = $_SESSION['fb_user_id'];
    $phone = $_SESSION['fb_phone'];
    $code = $_POST['code'] ?? '';
    
    if (empty($code)) {
        $_SESSION['auth_error'] = "Inserisci il codice di verifica.";
        header("Location: verify-phone-code.php");
        exit;
    }
    
    // Verifica il codice
    $verified = verifyTwilioCode($conn, $phone, $code, $twilio_account_sid, $twilio_auth_token, $twilio_verify_service_sid);
    
    if ($verified) {
        // Aggiorna il numero di telefono dell'utente
        $update_stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $update_stmt->bind_param("si", $phone, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Ottieni dati aggiornati dell'utente
        $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();
        
        // Continua con l'accesso
        proceedWithLogin($conn, $user);
    } else {
        $_SESSION['auth_error'] = "Codice di verifica non valido. Riprova.";
        header("Location: verify-phone-code.php");
        exit;
    }
}

// Se c'è una richiesta di reinvio del codice
if (isset($_GET['resend'])&&isset($_SESSION['fb_user_id'])&&isset($_SESSION['fb_phone'])) {
    $phone = $_SESSION['fb_phone'];
    
    // Invia il codice di verifica
    list($success, $result) = sendTwilioVerification($conn, $phone, $twilio_account_sid, $twilio_auth_token, $twilio_verify_service_sid);
    
    if ($success) {
        $_SESSION['fb_verification_sid'] = $result;
        $_SESSION['auth_success'] = "Il codice di verifica è stato inviato nuovamente.";
    } else {
        $_SESSION['auth_error'] = "Errore nell'invio del codice di verifica: $result";
    }
    
    header("Location: verify-phone-code.php");
    exit;
}

// Fase 1: Reindirizza l'utente all'URL di autorizzazione Facebook
if (!isset($_GET['code'])) {
    logSystemEvent($conn, 'info', 'Iniziato processo di autenticazione Facebook OAuth', null);
    $auth_url = getFacebookAuthUrl($facebook_app_id, $facebook_redirect_url);
    header("Location: $auth_url");
    exit;
}

// Fase 2: Gestisci il callback con il codice di autorizzazione
// Verifica il parametro state
if (!isset($_GET['state']) || !isset($_SESSION['fb_state']) || $_GET['state'] !== $_SESSION['fb_state']) {
    $_SESSION['auth_error'] = "Errore di verifica dello stato. Possibile tentativo di CSRF.";
    logSystemEvent($conn, 'warning', 'Facebook OAuth: errore nella verifica dello stato', null);
    header("Location: ../login.php");
    exit;
}

unset($_SESSION['fb_state']); // Pulisci il token di stato

// Ottieni il token di accesso
$access_token = getAccessToken($conn, $facebook_app_id, $facebook_app_secret, $facebook_redirect_url, $_GET['code']);

if (!$access_token) {
    $_SESSION['auth_error'] = "Impossibile ottenere l'accesso a Facebook. Riprova più tardi.";
    logSystemEvent($conn, 'error', 'Facebook OAuth: impossibile ottenere access token', null);
    header("Location: ../login.php");
    exit;
}

// Ottieni il profilo utente
$profile_data = getUserProfile($conn, $access_token);

if (!$profile_data) {
    $_SESSION['auth_error'] = "Impossibile ottenere il profilo Facebook. Riprova più tardi.";
    logSystemEvent($conn, 'error', 'Facebook OAuth: impossibile ottenere profilo utente', null);
    header("Location: ../login.php");
    exit;
}

// Trova o crea l'utente
$result = findOrCreateUser($conn, $profile_data);

if (!$result[0]) {
    $_SESSION['auth_error'] = $result[1]; // In questo caso, result[1] contiene un messaggio di errore
    header("Location: ../login.php");
    exit;
}

// Se è necessaria la verifica del telefono, reindirizza alla pagina di verifica
if (isset($result[2])&&$result[2] === true) {
    // Imposta l'utente nella sessione e reindirizza
    $_SESSION['fb_user_data'] = $result[1]; // Salva i dati utente nella sessione
    header("Location: verify-phone.php");
    exit;
}

// Se non è necessaria la verifica del telefono, procedi con l'accesso
proceedWithLogin($conn, $result[1]);
?>
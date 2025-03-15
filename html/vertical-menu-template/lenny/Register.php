<?php
// Abilita la visualizzazione degli errori per il debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Importa le classi PHPMailer a livello globale
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Inizia la sessione
session_start();

// Se l'utente è già loggato, reindirizza alla dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Includi la connessione al database
require_once 'db_connection.php';

// Determina il percorso corretto del vendor
$vendorPaths = [
    __DIR__ . '/../../../vendor/',
    __DIR__ . '/../../vendor/',
    __DIR__ . '/../vendor/',
    __DIR__ . '/vendor/',
    'C:/laragon/www/lenny-dev/vendor/'
];

$vendorPath = null;
foreach ($vendorPaths as $path) {
    if (is_dir($path)) {
        $vendorPath = $path;
        break;
    }
}

// Se il percorso del vendor è stato trovato, includi i file di PHPMailer
if ($vendorPath !== null) {
    require_once $vendorPath . 'phpmailer/phpmailer/src/Exception.php';
    require_once $vendorPath . 'phpmailer/phpmailer/src/PHPMailer.php';
    require_once $vendorPath . 'phpmailer/phpmailer/src/SMTP.php';
} else {
    // Registra un errore se non è possibile trovare PHPMailer
    error_log("Impossibile trovare la directory vendor per PHPMailer");
}

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

// Funzione per generare un codice di verifica
function generateVerificationCode($length = 6) {
    return substr(str_shuffle("0123456789"), 0, $length);
}

// Funzione per inviare una email usando PHPMailer e configurazioni dal database
function sendVerificationEmail($conn, $email, $fullName, $verificationCode) {
    global $vendorPath;
    
    // Se PHPMailer non è disponibile, usa la funzione mail() nativa
    if ($vendorPath === null) {
        return sendMailNative($conn, $email, $fullName, $verificationCode);
    }
    
    // Recupera le impostazioni email dal database
    $siteName = getSetting($conn, 'site_name', 'Lenny');
    $mailFromName = getSetting($conn, 'mail_from_name', $siteName);
    $mailFromAddress = getSetting($conn, 'mail_from_address', 'noreply@example.com');
    
    // Recupera le impostazioni SMTP dal database
    $mailDriver = getSetting($conn, 'mail_driver', 'smtp');
    $mailHost = getSetting($conn, 'mail_host', '');
    $mailPort = getSetting($conn, 'mail_port', '587');
    $mailEncryption = getSetting($conn, 'mail_encryption', 'tls');
    $mailUsername = getSetting($conn, 'mail_username', '');
    $mailPassword = getSetting($conn, 'mail_password', '');
    
    // Crea il corpo del messaggio
    $subject = "$siteName - Codice di verifica email";
    $messageBody = "
    <html>
    <body>
        <h2>Benvenuto su $siteName, $fullName!</h2>
        <p>Grazie per esserti registrato. Per completare la registrazione, inserisci il seguente codice di verifica:</p>
        <h3 style='background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 24px;'>$verificationCode</h3>
        <p>Il codice è valido per 15 minuti.</p>
        <p>Se non hai richiesto questa registrazione, puoi ignorare questa email.</p>
        <p>Cordiali saluti,<br>Il team di $siteName</p>
    </body>
    </html>
    ";
    
    try {
        // Inizializza PHPMailer
        $mail = new PHPMailer(true);
        
        // Impostazioni server
        if ($mailDriver == 'smtp') {
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->Port = $mailPort;
            
            if (!empty($mailEncryption)&&$mailEncryption != 'none') {
                $mail->SMTPSecure = $mailEncryption;
            }
            
            if (!empty($mailUsername)) {
                $mail->SMTPAuth = true;
                $mail->Username = $mailUsername;
                $mail->Password = $mailPassword;
            }
            
            // Debug SMTP (solo in ambiente di sviluppo)
            if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer [$level]: $str");
                };
            }
        }
        
        // Imposta il charset
        $mail->CharSet = 'UTF-8';
        
        // Mittente e destinatario
        $mail->setFrom($mailFromAddress, $mailFromName);
        $mail->addAddress($email, $fullName);
        
        // Contenuto email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $messageBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $messageBody));
        
        // Invia l'email
        if ($mail->send()) {
            // Registra il successo nei log di sistema se disponibili
            logSystemEvent($conn, 'info', "Email di verifica inviata a $email", isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
            return true;
        } else {
            // Registra l'errore nei log
            logSystemEvent($conn, 'error', "Errore invio email a $email: " . $mail->ErrorInfo, isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
            return false;
        }
    } catch (Exception $e) {
        // Registra l'eccezione nei log
        logSystemEvent($conn, 'error', "Eccezione invio email a $email: " . $e->getMessage(), isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
        
        // Prova con la funzione mail() nativa come fallback
        return sendMailNative($conn, $email, $fullName, $verificationCode);
    }
}

// Funzione per inviare email con la funzione mail() nativa
function sendMailNative($conn, $email, $fullName, $verificationCode) {
    $siteName = getSetting($conn, 'site_name', 'Lenny');
    $from_email = getSetting($conn, 'mail_from_address', 'noreply@example.com');
    $from_name = getSetting($conn, 'mail_from_name', $siteName);
    
    $subject = "$siteName - Codice di verifica email";
    $messageBody = "
    <html>
    <body>
        <h2>Benvenuto su $siteName, $fullName!</h2>
        <p>Grazie per esserti registrato. Per completare la registrazione, inserisci il seguente codice di verifica:</p>
        <h3 style='background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 24px;'>$verificationCode</h3>
        <p>Il codice è valido per 15 minuti.</p>
        <p>Se non hai richiesto questa registrazione, puoi ignorare questa email.</p>
        <p>Cordiali saluti,<br>Il team di $siteName</p>
    </body>
    </html>
    ";
    
    // Headers per email HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $from_name <$from_email>" . "\r\n";
    
    // Invia email con mail() nativa
    $success = mail($email, $subject, $messageBody, $headers);
    
    if ($success) {
        logSystemEvent($conn, 'info', "Email di verifica inviata a $email usando mail() nativa", isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
    } else {
        logSystemEvent($conn, 'error', "Errore invio email nativa a $email", isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
    }
    
    return $success;
}

// Funzione per registrare eventi di sistema nei log
function logSystemEvent($conn, $level, $message, $user_id = null) {
    // Verifica se esiste la tabella system_logs
    $resultTable = $conn->query("SHOW TABLES LIKE 'system_logs'");
    if ($resultTable&&$resultTable->num_rows > 0) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO system_logs (level, message, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssiss", $level, $message, $user_id, $ip, $userAgent);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Scrivi anche in un file di log
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/debug_log_' . date('Y-m-d') . '.txt';
    $logMessage = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Funzione per inviare un SMS con il codice di verifica usando le impostazioni del database
function sendVerificationSMS($conn, $phoneNumber, $verificationCode) {
    // Recupera le impostazioni SMS dal database
    $provider = getSetting($conn, 'default_sms_provider', 'twilio');
    $template = getSetting($conn, 'sms_verification_template', 'Il tuo codice di verifica è: {code}. Valido per 15 minuti.');
    $smsValidity = getSetting($conn, 'sms_validity_period', '15');
    
    // Sostituisci i placeholders nel template
    $message = str_replace('{code}', $verificationCode, $template);
    $message = str_replace('{minutes}', $smsValidity, $message);
    
    // Scegli il provider in base alle impostazioni
    if ($provider == 'twilio'&&getSetting($conn, 'twilio_enabled', '0') == '1') {
        return sendTwilioSMS($conn, $phoneNumber, $message);
    } elseif ($provider == 'messagebird'&&getSetting($conn, 'messagebird_enabled', '0') == '1') {
        return sendMessageBirdSMS($conn, $phoneNumber, $message);
    } elseif ($provider == 'vonage'&&getSetting($conn, 'vonage_enabled', '0') == '1') {
        return sendVonageSMS($conn, $phoneNumber, $message);
    } elseif ($provider == 'plivo'&&getSetting($conn, 'plivo_enabled', '0') == '1') {
        return sendPlivoSMS($conn, $phoneNumber, $message);
    } elseif ($provider == 'firebase'&&getSetting($conn, 'firebase_sms_enabled', '0') == '1') {
        return sendFirebaseSMS($conn, $phoneNumber, $message);
    } else {
        // Se nessun provider è configurato, simula il successo in ambiente di sviluppo
        if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            logSystemEvent($conn, 'info', "SIMULAZIONE SMS: Invio a $phoneNumber del codice $verificationCode");
            return true;
        }
        
        logSystemEvent($conn, 'error', "Nessun provider SMS valido configurato per l'invio a $phoneNumber");
        return false;
    }
}

// Funzione per inviare SMS tramite Twilio
function sendTwilioSMS($conn, $phoneNumber, $message) {
    $accountSid = getSetting($conn, 'twilio_account_sid', '');
    $authToken = getSetting($conn, 'twilio_auth_token', '');
    $fromNumber = getSetting($conn, 'twilio_phone_number', '');

    // In ambiente di sviluppo, simula l'invio
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        logSystemEvent($conn, 'info', "SIMULAZIONE Twilio SMS: Invio a $phoneNumber: $message");
        return true;
    }

    // Implementazione reale di Twilio richiederebbe la libreria Twilio
    // Qui l'esempio usa cURL per chiamare l'API Twilio
    
    try {
        $url = "https://api.twilio.com/2010-04-01/Accounts/$accountSid/Messages.json";
        
        $data = [
            'From' => $fromNumber,
            'To' => $phoneNumber,
            'Body' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "$accountSid:$authToken");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpcode >= 200&&$httpcode < 300) {
            logSystemEvent($conn, 'info', "SMS inviato con successo a $phoneNumber via Twilio");
            return true;
        } else {
            logSystemEvent($conn, 'error', "Errore invio SMS a $phoneNumber via Twilio: $response");
            return false;
        }
    } catch (Exception $e) {
        logSystemEvent($conn, 'error', "Eccezione invio SMS a $phoneNumber via Twilio: " . $e->getMessage());
        return false;
    }
}

// Funzione per inviare SMS tramite MessageBird
function sendMessageBirdSMS($conn, $phoneNumber, $message) {
    // In ambiente di sviluppo, simula l'invio
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        logSystemEvent($conn, 'info', "SIMULAZIONE MessageBird SMS: Invio a $phoneNumber: $message");
        return true;
    }
    
    // Implementazione reale di MessageBird richiederebbe la libreria MessageBird
    logSystemEvent($conn, 'info', "SMS simulato via MessageBird a $phoneNumber");
    return true;
}

// Funzione per inviare SMS tramite Vonage (ex Nexmo)
function sendVonageSMS($conn, $phoneNumber, $message) {
    // In ambiente di sviluppo, simula l'invio
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        logSystemEvent($conn, 'info', "SIMULAZIONE Vonage SMS: Invio a $phoneNumber: $message");
        return true;
    }
    
    // Implementazione reale di Vonage richiederebbe la libreria Vonage
    logSystemEvent($conn, 'info', "SMS simulato via Vonage a $phoneNumber");
    return true;
}

// Funzione per inviare SMS tramite Plivo
function sendPlivoSMS($conn, $phoneNumber, $message) {
    // In ambiente di sviluppo, simula l'invio
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        logSystemEvent($conn, 'info', "SIMULAZIONE Plivo SMS: Invio a $phoneNumber: $message");
        return true;
    }
    
    // Implementazione reale di Plivo richiederebbe la libreria Plivo
    logSystemEvent($conn, 'info', "SMS simulato via Plivo a $phoneNumber");
    return true;
}

// Funzione per inviare SMS tramite Firebase
function sendFirebaseSMS($conn, $phoneNumber, $message) {
    // In ambiente di sviluppo, simula l'invio
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        logSystemEvent($conn, 'info', "SIMULAZIONE Firebase SMS: Invio a $phoneNumber: $message");
        return true;
    }
    
    // Implementazione reale di Firebase richiederebbe la libreria Firebase
    logSystemEvent($conn, 'info', "SMS simulato via Firebase a $phoneNumber");
    return true;
}

// Verifica se esiste la colonna phone nella tabella users, altrimenti la crea
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if (!$result || $result->num_rows == 0) {
    // La colonna non esiste, la aggiungiamo
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER email");
}

// Recupera le impostazioni di sistema dal database
$siteName = getSetting($conn, 'site_name', 'Lenny');
$siteLogo = getSetting($conn, 'site_logo', '');
$primaryColor = getSetting($conn, 'primary_color', '#5A8DEE');

// Recupera le impostazioni di social login dal database
$facebookEnabled = getSetting($conn, 'facebook_enabled', '0');
$googleEnabled = getSetting($conn, 'google_enabled', '0');
$twitterEnabled = getSetting($conn, 'twitter_enabled', '0');
$githubEnabled = getSetting($conn, 'github_enabled', '0');

// Recupera le impostazioni di sicurezza per la password dal database
$requireUppercase = getSetting($conn, 'require_uppercase', '1');
$requireLowercase = getSetting($conn, 'require_lowercase', '1');
$requireNumber = getSetting($conn, 'require_number', '1');
$requireSpecial = getSetting($conn, 'require_special', '1');
$minPasswordLength = getSetting($conn, 'min_password_length', '8');

// Variabili per i messaggi di errore/successo
$error_message = '';
$success_message = '';
$verification_needed = false;
$verification_email = false;
$verification_phone = false;

// Gestisci il form di registrazione quando viene inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ottieni i dati dal form
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $termsAccepted = isset($_POST['terms'])&&$_POST['terms'] === 'on';
    
    // Validazione di base
    if (empty($username) || empty($email) || empty($password) || empty($fullName) || empty($confirmPassword) || empty($phone)) {
        $error_message = 'Per favore, compila tutti i campi richiesti, incluso il numero di telefono.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Inserisci un indirizzo email valido.';
    } elseif ($password != $confirmPassword) {
        $error_message = 'Le password non corrispondono.';
    } elseif (!$termsAccepted) {
        $error_message = 'Devi accettare i termini e le condizioni.';
    } else {
        // Validazione della password basata sulle impostazioni dal database
        $passwordErrors = [];
        
        if ($requireUppercase == '1'&&!preg_match('/[A-Z]/', $password)) {
            $passwordErrors[] = 'almeno una lettera maiuscola';
        }
        
        if ($requireLowercase == '1'&&!preg_match('/[a-z]/', $password)) {
            $passwordErrors[] = 'almeno una lettera minuscola';
        }
        
        if ($requireNumber == '1'&&!preg_match('/[0-9]/', $password)) {
            $passwordErrors[] = 'almeno un numero';
        }
        
        if ($requireSpecial == '1'&&!preg_match('/[^A-Za-z0-9]/', $password)) {
            $passwordErrors[] = 'almeno un carattere speciale';
        }
        
        if (strlen($password) < intval($minPasswordLength)) {
            $passwordErrors[] = 'almeno ' . $minPasswordLength . ' caratteri';
        }
        
        if (!empty($passwordErrors)) {
            $error_message = 'La password deve contenere: ' . implode(', ', $passwordErrors) . '.';
        } else {
            try {
                // Verifica se l'username o l'email esistono già
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                
                if (!$stmt) {
                    throw new Exception("Errore nella preparazione della query: " . $conn->error);
                }
                
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error_message = 'Nome utente o email già registrati. Prova con credenziali diverse.';
                } else {
                    // Verifica se è stata richiesta la verifica via email o SMS
                    if (!isset($_POST['email_verified']) || $_POST['email_verified'] !== 'true') {
                        // Genera un codice di verifica per email e lo salva in sessione
                        $emailCode = generateVerificationCode();
                        $_SESSION['email_verification'] = [
                            'email' => $email,
                            'code' => $emailCode,
                            'expires' => time() + 900 // 15 minuti
                        ];
                        
                        // Invia l'email di verifica
                        if (sendVerificationEmail($conn, $email, $fullName, $emailCode)) {
                            $verification_needed = true;
                            $verification_email = true;
                            $success_message = "Abbiamo inviato un codice di verifica all'indirizzo email fornito.";
                            
                            // Salva temporaneamente i dati del form in sessione
                            $_SESSION['registration_data'] = [
                                'username' => $username,
                                'email' => $email,
                                'password' => $password, // la password verrà hashata solo alla conferma
                                'full_name' => $fullName,
                                'phone' => $phone
                            ];
                        } else {
                            $error_message = "Impossibile inviare l'email di verifica. Riprova più tardi.";
                        }
                    } elseif (!isset($_POST['phone_verified']) || $_POST['phone_verified'] !== 'true') {
                        // Genera un codice di verifica per SMS e lo salva in sessione
                        $smsCode = generateVerificationCode();
                        $_SESSION['phone_verification'] = [
                            'phone' => $phone,
                            'code' => $smsCode,
                            'expires' => time() + 900 // 15 minuti
                        ];
                        
                        // Invia l'SMS di verifica
                        if (sendVerificationSMS($conn, $phone, $smsCode)) {
                            $verification_needed = true;
                            $verification_phone = true;
                            $success_message = "Abbiamo inviato un codice di verifica al numero di telefono fornito.";
                            
                            // Mantieni i dati già verificati
                            $_SESSION['registration_data']['email_verified'] = true;
                        } else {
                            $error_message = "Impossibile inviare l'SMS di verifica. Riprova più tardi.";
                        }
                    } else {
                        // Tutto è stato verificato, registra l'utente
                        // Hash della password
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Assegna sempre il ruolo "user" (ID 3)
                        $roleId = 3;
                        
                        // Inserisci il nuovo utente
                        $insertStmt = $conn->prepare("INSERT INTO users (username, password, email, phone, full_name, role_id, status, password_changed_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
                        
                        if (!$insertStmt) {
                            throw new Exception("Errore nella preparazione della query di inserimento: " . $conn->error);
                        }
                        
                        $status = 'active';
                        $insertStmt->bind_param("sssssi", $username, $hashedPassword, $email, $phone, $fullName, $roleId);
                        
                        if ($insertStmt->execute()) {
                            $userId = $insertStmt->insert_id;
                            
                            // Registra il nuovo accesso nei log
                            $log_ip = $_SERVER['REMOTE_ADDR'];
                            $log_agent = $_SERVER['HTTP_USER_AGENT'];
                            
                            // Verifica se esiste la tabella login_logs
                            $resultTable = $conn->query("SHOW TABLES LIKE 'login_logs'");
                            if ($resultTable&&$resultTable->num_rows > 0) {
                                $logStmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, success, notes, created_at) VALUES (?, ?, ?, 1, 'Registrazione nuovo utente', NOW())");
                                
                                if ($logStmt) {
                                    $logStmt->bind_param("iss", $userId, $log_ip, $log_agent);
                                    $logStmt->execute();
                                    $logStmt->close();
                                }
                            }
                            
                            // Registra nei log di sistema
                            logSystemEvent($conn, 'info', "Nuovo utente registrato: $username ($email)", $userId);
                            
                            // Pulisci i dati di registrazione dalla sessione
                            unset($_SESSION['registration_data']);
                            unset($_SESSION['email_verification']);
                            unset($_SESSION['phone_verification']);
                            
                            // Imposta un messaggio di successo e reindirizza al login
                            $_SESSION['registration_success'] = true;
                            header("Location: login.php");
                            exit;
                        } else {
                            $error_message = "Errore durante la registrazione. Riprova più tardi.";
                            logSystemEvent($conn, 'error', "Errore registrazione utente: " . $insertStmt->error);
                        }
                        
                        $insertStmt->close();
                    }
                }
                
                $stmt->close();
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                logSystemEvent($conn, 'error', 'Registration error: ' . $e->getMessage());
            }
        }
    }
}

// Verifica del codice email/SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['verification_code'])) {
    $verificationCode = trim($_POST['verification_code']);
    $verificationType = $_POST['verification_type'];
    
    if ($verificationType === 'email'&&isset($_SESSION['email_verification'])) {
        if (time() > $_SESSION['email_verification']['expires']) {
            $error_message = "Il codice di verifica è scaduto. Richiedi un nuovo codice.";
        } elseif ($verificationCode === $_SESSION['email_verification']['code']) {
            // Email verificata con successo
            $_SESSION['registration_data']['email_verified'] = true;
            
            // Passa alla verifica del telefono
            $phone = $_SESSION['registration_data']['phone'];
            $smsCode = generateVerificationCode();
            $_SESSION['phone_verification'] = [
                'phone' => $phone,
                'code' => $smsCode,
                'expires' => time() + 900 // 15 minuti
            ];
            
            if (sendVerificationSMS($conn, $phone, $smsCode)) {
                $verification_needed = true;
                $verification_email = false;
                $verification_phone = true;
                $success_message = "Email verificata! Abbiamo inviato un codice di verifica al tuo numero di telefono.";
            } else {
                $error_message = "Impossibile inviare l'SMS di verifica. Riprova più tardi.";
            }
        } else {
            $error_message = "Codice di verifica non valido. Riprova.";
        }
    } elseif ($verificationType === 'phone'&&isset($_SESSION['phone_verification'])) {
        if (time() > $_SESSION['phone_verification']['expires']) {
            $error_message = "Il codice di verifica è scaduto. Richiedi un nuovo codice.";
        } elseif ($verificationCode === $_SESSION['phone_verification']['code']) {
            // Telefono verificato con successo
            $_SESSION['registration_data']['phone_verified'] = true;
            
            // Procedi con la registrazione
            $username = $_SESSION['registration_data']['username'];
            $email = $_SESSION['registration_data']['email'];
            $password = $_SESSION['registration_data']['password'];
            $fullName = $_SESSION['registration_data']['full_name'];
            $phone = $_SESSION['registration_data']['phone'];
            
            // Hash della password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Assegna sempre il ruolo "user" (ID 3)
            $roleId = 3;
            
            // Inserisci il nuovo utente
            $insertStmt = $conn->prepare("INSERT INTO users (username, password, email, phone, full_name, role_id, status, password_changed_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
            
            if (!$insertStmt) {
                throw new Exception("Errore nella preparazione della query di inserimento: " . $conn->error);
            }
            
            $status = 'active';
            $insertStmt->bind_param("sssssi", $username, $hashedPassword, $email, $phone, $fullName, $roleId);
            
            if ($insertStmt->execute()) {
                $userId = $insertStmt->insert_id;
                
                // Registra il nuovo accesso nei log
                $log_ip = $_SERVER['REMOTE_ADDR'];
                $log_agent = $_SERVER['HTTP_USER_AGENT'];
                
                // Verifica se esiste la tabella login_logs
                $resultTable = $conn->query("SHOW TABLES LIKE 'login_logs'");
                if ($resultTable&&$resultTable->num_rows > 0) {
                    $logStmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, success, notes, created_at) VALUES (?, ?, ?, 1, 'Registrazione nuovo utente', NOW())");
                    
                    if ($logStmt) {
                        $logStmt->bind_param("iss", $userId, $log_ip, $log_agent);
                        $logStmt->execute();
                        $logStmt->close();
                    }
                }
                
                // Registra nei log di sistema
                logSystemEvent($conn, 'info', "Nuovo utente registrato: $username ($email)", $userId);
                
                // Pulisci i dati di registrazione dalla sessione
                unset($_SESSION['registration_data']);
                unset($_SESSION['email_verification']);
                unset($_SESSION['phone_verification']);
                
                // Imposta un messaggio di successo e reindirizza al login
                $_SESSION['registration_success'] = true;
                header("Location: login.php");
                exit;
            } else {
                $error_message = "Errore durante la registrazione. Riprova più tardi.";
                logSystemEvent($conn, 'error', "Errore registrazione utente: " . $insertStmt->error);
            }
            
            $insertStmt->close();
        } else {
            $error_message = "Codice di verifica non valido. Riprova.";
        }
    }
}

// Richiesta di un nuovo codice
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['resend_code'])) {
    $verificationType = $_POST['verification_type'];
    
    if ($verificationType === 'email'&&isset($_SESSION['registration_data']['email'])) {
        $email = $_SESSION['registration_data']['email'];
        $fullName = $_SESSION['registration_data']['full_name'];
        $emailCode = generateVerificationCode();
        $_SESSION['email_verification'] = [
            'email' => $email,
            'code' => $emailCode,
            'expires' => time() + 900 // 15 minuti
        ];
        
        if (sendVerificationEmail($conn, $email, $fullName, $emailCode)) {
            $success_message = "Abbiamo inviato un nuovo codice di verifica al tuo indirizzo email.";
            logSystemEvent($conn, 'info', "Codice di verifica email reinviato a: $email");
        } else {
            $error_message = "Impossibile inviare l'email di verifica. Riprova più tardi.";
            logSystemEvent($conn, 'error', "Errore reinvio codice email a: $email");
        }
    } elseif ($verificationType === 'phone'&&isset($_SESSION['registration_data']['phone'])) {
        $phone = $_SESSION['registration_data']['phone'];
        $smsCode = generateVerificationCode();
        $_SESSION['phone_verification'] = [
            'phone' => $phone,
            'code' => $smsCode,
            'expires' => time() + 900 // 15 minuti
        ];
        
        if (sendVerificationSMS($conn, $phone, $smsCode)) {
            $success_message = "Abbiamo inviato un nuovo codice di verifica al tuo numero di telefono.";
            logSystemEvent($conn, 'info', "Codice di verifica SMS reinviato a: $phone");
        } else {
            $error_message = "Impossibile inviare l'SMS di verifica. Riprova più tardi.";
            logSystemEvent($conn, 'error', "Errore reinvio codice SMS a: $phone");
        }
    }
}

// Chiudi la connessione al database
$conn->close();
?>
<!doctype html>
<html
  lang="it"
  class="layout-wide customizer-hide"
  dir="ltr"
  data-skin="default"
  data-assets-path="../../../assets/"
  data-template="vertical-menu-template"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Registrazione | <?php echo htmlspecialchars($siteName); ?></title>

    <meta name="description" content="<?php echo htmlspecialchars($siteName); ?> Registrazione Nuovo Utente" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/css/pages/page-auth.css" />

    <!-- Helpers -->
    <script src="../../../assets/vendor/js/helpers.js"></script>
    <script src="../../../assets/vendor/js/template-customizer.js"></script>
    <script src="../../../assets/js/config.js"></script>
    
    <!-- Custom Styles -->
    <style>
      :root {
        --primary-color: <?php echo $primaryColor; ?>;
      }
      
      .cursor-pointer {
        cursor: pointer;
      }
      .input-group-text:hover {
        background-color: #eff2f6;
      }
      
      .btn-primary {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
      }
      .btn-primary:hover {
        opacity: 0.9;
      }
      .text-primary, .app-brand-text {
        color: var(--primary-color) !important;
      }
      
      .verification-container {
        margin-top: 1.5rem;
        padding: 1.5rem;
        border: 1px solid #e9ecef;
        border-radius: 0.375rem;
        background-color: #f8f9fa;
      }
      
      .verification-header {
        margin-bottom: 1rem;
        color: #495057;
      }
      
      .verification-code-input {
        font-size: 1.2rem;
        letter-spacing: 0.25rem;
        text-align: center;
      }
      
      .resend-link {
        display: inline-block;
        margin-top: 0.5rem;
        color: var(--primary-color);
        text-decoration: none;
        font-size: 0.875rem;
      }
      
      .resend-link:hover {
        text-decoration: underline;
      }
      
      .password-requirements {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
      }
      
      .required-field::after {
        content: " *";
        color: #ff3e1d;
      }
    </style>
  </head>

  <body>
    <!-- Content -->

    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
          <!-- Register Card -->
          <div class="card">
            <div class="card-body">
              <!-- Logo -->
              <div class="app-brand justify-content-center mb-4">
                <a href="index.php" class="app-brand-link">
                  <span class="app-brand-logo demo">
                    <?php if (!empty($siteLogo)): ?>
                      <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?> Logo" height="32">
                    <?php else: ?>
                      <div class="rounded-circle bg-primary p-2">
                        <i class="ti tabler-tools-kitchen-2 text-white"></i>
                      </div>
                    <?php endif; ?>
                  </span>
                  <span class="app-brand-text demo text-heading fw-bold"><?php echo strtoupper(htmlspecialchars($siteName)); ?></span>
                </a>
              </div>
              <!-- /Logo -->
              
              <?php if ($verification_needed): ?>
                <!-- Verificazione Email o Telefono -->
                <div class="verification-container">
                  <h4 class="verification-header">
                    <?php if ($verification_email): ?>
                      Verifica la tua email
                    <?php elseif ($verification_phone): ?>
                      Verifica il tuo numero di telefono
                    <?php endif; ?>
                  </h4>
                  
                  <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-3">
                      <i class="tabler-alert-circle me-2"></i>
                      <div><?php echo htmlspecialchars($error_message); ?></div>
                    </div>
                  <?php endif; ?>
                  
                  <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success d-flex align-items-center mb-3">
                      <i class="tabler-check-circle me-2"></i>
                      <div><?php echo htmlspecialchars($success_message); ?></div>
                    </div>
                  <?php endif; ?>
                  
                  <p>
                    <?php if ($verification_email): ?>
                      Abbiamo inviato un codice di verifica a <strong><?php echo htmlspecialchars($_SESSION['registration_data']['email']); ?></strong>
                    <?php elseif ($verification_phone): ?>
                      Abbiamo inviato un codice di verifica al numero <strong><?php echo htmlspecialchars($_SESSION['registration_data']['phone']); ?></strong>
                    <?php endif; ?>
                  </p>
                  
                  <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="mb-3">
                      <label for="verification_code" class="form-label">Codice di verifica</label>
                      <input
                        type="text"
                        class="form-control verification-code-input"
                        id="verification_code"
                        name="verification_code"
                        placeholder="Inserisci il codice"
                        maxlength="6"
                        required />
                    </div>
                    
                    <input type="hidden" name="verification_type" value="<?php echo $verification_email ? 'email' : 'phone'; ?>">
                    
                    <button type="submit" class="btn btn-primary d-grid w-100 mb-3">Verifica</button>
                    
                    <div class="text-center">
                      <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" name="resend_code" value="1">
                        <input type="hidden" name="verification_type" value="<?php echo $verification_email ? 'email' : 'phone'; ?>">
                        <button type="submit" class="btn btn-link resend-link">Non hai ricevuto il codice? Invia di nuovo</button>
                      </form>
                    </div>
                  </form>
                </div>
                
              <?php else: ?>
                <!-- Form di Registrazione Standard -->
                <h4 class="mb-1">L'avventura inizia qui 🚀</h4>
                <p class="mb-4">Rendi semplice e divertente la gestione del tuo account!</p>
  
                <?php if (!empty($error_message)): ?>
                  <div class="alert alert-danger d-flex align-items-center mb-3">
                    <i class="tabler-alert-circle me-2"></i>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                  </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                  <div class="alert alert-success d-flex align-items-center mb-3">
                    <i class="tabler-check-circle me-2"></i>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                  </div>
                <?php endif; ?>
  
                <form id="formAuthentication" class="mb-3" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                  <div class="mb-3 form-control-validation">
                    <label for="full_name" class="form-label required-field">Nome e Cognome</label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="tabler-user"></i></span>
                      <input
                        type="text"
                        class="form-control"
                        id="full_name"
                        name="full_name"
                        placeholder="Inserisci il tuo nome e cognome"
                        value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                        autofocus
                        required />
                    </div>
                  </div>
                  
                  <div class="mb-3 form-control-validation">
                    <label for="username" class="form-label required-field">Username</label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="tabler-user-circle"></i></span>
                      <input
                        type="text"
                        class="form-control"
                        id="username"
                        name="username"
                        placeholder="Scegli un username"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        required />
                    </div>
                  </div>
                  
                  <div class="mb-3 form-control-validation">
                    <label for="email" class="form-label required-field">Email</label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="tabler-mail"></i></span>
                      <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email" 
                        placeholder="Inserisci la tua email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        required />
                    </div>
                  </div>
                  
                  <div class="mb-3 form-control-validation">
                    <label for="phone" class="form-label required-field">Telefono</label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="tabler-phone"></i></span>
                      <input 
                        type="tel" 
                        class="form-control" 
                        id="phone" 
                        name="phone" 
                        placeholder="Inserisci il tuo numero di telefono"
                        value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                        required />
                    </div>
                  </div>
                  
                  <div class="mb-3 form-password-toggle form-control-validation">
                    <label class="form-label required-field" for="password">Password</label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="tabler-lock"></i></span>
                      <input
                        type="password"
                        id="password"
                        class="form-control"
                        name="password"
                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                        aria-describedby="password"
                        required />
                      <span class="input-group-text cursor-pointer" id="toggle-password"><i class="tabler-eye-off"></i></span>
                    </div>
                    <div class="password-requirements">
                      La password deve contenere: 
                      <?php if ($requireUppercase == '1'): ?><span class="req-uppercase">maiuscole</span>, <?php endif; ?>
                      <?php if ($requireLowercase == '1'): ?><span class="req-lowercase">minuscole</span>, <?php endif; ?>
                      <?php if ($requireNumber == '1'): ?><span class="req-number">numeri</span>, <?php endif; ?>
                      <?php if ($requireSpecial == '1'): ?><span class="req-special">caratteri speciali</span>, <?php endif; ?>
                      <?php if (intval($minPasswordLength) > 0): ?><span class="req-length">almeno <?php echo $minPasswordLength; ?> caratteri</span><?php endif; ?>
                    </div>
                  </div>
                  
                  <div class="mb-3 form-password-toggle form-control-validation">
                    <label class="form-label required-field" for="confirm_password">Conferma Password</label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="tabler-lock"></i></span>
                      <input
                        type="password"
                        id="confirm_password"
                        class="form-control"
                        name="confirm_password"
                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                        aria-describedby="confirm_password"
                        required />
                      <span class="input-group-text cursor-pointer" id="toggle-confirm-password"><i class="tabler-eye-off"></i></span>
                    </div>
                  </div>
                  
                  <div class="mb-3 form-control-validation">
                    <div class="form-check mb-0 ms-2">
                      <input class="form-check-input" type="checkbox" id="terms" name="terms" required />
                      <label class="form-check-label" for="terms">
                        Accetto la
                        <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#privacyModal">privacy policy&i termini</a>
                      </label>
                    </div>
                  </div>
                  
                  <button class="btn btn-primary d-grid w-100" type="submit">Registrati</button>
                </form>
  
                <p class="text-center">
                  <span>Hai già un account?</span>
                  <a href="login.php">
                    <span>Accedi</span>
                  </a>
                </p>
  
                <?php if ($facebookEnabled == '1' || $googleEnabled == '1' || $twitterEnabled == '1' || $githubEnabled == '1'): ?>
                <div class="divider my-4">
                  <div class="divider-text">oppure</div>
                </div>
  
                <div class="d-flex justify-content-center">
                  <?php if ($facebookEnabled == '1'): ?>
                  <a href="auth/facebook.php" class="btn btn-icon rounded-circle btn-text-facebook me-1_5">
                    <i class="icon-base ti tabler-brand-facebook-filled icon-20px"></i>
                  </a>
                  <?php endif; ?>
  
                  <?php if ($twitterEnabled == '1'): ?>
                  <a href="auth/twitter.php" class="btn btn-icon rounded-circle btn-text-twitter me-1_5">
                    <i class="icon-base ti tabler-brand-twitter-filled icon-20px"></i>
                  </a>
                  <?php endif; ?>
  
                  <?php if ($githubEnabled == '1'): ?>
                  <a href="auth/github.php" class="btn btn-icon rounded-circle btn-text-github me-1_5">
                    <i class="icon-base ti tabler-brand-github-filled icon-20px"></i>
                  </a>
                  <?php endif; ?>
  
                  <?php if ($googleEnabled == '1'): ?>
                  <a href="auth/google.php" class="btn btn-icon rounded-circle btn-text-google-plus">
                    <i class="icon-base ti tabler-brand-google-filled icon-20px"></i>
                  </a>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
          <!-- Register Card -->
        </div>
      </div>
    </div>
    
    <!-- Modal Privacy Policy -->
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Privacy Policy e Termini d'Uso</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <h6>Informativa sulla Privacy</h6>
            <p>
              Questa Informativa sulla privacy descrive come vengono raccolti, utilizzati e condivisi i tuoi dati personali quando ti registri a <?php echo htmlspecialchars($siteName); ?>.
            </p>
            <p>
              <strong>Dati personali che raccogliamo:</strong><br>
              Quando ti registri, raccogliamo le seguenti informazioni:
              <ul>
                <li>Nome e cognome</li>
                <li>Indirizzo email</li>
                <li>Username</li>
                <li>Numero di telefono</li>
              </ul>
            </p>
            <p>
              <strong>Come utilizziamo i tuoi dati personali:</strong><br>
              Utilizziamo le informazioni raccolte per:
              <ul>
                <li>Creare e gestire il tuo account</li>
                <li>Comunicare con te riguardo al tuo account o ai nostri servizi</li>
                <li>Migliorare e personalizzare la tua esperienza sulla piattaforma</li>
                <li>Prevenire frodi e garantire la sicurezza</li>
              </ul>
            </p>
            <p>
              <strong>Condivisione dei dati:</strong><br>
              Non condividiamo i tuoi dati personali con terze parti, eccetto quando:
              <ul>
                <li>È necessario per fornire i servizi richiesti</li>
                <li>Siamo legalmente obbligati a farlo</li>
                <li>Hai fornito il tuo consenso esplicito</li>
              </ul>
            </p>
            <hr>
            <h6>Termini d'Uso</h6>
            <p>
              Utilizzando il nostro servizio, accetti di:
              <ul>
                <li>Fornire informazioni accurate durante la registrazione</li>
                <li>Mantenere la riservatezza delle tue credenziali di accesso</li>
                <li>Non utilizzare il servizio per scopi illegali o non autorizzati</li>
                <li>Non violare i diritti di proprietà intellettuale</li>
                <li>Rispettare le linee guida della community e gli altri utenti</li>
              </ul>
            </p>
            <p>
              Ci riserviamo il diritto di modificare o terminare il servizio in qualsiasi momento, con o senza preavviso.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Ho capito</button>
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
    <script src="../../../assets/vendor/libs/@form-validation/popular.js"></script>
    <script src="../../../assets/vendor/libs/@form-validation/bootstrap5.js"></script>
    <script src="../../../assets/vendor/libs/@form-validation/auto-focus.js"></script>

    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>

    <!-- Page JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Gestisce il toggle della password
      const togglePasswordBtn = document.getElementById('toggle-password');
      const passwordInput = document.getElementById('password');
      
      if (togglePasswordBtn&&passwordInput) {
        togglePasswordBtn.addEventListener('click', function() {
          // Cambia il tipo di input
          const currentType = passwordInput.getAttribute('type');
          const newType = currentType === 'password' ? 'text' : 'password';
          passwordInput.setAttribute('type', newType);
          
          // Cambia l'icona
          const icon = togglePasswordBtn.querySelector('i');
          if (newType === 'password') {
            icon.classList.remove('tabler-eye');
            icon.classList.add('tabler-eye-off');
          } else {
            icon.classList.remove('tabler-eye-off');
            icon.classList.add('tabler-eye');
          }
        });
      }
      
      // Gestisce il toggle della conferma password
      const toggleConfirmPasswordBtn = document.getElementById('toggle-confirm-password');
      const confirmPasswordInput = document.getElementById('confirm_password');
      
      if (toggleConfirmPasswordBtn&&confirmPasswordInput) {
        toggleConfirmPasswordBtn.addEventListener('click', function() {
          // Cambia il tipo di input
          const currentType = confirmPasswordInput.getAttribute('type');
          const newType = currentType === 'password' ? 'text' : 'password';
          confirmPasswordInput.setAttribute('type', newType);
          
          // Cambia l'icona
          const icon = toggleConfirmPasswordBtn.querySelector('i');
          if (newType === 'password') {
            icon.classList.remove('tabler-eye');
            icon.classList.add('tabler-eye-off');
          } else {
            icon.classList.remove('tabler-eye-off');
            icon.classList.add('tabler-eye');
          }
        });
      }
      
      // Validazione password in tempo reale
      if (passwordInput) {
        passwordInput.addEventListener('input', function() {
          const password = this.value;
          
          // Verifica i requisiti della password
          <?php if ($requireUppercase == '1'): ?>
          const hasUppercase = /[A-Z]/.test(password);
          document.querySelector('.req-uppercase').style.color = hasUppercase ? 'green' : '#6c757d';
          <?php endif; ?>
          
          <?php if ($requireLowercase == '1'): ?>
          const hasLowercase = /[a-z]/.test(password);
          document.querySelector('.req-lowercase').style.color = hasLowercase ? 'green' : '#6c757d';
          <?php endif; ?>
          
          <?php if ($requireNumber == '1'): ?>
          const hasNumber = /[0-9]/.test(password);
          document.querySelector('.req-number').style.color = hasNumber ? 'green' : '#6c757d';
          <?php endif; ?>
          
          <?php if ($requireSpecial == '1'): ?>
          const hasSpecial = /[^A-Za-z0-9]/.test(password);
          document.querySelector('.req-special').style.color = hasSpecial ? 'green' : '#6c757d';
          <?php endif; ?>
          
          <?php if (intval($minPasswordLength) > 0): ?>
          const isLongEnough = password.length >= <?php echo intval($minPasswordLength); ?>;
          document.querySelector('.req-length').style.color = isLongEnough ? 'green' : '#6c757d';
          <?php endif; ?>
        });
      }
      
      // Validazione del form
      const registerForm = document.getElementById('formAuthentication');
      if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
          const fullName = document.getElementById('full_name').value.trim();
          const username = document.getElementById('username').value.trim();
          const email = document.getElementById('email').value.trim();
          const phone = document.getElementById('phone').value.trim();
          const password = passwordInput.value;
          const confirmPassword = confirmPasswordInput.value;
          const termsCheckbox = document.getElementById('terms');
          
          let isValid = true;
          let errorMessage = '';
          
          // Validazione campi obbligatori
          if (!fullName || !username || !email || !phone || !password || !confirmPassword) {
            errorMessage = 'Per favore, compila tutti i campi richiesti.';
            isValid = false;
          }
          // Validazione email
          else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errorMessage = 'Inserisci un indirizzo email valido.';
            isValid = false;
          }
          // Validazione corrispondenza password
          else if (password !== confirmPassword) {
            errorMessage = 'Le password non corrispondono.';
            isValid = false;
          }
          // Validazione termini e condizioni
          else if (!termsCheckbox.checked) {
            errorMessage = 'Devi accettare i termini e le condizioni.';
            isValid = false;
          }
          // Validazione password
          else {
            <?php if ($requireUppercase == '1'): ?>
            if (!/[A-Z]/.test(password)) {
              errorMessage = 'La password deve contenere almeno una lettera maiuscola.';
              isValid = false;
            }
            <?php endif; ?>
            
            <?php if ($requireLowercase == '1'): ?>
            if (!/[a-z]/.test(password)&&isValid) {
              errorMessage = 'La password deve contenere almeno una lettera minuscola.';
              isValid = false;
            }
            <?php endif; ?>
            
            <?php if ($requireNumber == '1'): ?>
            if (!/[0-9]/.test(password)&&isValid) {
              errorMessage = 'La password deve contenere almeno un numero.';
              isValid = false;
            }
            <?php endif; ?>
            
            <?php if ($requireSpecial == '1'): ?>
            if (!/[^A-Za-z0-9]/.test(password)&&isValid) {
              errorMessage = 'La password deve contenere almeno un carattere speciale.';
              isValid = false;
            }
            <?php endif; ?>
            
            <?php if (intval($minPasswordLength) > 0): ?>
            if (password.length < <?php echo intval($minPasswordLength); ?>&&isValid) {
              errorMessage = 'La password deve essere lunga almeno <?php echo intval($minPasswordLength); ?> caratteri.';
              isValid = false;
            }
            <?php endif; ?>
          }
          
          if (!isValid) {
            e.preventDefault();
            alert(errorMessage);
          }
        });
      }
      
      // Gestione verifica codice
      const verificationCodeInput = document.getElementById('verification_code');
      if (verificationCodeInput) {
        verificationCodeInput.addEventListener('input', function(e) {
          // Consenti solo numeri
          e.target.value = e.target.value.replace(/\D/g, '');
          
          // Limita a 6 caratteri
          if (e.target.value.length > 6) {
            e.target.value = e.target.value.slice(0, 6);
          }
        });
      }
    });
    </script>
  </body>
</html>
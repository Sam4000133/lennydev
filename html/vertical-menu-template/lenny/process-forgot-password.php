<?php
session_start();
require_once 'db_connection.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verifica se è una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot-psw.php');
    exit;
}

// Recupera l'email dal form
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

// Verifica se l'email è valida
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Inserisci un indirizzo email valido.';
    header('Location: forgot-psw.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Verifica se l'email esiste nel database
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() === 0) {
        // Non mostriamo all'utente se l'email esiste o meno per sicurezza
        $_SESSION['success'] = "Se l'indirizzo email è registrato nel nostro sistema, riceverai un'email con le istruzioni per recuperare la password.";
        header('Location: forgot-psw.php');
        exit;
    }
    
    // Genera un token di recupero
    $token = bin2hex(random_bytes(32));
    $expiryDate = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Controlla se esiste già un token per questo utente
    $stmt = $conn->prepare("SELECT id FROM password_resets WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        // Aggiorna il token esistente
        $stmt = $conn->prepare("UPDATE password_resets SET token = ?, expires_at = ?, created_at = NOW() WHERE email = ?");
        $stmt->execute([$token, $expiryDate, $email]);
    } else {
        // Inserisci un nuovo token
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$email, $token, $expiryDate]);
    }
    
    // Recupera le impostazioni email dal database
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings");
    $stmt->execute();
    
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Ottieni le configurazioni necessarie o usa valori predefiniti
    $mailHost = $settings['mail_host'] ?? 'mail.hydra-dev.xyz';
    $mailPort = $settings['mail_port'] ?? 587;
    $mailUsername = $settings['mail_username'] ?? 'info@hydra-dev.xyz';
    $mailPassword = $settings['mail_password'] ?? 'Eleum@s400';
    $mailFromAddress = $settings['mail_from_address'] ?? 'info@hydra-dev.com';
    $mailFromName = $settings['mail_from_name'] ?? 'Lenny';
    $mailEncryption = $settings['mail_encryption'] ?? 'tls';
    $siteName = $settings['site_name'] ?? 'Lenny';
    $siteUrl = $settings['site_url'] ?? 'http://' . $_SERVER['HTTP_HOST'];
    $primaryColor = $settings['primary_color'] ?? '#5A8DEE';
    
    // Costruisci l'URL di reset
    // Ottieni l'URL corrente e sostituisci il nome del file
    $currentDir = dirname($_SERVER['PHP_SELF']);
    $baseUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$currentDir";
    $resetUrl = $baseUrl . "/reset-password.php?token=" . $token;
    
    // Configura PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Impostazioni server
        $mail->isSMTP();
        $mail->Host = $mailHost;
        $mail->SMTPAuth = true;
        $mail->Username = $mailUsername;
        $mail->Password = $mailPassword;
        $mail->SMTPSecure = $mailEncryption;
        $mail->Port = $mailPort;
        $mail->CharSet = 'UTF-8';
        
        // Mittente e destinatario
        $mail->setFrom($mailFromAddress, $mailFromName);
        $mail->addAddress($email);
        
        // Contenuto
        $mail->isHTML(true);
        $mail->Subject = "$siteName - Recupero Password";
        
        // Crea il template dell'email
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: ' . $primaryColor . '; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; border: 1px solid #ddd; }
                .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #777; }
                .button { display: inline-block; background-color: ' . $primaryColor . '; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>' . $siteName . '</h2>
                </div>
                <div class="content">
                    <p>Gentile utente,</p>
                    <p>Abbiamo ricevuto una richiesta di recupero password per il tuo account.</p>
                    <p>Per reimpostare la tua password, clicca sul pulsante qui sotto:</p>
                    <p style="text-align: center;">
                        <a href="' . $resetUrl . '" class="button">Reimposta Password</a>
                    </p>
                    <p>Oppure copia e incolla il seguente link nel tuo browser:</p>
                    <p>' . $resetUrl . '</p>
                    <p>Se non hai richiesto il recupero della password, ignora questa email.</p>
                    <p>Il link scadrà tra 24 ore.</p>
                    <p>Grazie,<br>' . $siteName . ' Team</p>
                </div>
                <div class="footer">&copy; ' . date('Y') . ' ' . $siteName . '. Tutti i diritti riservati.
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Gentile utente,\n\n" .
                       "Abbiamo ricevuto una richiesta di recupero password per il tuo account.\n\n" .
                       "Per reimpostare la tua password, visita il seguente link:\n" .
                       $resetUrl . "\n\n" .
                       "Se non hai richiesto il recupero della password, ignora questa email.\n\n" .
                       "Il link scadrà tra 24 ore.\n\n" .
                       "Grazie,\n" . $siteName . " Team";
        
        $mail->send();
        
        // Log dell'invio email
        $stmt = $conn->prepare("INSERT INTO system_logs (level, message, ip_address) VALUES (?, ?, ?)");
        $stmt->execute(['info', "Email di recupero password inviata a $email", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Se l'indirizzo email è registrato nel nostro sistema, riceverai un'email con le istruzioni per recuperare la password.";
    } catch (Exception $e) {
        // Log dell'errore
        $stmt = $conn->prepare("INSERT INTO system_logs (level, message, ip_address) VALUES (?, ?, ?)");
        $stmt->execute(['error', "Errore nell'invio dell'email di recupero password a $email: " . $mail->ErrorInfo, $_SERVER['REMOTE_ADDR']]);
        
        error_log("Errore PHPMailer: " . $mail->ErrorInfo);
        $_SESSION['error'] = "Si è verificato un errore durante l'invio dell'email. Riprova più tardi.";
    }
    
} catch (PDOException $e) {
    // Log dell'errore nel database
    error_log("Errore nel database: " . $e->getMessage());
    $_SESSION['error'] = "Si è verificato un errore. Riprova più tardi.";
}

header('Location: forgot-psw.php');
exit;
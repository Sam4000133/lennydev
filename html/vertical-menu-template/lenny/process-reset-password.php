<?php
session_start();
require_once 'db_connection.php';

// Verifica se è una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot-psw.php');
    exit;
}

// Recupera i dati dal form
$token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password = filter_input(INPUT_POST, 'password');
$confirmPassword = filter_input(INPUT_POST, 'confirm-password');

// Verifica se tutti i campi sono compilati
if (empty($token) || empty($password) || empty($confirmPassword)) {
    $_SESSION['error'] = 'Tutti i campi sono obbligatori.';
    header("Location: reset-password.php?token=$token");
    exit;
}

// Verifica se le password coincidono
if ($password !== $confirmPassword) {
    $_SESSION['error'] = 'Le password non coincidono.';
    header("Location: reset-password.php?token=$token");
    exit;
}

try {
    $conn = getDBConnection();
    
    // Recupera le impostazioni delle password dal database
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('min_password_length', 'require_uppercase', 'require_lowercase', 'require_number', 'require_special')");
    $stmt->execute();
    
    $passwordSettings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $passwordSettings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Imposta valori predefiniti se non sono configurati
    $minLength = $passwordSettings['min_password_length'] ?? 8;
    $requireUppercase = $passwordSettings['require_uppercase'] ?? '1';
    $requireLowercase = $passwordSettings['require_lowercase'] ?? '1';
    $requireNumber = $passwordSettings['require_number'] ?? '1';
    $requireSpecial = $passwordSettings['require_special'] ?? '1';
    
    // Verifica la sicurezza della password
    $passwordErrors = [];
    
    // Verifica lunghezza minima
    if (strlen($password) < $minLength) {
        $passwordErrors[] = "La password deve contenere almeno $minLength caratteri.";
    }
    
    // Verifica maiuscole
    if ($requireUppercase === '1'&&!preg_match('/[A-Z]/', $password)) {
        $passwordErrors[] = "La password deve contenere almeno una lettera maiuscola.";
    }
    
    // Verifica minuscole
    if ($requireLowercase === '1'&&!preg_match('/[a-z]/', $password)) {
        $passwordErrors[] = "La password deve contenere almeno una lettera minuscola.";
    }
    
    // Verifica numeri
    if ($requireNumber === '1'&&!preg_match('/[0-9]/', $password)) {
        $passwordErrors[] = "La password deve contenere almeno un numero.";
    }
    
    // Verifica caratteri speciali
    if ($requireSpecial === '1'&&!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $passwordErrors[] = "La password deve contenere almeno un carattere speciale.";
    }
    
    // Se ci sono errori di validazione, mostrali all'utente
    if (!empty($passwordErrors)) {
        $_SESSION['error'] = implode('<br>', $passwordErrors);
        header("Location: reset-password.php?token=$token");
        exit;
    }
    
    // Verifica se il token è valido e non scaduto
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resetInfo) {
        $_SESSION['error'] = 'Il link di recupero non è valido o è scaduto.';
        header('Location: forgot-psw.php');
        exit;
    }
    
    // Aggiorna la password dell'utente
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $result = $stmt->execute([$hashedPassword, $resetInfo['email']]);
    
    if (!$result) {
        $_SESSION['error'] = "Si è verificato un errore durante l'aggiornamento della password.";
        header("Location: reset-password.php?token=$token");
        exit;
    }
    
    // Elimina il token di reset
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    
    // Log del reset password
    $stmt = $conn->prepare("INSERT INTO system_logs (level, message, ip_address) VALUES (?, ?, ?)");
    $stmt->execute(['info', "Password reimpostata con successo per " . $resetInfo['email'], $_SERVER['REMOTE_ADDR']]);
    
    $_SESSION['success'] = 'La tua password è stata reimpostata con successo. Ora puoi accedere con la nuova password.';
    header('Location: login.php');
    exit;
    
} catch (PDOException $e) {
    error_log("Errore nel database: " . $e->getMessage());
    $_SESSION['error'] = "Si è verificato un errore. Riprova più tardi.";
    header("Location: reset-password.php?token=$token");
    exit;
}
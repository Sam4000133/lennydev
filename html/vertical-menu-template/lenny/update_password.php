<?php
session_start();
require_once 'db_connection.php';

// Assicurati che gli errori vengano visualizzati
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Crea un file di log per debugging
$log_file = 'password_change.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Tentativo cambio password\n", FILE_APPEND);

// Prepara la risposta
$response = ['success' => false, 'message' => ''];

// Verifica se è una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo non valido';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Metodo non valido\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Verifica se l'utente è loggato
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    $response['message'] = 'Utente non autenticato';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Utente non autenticato\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Ottieni i dati dal POST
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Log dei dati ricevuti (oscurati per sicurezza)
file_put_contents($log_file, date('Y-m-d H:i:s') . " - User ID: $user_id\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Password attuale fornita: " . (empty($current_password) ? "NO" : "SI") . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Nuova password fornita: " . (empty($new_password) ? "NO" : "SI") . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Conferma password fornita: " . (empty($confirm_password) ? "NO" : "SI") . "\n", FILE_APPEND);

// Validazione base
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $response['message'] = 'Tutti i campi sono obbligatori';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Campi mancanti\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

if ($new_password !== $confirm_password) {
    $response['message'] = 'La nuova password e la conferma non corrispondono';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Password e conferma non corrispondono\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Ottieni la password corrente dell'utente
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $stored_password = $row['password'];
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Hash nel DB: " . substr($stored_password, 0, 10) . "...\n", FILE_APPEND);
    
    // Verifica password (procedura diretta)
    $password_match = password_verify($current_password, $stored_password);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Verifica password: " . ($password_match ? "Successo" : "Fallimento") . "\n", FILE_APPEND);
    
    if ($password_match) {
        // Genera nuovo hash
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Nuovo hash generato\n", FILE_APPEND);
        
        // Aggiorna la password
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $new_hash, $user_id);
        
        if ($update->execute()) {
            $response['success'] = true;
            $response['message'] = 'Password aggiornata con successo!';
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Password aggiornata con successo\n", FILE_APPEND);
        } else {
            $response['message'] = 'Errore durante l\'aggiornamento: ' . $conn->error;
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Errore SQL: " . $conn->error . "\n", FILE_APPEND);
        }
    } else {
        $response['message'] = 'La password attuale non è corretta';
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Password attuale non corretta\n", FILE_APPEND);
    }
} else {
    $response['message'] = 'Utente non trovato';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Utente non trovato\n", FILE_APPEND);
}

// Ritorna la risposta JSON
header('Content-Type: application/json');
echo json_encode($response);
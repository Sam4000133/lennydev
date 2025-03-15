<?php
session_start();
require_once 'db_connection.php';

// Abilita visualizzazione errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    die("Utente non autenticato");
}

// Ottieni la password corrente
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

echo "<h1>Test Cambio Password</h1>";
echo "<p>User ID: " . $user_id . "</p>";
echo "<p>Password hash nel DB: " . substr($user['password'], 0, 15) . "...</p>";

// Se il form è inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($current_password) || empty($new_password)) {
        echo "<div style='color:red'>Inserisci entrambe le password</div>";
    } else {
        // Verifica password attuale
        $password_check = password_verify($current_password, $user['password']);
        echo "<p>Verifica password: " . ($password_check ? "OK" : "FALLITA") . "</p>";
        
        if ($password_check) {
            // Crea nuovo hash
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            echo "<p>Nuovo hash: " . substr($new_hash, 0, 15) . "...</p>";
            
            // Aggiorna password
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $new_hash, $user_id);
            
            if ($update->execute()) {
                echo "<div style='color:green'>Password aggiornata con successo!</div>";
            } else {
                echo "<div style='color:red'>Errore: " . $conn->error . "</div>";
            }
        } else {
            echo "<div style='color:red'>Password attuale non corretta</div>";
        }
    }
}
?>

<form method="post" action="">
    <div>
        <label>Password attuale:</label>
        <input type="password" name="current_password">
    </div>
    <div>
        <label>Nuova password:</label>
        <input type="password" name="new_password">
    </div>
    <button type="submit">Cambia Password</button>
</form>
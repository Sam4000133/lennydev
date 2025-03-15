<?php
// Script di reset della password per l'admin (solo ambiente di sviluppo)
// RIMUOVERE QUESTO FILE IN PRODUZIONE!

// Connessione al database
$db_host = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "lennytest";

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die("Errore di connessione al database: " . $conn->connect_error);
}

// Imposta il set di caratteri
$conn->set_charset("utf8mb4");

// Crea una nuova password nota
$user_id = 1; // L'ID dell'admin
$new_password = "Admin123!"; // Nuova password che rispetta i requisiti
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Aggiorna la password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed_password, $user_id);

if ($stmt->execute()) {
    echo "<h1>Reset password completato</h1>";
    echo "<p>Utente ID: $user_id</p>";
    echo "<p>Nuova password: $new_password</p>";
    echo "<p>Hash generato: " . substr($hashed_password, 0, 15) . "...</p>";
    
    // Mostra anche il set di caratteri e la collation
    $result = $conn->query("SELECT @@character_set_database, @@collation_database");
    $charset_info = $result->fetch_assoc();
    echo "<p>Database charset: " . $charset_info['@@character_set_database'] . "</p>";
    echo "<p>Database collation: " . $charset_info['@@collation_database'] . "</p>";
    
    // Mostra la versione di PHP e MySQL
    echo "<p>PHP version: " . phpversion() . "</p>";
    echo "<p>MySQL version: " . $conn->server_info . "</p>";
} else {
    echo "Errore durante il reset della password: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
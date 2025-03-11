<?php
// db_test.php - Un file semplice per verificare la connessione al database e visualizzare i dati
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Includi il file di connessione
require_once 'db_connection.php';

echo "<h1>Test Connessione Database</h1>";

if (!$conn) {
    echo "<p style='color:red;'>ERRORE: Connessione al database fallita.</p>";
    exit;
}

echo "<p style='color:green;'>Connessione al database stabilita con successo!</p>";

// Verifica tabelle esistenti
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

echo "<h2>Tabelle esistenti:</h2>";
echo "<ul>";
foreach ($tables as $table) {
    echo "<li>$table</li>";
}
echo "</ul>";

// Verifica dati nelle tabelle principali
echo "<h2>Ruoli nel sistema:</h2>";
$roles = $conn->query("SELECT id, name, description FROM roles");
if ($roles->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Descrizione</th><th>Utenti</th></tr>";
    while ($role = $roles->fetch_assoc()) {
        $roleId = $role['id'];
        $userCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = $roleId")->fetch_assoc()['count'];
        echo "<tr>";
        echo "<td>{$role['id']}</td>";
        echo "<td>{$role['name']}</td>";
        echo "<td>{$role['description']}</td>";
        echo "<td>$userCount</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Nessun ruolo trovato.</p>";
}

echo "<h2>Utenti nel sistema:</h2>";
$users = $conn->query("SELECT u.id, u.username, u.email, u.full_name, u.status, r.name as role_name 
                       FROM users u 
                       LEFT JOIN roles r ON u.role_id = r.id 
                       LIMIT 10");
if ($users->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Nome completo</th><th>Ruolo</th><th>Stato</th></tr>";
    while ($user = $users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['role_name']}</td>";
        echo "<td>{$user['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    $totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    if ($totalUsers > 10) {
        echo "<p>Mostrati 10 utenti su $totalUsers totali.</p>";
    }
} else {
    echo "<p>Nessun utente trovato.</p>";
}

echo "<h2>Permessi nel sistema:</h2>";
$permissions = $conn->query("SELECT id, name, category FROM permissions ORDER BY category, name LIMIT 20");
if ($permissions->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Categoria</th></tr>";
    while ($perm = $permissions->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$perm['id']}</td>";
        echo "<td>{$perm['name']}</td>";
        echo "<td>{$perm['category']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    $totalPerms = $conn->query("SELECT COUNT(*) as count FROM permissions")->fetch_assoc()['count'];
    if ($totalPerms > 20) {
        echo "<p>Mostrati 20 permessi su $totalPerms totali.</p>";
    }
} else {
    echo "<p>Nessun permesso trovato.</p>";
}

// Chiudi la connessione
$conn->close();
?>
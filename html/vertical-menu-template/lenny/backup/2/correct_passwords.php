<?php
// Abilita visualizzazione errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connessione al database
$db_host = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "lennytest";

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Imposta esplicitamente il charset e la collation
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci");

// Inizio output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Correzione Password MySQL 8.0</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { display: inline-block; padding: 8px 16px; background: #4CAF50; color: white; 
               text-decoration: none; border-radius: 4px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Correzione Password per MySQL 8.0 (utf8mb4_0900_ai_ci)</h1>";

// Verifica configurazione
echo "<h2>Verifica configurazione database</h2>";
echo "<pre>";

$result = $conn->query("SELECT VERSION() as version");
$version = $result->fetch_assoc()['version'];
echo "Versione MySQL: $version\n";

$result = $conn->query("SELECT @@character_set_database as charset, @@collation_database as collation");
$dbSettings = $result->fetch_assoc();
echo "Charset database: {$dbSettings['charset']}\n";
echo "Collation database: {$dbSettings['collation']}\n";

$result = $conn->query("SHOW VARIABLES LIKE 'character_set_connection'");
$connectionCharset = $result->fetch_assoc()['Value'];
echo "Charset connessione: $connectionCharset\n";

$result = $conn->query("SHOW VARIABLES LIKE 'collation_connection'");
$connectionCollation = $result->fetch_assoc()['Value'];
echo "Collation connessione: $connectionCollation\n";
echo "</pre>";

// Funzione per elaborare gli utenti
function process_users($conn, $mode = 'diagnose') {
    echo "<h2>" . ($mode == 'diagnose' ? "Diagnosi" : "Correzione") . " delle password</h2>";
    
    // Password di test standard e il suo hash bcrypt
    $test_password = 'password123';
    $standard_hash = '$2y$10$xtskTnXUJAy/iEiDkvj2c.D.1CMKvTsWkqUmNZeZQTBw4hRF7ZBme';
    
    // Ottieni tutti gli utenti
    $result = $conn->query("SELECT id, username, email, password, status FROM users ORDER BY id");
    
    echo "<table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Status</th>
            <th>Password Hash</th>
            <th>Test Verifica</th>
            " . ($mode == 'fix' ? "<th>Azione</th>" : "") . "
        </tr>";
    
    $fixed_count = 0;
    $problematic_count = 0;
    
    while ($user = $result->fetch_assoc()) {
        $id = $user['id'];
        $username = htmlspecialchars($user['username']);
        $email = htmlspecialchars($user['email']);
        $status = $user['status'];
        $hash = $user['password'];
        
        // Verifica se l'hash ha un formato valido
        $valid_format = (strpos($hash, '$2y$') === 0&&strlen($hash) >= 60);
        
        // Verifica se l'hash standard funziona
        $verify_result = password_verify($test_password, $hash);
        
        // Determina lo stato dell'hash
        if ($verify_result) {
            $verify_status = "<span class='success'>OK</span>";
        } else if (!$valid_format) {
            $verify_status = "<span class='error'>Formato non valido</span>";
            $problematic_count++;
        } else {
            $verify_status = "<span class='warning'>Non verificabile</span>";
            $problematic_count++;
        }
        
        // Output utente
        echo "<tr>
            <td>$id</td>
            <td>$username</td>
            <td>$email</td>
            <td>$status</td>
            <td><code>" . substr(htmlspecialchars($hash), 0, 20) . "...</code></td>
            <td>$verify_status</td>";
        
        // Azioni di correzione
        if ($mode == 'fix') {
            echo "<td>";
            
            if (!$verify_result) {
                // Aggiorna con l'hash standard
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $standard_hash, $id);
                
                if ($stmt->execute()) {
                    echo "<span class='success'>Corretto con password123</span>";
                    $fixed_count++;
                } else {
                    echo "<span class='error'>Errore: {$stmt->error}</span>";
                }
                
                $stmt->close();
            } else {
                echo "<span class='success'>Già OK</span>";
            }
            
            echo "</td>";
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
    
    if ($mode == 'fix') {
        echo "<p><strong>Utenti corretti:</strong> $fixed_count</p>";
    } else {
        echo "<p><strong>Utenti con possibili problemi:</strong> $problematic_count</p>";
    }
    
    return $problematic_count;
}

// Esegui la diagnosi
$problematicUsers = process_users($conn);

// Se ci sono utenti problematici, offrire l'opzione di correzione
if ($problematicUsers > 0) {
    if (isset($_GET['fix'])) {
        process_users($conn, 'fix');
        echo "<p class='success'>Correzione completata! Tutti gli utenti problematici ora hanno la password \"password123\".</p>";
        echo "<p>Adesso dovresti poter accedere al sistema con queste credenziali.</p>";
        echo "<a href='login.php' class='btn'>Vai alla pagina di login</a>";
    } else {
        echo "<div class='warning' style='padding: 15px; margin-top: 20px; border-radius: 5px; background-color: #fff3cd;'>
            <h3>Attenzione: Trovati $problematicUsers utenti con problemi di verifica password</h3>
            <p>Vuoi correggere automaticamente tutte le password problematiche?</p>
            <p>Tutti gli utenti con password non verificabili avranno la password impostata a: <strong>password123</strong></p>
            <a href='?fix=1' class='btn' style='background-color: #e3a919; margin-right: 10px;'>Sì, correggi le password</a>
            <a href='login.php' class='btn' style='background-color: #6c757d;'>No, torna al login</a>
        </div>";
    }
} else {
    echo "<p class='success'>Nessun problema rilevato con le password! Se ancora non riesci ad accedere, il problema potrebbe essere nel codice di login.</p>";
    
    echo "<h2>Verifica il file login.php</h2>";
    echo "<p>Assicurati che il codice di login.php includa le seguenti linee dopo la connessione:</p>";
    echo "<pre>
\$conn->set_charset(\"utf8mb4\");
\$conn->query(\"SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci\");
</pre>";
    
    echo "<p>Inoltre controlla che la verifica della password utilizzi <code>password_verify()</code>:</p>";
    echo "<pre>
if (password_verify(\$password, \$user['password'])) {
    // Login corretto
} else {
    // Password errata
}
</pre>";
}

// Sezione istruzioni
echo "<h2>Istruzioni per la risoluzione permanente</h2>
<ol>
    <li>Assicurati che tutti i tuoi file PHP che si connettono al database utilizzino la collation corretta:
        <pre>
\$conn->set_charset(\"utf8mb4\");
\$conn->query(\"SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci\");
        </pre>
    </li>
    <li>Se utilizzi PDO invece di MySQLi, usa:
        <pre>
\$pdo->exec(\"SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci\");
        </pre>
    </li>
    <li>Quando generi nuovi hash di password, usa sempre la funzione standard PHP:
        <pre>
\$hashed_password = password_hash(\$password, PASSWORD_DEFAULT);
        </pre>
    </li>
    <li>Quando verifichi le password, usa la funzione corrispondente:
        <pre>
if (password_verify(\$password, \$hashed_password)) {
    // Password corretta
}
        </pre>
    </li>
</ol>";

echo "</div></body></html>";
$conn->close();
?>
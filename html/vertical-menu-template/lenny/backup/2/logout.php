<?php
// Inizia la sessione
session_start();

// Cancella tutte le variabili di sessione
$_SESSION = array();

// Se e necessario distruggere la sessione completamente, elimina anche il cookie di sessione
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Infine, distruggi la sessione
session_destroy();

// Reindirizza alla pagina di login
header("Location: login.php");
exit;
?>
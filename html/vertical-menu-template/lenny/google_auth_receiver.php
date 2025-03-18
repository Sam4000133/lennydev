<?php
// File: google_auth_receiver.php
// Questo file riceve il codice da Google e lo passa al vero handler

// Abilita la visualizzazione errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inizia sessione
session_start();

// Log per debug
file_put_contents(__DIR__ . '/google_debug.log', 
    date('[Y-m-d H:i:s] ') . 
    "Ricevuta richiesta Google\n" . 
    "GET: " . print_r($_GET, true) . "\n" . 
    "---------------\n", 
    FILE_APPEND);

// Verifica se è stata ricevuta una risposta da Google (il codice)
if (isset($_GET['code'])) {
    // Salva il codice nella sessione
    $_SESSION['google_auth_code'] = $_GET['code'];
    
    // Reindirizza al vero handler
    header('Location: auth/google.php?from_receiver=1');
    exit;
} 
// Se non c'è un codice, questo è un errore o una cancellazione
elseif (isset($_GET['error'])) {
    $_SESSION['auth_error'] = "Autenticazione Google interrotta: " . $_GET['error'];
    header('Location: login.php');
    exit;
}
// Se non c'è né codice né errore, redireziona al login
else {
    $_SESSION['auth_error'] = "Errore durante l'autenticazione Google";
    header('Location: login.php');
    exit;
}
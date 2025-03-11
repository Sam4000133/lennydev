<?php
/**
 * Script per aggiungere controlli di autenticazione a tutte le pagine PHP
 */

// Definisci una mappatura tra nomi di file e i permessi richiesti
$pagePermissions = [
    'index.php' => 'Panoramica',
    'ordini-in-corso.php' => 'Ordini in corso',
    'cronologia-ordini.php' => 'Cronologia ordini',
    'resi-rimborsi.php' => 'Resi e rimborsi',
    'informazioni-base.php' => 'Informazioni base',
    'indirizzo-consegna.php' => 'Indirizzo e consegna',
    'orari-apertura.php' => 'Orari di apertura',
    'menu.php' => 'Menu',
    'dati-operativi.php' => 'Dati operativi',
    'pagamenti.php' => 'Pagamenti',
    'commissioni.php' => 'Commissioni',
    'notifiche.php' => 'Notifiche',
    'integrazione-ia.php' => 'Integrazione IA',
    'documenti.php' => 'Documenti',
    'promozioni.php' => 'Promozioni',
    'registrazione.php' => 'Registrazione driver',
    'assegnazione-ordini.php' => 'Assegnazione ordini',
    'tracking-gps.php' => 'Tracking GPS',
    'pagamenti-driver.php' => 'Pagamenti driver',
    'report-vendite.php' => 'Report vendite',
    'performance.php' => 'Performance',
    'statistiche-prodotti.php' => 'Statistiche prodotti',
    'clienti.php' => 'Clienti',
    'recensioni.php' => 'Recensioni',
    'reclami.php' => 'Reclami',
    'elenco-ristoranti.php' => 'Elenco ristoranti',
    'filtri-ricerca.php' => 'Filtri e ricerca',
    'categorie.php' => 'Categorie',
    'email-automatiche.php' => 'Email automatiche',
    'sms.php' => 'SMS',
    'chat-supporto.php' => 'Chat supporto',
    'piani-membership.php' => 'Piani membership',
    'fatturazione.php' => 'Fatturazione',
    'impostazioni.php' => 'Impostazioni',
    'ruoli-permessi.php' => 'Ruoli&permessi',
    'integrazioni.php' => 'Integrazioni',
    'sicurezza.php' => 'Sicurezza',
    'privacy.php' => 'Privacy',
    'backup.php' => 'Backup'
];

// File da escludere (non modificare)
$excludeFiles = [
    'login.php', 'logout.php', 'access-denied.php', 'check_auth.php', 
    'db_connection.php', 'menu_helper.php', 'navbar.php', 'sidebar.php',
    'update_auth_checks.php'
];

// Codice di controllo dell'autenticazione da inserire
function generateAuthCheckCode($permissionName) {
    return <<<PHP
<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Verifica l'accesso specifico a questa pagina
if (!userHasAccess('$permissionName')) {
    // Reindirizza alla pagina di accesso negato
    header("Location: access-denied.php");
    exit;
}

// Resto del codice della pagina...
PHP;
}

// Ottieni tutti i file PHP nella directory corrente
$phpFiles = glob('*.php');

// Contatori
$updatedCount = 0;
$skippedCount = 0;
$errorCount = 0;

// Elabora ciascun file
foreach ($phpFiles as $filename) {
    // Salta i file da escludere
    if (in_array($filename, $excludeFiles)) {
        echo "Saltato (file escluso): $filename\n";
        $skippedCount++;
        continue;
    }
    
    // Ottieni il permesso richiesto per questa pagina
    $requiredPermission = isset($pagePermissions[$filename]) ? $pagePermissions[$filename] : null;
    
    if (!$requiredPermission) {
        echo "Saltato (permesso non definito): $filename\n";
        $skippedCount++;
        continue;
    }
    
    // Leggi il contenuto del file
    $content = file_get_contents($filename);
    
    // Verifica se il controllo di autenticazione è già presente
    if (strpos($content, "require_once 'check_auth.php'") !== false) {
        echo "Saltato (controllo già presente): $filename\n";
        $skippedCount++;
        continue;
    }
    
    // Genera il nuovo codice di controllo dell'autenticazione
    $authCheckCode = generateAuthCheckCode($requiredPermission);
    
    // Trova l'apertura del tag PHP
    $phpOpenTagPos = strpos($content, '<?php');
    
    if ($phpOpenTagPos !== false) {
        // Trova il primo carattere di nuova riga dopo il tag di apertura PHP
        $nlPos = strpos($content, "\n", $phpOpenTagPos);
        
        if ($nlPos !== false) {
            // Inserisci il controllo dell'autenticazione dopo il tag di apertura PHP e la prima riga
            $newContent = substr($content, 0, $nlPos + 1) . "\n" . $authCheckCode . "\n" . substr($content, $nlPos + 1);
            
            // Salva il file modificato
            if (file_put_contents($filename, $newContent)) {
                echo "Aggiornato: $filename\n";
                $updatedCount++;
            } else {
                echo "ERRORE durante la scrittura: $filename\n";
                $errorCount++;
            }
        } else {
            echo "ERRORE: Impossibile trovare una nuova riga dopo il tag PHP in: $filename\n";
            $errorCount++;
        }
    } else {
        // Il file non ha un tag di apertura PHP, aggiungi il tag e il controllo
        $newContent = $authCheckCode . "\n\n" . $content;
        
        // Salva il file modificato
        if (file_put_contents($filename, $newContent)) {
            echo "Aggiornato (aggiunto tag PHP): $filename\n";
            $updatedCount++;
        } else {
            echo "ERRORE durante la scrittura: $filename\n";
            $errorCount++;
        }
    }
}

// Riepilogo
echo "\nAggiornamento completato!\n";
echo "File aggiornati: $updatedCount\n";
echo "File saltati: $skippedCount\n";
echo "Errori: $errorCount\n";
?>
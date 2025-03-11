<?php
// Script per correggere le verifiche dei permessi nei file PHP

// Mappatura dei nomi dei file alle corrispondenti permissioni nella tabella 'permissions'
$fileToPermissionMap = [
    'panoramica.php' => 'Panoramica',
    'gestione_ordini.php' => 'Gestione Ordini',
    'ordini_in_corso.php' => 'Ordini in corso',
    'cronologia_ordini.php' => 'Cronologia ordini',
    'resi_rimborsi.php' => 'Resi e rimborsi',
    'gestione_ristorante.php' => 'Gestione Ristorante',
    'informazioni_base.php' => 'Informazioni base',
    'indirizzo_consegna.php' => 'Indirizzo e consegna',
    'orari_apertura.php' => 'Orari di apertura',
    'menu.php' => 'Menu',
    'dati_operativi.php' => 'Dati operativi',
    'pagamenti.php' => 'Pagamenti',
    'commissioni.php' => 'Commissioni',
    'notifiche.php' => 'Notifiche',
    'integrazione_ia.php' => 'Integrazione IA',
    'documenti.php' => 'Documenti',
    'promozioni.php' => 'Promozioni',
    'gestione_driver.php' => 'Gestione Driver',
    'registrazione_driver.php' => 'Registrazione driver',
    'assegnazione_ordini.php' => 'Assegnazione ordini',
    'tracking_gps.php' => 'Tracking GPS',
    'pagamenti_driver.php' => 'Pagamenti driver',
    'analytics.php' => 'Analytics',
    'report_vendite.php' => 'Report vendite',
    'performance.php' => 'Performance',
    'statistiche_prodotti.php' => 'Statistiche prodotti',
    'crm.php' => 'CRM',
    'clienti.php' => 'Clienti',
    'recensioni.php' => 'Recensioni',
    'reclami.php' => 'Reclami',
    'marketplace.php' => 'Marketplace',
    'elenco_ristoranti.php' => 'Elenco ristoranti',
    'filtri_ricerca.php' => 'Filtri e ricerca',
    'categorie.php' => 'Categorie',
    'comunicazioni.php' => 'Comunicazioni',
    'email_automatiche.php' => 'Email automatiche',
    'sms.php' => 'SMS',
    'chat_supporto.php' => 'Chat supporto',
    'abbonamenti.php' => 'Abbonamenti',
    'piani_membership.php' => 'Piani membership',
    'fatturazione.php' => 'Fatturazione',
    'sistema.php' => 'Sistema',
    'impostazioni.php' => 'Impostazioni',
    'ruoli_permessi.php' => 'Ruoli&permessi',
    'integrazioni.php' => 'Integrazioni',
    'sicurezza.php' => 'Sicurezza',
    'privacy.php' => 'Privacy',
    'backup.php' => 'Backup'
];

// Caso particolare per il nome del file integrazioni.php che dovrebbe usare 'Integrazioni'
$specificFixMap = [
    'integrazioni.php' => 'Integrazioni'
];

// Directory da scansionare
$directory = './'; // Modifica questo percorso se necessario

// Ottieni tutti i file PHP nella directory
$phpFiles = glob($directory . '*.php');

$changedFiles = 0;
$errorFiles = [];
$logMessages = [];

foreach ($phpFiles as $file) {
    $basename = basename($file);
    
    // Salta file di sistema o librerie
    if (in_array($basename, ['check_auth.php', 'db_connection.php', 'sidebar.php', 'navbar.php', 'config.php'])) {
        $logMessages[] = "Skipping system file: $basename";
        continue;
    }
    
    // Leggi il contenuto del file
    $content = file_get_contents($file);
    if ($content === false) {
        $errorFiles[] = $file;
        $logMessages[] = "Error: Unable to read file $file";
        continue;
    }
    
    // Determina il permesso corretto da usare
    $permissionToUse = null;
    
    // Controllo specifico per integrazioni.php
    if (isset($specificFixMap[$basename])) {
        $permissionToUse = $specificFixMap[$basename];
    } 
    // Altrimenti cerca nella mappa generale
    elseif (isset($fileToPermissionMap[$basename])) {
        $permissionToUse = $fileToPermissionMap[$basename];
    }
    
    if ($permissionToUse !== null) {
        // Pattern per trovare la verifica di accesso con qualsiasi nome di permesso
        $pattern = "/if\s*\(\s*!\s*userHasAccess\s*\(\s*['\"]([^'\"]+)['\"]\s*\)\s*\)\s*\{/";
        
        // Trova il permesso attualmente usato
        if (preg_match($pattern, $content, $matches)) {
            $currentPermission = $matches[1];
            
            // Se il permesso è già corretto, salta
            if ($currentPermission === $permissionToUse) {
                $logMessages[] = "File $basename already uses correct permission: $permissionToUse";
                continue;
            }
            
            // Sostituisci con il permesso corretto
            $replacement = "if (!userHasAccess('$permissionToUse')) {";
            $newContent = preg_replace($pattern, $replacement, $content);
            
            // Scrivi il file aggiornato
            if (file_put_contents($file, $newContent) !== false) {
                $changedFiles++;
                $logMessages[] = "Updated $basename: Changed permission from '$currentPermission' to '$permissionToUse'";
            } else {
                $errorFiles[] = $file;
                $logMessages[] = "Error: Unable to write to file $file";
            }
        } else {
            $logMessages[] = "No permission check found in $basename";
        }
    } else {
        $logMessages[] = "Warning: No mapping found for $basename";
    }
}

// Output summary
echo "=== Permission Check Correction Summary ===\n";
echo "Files processed: " . count($phpFiles) . "\n";
echo "Files changed: $changedFiles\n";
echo "Errors encountered: " . count($errorFiles) . "\n";

if (!empty($errorFiles)) {
    echo "\nFiles with errors:\n";
    foreach ($errorFiles as $errFile) {
        echo "- " . basename($errFile) . "\n";
    }
}

echo "\n=== Detailed Log ===\n";
foreach ($logMessages as $msg) {
    echo "$msg\n";
}

// Salva log in un file
file_put_contents('permission_fix_log.txt', implode("\n", $logMessages));
echo "\nLog saved to permission_fix_log.txt\n";
?>
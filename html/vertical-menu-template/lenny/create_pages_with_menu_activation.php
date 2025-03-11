<?php
// Script per aggiungere il file JavaScript per il comportamento a fisarmonica a tutte le pagine PHP

// Ottieni una lista di tutti i file PHP nella directory corrente
$phpFiles = glob('*.php');

// Escludi alcuni file specifici
$excludeFiles = ['navbar.php', 'sidebar.php', 'db_connection.php', 'menu_helper.php'];
$phpFiles = array_diff($phpFiles, $excludeFiles);

// Il tag script da aggiungere
$scriptTag = '<script src="../../../assets/js/menu_accordion.js"></script>';

// Contatori
$updatedCount = 0;
$skippedCount = 0;

// Itera su tutti i file PHP
foreach ($phpFiles as $file) {
    // Leggi il contenuto del file
    $content = file_get_contents($file);
    
    // Controlla se il riferimento allo script è già presente
    if (strpos($content, 'menu_accordion.js') !== false) {
        echo "Riferimento già presente in: $file\n";
        $skippedCount++;
        continue;
    }
    
    // Trova la posizione dove inserire lo script (prima di </body>)
    $pos = strrpos($content, '</body>');
    
    if ($pos !== false) {
        // Rimuovi eventuali script di attivazione del menu esistenti
        $pattern = '/<script>\s*document\.addEventListener\(\'DOMContentLoaded\',\s*function\(\)\s*\{[\s\S]*?}\);\s*<\/script>/';
        $content = preg_replace($pattern, '', $content);
        
        // Inserisci il nuovo tag script prima di </body>
        $updatedContent = substr($content, 0, $pos) . "\n    " . $scriptTag . "\n" . substr($content, $pos);
        
        // Scrivi il contenuto aggiornato nel file
        if (file_put_contents($file, $updatedContent)) {
            echo "Riferimento aggiunto con successo a: $file\n";
            $updatedCount++;
        } else {
            echo "Errore nell'aggiornamento del file: $file\n";
        }
    } else {
        echo "Tag </body> non trovato in: $file\n";
        $skippedCount++;
    }
}

// Output finale
echo "\nOperazione completata!\n";
echo "File aggiornati: $updatedCount\n";
echo "File saltati: $skippedCount\n";
?>
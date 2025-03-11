<?php
// Imposta la visualizzazione degli errori per debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Directory da scansionare - imposta il percorso correttamente
$baseDir = __DIR__;

// Contatori per i report
$totalFiles = 0;
$fixedFiles = 0;
$skippedFiles = 0;

// Funzione per correggere tag PHP duplicati
function fixPhpDuplicateTags($filePath) {
    global $fixedFiles, $skippedFiles;
    
    // Leggi il contenuto del file
    $content = file_get_contents($filePath);
    if ($content === false) {
        echo "<p class='error'>Impossibile leggere il file: $filePath</p>";
        $skippedFiles++;
        return false;
    }
    
    // Verifica se c'è un tag PHP duplicato all'inizio
    $pattern = '/^<\?php\s*(\r?\n\s*)+<\?php/';
    if (preg_match($pattern, $content)) {
        // Crea backup del file originale
        file_put_contents($filePath . '.bak', $content);
        
        // Rimuovi il tag duplicato
        $fixedContent = preg_replace($pattern, '<?php', $content);
        
        // Salva il file corretto
        if (file_put_contents($filePath, $fixedContent)) {
            echo "<p class='success'>✅ Corretto tag PHP duplicato in: $filePath</p>";
            $fixedFiles++;
            return true;
        } else {
            echo "<p class='error'>❌ Errore durante il salvataggio del file: $filePath</p>";
            $skippedFiles++;
            return false;
        }
    } else {
        // Il file non ha tag PHP duplicati, verifica per BOM e spazi iniziali
        
        // Rimuovi BOM se presente
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
            file_put_contents($filePath . '.bak', $content);
            
            // Ora vedi se c'è del testo prima del tag PHP
            $phpPos = strpos($content, '<?php');
            if ($phpPos > 0) {
                // Rimuovi tutto fino al tag PHP
                $content = substr($content, $phpPos);
            }
            
            if (file_put_contents($filePath, $content)) {
                echo "<p class='success'>✅ Rimosso BOM o testo iniziale in: $filePath</p>";
                $fixedFiles++;
                return true;
            }
        } else {
            // Verifica se c'è del testo prima del primo tag PHP
            $phpPos = strpos($content, '<?php');
            if ($phpPos !== false&&$phpPos > 0) {
                file_put_contents($filePath . '.bak', $content);
                
                // Rimuovi tutto fino al tag PHP
                $content = substr($content, $phpPos);
                
                if (file_put_contents($filePath, $content)) {
                    echo "<p class='success'>✅ Rimosso testo prima del tag PHP in: $filePath</p>";
                    $fixedFiles++;
                    return true;
                }
            } else {
                // Il file sembra ok
                return false;
            }
        }
    }
    
    $skippedFiles++;
    return false;
}

// Funzione per scansionare ricorsivamente le directory
function scanDirectoryForPhpFiles($dir) {
    global $totalFiles;
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'fix_duplicates.php' || substr($file, -4) === '.bak') {
            continue;
        }
        
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            // Scansiona ricorsivamente le sottodirectory
            scanDirectoryForPhpFiles($path);
        } else if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            // Abbiamo trovato un file PHP
            $totalFiles++;
            fixPhpDuplicateTags($path);
        }
    }
}

// Stile per l'output
echo '<!DOCTYPE html>
<html>
<head>
    <title>PHP Duplicate Tag Fixer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .results {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            max-height: 500px;
            overflow-y: auto;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .summary {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #0069d9;
        }
    </style>
</head>
<body>
    <h1>PHP Duplicate Tag Fixer</h1>';

// Vedi se l'utente ha avviato la scansione
if (isset($_POST['scan'])) {
    echo '<div class="results">';
    echo '<h2>Scansione in corso...</h2>';
    
    // Esegui la scansione
    scanDirectoryForPhpFiles($baseDir);
    
    echo '</div>';
    
    // Mostra sommario
    echo '<div class="summary">';
    echo '<h2>Risultati:</h2>';
    echo '<p>File PHP totali trovati: ' . $totalFiles . '</p>';
    echo '<p>File corretti: ' . $fixedFiles . '</p>';
    echo '<p>File saltati o non modificati: ' . ($totalFiles - $fixedFiles) . '</p>';
    echo '</div>';
    
    echo '<p><a href="">Torna indietro</a></p>';
} else {
    // Mostra il form per avviare la scansione
    echo '<p>Questo script cerca e corregge automaticamente i seguenti problemi nei file PHP:</p>
    <ul>
        <li>Tag PHP duplicati all\'inizio dei file (es. <code>&lt;?php<br>&lt;?php</code>)</li>
        <li>BOM (Byte Order Mark) all\'inizio dei file</li>
        <li>Testo o caratteri prima del tag PHP di apertura</li>
    </ul>
    
    <p><strong>Directory da scansionare:</strong> ' . htmlspecialchars($baseDir) . '</p>
    
    <form method="post">
        <button type="submit" name="scan">Avvia scansione</button>
    </form>';
}

echo '</body>
</html>';
?>
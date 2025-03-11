<?php
// Script per generare automaticamente le pagine del pannello di amministrazione
// basato sulla struttura della sidebar

// Estrai tutte le pagine PHP menzionate nella sidebar
function extractAllPages($sidebarContent) {
    $pages = [];
    
    // Trova tutte le pagine PHP nella sidebar
    preg_match_all("/'([a-z0-9-]+\.php)'\s*=>\s*\[\s*'title'\s*=>\s*'([^']+)',\s*'icon'\s*=>\s*'([^']+)',\s*'permission'\s*=>\s*'([^']*)'/", 
                   $sidebarContent, 
                   $matches, 
                   PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $pages[$match[1]] = [
            'title' => $match[2],
            'icon' => $match[3],
            'permission' => $match[4]
        ];
    }
    
    return $pages;
}

// Genera il template della pagina
function generatePageTemplate($pageInfo, $templateContent) {
    $title = $pageInfo['title'];
    $permission = $pageInfo['permission'];
    $icon = $pageInfo['icon'];
    
    // Crea un ID di permesso valido (senza spazi)
    $permissionId = str_replace(' ', '', $permission);
    
    // Sostituisci il titolo nella pagina
    $templateContent = str_replace('Ordini in Corso', $title, $templateContent);
    $templateContent = str_replace('OrdiniInCorso', $permissionId, $templateContent);
    
    // Aggiorna la meta descrizione
    $templateContent = preg_replace('/<meta name="description" content=".*?" \/>/', '<meta name="description" content="Gestione ' . strtolower($title) . '" />', $templateContent);
    
    return $templateContent;
}

// Backup esistente e creazione nuova pagina
function createPageFile($filename, $content) {
    // Se il file esiste, crea un backup
    if (file_exists($filename)) {
        $backupFilename = $filename . '.bak';
        if (!copy($filename, $backupFilename)) {
            echo "Errore nel creare il backup di $filename<br>";
            return false;
        }
        echo "Backup creato: $backupFilename<br>";
    }
    
    // Crea la nuova pagina
    if (file_put_contents($filename, $content) === false) {
        echo "Errore nella creazione del file $filename<br>";
        return false;
    }
    
    echo "File creato con successo: $filename<br>";
    return true;
}

// Leggi il contenuto della sidebar
$sidebarContent = file_get_contents('sidebar.php');
if ($sidebarContent === false) {
    die("Impossibile leggere il file sidebar.php");
}

// Leggi il template di esempio
$templateContent = file_get_contents('ordini-in-corso.php');
if ($templateContent === false) {
    die("Impossibile leggere il file template");
}

// Estrai la struttura del menu
$menuPages = extractAllPages($sidebarContent);

// Mostra quante pagine sono state trovate
echo "Trovate " . count($menuPages) . " pagine da generare.<br><br>";

// Genera tutte le pagine
foreach ($menuPages as $filename => $pageInfo) {
    // Genera il contenuto della pagina
    $pageContent = generatePageTemplate($pageInfo, $templateContent);
    
    // Crea il file
    createPageFile($filename, $pageContent);
}

echo "<br>Generazione pagine completata!";
?>
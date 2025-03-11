<?php
// Script per generare automaticamente le pagine del pannello di amministrazione
// basato sulla struttura della sidebar

// Estrai la struttura del menu dalla sidebar
function extractMenuStructure($sidebarContent) {
    $menuStructure = [];
    
    // Pattern per estrarre la struttura del menu
    preg_match('/\$menu_structure\s*=\s*\[(.*?)\];/s', $sidebarContent, $matches);
    
    if (empty($matches[1])) {
        return $menuStructure;
    }
    
    $menuDefinition = $matches[1];
    
    // Estrai tutti i sottomenu
    preg_match_all("/'submenu'\s*=>\s*\[(.*?)\]/s", $menuDefinition, $submenuMatches);
    
    if (empty($submenuMatches[1])) {
        return $menuStructure;
    }
    
    foreach ($submenuMatches[1] as $submenuContent) {
        // Estrai ogni pagina dal sottomenu
        preg_match_all("/'(.*?\.php)'\s*=>\s*\['title'\s*=>\s*'(.*?)'/", $submenuContent, $pageMatches);
        
        if (!empty($pageMatches[1])) {
            for ($i = 0; $i < count($pageMatches[1]); $i++) {
                $pageName = $pageMatches[1][$i];
                $pageTitle = $pageMatches[2][$i];
                
                // Estrai l'icona se presente
                preg_match("/'$pageName'\s*=>\s*\['title'\s*=>\s*'.*?',\s*'icon'\s*=>\s*'(.*?)'/", $submenuContent, $iconMatch);
                $icon = !empty($iconMatch[1]) ? $iconMatch[1] : '';
                
                // Estrai il permesso
                preg_match("/'$pageName'\s*=>\s*\['title'\s*=>\s*'.*?',\s*'icon'\s*=>\s*'.*?',\s*'permission'\s*=>\s*'(.*?)'/", $submenuContent, $permMatch);
                $permission = !empty($permMatch[1]) ? $permMatch[1] : '';
                
                $menuStructure[$pageName] = [
                    'title' => $pageTitle,
                    'icon' => $icon,
                    'permission' => $permission
                ];
            }
        }
    }
    
    return $menuStructure;
}

// Genera il template della pagina
function generatePageTemplate($pageInfo, $templateContent) {
    $title = $pageInfo['title'];
    $permission = $pageInfo['permission'];
    $icon = $pageInfo['icon'];
    
    // Sostituisci il titolo nella pagina
    $templateContent = str_replace('Ordini in Corso', $title, $templateContent);
    $templateContent = str_replace('OrdiniInCorso', str_replace(' ', '', $permission), $templateContent);
    
    // Aggiorna la meta descrizione
    $templateContent = preg_replace('/<meta name="description" content=".*?" \/>/', '<meta name="description" content="' . $title . '" />', $templateContent);
    
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
$menuStructure = extractMenuStructure($sidebarContent);

// Genera tutte le pagine
foreach ($menuStructure as $filename => $pageInfo) {
    // Genera il contenuto della pagina
    $pageContent = generatePageTemplate($pageInfo, $templateContent);
    
    // Crea il file
    createPageFile($filename, $pageContent);
}

echo "<br>Generazione pagine completata!";
?>
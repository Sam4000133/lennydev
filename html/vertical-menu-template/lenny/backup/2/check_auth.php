<?php
/**
 * check_auth.php - Sistema di gestione autenticazione e permessi
 * 
 * Questo file gestisce:
 * 1. Verifica della sessione utente
 * 2. Controllo dei permessi utente
 * 3. Funzioni helper per l'interfaccia
 * 4. Esposizione dei permessi a JavaScript
 */

// Assicuriamoci che la sessione sia avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica che l'utente sia loggato
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Includi la connessione al database se non è già disponibile
if (!isset($conn) || $conn === null) {
    require_once 'db_connection.php';
}

// Debug informativo per ambiente di sviluppo
if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    error_log("=== CHECK_AUTH DEBUG ===");
    error_log("User ID: " . $_SESSION['user_id']);
    error_log("Role ID: " . ($_SESSION['role_id'] ?? 'not set'));
    
    // Log dei permessi disponibili
    if (isset($_SESSION['permissions'])&&is_array($_SESSION['permissions'])) {
        $perm_keys = array_keys($_SESSION['permissions']);
        error_log("Available permissions: " . implode(", ", $perm_keys));
    } else {
        error_log("No permissions found in session");
    }
}

/**
 * Normalizza un nome di permesso per confronti consistenti
 * 
 * @param string $name Nome del permesso da normalizzare
 * @return string Nome normalizzato
 */
function normalizePermissionName($name) {
    // Rimuove spazi extra intorno a caratteri speciali come&$normalized = preg_replace('/\s*([&])\s*/', '$1', $name);
    // Converte in minuscolo
    return strtolower($normalized);
}

/**
 * Verifica se l'utente ha accesso a una determinata funzionalità
 * 
 * @param string $featureName Nome della funzionalità/permesso
 * @return bool True se l'utente ha accesso, false altrimenti
 */
function userHasAccess($featureName) {
    // Gestisci caso di feature name vuoto
    if (empty($featureName)) {
        return false;
    }
    
    // Log per debug in ambiente di sviluppo
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        error_log("Checking access for: '$featureName'");
    }
    
    // Se l'utente è amministratore (role_id = 1), ha tutti i permessi
    if (isset($_SESSION['role_id'])&&$_SESSION['role_id'] == 1) {
        if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            error_log("Admin user: access granted");
        }
        return true;
    }
    
    // Ottieni i permessi dalla sessione
    $permissions = isset($_SESSION['permissions']) ? $_SESSION['permissions'] : [];
    
    // Verifica con corrispondenza esatta (case sensitive)
    if (isset($permissions[$featureName])) {
        $hasAccess = (bool)$permissions[$featureName]['can_read'] || 
                     (bool)$permissions[$featureName]['can_write'] || 
                     (bool)$permissions[$featureName]['can_create'];
                     
        if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            error_log("Permission '$featureName' found: " . ($hasAccess ? 'access granted' : 'access denied'));
        }
        
        return $hasAccess;
    }
    
    // Normalizza il nome del permesso per confronti più flessibili
    $normalizedFeatureName = normalizePermissionName($featureName);
    
    foreach ($permissions as $permName => $permDetails) {
        // Verifica con normalizzazione (gestisce spazi e caratteri speciali)
        $normalizedPermName = normalizePermissionName($permName);
        
        if ($normalizedPermName === $normalizedFeatureName) {
            $hasAccess = (bool)$permDetails['can_read'] || 
                         (bool)$permDetails['can_write'] || 
                         (bool)$permDetails['can_create'];
                         
            if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
                error_log("Permission found with normalized check '$permName': " . 
                         ($hasAccess ? 'access granted' : 'access denied'));
            }
            
            return $hasAccess;
        }
        
        // Verifica case-insensitive come fallback
        if (strcasecmp($permName, $featureName) === 0) {
            $hasAccess = (bool)$permDetails['can_read'] || 
                         (bool)$permDetails['can_write'] || 
                         (bool)$permDetails['can_create'];
                         
            if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
                error_log("Permission found with case-insensitive check '$permName': " . 
                         ($hasAccess ? 'access granted' : 'access denied'));
            }
            
            return $hasAccess;
        }
    }
    
    // Se arriviamo qui, il permesso non è stato trovato
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        error_log("Permission '$featureName' not found: access denied");
    }
    
    return false;
}

/**
 * Verifica se l'utente ha un determinato permesso con un tipo di accesso specifico
 * 
 * @param string $permissionName Nome del permesso
 * @param string $accessType Tipo di accesso (read, write, create)
 * @return bool True se l'utente ha il permesso, false altrimenti
 */
function hasPermission($permissionName, $accessType = 'read') {
    // Gestisci caso di permission name vuoto
    if (empty($permissionName)) {
        return false;
    }
    
    // Admin ha sempre tutti i permessi
    if (isset($_SESSION['role_id'])&&$_SESSION['role_id'] == 1) {
        return true;
    }
    
    // Ottieni i permessi dalla sessione
    $permissions = isset($_SESSION['permissions']) ? $_SESSION['permissions'] : [];
    
    // Verifica con corrispondenza esatta (case sensitive)
    if (isset($permissions[$permissionName])) {
        switch ($accessType) {
            case 'read':
                return (bool)$permissions[$permissionName]['can_read'];
            case 'write':
                return (bool)$permissions[$permissionName]['can_write'];
            case 'create':
                return (bool)$permissions[$permissionName]['can_create'];
            default:
                return false;
        }
    }
    
    // Normalizza il nome del permesso per confronti più flessibili
    $normalizedPermissionName = normalizePermissionName($permissionName);
    
    foreach ($permissions as $permName => $permDetails) {
        // Verifica con normalizzazione
        $normalizedPermName = normalizePermissionName($permName);
        
        if ($normalizedPermName === $normalizedPermissionName || strcasecmp($permName, $permissionName) === 0) {
            switch ($accessType) {
                case 'read':
                    return (bool)$permDetails['can_read'];
                case 'write':
                    return (bool)$permDetails['can_write'];
                case 'create':
                    return (bool)$permDetails['can_create'];
                default:
                    return false;
            }
        }
    }
    
    return false;
}

/**
 * Visualizza un debug box con i permessi dell'utente corrente
 * Funzione utile in ambiente di sviluppo
 */
function debugPermissions() {
    if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        return; // Esegui solo in ambiente di sviluppo
    }
    
    echo '<div class="card mb-4 bg-light">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti tabler-bug me-2"></i>DEBUG: Permessi Utente</h5>
            </div>
            <div class="card-body" style="max-height: 300px; overflow: auto;">
                <p>
                    <strong>User ID:</strong> ' . $_SESSION['user_id'] . '<br>
                    <strong>Username:</strong> ' . ($_SESSION['username'] ?? 'N/A') . '<br>
                    <strong>Role ID:</strong> ' . ($_SESSION['role_id'] ?? 'N/A') . '
                </p>';
    
    if (isset($_SESSION['permissions'])&&is_array($_SESSION['permissions'])) {
        echo '<div class="table-responsive">
                <table class="table table-sm table-bordered table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Permission</th>
                            <th>Category</th>
                            <th class="text-center">Read</th>
                            <th class="text-center">Write</th>
                            <th class="text-center">Create</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($_SESSION['permissions'] as $name => $details) {
            echo '<tr>
                    <td>' . htmlspecialchars($name) . '</td>
                    <td>' . htmlspecialchars($details['category']) . '</td>
                    <td class="text-center">' . ($details['can_read'] ? '<i class="ti tabler-check text-success"></i>' : '<i class="ti tabler-x text-danger"></i>') . '</td>
                    <td class="text-center">' . ($details['can_write'] ? '<i class="ti tabler-check text-success"></i>' : '<i class="ti tabler-x text-danger"></i>') . '</td>
                    <td class="text-center">' . ($details['can_create'] ? '<i class="ti tabler-check text-success"></i>' : '<i class="ti tabler-x text-danger"></i>') . '</td>
                  </tr>';
        }
        
        echo '</tbody>
            </table>
          </div>';
    } else {
        echo '<div class="alert alert-warning">
                <i class="ti tabler-alert-triangle me-1"></i> Nessun permesso trovato nella sessione.
              </div>';
    }
    
    echo '</div>
        </div>';
}

/**
 * Espone i permessi dell'utente a JavaScript
 * Crea funzioni JS utili per gestire l'interfaccia in base ai permessi
 */
function exposePermissionsToJs() {
    $permissions = isset($_SESSION['permissions']) ? $_SESSION['permissions'] : [];
    $roleId = $_SESSION['role_id'] ?? 0;
    
    echo "<script>\n";
    echo "/* Permessi utente esposti da PHP */\n";
    echo "const userPermissions = " . json_encode($permissions) . ";\n";
    echo "const userRoleId = " . $roleId . ";\n";
    
    echo "/**
 * Normalizza un nome di permesso per confronti JavaScript
 * @param {string} name - Nome del permesso da normalizzare
 * @return {string} Nome normalizzato
 */
function normalizePermissionName(name) {
    // Gestisce null o undefined
    if (!name) return '';
    // Rimuove spazi extra intorno a caratteri speciali come&return name.toLowerCase().replace(/\\s*([&])\\s*/g, '$1');
}

/**
 * Verifica se l'utente ha accesso in lettura a un permesso
 * @param {string} permissionName - Nome del permesso
 * @return {boolean} True se ha accesso, false altrimenti
 */
function checkReadPermission(permissionName) {
    // Admin ha tutti i permessi
    if (userRoleId === 1) return true;
    
    // Gestisci input nullo
    if (!permissionName) return false;
    
    // Verifica diretta
    if (userPermissions[permissionName]) {
        return userPermissions[permissionName].can_read === 1;
    }
    
    // Normalizza e verifica
    const normalizedName = normalizePermissionName(permissionName);
    
    for (const [name, details] of Object.entries(userPermissions)) {
        // Verifica normalizzata
        if (normalizePermissionName(name) === normalizedName) {
            return details.can_read === 1;
        }
        // Fallback case-insensitive
        if (name.toLowerCase() === permissionName.toLowerCase()) {
            return details.can_read === 1;
        }
    }
    
    return false;
}

/**
 * Verifica se l'utente ha accesso in scrittura a un permesso
 * @param {string} permissionName - Nome del permesso
 * @return {boolean} True se ha accesso, false altrimenti
 */
function checkWritePermission(permissionName) {
    // Admin ha tutti i permessi
    if (userRoleId === 1) return true;
    
    // Gestisci input nullo
    if (!permissionName) return false;
    
    // Verifica diretta
    if (userPermissions[permissionName]) {
        return userPermissions[permissionName].can_write === 1;
    }
    
    // Normalizza e verifica
    const normalizedName = normalizePermissionName(permissionName);
    
    for (const [name, details] of Object.entries(userPermissions)) {
        // Verifica normalizzata
        if (normalizePermissionName(name) === normalizedName) {
            return details.can_write === 1;
        }
        // Fallback case-insensitive
        if (name.toLowerCase() === permissionName.toLowerCase()) {
            return details.can_write === 1;
        }
    }
    
    return false;
}

/**
 * Verifica se l'utente ha accesso in creazione a un permesso
 * @param {string} permissionName - Nome del permesso
 * @return {boolean} True se ha accesso, false altrimenti
 */
function checkCreatePermission(permissionName) {
    // Admin ha tutti i permessi
    if (userRoleId === 1) return true;
    
    // Gestisci input nullo
    if (!permissionName) return false;
    
    // Verifica diretta
    if (userPermissions[permissionName]) {
        return userPermissions[permissionName].can_create === 1;
    }
    
    // Normalizza e verifica
    const normalizedName = normalizePermissionName(permissionName);
    
    for (const [name, details] of Object.entries(userPermissions)) {
        // Verifica normalizzata
        if (normalizePermissionName(name) === normalizedName) {
            return details.can_create === 1;
        }
        // Fallback case-insensitive
        if (name.toLowerCase() === permissionName.toLowerCase()) {
            return details.can_create === 1;
        }
    }
    
    return false;
}

/**
 * Applica controlli di permesso automaticamente agli elementi UI
 * Da eseguire dopo il caricamento del DOM
 */
function applyPermissionControls() {
    // Elementi con attributo data-permission-write
    document.querySelectorAll('[data-permission-write]').forEach(el => {
        const permName = el.getAttribute('data-permission-write');
        if (!checkWritePermission(permName)) {
            el.disabled = true;
            el.classList.add('disabled');
            // Aggiungi tooltip se non presente
            if (!el.getAttribute('title')) {
                el.setAttribute('title', 'Non hai permessi di modifica');
                el.setAttribute('data-bs-toggle', 'tooltip');
            }
        }
    });
    
    // Elementi con attributo data-permission-create
    document.querySelectorAll('[data-permission-create]').forEach(el => {
        const permName = el.getAttribute('data-permission-create');
        if (!checkCreatePermission(permName)) {
            el.disabled = true;
            el.classList.add('disabled');
            // Aggiungi tooltip se non presente
            if (!el.getAttribute('title')) {
                el.setAttribute('title', 'Non hai permessi di creazione');
                el.setAttribute('data-bs-toggle', 'tooltip');
            }
        }
    });
    
    // Elementi con attributo data-permission-read
    document.querySelectorAll('[data-permission-read]').forEach(el => {
        const permName = el.getAttribute('data-permission-read');
        if (!checkReadPermission(permName)) {
            // Per elementi che dovrebbero essere nascosti completamente
            el.style.display = 'none';
        }
    });
    
    // Reinizializza i tooltip se Bootstrap è disponibile
    if (typeof bootstrap !== 'undefined'&&bootstrap.Tooltip) {
        const tooltips = document.querySelectorAll('[data-bs-toggle=\"tooltip\"]');
        tooltips.forEach(el => new bootstrap.Tooltip(el));
    }
}

// Esegui i controlli quando il DOM è pronto
document.addEventListener('DOMContentLoaded', applyPermissionControls);";
    
    echo "</script>\n";
}

/**
 * Verifica l'accesso a una pagina e reindirizza se necessario
 * 
 * @param string $permissionName Nome del permesso richiesto
 * @param string $redirectUrl URL di reindirizzamento in caso di accesso negato
 * @param string $accessType Tipo di accesso richiesto (read, write, create)
 */
function requirePermission($permissionName, $redirectUrl = 'access-denied.php', $accessType = 'read') {
    $hasAccess = false;
    
    // Admin ha sempre accesso
    if (isset($_SESSION['role_id'])&&$_SESSION['role_id'] == 1) {
        $hasAccess = true;
    } else {
        // Verifica il tipo di accesso richiesto
        switch ($accessType) {
            case 'read':
                $hasAccess = hasPermission($permissionName, 'read');
                break;
            case 'write':
                $hasAccess = hasPermission($permissionName, 'write');
                break;
            case 'create':
                $hasAccess = hasPermission($permissionName, 'create');
                break;
            default:
                $hasAccess = userHasAccess($permissionName);
        }
    }
    
    // Se non ha accesso, reindirizza
    if (!$hasAccess) {
        if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            error_log("Access denied for permission: $permissionName ($accessType) - Redirecting to $redirectUrl");
        }
        
        header("Location: $redirectUrl");
        exit;
    }
}
?>
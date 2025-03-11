<?php
// menu_helper.php - Helper functions for the sidebar menu

/**
 * Determina se il link corrente corrisponde alla pagina attiva
 * 
 * @param string $link Il link da controllare
 * @return string La classe CSS da applicare all'elemento del menu
 */
function getMenuItemClass($link) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return ($currentPage == $link) ? 'menu-item active' : 'menu-item';
}

/**
 * Determina se un menu padre contiene la pagina corrente
 * 
 * @param array $links Array dei link contenuti nel menu padre
 * @return string La classe CSS da applicare all'elemento padre del menu
 */
function getParentMenuItemClass($links) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return in_array($currentPage, $links) ? 'menu-item open active' : 'menu-item';
}

/**
 * Determina lo stile del sottomenu (aperto o chiuso)
 * 
 * @param array $links Array dei link contenuti nel sottomenu
 * @return string Lo stile CSS da applicare al sottomenu
 */
function getSubmenuStyle($links) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return in_array($currentPage, $links) ? 'display: block;' : '';
}

/**
 * Verifica se l'utente ha accesso ad almeno una voce del menu padre
 * 
 * @param array $permissions Array dei permessi necessari per le voci del menu
 * @return bool True se l'utente ha accesso ad almeno una voce, false altrimenti
 */
function canAccessParentMenu($permissions) {
    foreach ($permissions as $permission) {
        if (userHasAccess($permission)) {
            return true;
        }
    }
    return false;
}
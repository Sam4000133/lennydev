/**
 * Menu State - Keeps parent menus open when a submenu item is active
 */

(function() {
  'use strict';

  function activateMenuItem() {
    // Ottieni il percorso della pagina corrente
    var currentPath = window.location.pathname;
    var currentFile = currentPath.split('/').pop();
    
    console.log('Current path:', currentPath);
    console.log('Current file:', currentFile);
    
    // Trova tutti gli elementi menu-item con sottomenu
    var menuItems = document.querySelectorAll('.menu-item');
    
    menuItems.forEach(function(menuItem) {
      // Controlla se questo menu-item contiene un link alla pagina corrente
      var links = menuItem.querySelectorAll('.menu-link, .menu-toggle');
      
      for (var i = 0; i < links.length; i++) {
        var link = links[i];
        var href = link.getAttribute('href');
        
        // Salta i link vuoti o javascript:void(0)
        if (!href || href === '#' || href.indexOf('javascript:') === 0) continue;
        
        // Estrai il nome del file dall'href
        var linkFile = href.split('/').pop();
        
        console.log('Checking link:', href, 'Link file:', linkFile);
        
        // Se il file corrente corrisponde al file del link
        if (currentFile === linkFile || currentPath.indexOf(href) !== -1) {
          console.log('Match found:', href);
          
          // Se siamo in un sottomenu, imposta anche il genitore come aperto
          var parentMenuItem = link.closest('.menu-item');
          if (parentMenuItem) {
            // Segna questo elemento come attivo
            parentMenuItem.classList.add('active');
            
            // Risali ai genitori e aprili
            while (parentMenuItem) {
              // Questo è un menu-item?
              if (parentMenuItem.classList.contains('menu-item')) {
                parentMenuItem.classList.add('open');
                
                // Imposta aria-expanded="true" sul menu-toggle
                var toggleBtn = parentMenuItem.querySelector('.menu-toggle');
                if (toggleBtn) {
                  toggleBtn.setAttribute('aria-expanded', 'true');
                }
              }
              
              // Vai al prossimo genitore
              parentMenuItem = parentMenuItem.parentElement;
              if (parentMenuItem) {
                parentMenuItem = parentMenuItem.closest('.menu-item');
              }
            }
            
            // Interrompi la ricerca, abbiamo trovato una corrispondenza
            return;
          }
        }
      }
    });
  }

  // Attendi che la pagina sia completamente caricata
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      // Esegui con un ritardo per assicurarsi che tutti gli script siano caricati
      setTimeout(activateMenuItem, 300);
    });
  } else {
    // La pagina è già caricata
    setTimeout(activateMenuItem, 300);
  }
})();
// menu_accordion.js - Comportamento a fisarmonica per il menu
document.addEventListener('DOMContentLoaded', function() {
    // Aggiungi listener per comportamento fisarmonica
    document.querySelectorAll('.menu-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            const menuItem = this.parentElement;
            
            // Se questo menu non è già aperto, chiudi tutti gli altri menu aperti
            if (!menuItem.classList.contains('open')) {
                document.querySelectorAll('.menu-item.open').forEach(function(openItem) {
                    if (openItem !== menuItem) {
                        openItem.classList.remove('open', 'active');
                        const submenu = openItem.querySelector('.menu-sub');
                        if (submenu) {
                            submenu.style.display = 'none';
                        }
                    }
                });
            }
        });
    });
});
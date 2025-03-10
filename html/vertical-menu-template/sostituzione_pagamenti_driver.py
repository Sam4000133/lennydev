import os
import re

# Percorso della cartella contenente i file HTML
cartella = r"C:\cambio-file"

# Contatore per i file modificati
file_modificati = 0

# Funzione per sostituire il testo nei file usando espressione regolare
def sostituisci_testo_driver(percorso_file):
    global file_modificati
    
    # Leggi il contenuto del file
    with open(percorso_file, 'r', encoding='utf-8') as file:
        contenuto = file.read()
    
    # Pattern esatto per trovare solo il link dei pagamenti driver
    # Cerca il pattern che contiene sia "Gestione Driver" che "Pagamenti driver"
    pattern = r'(<li class="menu-item">\s*<a href=")pagamenti\.html(" class="menu-link">\s*<i class="menu-icon icon-base ti tabler-cash"></i>\s*<div data-i18n="Pagamenti driver">Pagamenti driver</div>\s*</a>\s*</li>)'
    
    # Sostituisci solo l'URL mantenendo il resto identico
    nuovo_contenuto, num_sostituzioni = re.subn(pattern, r'\1pagamenti-driver.html\2', contenuto)
    
    # Se sono state fatte sostituzioni, scrivi il file
    if num_sostituzioni > 0:
        with open(percorso_file, 'w', encoding='utf-8') as file:
            file.write(nuovo_contenuto)
        
        print(f"Modificato: {percorso_file} ({num_sostituzioni} sostituzioni)")
        file_modificati += 1
        return True
    
    return False

# Elabora tutti i file HTML nella cartella
print("Inizio sostituzione...")
for nome_file in os.listdir(cartella):
    if nome_file.endswith('.html'):
        percorso_completo = os.path.join(cartella, nome_file)
        sostituisci_testo_driver(percorso_completo)

print(f"\nOperazione completata. File modificati: {file_modificati}")
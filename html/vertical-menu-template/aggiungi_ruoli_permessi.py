import os
import re

# Percorso della cartella contenente i file HTML
cartella = r"C:\cambio-file"

# Elemento da inserire (la nuova voce di menu)
nuova_voce = '''        <li class="menu-item">
          <a href="ruoli-permessi.html" class="menu-link">
            <i class="menu-icon icon-base ti tabler-key"></i>
            <div data-i18n="Ruoli & Permessi">Ruoli & permessi</div>
          </a>
        </li>'''

# Contatore per i file modificati
file_modificati = 0

# Funzione per inserire la nuova voce di menu dopo "Impostazioni" e prima di "Integrazioni"
def aggiungi_voce_menu(percorso_file):
    global file_modificati
    
    # Ignora il file ruoli-permessi.html
    if os.path.basename(percorso_file) == "ruoli-permessi.html":
        print(f"Saltato: {percorso_file} (file destinazione)")
        return False
    
    # Leggi il contenuto del file
    with open(percorso_file, 'r', encoding='utf-8') as file:
        contenuto = file.read()
    
    # Verifica se la voce è già presente nel file
    if 'href="ruoli-permessi.html"' in contenuto:
        print(f"Saltato: {percorso_file} (voce già presente)")
        return False
    
    # Pattern per trovare dove inserire la nuova voce:
    # Cerca la voce "Impostazioni" seguita dalla chiusura </li> all'interno della sottocategoria Sistema
    pattern = r'(<li class="menu-item(?:\s+active)?">\s*<a href="impostazioni\.html" class="menu-link">\s*<i class="menu-icon icon-base ti tabler-settings"></i>\s*<div data-i18n="Impostazioni">Impostazioni</div>\s*</a>\s*</li>)'
    
    # Verifica se il pattern è presente
    match = re.search(pattern, contenuto)
    if not match:
        print(f"Saltato: {percorso_file} (pattern 'Impostazioni' non trovato)")
        return False
    
    # Sostituisci il match con il match seguito dalla nuova voce
    nuovo_contenuto = re.sub(pattern, r'\1\n' + nuova_voce, contenuto)
    
    # Verifica se il contenuto è stato modificato
    if nuovo_contenuto != contenuto:
        # Scrivi il nuovo contenuto nel file
        with open(percorso_file, 'w', encoding='utf-8') as file:
            file.write(nuovo_contenuto)
        
        print(f"Modificato: {percorso_file}")
        file_modificati += 1
        return True
    
    print(f"Saltato: {percorso_file} (nessuna modifica necessaria)")
    return False

# Elabora tutti i file HTML nella cartella
print("Inizio aggiunta voce di menu 'Ruoli & permessi'...")
for nome_file in os.listdir(cartella):
    if nome_file.endswith('.html'):
        percorso_completo = os.path.join(cartella, nome_file)
        aggiungi_voce_menu(percorso_completo)

print(f"\nOperazione completata. File modificati: {file_modificati}")
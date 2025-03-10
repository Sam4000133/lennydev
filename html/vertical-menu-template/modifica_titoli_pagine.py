import os
import re
from bs4 import BeautifulSoup

# Percorso della cartella contenente i file HTML
cartella = r"C:\cambio-file"

# Contatore per i file modificati
file_modificati = 0

def modifica_titoli_pagina(percorso_file):
    global file_modificati
    
    # Leggi il contenuto del file
    with open(percorso_file, 'r', encoding='utf-8') as file:
        contenuto = file.read()
    
    # Usa BeautifulSoup per analizzare l'HTML
    soup = BeautifulSoup(contenuto, 'html.parser')
    
    # Cerca di trovare il menu attivo
    testo_menu = None
    
    # Prima cerca se c'è un sottomenu attivo
    submenu_active = soup.select_one('li.menu-item ul.menu-sub li.menu-item.active')
    if submenu_active:
        # Trova il testo del sottomenu attivo
        submenu_text = submenu_active.select_one('div[data-i18n]')
        if submenu_text:
            submenu_text = submenu_text.text.strip()
            
            # Trova il menu principale che contiene questo sottomenu
            parent_menu = submenu_active.parent.parent
            parent_text = parent_menu.select_one('div[data-i18n]')
            if parent_text:
                parent_text = parent_text.text.strip()
                testo_menu = f"{parent_text} - {submenu_text}"
    
    # Se non abbiamo trovato un sottomenu attivo, cerchiamo il menu principale attivo
    if not testo_menu:
        menu_active = soup.select_one('li.menu-item.active')
        if menu_active:
            menu_text = menu_active.select_one('div[data-i18n]')
            if menu_text:
                testo_menu = menu_text.text.strip()
    
    if not testo_menu:
        print(f"Nessun menu attivo trovato in: {percorso_file}")
        return False
    
    print(f"Menu attivo trovato: {testo_menu} in: {percorso_file}")
    
    # Sostituisci il titolo della pagina
    nuovo_titolo = f"{testo_menu} pagina di esempio"
    
    # Sostituisci il testo del paragrafo
    nuovo_paragrafo = f"""<p class="text-center">
                La pagina {testo_menu} è in lavori in corso.
                <br/>
                <div class="mt-4 d-flex justify-content-center">
                  <div class="rounded-circle bg-primary p-4">
                    <i class="ti tabler-tools text-white" style="font-size: 2rem;"></i>
                  </div>
                </div>
              </p>"""
    
    # Modifica il titolo h4
    pattern_titolo = r'<h4 class="py-4 mb-6">.*?</h4>'
    nuovo_contenuto = re.sub(pattern_titolo, f'<h4 class="py-4 mb-6">{nuovo_titolo}</h4>', contenuto)
    
    # Modifica il paragrafo - cerca sia il pattern originale che il pattern già modificato
    pattern_paragrafo1 = r'<p>\s*Sample page\.<br />For more layout options use\s*<a\s*target="_blank"\s*class="fw-medium"\s*>Layout docs</a\s*>\.\s*</p>'
    pattern_paragrafo2 = r'<p class="text-center">.*?</div>\s*</div>\s*</p>'
    
    # Prima prova con il pattern originale
    if re.search(pattern_paragrafo1, nuovo_contenuto):
        nuovo_contenuto = re.sub(pattern_paragrafo1, nuovo_paragrafo, nuovo_contenuto)
    # Se non trova, prova con il secondo pattern (già modificato in precedenza)
    elif re.search(pattern_paragrafo2, nuovo_contenuto):
        nuovo_contenuto = re.sub(pattern_paragrafo2, nuovo_paragrafo, nuovo_contenuto)
    
    # Verifica se sono state fatte modifiche
    if nuovo_contenuto != contenuto:
        # Scrivi il nuovo contenuto nel file
        with open(percorso_file, 'w', encoding='utf-8') as file:
            file.write(nuovo_contenuto)
        
        print(f"Modificato: {percorso_file}")
        file_modificati += 1
        return True
    
    print(f"Nessuna modifica necessaria in: {percorso_file}")
    return False

# Elabora tutti i file HTML nella cartella
print("Inizio modifica dei titoli delle pagine...")
for nome_file in os.listdir(cartella):
    if nome_file.endswith('.html'):
        percorso_completo = os.path.join(cartella, nome_file)
        modifica_titoli_pagina(percorso_completo)

print(f"\nOperazione completata. File modificati: {file_modificati}")
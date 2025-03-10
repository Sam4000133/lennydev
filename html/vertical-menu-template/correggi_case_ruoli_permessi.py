import os
import re

# Percorso della cartella contenente i file HTML
cartella = r"C:\cambio-file"

# Contatore per i file modificati
file_modificati = 0

def correggi_case_ruoli_permessi(percorso_file):
    global file_modificati
    
    # Leggi il contenuto del file
    with open(percorso_file, 'r', encoding='utf-8') as file:
        contenuto = file.read()
    
    # Pattern da cercare (con la 'P' maiuscola nell'attributo data-i18n)
    pattern = r'<div data-i18n="Ruoli & Permessi">Ruoli & permessi</div>'
    
    # Sostituzione con la 'p' minuscola nell'attributo data-i18n
    sostituzione = r'<div data-i18n="Ruoli & permessi">Ruoli & permessi</div>'
    
    # Effettua la sostituzione
    nuovo_contenuto, numero_sostituzioni = re.subn(pattern, sostituzione, contenuto)
    
    # Se sono state fatte sostituzioni, scrivi il file
    if numero_sostituzioni > 0:
        with open(percorso_file, 'w', encoding='utf-8') as file:
            file.write(nuovo_contenuto)
        
        print(f"Modificato: {percorso_file} ({numero_sostituzioni} sostituzioni)")
        file_modificati += 1
        return True
    
    print(f"Nessuna modifica necessaria in: {percorso_file}")
    return False

# Elabora tutti i file HTML nella cartella
print("Inizio correzione del case di 'Ruoli & permessi'...")
for nome_file in os.listdir(cartella):
    if nome_file.endswith('.html'):
        percorso_completo = os.path.join(cartella, nome_file)
        correggi_case_ruoli_permessi(percorso_completo)

print(f"\nOperazione completata. File modificati: {file_modificati}")
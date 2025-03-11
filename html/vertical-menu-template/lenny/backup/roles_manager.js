/**
 * Lenny - Role & Permissions Manager
 * Versione corretta
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  // Initialize variables
  let rolesList = [];
  let permissionsList = [];
  let selectAll = document.getElementById('selectAll');
  let modalRoleName = document.getElementById('modalRoleName');
  let modalTitle = document.querySelector('.role-title');
  let currentRoleId = null;
  
  // Load roles
  function loadRoles() {
    console.log('Caricamento ruoli...');
    fetch('roles_api.php')
      .then(response => response.json())
      .then(data => {
        console.log('Dati ruoli ricevuti:', data);
        if (data.success) {
          rolesList = data.data;
          renderRoleCards();
        } else {
          console.error('Errore caricamento ruoli:', data.message);
        }
      })
      .catch(error => console.error('Errore fetch ruoli:', error));
  }
  
  // Load permissions
  function loadPermissions() {
    console.log('Caricamento permessi...');
    fetch('permissions_api.php')
      .then(response => response.json())
      .then(data => {
        console.log('Dati permessi ricevuti:', data);
        if (data.success) {
          permissionsList = data.data;
          console.log('Permessi caricati:', permissionsList);
        } else {
          console.error('Errore caricamento permessi:', data.message);
        }
      })
      .catch(error => console.error('Errore fetch permessi:', error));
  }
  
  // Render role cards
  function renderRoleCards() {
    console.log('Rendering ruoli...');
    const roleCardsContainer = document.querySelector('.row.g-6');
    if (!roleCardsContainer) {
      console.error('Container ruoli non trovato');
      return;
    }
    
    // Keep only the last card (add new role) and remove others
    const addNewRoleCard = roleCardsContainer.querySelector('.card.h-100')?.closest('.col-xl-4');
    if (addNewRoleCard) {
      roleCardsContainer.innerHTML = '';
      roleCardsContainer.appendChild(addNewRoleCard);
    } else {
      console.warn('Card "aggiungi nuovo ruolo" non trovata');
    }
    
    // Add role cards
    rolesList.forEach(role => {
      const userCount = role.user_count || 0;
      console.log(`Rendering ruolo: ${role.name}, users: ${userCount}`);
      
      let avatarHTML = '';
      if (role.sample_users && role.sample_users.length > 0) {
        role.sample_users.forEach((user, index) => {
          if (index < 4) { // Show max 4 avatars
            const avatarId = (index % 14) + 1; // Usa un numero da 1 a 14 per gli avatar
            avatarHTML += `
              <li data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top" 
                  title="${user.full_name || user.username}" class="avatar pull-up">
                <img class="rounded-circle" src="../../../assets/img/avatars/${avatarId}.png" alt="Avatar" />
              </li>
            `;
          }
        });
        
        // If there are more users than shown
        if (userCount > 4) {
          avatarHTML += `
            <li class="avatar">
              <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip" 
                    data-bs-placement="bottom" title="${userCount - 4} more">
                +${userCount - 4}
              </span>
            </li>
          `;
        }
      } else {
        console.log('Nessun utente di esempio per questo ruolo');
      }
      
      const roleCard = document.createElement('div');
      roleCard.className = 'col-xl-4 col-lg-6 col-md-6';
      roleCard.innerHTML = `
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <h6 class="fw-normal mb-0 text-body">Total ${userCount} users</h6>
              <ul class="list-unstyled d-flex align-items-center avatar-group mb-0">
                ${avatarHTML}
              </ul>
            </div>
            <div class="d-flex justify-content-between align-items-end">
              <div class="role-heading">
                <h5 class="mb-1">${role.name}</h5>
                <a href="javascript:;" data-role-id="${role.id}" class="role-edit-modal">
                  <span>Modifica Ruolo</span>
                </a>
              </div>
              <a href="javascript:void(0);" data-role-id="${role.id}" class="delete-role">
                <i class="icon-base ti tabler-trash icon-md text-red-500"></i>
              </a>
            </div>
          </div>
        </div>
      `;
      
      // Insert before the add new role card
      roleCardsContainer.insertBefore(roleCard, addNewRoleCard);
      
      // Setup edit handler
      roleCard.querySelector('.role-edit-modal').addEventListener('click', function() {
        const roleId = this.getAttribute('data-role-id');
        openEditRoleModal(roleId);
      });
      
      // Setup delete handler
      roleCard.querySelector('.delete-role').addEventListener('click', function() {
        const roleId = this.getAttribute('data-role-id');
        deleteRole(roleId);
      });
    });
    
    // Reinitialize tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(tooltip => {
      new bootstrap.Tooltip(tooltip);
    });
  }
  
  // Open edit role modal
  function openEditRoleModal(roleId) {
    console.log('Apertura modal modifica ruolo:', roleId);
    const role = rolesList.find(r => r.id == roleId);
    if (!role) {
      console.error('Ruolo non trovato:', roleId);
      return;
    }
    
    // Set modal title and role name
    modalTitle.textContent = 'Modifica ruolo';
    modalRoleName.value = role.name;
    currentRoleId = roleId;
    
    // Fetch role permissions and set the checkboxes
    fetch(`roles_api.php?id=${roleId}`)
      .then(response => response.json())
      .then(data => {
        console.log('Dati permessi ruolo ricevuti:', data);
        if (data.success) {
          const rolePermissions = data.data.permissions;
          
          // Reset all checkboxes
          document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = false;
          });
          
          // Organize permissions by category
          const permissionsByCategory = {};
          rolePermissions.forEach(perm => {
            if (!permissionsByCategory[perm.category]) {
              permissionsByCategory[perm.category] = {
                read: false,
                write: false,
                create: false
              };
            }
            
            if (perm.can_read == 1) permissionsByCategory[perm.category].read = true;
            if (perm.can_write == 1) permissionsByCategory[perm.category].write = true;
            if (perm.can_create == 1) permissionsByCategory[perm.category].create = true;
          });
          
          console.log('Permessi organizzati per categoria:', permissionsByCategory);
          
          // Update checkboxes based on permissions
          document.querySelectorAll('table tbody tr').forEach(row => {
            const categoryCell = row.querySelector('td:first-child');
            if (!categoryCell) return;
            
            const category = categoryCell.textContent.trim();
            if (category === 'Accesso Amministratore') return;
            
            if (permissionsByCategory[category]) {
              const perms = permissionsByCategory[category];
              
              // Normalizziamo il nome della categoria per gli ID
              const categoryId = category.toLowerCase().replace(/\s+|&/g, '');
              
              // Set checkboxes
              const readCheckbox = document.getElementById(`${categoryId}Read`);
              if (readCheckbox) readCheckbox.checked = perms.read;
              
              const writeCheckbox = document.getElementById(`${categoryId}Write`);
              if (writeCheckbox) writeCheckbox.checked = perms.write;
              
              const createCheckbox = document.getElementById(`${categoryId}Create`);
              if (createCheckbox) createCheckbox.checked = perms.create;
            }
          });
          
          // Check if all checkboxes are checked
          updateSelectAllCheckbox();
          
          // Open the modal
          const modal = new bootstrap.Modal(document.getElementById('addRoleModal'));
          modal.show();
        } else {
          console.error('Errore caricamento permessi ruolo:', data.message);
        }
      })
      .catch(error => console.error('Errore:', error));
  }
  
  // Delete role
  function deleteRole(roleId) {
    console.log('Tentativo eliminazione ruolo:', roleId);
    if (confirm('Sei sicuro di voler eliminare questo ruolo? Questa azione non può essere annullata.')) {
      fetch(`roles_api.php?id=${roleId}`, {
        method: 'DELETE'
      })
        .then(response => response.json())
        .then(data => {
          console.log('Risposta eliminazione ruolo:', data);
          if (data.success) {
            // Reload roles and refresh the view
            loadRoles();
            
            // Show success message
            alert('Ruolo eliminato con successo!');
          } else {
            alert('Errore durante l\'eliminazione del ruolo: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Errore eliminazione ruolo:', error);
          alert('Errore di connessione: ' + error);
        });
    }
  }
  
  // Handle select all checkbox
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      const isChecked = this.checked;
      document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        if (checkbox !== selectAll) {
          checkbox.checked = isChecked;
        }
      });
    });
  }
  
  // Update select all checkbox based on other checkboxes
  function updateSelectAllCheckbox() {
    if (!selectAll) return;
    
    const totalCheckboxes = document.querySelectorAll('input[type="checkbox"]:not(#selectAll)').length;
    const checkedCheckboxes = document.querySelectorAll('input[type="checkbox"]:not(#selectAll):checked').length;
    
    selectAll.checked = checkedCheckboxes === totalCheckboxes;
    selectAll.indeterminate = checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
  }
  
  // CORREZIONE PRINCIPALE: Gestione corretta dell'invio del form
  const addRoleForm = document.getElementById('addRoleForm');
  if (addRoleForm) {
    addRoleForm.addEventListener('submit', function(e) {
      e.preventDefault(); // Previeni il comportamento predefinito del form
      
      console.log('Invio form ruolo avviato');
      
      const roleName = modalRoleName.value.trim();
      if (!roleName) {
        alert('Inserisci un nome per il ruolo');
        return;
      }
      
      // Raccogli i permessi corretti dall'API
      const permissions = [];
      
      // Per ogni permesso nel sistema
      permissionsList.forEach(permission => {
        const categoryId = permission.category.toLowerCase().replace(/\s+|&/g, '');
        
        // Trova i checkbox corrispondenti per questo permesso
        const readCheckbox = document.getElementById(`${categoryId}Read`);
        const writeCheckbox = document.getElementById(`${categoryId}Write`);
        const createCheckbox = document.getElementById(`${categoryId}Create`);
        
        // Verifica se i checkbox sono selezionati
        const canRead = readCheckbox && readCheckbox.checked ? 1 : 0;
        const canWrite = writeCheckbox && writeCheckbox.checked ? 1 : 0;
        const canCreate = createCheckbox && createCheckbox.checked ? 1 : 0;
        
        // Se almeno uno dei permessi è attivo, aggiungi alla lista
        if (canRead || canWrite || canCreate) {
          permissions.push({
            id: permission.id, // Importante: usa l'ID del permesso, non la categoria
            can_read: canRead,
            can_write: canWrite,
            can_create: canCreate
          });
        }
      });
      
      // Crea i dati da inviare
      const roleData = {
        name: roleName,
        description: '',
        permissions: permissions
      };
      
      // Se stiamo modificando un ruolo esistente, aggiungi l'ID
      if (currentRoleId) {
        roleData.id = currentRoleId;
      }
      
      console.log('Dati ruolo da inviare:', roleData);
      
      // Invia i dati all'API corretta
      fetch('roles_api.php', {
        method: currentRoleId ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(roleData)
      })
        .then(response => {
          console.log('Status risposta HTTP:', response.status);
          return response.json();
        })
        .then(data => {
          console.log('Risposta server:', data);
          
          if (data.success) {
            // Chiudi il modal
            const modalElement = document.getElementById('addRoleModal');
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) {
              modalInstance.hide();
            }
            
            // Reset form
            modalRoleName.value = '';
            currentRoleId = null;
            modalTitle.textContent = 'Aggiungi nuovo ruolo';
            
            // Reset all checkboxes
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
              checkbox.checked = false;
            });
            
            // Ricarica i ruoli per aggiornare la vista
            loadRoles();
            
            // Mostra messaggio di successo
            alert(currentRoleId ? 'Ruolo aggiornato con successo!' : 'Ruolo creato con successo!');
          } else {
            alert('Errore: ' + (data.message || 'Errore sconosciuto'));
          }
        })
        .catch(error => {
          console.error('Errore durante l\'operazione:', error);
          alert('Errore di connessione: ' + error);
        });
    });
  } else {
    console.error('Form ruolo non trovato!');
  }
  
  // Add event listener to "Add new role" button to reset currentRoleId
  document.querySelector('.add-new-role')?.addEventListener('click', function() {
    console.log('Apertura modal nuovo ruolo');
    
    // Reset form values
    modalRoleName.value = '';
    currentRoleId = null;
    modalTitle.textContent = 'Aggiungi nuovo ruolo';
    
    // Reset all checkboxes
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
      checkbox.checked = false;
    });
    
    // Update select all checkbox
    updateSelectAllCheckbox();
  });
  
  // Add event listeners to permission checkboxes
  document.addEventListener('change', function(e) {
    if (e.target && e.target.type === 'checkbox') {
      updateSelectAllCheckbox();
    }
  });
  
  // Initialize the page
  console.log('Inizializzazione pagina...');
  loadRoles();
  loadPermissions();
});
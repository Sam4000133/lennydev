/**
 * Lenny - Users Manager
 * Script per la gestione degli utenti
 */

'use strict';

document.addEventListener('DOMContentLoaded', function() {
  console.log('Inizializzazione Users Manager');

  // Distruggi qualsiasi istanza esistente per sicurezza
  if ($.fn.DataTable.isDataTable('.datatables-users')) {
    $('.datatables-users').DataTable().destroy();
    console.log('Distrutta precedente istanza della tabella');
  }

  // Inizializzazione della DataTable per gli utenti
  const usersTable = $('.datatables-users').DataTable({
    ajax: {
      url: 'users_api.php',
      dataSrc: 'data'  // IMPORTANTE: Specifica dove trovare i dati nella risposta JSON
    },
    columns: [
      { data: '', defaultContent: '', className: 'control' }, // Colonna per controllo responsive
      { data: 'avatar', 
        render: function(data, type, row) {
          return `<div class="avatar avatar-sm">
                    <img src="${data || '../../../assets/img/avatars/' + (Math.floor(Math.random() * 14) + 1) + '.png'}" alt="Avatar" class="rounded-circle">
                  </div>`;
        } 
      },
      { data: 'full_name', 
        render: function(data, type, row) {
          return `<div class="d-flex justify-content-start align-items-center user-name">
                    <div class="d-flex flex-column">
                      <span class="fw-medium">${data || row.username}</span>
                      <small class="text-body-secondary">${row.email}</small>
                    </div>
                  </div>`;
        } 
      },
      { data: 'role_name', defaultContent: '<span class="text-muted">Non assegnato</span>' },
      { data: 'status',
        render: function(data) {
          let statusClass = {
            'active': 'bg-label-success',
            'inactive': 'bg-label-secondary',
            'suspended': 'bg-label-warning'
          };
          
          return `<span class="badge ${statusClass[data] || 'bg-label-primary'}">${data}</span>`;
        }
      },
      { 
        data: null,
        render: function (data, type, row) {
          return `<div class="d-flex">
                    <a href="javascript:void(0);" class="btn btn-sm btn-icon btn-text-secondary rounded-pill edit-user me-1" 
                       data-id="${row.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Modifica">
                      <i class="icon-base ti tabler-edit"></i>
                    </a>
                    <a href="javascript:void(0);" class="btn btn-sm btn-icon btn-text-secondary rounded-pill view-user me-1" 
                       data-id="${row.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Visualizza">
                      <i class="icon-base ti tabler-eye"></i>
                    </a>
                    <a href="javascript:void(0);" class="btn btn-sm btn-icon btn-text-secondary rounded-pill delete-user" 
                       data-id="${row.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Elimina">
                      <i class="icon-base ti tabler-trash"></i>
                    </a>
                  </div>`;
        }
      }
    ],
    columnDefs: [
      {
        targets: 0,
        orderable: false,
        searchable: false,
        responsivePriority: 2
      }
    ],
    dom: '<"card-header d-flex flex-wrap py-3"<"me-5"f><"dt-action-buttons text-end ms-auto"B>><"table-responsive"t><"card-footer d-flex align-items-center"<"m-0"i><"pagination justify-content-end"p>>',
    buttons: [
      {
        text: '<i class="icon-base ti tabler-plus me-1"></i><span>Aggiungi nuovo utente</span>',
        className: 'btn btn-primary',
        attr: {
          'data-bs-toggle': 'modal',
          'data-bs-target': '#addUserModal'
        }
      }
    ],
    responsive: {
      details: {
        display: $.fn.dataTable.Responsive.display.modal({
          header: function(row) {
            const data = row.data();
            return 'Dettagli per ' + (data.full_name || data.username);
          }
        }),
        type: 'column',
        renderer: function(api, rowIdx, columns) {
          const data = $.map(columns, function(col, i) {
            return col.title !== '' && col.hidden
              ? '<tr data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">' +
                  '<td>' + col.title + ':' + '</td> ' +
                  '<td>' + col.data + '</td>' +
                '</tr>'
              : '';
          }).join('');

          return data ? $('<table class="table"/>').append(data) : false;
        }
      }
    },
    language: {
      search: 'Cerca:',
      searchPlaceholder: 'Cerca utente...',
      lengthMenu: 'Mostra _MENU_ elementi',
      info: 'Visualizzati _START_ - _END_ di _TOTAL_ elementi',
      infoEmpty: 'Nessun elemento disponibile',
      infoFiltered: '(filtrati da _MAX_ elementi totali)',
      paginate: {
        first: 'Primo',
        previous: 'Precedente',
        next: 'Successivo',
        last: 'Ultimo'
      },
      emptyTable: 'Nessun dato disponibile nella tabella'
    }
  });

  // Rendi disponibile usersTable globalmente
  window.usersTable = usersTable;

  // Inizializza i tooltip per i pulsanti delle azioni
  function initializeTooltips() {
    // Distruggi i tooltip esistenti per evitare duplicati
    $('[data-bs-toggle="tooltip"]').tooltip('dispose');
    
    // Crea nuovi tooltip
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  }

  // Reinizializza i tooltip dopo ogni ridisegno della tabella
  usersTable.on('draw', function () {
    initializeTooltips();
  });

  // Toggle per la visibilità della password
  $(document).on('click', '.toggle-password', function() {
    const passwordInput = $(this).siblings('input');
    const icon = $(this).find('i');
    
    if (passwordInput.attr('type') === 'password') {
      passwordInput.attr('type', 'text');
      icon.removeClass('tabler-eye-off').addClass('tabler-eye');
    } else {
      passwordInput.attr('type', 'password');
      icon.removeClass('tabler-eye').addClass('tabler-eye-off');
    }
  });

  // Reset del form quando si apre il modal per aggiungere un nuovo utente
  $(document).on('click', '[data-bs-target="#addUserModal"]', function() {
    resetUserForm();
  });

  // Reset del form
  function resetUserForm() {
    const userForm = document.getElementById('addUserForm');
    if (userForm) {
      userForm.reset();
      document.getElementById('userId').value = '';
      document.querySelector('.user-title').textContent = 'Aggiungi nuovo utente';
      document.querySelector('.user-subtitle').textContent = 'Inserisci i dati per il nuovo utente';
      
      // Gestione elementi per password
      const passwordHint = document.querySelector('.password-hint');
      if (passwordHint) passwordHint.style.display = 'none';
      
      const passwordRequired = document.querySelector('.user-password-required');
      if (passwordRequired) passwordRequired.style.display = 'inline';
      
      // Focus sul primo campo dopo l'apertura del modal
      $('#addUserModal').on('shown.bs.modal', function() {
        $('#modalUserName').focus();
      });
    }
  }

  // Gestione del form per aggiungere/modificare un utente
  const userForm = document.getElementById('addUserForm');
  if (userForm) {
    userForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Recupera i dati dal form
      const userId = document.getElementById('userId').value;
      const username = document.getElementById('modalUserName').value.trim();
      const email = document.getElementById('modalUserEmail').value.trim();
      const fullName = document.getElementById('modalUserFullName').value.trim();
      const roleId = document.getElementById('modalUserRole').value;
      const password = document.getElementById('modalUserPassword').value;
      const status = document.getElementById('modalUserStatus').value;
      
      // Validazione base
      if (!username || !email) {
        alert('Username e email sono campi obbligatori');
        return;
      }
      
      // Validazione email
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        alert('Inserisci un indirizzo email valido');
        return;
      }
      
      // Password obbligatoria per i nuovi utenti
      if (!userId && !password) {
        alert('La password è obbligatoria per i nuovi utenti');
        return;
      }
      
      // Prepara i dati da inviare
      const userData = {
        username: username,
        email: email,
        full_name: fullName,
        role_id: roleId,
        status: status
      };
      
      // Aggiungi la password solo se fornita
      if (password) {
        userData.password = password;
      }
      
      // Aggiungi l'ID se è un aggiornamento
      if (userId) {
        userData.id = userId;
      }
      
      // Mostra spinner durante il salvataggio
      const submitBtn = userForm.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvataggio...';
      submitBtn.disabled = true;
      
      // Invia i dati all'API
      fetch('users_api.php', {
        method: userId ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(userData)
      })
        .then(response => response.json())
        .then(data => {
          // Ripristina il pulsante
          submitBtn.innerHTML = originalBtnText;
          submitBtn.disabled = false;
          
          if (data.success) {
            // Chiudi il modal
            const modalElement = document.getElementById('addUserModal');
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) {
              modalInstance.hide();
            }
            
            // Ricarica la tabella
            usersTable.ajax.reload();
            
            // Messaggio di successo
            alert(userId ? 'Utente aggiornato con successo!' : 'Utente creato con successo!');
          } else {
            // Messaggio di errore
            alert('Errore: ' + data.message);
          }
        })
        .catch(error => {
          // Ripristina il pulsante
          submitBtn.innerHTML = originalBtnText;
          submitBtn.disabled = false;
          
          console.error('Errore:', error);
          alert('Errore di connessione al server. Riprova più tardi.');
        });
    });
  }

  // Gestione del click sul pulsante di modifica
  $(document).on('click', '.edit-user', function() {
    const userId = $(this).data('id');
    
    // Aggiorna il titolo del modal
    document.querySelector('.user-title').textContent = 'Modifica utente';
    document.querySelector('.user-subtitle').textContent = 'Modifica i dati dell\'utente';
    
    // Mostra/nascondi elementi relativi alla password
    const passwordHint = document.querySelector('.password-hint');
    if (passwordHint) passwordHint.style.display = 'block';
    
    const passwordRequired = document.querySelector('.user-password-required');
    if (passwordRequired) passwordRequired.style.display = 'none';
    
    // Mostra spinner sul pulsante
    const editBtn = $(this);
    const originalContent = editBtn.html();
    editBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
    editBtn.prop('disabled', true);
    
    // Recupera i dati dell'utente
    fetch(`users_api.php?id=${userId}`)
      .then(response => response.json())
      .then(data => {
        // Ripristina il pulsante
        editBtn.html(originalContent);
        editBtn.prop('disabled', false);
        
        if (data.success) {
          const user = data.data;
          
          // Popola il form con i dati utente
          document.getElementById('userId').value = user.id;
          document.getElementById('modalUserName').value = user.username;
          document.getElementById('modalUserEmail').value = user.email;
          document.getElementById('modalUserFullName').value = user.full_name || '';
          document.getElementById('modalUserRole').value = user.role_id || '';
          document.getElementById('modalUserStatus').value = user.status || 'active';
          document.getElementById('modalUserPassword').value = ''; // Password sempre vuota
          
          // Apri il modal
          const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
          modal.show();
        } else {
          alert('Errore nel caricamento dei dati utente: ' + data.message);
        }
      })
      .catch(error => {
        // Ripristina il pulsante
        editBtn.html(originalContent);
        editBtn.prop('disabled', false);
        
        console.error('Errore:', error);
        alert('Errore di connessione al server. Riprova più tardi.');
      });
  });

  // Gestione del click sul pulsante di visualizzazione
  $(document).on('click', '.view-user', function() {
    const userId = $(this).data('id');
    
    // Mostra spinner sul pulsante
    const viewBtn = $(this);
    const originalContent = viewBtn.html();
    viewBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
    viewBtn.prop('disabled', true);
    
    // Recupera i dati dell'utente
    fetch(`users_api.php?id=${userId}`)
      .then(response => response.json())
      .then(data => {
        // Ripristina il pulsante
        viewBtn.html(originalContent);
        viewBtn.prop('disabled', false);
        
        if (data.success) {
          const user = data.data;
          
          // Crea un modal dinamico per visualizzare i dettagli
          let viewModal = document.getElementById('viewUserModal');
          if (!viewModal) {
            const modalHTML = `
              <div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Dettagli utente</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="d-flex justify-content-center mb-4">
                        <div class="avatar avatar-xl">
                          <img src="../../../assets/img/avatars/1.png" alt="Avatar" class="rounded-circle" id="viewUserAvatar">
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-12 mb-3">
                          <label class="form-label">Username:</label>
                          <p id="viewUserUsername" class="mb-0 fw-medium"></p>
                        </div>
                        <div class="col-12 mb-3">
                          <label class="form-label">Email:</label>
                          <p id="viewUserEmail" class="mb-0 fw-medium"></p>
                        </div>
                        <div class="col-12 mb-3">
                          <label class="form-label">Nome completo:</label>
                          <p id="viewUserFullName" class="mb-0 fw-medium"></p>
                        </div>
                        <div class="col-12 mb-3">
                          <label class="form-label">Ruolo:</label>
                          <p id="viewUserRole" class="mb-0 fw-medium"></p>
                        </div>
                        <div class="col-12 mb-3">
                          <label class="form-label">Stato:</label>
                          <p id="viewUserStatus" class="mb-0"></p>
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Chiudi</button>
                      <button type="button" class="btn btn-primary edit-from-view" data-id="">Modifica</button>
                    </div>
                  </div>
                </div>
              </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            viewModal = document.getElementById('viewUserModal');
          }
          
          // Imposta i dati nel modal
          document.getElementById('viewUserUsername').textContent = user.username;
          document.getElementById('viewUserEmail').textContent = user.email;
          document.getElementById('viewUserFullName').textContent = user.full_name || '-';
          document.getElementById('viewUserRole').textContent = user.role_name || 'Non assegnato';
          
          // Imposta lo stato con badge
          const statusElement = document.getElementById('viewUserStatus');
          const statusClasses = {
            'active': 'success',
            'inactive': 'secondary',
            'suspended': 'warning'
          };
          const statusClass = statusClasses[user.status] || 'primary';
          statusElement.innerHTML = `<span class="badge bg-${statusClass}">${user.status}</span>`;
          
          // Imposta l'avatar
          const avatarElement = document.getElementById('viewUserAvatar');
          avatarElement.src = user.avatar || `../../../assets/img/avatars/${Math.floor(Math.random() * 14) + 1}.png`;
          
          // Imposta l'ID per il pulsante modifica
          const editFromViewBtn = viewModal.querySelector('.edit-from-view');
          editFromViewBtn.setAttribute('data-id', user.id);
          
          // Handler per il pulsante modifica
          $(editFromViewBtn).off('click').on('click', function() {
            // Chiudi il modal di visualizzazione
            bootstrap.Modal.getInstance(viewModal).hide();
            
            // Attiva l'azione di modifica
            $(`.edit-user[data-id="${this.getAttribute('data-id')}"]`).click();
          });
          
          // Apri il modal
          const modal = new bootstrap.Modal(viewModal);
          modal.show();
        } else {
          alert('Errore nel caricamento dei dati utente: ' + data.message);
        }
      })
      .catch(error => {
        // Ripristina il pulsante
        viewBtn.html(originalContent);
        viewBtn.prop('disabled', false);
        
        console.error('Errore:', error);
        alert('Errore di connessione al server. Riprova più tardi.');
      });
  });

  // Gestione del click sul pulsante di eliminazione
  $(document).on('click', '.delete-user', function() {
    const userId = $(this).data('id');
    
    // Cerca di ottenere il nome utente dalla riga della tabella
    const userRow = $(this).closest('tr');
    let userName = 'questo utente';
    
    try {
      const userData = usersTable.row(userRow).data();
      if (userData) {
        userName = userData.full_name || userData.username;
      }
    } catch (e) {
      console.warn('Impossibile ottenere i dati della riga', e);
    }
    
    if (confirm(`Sei sicuro di voler eliminare l'utente "${userName}"? Questa azione non può essere annullata.`)) {
      // Mostra spinner sul pulsante
      const deleteBtn = $(this);
      const originalContent = deleteBtn.html();
      deleteBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
      deleteBtn.prop('disabled', true);
      
      // Invia la richiesta di eliminazione
      fetch(`users_api.php?id=${userId}`, {
        method: 'DELETE'
      })
        .then(response => response.json())
        .then(data => {
          // Ripristina il pulsante
          deleteBtn.html(originalContent);
          deleteBtn.prop('disabled', false);
          
          if (data.success) {
            // Ricarica la tabella
            usersTable.ajax.reload();
            
            // Messaggio di successo
            alert('Utente eliminato con successo!');
          } else {
            alert('Errore durante l\'eliminazione dell\'utente: ' + data.message);
          }
        })
        .catch(error => {
          // Ripristina il pulsante
          deleteBtn.html(originalContent);
          deleteBtn.prop('disabled', false);
          
          console.error('Errore:', error);
          alert('Errore di connessione al server. Riprova più tardi.');
        });
    }
  });

  // Inizializzazione dei tooltip
  initializeTooltips();
});
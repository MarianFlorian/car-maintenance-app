// assets/js/admin_users.js

/**
 * Injectează modalele pentru CRUD utilizatori și vehicule.
 */
function initAdminUserModals() {
  const modalHTML = `
  <!-- Add User Modal -->
  <div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog"><form method="post" class="modal-content">
      <input type="hidden" name="form_type" value="add_user">
      <div class="modal-header">
        <h5 class="modal-title">Adaugă Utilizator</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>Email</label>
          <input type="email" name="email" class="form-control" required></div>
        <div class="form-group"><label>Parolă</label>
          <input type="password" name="password" class="form-control" required></div>
        <div class="form-group"><label>Confirmă Parolă</label>
          <input type="password" name="confirm_password" class="form-control" required></div>
        <div class="form-group"><label>Rol</label>
          <select name="role" class="form-control">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select></div>
        <hr>
        <h6>Adaugă vehicul (opțional)</h6>
        <div class="form-group"><label>Brand</label>
          <input type="text" name="vehicle_brand" class="form-control"></div>
        <div class="form-group"><label>Model</label>
          <input type="text" name="vehicle_model" class="form-control"></div>
        <div class="form-group"><label>An</label>
          <input type="number" name="vehicle_year" class="form-control" min="1900" max="${new Date().getFullYear()}"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button type="submit" class="btn btn-primary">Adaugă</button>
      </div>
    </form></div>
  </div>

  <!-- User Details / Edit Modal -->
  <div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalii Utilizator</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <form method="post" id="editUserForm">
          <input type="hidden" name="form_type" value="edit_user">
          <input type="hidden" name="user_id" id="detailUserId">
          <div class="form-row">
            <div class="form-group col-md-6"><label>Email</label>
              <input type="email" name="email" id="detailEmail" class="form-control" required></div>
            <div class="form-group col-md-6"><label>Rol</label>
              <select name="role" id="detailRole" class="form-control">
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Parolă Nouă (opțional)</label>
              <input type="password" name="new_password" class="form-control"></div>
            <div class="form-group col-md-6"><label>Confirmă Parolă</label>
              <input type="password" name="confirm_password" class="form-control"></div>
          </div>
          <button type="submit" class="btn btn-primary">Salvează Utilizator</button>
        </form>
        <hr>
        <h6>Vehicule</h6>
        <table class="table table-sm mb-3" id="detailVehiclesTable">
          <thead>
            <tr><th>ID</th><th>Brand</th><th>Model</th><th>An</th><th>Acțiuni</th></tr>
          </thead>
          <tbody></tbody>
        </table>
        <form method="post" id="addVehicleForm">
          <input type="hidden" name="form_type" value="add_vehicle_user">
          <input type="hidden" name="user_vehicle_id" id="userVehicleId">
          <div class="form-row">
            <div class="form-group col-md-4"><label>Brand</label>
              <input type="text" name="vehicle_brand" class="form-control" required></div>
            <div class="form-group col-md-4"><label>Model</label>
              <input type="text" name="vehicle_model" class="form-control" required></div>
            <div class="form-group col-md-4"><label>An</label>
              <input type="number" name="vehicle_year" class="form-control" min="1900" max="${new Date().getFullYear()}" required></div>
          </div>
          <button type="submit" class="btn btn-success">Adaugă Vehicul</button>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Închide</button>
      </div>
    </div></div>
  </div>

  <!-- Edit Vehicle Modal -->
  <div class="modal fade" id="editVehicleModal" tabindex="-1">
    <div class="modal-dialog"><form method="post" class="modal-content">
      <input type="hidden" name="form_type" value="edit_vehicle_user">
      <input type="hidden" name="vehicle_id" id="editVehId">
      <div class="modal-header">
        <h5 class="modal-title">Editează Vehicul</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>Brand</label>
          <input type="text" name="brand" id="editVehBrand" class="form-control" required></div>
        <div class="form-group"><label>Model</label>
          <input type="text" name="model" id="editVehModel" class="form-control" required></div>
        <div class="form-group"><label>An</label>
          <input type="number" name="year" id="editVehYear" class="form-control" min="1900" max="${new Date().getFullYear()}" required></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button class="btn btn-warning">Salvează Vehicul</button>
      </div>
    </form></div>
  </div>
  `;
  document.body.insertAdjacentHTML('beforeend', modalHTML);
}


$(function(){
  // Inject modals once on page load
  initAdminUserModals();

  // Parse vehicles data passed from PHP
  const vehiclesData = window.vehiclesDataJson
    ? JSON.parse(window.vehiclesDataJson)
    : {};

  // Initialize DataTable
  $('#usersTable').DataTable({
    lengthMenu: [10,25,50,100],
    pageLength: 25
  });

  // Show Add User modal
  $('#addUserBtn').on('click', () => {
    $('#addUserModal').modal('show');
  });

  // View / Edit User
  $('#usersTable tbody').on('click', '.viewUserBtn, .editUserBtn', function(){
    const tr = $(this).closest('tr');
    const uid = tr.data('id');
    $('#detailUserId').val(uid);
    $('#detailEmail').val(tr.data('email'));
    $('#detailRole').val(tr.data('role'));
    $('#userVehicleId').val(uid);

    const vs = vehiclesData[uid] || [];
    const tb = $('#detailVehiclesTable tbody').empty();
    vs.forEach(v => {
      tb.append(`
        <tr data-vid="${v.id}"
            data-brand="${v.brand}"
            data-model="${v.model}"
            data-year="${v.year}">
          <td>${v.id}</td>
          <td>${v.brand}</td>
          <td>${v.model}</td>
          <td>${v.year}</td>
          <td>
            <button class="btn btn-sm btn-warning editVehBtn">
              <i class="fas fa-edit"></i>
            </button>
          </td>
        </tr>`);
    });

    $('#userDetailsModal').modal('show');
  });

  // Edit Vehicle
  $(document).on('click', '.editVehBtn', function(){
    const row = $(this).closest('tr');
    $('#editVehId').val(   row.data('vid')   );
    $('#editVehBrand').val(row.data('brand'));
    $('#editVehModel').val(row.data('model'));
    $('#editVehYear').val( row.data('year')  );
    $('#editVehicleModal').modal('show');
  });
});

// assets/js/scripts.js

$(function() {
  /* === REALIMENTĂRI === */

  // RESET modal pentru ADD
  $('#addBtn').on('click', function() {
    $('#formType').val('add_fueling');
    $('#fuelId').val('');
    $('#fuelForm')[0].reset();
    $('#fuelModalLabel').text('Adaugă Realimentare');
    $('#submitBtn').text('Adaugă Realimentare');
  });

  // POPULATE & SHOW modal pentru EDIT
  $('.editFuelBtn').on('click', function() {
    var tr = $(this).closest('tr');
    $('#formType').val('edit_fueling');
    $('#fuelId').val(tr.data('id'));
    $('#fuelModalLabel').text('Editează Realimentare');
    $('#submitBtn').text('Salvează Modificări');

    $('select[name="vehicle_id"]').val(  tr.data('vehicleId')   );
    $('input[name="date"]').val(         tr.data('date')        );
    $('input[name="time"]').val(         tr.data('time')        );
    $('input[name="km"]').val(           tr.data('km')          );
    $('select[name="fuel_type"]').val(   tr.data('fuelType')    );
    $('input[name="price_per_l"]').val(  tr.data('pricePerL')   );
    $('input[name="total_cost"]').val(   tr.data('totalCost')   );
    $('input[name="liters"]').val(       tr.data('liters')      );
    $('input[name="gas_station"]').val(  tr.data('gasStation')  );
    $('#full_tank').prop('checked',      tr.data('fullTank')    );
    
    $('#fuelModal').modal('show');
  });

  // OCR + parse_receipt
  $('#extractBtn').on('click', async function() {
    var input  = document.getElementById('receiptImage');
    var status = document.getElementById('ocrStatus');
    if (!input.files.length) {
      status.textContent = 'Alege mai întâi o imagine.';
      return;
    }
    status.textContent = 'Se extrage textul din imagine…';
    let ocrText;
    try {
      const result = await Tesseract.recognize(input.files[0], 'ron');
      ocrText = result.data.text;
    } catch (err) {
      console.error(err);
      status.textContent = 'Eroare OCR.';
      return;
    }
    status.textContent = 'Se procesează textul…';
    try {
      const resp = await fetch('parse_receipt.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ocr_text: ocrText })
      });
      const json = await resp.json();
      if (!json.success) {
        status.textContent = 'Eroare: ' + json.error;
        return;
      }
      var d = json.data;
      $('input[name="gas_station"]').val(
        d.gas_station + (d.station_city ? ' - ' + d.station_city : '')
      );
      if (d.fuel_type) $('select[name="fuel_type"]').val(d.fuel_type);
      $('input[name="date"]').val(d.date);
      $('input[name="time"]').val(d.time);
      $('input[name="liters"]').val(d.liters);
      $('input[name="price_per_l"]').val(d.price_per_l);
      $('input[name="total_cost"]').val(d.total_cost);
      status.textContent = 'Date extrase cu succes.';
    } catch (err) {
      console.error(err);
      status.textContent = 'Eroare la procesare: ' + err.message;
    }
  });

  // Auto‐calculate: price ↔ liters ↔ total
  var priceInput  = $('input[name="price_per_l"]'),
      totalInput  = $('input[name="total_cost"]'),
      litersInput = $('input[name="liters"]');

  function recalcFields(e) {
    var price  = parseFloat(priceInput.val()),
        total  = parseFloat(totalInput.val()),
        liters = parseFloat(litersInput.val());
    if (e.target.name === 'price_per_l' && !isNaN(price) && !isNaN(liters)) {
      totalInput.val((price * liters).toFixed(2));
    } else if (e.target.name === 'total_cost' && !isNaN(price) && !isNaN(total)) {
      litersInput.val((total / price).toFixed(2));
    } else if (e.target.name === 'liters' && !isNaN(total) && !isNaN(liters)) {
      priceInput.val((total / liters).toFixed(2));
    }
  }
  priceInput.on('input', recalcFields);
  totalInput.on('input', recalcFields);
  litersInput.on('input', recalcFields);


  /* === EDIT SERVICE === */
  // RESET pentru ADD Service
  $('#addServiceBtn').on('click', function() {
    $('#formType').val('add_service');
    $('#serviceId').val('');
    $('#serviceForm')[0].reset();
    $('#serviceModalLabel').text('Adaugă Service');
    $('#submitBtn').text('Adaugă Service');
  });

  // Populate & Show pentru EDIT Service
  $('.editServiceBtn').on('click', function() {
    var tr = $(this).closest('tr');
    $('#formType').val('edit_service');
    $('#serviceId').val(tr.data('id'));
    $('#serviceModalLabel').text('Editează Service');
    $('#submitBtn').text('Salvează Modificări');

    $('#vehicleSelect').val(  tr.data('vehicleId')   );
    $('#dateInput').val(     tr.data('date')        );
    $('#kmInput').val(       tr.data('km')          );
    $('#costInput').val(     tr.data('cost')        );
    $('#centerInput').val(   tr.data('center')      );
    $('#descInput').val(     tr.data('description'));
    $('#notifDate').val(     tr.data('notifDate')   );
    $('#notifKm').val(       tr.data('notifKm')     );
    $('#notifNote').val(     tr.data('notifNote')   );

    $('#serviceModal').modal('show');
  });

  // Close notifications panel on outside click
  $(document).on('click', function(e) {
    var $panel  = $('#notifPanel'),
        $toggle = $('#notifToggle');
    if ($panel.hasClass('show') &&
        !$(e.target).closest('#notifPanel, #notifToggle').length) {
      $panel.collapse('hide');
    }
  });


  /* === TAXE === */

  // Reset modal pentru ADD Taxă
  $('#addTaxBtn').on('click', function(){
    $('#taxFormType').val('add_tax');
    $('#taxForm')[0].reset();
    $('#taxModalLabel').text('Adaugă Taxă');
    $('#taxSubmitBtn').text('Salvează');
    $('#otherTaxTypeGroup').hide();
  });

  // Populate & Show modal pentru EDIT Taxă
  $('.editTaxBtn').on('click', function(){
    var tr = $(this).closest('tr');
    $('#taxFormType').val('edit_tax');
    $('#taxId').val(tr.data('id'));
    $('#taxModalLabel').text('Editează Taxă');
    $('#taxSubmitBtn').text('Salvează Modificări');

    $('#taxVehicleSelect').val(      tr.data('vehicleId') );
    var type = tr.data('type');
    var opts = ['RCA','CASCO','ITP','RAR','Rovinietă','Taxă poluare','Taxă drum','Parcare'];
    if (opts.includes(type)) {
      $('#taxTypeSelect').val(type);
      $('#otherTaxTypeGroup').hide();
    } else {
      $('#taxTypeSelect').val('Altele');
      $('#otherTaxTypeGroup').show();
      $('#taxTypeOtherInput').val(type);
    }

    $('#amountInput').val(           tr.data('amount')    );
    $('#datePaidInput').val(         tr.data('datePaid')  );
    $('#dueDateInput').val(          tr.data('dueDate')   );
    $('#notesInput').val(            tr.data('notes')     );
    $('#addToDocsInput').prop('checked', tr.data('add')==1 );

    $('#taxModal').modal('show');
  });

  // Toggle “Altele” field
  $('#taxTypeSelect').on('change', function(){
    $('#otherTaxTypeGroup').toggle( $(this).val()==='Altele' );
  });

}); // end main $(function())




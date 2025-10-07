<?php
// pages/maps.php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: /login.php");
  exit();
}
?>
<?php include '../includes/header.php'; ?>

<div class="row">
  <?php include '../includes/sidebar.php'; ?>
  <div class="col-md-9 p-0">
    <div id="map"></div>
  </div>
</div>

<!-- Google Maps & Places API (înlocuiește YOUR_API_KEY) -->
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places"></script>
<script>
  let map, service, infoWindow;

  function initMap() {
    // 1. Centrare harta pe poziția utilizatorului (fallback la București)
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(pos => {
        const coords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        loadMap(coords);
      }, () => {
        loadMap({ lat: 44.4268, lng: 26.1025 });
      });
    } else {
      loadMap({ lat: 44.4268, lng: 26.1025 });
    }
  }

  function loadMap(center) {
    map = new google.maps.Map(document.getElementById('map'), {
      center,
      zoom: 13
    });
    infoWindow = new google.maps.InfoWindow();
    // 2. Semn de poziție curentă
    new google.maps.Marker({
      position: center,
      map,
      icon: { url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' },
      title: 'Poziția ta'
    });

    // 3. Caută benzinării
    service = new google.maps.places.PlacesService(map);
    ['gas_station','car_repair'].forEach(type => {
      service.nearbySearch({
        location: center,
        radius: 5000,
        type: [type]
      }, (results, status) => {
        if (status === google.maps.places.PlacesServiceStatus.OK) {
          for (let place of results) createMarker(place, type);
        }
      });
    });
  }

  function createMarker(place, type) {
    if (!place.geometry || !place.geometry.location) return;
    const icons = {
      gas_station: 'https://maps.google.com/mapfiles/ms/icons/red-pushpin.png',
      car_repair:  'https://maps.google.com/mapfiles/ms/icons/green-pushpin.png'
    };
    const marker = new google.maps.Marker({
      map,
      position: place.geometry.location,
      icon: icons[type] || null
    });
    google.maps.event.addListener(marker, 'click', () => {
      infoWindow.setContent(`
        <strong>${place.name}</strong><br>
        ${place.vicinity || ''}<br>
        <em>${type === 'gas_station' ? 'Benzinărie' : 'Service'}</em>
      `);
      infoWindow.open(map, marker);
    });
  }

  // inițializează hartă
  window.onload = initMap;
</script>

<?php include '../includes/footer.php'; ?>

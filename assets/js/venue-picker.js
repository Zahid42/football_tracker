/* Admin: pick a venue's location on a map (click, drag, search, paste a Google Maps link, or use my location). */
(function () {
  'use strict';
  var lat = document.getElementById('f_latitude'), lng = document.getElementById('f_longitude');
  if (!lat || !lng || !window.L) { return; }
  var holder = lat.closest('.col-12, [class*="col-"]');
  var row = holder ? holder.parentNode : lat.parentNode;
  var box = document.createElement('div'); box.className = 'col-12 venue-picker';
  box.innerHTML = '<label class="form-label">Map location</label>' +
    '<div class="input-group input-group-sm mb-2"><input class="form-control" id="vpSearch" placeholder="Search a place or address, or paste a Google Maps link"><button type="button" class="btn btn-outline-theme" id="vpGo"><i class="bi bi-search"></i> Find</button>' +
    '<button type="button" class="btn btn-outline-theme" id="vpMe" title="Use my current location"><i class="bi bi-crosshair"></i></button><button type="button" class="btn btn-outline-danger" id="vpClear" title="Remove the pin"><i class="bi bi-x-lg"></i></button></div>' +
    '<div class="venue-map" id="vpMap"></div><div class="form-text" id="vpMsg">Click the map or drag the pin to set the exact spot.</div>';
  var after = holder && holder.nextElementSibling ? holder.nextElementSibling.nextElementSibling : null;
  row.insertBefore(box, after);
  var msg = document.getElementById('vpMsg');
  var start = [parseFloat(lat.value), parseFloat(lng.value)], has = !isNaN(start[0]) && !isNaN(start[1]);
  var map = L.map('vpMap', { center: has ? start : [23.8103, 90.4125], zoom: has ? 16 : 11 });
  FTMap.tiles(map);
  var marker = null;
  function put(a, b, fly) {
    a = Math.round(a * 1e6) / 1e6; b = Math.round(b * 1e6) / 1e6;
    lat.value = a; lng.value = b;
    if (!marker) {
      marker = L.marker([a, b], { icon: FTMap.pin(), draggable: true }).addTo(map);
      marker.on('dragend', function () { var p = marker.getLatLng(); put(p.lat, p.lng); });
    } else { marker.setLatLng([a, b]); }
    if (fly) { map.setView([a, b], Math.max(map.getZoom(), 16)); }
    msg.textContent = 'Pinned at ' + a + ', ' + b;
  }
  if (has) { put(start[0], start[1]); }
  map.on('click', function (e) { put(e.latlng.lat, e.latlng.lng); });
  function fromLink(t) {
    var m = t.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/) || t.match(/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/) || t.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/) || t.match(/^\s*(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)\s*$/);
    return m ? [parseFloat(m[1]), parseFloat(m[2])] : null;
  }
  function find() {
    var q = document.getElementById('vpSearch').value.trim(); if (!q) { return; }
    var c = fromLink(q); if (c) { put(c[0], c[1], true); return; }
    msg.textContent = 'Searching...';
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); }).then(function (j) { if (j && j[0]) { put(parseFloat(j[0].lat), parseFloat(j[0].lon), true); } else { msg.textContent = 'Nothing found. Try a more specific name, or click the map.'; } })
      .catch(function () { msg.textContent = 'Search is not available right now. Click the map instead.'; });
  }
  document.getElementById('vpGo').addEventListener('click', find);
  document.getElementById('vpSearch').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); find(); } });
  document.getElementById('vpMe').addEventListener('click', function () {
    if (!navigator.geolocation) { msg.textContent = 'Location is not available in this browser.'; return; }
    navigator.geolocation.getCurrentPosition(function (p) { put(p.coords.latitude, p.coords.longitude, true); }, function () { msg.textContent = 'Could not get your location.'; });
  });
  document.getElementById('vpClear').addEventListener('click', function () { lat.value = ''; lng.value = ''; if (marker) { map.removeLayer(marker); marker = null; } msg.textContent = 'Pin removed.'; });
  [lat, lng].forEach(function (i) { i.addEventListener('change', function () { var a = parseFloat(lat.value), b = parseFloat(lng.value); if (!isNaN(a) && !isNaN(b)) { put(a, b, true); } }); });
  setTimeout(function () { map.invalidateSize(); }, 300);
})();

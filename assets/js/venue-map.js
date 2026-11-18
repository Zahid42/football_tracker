/* Premium venue maps (Leaflet + CARTO tiles that follow the site theme). */
(function () {
  'use strict';
  if (!window.L) { return; }
  var TILES = {
    dark: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
    light: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png'
  };
  var ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>';
  function themeKey() { return (document.documentElement.getAttribute('data-theme') || 'dark') === 'dark' ? 'dark' : 'light'; }
  function pin() {
    return L.divIcon({ className: 'venue-pin', html: '<span class="pulse"></span><span class="dot"><i class="bi bi-geo-alt-fill"></i></span>', iconSize: [44, 44], iconAnchor: [22, 40] });
  }
  window.FTMap = {
    tiles: function (map) {
      var layer = L.tileLayer(TILES[themeKey()], { attribution: ATTR, subdomains: 'abcd', maxZoom: 19 }).addTo(map);
      new MutationObserver(function () { layer.setUrl(TILES[themeKey()]); }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
      return layer;
    },
    pin: pin
  };
  function init(el) {
    var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
    if (isNaN(lat) || isNaN(lng)) { return; }
    var map = L.map(el, { center: [lat, lng], zoom: 16, scrollWheelZoom: false, zoomControl: false, attributionControl: true });
    L.control.zoom({ position: 'topright' }).addTo(map);
    FTMap.tiles(map);
    L.marker([lat, lng], { icon: pin(), title: el.dataset.name || '' }).addTo(map);
    map.once('focus', function () { map.scrollWheelZoom.enable(); });
    el.addEventListener('click', function () { map.scrollWheelZoom.enable(); });
    setTimeout(function () { map.invalidateSize(); }, 200);
  }
  function boot() { [].forEach.call(document.querySelectorAll('.venue-map[data-lat]'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();

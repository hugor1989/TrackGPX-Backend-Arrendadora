<!DOCTYPE html>
<html>
<head>
    <title>Rastreo en Vivo - TrackGPX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { margin: 0; font-family: sans-serif; }
        #map { height: 100vh; width: 100vw; }
        .info-card {
            position: absolute; top: 10px; left: 10px; z-index: 1000;
            background: white; padding: 15px; border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2); width: 250px;
        }
    </style>
</head>
<body>
    <div class="info-card">
        <h3 id="v-name">Cargando...</h3>
        <p id="v-plate">Placa: --</p>
        <p>Velocidad: <span id="v-speed">0</span> km/h</p>
        <p><small>Actualizado: <span id="v-time">--</span></small></p>
    </div>

    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const token = "{{ $token }}";
        let map = L.map('map').setView([20.6596, -103.3496], 12);
        let marker;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        async function updateLocation() {
            try {
                const response = await fetch(`/api/public-location/${token}`);
                const data = await response.json();

                if (data.error) {
                    console.error("Link inválido:", data.error);
                    return;
                }

                const lat = data.lat || data.latitude;
                const lng = data.lng || data.longitude;

                if (!lat || !lng) {
                    console.warn("Esperando coordenadas...");
                    document.getElementById('v-name').innerText = (data.name || '') + " (Sin señal...)";
                    return;
                }

                document.getElementById('v-name').innerText = data.name;
                document.getElementById('v-plate').innerText = data.plate || 'S/P';
                document.getElementById('v-speed').innerText = Math.round(data.speed || 0);
                document.getElementById('v-time').innerText = data.last_update;

                const pos = [lat, lng]; // ✅ corregido

                const iconName = data.map_icon || 'car-sport';
                const iconUrl = `https://backend.track-gpx.com.mx/assets/icons/map/${iconName}.png`;

                if (!marker) {
                    marker = L.marker(pos, {
                        icon: L.icon({
                            iconUrl: iconUrl,
                            iconSize: [45, 45],
                            iconAnchor: [22, 22],
                            popupAnchor: [0, -20]
                        })
                    }).addTo(map);
                    map.setView(pos, 16);
                } else {
                    marker.setLatLng(pos);
                    if (marker._icon) {
                        marker._icon.style.transformOrigin = 'center';
                        const currentTransform = marker._icon.style.transform.replace(/rotate\(.*?\)/g, '');
                        marker._icon.style.transform = `${currentTransform} rotate(${data.heading || 0}deg)`;
                    }
                }
            } catch (e) {
                console.error("Error actualizando ubicación", e);
            }
        }

        setInterval(updateLocation, 5000);
        updateLocation();
    </script>
</body>
</html>
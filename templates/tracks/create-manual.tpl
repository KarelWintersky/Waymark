{extends file="layout.tpl"}
{block name=content}
<div class="page-head">
    <h1>Создать трек</h1>
</div>

{if $error|default:null}
    <div class="alert alert--error">{$error|escape}</div>
{/if}

<form method="post" action="/tracks/create/manual" class="track-form">
    <input type="hidden" name="points" id="pointsInput" value="">

    <label class="field">
        <span>Название *</span>
        <input type="text" name="title" required maxlength="255" value="{$old.title|escape}" autofocus>
    </label>

    <label class="field">
        <span>Описание</span>
        <textarea name="description" rows="4">{$old.description|escape}</textarea>
    </label>

    <label class="field">
        <span>Дата записи</span>
        <input type="date" name="date_recorded" value="{$old.date_recorded|escape}">
    </label>

    <div class="field">
        <span>Маршрут *</span>
        <p class="hint">Кликами по карте добавляйте засечки; линия между ними — будущий маршрут.</p>
        <div id="manual-map" class="track-map"></div>
        <div class="track-map-tools">
            <button id="undo-point" class="btn btn--ghost" type="button">Убрать последнюю засечку</button>
            <button id="clear-points" class="btn btn--ghost" type="button">Очистить</button>
            <span id="point-count" class="hint"></span>
        </div>
    </div>

    <div class="track-form__submit">
        <button type="submit" class="btn btn--primary">Создать</button>
        <a class="link-muted" href="/my/tracks">Отмена</a>
    </div>
</form>
{/block}

{block name=scripts}
{if $draft_points_json|default:null}
<script>
window.WAYMARK = {
    points: {$draft_points_json},
    lat:    {$default_lat},
    lon:    {$default_lon},
    zoom:   {$default_zoom}
};
</script>
{/if}
{literal}
<script>
(function () {
    'use strict';

    var data = window.WAYMARK || { points: [], lat: 59.93863, lon: 30.314113, zoom: 11 };
    var map = L.map('manual-map').setView([data.lat, data.lon], data.zoom || 11);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var points = (data.points || []).map(function (p) { return [p.lat, p.lng]; });

    var line = L.polyline(points, { color: '#c0532f', weight: 4, opacity: 0.85 }).addTo(map);
    var dots = L.layerGroup().addTo(map);

    var input = document.getElementById('pointsInput');
    var count = document.getElementById('point-count');
    var undo  = document.getElementById('undo-point');
    var clear = document.getElementById('clear-points');

    var fittedOnce = false;

    function rounded(ll) {
        return { lat: +ll[0].toFixed(6), lng: +ll[1].toFixed(6) };
    }

    function redraw() {
        line.setLatLngs(points);
        dots.clearLayers();

        points.forEach(function (ll, i) {
            L.circleMarker(ll, { radius: 6, color: '#fff', weight: 2, fillColor: '#c0532f', fillOpacity: 1 })
                .addTo(dots).bindTooltip(String(i + 1));
        });

        input.value = JSON.stringify(points.map(rounded));

        var n = points.length;
        count.textContent = n === 0 ? 'засечек пока нет' : 'засечек: ' + n;

        if (n >= 2 && !fittedOnce) {
            map.fitBounds(line.getBounds(), { padding: [30, 30] });
            fittedOnce = true;
        }
    }

    map.on('click', function (e) {
        points.push([e.latlng.lat, e.latlng.lng]);
        redraw();
    });

    undo.addEventListener('click', function () { points.pop(); redraw(); });
    clear.addEventListener('click', function () { points.length = 0; fittedOnce = false; redraw(); });

    redraw();
})();
</script>
{/literal}
{/block}
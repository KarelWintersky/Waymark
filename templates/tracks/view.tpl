{extends file="layout.tpl"}
{block name=content}
<div class="track-view">
    <div class="track-view__head">
        <h1 class="track-view__title">{$track.title|escape}</h1>
        <span class="badge badge--{$track.visibility}">{$visibility_label|escape}</span>
    </div>

    {if $flash|default:null}
        <div class="alert alert--{$flash.type}">{$flash.message|escape}</div>
    {/if}

    <div class="track-view__meta">
        Автор: <a href="/users/{$track.user_id}">{$track.username|escape}</a>
        {if $track.date_recorded} · Запись: {$track.date_recorded}{/if}
        · Источник: {$track.source|upper}
        {if $stats_distance|default:null} · Длина: {$stats_distance} км{/if}
        {if $stats_duration|default:null} · В пути: {$stats_duration}{/if}
        {if $points_count|default:null} · Засечек: {$points_count}{/if}
        {if $photo_points_count|default:0} · Фото на карте: {$photo_points_count}{/if}
    </div>

    {if $track.description|default:''}
        <div class="track-view__desc">{$track.description|escape}</div>
    {/if}

    {if $has_map|default:false}
        <div class="track-map-wrap">
            <div id="track-map" class="track-map"></div>
        </div>
    {else}
        <p class="hint">Для этого трека нет данных геометрии.</p>
    {/if}
</div>
{/block}

{block name=scripts}
{if $has_map|default:false}
<script>
window.WAYMARK = {
    geometry: {$geometry_json|default:'[]'},
    bbox:     {$bbox_json|default:'[null,null,null,null]'},
    lat:      {$default_lat},
    lon:      {$default_lon},
    zoom:     {$default_zoom},
    photoPoints: {$photo_points_json|default:'[]'}
};
</script>
{literal}
<script>
(function () {
    'use strict';

    function esc(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
        });
    }

    var data = window.WAYMARK;

    if (!data.geometry || !data.geometry.length) {
        return;
    }

    var bbox = data.bbox;
    var center = (bbox && bbox.length === 4 && bbox[0] !== null && bbox[1] !== null)
        ? [(bbox[0] + bbox[1]) / 2, (bbox[2] + bbox[3]) / 2]
        : [data.lat, data.lon];

    var map = L.map('track-map').setView(center, data.zoom || 11);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var line = L.polyline(data.geometry.map(function (p) { return [p.lat, p.lng]; }), {
        color: '#c0532f',
        weight: 4,
        opacity: 0.85
    }).addTo(map);

    if (data.geometry.length > 1) {
        var start = data.geometry[0];
        var end   = data.geometry[data.geometry.length - 1];

        L.circleMarker([start.lat, start.lng], { radius: 5, color: '#fff', weight: 2, fillColor: '#2f6f45', fillOpacity: 1 })
            .addTo(map).bindTooltip('Старт');
        L.circleMarker([end.lat, end.lng], { radius: 5, color: '#fff', weight: 2, fillColor: '#a03020', fillOpacity: 1 })
            .addTo(map).bindTooltip('Финиш');
    }

    // Точки фотографий с координатами (кликабельные).
    var photoPoints = data.photoPoints || [];

    photoPoints.forEach(function (point) {
        if (!point || point.lat === undefined || point.lng === undefined) {
            return;
        }

        var url = point.url || '';
        var content = '<figure class="photo-popup">' +
            '<a href="' + esc(url) + '" target="_blank" rel="noopener">' +
            '<img src="' + esc(url) + '" alt="" loading="lazy"></a>' +
            '<figcaption>' +
            (point.name ? '<div class="photo-popup__name">' + esc(point.name) + '</div>' : '') +
            (point.taken_at ? '<div class="photo-popup__taken">' + esc(point.taken_at) + '</div>' : '') +
            '<a class="photo-popup__link" href="' + esc(url) + '" target="_blank" rel="noopener">Открыть оригинал</a>' +
            '</figcaption></figure>';

        L.circleMarker([point.lat, point.lng], {
            radius: 7,
            color: '#ffffff',
            weight: 2,
            fillColor: '#1f6feb',
            fillOpacity: 1
        }).addTo(map)
            .bindTooltip(point.name ? 'Фото: ' + point.name : 'Фото')
            .bindPopup(content, { maxWidth: 340 });
    });

    var bounds = line.getBounds();

    photoPoints.forEach(function (point) {
        if (point && point.lat !== undefined && point.lng !== undefined) {
            bounds.extend([point.lat, point.lng]);
        }
    });

    if (bbox && bbox.length === 4 && bbox[0] !== null && bbox[1] !== null) {
        bounds.extend([[bbox[0], bbox[2]], [bbox[1], bbox[3]]]);
    }

    map.fitBounds(bounds, { padding: [20, 20] });
})();
</script>
{/literal}
{/if}
{/block}
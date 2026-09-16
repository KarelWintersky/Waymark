{extends file="layout.tpl"}
{block name=content}
<div class="track-view">
    <div class="track-view__head">
        <h1 class="track-view__title">{$track.title|escape}</h1>
        <span class="badge badge--{$track.visibility}">{$visibility_label|escape}</span>
    </div>

    <div class="track-view__meta">
        Автор: <a href="/users/{$track.user_id}">{$track.username|escape}</a>
        {if $track.date_recorded} · Запись: {$track.date_recorded}{/if}
        · Источник: {$track.source|upper}
        {if $points_count|default:null} · Точек: {$points_count}{/if}
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
    bbox:     {$bbox_json|default:'[null,null,null,null]'}
};
</script>
{literal}
<script>
(function () {
    'use strict';

    var data = window.WAYMARK;

    if (!data.geometry || !data.geometry.length) {
        return;
    }

    var map = L.map('track-map', { scrollWheelZoom: false });

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

    var bbox = data.bbox;
    if (bbox && bbox.length === 4 && bbox[0] !== null && bbox[1] !== null) {
        map.fitBounds([[bbox[0], bbox[2]], [bbox[1], bbox[3]]]);
    } else {
        map.fitBounds(line.getBounds());
    }
})();
</script>
{/literal}
{/if}
{/block}
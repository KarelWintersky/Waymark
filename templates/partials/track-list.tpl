{if $tracks|default:null}
    {if $heading|default:''}<h2>{$heading}</h2>{/if}
    <div class="track-list">
        {foreach $tracks as $track}
        <a class="track-card" href="/tracks/{$track.id}">
            <div class="track-card__title">{$track.title|escape}</div>
            {if $track.username}<div class="track-card__author">{$track.username|escape}</div>{/if}
            {if $track.date_recorded}<div class="track-card__date">Запись: {$track.date_recorded}</div>{/if}
        </a>
        {/foreach}
    </div>
{else}
    <p class="empty">Публичных треков пока нет. Загрузите первый трек — и он появится на этой странице.</p>
{/if}
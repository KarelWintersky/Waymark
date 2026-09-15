{if $tracks|default:null}
    {if $heading|default:''}<h2>{$heading}</h2>{/if}
    <div class="track-list">
        {foreach $tracks as $track}
        <div class="track-card">
            <a class="track-card__title" href="/tracks/{$track.id}">{$track.title|escape}</a>
            {if $track.username}<div class="track-card__author">Автор: <a href="/users/{$track.user_id}">{$track.username|escape}</a></div>{/if}
            {if $track.date_recorded}<div class="track-card__date">Запись: {$track.date_recorded}</div>{/if}
        </div>
        {/foreach}
    </div>
{else}
    <p class="empty">Публичных треков пока нет. Загрузите первый трек — и он появится на этой странице.</p>
{/if}
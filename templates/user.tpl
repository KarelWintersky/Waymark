{extends file="layout.tpl"}
{block name=content}
<section class="profile">
    <h1>{$user.username|escape}</h1>
    <p class="muted">На Waymark с {$user.created_at}</p>
</section>

{if $tracks}
    <h2>Публичные треки пользователя</h2>
    {include file="partials/track-list.tpl" tracks=$tracks heading='Публичные треки пользователя'}
{else}
    <p class="empty">Пользователь пока не публиковал треков.</p>
{/if}
{/block}
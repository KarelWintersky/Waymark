{extends file="layout.tpl"}
{block name=content}
<div class="page-head">
    <h1>Мои треки</h1>
    <div class="page-head__actions">
        <a class="btn btn--primary" href="/tracks/create/manual">Создать трек</a>
        <a class="btn btn--ghost" href="/tracks/create">Загрузить трек</a>
    </div>
</div>

{if $error|default:null}
    <div class="alert alert--error">{$error|escape}</div>
{/if}

{if $tracks}
    <div class="track-manager">
        {foreach $tracks as $track}
        <div class="track-row">
            <div class="track-row__main">
                <div class="track-row__title">{$track.title|escape}</div>
                <div class="track-row__meta">
                    {if $track.date_recorded}Запись: {$track.date_recorded}{/if}
                    {if $track.source == 'manual'}
                        Создан вручную · создан {$track.created_at}
                    {else}
                        Файл: {$track.source_file|escape} · создан {$track.created_at}
                    {/if}
                </div>
            </div>

            <span class="badge badge--{$track.visibility}">{$track.visibility_label|escape}</span>

            <div class="track-row__actions">
                <a class="btn btn--ghost" href="/tracks/{$track.id}">Показать на карте</a>
                <a class="btn btn--ghost" href="/tracks/{$track.id}/edit">Изменить</a>
                <a class="btn btn--ghost" href="/tracks/{$track.id}/media">Медиа</a>

                {if $track.visibility == 'private'}
                    <form method="post" action="/tracks/{$track.id}/share" class="inline-form">
                        <button type="submit" class="btn btn--ghost">Опубликовать по ссылке</button>
                    </form>
                    <form method="post" action="/tracks/{$track.id}/publish" class="inline-form">
                        <button type="submit" class="btn btn--ghost">Опубликовать</button>
                    </form>
                {elseif $track.visibility == 'protected'}
                    {if $track.share_url}
                        <span class="share-chip">
                            <a href="{$track.share_url}" target="_blank" rel="noopener">{$track.share_url}</a>
                        </span>
                    {/if}
                    <form method="post" action="/tracks/{$track.id}/publish" class="inline-form">
                        <button type="submit" class="btn btn--ghost">Опубликовать</button>
                    </form>
                    <form method="post" action="/tracks/{$track.id}/unpublish" class="inline-form">
                        <button type="submit" class="btn btn--ghost">Сделать приватным</button>
                    </form>
                {else}
                    <form method="post" action="/tracks/{$track.id}/unpublish" class="inline-form">
                        <button type="submit" class="btn btn--ghost">Сделать приватным</button>
                    </form>
                {/if}

                <form method="post" action="/tracks/{$track.id}/delete" class="inline-form"
                      onsubmit="return confirm('Удалить трек «{$track.title|escape:'javascript'}»? Файл будет удалён позже.');">
                    <button type="submit" class="btn btn--danger">Удалить</button>
                </form>
            </div>
        </div>
        {/foreach}
    </div>
{else}
    <p class="empty">Треков пока нет. Создайте первый кликами на карте («Создать трек») или загрузите файл GPX/JSON («Загрузить трек»).</p>
{/if}
{/block}
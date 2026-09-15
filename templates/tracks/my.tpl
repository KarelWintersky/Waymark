{extends file="layout.tpl"}
{block name=content}
<div class="page-head">
    <h1>Мои треки</h1>
    <a class="btn btn--primary" href="/tracks/create">+ Новый трек</a>
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
                    Файл: {$track.source_file|escape} · создан {$track.created_at}
                </div>
            </div>

            <span class="badge badge--{$track.visibility}">{$track.visibility_label|escape}</span>

            <div class="track-row__actions">
                <a class="btn btn--ghost" href="/tracks/{$track.id}/edit">Изменить</a>

                <button class="btn btn--ghost" type="button" disabled title="Появится в задачах 12-13">Опубликовать по ссылке</button>
                <button class="btn btn--ghost" type="button" disabled title="Появится в задачах 12-13">Опубликовать</button>

                <form method="post" action="/tracks/{$track.id}/delete" class="inline-form"
                      onsubmit="return confirm('Удалить трек «{$track.title|escape:'javascript'}»? Файл будет удалён позже.');">
                    <button type="submit" class="btn btn--danger">Удалить</button>
                </form>
            </div>
        </div>
        {/foreach}
    </div>
{else}
    <p class="empty">Треков пока нет. Загрузите первый — кнопка «+ Новый трек».</p>
{/if}
{/block}
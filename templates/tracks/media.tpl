{extends file="layout.tpl"}
{block name=content}
<div class="page-head">
    <h1>Медиа — {$track.title|escape}</h1>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="/tracks/{$track.id}">На карту</a>
        <a class="btn btn--ghost" href="/my/tracks">Мои треки</a>
    </div>
</div>

{if $flash|default:null}
    <div class="alert alert--{$flash.type}">{$flash.message|escape}</div>
{/if}

<section class="media-section">
    <h2>Загрузить фотографии</h2>
    <form class="media-upload" method="post" enctype="multipart/form-data" action="/tracks/{$track.id}/media">
        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png">
        <button type="submit" class="btn btn--primary">Загрузить</button>
        <p class="hint">JPEG / PNG, до {$upload_max_images|default:10} шт. на трек. Из EXIF считываются время съёмки, координаты и направление взгляда.</p>
    </form>
</section>

{if $media}
    <section class="media-section">
        <h2>Фотографии ({$media|@count})</h2>
        <div class="media-table-wrap">
            <table class="media-table">
                <thead>
                <tr>
                    <th class="media-table__thumb"></th>
                    <th>Название</th>
                    <th>Размер</th>
                    <th>Координаты</th>
                    {*<th>Направление</th>*}
                    <th>Снято</th>
                    {*<th>Описание</th>*}
                    <th class="media-table__actions"></th>
                </tr>
                </thead>
                <tbody>
                {foreach $media as $photo}
                <tr>
                    <td class="media-table__thumb">
                        <a href="/storage/media/{$photo.file_path}">
                            <img class="media-thumb" src="/storage/media/{$photo.file_path}" alt="{$photo.original_name|escape}" loading="lazy">
                        </a>
                    </td>
                    <td class="media-table__name" title="{$photo.original_name|escape}">{$photo.original_name|escape}</td>
                    <td class="media-table__num">{$photo.size_human}</td>
                    <td>
                        {if $photo.latitude !== null && $photo.longitude !== null}
                            <span class="media-card__coords">{$photo.latitude|string_format:"%.6f"}, {$photo.longitude|string_format:"%.6f"}</span>
                        {else}—{/if}
                    </td>
                    {*<td>{if $photo.direction !== null}{$photo.direction}°{else}—{/if}</td>*}
                    <td>{$photo.taken_at|default:'—'}</td>
                    {*<td>
                        <form method="post" action="/tracks/{$track.id}/media/{$photo.id}/description" class="media-desc-form">
                            <input type="text" name="description" value="{$photo.description|escape}" placeholder="Описание…" maxlength="500">
                            <button type="submit" class="btn btn--ghost">Сохранить</button>
                        </form>
                    </td>*}
                    <td class="media-table__actions">
                        <form method="post" action="/tracks/{$track.id}/media/{$photo.id}/delete" class="inline-form"
                              onsubmit="return confirm('Удалить фото «{$photo.original_name|escape:'javascript'}»?');">
                            <button type="submit" class="btn btn--danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </section>
{else}
    <p class="empty">Фотографий пока нет. Загрузите первые через форму выше.</p>
{/if}
{/block}
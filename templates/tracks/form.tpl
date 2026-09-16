{extends file="layout.tpl"}
{block name=content}
<div class="page-head">
    <h1>{if $mode === 'edit'}Редактирование трека{else}Загрузить трек{/if}</h1>
</div>

{if $error|default:null}
    <div class="alert alert--error">{$error|escape}</div>
{/if}

<form method="post" enctype="multipart/form-data" class="track-form"
      action="{if $mode === 'edit'}/tracks/{$track.id}/edit{else}/tracks/create{/if}">

    <label class="field">
        <span>Название *</span>
        <input type="text" name="title" required maxlength="255" value="{$old.title|escape}" autofocus>
    </label>

    <label class="field">
        <span>Описание</span>
        <textarea name="description" rows="5">{$old.description|escape}</textarea>
    </label>

    <label class="field">
        <span>Дата записи</span>
        <input id="dateRecorded" type="date" name="date_recorded" value="{$old.date_recorded|escape}">
        <label class="checkbox">
            <input id="dateFromFile" type="checkbox" name="date_from_file" value="1"
                   {if $old.date_from_file}checked{/if}>
            Взять из файла
        </label>
    </label>

    <label class="field">
        <span>Файл трека {if $mode !== 'edit'}*{/if}</span>
        <input type="file" name="source_file" accept=".gpx,.json"
               {if $mode !== 'edit'}required{/if}>
        {if $mode === 'edit' && $track.source == 'manual'}
            <small class="hint">Трек создан вручную, файла нет. Если загрузить файл — геометрия будет заменена.</small>
        {elseif $mode === 'edit'}
            <small class="hint">Сейчас: {$track.source_file|escape}. Если новый файл не выбран — останется прежний.</small>
        {/if}
    </label>

    <div class="track-form__submit">
        <button type="submit" class="btn btn--primary">
            {if $mode === 'edit'}Сохранить{else}Загрузить{/if}
        </button>
        <a class="link-muted" href="/my/tracks">Отмена</a>
    </div>
</form>

{literal}
<script>
(function () {
    var dateInput = document.getElementById('dateRecorded');
    var fromFile  = document.getElementById('dateFromFile');
    if (!dateInput || !fromFile) return;
    var sync = function () { dateInput.disabled = fromFile.checked; };
    fromFile.addEventListener('change', sync);
    sync();
})();
</script>
{/literal}
{/block}
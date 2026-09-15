{extends file="layout.tpl"}
{block name=content}
<h1>Ошибка сервера</h1>
<p class="empty">Что-то пошло не так. Попробуйте позже.</p>
{if $debug_message|default:''}
<p class="debug-message">{$debug_message|escape}</p>
{/if}
{/block}
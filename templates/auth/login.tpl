{extends file="layout.tpl"}
{block name=content}
<div class="auth-card">
    <h1>Вход</h1>

    {if $error|default:null}
        <div class="alert alert--error">{$error|escape}</div>
    {/if}

    <form method="post" action="/login" class="auth-form">
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" required autofocus value="{$email|default:''|escape}">
        </label>
        <label class="field">
            <span>Пароль</span>
            <input type="password" name="password" required>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="remember" value="1"> Запомнить меня
        </label>
        <button type="submit" class="btn btn--primary">Войти</button>
    </form>

    <p class="auth-hint"><a href="/password/forgot">Забыли пароль?</a></p>
</div>
{/block}
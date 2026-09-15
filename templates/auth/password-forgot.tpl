{extends file="layout.tpl"}
{block name=content}
<div class="auth-card">
    <h1>Сброс пароля</h1>

    <div class="alert alert--info">
        Восстановление пароля пока недоступно. Обратитесь к администратору,
        чтобы получить новый пароль.
    </div>

    <form method="post" action="/password/forgot" class="auth-form">
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="" autocomplete="off" disabled>
        </label>
        <button type="submit" class="btn btn--primary" disabled title="Восстановление появится позже">Выслать ссылку для сброса</button>
    </form>

    <p class="auth-hint"><a href="/login">← Вернуться ко входу</a></p>
</div>
{/block}
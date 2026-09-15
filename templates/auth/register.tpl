{extends file="layout.tpl"}
{block name=content}
<div class="auth-card">
    <h1>Регистрация</h1>

    <div class="alert alert--info">
        Открытая регистрация пока недоступна. Аккаунты создаёт администратор
        через скрипт <code>admin/auth.register.php</code>.
    </div>

    <form method="post" action="/register" class="auth-form">
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="" autocomplete="off" disabled>
        </label>
        <label class="field">
            <span>Имя</span>
            <input type="text" name="username" value="" autocomplete="off" disabled>
        </label>
        <label class="field">
            <span>Пароль</span>
            <input type="password" name="password" autocomplete="new-password" disabled>
        </label>
        <button type="submit" class="btn btn--primary" disabled title="Регистрация откроется позже">Зарегистрироваться</button>
    </form>
</div>
{/block}
<div class="mx-auto max-w-md">
    <div class="card p-8">
        <h1 class="text-2xl">Connexion</h1>
        <p class="mt-1 text-sm text-slate-500">Content de vous revoir au Caddistan.</p>

        <form action="/connexion" method="post" class="mt-6 space-y-4">
            <div>
                <label for="email" class="label">Adresse e-mail</label>
                <input id="email" name="email" type="email" class="input" required autocomplete="email" value="{{ old('email') }}">
            </div>
            <div>
                <label for="password" class="label">Mot de passe</label>
                <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-primary w-full py-2.5">Se connecter</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Pas encore de compte ? <a href="/inscription" class="btn-link">Inscrivez-vous</a>
        </p>
    </div>
</div>

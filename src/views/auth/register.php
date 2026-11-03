<div class="mx-auto max-w-md">
    <div class="card p-8">
        <h1 class="text-2xl">Créer un compte</h1>
        <p class="mt-1 text-sm text-slate-500">Rejoignez le peuple du Caddistan.</p>

        <form action="/inscription" method="post" class="mt-6 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="label">Prénom</label>
                    <input id="first_name" name="first_name" class="input" required autocomplete="given-name" value="<?= old('first_name') ?>">
                </div>
                <div>
                    <label for="last_name" class="label">Nom</label>
                    <input id="last_name" name="last_name" class="input" required autocomplete="family-name" value="<?= old('last_name') ?>">
                </div>
            </div>
            <div>
                <label for="email" class="label">Adresse e-mail</label>
                <input id="email" name="email" type="email" class="input" required autocomplete="email" value="<?= old('email') ?>">
            </div>
            <div>
                <label for="password" class="label">Mot de passe</label>
                <input id="password" name="password" type="password" class="input" required minlength="8" autocomplete="new-password">
                <p class="mt-1 text-xs text-slate-400">8 caractères minimum.</p>
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirmation</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn-primary w-full py-2.5">Créer mon compte</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Déjà inscrit ? <a href="/connexion" class="btn-link">Connectez-vous</a>
        </p>
    </div>
</div>

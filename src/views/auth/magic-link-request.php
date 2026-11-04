<div class="mx-auto max-w-md">
    <div class="card p-8">
        <h1 class="text-2xl">Connexion sans mot de passe</h1>
        <p class="mt-1 text-sm text-slate-500">Recevez un lien de connexion par e-mail. Il est valable 15 minutes et ne sert qu'une seule fois.</p>

        <form action="/connexion/sans-mot-de-passe" method="post" class="mt-6 space-y-4">
            {!! csrf_field() !!}
            <div>
                <label for="email" class="label">Adresse e-mail</label>
                <input id="email" name="email" type="email" class="input" required autocomplete="email" value="{{ old('email') }}">
            </div>
            <button type="submit" class="btn-primary w-full py-2.5">Recevoir le lien</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="/connexion" class="btn-link">Se connecter avec un mot de passe</a>
        </p>
    </div>
</div>

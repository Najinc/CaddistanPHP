<div class="mx-auto max-w-md">
    <div class="card p-8 text-center">
        <h1 class="text-2xl">Connexion</h1>
        <p class="mt-1 text-sm text-slate-500">Ce lien ne fonctionne qu'une seule fois.</p>

        <form action="{{ $action }}" method="post" class="mt-6">
            {!! csrf_field() !!}
            <button type="submit" class="btn-primary w-full py-2.5">Se connecter</button>
        </form>
    </div>
</div>

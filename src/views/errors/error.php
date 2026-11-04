<?php /** @var int $status @var string $message @var ?string $reference @var ?Throwable $exception */ ?>
<div class="mx-auto max-w-xl py-16 text-center">
    <p class="text-7xl font-extrabold text-brand-600">{{ (int) $status }}</p>
    <h1 class="mt-4 text-2xl">{{ $message ?: 'Une erreur est survenue' }}</h1>
    <p class="mt-2 text-slate-500">Ce caddy a dû prendre un mauvais rayon.</p>
    <?php if (!empty($reference)): ?>
        <p class="mt-4 text-sm text-slate-500">Référence de l'incident : <code class="font-mono">{{ $reference }}</code></p>
    <?php endif ?>
    <a href="/" class="btn-primary mt-8">Retour à l'accueil</a>
</div>

<?php if ($exception): ?>
    <pre class="card overflow-x-auto p-4 text-left text-xs text-red-700">{{ get_class($exception) . ': ' . $exception->getMessage() . "\n\n" . $exception->getTraceAsString() }}</pre>
<?php endif ?>

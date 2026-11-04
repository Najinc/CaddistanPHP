<?php
use App\Mail\Mailer;

$mails = Mailer::mailbox()->latest();
?>
<details class="fixed bottom-4 right-4 z-40" data-mail-widget>
    <summary class="relative flex h-12 w-12 cursor-pointer list-none items-center justify-center rounded-full bg-ink text-white shadow-lg hover:bg-slate-700 [&::-webkit-details-marker]:hidden"
             aria-label="Boîte mail de test, {{ count($mails) }} message(s)">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="3" y="5" width="18" height="14" rx="2"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/>
        </svg>
        <?php if ($mails !== []): ?>
            <span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-brand-600 px-1 text-center text-xs font-bold leading-5 text-white">{{ count($mails) }}</span>
        <?php endif ?>
    </summary>

    <div class="card absolute bottom-14 right-0 max-h-[70vh] w-[min(26rem,calc(100vw-2rem))] overflow-y-auto">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
            <div>
                <p class="font-bold text-ink">Boîte mail de test</p>
                <p class="text-xs text-slate-500">Les e-mails envoyés par le site. Absente en production.</p>
            </div>
            <?php if ($mails !== []): ?>
                <form action="/dev/mails/vider" method="post">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn-link">Vider</button>
                </form>
            <?php endif ?>
        </div>

        <?php if ($mails === []): ?>
            <p class="px-4 py-6 text-center text-sm text-slate-500">Aucun e-mail pour l'instant. Demandez un lien de connexion ou passez une commande.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-200">
                <?php foreach ($mails as $mail): ?>
                    <li class="space-y-2 px-4 py-3" data-mail>
                        <div class="flex items-baseline justify-between gap-3 text-xs text-slate-500">
                            <span class="truncate">À : {{ $mail->to }}</span>
                            <time class="shrink-0">{{ date('H:i:s', (int) $mail->sentAt) }}</time>
                        </div>
                        <p class="font-semibold text-ink">{{ $mail->subject }}</p>
                        <p class="whitespace-pre-line text-sm text-slate-600">{{ $mail->body }}</p>
                        <?php if ($mail->link !== null): ?>
                            <a href="{{ $mail->link }}" class="btn-primary">{{ $mail->linkLabel ?? 'Ouvrir le lien' }}</a>
                            <p class="select-all break-all font-mono text-xs text-slate-500">{{ $mail->link }}</p>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</details>

<?php /** @var float|int $rating */ ?>
<span class="inline-flex text-amber-400" aria-label="{{ $rating }} sur 5">
    <?php for ($i = 1; $i <= 5; $i++): ?>
        <svg class="h-4 w-4 {{ $i <= round($rating) ? '' : 'text-slate-200' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9z"/>
        </svg>
    <?php endfor ?>
</span>

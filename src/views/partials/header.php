<?php
use App\Core\Auth;
use App\Services\Cart;

$cartCount = Cart::count();
?>
<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="container-page flex h-16 items-center gap-4">
        <a href="/" class="flex shrink-0 items-center gap-2 text-xl font-extrabold tracking-tight text-ink">
            <img src="/images/logo.svg" alt="" class="h-8 w-8">
            Caddistan
        </a>

        <nav class="hidden items-center gap-1 md:flex">
            <a href="/produits" class="nav-link {{ is_current('/produits') ? 'is-active' : '' }}">Catalogue</a>
            <a href="/produits?categorie=supermarche" class="nav-link">Supermarché</a>
            <a href="/produits?categorie=golf" class="nav-link">Golf</a>
            <a href="/produits?categorie=logistique" class="nav-link">Logistique</a>
        </nav>

        <form action="/produits" method="get" class="relative ml-auto hidden w-full max-w-xs sm:block" data-live-search>
            <input type="search" name="q" placeholder="Rechercher un caddy…" autocomplete="off"
                   class="input pr-3" aria-label="Rechercher" data-live-search-input>
            <div class="absolute inset-x-0 top-full mt-1 hidden overflow-hidden card" data-live-search-results></div>
        </form>

        <div class="ml-auto flex items-center gap-1 sm:ml-0">
            <a href="/panier" class="nav-link relative {{ is_current('/panier') ? 'is-active' : '' }}" aria-label="Panier">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l2.4 12.2a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.8L21 7H6"/>
                    <circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>
                </svg>
                <span class="absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-brand-600 px-1 text-center text-xs font-bold leading-5 text-white {{ $cartCount ? '' : 'hidden' }}"
                      data-cart-count>{{ $cartCount }}</span>
            </a>

            <?php if (Auth::check()): ?>
                <?php if (Auth::can('admin.dashboard')): ?>
                    <a href="/admin" class="nav-link {{ is_current('/admin') ? 'is-active' : '' }}">Admin</a>
                <?php endif ?>
                <a href="/compte" class="nav-link {{ is_current('/compte') ? 'is-active' : '' }}">{{ Auth::user()['first_name'] }}</a>
                <form action="/deconnexion" method="post">
                    {!! csrf_field() !!}
                    <button type="submit" class="nav-link cursor-pointer">Déconnexion</button>
                </form>
            <?php else: ?>
                <a href="/connexion" class="nav-link">Connexion</a>
                <a href="/inscription" class="btn-primary hidden sm:inline-flex">Créer un compte</a>
            <?php endif ?>
        </div>
    </div>
</header>

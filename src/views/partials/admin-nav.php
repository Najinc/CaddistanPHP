<nav class="mb-8 flex flex-wrap gap-1 border-b border-slate-200 pb-3">
    <a href="/admin" class="nav-link {{ ($_SERVER['REQUEST_URI'] ?? '') === '/admin' ? 'is-active' : '' }}">Tableau de bord</a>
    <a href="/admin/produits" class="nav-link {{ is_current('/admin/produits') ? 'is-active' : '' }}">Produits</a>
    <a href="/admin/commandes" class="nav-link {{ is_current('/admin/commandes') ? 'is-active' : '' }}">Commandes</a>
    <a href="/admin/utilisateurs" class="nav-link {{ is_current('/admin/utilisateurs') ? 'is-active' : '' }}">Utilisateurs</a>
</nav>

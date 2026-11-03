<?php /** @var array $products */ ?>
<?= partial('admin-nav') ?>
<div class="flex items-center justify-between">
    <h1>Produits</h1>
    <a href="/admin/produits/nouveau" class="btn-primary">Nouveau produit</a>
</div>

<div class="card mt-6 overflow-x-auto">
    <table class="table">
        <thead><tr><th></th><th>Nom</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>Statut</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><img src="<?= $product['image'] ?: '/images/products/default.svg' ?>" alt="" class="h-10 w-10 rounded bg-slate-100 object-contain p-0.5"></td>
                    <td class="font-medium text-ink"><?= $product['name'] ?></td>
                    <td><?= $product['category_name'] ?></td>
                    <td><?= money((int) $product['price_cents']) ?></td>
                    <td><?= $product['stock'] ?></td>
                    <td>
                        <?php if ($product['is_active']): ?>
                            <span class="badge bg-emerald-100 text-emerald-800">Actif</span>
                        <?php else: ?>
                            <span class="badge bg-slate-200 text-slate-600">Masqué</span>
                        <?php endif ?>
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <a href="/admin/produits/<?= $product['id'] ?>/modifier" class="btn-link">Modifier</a>
                        <form action="/admin/produits/<?= $product['id'] ?>/supprimer" method="post" class="ml-3 inline"
                              data-confirm="Supprimer « <?= $product['name'] ?> » ?">
                            <button type="submit" class="btn-link text-red-600">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?php
/** @var ?array $product @var array $categories */
$value = fn (string $key, mixed $default = '') => old($key, (string) ($product[$key] ?? $default));
$action = $product ? '/admin/produits/' . $product['id'] : '/admin/produits';
?>
{!! partial('admin-nav') !!}
<a href="/admin/produits" class="btn-link">&larr; Produits</a>
<h1 class="mt-2">{{ $product ? 'Modifier « ' . $product['name'] . ' »' : 'Nouveau produit' }}</h1>

<form action="{{ $action }}" method="post" enctype="multipart/form-data" class="mt-6 grid gap-8 lg:grid-cols-[1fr_320px]">
    {!! csrf_field() !!}
    <div class="card space-y-4 p-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="label">Nom</label>
                <input id="name" name="name" class="input" required value="{{ $value('name') }}">
            </div>
            <div>
                <label for="slug" class="label">Slug <span class="font-normal text-slate-400">(auto si vide)</span></label>
                <input id="slug" name="slug" class="input" value="{{ $value('slug') }}">
            </div>
        </div>
        <div>
            <label for="short_description" class="label">Accroche</label>
            <input id="short_description" name="short_description" class="input" value="{{ $value('short_description') }}">
        </div>
        <div>
            <label for="description" class="label">Description</label>
            <textarea id="description" name="description" rows="6" class="input">{{ $value('description') }}</textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="category_id" class="label">Catégorie</label>
                <select id="category_id" name="category_id" class="input">
                    <?php foreach ($categories as $category): ?>
                        <option value="{{ $category['id'] }}" {{ $value('category_id') == $category['id'] ? 'selected' : '' }}>{{ $category['name'] }}</option>
                    <?php endforeach ?>
                </select>
            </div>
            <div>
                <label for="price" class="label">Prix (€)</label>
                <input id="price" name="price" class="input" required inputmode="decimal"
                       value="{{ old('price', $product ? number_format($product['price_cents'] / 100, 2, '.', '') : '') }}">
            </div>
            <div>
                <label for="stock" class="label">Stock</label>
                <input id="stock" name="stock" type="number" class="input" value="{{ $value('stock', 0) }}">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" {{ ($product['is_active'] ?? 1) ? 'checked' : '' }}>
            Visible dans le catalogue
        </label>
    </div>

    <div class="space-y-4">
        <div class="card space-y-4 p-6">
            <h2 class="text-lg">Image</h2>
            <?php if ($product && $product['image']): ?>
                <img src="{{ $product['image'] }}" alt="" class="h-40 w-full rounded-lg bg-slate-100 object-contain p-2">
            <?php endif ?>
            <div>
                <label for="image" class="label">Envoyer un fichier</label>
                <input id="image" name="image" type="file" accept="image/*" class="block w-full text-sm">
            </div>
            <div>
                <label for="image_url" class="label">… ou importer depuis une URL</label>
                <input id="image_url" name="image_url" type="url" class="input" placeholder="https://exemple.com/caddy.png">
            </div>
        </div>
        <button type="submit" class="btn-primary w-full py-3">{{ $product ? 'Enregistrer' : 'Créer le produit' }}</button>
    </div>
</form>

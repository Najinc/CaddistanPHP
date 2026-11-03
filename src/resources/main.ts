import './style.css';

/** Recherche instantanée dans l'en-tête (appel à l'API JSON /api/produits). */
function initLiveSearch(): void {
    const form = document.querySelector<HTMLFormElement>('[data-live-search]');
    const input = form?.querySelector<HTMLInputElement>('[data-live-search-input]');
    const results = form?.querySelector<HTMLElement>('[data-live-search-results]');
    if (!form || !input || !results) return;

    let timer: number | undefined;

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(async () => {
            const q = input.value.trim();
            if (q === '') {
                results.classList.add('hidden');
                return;
            }

            const response = await fetch(`/api/produits?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
            });
            const { data } = (await response.json()) as {
                data: { name: string; price: string; image: string; category: string; url: string }[];
            };

            results.innerHTML = data.length
                ? data.map((p) => `
                    <a href="${p.url}" class="flex items-center gap-3 px-3 py-2 text-sm hover:bg-slate-50">
                        <img src="${p.image}" alt="" class="h-10 w-10 rounded bg-slate-100 object-contain p-1">
                        <span class="flex-1"><span class="block font-medium text-ink">${p.name}</span>
                        <span class="text-xs text-slate-500">${p.category}</span></span>
                        <span class="font-semibold">${p.price}</span>
                    </a>`).join('')
                : '<p class="px-3 py-2 text-sm text-slate-500">Aucun résultat</p>';
            results.classList.remove('hidden');
        }, 200);
    });

    document.addEventListener('click', (event) => {
        if (!form.contains(event.target as Node)) results.classList.add('hidden');
    });
}

/** Ajout au panier sans rechargement depuis les cartes produit. */
function initAddToCart(): void {
    document.querySelectorAll<HTMLFormElement>('[data-add-to-cart]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button');
            button?.setAttribute('disabled', '');

            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
            });
            const json = (await response.json()) as { count?: number };

            const badge = document.querySelector<HTMLElement>('[data-cart-count]');
            if (badge && json.count !== undefined) {
                badge.textContent = String(json.count);
                badge.classList.remove('hidden');
            }

            if (button) {
                button.textContent = 'Ajouté ✓';
                window.setTimeout(() => {
                    button.textContent = 'Ajouter';
                    button.removeAttribute('disabled');
                }, 1200);
            }
        });
    });
}

/** Confirmation avant soumission (ex. suppression d'un produit). */
function initConfirm(): void {
    document.querySelectorAll<HTMLFormElement>('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm ?? 'Confirmer ?')) event.preventDefault();
        });
    });
}

/** Soumet automatiquement le formulaire quand un select change (tri, filtres, rôles). */
function initAutoSubmit(): void {
    document.querySelectorAll<HTMLSelectElement>('select[data-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => select.form?.submit());
    });
}

/** Fermeture des messages flash. */
function initDismiss(): void {
    document.querySelectorAll<HTMLElement>('[data-dismiss]').forEach((button) => {
        button.addEventListener('click', () => button.closest('[data-dismissible]')?.remove());
    });
}

initLiveSearch();
initAddToCart();
initConfirm();
initAutoSubmit();
initDismiss();

<?php /** @var array $users @var array $roles */ ?>
<?= partial('admin-nav') ?>
<h1>Utilisateurs</h1>

<div class="card mt-6 overflow-x-auto">
    <table class="table">
        <thead><tr><th>#</th><th>Nom</th><th>E-mail</th><th>Inscrit le</th><th>Commandes</th><th>Rôle</th></tr></thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td class="font-medium text-ink"><?= $user['first_name'] . ' ' . $user['last_name'] ?></td>
                    <td><?= $user['email'] ?></td>
                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                    <td><?= $user['orders_count'] ?></td>
                    <td>
                        <form action="/admin/utilisateurs/<?= $user['id'] ?>/role" method="post" class="flex items-center gap-2">
                            <select name="role" class="input w-auto py-1" data-autosubmit>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= $role === 'admin' ? 'Administrateur' : 'Client' ?></option>
                                <?php endforeach ?>
                            </select>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

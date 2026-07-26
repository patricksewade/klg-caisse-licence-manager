<?php
declare(strict_types=1);
/**
 * KLG-Caisse License Manager — Vue : Liste de toutes les licences
 * ================================================================
 * Pilier 4 : Vue filtrée du parc complet des licences V2.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/core/database/db_functions.php';
require_once __DIR__ . '/layout.php';

// Filtre de statut
$filtre = isset($_GET['statut']) && in_array($_GET['statut'], ['ACTIVE','EXPIREE','REVOQUEE','MIGREE','EN_ATTENTE'], true)
    ? $_GET['statut']
    : null;

$licences = klg_db_get_all_licences($filtre);

function klg_badge_statut_list(string $statut): string
{
    $map = [
        'ACTIVE'    => ['badge--active',   '✅', 'Active'],
        'EXPIREE'   => ['badge--expired',  '⚠️', 'Expirée'],
        'REVOQUEE'  => ['badge--revoked',  '🚫', 'Révoquée'],
        'MIGREE'    => ['badge--migrated', '🔄', 'Migrée'],
        'EN_ATTENTE'=> ['badge--pending',  '⏳', 'En attente'],
    ];
    [$class, $icon, $label] = $map[$statut] ?? ['badge--pending', '❓', $statut];
    return '<span class="badge ' . $class . '">' . $icon . ' ' . $label . '</span>';
}

klg_layout_header('Toutes les licences V2', 'list_licences', 'Parc complet des licences émises');
?>

<!-- Filtres de statut -->
<div class="flex-center gap-8 mb-24" style="flex-wrap:wrap;">
    <a href="?page=list_licences" class="btn btn--sm <?= $filtre === null ? 'btn--primary' : 'btn--ghost' ?>">Toutes</a>
    <a href="?page=list_licences&statut=ACTIVE"    class="btn btn--sm <?= $filtre === 'ACTIVE' ? 'btn--primary' : 'btn--ghost' ?>">✅ Actives</a>
    <a href="?page=list_licences&statut=EXPIREE"   class="btn btn--sm <?= $filtre === 'EXPIREE' ? 'btn--danger' : 'btn--ghost' ?>">⚠️ Expirées</a>
    <a href="?page=list_licences&statut=REVOQUEE"  class="btn btn--sm <?= $filtre === 'REVOQUEE' ? 'btn--orange' : 'btn--ghost' ?>">🚫 Révoquées</a>
    <div style="margin-left:auto;">
        <a href="?page=create_licence" class="btn btn--primary btn--sm">+ Émettre une licence</a>
    </div>
</div>

<div class="panel">
    <div class="panel__header">
        <div>
            <div class="panel__title">📋 Liste des licences<?= $filtre ? ' — ' . $filtre : '' ?></div>
            <div class="panel__subtitle"><?= count($licences) ?> licence(s) trouvée(s)</div>
        </div>
    </div>
    <div class="table-wrapper">
        <?php if (empty($licences)): ?>
            <div class="panel__body text-center text-muted">Aucune licence trouvée pour ce filtre.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Expiration</th>
                    <th>Émission</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($licences as $lic): ?>
                <tr>
                    <td><code><?= htmlspecialchars($lic['cle_licence']) ?></code></td>
                    <td>
                        <strong><?= htmlspecialchars($lic['nom_prenom']) ?></strong>
                        <?php if (!empty($lic['entreprise'])): ?>
                            <br><span class="text-muted"><?= htmlspecialchars($lic['entreprise']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge--v2"><?= htmlspecialchars($lic['type_licence']) ?></span></td>
                    <td><?= klg_badge_statut_list($lic['statut']) ?></td>
                    <td class="text-muted">
                        <?= !empty($lic['date_expiration']) ? date('d/m/Y', strtotime($lic['date_expiration'])) : '<em>Perpétuelle</em>' ?>
                    </td>
                    <td class="text-muted"><?= date('d/m/Y', strtotime($lic['date_emission'])) ?></td>
                    <td>
                        <?php if ($lic['statut'] === 'ACTIVE'): ?>
                            <a href="?page=revoke_licence&cle=<?= urlencode($lic['cle_licence']) ?>" class="btn btn--sm btn--ghost">Révoquer</a>
                        <?php endif; ?>
                        <a href="?page=create_licence&client_id=<?= (int)$lic['client_id'] ?>" class="btn btn--sm btn--primary">Renouveler</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php
klg_layout_footer();

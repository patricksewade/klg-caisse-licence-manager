<?php
declare(strict_types=1);
/**
 * KLG-Caisse License Manager — Vue : Tableau de Bord
 * ====================================================
 * Pilier 4 : Vue 360° du parc client, statistiques,
 * alertes d'expiration et journal d'audit récent.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/core/database/db_functions.php';
require_once dirname(__DIR__) . '/core/audit/audit_functions.php';
require_once __DIR__ . '/layout.php';

// -- Récupération des données --
$stats           = klg_db_get_statistiques();
$alertes         = klg_db_get_licences_expirant_bientot(30);
$audit_recent    = klg_audit_get_recent(10);
$licences_recent = klg_db_get_all_licences();
$licences_recent = array_slice($licences_recent, 0, 8); // Les 8 plus récentes

// -- Libellés et icônes des statuts --
function klg_badge_statut(string $statut): string
{
    $map = [
        'ACTIVE'    => ['class' => 'badge--active',   'icon' => '✅', 'label' => 'Active'],
        'EXPIREE'   => ['class' => 'badge--expired',  'icon' => '⚠️', 'label' => 'Expirée'],
        'REVOQUEE'  => ['class' => 'badge--revoked',  'icon' => '🚫', 'label' => 'Révoquée'],
        'MIGREE'    => ['class' => 'badge--migrated', 'icon' => '🔄', 'label' => 'Migrée'],
        'EN_ATTENTE'=> ['class' => 'badge--pending',  'icon' => '⏳', 'label' => 'En attente'],
    ];
    $info = $map[$statut] ?? ['class' => 'badge--pending', 'icon' => '❓', 'label' => $statut];
    return '<span class="badge ' . $info['class'] . '">' . $info['icon'] . ' ' . $info['label'] . '</span>';
}

klg_layout_header('Tableau de bord', 'dashboard', 'Vue d\'ensemble du parc de licences KLG-Caisse');
?>

<!-- Statistiques rapides -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--blue">📋</div>
        <div>
            <div class="stat-card__value"><?= $stats['total_licences'] ?></div>
            <div class="stat-card__label">Licences V2 émises</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--green">✅</div>
        <div>
            <div class="stat-card__value"><?= $stats['actives'] ?></div>
            <div class="stat-card__label">Licences actives</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--red">⚠️</div>
        <div>
            <div class="stat-card__value"><?= $stats['expirees'] ?></div>
            <div class="stat-card__label">Licences expirées</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--orange">👥</div>
        <div>
            <div class="stat-card__value"><?= $stats['total_clients'] ?></div>
            <div class="stat-card__label">Clients enregistrés</div>
        </div>
    </div>
</div>

<?php if (!empty($alertes)): ?>
<!-- Alertes d'expiration -->
<div class="panel mb-24">
    <div class="panel__header">
        <div>
            <div class="panel__title">🔔 Licences expirant dans les 30 prochains jours</div>
            <div class="panel__subtitle"><?= count($alertes) ?> licence(s) concernée(s)</div>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Expiration</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alertes as $alerte): ?>
                <tr>
                    <td><code><?= htmlspecialchars($alerte['cle_licence']) ?></code></td>
                    <td><?= htmlspecialchars($alerte['nom_prenom']) ?></td>
                    <td><?= htmlspecialchars($alerte['type_licence']) ?></td>
                    <td><?= !empty($alerte['date_expiration']) ? date('d/m/Y', strtotime($alerte['date_expiration'])) : '—' ?></td>
                    <td>
                        <a href="?page=create_licence&client_id=<?= (int)$alerte['client_id'] ?>" class="btn btn--sm btn--primary">Renouveler</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

    <!-- Dernières licences émises -->
    <div class="panel">
        <div class="panel__header">
            <div>
                <div class="panel__title">🔑 Dernières licences émises</div>
            </div>
            <a href="?page=list_licences" class="btn btn--sm btn--ghost">Voir tout</a>
        </div>
        <div class="table-wrapper">
            <?php if (empty($licences_recent)): ?>
                <div class="panel__body text-center text-muted">Aucune licence V2 émise pour l'instant.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Client</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($licences_recent as $lic): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($lic['cle_licence']) ?></code></td>
                        <td><?= htmlspecialchars($lic['nom_prenom']) ?></td>
                        <td><?= klg_badge_statut($lic['statut']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Journal d'audit récent -->
    <div class="panel">
        <div class="panel__header">
            <div>
                <div class="panel__title">📜 Journal d'audit récent</div>
            </div>
            <a href="?page=audit_logs" class="btn btn--sm btn--ghost">Voir tout</a>
        </div>
        <div class="table-wrapper">
            <?php if (empty($audit_recent)): ?>
                <div class="panel__body text-center text-muted">Aucune action enregistrée.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Cible</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_recent as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td><?= htmlspecialchars((string)($log['cible'] ?? '—')) ?></td>
                        <td class="text-muted"><?= date('d/m H:i', strtotime($log['date_action'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
klg_layout_footer();

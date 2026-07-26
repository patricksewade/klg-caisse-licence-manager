<?php
declare(strict_types=1);
/**
 * KLG-Caisse License Manager — Vue : Journal d'Audit
 * ====================================================
 * Affiche le journal d'audit complet des actions de l'éditeur.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/core/audit/audit_functions.php';
require_once __DIR__ . '/layout.php';

$logs = klg_audit_get_recent(100);

klg_layout_header("Journal d'audit", 'audit_logs', 'Historique immuable des actions sensibles');
?>

<div class="panel">
    <div class="panel__header">
        <div>
            <div class="panel__title">📜 Journal d'audit</div>
            <div class="panel__subtitle"><?= count($logs) ?> dernières actions enregistrées</div>
        </div>
    </div>
    <div class="table-wrapper">
        <?php if (empty($logs)): ?>
            <div class="panel__body text-center text-muted">Aucune action enregistrée pour l'instant.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Action</th>
                    <th>Cible</th>
                    <th>Adresse IP</th>
                    <th>Date & Heure</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-muted"><?= (int)$log['id'] ?></td>
                    <td><span class="badge badge--v2"><?= htmlspecialchars($log['action']) ?></span></td>
                    <td><?= !empty($log['cible']) ? '<code>' . htmlspecialchars($log['cible']) . '</code>' : '—' ?></td>
                    <td class="text-muted"><?= htmlspecialchars((string)($log['ip_address'] ?? '—')) ?></td>
                    <td class="text-muted"><?= date('d/m/Y à H:i:s', strtotime($log['date_action'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php
klg_layout_footer();

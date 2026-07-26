<?php
declare(strict_types=1);
/**
 * KLG-Caisse License Manager — Vue : Révocation Hors-Ligne
 * =========================================================
 * Pilier 2 : Traitement des codes HMAC de révocation fournis
 * par les clients souhaitant changer de machine (HWID).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/core/security/crypto_functions.php';
require_once dirname(__DIR__) . '/core/database/db_functions.php';
require_once dirname(__DIR__) . '/core/audit/audit_functions.php';
require_once __DIR__ . '/layout.php';

$message      = null;
$message_type = 'info';
$licence_info = null;

// ================================================================
// TRAITEMENT DU FORMULAIRE
// ================================================================
if (isset($_POST['btn_verifier_revocation'])) {

    $cle_licence     = trim((string)($_POST['cle_licence'] ?? ''));
    $code_revocation = trim((string)($_POST['code_revocation'] ?? ''));
    $motif           = trim((string)($_POST['motif'] ?? ''));

    $erreurs = [];
    if (empty($cle_licence)) {
        $erreurs[] = 'La clé de licence est obligatoire.';
    }
    if (empty($code_revocation)) {
        $erreurs[] = 'Le code de révocation est obligatoire.';
    }

    if (!empty($erreurs)) {
        $message      = implode('<br>', $erreurs);
        $message_type = 'error';
    } else {
        // Recherche de la licence en base
        $licence_info = klg_db_get_licence_by_cle($cle_licence);

        if ($licence_info === null) {
            $message      = 'Aucune licence V2 trouvée avec la référence <strong>' . htmlspecialchars($cle_licence) . '</strong>.';
            $message_type = 'error';
        } elseif ($licence_info['statut'] === 'REVOQUEE') {
            $message      = 'Cette licence est déjà marquée comme révoquée.';
            $message_type = 'warning';
        } else {
            // Vérification du code HMAC
            $hwid = $licence_info['hwid'] ?? '';
            $resultat = klg_verifier_code_revocation($code_revocation, $cle_licence, $hwid);

            if (!$resultat['valide']) {
                $message      = '❌ Code de révocation invalide. La signature HMAC-SHA256 ne correspond pas. Vérifiez que le client vous a transmis le bon code.';
                $message_type = 'error';
                $licence_info = null;
            } elseif (isset($_POST['btn_confirmer_revocation'])) {
                // Confirmation : marquer la licence comme révoquée
                $rev_ok = klg_db_revoquer_licence((int)$licence_info['id']);
                klg_db_enregistrer_revocation(
                    (int)$licence_info['id'],
                    $code_revocation,
                    $hwid,
                    $motif
                );
                klg_log_action(AUDIT_REVOCATION_LICENCE, $cle_licence, [
                    'hwid'   => substr($hwid, 0, 16) . '...',
                    'motif'  => $motif,
                    'client' => $licence_info['nom_prenom'],
                ]);

                if ($rev_ok) {
                    $message      = '✅ Licence <strong>' . htmlspecialchars($cle_licence) . '</strong> révoquée avec succès. Vous pouvez maintenant émettre un nouveau jeton JWT pour le nouveau HWID du client.';
                    $message_type = 'success';
                    $licence_info = null;
                } else {
                    $message      = '❌ Erreur lors de la mise à jour de la base de données. Réessayez.';
                    $message_type = 'error';
                }
            } else {
                // Code valide mais pas encore confirmé
                $message      = '✅ Code de révocation valide ! Vérifiez les informations ci-dessous puis confirmez la révocation.';
                $message_type = 'success';
            }
        }
    }
}

klg_layout_header('Traiter une révocation', 'revoke_licence', 'Vérification HMAC-SHA256 du code de désactivation hors-ligne');
?>

<div class="alert alert--info">
    <span>ℹ️</span>
    <div>
        <strong>Processus de révocation hors-ligne :</strong><br>
        Le client clique sur "Révoquer hors-ligne" dans KLG-Caisse → KLG-Caisse génère un <strong>Code de Révocation</strong> et verrouille le logiciel → Le client vous transmet ce code → Vous le collez ici pour valider et libérer la licence.
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert--<?= $message_type ?>"><?= $message ?></div>
<?php endif; ?>

<?php if ($licence_info !== null): ?>
<!-- Informations de la licence trouvée — Demande de confirmation -->
<div class="panel mb-24">
    <div class="panel__header">
        <div class="panel__title">📋 Licence identifiée — Confirmation requise</div>
    </div>
    <div class="panel__body">
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Référence</label>
                <div><code><?= htmlspecialchars($licence_info['cle_licence']) ?></code></div>
            </div>
            <div class="form-group">
                <label class="form-label">Client</label>
                <div><?= htmlspecialchars($licence_info['nom_prenom']) ?></div>
            </div>
            <div class="form-group">
                <label class="form-label">Type</label>
                <div><?= htmlspecialchars($licence_info['type_licence']) ?></div>
            </div>
            <div class="form-group">
                <label class="form-label">HWID actuel</label>
                <div><code><?= htmlspecialchars(substr($licence_info['hwid'], 0, 32)) ?>...</code></div>
            </div>
        </div>

        <hr class="divider">

        <form method="POST" action="">
            <input type="hidden" name="cle_licence" value="<?= htmlspecialchars($_POST['cle_licence'] ?? '') ?>">
            <input type="hidden" name="code_revocation" value="<?= htmlspecialchars($_POST['code_revocation'] ?? '') ?>">
            <div class="form-group mb-16">
                <label class="form-label" for="motif">Motif de la révocation</label>
                <input type="text" id="motif" name="motif" class="form-control"
                       placeholder="ex: Changement de machine, panne serveur..."
                       value="<?= htmlspecialchars($_POST['motif'] ?? '') ?>">
            </div>
            <div class="flex-center gap-12" style="justify-content:flex-end;">
                <a href="?page=revoke_licence" class="btn btn--ghost">Annuler</a>
                <button type="submit" name="btn_verifier_revocation" class="btn btn--ghost" value="1">← Recommencer</button>
                <button type="submit" name="btn_confirmer_revocation" class="btn btn--danger" value="1"
                        onclick="return confirm('Confirmer la révocation de cette licence ? Cette action est irréversible.')">
                    🚫 Confirmer la révocation
                </button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Formulaire de saisie du code -->
<div class="panel">
    <div class="panel__header">
        <div class="panel__title">🔍 Saisir le code de révocation</div>
    </div>
    <div class="panel__body">
        <form method="POST" action="" autocomplete="off">
            <div class="form-grid mb-16">
                <div class="form-group">
                    <label class="form-label" for="cle_licence">
                        Référence de la licence <span class="required">*</span>
                    </label>
                    <input type="text" id="cle_licence" name="cle_licence" class="form-control"
                           placeholder="ex: V2-2026-A3K7-P9QX" required
                           value="<?= htmlspecialchars($_POST['cle_licence'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group mb-16">
                <label class="form-label" for="code_revocation">
                    Code de Révocation fourni par le client <span class="required">*</span>
                </label>
                <textarea id="code_revocation" name="code_revocation" class="form-control"
                          rows="4" placeholder="Collez ici le code de révocation transmis par le client..." required><?= htmlspecialchars($_POST['code_revocation'] ?? '') ?></textarea>
                <span class="form-hint">Ce code a été généré par KLG-Caisse lors de la désactivation hors-ligne du client.</span>
            </div>
            <div class="flex-center gap-12" style="justify-content:flex-end;">
                <button type="submit" name="btn_verifier_revocation" value="1" class="btn btn--primary">
                    🔍 Vérifier le code
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
klg_layout_footer();

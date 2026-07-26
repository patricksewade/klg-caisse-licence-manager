<?php
declare(strict_types=1);
/**
 * KLG-Caisse License Manager — Vue : Création de Licence V2
 * ===========================================================
 * Pilier 1 : Formulaire de création/émission de licence JWT RS256.
 * Les champs clients sont calqués sur la table existante `cles_licence`.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/core/security/crypto_functions.php';
require_once dirname(__DIR__) . '/core/license/license_v2_functions.php';
require_once dirname(__DIR__) . '/core/database/db_functions.php';
require_once dirname(__DIR__) . '/core/audit/audit_functions.php';
require_once __DIR__ . '/layout.php';

$message        = null;
$message_type   = 'info';
$jwt_genere     = null;
$cle_genere     = null;

// ================================================================
// TRAITEMENT DU FORMULAIRE
// ================================================================
if (isset($_POST['btn_generer_licence'])) {

    // --- Récupération et assainissement des données ---
    $nom_prenom   = trim((string) ($_POST['nom_prenom_titulaire_licence'] ?? ''));
    $telephone    = trim((string) ($_POST['telephone_titulaire_licence'] ?? ''));
    $email        = trim((string) ($_POST['email_titulaire_licence'] ?? ''));
    $hwid         = trim((string) ($_POST['hwid'] ?? ''));
    $type_licence = trim((string) ($_POST['type_licence'] ?? 'ESSAI'));
    $duree        = (int) ($_POST['duree_periode_utilisation'] ?? 0);
    $unite        = trim((string) ($_POST['unite_temps_periode_utilisation'] ?? 'months'));
    $date_exp_str = trim((string) ($_POST['date_expiration'] ?? ''));

    // Feature flags cochés
    $features_possibles = ['multi_caisse', 'statistiques_avancees', 'module_fidelite', 'module_stock'];
    $features = [];
    foreach ($features_possibles as $feat) {
        if (!empty($_POST['feature_' . $feat])) {
            $features[$feat] = true;
        }
    }

    // --- Validation ---
    $erreurs = [];
    if (empty($nom_prenom)) {
        $erreurs[] = 'Le nom et prénom du titulaire sont obligatoires.';
    }
    if (empty($hwid)) {
        $erreurs[] = 'Le HWID est obligatoire. Demandez-le au client depuis son logiciel KLG-Caisse.';
    }
    if (!in_array($type_licence, ['ESSAI', 'ABONNEMENT', 'PERPETUELLE'], true)) {
        $erreurs[] = 'Type de licence invalide.';
    }
    if (in_array($type_licence, ['ESSAI', 'ABONNEMENT'], true) && empty($date_exp_str)) {
        $erreurs[] = 'La date d\'expiration est obligatoire pour les licences ESSAI et ABONNEMENT.';
    }

    if (!empty($erreurs)) {
        $message      = implode('<br>', $erreurs);
        $message_type = 'error';
    } else {
        // --- Calcul de la date d'expiration en datetime ---
        $date_expiration = '';
        if ($type_licence !== 'PERPETUELLE' && !empty($date_exp_str)) {
            $date_expiration = date('Y-m-d', strtotime($date_exp_str)) . ' 23:59:59';
        }

        // --- Génération de la référence unique V2 ---
        $cle_licence = klg_generer_ref_licence_v2();

        // --- Chargement de la clé privée RSA ---
        $private_key_pem = klg_load_private_key();
        if ($private_key_pem === null) {
            $message      = '🔐 Erreur critique : Impossible de charger la clé privée RSA. Vérifiez le fichier <code>keys/klg_private_key.pem</code>.';
            $message_type = 'error';
        } else {
            // --- Émission du JWT ---
            $jwt = klg_emettre_licence_v2([
                'nom_prenom'                        => $nom_prenom,
                'telephone'                         => $telephone,
                'email'                             => $email,
                'hwid'                              => $hwid,
                'type_licence'                      => $type_licence,
                'cle_licence'                       => $cle_licence,
                'duree_periode_utilisation'         => $duree,
                'unite_temps_periode_utilisation'   => $unite,
                'date_expiration'                   => $date_expiration,
                'features'                          => $features,
            ], $private_key_pem);

            if ($jwt === null) {
                $message      = '❌ Erreur lors de la signature RSA du jeton JWT. Vérifiez la validité de la clé privée.';
                $message_type = 'error';
            } else {
                // --- Enregistrement client ---
                $client_id = klg_db_upsert_client($nom_prenom, $telephone, $email);

                // --- Enregistrement de la licence ---
                $licence_id = null;
                if ($client_id) {
                    $licence_id = klg_db_inserer_licence_v2(
                        $client_id,
                        $cle_licence,
                        $type_licence,
                        $hwid,
                        $jwt,
                        $duree,
                        $unite,
                        $date_expiration,
                        $features
                    );
                }

                // --- Audit ---
                klg_log_action(AUDIT_GENERATION_JWT, $cle_licence, [
                    'type_licence' => $type_licence,
                    'hwid'         => substr($hwid, 0, 16) . '...',
                    'client'       => $nom_prenom,
                    'licence_id'   => $licence_id,
                ]);

                $jwt_genere  = $jwt;
                $cle_genere  = $cle_licence;
                $message      = '✅ Licence V2 émise avec succès ! Copiez le jeton JWT ci-dessous et transmettez-le au client.';
                $message_type = 'success';
            }
        }
    }
}

klg_layout_header('Émettre une licence V2', 'create_licence', 'Génération d\'un jeton JWT signé RSA-SHA256');
?>

<?php if ($message): ?>
<div class="alert alert--<?= $message_type ?>" role="alert">
    <?= $message ?>
</div>
<?php endif; ?>

<?php if ($jwt_genere): ?>
<!-- Affichage du JWT généré -->
<div class="panel mb-24">
    <div class="panel__header">
        <div>
            <div class="panel__title">🔑 Jeton JWT généré — Référence : <code><?= htmlspecialchars($cle_genere ?? '') ?></code></div>
            <div class="panel__subtitle">Transmettez ce bloc entier au client pour qu'il l'importe dans KLG-Caisse.</div>
        </div>
    </div>
    <div class="panel__body">
        <div class="jwt-output" id="jwt-output-block">
            <button class="jwt-output__copy-btn" onclick="klg_copier_jwt()" id="btn-copy-jwt">📋 Copier</button>
            <?= htmlspecialchars($jwt_genere) ?>
        </div>
    </div>
</div>
<script>
function klg_copier_jwt() {
    const txt = document.getElementById('jwt-output-block').innerText.replace('📋 Copier', '').trim();
    navigator.clipboard.writeText(txt).then(() => {
        const btn = document.getElementById('btn-copy-jwt');
        btn.textContent = '✅ Copié !';
        setTimeout(() => btn.textContent = '📋 Copier', 2000);
    });
}
</script>
<?php endif; ?>

<!-- Formulaire de création -->
<div class="panel">
    <div class="panel__header">
        <div>
            <div class="panel__title">📝 Informations de la licence</div>
            <div class="panel__subtitle">Remplissez tous les champs obligatoires puis cliquez sur "Émettre".</div>
        </div>
    </div>
    <div class="panel__body">
        <form method="POST" action="" autocomplete="off" id="form-create-licence">

            <!-- Section : Informations client (calquées sur table cles_licence) -->
            <p class="panel__title mb-16" style="font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gris-texte);">👤 Informations du titulaire</p>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="nom_prenom_titulaire_licence">
                        Nom et Prénom <span class="required">*</span>
                    </label>
                    <input type="text" id="nom_prenom_titulaire_licence" name="nom_prenom_titulaire_licence"
                           class="form-control" placeholder="ex: Jean DUPONT" required
                           value="<?= htmlspecialchars($_POST['nom_prenom_titulaire_licence'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="telephone_titulaire_licence">Téléphone</label>
                    <input type="tel" id="telephone_titulaire_licence" name="telephone_titulaire_licence"
                           class="form-control" placeholder="ex: +229 97 00 00 00"
                           value="<?= htmlspecialchars($_POST['telephone_titulaire_licence'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email_titulaire_licence">Adresse e-mail</label>
                    <input type="email" id="email_titulaire_licence" name="email_titulaire_licence"
                           class="form-control" placeholder="jean.dupont@exemple.com"
                           value="<?= htmlspecialchars($_POST['email_titulaire_licence'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="hwid">
                        HWID — Empreinte Matérielle <span class="required">*</span>
                    </label>
                    <input type="text" id="hwid" name="hwid"
                           class="form-control" placeholder="ex: e3b0c44298fc1c149afbf4c8996fb924..."
                           required value="<?= htmlspecialchars($_POST['hwid'] ?? '') ?>">
                    <span class="form-hint">Le client obtient son HWID dans KLG-Caisse → Licence → "Obtenir mon HWID".</span>
                </div>
            </div>

            <hr class="divider">

            <!-- Section : Paramètres de la licence -->
            <p class="panel__title mb-16" style="font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gris-texte);">⚙️ Paramètres de la licence</p>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="type_licence">
                        Type de licence <span class="required">*</span>
                    </label>
                    <select id="type_licence" name="type_licence" class="form-control" required onchange="klg_toggle_expiration(this.value)">
                        <option value="ESSAI"       <?= (($_POST['type_licence'] ?? '') === 'ESSAI')       ? 'selected' : '' ?>>ESSAI</option>
                        <option value="ABONNEMENT"  <?= (($_POST['type_licence'] ?? '') === 'ABONNEMENT')  ? 'selected' : '' ?>>ABONNEMENT</option>
                        <option value="PERPETUELLE" <?= (($_POST['type_licence'] ?? '') === 'PERPETUELLE') ? 'selected' : '' ?>>PERPETUELLE</option>
                    </select>
                </div>
                <div class="form-group" id="groupe-expiration">
                    <label class="form-label" for="date_expiration">Date d'expiration</label>
                    <input type="date" id="date_expiration" name="date_expiration"
                           class="form-control"
                           min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                           value="<?= htmlspecialchars($_POST['date_expiration'] ?? '') ?>">
                    <span class="form-hint">Obligatoire pour ESSAI et ABONNEMENT. Laisser vide si PERPETUELLE.</span>
                </div>
                <div class="form-group">
                    <label class="form-label" for="duree_periode_utilisation">Durée de la période</label>
                    <input type="number" id="duree_periode_utilisation" name="duree_periode_utilisation"
                           class="form-control" min="1" placeholder="ex: 12"
                           value="<?= htmlspecialchars((string)($_POST['duree_periode_utilisation'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="unite_temps_periode_utilisation">Unité de temps</label>
                    <select id="unite_temps_periode_utilisation" name="unite_temps_periode_utilisation" class="form-control">
                        <?php
                        $unites = ['years' => 'Année(s)', 'months' => 'Mois', 'days' => 'Jour(s)', 'hours' => 'Heure(s)'];
                        foreach ($unites as $val => $label):
                            $sel = (($_POST['unite_temps_periode_utilisation'] ?? 'months') === $val) ? 'selected' : '';
                        ?>
                        <option value="<?= $val ?>" <?= $sel ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr class="divider">

            <!-- Section : Feature Flags -->
            <p class="panel__title mb-16" style="font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gris-texte);">🧩 Modules et Fonctionnalités activés</p>
            <div class="features-grid">
                <?php
                $features_defs = [
                    'multi_caisse'         => ['icon' => '🖥️', 'label' => 'Multi-Caisse'],
                    'statistiques_avancees'=> ['icon' => '📊', 'label' => 'Statistiques Avancées'],
                    'module_fidelite'      => ['icon' => '⭐', 'label' => 'Module Fidélité'],
                    'module_stock'         => ['icon' => '📦', 'label' => 'Gestion du Stock'],
                ];
                foreach ($features_defs as $key => $def):
                    $checked = !empty($_POST['feature_' . $key]) ? 'checked' : '';
                ?>
                <label class="feature-item" for="feat_<?= $key ?>">
                    <input type="checkbox" id="feat_<?= $key ?>" name="feature_<?= $key ?>" value="1" <?= $checked ?>
                           style="accent-color:var(--bleu-moyen);">
                    <span class="feature-item__icon"><?= $def['icon'] ?></span>
                    <span class="feature-item__label"><?= $def['label'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <hr class="divider">

            <div class="flex-center gap-12" style="justify-content:flex-end;">
                <a href="?page=dashboard" class="btn btn--ghost">Annuler</a>
                <button type="submit" name="btn_generer_licence" class="btn btn--primary" id="btn-submit">
                    🔑 Émettre la licence V2
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function klg_toggle_expiration(type) {
    const groupe = document.getElementById('groupe-expiration');
    const input  = document.getElementById('date_expiration');
    if (type === 'PERPETUELLE') {
        input.removeAttribute('required');
        groupe.style.opacity = '.5';
        input.value = '';
    } else {
        input.setAttribute('required', 'required');
        groupe.style.opacity = '1';
    }
}
// Initialiser l'état à l'affichage
klg_toggle_expiration(document.getElementById('type_licence').value);
</script>
<?php
klg_layout_footer();

<?php
declare(strict_types=1);
/**
 * KLG-Caisse License Manager — Template Global (Layout)
 * ======================================================
 * Ce fichier est inclus par chaque vue. Il génère le HTML
 * complet de la page en deux parties : klg_layout_header() et
 * klg_layout_footer().
 *
 * Usage dans une vue :
 *   klg_layout_header('Titre de la page', 'nav-item-id');
 *   // ... contenu ...
 *   klg_layout_footer();
 */

/**
 * Retourne la liste des éléments de navigation de la sidebar.
 * @return array
 */
function klg_nav_items(): array
{
    return [
        [
            'id'      => 'dashboard',
            'label'   => 'Tableau de bord',
            'icon'    => '📊',
            'href'    => '?page=dashboard',
            'section' => null,
        ],
        [
            'id'      => 'create_licence',
            'label'   => 'Émettre une licence V2',
            'icon'    => '🔑',
            'href'    => '?page=create_licence',
            'section' => 'Licences V2',
        ],
        [
            'id'      => 'list_licences',
            'label'   => 'Toutes les licences',
            'icon'    => '📋',
            'href'    => '?page=list_licences',
            'section' => null,
        ],
        [
            'id'      => 'revoke_licence',
            'label'   => 'Traiter une révocation',
            'icon'    => '🚫',
            'href'    => '?page=revoke_licence',
            'section' => null,
        ],
        [
            'id'      => 'audit_logs',
            'label'   => "Journal d'audit",
            'icon'    => '📜',
            'href'    => '?page=audit_logs',
            'section' => 'Administration',
        ],
    ];
}


/**
 * Génère le HTML de début de page (header + sidebar + ouverture du contenu).
 *
 * @param string $page_title Titre affiché dans l'onglet et le header.
 * @param string $active_nav ID de l'élément de navigation actif.
 * @param string $subtitle   Sous-titre optionnel du header.
 */
function klg_layout_header(string $page_title, string $active_nav = 'dashboard', string $subtitle = ''): void
{
    $base_url = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    $nav_items = klg_nav_items();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — KLG-Caisse License Manager</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/style.css">
</head>
<body>
<div class="app-wrapper">

    <!-- ===================== SIDEBAR ===================== -->
    <aside class="sidebar">
        <div class="sidebar__logo">
            <div class="sidebar__logo-icon">🔐</div>
            <div class="sidebar__logo-text">
                KLG-Caisse
                <small>License Manager</small>
            </div>
        </div>
        <nav class="sidebar__nav">
            <?php
            $last_section = null;
            foreach ($nav_items as $item):
                if ($item['section'] !== null && $item['section'] !== $last_section):
                    $last_section = $item['section'];
                    ?>
                    <div class="nav-section-title"><?= htmlspecialchars($item['section']) ?></div>
                    <?php
                endif;
                $is_active = ($active_nav === $item['id']) ? ' active' : '';
                ?>
                <a href="<?= $base_url . $item['href'] ?>" class="nav-item<?= $is_active ?>">
                    <span class="nav-icon"><?= $item['icon'] ?></span>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar__footer">
            KLG-Caisse License Manager v2.0.0<br>
            &copy; <?= date('Y') ?> KLG Éditions
        </div>
    </aside>

    <!-- =================== CONTENU PRINCIPAL =================== -->
    <div class="main-content">
        <header class="top-header">
            <div>
                <div class="top-header__title"><?= htmlspecialchars($page_title) ?></div>
                <?php if ($subtitle): ?>
                    <div class="top-header__subtitle"><?= htmlspecialchars($subtitle) ?></div>
                <?php endif; ?>
            </div>
            <div class="top-header__right">
                <span class="header-badge">Serveur Éditeur</span>
            </div>
        </header>
        <main class="page-body">
    <?php
}


/**
 * Génère le HTML de fin de page (fermeture des balises).
 */
function klg_layout_footer(): void
{
    ?>
        </main>
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->
</body>
</html>
    <?php
}

-- =============================================================
-- KLG-CAISSE LICENSE MANAGER — Schéma de Base de Données V2
-- Préfixe des tables : klg_c_lm_
-- La table legacy `cles_licence` est CONSERVÉE pour la V1.
-- =============================================================

SET NAMES 'utf8mb4';
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Table 1 : klg_c_lm_clients
-- Informations sur les acheteurs de licences KLG-Caisse
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `klg_c_lm_clients` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nom_prenom`  VARCHAR(150) NOT NULL COMMENT 'Nom et prénom du titulaire',
    `entreprise`  VARCHAR(150) DEFAULT NULL COMMENT 'Raison sociale / nom de l\'entreprise',
    `telephone`   VARCHAR(30)  DEFAULT NULL,
    `email`       VARCHAR(150) DEFAULT NULL,
    `date_create` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_modif`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Acheteurs de licences KLG-Caisse';

-- -------------------------------------------------------------
-- Table 2 : klg_c_lm_licences
-- Chaque jeton JWT émis constitue une ligne ici
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `klg_c_lm_licences` (
    `id`                           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`                    INT UNSIGNED NOT NULL COMMENT 'Référence client',
    `cle_licence`                  VARCHAR(30)  NOT NULL COMMENT 'Référence unique (ex: V2-2026-ABCD-1234)',
    `version`                      TINYINT(1)   NOT NULL DEFAULT 2 COMMENT '1=V1 legacy, 2=V2 JWT',
    `type_licence`                 ENUM('ESSAI','ABONNEMENT','PERPETUELLE') NOT NULL DEFAULT 'ESSAI',
    `hwid`                         VARCHAR(255) DEFAULT NULL COMMENT 'Empreinte matérielle du serveur client',
    `jwt_token`                    MEDIUMTEXT   DEFAULT NULL COMMENT 'Jeton JWT signé (V2 uniquement)',
    `duree_periode_utilisation`    INT          DEFAULT NULL COMMENT 'Durée en nombre d\'unités',
    `unite_temps_periode_utilisation` ENUM('years','months','days','hours','minutes','seconds') DEFAULT 'months',
    `date_first_activation`        DATETIME     DEFAULT NULL,
    `date_expiration`              DATETIME     DEFAULT NULL COMMENT 'NULL si PERPETUELLE',
    `statut`                       ENUM('ACTIVE','EXPIREE','REVOQUEE','MIGREE','EN_ATTENTE') NOT NULL DEFAULT 'EN_ATTENTE',
    `features`                     JSON         DEFAULT NULL COMMENT 'Feature flags JSON (ex: {"multi_caisse":true})',
    `date_emission`                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de génération du JWT',
    `date_modif`                   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cle_licence` (`cle_licence`),
    KEY `idx_client_id` (`client_id`),
    KEY `idx_statut` (`statut`),
    KEY `idx_hwid` (`hwid`(64)),
    CONSTRAINT `fk_licence_client` FOREIGN KEY (`client_id`) REFERENCES `klg_c_lm_clients` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Licences V2 JWT émises';

-- -------------------------------------------------------------
-- Table 3 : klg_c_lm_revocations
-- Preuves de révocation hors-ligne reçues des clients
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `klg_c_lm_revocations` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `licence_id`    INT UNSIGNED NOT NULL COMMENT 'Licence révoquée',
    `code_hmac`     TEXT         NOT NULL COMMENT 'Code brut soumis par le client',
    `ancien_hwid`   VARCHAR(255) NOT NULL COMMENT 'HWID de la machine désactivée',
    `motif`         VARCHAR(255) DEFAULT NULL COMMENT 'Motif indiqué par le client',
    `validee_par`   VARCHAR(100) DEFAULT NULL COMMENT 'Administrateur ayant validé la révocation',
    `date_revocation` DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_licence_id` (`licence_id`),
    CONSTRAINT `fk_revocation_licence` FOREIGN KEY (`licence_id`) REFERENCES `klg_c_lm_licences` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Preuves de révocation hors-ligne';

-- -------------------------------------------------------------
-- Table 4 : klg_c_lm_releases
-- Catalogue des versions de KLG-Caisse disponibles
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `klg_c_lm_releases` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version`       VARCHAR(20)  NOT NULL COMMENT 'ex: 1.0.7',
    `changelog`     TEXT         DEFAULT NULL COMMENT 'Notes de version',
    `chemin_zip`    VARCHAR(255) DEFAULT NULL COMMENT 'Chemin relatif vers le paquet ZIP',
    `hash_sha256`   CHAR(64)     DEFAULT NULL COMMENT 'Empreinte SHA-256 du ZIP',
    `est_active`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=version proposée au téléchargement',
    `date_upload`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Versions téléchargeables de KLG-Caisse';

-- -------------------------------------------------------------
-- Table 5 : klg_c_lm_audit_logs
-- Journal immuable des actions sensibles de l'éditeur
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `klg_c_lm_audit_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `action`      VARCHAR(100)   NOT NULL COMMENT 'ex: GENERATION_JWT, REVOCATION, MODIFICATION_HWID',
    `cible`       VARCHAR(100)   DEFAULT NULL COMMENT 'Identifiant de la ressource concernée (ex: cle_licence)',
    `details`     TEXT           DEFAULT NULL COMMENT 'Détails JSON de l\'action',
    `ip_address`  VARCHAR(45)    DEFAULT NULL,
    `date_action` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_action` (`action`),
    KEY `idx_date` (`date_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Journal d\'audit immuable des actions sensibles';

SET FOREIGN_KEY_CHECKS = 1;

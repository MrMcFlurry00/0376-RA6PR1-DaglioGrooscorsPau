-- ============================================================
-- Tracking DB - Base de dades completa
-- Aplicació de control horari i de projectes
-- ============================================================

CREATE DATABASE IF NOT EXISTS tracking_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tracking_db;

-- Neteja de taules (per reinstal·lació neta)
DROP TABLE IF EXISTS alertes;
DROP TABLE IF EXISTS registres;
DROP TABLE IF EXISTS projectes;
DROP TABLE IF EXISTS usuaris;
DROP TABLE IF EXISTS horari_config;

-- ============================================================
-- TAULES
-- ============================================================

CREATE TABLE usuaris (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    contrasenya VARCHAR(255) NOT NULL,
    rol ENUM('empleat', 'admin') DEFAULT 'empleat',
    actiu TINYINT(1) DEFAULT 1,
    data_alta DATE DEFAULT (CURRENT_DATE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE projectes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    client VARCHAR(150) DEFAULT NULL,
    descripcio TEXT,
    hores_pressupostades INT NOT NULL DEFAULT 0,
    color VARCHAR(7) DEFAULT '#2563eb',
    actiu TINYINT(1) DEFAULT 1,
    data_inici DATE DEFAULT NULL,
    data_fi DATE DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE registres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuari_id INT NOT NULL,
    projecte_id INT NOT NULL,
    entrada DATETIME NOT NULL,
    sortida DATETIME DEFAULT NULL,
    notes TEXT,
    INDEX idx_usuari_data (usuari_id, entrada),
    INDEX idx_projecte (projecte_id),
    INDEX idx_entrada (entrada),
    FOREIGN KEY (usuari_id) REFERENCES usuaris(id) ON DELETE CASCADE,
    FOREIGN KEY (projecte_id) REFERENCES projectes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE horari_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hora_entrada_prevista TIME DEFAULT '09:00:00',
    hora_sortida_prevista TIME DEFAULT '18:00:00',
    hores_diaries DECIMAL(4,2) DEFAULT 8.00,
    tolerancia_minuts INT DEFAULT 15
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE alertes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuari_id INT NOT NULL,
    tipus ENUM('retard','sortida_anticipada','hores_insuficients','no_fitxa','registre_obert') NOT NULL,
    missatge VARCHAR(255) NOT NULL,
    data_alerta DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_referent DATE,
    resolta TINYINT(1) DEFAULT 0,
    INDEX idx_usuari (usuari_id),
    INDEX idx_data (data_referent),
    INDEX idx_resolta (resolta),
    FOREIGN KEY (usuari_id) REFERENCES usuaris(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DADES INICIALS
-- ============================================================

-- Contrasenya per defecte per a tots: "password"
INSERT INTO horari_config (id, hora_entrada_prevista, hora_sortida_prevista, hores_diaries, tolerancia_minuts)
VALUES (1, '09:00:00', '18:00:00', 8.00, 15)
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO usuaris (nom, email, contrasenya, rol) VALUES 
('Joan Empleat', 'joan@empresa.com', '$2y$10$VyCfHl4pFCbyH1OiWiL6rOwoVl0Pus7pFgpaKV.1gB.R3Xo6GF7MC', 'empleat'),
('Anna Garcia', 'anna@empresa.com', '$2y$10$VyCfHl4pFCbyH1OiWiL6rOwoVl0Pus7pFgpaKV.1gB.R3Xo6GF7MC', 'empleat'),
('Pere Marti', 'pere@empresa.com', '$2y$10$VyCfHl4pFCbyH1OiWiL6rOwoVl0Pus7pFgpaKV.1gB.R3Xo6GF7MC', 'empleat'),
('Maria Lopez', 'maria@empresa.com', '$2y$10$VyCfHl4pFCbyH1OiWiL6rOwoVl0Pus7pFgpaKV.1gB.R3Xo6GF7MC', 'empleat'),
('Carles Vidal', 'carles@empresa.com', '$2y$10$VyCfHl4pFCbyH1OiWiL6rOwoVl0Pus7pFgpaKV.1gB.R3Xo6GF7MC', 'empleat'),
('Laura Pons', 'laura@empresa.com', '$2y$10$VyCfHl4pFCbyH1OiWiL6rOwoVl0Pus7pFgpaKV.1gB.R3Xo6GF7MC', 'empleat'),
('Marc Ferrer', 'marc@empresa.com', '$2y$10$VyCfHl4pFCbyH1OiWiL6rOwoVl0Pus7pFgpaKV.1gB.R3Xo6GF7MC', 'empleat'),
('Cap Administrador', 'cap@empresa.com', '$2y$10$VyCfHl4pFCbyH1OiWiL6rOwoVl0Pus7pFgpaKV.1gB.R3Xo6GF7MC', 'admin');

INSERT INTO projectes (nom, client, descripcio, hores_pressupostades, color) VALUES 
('Projecte A - Web Client', 'TechCorp SL', 'Desenvolupament web corporatiu', 100, '#2563eb'),
('Projecte B - App Mòbil', 'StartupXYZ', 'Aplicació mòbil iOS/Android', 50, '#10b981'),
('Projecte C - E-Commerce', 'ShopOnline SA', 'Botiga online multi-idioma', 200, '#f59e0b'),
('Projecte D - Consultoria', 'BigCorp SA', 'Consultoria estratègica IT', 80, '#ef4444'),
('Projecte E - Manteniment', 'Internal', 'Manteniment intern i suport', 40, '#8b5cf6'),
('Projecte F - Migració Cloud', 'TechCorp SL', 'Migració de serveis al núvol', 150, '#06b6d4');

-- ============================================================
-- DADES DE DEMO: registres d'aquesta setmana
-- ============================================================

-- Avui
INSERT INTO registres (usuari_id, projecte_id, entrada, sortida) VALUES
(2, 1, CONCAT(CURDATE(), ' 08:55:00'), CONCAT(CURDATE(), ' 18:05:00')),
(3, 2, CONCAT(CURDATE(), ' 09:20:00'), CONCAT(CURDATE(), ' 17:30:00')),
(4, 1, CONCAT(CURDATE(), ' 08:45:00'), CONCAT(CURDATE(), ' 18:30:00')),
(5, 3, CONCAT(CURDATE(), ' 09:05:00'), NULL),  -- encara treballant
(6, 4, CONCAT(CURDATE(), ' 09:00:00'), CONCAT(CURDATE(), ' 16:30:00')),  -- surt aviat
(7, 5, CONCAT(CURDATE(), ' 08:50:00'), CONCAT(CURDATE(), ' 18:00:00'));

-- Ahir
INSERT INTO registres (usuari_id, projecte_id, entrada, sortida) VALUES
(2, 1, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 18:00:00')),
(3, 2, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 09:30:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 17:00:00')),
(4, 1, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 08:50:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 19:00:00')),
(5, 3, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 09:10:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 18:15:00')),
(6, 4, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 08:55:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 18:10:00')),
(7, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 17:45:00'));

-- Fa 2 dies
INSERT INTO registres (usuari_id, projecte_id, entrada, sortida) VALUES
(2, 1, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 08:55:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 18:00:00')),
(3, 2, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 18:00:00')),
(4, 1, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 18:00:00')),
(5, 3, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 08:50:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 19:00:00')),
(6, 4, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 09:05:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 17:30:00')),
(7, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 18:00:00'));

-- Fa 3 dies
INSERT INTO registres (usuari_id, projecte_id, entrada, sortida) VALUES
(2, 1, CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 18:00:00')),
(3, 2, CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 09:15:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 18:00:00')),
(4, 1, CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 08:45:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 18:30:00')),
(5, 3, CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 18:00:00')),
(7, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 17:00:00'));

-- Fa 4 dies
INSERT INTO registres (usuari_id, projecte_id, entrada, sortida) VALUES
(2, 1, CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 08:55:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 18:00:00')),
(4, 1, CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 18:00:00')),
(5, 3, CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 19:00:00')),
(6, 4, CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 09:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 18:00:00'));

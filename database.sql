CREATE DATABASE IF NOT EXISTS tracking_db;
USE tracking_db;

CREATE TABLE usuaris (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    contrasenya VARCHAR(255) NOT NULL,
    rol ENUM('empleat', 'admin') DEFAULT 'empleat'
);

CREATE TABLE projectes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    hores_pressupostades INT NOT NULL
);

CREATE TABLE registres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuari_id INT,
    projecte_id INT,
    entrada DATETIME NOT NULL,
    sortida DATETIME DEFAULT NULL,
    FOREIGN KEY (usuari_id) REFERENCES usuaris(id),
    FOREIGN KEY (projecte_id) REFERENCES projectes(id)
);

INSERT INTO usuaris (nom, email, contrasenya, rol) VALUES 
('Joan Empleat', 'joan@empresa.com', '$2y$10$7rO77Y4tK7Lclw/XoNqWKuIsk8f8qZ4G6rM8IuW2qO6jUv3Y4F792', 'empleat'),
('Cap Administrador', 'cap@empresa.com', '$2y$10$7rO77Y4tK7Lclw/XoNqWKuIsk8f8qZ4G6rM8IuW2qO6jUv3Y4F792', 'admin');

INSERT INTO projectes (nom, hores_pressupostades) VALUES ('Projecte A', 100), ('Projecte B', 50);
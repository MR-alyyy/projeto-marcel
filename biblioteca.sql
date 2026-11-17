-- ============================================================
-- biblioteca.sql
-- Importe este script no phpMyAdmin (aba "Importar" ou "SQL").
-- O script recria as tabelas da biblioteca e mantém a estrutura
-- lógica do sistema com relacionamentos consistentes.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `biblioteca`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `biblioteca`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `lista_desejos`;
DROP TABLE IF EXISTS `emprestimos`;
DROP TABLE IF EXISTS `prateleira`;
DROP TABLE IF EXISTS `livros`;
DROP TABLE IF EXISTS `leitores`;
DROP TABLE IF EXISTS `funcionario`;
DROP TABLE IF EXISTS `bibliotecario`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `bibliotecario` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(30) NOT NULL,
  `senha` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bibliotecario_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bibliotecario` (`nome`, `senha`) VALUES
('alysson', '$2y$10$cCfzNLLy5EcT/4wlzkPETOvfnNtsaYGIG48hv.1FN8Pca7nFMK5/2');

CREATE TABLE `funcionario` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(30) NOT NULL,
  `senha` VARCHAR(100) NOT NULL,
  `telefone` VARCHAR(15) NOT NULL,
  `cpf` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `matricula` VARCHAR(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_funcionario_cpf` (`cpf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `leitores` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(30) NOT NULL,
  `senha` VARCHAR(100) NOT NULL,
  `telefone` VARCHAR(15) NOT NULL,
  `cpf` VARCHAR(100) NOT NULL,
  `endereco` VARCHAR(60) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leitor_cpf` (`cpf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `livros` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `status` VARCHAR(15) NOT NULL DEFAULT 'ativo',
  `titulo` VARCHAR(30) NOT NULL,
  `autor` VARCHAR(30) NOT NULL,
  `genero` VARCHAR(15) NOT NULL,
  `quantidade` INT NOT NULL DEFAULT 1,
  `descricao` VARCHAR(350) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `prateleira` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(30) NOT NULL,
  `id_livro` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prateleira_livro` (`id_livro`),
  CONSTRAINT `fk_prateleira_livro` FOREIGN KEY (`id_livro`) REFERENCES `livros` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `emprestimos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_livro` INT NOT NULL,
  `id_leitor` INT NOT NULL,
  `data_emprestimo` DATE NOT NULL,
  `data_prevista` DATE NOT NULL,
  `data_devolucao` DATE DEFAULT NULL,
  `multa` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(15) NOT NULL DEFAULT 'emprestado',
  PRIMARY KEY (`id`),
  KEY `idx_emprestimos_id_livro` (`id_livro`),
  KEY `idx_emprestimos_id_leitor` (`id_leitor`),
  CONSTRAINT `fk_emprestimo_livro` FOREIGN KEY (`id_livro`) REFERENCES `livros` (`id`),
  CONSTRAINT `fk_emprestimo_leitor` FOREIGN KEY (`id_leitor`) REFERENCES `leitores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `lista_desejos` (
  `id_leitor` INT NOT NULL,
  `id_livro` INT NOT NULL,
  `data_adicionado` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_leitor`, `id_livro`),
  CONSTRAINT `fk_desejo_leitor` FOREIGN KEY (`id_leitor`) REFERENCES `leitores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_desejo_livro` FOREIGN KEY (`id_livro`) REFERENCES `livros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

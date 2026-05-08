-- Trilhas de auditoria
CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabela VARCHAR(50) NOT NULL,
    registro_id INT NOT NULL,
    acao ENUM('insercao','edicao','exclusao') NOT NULL,
    usuario VARCHAR(100) NOT NULL,
    data DATETIME DEFAULT CURRENT_TIMESTAMP,
    detalhes TEXT
) ENGINE=InnoDB;

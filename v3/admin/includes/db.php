<?php
// O $pdo MySQL já foi criado por includes/db.php (carregado via auth.php)
// Apenas ajusta o fetch mode para FETCH_OBJ (compatível com o código do painel)
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

<?php
/**
 * Script de inicialização do banco de dados.
 * Execute uma vez para criar tabelas e dados iniciais.
 * Acesse via browser: /database/init.php?token=inicializar
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (($_GET['token'] ?? '') !== 'inicializar') {
    die('Acesso negado. Adicione ?token=inicializar na URL.');
}

$db = getDB();

$schema = file_get_contents(__DIR__ . '/schema.sql');
$statements = array_filter(array_map('trim', explode(';', $schema)));

foreach ($statements as $stmt) {
    if ($stmt) {
        $db->exec($stmt);
    }
}

// Admin padrão (alterar senha após o primeiro acesso)
$adminExists = dbQueryOne('SELECT id FROM admins WHERE email = ?', ['admin@afiacaoalmeida.com']);
if (!$adminExists) {
    require_once __DIR__ . '/../includes/auth.php';
    dbExecute(
        'INSERT INTO admins (nome, email, senha) VALUES (?, ?, ?)',
        ['Administrador', 'admin@afiacaoalmeida.com', hashPassword('Almeida@2025')]
    );
}

// Configurações padrão
$configs = [
    'empresa_nome'         => COMPANY_NAME,
    'empresa_telefone'     => COMPANY_PHONE,
    'empresa_endereco'     => COMPANY_ADDRESS,
    'empresa_cidade'       => COMPANY_CITY,
    'empresa_email'        => COMPANY_EMAIL,
    'horario_seg_sex'      => '08:00 - 18:00',
    'horario_sabado'       => '08:00 - 12:00',
    'horario_domingo'      => 'Fechado',
    'instagram'            => 'https://instagram.com/afiacaoalmeida',
    'facebook'             => 'https://facebook.com/afiacaoalmeida',
    'tiktok'               => 'https://tiktok.com/@afiacaoalmeida',
    'link_maps'            => 'https://maps.google.com/?q=R.+Raimundo+Dias+Dos+Santos+2876',
];

foreach ($configs as $key => $value) {
    setConfig($key, $value);
}

// Tabela de preços padrão
$precos = [
    ['afiacao',    'Alicate de Cutícula',    10.00],
    ['afiacao',    'Alicate de Sobrancelha', 10.00],
    ['afiacao',    'Tesoura de Cabelo',      15.00],
    ['afiacao',    'Tesoura de Cutícula',    12.00],
    ['afiacao',    'Tesoura Cirúrgica',      20.00],
    ['manutencao', 'Alicate de Cutícula',     8.00],
    ['manutencao', 'Tesoura de Cabelo',      12.00],
    ['polimento',  'Qualquer Equipamento',   5.00],
    ['limpeza',    'Qualquer Equipamento',   5.00],
    ['combo',      'Alicate de Cutícula',    18.00],
    ['combo',      'Tesoura de Cabelo',      25.00],
];

$precosExistem = dbQueryOne('SELECT id FROM tabela_precos LIMIT 1');
if (!$precosExistem) {
    foreach ($precos as [$servico, $tipo, $preco]) {
        dbExecute(
            'INSERT INTO tabela_precos (servico, tipo_equipamento, preco) VALUES (?, ?, ?)',
            [$servico, $tipo, $preco]
        );
    }
}

echo '<h2>✅ Banco de dados inicializado com sucesso!</h2>';
echo '<p><strong>Acesso admin:</strong><br>';
echo 'E-mail: admin@afiacaoalmeida.com<br>';
echo 'Senha: Almeida@2025</p>';
echo '<p><strong>⚠️ Altere a senha após o primeiro acesso!</strong></p>';
echo '<p><a href="../admin/login.php">→ Ir para o Login Admin</a></p>';
echo '<p style="color:red;"><strong>Delete ou proteja este arquivo após a inicialização.</strong></p>';

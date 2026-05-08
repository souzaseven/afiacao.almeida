<?php
require_once __DIR__ . '/config.php';

// ── Resposta JSON ─────────────────────────────────────────────────────────────
function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Sanitização ───────────────────────────────────────────────────────────────
function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// ── Formatação ───────────────────────────���────────────────────────────────���───
function formatMoney(float $value): string {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function formatDate(?string $date): string {
    if (!$date || $date === '0000-00-00') return '-';
    return date('d/m/Y', strtotime($date));
}

function formatDateTime(?string $dt): string {
    if (!$dt || $dt === '0000-00-00 00:00:00') return '-';
    return date('d/m/Y H:i', strtotime($dt));
}

// ── Geração de número de OS ─────────────────────────────��─────────────────────
function generateOSNumber(): string {
    $prefix = date('Ym');
    $last = dbQueryOne(
        "SELECT numero_os FROM afiacao_ordens WHERE numero_os LIKE ? ORDER BY id DESC LIMIT 1",
        [$prefix . '%']
    );
    $seq = $last ? (int) substr($last['numero_os'], -4) + 1 : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// ── Labels e classes de status ──────────────────────────��─────────────────────
function getStatusLabel(string $status): string {
    return match($status) {
        'aguardando'  => 'Aguardando Retirada',
        'em_afiacao'  => 'Em Afiação',
        'pronto'      => 'Pronto',
        'entregue'    => 'Entregue',
        'cancelado'   => 'Cancelado',
        default       => ucfirst($status),
    };
}

function getStatusClass(string $status): string {
    return match($status) {
        'aguardando'  => 'warning',
        'em_afiacao'  => 'info',
        'pronto'      => 'success',
        'entregue'    => 'secondary',
        'cancelado'   => 'danger',
        default       => 'secondary',
    };
}

function getEntregaStatusLabel(string $status): string {
    return match($status) {
        'agendado'   => 'Agendado',
        'em_rota'    => 'Em Rota',
        'concluido'  => 'Concluído',
        'reagendado' => 'Reagendado',
        default      => ucfirst($status),
    };
}

function getEntregaStatusClass(string $status): string {
    return match($status) {
        'agendado'   => 'warning',
        'em_rota'    => 'info',
        'concluido'  => 'success',
        'reagendado' => 'secondary',
        default      => 'secondary',
    };
}

// ── WhatsApp link ────────────────────────────────────────────────────────���────
function whatsappLink(string $message = ''): string {
    $msg = $message ?: 'Olá! Gostaria de um orçamento de afiação.';
    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . urlencode($msg);
}

// ── Configurações com cache em memória + fallback para constantes ─────────────
function getConfig(string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];

    try {
        $row = dbQueryOne('SELECT valor FROM afiacao_configuracoes WHERE chave = ?', [$key]);
        $val = ($row && $row['valor'] !== null && $row['valor'] !== '') ? $row['valor'] : '';
    } catch (\Throwable $e) {
        $val = '';
    }

    if ($val === '') {
        $constMap = [
            'empresa_nome'     => defined('COMPANY_NAME')    ? COMPANY_NAME    : '',
            'empresa_telefone' => defined('COMPANY_PHONE')   ? COMPANY_PHONE   : '',
            'empresa_endereco' => defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '',
            'empresa_cidade'   => defined('COMPANY_CITY')    ? COMPANY_CITY    : '',
            'empresa_email'    => defined('COMPANY_EMAIL')   ? COMPANY_EMAIL   : '',
            'empresa_whatsapp' => defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '',
        ];
        $val = $constMap[$key] ?? $default;
    }

    return $cache[$key] = $val;
}

function setConfig(string $key, string $value): void {
    static $cache = [];
    try {
        dbUpdate(
            'INSERT INTO afiacao_configuracoes (chave, valor, atualizado_em) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = NOW()',
            [$key, $value]
        );
        $cache[$key] = $value;
    } catch (\Throwable $e) {
        // Tabela pode não existir ainda
    }
}

// ── Helpers de input ─────────────────────────────────���────────────────────────
function post(string $key, string $default = ''): string {
    return trim($_POST[$key] ?? $default);
}

function get(string $key, string $default = ''): string {
    return trim($_GET[$key] ?? $default);
}

function moneyToFloat(string $value): float {
    $v = preg_replace('/[^0-9,\.]/', '', $value);
    if (substr_count($v, ',') === 1 && substr_count($v, '.') >= 1) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } elseif (substr_count($v, ',') === 1) {
        $v = str_replace(',', '.', $v);
    }
    return (float) $v;
}

// ── Flash messages ─────────────────────────────���──────────────────────────────
function flashSuccess(string $msg): void {
    $_SESSION['flash_success'] = $msg;
}

function flashError(string $msg): void {
    $_SESSION['flash_error'] = $msg;
}

// ── Redirecionamento ───────────────────────��──────────────────────────────────
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── CSRF ────────────────────────────────────────────────────────────��─────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['_token'] ?? '')) {
        flashError('Sessão expirada ou requisição inválida. Tente novamente.');
        redirect(basename($_SERVER['PHP_SELF']));
    }
}

// ── Paginação ───────────────────────────────────────────────────────��─────────
function paginate(string $table, string $where, array $params, int $limit = 30): array {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;
    $total  = dbQueryOne("SELECT COUNT(*) AS n FROM $table WHERE $where", $params)['n'] ?? 0;
    $pages  = (int) ceil($total / $limit);
    return ['page' => $page, 'pages' => $pages, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
}

function paginationHtml(int $page, int $pages, array $extraParams = []): string {
    if ($pages <= 1) return '';
    $params = array_merge(
        array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY),
        $extraParams
    );
    $link = fn($p) => '?' . http_build_query(array_merge($params, ['page' => $p]));
    $html = '<div class="pagination">';
    if ($page > 1)     $html .= '<a href="' . $link(1)        . '" class="btn btn-outline btn-sm" title="Primeira">&laquo;&laquo;</a>';
    if ($page > 1)     $html .= '<a href="' . $link($page - 1) . '" class="btn btn-outline btn-sm">&laquo;</a>';
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        $html .= '<a href="' . $link($i) . '" class="btn btn-sm ' . ($i === $page ? 'btn-primary' : 'btn-outline') . '">' . $i . '</a>';
    }
    if ($page < $pages) $html .= '<a href="' . $link($page + 1) . '" class="btn btn-outline btn-sm">&raquo;</a>';
    if ($page < $pages) $html .= '<a href="' . $link($pages)    . '" class="btn btn-outline btn-sm" title="Última">&raquo;&raquo;</a>';
    $html .= '<span class="pagination-info">Pág. ' . $page . ' de ' . $pages . ' &nbsp;·&nbsp; ' . number_format($pages * 30 > 0 ? $page * 30 : 0) . ' / ' . $pages * 30 . '</span>';
    $html .= '</div>';
    return $html;
}

<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle = 'Ordens de Serviço';
$currentPage = 'os';
$breadcrumb = 'Gestão de ordens de serviço';

// ── Helpers ───────────────────────────────────────────────────────────────────
function gerarNumeroOS(PDO $pdo): string
{
    $ano = date('Y');
    $n = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE YEAR(criado_em)='{$ano}'")->fetchColumn();
    return $ano . '-' . str_pad($n + 1, 4, '0', STR_PAD_LEFT);
}
function badgeOS(string $s): string
{
    return match ($s) {
        'aguardando' => '<span class="badge badge-warning">Aguardando</span>',
        'em_andamento' => '<span class="badge badge-info">Em andamento</span>',
        'pronto' => '<span class="badge badge-success">Pronto</span>',
        'entregue' => '<span class="badge badge-neutral">Entregue</span>',
        'cancelado' => '<span class="badge badge-danger">Cancelado</span>',
        default => '<span class="badge badge-neutral">' . htmlspecialchars($s) . '</span>',
    };
}
function badgePag(string $s): string
{
    return match ($s) {
        'pago' => '<span class="badge badge-success">Pago</span>',
        'parcial' => '<span class="badge badge-warning">Parcial</span>',
        default => '<span class="badge badge-neutral">Pendente</span>',
    };
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $cliente_id = (int) ($_POST['cliente_id'] ?? 0) ?: null;
        $prazo = trim($_POST['prazo'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['aguardando', 'em_andamento', 'pronto', 'entregue', 'cancelado'])
            ? $_POST['status'] : 'aguardando';
        $status_pagamento = in_array($_POST['status_pagamento'] ?? '', ['pendente', 'parcial', 'pago'])
            ? $_POST['status_pagamento'] : 'pendente';
        $tipo_finalizacao = ($_POST['tipo_finalizacao'] ?? '') === 'entrega' ? 'entrega' : 'retirada';
        $taxa_entrega = (float) str_replace(',', '.', str_replace('.', '', $_POST['taxa_entrega'] ?? '0'));
        $observacoes = trim($_POST['observacoes'] ?? '');

        $equipamentos = $_POST['item_equip'] ?? [];
        $servicos_item = $_POST['item_srv'] ?? [];
        $quantidades = $_POST['item_qtd'] ?? [];
        $valores = $_POST['item_val'] ?? [];
        $obs_itens = $_POST['item_obs'] ?? [];

        $valor_total = $taxa_entrega;
        foreach ($quantidades as $i => $qtd) {
            $qtd = max(1, (int) $qtd);
            $val = (float) str_replace(',', '.', str_replace('.', '', $valores[$i] ?? '0'));
            $valor_total += $qtd * $val;
        }

        try {
            $pdo->beginTransaction();

            if ($id > 0) {
                $pdo->prepare("
                    UPDATE ordens_servico SET
                        cliente_id=?, prazo=?, status=?, status_pagamento=?,
                        tipo_finalizacao=?, taxa_entrega=?, valor_total=?, observacoes=?
                    WHERE id=?
                ")->execute([$cliente_id, $prazo, $status, $status_pagamento, $tipo_finalizacao, $taxa_entrega, $valor_total, $observacoes, $id]);
                $pdo->prepare("DELETE FROM itens_os WHERE os_id=?")->execute([$id]);
                $numero = '';
            } else {
                $numero = gerarNumeroOS($pdo);
                $pdo->prepare("
                    INSERT INTO ordens_servico
                        (numero,cliente_id,prazo,status,status_pagamento,tipo_finalizacao,taxa_entrega,valor_total,observacoes)
                    VALUES (?,?,?,?,?,?,?,?,?)
                ")->execute([$numero, $cliente_id, $prazo, $status, $status_pagamento, $tipo_finalizacao, $taxa_entrega, $valor_total, $observacoes]);
                $id = (int) $pdo->lastInsertId();
            }

            $stmtItem = $pdo->prepare("
                INSERT INTO itens_os (os_id,equipamento,servico_id,quantidade,valor_unitario,observacao)
                VALUES (?,?,?,?,?,?)
            ");
            foreach ($equipamentos as $i => $equip) {
                $equip = trim($equip);
                if ($equip === '')
                    continue;
                $qtd = max(1, (int) ($quantidades[$i] ?? 1));
                $val = (float) str_replace(',', '.', str_replace('.', '', $valores[$i] ?? '0'));
                $sid = (int) ($servicos_item[$i] ?? 0) ?: null;
                $obs = trim($obs_itens[$i] ?? '');
                $stmtItem->execute([$id, $equip, $sid, $qtd, $val, $obs]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = $numero
                ? "OS #{$numero} criada com sucesso."
                : 'OS atualizada com sucesso.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Erro ao salvar: ' . $e->getMessage();
        }
        header('Location: os.php');
        exit;
    }

    if ($action === 'status') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = $_POST['novo_status'] ?? '';
        if ($id > 0 && in_array($st, ['aguardando', 'em_andamento', 'pronto', 'entregue', 'cancelado'])) {
            try {
                $pdo->prepare("UPDATE ordens_servico SET status=? WHERE id=?")->execute([$st, $id]);
            } catch (PDOException $e) {
            }
        }
        header('Location: os.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    }

    if ($action === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM ordens_servico WHERE id=?")->execute([$id]);
                $_SESSION['flash_success'] = 'OS excluída.';
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        }
        header('Location: os.php');
        exit;
    }
}

// ── Filtros GET ───────────────────────────────────────────────────────────────
$filtro   = $_GET['status']  ?? 'ativos';
$periodo  = $_GET['periodo'] ?? '';
$data_de  = trim($_GET['data_de']  ?? '');
$data_ate = trim($_GET['data_ate'] ?? '');
$pagina   = max(1, (int)($_GET['p'] ?? 1));
$por_pag  = 50;

$where  = ['1=1'];
$params = [];

if ($filtro === 'ativos') {
    $where[] = "o.status NOT IN ('entregue','cancelado')";
} elseif ($filtro !== 'todos') {
    $where[] = "o.status = ?";
    $params[] = $filtro;
}

if ($periodo === 'hoje') {
    $where[] = "DATE(o.data_entrada) = CURDATE()";
} elseif ($periodo === 'semana') {
    $where[] = "DATE(o.data_entrada) >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($periodo === 'mes') {
    $where[] = "DATE_FORMAT(o.data_entrada,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')";
} elseif ($periodo === 'custom') {
    if ($data_de !== '') { $where[] = "DATE(o.data_entrada) >= ?"; $params[] = $data_de; }
    if ($data_ate !== '') { $where[] = "DATE(o.data_entrada) <= ?"; $params[] = $data_ate; }
}

try {
    $whereStr = implode(' AND ', $where);

    $cntStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM ordens_servico o
         LEFT JOIN afiacao_clientes c ON c.id = o.cliente_id
         WHERE {$whereStr}"
    );
    $cntStmt->execute($params);
    $totalRegistros = (int) $cntStmt->fetchColumn();
    $totalPaginas   = max(1, (int) ceil($totalRegistros / $por_pag));
    $pagina         = min($pagina, $totalPaginas);
    $offset         = ($pagina - 1) * $por_pag;

    $osLista = $pdo->prepare(
        "SELECT o.*, c.nome AS cliente_nome, c.nome_fantasia, c.telefone
         FROM ordens_servico o
         LEFT JOIN afiacao_clientes c ON c.id = o.cliente_id
         WHERE {$whereStr}
         ORDER BY o.id DESC LIMIT {$por_pag} OFFSET {$offset}"
    );
    $osLista->execute($params);
    $osLista = $osLista->fetchAll(PDO::FETCH_ASSOC);

    // Contadores
    $contadores = [];
    $rows = $pdo->query("SELECT status, COUNT(*) n FROM ordens_servico GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r)
        $contadores[$r['status']] = (int) $r['n'];
    $totalAtivos = array_sum(array_filter($contadores, fn($k) => !in_array($k, ['entregue', 'cancelado']), ARRAY_FILTER_USE_KEY));

    // Dados para selects
    $clientes = $pdo->query("SELECT id,nome,nome_fantasia FROM afiacao_clientes WHERE status='ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    $servicos = $pdo->query("SELECT id,nome,preco FROM servicos WHERE ativo=1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Tabelas provavelmente não criadas — mostrar tela de setup
    http_response_code(200);
    $msg = htmlspecialchars($e->getMessage());
    die('<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
    <title>Erro — Afiação Almeida</title>
    <style>body{font-family:Segoe UI,sans-serif;background:#141414;color:#f0f0f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
    .box{background:#1e1e1e;border:1px solid #333;border-radius:12px;padding:2.5rem;max-width:600px;width:100%;}
    h2{color:#F87171;margin-bottom:.5rem;}p{color:#aaa;font-size:.9rem;margin-bottom:1.2rem;}
    code{background:#2a2a2a;padding:6px 12px;border-radius:6px;font-family:monospace;font-size:.82rem;color:#fca5a5;display:block;word-break:break-all;}
    .btn{display:inline-block;background:#C9A84C;color:#141414;padding:.6rem 1.3rem;border-radius:8px;text-decoration:none;font-weight:700;font-size:.88rem;margin-top:1rem;margin-right:.5rem;}
    .btn-ghost{background:#2a2a2a;color:#ddd;}
    </style></head><body><div class="box">
    <h2>&#9888; Erro de banco de dados</h2>
    <p>A página <strong>Ordens de Serviço</strong> encontrou um erro ao acessar o banco de dados MySQL. Verifique se as tabelas foram criadas.</p>
    <code>' . $msg . '</code>
    <br>
    <a class="btn" href="../database/criar_tabelas.php">&#9881; Criar / verificar tabelas</a>
    <a class="btn btn-ghost" href="index.php">&#8592; Voltar ao painel</a>
    </div></body></html>');
}

$topbarActions = '<button class="btn btn-primary btn-sm" id="btnNovaOS">
    <i class="fas fa-plus"></i> Nova OS
</button>';

$extraHead = '<style>
/* ══ Modal ═══════════════════════════════════════════════ */
.modal-overlay {
    position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;
    display:flex;align-items:center;justify-content:center;padding:1rem;
    opacity:0;pointer-events:none;transition:opacity .22s;
}
.modal-overlay.open{opacity:1;pointer-events:auto;}
.modal {
    background:var(--surface-1);border:1px solid var(--border);
    border-radius:var(--radius-xl);width:100%;max-width:900px;max-height:96vh;
    display:flex;flex-direction:column;overflow:hidden;
    transform:scale(.95) translateY(10px);
    transition:transform .25s cubic-bezier(.4,0,.2,1);
    box-shadow:0 24px 64px rgba(0,0,0,.6);
}
.modal-overlay.open .modal{transform:scale(1) translateY(0);}
.modal-head{
    padding:.8rem 1.2rem;border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}
.modal-title{font-size:1rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:.5rem;}
.modal-title i{color:var(--gold);}
.btn-close-modal{background:none;border:none;color:var(--text-muted);font-size:1.1rem;cursor:pointer;
    padding:4px 6px;border-radius:var(--radius-sm);line-height:1;
    transition:color var(--transition),background var(--transition);}
.btn-close-modal:hover{color:var(--danger);background:var(--danger-dim);}
.modal-body{flex:1;padding:.9rem 1.1rem;overflow-y:auto;}
.modal-foot{
    padding:.7rem 1.1rem;border-top:1px solid var(--border);
    display:flex;gap:.6rem;justify-content:space-between;align-items:center;flex-shrink:0;
}
.modal-total{font-size:.95rem;font-weight:700;color:var(--gold);}

/* ══ Layout 2 colunas (topo do modal) ═══════════════════ */
.os-cols{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:.85rem;}
@media(max-width:640px){.os-cols{grid-template-columns:1fr;}}

/* ══ Seção ════════════════════════════════════════════════ */
.fsec{margin-bottom:.75rem;}
.fsec-title{
    font-size:.66rem;font-weight:700;color:var(--rose);text-transform:uppercase;
    letter-spacing:.09em;padding-bottom:.28rem;margin-bottom:.55rem;
    border-bottom:1px solid var(--border);display:block;
}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;}
.form-row-3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:.65rem;}
.form-group{margin-bottom:.6rem;}
.form-group:last-child{margin-bottom:0;}
.form-label{display:block;font-size:.74rem;font-weight:600;color:var(--text-secondary);margin-bottom:.22rem;letter-spacing:.03em;}
.form-label .req{color:var(--danger);}
.modal-body .form-control,.modal-body .form-select{
    width:100%;background:var(--surface-2);border:1px solid var(--border);
    border-radius:var(--radius-md);color:var(--text-primary);font-size:.84rem;
    padding:.42rem .75rem;transition:border-color var(--transition);font-family:inherit;
}
.modal-body .form-control:focus,.modal-body .form-select:focus{
    outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,168,76,.12);
}
textarea.form-control{resize:vertical;min-height:48px;}

/* ══ Tabela de itens ══════════════════════════════════════ */
.itens-wrap{border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden;margin-bottom:.6rem;}
.itens-head{
    display:grid;grid-template-columns:2fr 1.6fr 60px 1fr 1.4fr 36px;gap:.5rem;
    padding:.45rem .7rem;background:var(--surface-2);
    font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;
}
.item-row{
    display:grid;grid-template-columns:2fr 1.6fr 60px 1fr 1.4fr 36px;gap:.5rem;
    padding:.45rem .7rem;border-top:1px solid rgba(255,255,255,.04);align-items:center;
}
.item-row .form-control{padding:.32rem .55rem;font-size:.82rem;}
.item-row select.form-control{font-size:.82rem;}
.btn-del-item{
    width:30px;height:30px;border-radius:var(--radius-sm);border:1px solid var(--border);
    background:var(--surface-2);color:var(--text-muted);cursor:pointer;
    display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;
    transition:all var(--transition);flex-shrink:0;
}
.btn-del-item:hover{border-color:var(--danger);color:var(--danger);background:var(--danger-dim);}
.btn-add-item{font-size:.82rem;}

/* ══ Tabela listagem ══════════════════════════════════════ */
.os-table{width:100%;border-collapse:collapse;}
.os-table th{
    padding:.65rem 1rem;font-size:.71rem;font-weight:700;color:var(--text-muted);
    text-transform:uppercase;letter-spacing:.06em;text-align:left;
    border-bottom:1px solid var(--border);white-space:nowrap;
}
.os-table td{
    padding:.8rem 1rem;font-size:.875rem;color:var(--text-primary);
    border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;
}
.os-table tr:last-child td{border-bottom:none;}
.os-table tr:hover td{background:rgba(255,255,255,.02);}
.num-os{font-weight:700;color:var(--gold);font-family:monospace;font-size:.9rem;}
.cli-nome{font-weight:600;}
.cli-fan{font-size:.75rem;color:var(--text-muted);}
.val-os{font-weight:700;font-size:.92rem;}
.prazo-venc{color:var(--danger);font-weight:600;}
.actions-cell{display:flex;gap:.4rem;align-items:center;}
.btn-icon{
    width:30px;height:30px;border-radius:var(--radius-md);border:1px solid var(--border);
    background:var(--surface-2);color:var(--text-muted);cursor:pointer;
    display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;
    transition:all var(--transition);text-decoration:none;
}
.btn-icon:hover{border-color:var(--gold);color:var(--gold);background:rgba(201,168,76,.08);}
.btn-icon.danger:hover{border-color:var(--danger);color:var(--danger);background:rgba(248,113,113,.08);}

/* ══ Toolbar ══════════════════════════════════════════════ */
.tbl-card{background:var(--surface-1);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;}
.tbl-toolbar{
    display:flex;gap:.7rem;align-items:center;padding:.9rem 1.1rem;
    border-bottom:1px solid var(--border);flex-wrap:wrap;
}
.search-wrap{position:relative;flex:1;min-width:180px;}
.search-wrap i{position:absolute;left:.7rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.85rem;}
.search-wrap .form-control{padding-left:2.1rem;}
.filter-tabs{display:flex;gap:.2rem;flex-wrap:wrap;}
.ftab{
    padding:.3rem .75rem;border-radius:var(--radius-md);font-size:.76rem;font-weight:600;
    color:var(--text-muted);text-decoration:none;transition:all var(--transition);white-space:nowrap;
}
.ftab:hover{background:var(--surface-2);color:var(--text-primary);}
.ftab.active{background:rgba(201,168,76,.15);color:var(--gold);}
.empty-state{text-align:center;padding:2.5rem 1rem;color:var(--text-muted);}
.empty-state i{font-size:1.8rem;margin-bottom:.6rem;opacity:.35;display:block;}

/* ══ Status select inline ═════════════════════════════════ */
.status-select{
    background:transparent;border:none;color:inherit;font-size:inherit;
    cursor:pointer;font-family:inherit;outline:none;
}

/* ══ Busca de cliente (searchable select) ════════════════ */
.cs-wrap{position:relative;}
#csDropdown{
    position:fixed;
    background:var(--surface-2);border:1px solid var(--border);
    border-radius:var(--radius-md);max-height:210px;overflow-y:auto;
    z-index:9999;display:none;
    box-shadow:0 8px 24px rgba(0,0,0,.55);
    min-width:200px;
}
.cs-opt{
    padding:.48rem .75rem;cursor:pointer;font-size:.84rem;
    color:var(--text-primary);transition:background var(--transition);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.cs-opt:hover{background:rgba(201,168,76,.12);color:var(--gold);}
.cs-empty{padding:.45rem .75rem;font-size:.82rem;color:var(--text-muted);}

/* ══ KPIs ═════════════════════════════════════════════════ */
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.85rem;margin-bottom:1.25rem;}
.kpi{background:var(--surface-1);border:1px solid var(--border);border-radius:var(--radius-lg);padding:.85rem 1rem;}
.kpi-val{font-size:1.5rem;font-weight:700;color:var(--text-primary);line-height:1;}
.kpi-lbl{font-size:.72rem;color:var(--text-muted);margin-top:.25rem;}

/* ══ Modal de confirmação ═════════════════════════════════ */
.confirm-overlay{
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);
    z-index:1100;align-items:center;justify-content:center;padding:1rem;
}
.confirm-overlay.open{display:flex;}
.confirm-box{
    background:var(--surface-1);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:1.75rem 1.5rem;
    max-width:360px;width:100%;text-align:center;
    transform:scale(.92);opacity:0;
    transition:transform .18s ease,opacity .18s ease;
}
.confirm-overlay.open .confirm-box{transform:scale(1);opacity:1;}
.confirm-icon{font-size:2rem;color:var(--danger);margin-bottom:.75rem;}
.confirm-title{font-size:1rem;font-weight:700;color:var(--text-primary);margin-bottom:.4rem;}
.confirm-msg{font-size:.875rem;color:var(--text-secondary);margin-bottom:1.5rem;line-height:1.5;}
.confirm-msg strong{color:var(--text-primary);}
.confirm-actions{display:flex;gap:.75rem;justify-content:center;}
</style>';

require_once 'includes/header.php';
?>

<!-- KPIs -->
<div class="kpis">
    <div class="kpi">
        <div class="kpi-val"><?= $totalAtivos ?></div>
        <div class="kpi-lbl">Em aberto</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--warning)"><?= $contadores['aguardando'] ?? 0 ?></div>
        <div class="kpi-lbl">Aguardando</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--info)"><?= $contadores['em_andamento'] ?? 0 ?></div>
        <div class="kpi-lbl">Em andamento</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--success)"><?= $contadores['pronto'] ?? 0 ?></div>
        <div class="kpi-lbl">Prontos</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--text-muted)"><?= $contadores['entregue'] ?? 0 ?></div>
        <div class="kpi-lbl">Entregues</div>
    </div>
</div>

<!-- Tabela -->
<div class="tbl-card">
    <div class="tbl-toolbar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="form-control"
                placeholder="Buscar por OS ou cliente..." autocomplete="off">
        </div>
        <div class="filter-tabs">
            <?php
            $tabs = [
                'ativos'       => "Em aberto ({$totalAtivos})",
                'aguardando'   => 'Aguardando',
                'em_andamento' => 'Andamento',
                'pronto'       => 'Prontos',
                'entregue'     => 'Entregues',
                'cancelado'    => 'Cancelados',
                'todos'        => 'Todos',
            ];
            foreach ($tabs as $k => $lbl): ?>
                <a href="?status=<?= $k ?>"
                    class="ftab<?= $filtro === $k ? ' active' : '' ?>"><?= $lbl ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Filtro de período -->
    <div style="padding:.5rem 1.1rem;border-top:1px solid var(--border);display:flex;gap:.35rem;align-items:center;flex-wrap:wrap">
        <span style="font-size:.69rem;color:var(--text-muted);font-weight:700;letter-spacing:.06em;margin-right:.25rem">PERÍODO</span>
        <?php
        $baseUrl = '?status=' . urlencode($filtro);
        $periodos = ['' => 'Todos', 'hoje' => 'Hoje', 'semana' => 'Semana', 'mes' => 'Este mês'];
        foreach ($periodos as $pk => $pl): ?>
            <a href="<?= $baseUrl . ($pk ? '&periodo=' . $pk : '') ?>"
               class="ftab<?= $periodo === $pk ? ' active' : '' ?>"><?= $pl ?></a>
        <?php endforeach; ?>
        <form method="GET" style="display:flex;gap:.35rem;align-items:center;margin-left:.3rem">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filtro) ?>">
            <input type="hidden" name="periodo" value="custom">
            <input type="date" name="data_de" value="<?= htmlspecialchars($data_de) ?>"
                class="form-control" style="padding:.28rem .5rem;font-size:.78rem;width:130px">
            <span style="color:var(--text-muted);font-size:.75rem">até</span>
            <input type="date" name="data_ate" value="<?= htmlspecialchars($data_ate) ?>"
                class="form-control" style="padding:.28rem .5rem;font-size:.78rem;width:130px">
            <button type="submit" class="btn btn-ghost btn-sm" style="padding:.28rem .6rem">
                <i class="fas fa-filter"></i>
            </button>
        </form>
    </div>

    <?php if (empty($osLista)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <p>Nenhuma OS encontrada.</p>
        </div>
    <?php else: ?>
        <table class="os-table">
            <thead>
                <tr>
                    <th>OS</th>
                    <th>Cliente</th>
                    <th>Status</th>
                    <th>Pagamento</th>
                    <th>Prazo</th>
                    <th>Total</th>
                    <th style="text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($osLista as $os):
                    $hoje = date('Y-m-d');
                    $vencida = $os['prazo'] && $os['prazo'] < $hoje && !in_array($os['status'], ['entregue', 'cancelado']);
                    $osData = htmlspecialchars(json_encode([
                        'id' => $os['id'],
                        'numero' => $os['numero'],
                        'cliente_id' => $os['cliente_id'],
                        'prazo' => $os['prazo'] ?? '',
                        'status' => $os['status'],
                        'status_pagamento' => $os['status_pagamento'],
                        'tipo_finalizacao' => $os['tipo_finalizacao'],
                        'taxa_entrega' => number_format((float) $os['taxa_entrega'], 2, ',', '.'),
                        'observacoes' => $os['observacoes'] ?? '',
                    ]), ENT_QUOTES);
                    ?>
                    <tr>
                        <td><span class="num-os">#<?= htmlspecialchars($os['numero']) ?></span></td>
                        <td>
                            <div class="cli-nome"><?= htmlspecialchars($os['cliente_nome'] ?? '—') ?></div>
                            <?php if (!empty($os['nome_fantasia'])): ?>
                                <div class="cli-fan"><?= htmlspecialchars($os['nome_fantasia']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= badgeOS($os['status']) ?></td>
                        <td><?= badgePag($os['status_pagamento']) ?></td>
                        <td>
                            <?php if ($os['prazo']): ?>
                                <span class="<?= $vencida ? 'prazo-venc' : '' ?>">
                                    <?= date('d/m/Y', strtotime($os['prazo'])) ?>
                                    <?= $vencida ? '<i class="fas fa-exclamation-triangle" style="font-size:.75rem"></i>' : '' ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="val-os">R$ <?= number_format((float) $os['valor_total'], 2, ',', '.') ?></span></td>
                        <td>
                            <div class="actions-cell" style="justify-content:flex-end">
                                <!-- WhatsApp -->
                                <?php
                                $tel = preg_replace('/\D/', '', $os['telefone'] ?? '');
                                if (strlen($tel) === 11) $tel = '55' . $tel;
                                $waMensagem = rawurlencode("Olá " . ($os['cliente_nome'] ?? 'cliente') . "! Segue atualização sobre sua OS #{$os['numero']}.");
                                if ($tel): ?>
                                    <a href="https://wa.me/<?= $tel ?>?text=<?= $waMensagem ?>"
                                        target="_blank" class="btn-icon" title="WhatsApp"
                                        style="color:#25D366;border-color:#25D36625">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- Imprimir -->
                                <a href="os_print.php?id=<?= $os['id'] ?>" target="_blank"
                                    class="btn-icon" title="Imprimir OS">
                                    <i class="fas fa-print"></i>
                                </a>

                                <!-- Avançar status -->
                                <?php
                                $proximo = match ($os['status']) {
                                    'aguardando' => ['em_andamento', 'Iniciar', 'fas fa-play', 'var(--info)'],
                                    'em_andamento' => ['pronto', 'Concluir', 'fas fa-check', 'var(--success)'],
                                    'pronto' => ['entregue', 'Entregar', 'fas fa-truck', 'var(--text-muted)'],
                                    default => null,
                                };
                                if ($proximo): ?>
                                    <form method="POST" style="display:contents">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="id" value="<?= $os['id'] ?>">
                                        <input type="hidden" name="novo_status" value="<?= $proximo[0] ?>">
                                        <button type="submit" class="btn-icon" title="<?= $proximo[1] ?>"
                                            style="color:<?= $proximo[3] ?>;border-color:<?= $proximo[3] ?>20">
                                            <i class="<?= $proximo[2] ?>"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Editar -->
                                <button class="btn-icon" title="Editar" data-os="<?= $osData ?>">
                                    <i class="fas fa-pen"></i>
                                </button>

                                <!-- Excluir -->
                                <form method="POST" style="display:contents">
                                    <input type="hidden" name="action" value="excluir">
                                    <input type="hidden" name="id" value="<?= $os['id'] ?>">
                                    <button
                                        type="button"
                                        class="btn-icon danger"
                                        title="Excluir"
                                        onclick="confirmDelete(this.closest('form'), '<?= htmlspecialchars(addslashes($os['numero'])) ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPaginas > 1): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.1rem;border-top:1px solid var(--border);">
            <span style="font-size:.78rem;color:var(--text-muted)">
                Página <?= $pagina ?> de <?= $totalPaginas ?> &mdash; <?= number_format($totalRegistros) ?> registro<?= $totalRegistros !== 1 ? 's' : '' ?>
            </span>
            <div style="display:flex;gap:.3rem">
                <?php if ($pagina > 1): ?>
                    <a href="?status=<?= urlencode($filtro) ?>&p=<?= $pagina - 1 ?>" class="btn btn-ghost btn-sm"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($pg = max(1, $pagina - 2); $pg <= min($totalPaginas, $pagina + 2); $pg++): ?>
                    <a href="?status=<?= urlencode($filtro) ?>&p=<?= $pg ?>"
                        class="btn btn-sm <?= $pg === $pagina ? 'btn-primary' : 'btn-ghost' ?>"><?= $pg ?></a>
                <?php endfor; ?>
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="?status=<?= urlencode($filtro) ?>&p=<?= $pagina + 1 ?>" class="btn btn-ghost btn-sm"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- ══ Modal Nova/Editar OS ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal" id="modal">

        <div class="modal-head">
            <span class="modal-title" id="modalTitle"><i class="fas fa-file-invoice"></i> Nova OS</span>
            <button type="button" class="btn-close-modal" id="btnCloseModal"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" action="os.php" id="formOS" novalidate>
            <div class="modal-body">
                <input type="hidden" name="action" value="salvar">
                <input type="hidden" name="id" id="fId" value="0">

                <!-- Topo 2 colunas -->
                <div class="os-cols">
                    <!-- Coluna esquerda -->
                    <div>
                        <div class="fsec">
                            <span class="fsec-title"><i class="fas fa-user"></i> Cliente e Prazo</span>
                            <div class="form-group">
                                <label class="form-label" for="csInput">Cliente</label>
                                <div class="cs-wrap" id="csWrap">
                                    <input type="text" id="csInput" class="form-control" placeholder="Buscar cliente..."
                                        autocomplete="off">
                                    <input type="hidden" id="fCliente" name="cliente_id">
                                    <div class="cs-dropdown" id="csDropdown"></div>
                                </div>
                            </div>
                            <div class="form-row-2">
                                <div class="form-group" style="margin-bottom:0">
                                    <label class="form-label" for="fPrazo">Prazo de entrega</label>
                                    <input type="date" id="fPrazo" name="prazo" class="form-control">
                                </div>
                                <div class="form-group" style="margin-bottom:0">
                                    <label class="form-label" for="fTipoFin">Finalização</label>
                                    <select id="fTipoFin" name="tipo_finalizacao" class="form-control">
                                        <option value="retirada">Retirada</option>
                                        <option value="entrega">Entrega</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna direita -->
                    <div>
                        <div class="fsec">
                            <span class="fsec-title"><i class="fas fa-sliders-h"></i> Status e Pagamento</span>
                            <div class="form-row-2">
                                <div class="form-group">
                                    <label class="form-label" for="fStatus">Status da OS</label>
                                    <select id="fStatus" name="status" class="form-control">
                                        <option value="aguardando">Aguardando</option>
                                        <option value="em_andamento">Em andamento</option>
                                        <option value="pronto">Pronto</option>
                                        <option value="entregue">Entregue</option>
                                        <option value="cancelado">Cancelado</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="fPag">Pagamento</label>
                                    <select id="fPag" name="status_pagamento" class="form-control">
                                        <option value="pendente">Pendente</option>
                                        <option value="parcial">Parcial</option>
                                        <option value="pago">Pago</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label" for="fTaxa">Taxa de entrega (R$)</label>
                                <input type="text" id="fTaxa" name="taxa_entrega" class="form-control"
                                    placeholder="0,00" autocomplete="off" value="0,00">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Itens -->
                <div class="fsec">
                    <span class="fsec-title"><i class="fas fa-list"></i> Itens / Equipamentos</span>
                    <div class="itens-wrap">
                        <div class="itens-head">
                            <span>Equipamento</span>
                            <span>Serviço</span>
                            <span>Qtd</span>
                            <span>Valor unit.</span>
                            <span>Observação</span>
                            <span></span>
                        </div>
                        <div id="itensBody"></div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-add-item" id="btnAddItem">
                        <i class="fas fa-plus"></i> Adicionar item
                    </button>
                </div>

                <!-- Observações -->
                <div class="fsec" style="margin-bottom:0">
                    <span class="fsec-title"><i class="fas fa-sticky-note"></i> Observações</span>
                    <textarea id="fObs" name="observacoes" class="form-control" rows="2"
                        placeholder="Anotações sobre a ordem..."></textarea>
                </div>
            </div><!-- /.modal-body -->

            <div class="modal-foot">
                <span class="modal-total">Total: <span id="totalDisplay">R$ 0,00</span></span>
                <div style="display:flex;gap:.6rem">
                    <button type="button" class="btn btn-ghost btn-sm" id="btnCancelModal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save"></i> Salvar OS
                    </button>
                </div>
            </div>
        </form>

    </div><!-- /.modal -->
</div><!-- /.modal-overlay -->

<?php
?>
<!-- Modal de confirmação de exclusão -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="confirm-title">Excluir OS?</div>
        <p class="confirm-msg">Você está prestes a excluir a OS <strong id="confirmOsNum"></strong>. Todos os itens serão removidos.</p>
        <div class="confirm-actions">
            <button class="btn btn-ghost btn-sm" onclick="closeConfirm()">Cancelar</button>
            <button class="btn btn-danger btn-sm" id="confirmBtn">
                <i class="fas fa-trash-alt"></i> Excluir
            </button>
        </div>
    </div>
</div>
<?php
// Dados de serviços para o JS
$servicosArr = array_map(fn($s) => [
    'id' => (int) $s['id'],
    'nome' => $s['nome'],
    'preco' => (float) $s['preco'],
], $servicos);
$servicosJSON = json_encode($servicosArr, JSON_UNESCAPED_UNICODE);

// Dados de clientes para o JS
$clientesArr = array_map(fn($c) => [
    'id' => (int) $c['id'],
    'label' => $c['nome'] . ($c['nome_fantasia'] ? ' — ' . $c['nome_fantasia'] : ''),
], $clientes);
$clientesJSON = json_encode($clientesArr, JSON_UNESCAPED_UNICODE);

// Injeção de dados separada do código JS
$extraScripts = '<script>var OS_SERVICOS=' . $servicosJSON . '; var OS_CLIENTES=' . $clientesJSON . ';</script>';

// Código JS em heredoc single-quoted — PHP não interpola NADA aqui
$extraScripts .= <<<'JS'
<script>
var overlay    = document.getElementById('modalOverlay');
var modalTitle = document.getElementById('modalTitle');
function F(id) { return document.getElementById(id); }

// ── Busca de cliente ────────────────────────────────────────
// Move o dropdown para <body> para escapar do overflow:hidden do modal
var csDropdown = F('csDropdown');
document.body.appendChild(csDropdown);

function csPosition() {
    var rect = F('csInput').getBoundingClientRect();
    csDropdown.style.top   = (rect.bottom + 3) + 'px';
    csDropdown.style.left  = rect.left + 'px';
    csDropdown.style.width = rect.width + 'px';
}
function csRender(q) {
    csPosition();
    var list = q.trim() === ''
        ? OS_CLIENTES
        : OS_CLIENTES.filter(function(c) {
            return c.label.toLowerCase().indexOf(q.toLowerCase()) >= 0;
          });
    csDropdown.innerHTML = '';
    if (!list.length) {
        csDropdown.innerHTML = '<div class="cs-empty">Nenhum cliente encontrado</div>';
    } else {
        list.slice(0, 80).forEach(function(c) {
            var opt = document.createElement('div');
            opt.className = 'cs-opt';
            opt.textContent = c.label;
            opt.addEventListener('mousedown', function(e) {
                e.preventDefault();
                F('fCliente').value = c.id;
                F('csInput').value  = c.label;
                csDropdown.style.display = 'none';
            });
            csDropdown.appendChild(opt);
        });
    }
    csDropdown.style.display = 'block';
}
function csSetCliente(id) {
    var c = id ? OS_CLIENTES.find(function(c){ return String(c.id) === String(id); }) : null;
    F('fCliente').value = id || '';
    F('csInput').value  = c ? c.label : '';
}
function csClose() { csDropdown.style.display = 'none'; }

F('csInput').addEventListener('focus', function() { csRender(this.value); });
F('csInput').addEventListener('input', function() {
    F('fCliente').value = '';
    csRender(this.value);
});
document.addEventListener('click', function(e) {
    if (!e.target.closest('#csWrap') && e.target !== csDropdown && !csDropdown.contains(e.target)) {
        csClose();
    }
});

// Monta options do select de serviços a partir dos dados PHP
function buildSrvOpts(selectedId) {
    var html = '<option value="">— Serviço —</option>';
    OS_SERVICOS.forEach(function(s) {
        var sel  = String(s.id) === String(selectedId) ? ' selected' : '';
        var nome = String(s.nome)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        html += '<option value="' + s.id + '" data-preco="' + s.preco + '"' + sel + '>' + nome + '</option>';
    });
    return html;
}

function parseMoeda(s) {
    return parseFloat(String(s).replace(/\./g,'').replace(',','.')) || 0;
}
function formatMoeda(n) {
    return n.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
}
function calcTotal() {
    var taxa  = parseMoeda(F('fTaxa').value);
    var total = taxa;
    document.querySelectorAll('#itensBody .item-row').forEach(function(row) {
        var qtd = parseInt(row.querySelector('[name="item_qtd[]"]').value) || 0;
        var val = parseMoeda(row.querySelector('[name="item_val[]"]').value);
        total += qtd * val;
    });
    F('totalDisplay').textContent = 'R$ ' + formatMoeda(total);
}
function maskMoeda(el) {
    if (!el || el._masked) return;
    el._masked = true;
    el.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') return;
        if (e.key === 'Backspace') {
            e.preventDefault();
            var digits = this.value.replace(/\D/g, '').slice(0, -1);
            this.value = digits ? formatMoeda(parseInt(digits, 10) / 100) : '';
            calcTotal();
            return;
        }
        if (!/^\d$/.test(e.key)) { e.preventDefault(); return; }
        e.preventDefault();
        var digits = (this.value.replace(/\D/g, '') + e.key).replace(/^0+/, '') || '0';
        this.value = formatMoeda(parseInt(digits, 10) / 100);
        calcTotal();
    });
    el.addEventListener('focus', function() {
        var len = this.value.length;
        this.setSelectionRange(len, len);
    });
}

function novaLinhaItem(equip, srvId, qtd, val, obs) {
    equip = equip || '';
    srvId = srvId || '';
    qtd   = qtd   || 1;
    val   = val   || '0,00';
    obs   = obs   || '';

    var d = document.createElement('div');
    d.className = 'item-row';
    d.innerHTML =
        '<input type="text" name="item_equip[]" class="form-control" placeholder="Ex: Tesoura Iwasaki">' +
        '<select name="item_srv[]" class="form-control item-srv"></select>' +
        '<input type="number" name="item_qtd[]" class="form-control item-qtd" min="1" style="text-align:center">' +
        '<input type="text" name="item_val[]" class="form-control item-val" placeholder="0,00" autocomplete="off">' +
        '<input type="text" name="item_obs[]" class="form-control" placeholder="Observação...">' +
        '<button type="button" class="btn-del-item" title="Remover"><i class="fas fa-times"></i></button>';

    d.querySelector('[name="item_equip[]"]').value = equip;
    d.querySelector('.item-srv').innerHTML         = buildSrvOpts(srvId);
    d.querySelector('[name="item_qtd[]"]').value   = qtd;
    d.querySelector('[name="item_val[]"]').value   = val;
    d.querySelector('[name="item_obs[]"]').value   = obs;

    d.querySelector('.item-srv').addEventListener('change', function() {
        var srv = OS_SERVICOS.find(function(s){ return String(s.id) === this.value; }, this);
        if (srv) d.querySelector('[name="item_val[]"]').value = formatMoeda(srv.preco);
        calcTotal();
    });
    d.querySelector('[name="item_qtd[]"]').addEventListener('input', calcTotal);
    maskMoeda(d.querySelector('[name="item_val[]"]'));
    d.querySelector('.btn-del-item').addEventListener('click', function() {
        d.remove(); calcTotal();
    });
    return d;
}

F('btnAddItem').addEventListener('click', function() {
    var linha = novaLinhaItem();
    F('itensBody').appendChild(linha);
    linha.querySelector('[name="item_equip[]"]').focus();
});

maskMoeda(F('fTaxa'));

async function openModal(data) {
    var edit = !!(data && data.id);
    modalTitle.innerHTML = edit
        ? '<i class="fas fa-file-invoice"></i> Editar OS #' + data.numero
        : '<i class="fas fa-file-invoice"></i> Nova OS';

    F('fId').value      = (data && data.id)               || 0;
    csSetCliente((data && data.cliente_id) || '');
    F('fPrazo').value   = (data && data.prazo)            || '';
    F('fStatus').value  = (data && data.status)           || 'aguardando';
    F('fPag').value     = (data && data.status_pagamento) || 'pendente';
    F('fTipoFin').value = (data && data.tipo_finalizacao) || 'retirada';
    F('fTaxa').value    = (data && data.taxa_entrega)     || '0,00';
    F('fObs').value     = (data && data.observacoes)      || '';
    F('itensBody').innerHTML = '';

    if (edit) {
        try {
            var res   = await fetch('os_itens.php?os_id=' + data.id);
            var itens = await res.json();
            itens.forEach(function(item) {
                var v = parseFloat(item.valor_unitario || 0).toFixed(2).replace('.',',');
                F('itensBody').appendChild(
                    novaLinhaItem(item.equipamento, item.servico_id, item.quantidade, v, item.observacao)
                );
            });
        } catch(e) { console.error('Erro ao carregar itens:', e); }
    }

    if (!F('itensBody').children.length) {
        F('itensBody').appendChild(novaLinhaItem());
    }

    calcTotal();
    overlay.classList.add('open');
    setTimeout(function() { F('csInput').focus(); }, 80);
}

function closeModal() {
    overlay.classList.remove('open');
    csClose();
}

// ── Confirmação de exclusão ─────────────────────────────────
var confirmOverlay  = document.getElementById('confirmOverlay');
var pendingDelForm  = null;

function confirmDelete(form, numero) {
    pendingDelForm = form;
    document.getElementById('confirmOsNum').textContent = '#' + numero;
    confirmOverlay.classList.add('open');
}
function closeConfirm() {
    confirmOverlay.classList.remove('open');
    pendingDelForm = null;
}
document.getElementById('confirmBtn').addEventListener('click', function() {
    if (pendingDelForm) pendingDelForm.submit();
});
confirmOverlay.addEventListener('click', function(e) { if (e.target === confirmOverlay) closeConfirm(); });

F('btnNovaOS').addEventListener('click', function() { openModal(null); });
F('btnCloseModal').addEventListener('click', closeModal);
F('btnCancelModal').addEventListener('click', closeModal);
overlay.addEventListener('click', function(e) { if (e.target === overlay) closeModal(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeConfirm(); closeModal(); } });

document.querySelectorAll('[data-os]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        try { openModal(JSON.parse(btn.dataset.os)); } catch(e) { openModal(null); }
    });
});

// ── Busca em tempo real ─────────────────────────────────────
var searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('.os-table tbody tr').forEach(function(row) {
            row.style.display = (!q || row.textContent.toLowerCase().indexOf(q) >= 0) ? '' : 'none';
        });
    });
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { this.value = ''; this.dispatchEvent(new Event('input')); }
    });
}
</script>
JS;

require_once 'includes/footer.php';

<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle   = 'Clientes';
$currentPage = 'clientes';
$breadcrumb  = 'Cadastro e gestão de clientes';

// ── AJAX: histórico do cliente ────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'historico') {
    header('Content-Type: application/json; charset=utf-8');
    $cid = (int)($_GET['id'] ?? 0);
    if (!$cid) { echo json_encode(['error' => 'ID inválido']); exit; }
    try {
        $stmt = $pdo->prepare("
            SELECT numero, data_entrada, status, status_pagamento, valor_total
            FROM ordens_servico WHERE cliente_id = ?
            ORDER BY data_entrada DESC LIMIT 50
        ");
        $stmt->execute([$cid]);
        $os = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'os'    => $os,
            'total' => array_sum(array_column($os, 'valor_total')),
            'count' => count($os),
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Exportar CSV ──────────────────────────────────────────────────────────────
if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM para Excel
    $cols = ['ID','Nome','Nome Fantasia','Tipo','Status','Telefone','Telefone 2',
             'E-mail','CEP','Rua','Número','Complemento','Bairro','Cidade','UF',
             'Preferência Entrega','Melhor Horário','Aniversário','Observações'];
    echo implode(';', $cols) . "\r\n";
    try {
        $stmt = $pdo->query("SELECT * FROM afiacao_clientes ORDER BY nome");
        while ($r = $stmt->fetch()) {
            $row = [
                $r->id, $r->nome, $r->nome_fantasia ?? '', $r->tipo ?? '',
                $r->status ?? '', $r->telefone ?? '', $r->telefone2 ?? '',
                $r->email ?? '', $r->cep ?? '', $r->rua ?? '',
                $r->numero ?? '', $r->complemento ?? '', $r->bairro ?? '',
                $r->cidade ?? '', $r->uf ?? '',
                $r->preferencia_entrega ?? '', $r->melhor_horario ?? '',
                $r->aniversario ?? '',
                str_replace(["\r\n","\n","\r"], ' ', $r->observacoes ?? ''),
            ];
            echo implode(';', array_map(fn($v) => '"' . str_replace('"','""',$v) . '"', $row)) . "\r\n";
        }
    } catch (PDOException $e) {}
    exit;
}

// ── Ações POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'salvar') {
        $id                 = (int)($_POST['id'] ?? 0);
        $nome               = trim($_POST['nome']               ?? '');
        $nome_fantasia      = trim($_POST['nome_fantasia']      ?? '');
        $tipo               = in_array($_POST['tipo'] ?? '', ['autonoma','salao','outro']) ? $_POST['tipo'] : 'autonoma';
        $status             = ($_POST['status'] ?? 'ativo') === 'inativo' ? 'inativo' : 'ativo';
        $telefone           = trim($_POST['telefone']           ?? '');
        $telefone2          = trim($_POST['telefone2']          ?? '');
        $contato_nome       = trim($_POST['contato_nome']       ?? '');
        $email              = trim($_POST['email']              ?? '');
        $cep                = trim($_POST['cep']                ?? '');
        $rua                = trim($_POST['rua']                ?? '');
        $numero             = trim($_POST['numero']             ?? '');
        $complemento        = trim($_POST['complemento']        ?? '');
        $bairro             = trim($_POST['bairro']             ?? '');
        $cidade             = trim($_POST['cidade']             ?? '');
        $uf                 = strtoupper(trim($_POST['uf']      ?? ''));
        $link_maps          = trim($_POST['link_maps']          ?? '');
        $preferencia_entrega = in_array($_POST['preferencia_entrega'] ?? '', ['retirada','entrega','ambas'])
                                ? $_POST['preferencia_entrega'] : 'retirada';
        $melhor_horario     = trim($_POST['melhor_horario']     ?? '');
        $aniversario        = trim($_POST['aniversario']        ?? '');
        $observacoes        = trim($_POST['observacoes']        ?? '');

        if ($nome === '') {
            $_SESSION['flash_error'] = 'O nome do cliente é obrigatório.';
            header('Location: clientes.php');
            exit;
        }

        $fields = [$nome,$nome_fantasia,$tipo,$status,$telefone,$telefone2,$contato_nome,$email,
                   $cep,$rua,$numero,$complemento,$bairro,$cidade,$uf,
                   $link_maps,$preferencia_entrega,$melhor_horario,$aniversario,$observacoes];

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE afiacao_clientes SET
                        nome=?, nome_fantasia=?, tipo=?, status=?,
                        telefone=?, telefone2=?, contato_nome=?, email=?,
                        cep=?, rua=?, numero=?, complemento=?, bairro=?, cidade=?, uf=?,
                        link_maps=?, preferencia_entrega=?, melhor_horario=?, aniversario=?, observacoes=?
                    WHERE id=?
                ");
                $stmt->execute([...$fields, $id]);
                $_SESSION['flash_success'] = "Cliente «{$nome}» atualizado com sucesso.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO afiacao_clientes
                        (nome, nome_fantasia, tipo, status,
                         telefone, telefone2, contato_nome, email,
                         cep, rua, numero, complemento, bairro, cidade, uf,
                         link_maps, preferencia_entrega, melhor_horario, aniversario, observacoes)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute($fields);
                $_SESSION['flash_success'] = "Cliente «{$nome}» cadastrado com sucesso.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Erro ao salvar: ' . $e->getMessage();
        }
        header('Location: clientes.php');
        exit;
    }

    if ($action === 'importar') {
        $file = $_FILES['csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Erro ao receber o arquivo CSV.';
            header('Location: clientes.php'); exit;
        }
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $_SESSION['flash_error'] = 'Não foi possível abrir o arquivo.';
            header('Location: clientes.php'); exit;
        }
        // Detectar separador (primeiro usa ; depois ,)
        $firstLine = fgets($handle);
        rewind($handle);
        $sep = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';
        fgetcsv($handle, 0, $sep); // pular cabeçalho
        $ins = $err = 0;
        $stmt = $pdo->prepare("
            INSERT INTO afiacao_clientes
                (nome,nome_fantasia,tipo,status,telefone,telefone2,email,
                 cep,rua,numero,complemento,bairro,cidade,uf,
                 preferencia_entrega,melhor_horario,aniversario,observacoes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        while (($row = fgetcsv($handle, 0, $sep)) !== false) {
            // Suporta tanto CSV exportado por nós (com ID na col 0) quanto sem ID
            $offset = (isset($row[1]) && strlen($row[1]) > 0 && !is_numeric($row[0])) ? -1 : 0;
            $nome = trim($row[1 + $offset] ?? $row[0] ?? '');
            if ($nome === '') continue;
            $tipo = trim($row[3 + $offset] ?? 'autonoma');
            if (!in_array($tipo, ['autonoma','salao','outro'])) $tipo = 'autonoma';
            $status = trim($row[4 + $offset] ?? 'ativo');
            if (!in_array($status, ['ativo','inativo'])) $status = 'ativo';
            try {
                $stmt->execute([
                    $nome,
                    trim($row[2 + $offset] ?? ''),
                    $tipo, $status,
                    trim($row[5 + $offset] ?? ''),
                    trim($row[6 + $offset] ?? ''),
                    trim($row[7 + $offset] ?? ''),
                    trim($row[8 + $offset] ?? ''),
                    trim($row[9 + $offset] ?? ''),
                    trim($row[10 + $offset] ?? ''),
                    trim($row[11 + $offset] ?? ''),
                    trim($row[12 + $offset] ?? ''),
                    trim($row[13 + $offset] ?? ''),
                    strtoupper(trim($row[14 + $offset] ?? '')),
                    trim($row[15 + $offset] ?? 'retirada'),
                    trim($row[16 + $offset] ?? ''),
                    trim($row[17 + $offset] ?? ''),
                    trim($row[18 + $offset] ?? ''),
                ]);
                $ins++;
            } catch (PDOException $e) { $err++; }
        }
        fclose($handle);
        $_SESSION['flash_success'] = "{$ins} cliente(s) importado(s)" . ($err ? " — {$err} erro(s) ignorado(s)" : '') . '.';
        header('Location: clientes.php'); exit;
    }

    if ($action === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM ordens_servico WHERE cliente_id = ?");
                $stmt->execute([$id]);
                $tem_os = (int)$stmt->fetchColumn() > 0;

                if ($tem_os) {
                    $pdo->prepare("UPDATE afiacao_clientes SET status='inativo' WHERE id=?")->execute([$id]);
                    $_SESSION['flash_success'] = 'Cliente inativado — possui OS vinculadas e não pode ser excluído.';
                } else {
                    $pdo->prepare("DELETE FROM afiacao_clientes WHERE id=?")->execute([$id]);
                    $_SESSION['flash_success'] = 'Cliente excluído com sucesso.';
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        }
        header('Location: clientes.php');
        exit;
    }
}

// ── Filtros GET ───────────────────────────────────────────────────────────────
$q      = trim($_GET['q'] ?? '');
$filtro = in_array($_GET['status'] ?? '', ['ativo','inativo']) ? $_GET['status'] : 'todos';

// ── Estatísticas ──────────────────────────────────────────────────────────────
try {
    $stats = $pdo->query("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN status='ativo'   THEN 1 ELSE 0 END) AS ativos,
               SUM(CASE WHEN status='inativo' THEN 1 ELSE 0 END) AS inativos
          FROM afiacao_clientes
    ")->fetch();
} catch (PDOException $e) {
    $stats = (object)['total' => 0, 'ativos' => 0, 'inativos' => 0];
}

// ── Listagem ──────────────────────────────────────────────────────────────────
$clientes = [];
try {
    $where  = ['1=1'];
    $params = [];
    if ($q !== '') {
        $where[]  = "(nome LIKE ? OR nome_fantasia LIKE ? OR telefone LIKE ? OR email LIKE ? OR cidade LIKE ?)";
        $like     = "%{$q}%";
        $params   = array_merge($params, [$like,$like,$like,$like,$like]);
    }
    if ($filtro !== 'todos') {
        $where[]  = "status = ?";
        $params[] = $filtro;
    }
    $stmt = $pdo->prepare(
        "SELECT * FROM afiacao_clientes WHERE " . implode(' AND ', $where) . " ORDER BY nome ASC LIMIT 200"
    );
    $stmt->execute($params);
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {}

// ── Helpers ───────────────────────────────────────────────────────────────────
function tipoLabel(string $t): string {
    return match ($t) { 'salao' => 'Salão', 'outro' => 'Outro', default => 'Autônoma' };
}
function tipoBadgeClass(string $t): string {
    return match ($t) { 'salao' => 'badge-info', 'outro' => 'badge-rose', default => 'badge-gold' };
}
function prefLabel(string $p): string {
    return match ($p) { 'entrega' => 'Entrega', 'ambas' => 'Ambas', default => 'Retirada' };
}
function waLink(string $tel): string {
    $d = preg_replace('/\D/', '', $tel);
    return strlen($d) >= 10 ? 'https://wa.me/55' . $d : '';
}
function clientInitials(string $nome): string {
    $arr = array_values(array_filter(explode(' ', trim($nome))));
    return strtoupper(($arr[0][0] ?? '') . ($arr[1][0] ?? ''));
}
function localidade(object $c): string {
    $partes = array_filter([$c->cidade ?? '', $c->uf ?? '']);
    return $partes ? implode(' / ', $partes) : ($c->endereco ?? '');
}

$topbarActions = '
<a href="clientes.php?exportar=1" class="btn btn-ghost btn-sm" title="Exportar todos os clientes em CSV">
    <i class="fas fa-file-csv"></i> Exportar CSV
</a>
<button type="button" class="btn btn-ghost btn-sm" id="btnImportar" title="Importar clientes via CSV">
    <i class="fas fa-upload"></i> Importar
</button>
<button type="button" class="btn btn-primary btn-sm" id="btnNovo">
    <i class="fas fa-plus"></i> Novo cliente
</button>';

include 'includes/header.php';
?>

<style>
/* ── Colunas responsivas ─────────────────────────────────── */
@media(max-width:900px)  { .col-md { display:none; } }
@media(max-width:640px)  { .col-sm { display:none; } }

/* ── Chips de filtro ─────────────────────────────────────── */
.filter-bar {
    display:flex; gap:.6rem; margin-bottom:1.25rem;
    flex-wrap:wrap; align-items:center;
}
.filter-chip {
    display:inline-flex; align-items:center; gap:.45rem;
    padding:.38rem .9rem;
    background:var(--surface-1); border:1px solid var(--border);
    border-radius:var(--radius-full); font-size:.8rem; font-weight:500;
    color:var(--text-secondary); text-decoration:none; white-space:nowrap;
    transition:border-color var(--transition),color var(--transition);
}
.filter-chip:hover,.filter-chip.active { border-color:var(--gold); color:var(--gold); }
.filter-chip .num { font-weight:700; color:var(--text-primary); }
.filter-chip.active .num { color:var(--gold); }

/* ── Busca ───────────────────────────────────────────────── */
.search-bar { display:flex; gap:.6rem; margin-bottom:1.25rem; flex-wrap:wrap; align-items:center; }
.search-wrap { position:relative; flex:1; min-width:220px; }
.search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%);
    color:var(--text-muted); font-size:.88rem; pointer-events:none; }
.search-wrap .form-control { padding-left:36px; }

/* ── Avatar ──────────────────────────────────────────────── */
.c-avatar {
    width:36px; height:36px; border-radius:50%;
    background:var(--gold-dim); color:var(--gold);
    border:1px solid rgba(201,168,76,.25);
    display:flex; align-items:center; justify-content:center;
    font-size:.72rem; font-weight:700; flex-shrink:0;
}
.c-info  { display:flex; align-items:center; gap:.7rem; }
.c-name  { font-weight:600; color:var(--text-primary); font-size:.9rem; line-height:1.3; }
.c-fan   { font-size:.75rem; color:var(--gold); margin-top:1px; }
.c-sub   { font-size:.75rem; color:var(--text-muted); margin-top:1px; }

/* ── Modal centralizado ──────────────────────────────────── */
.drawer-overlay {
    position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:200;
    display:flex; align-items:center; justify-content:center; padding:1rem;
    opacity:0; pointer-events:none; transition:opacity .22s;
}
.drawer-overlay.open { opacity:1; pointer-events:auto; }

.drawer {
    background:var(--surface-1); border:1px solid var(--border);
    border-radius:var(--radius-xl); z-index:201;
    width:100%; max-width:880px; max-height:96vh;
    display:flex; flex-direction:column; overflow:hidden;
    transform:scale(.95) translateY(12px);
    transition:transform .25s cubic-bezier(.4,0,.2,1);
    box-shadow:0 24px 64px rgba(0,0,0,.55);
}
.drawer-overlay.open .drawer { transform:scale(1) translateY(0); }

.drawer-head {
    padding:.8rem 1.25rem; border-bottom:1px solid var(--border);
    display:flex; align-items:center; justify-content:space-between; flex-shrink:0;
}
.drawer-title { font-size:1rem; font-weight:600; color:var(--text-primary);
    display:flex; align-items:center; gap:.5rem; }
.drawer-title i { color:var(--gold); }
.btn-close-drawer { background:none; border:none; color:var(--text-muted);
    font-size:1.1rem; cursor:pointer; padding:4px 6px; border-radius:var(--radius-sm);
    transition:color var(--transition),background var(--transition); line-height:1; }
.btn-close-drawer:hover { color:var(--danger); background:var(--danger-dim); }

.drawer-body { flex:1; padding:.9rem 1.1rem; overflow-y:auto; }
.drawer-foot {
    padding:.7rem 1.1rem; border-top:1px solid var(--border);
    display:flex; gap:.6rem; justify-content:flex-end; flex-shrink:0;
}

/* ── Layout 2 colunas ────────────────────────────────────── */
.drawer-columns { display:grid; grid-template-columns:1fr 1fr; gap:1.1rem; }
.drawer-col { min-width:0; }
@media(max-width:660px) { .drawer-columns { grid-template-columns:1fr; } }

/* ── Seções do formulário ────────────────────────────────── */
.fsec { margin-bottom:.75rem; }
.fsec-title {
    font-size:.66rem; font-weight:700; color:var(--rose);
    text-transform:uppercase; letter-spacing:.09em;
    padding-bottom:.28rem; margin-bottom:.6rem;
    border-bottom:1px solid var(--border); display:block;
}
.form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; }
.form-row-3 { display:grid; grid-template-columns:2fr 1fr 1fr; gap:.65rem; }
.form-row-4 { display:grid; grid-template-columns:1fr 1fr 2fr 1fr; gap:.65rem; }
@media(max-width:520px) {
    .form-row-2,.form-row-3,.form-row-4 { grid-template-columns:1fr; }
}
.form-group { margin-bottom:.6rem; }
.form-group:last-child { margin-bottom:0; }
textarea.form-control { resize:vertical; min-height:50px; }

/* ── Inputs compactos no modal ───────────────────────────── */
.drawer-body .form-control,
.drawer-body .form-select {
    padding:.42rem .75rem;
    font-size:.84rem;
}
.drawer-body .form-label {
    font-size:.74rem;
    margin-bottom:.22rem;
}

/* ── Modal confirmação ───────────────────────────────────── */
.confirm-overlay {
    position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:300;
    display:flex; align-items:center; justify-content:center;
    opacity:0; pointer-events:none; transition:opacity .2s;
}
.confirm-overlay.open { opacity:1; pointer-events:auto; }
.confirm-box {
    background:var(--surface-1); border:1px solid var(--border);
    border-radius:var(--radius-lg); padding:1.75rem;
    max-width:380px; width:90%;
    transform:scale(.94); transition:transform .2s;
}
.confirm-overlay.open .confirm-box { transform:scale(1); }
.confirm-icon { width:48px; height:48px; border-radius:50%;
    background:var(--danger-dim); color:var(--danger);
    display:flex; align-items:center; justify-content:center;
    font-size:1.25rem; margin-bottom:1rem; }
.confirm-title { font-weight:700; color:var(--text-primary); margin-bottom:.35rem; }
.confirm-text  { font-size:.85rem; color:var(--text-muted); line-height:1.55; margin-bottom:1.5rem; }
.confirm-btns  { display:flex; gap:.75rem; justify-content:flex-end; }

/* ── Badge de preferência ────────────────────────────────── */
.pref-icon { font-size:.75rem; }
</style>

<!-- ── Filtros ────────────────────────────────────────────────────────────── -->
<div class="filter-bar">
    <a href="clientes.php<?= $q ? '?q='.urlencode($q) : '' ?>"
       class="filter-chip <?= $filtro==='todos' ? 'active' : '' ?>">
        <i class="fas fa-users"></i> Todos <span class="num"><?= (int)$stats->total ?></span>
    </a>
    <a href="clientes.php?status=ativo<?= $q ? '&q='.urlencode($q) : '' ?>"
       class="filter-chip <?= $filtro==='ativo' ? 'active' : '' ?>">
        <i class="fas fa-circle" style="color:var(--success);font-size:.5rem"></i> Ativos
        <span class="num"><?= (int)$stats->ativos ?></span>
    </a>
    <a href="clientes.php?status=inativo<?= $q ? '&q='.urlencode($q) : '' ?>"
       class="filter-chip <?= $filtro==='inativo' ? 'active' : '' ?>">
        <i class="fas fa-circle" style="color:var(--text-muted);font-size:.5rem"></i> Inativos
        <span class="num"><?= (int)$stats->inativos ?></span>
    </a>
</div>

<!-- ── Busca ──────────────────────────────────────────────────────────────── -->
<form method="GET" action="clientes.php" class="search-bar">
    <?php if ($filtro !== 'todos'): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($filtro) ?>">
    <?php endif; ?>
    <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" name="q" class="form-control"
               placeholder="Buscar por nome, salão, telefone, cidade..."
               value="<?= htmlspecialchars($q) ?>" autocomplete="off">
    </div>
    <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-search"></i> Buscar</button>
    <?php if ($q !== '' || $filtro !== 'todos'): ?>
        <a href="clientes.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Limpar</a>
    <?php endif; ?>
</form>

<!-- ── Tabela ─────────────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-users"></i>
            <?php if ($q !== ''): ?>Resultados para "<?= htmlspecialchars($q) ?>"
            <?php elseif ($filtro === 'ativo'): ?>Clientes ativos
            <?php elseif ($filtro === 'inativo'): ?>Clientes inativos
            <?php else: ?>Todos os clientes<?php endif; ?>
            <span class="badge badge-neutral" style="margin-left:.3rem"><?= count($clientes) ?></span>
        </span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th class="col-sm">Tipo</th>
                    <th class="col-sm">Telefone</th>
                    <th class="col-md">Cidade / UF</th>
                    <th class="col-md">Entrega</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <p><?= $q !== '' ? 'Nenhum resultado para "'.htmlspecialchars($q).'".' : 'Nenhum cliente cadastrado.' ?></p>
                        </div>
                    </td></tr>
                <?php else: foreach ($clientes as $c):
                    $wa  = waLink($c->telefone ?? '');
                    $loc = localidade($c);
                    $enc = fn(string $v) => htmlspecialchars($v, ENT_QUOTES);
                    $cliente_json = htmlspecialchars(json_encode([
                        'id'                 => $c->id,
                        'nome'               => $c->nome,
                        'nome_fantasia'      => $c->nome_fantasia      ?? '',
                        'tipo'               => $c->tipo               ?? 'autonoma',
                        'status'             => $c->status             ?? 'ativo',
                        'telefone'           => $c->telefone           ?? '',
                        'telefone2'          => $c->telefone2          ?? '',
                        'contato_nome'       => $c->contato_nome       ?? '',
                        'email'              => $c->email              ?? '',
                        'cep'                => $c->cep                ?? '',
                        'rua'                => $c->rua                ?? '',
                        'numero'             => $c->numero             ?? '',
                        'complemento'        => $c->complemento        ?? '',
                        'bairro'             => $c->bairro             ?? '',
                        'cidade'             => $c->cidade             ?? '',
                        'uf'                 => $c->uf                 ?? '',
                        'link_maps'          => $c->link_maps          ?? '',
                        'preferencia_entrega'=> $c->preferencia_entrega ?? 'retirada',
                        'melhor_horario'     => $c->melhor_horario     ?? '',
                        'aniversario'        => $c->aniversario        ?? '',
                        'observacoes'        => $c->observacoes        ?? '',
                    ]), ENT_QUOTES);
                ?>
                    <tr>
                        <td>
                            <div class="c-info">
                                <div class="c-avatar"><?= clientInitials($c->nome) ?></div>
                                <div>
                                    <div class="c-name"><?= htmlspecialchars($c->nome) ?></div>
                                    <?php if (!empty($c->nome_fantasia)): ?>
                                        <div class="c-fan"><i class="fas fa-store" style="font-size:.65rem;opacity:.7"></i> <?= htmlspecialchars($c->nome_fantasia) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c->contato_nome)): ?>
                                        <div class="c-sub"><i class="fas fa-user-tie" style="font-size:.6rem;opacity:.6"></i> <?= htmlspecialchars($c->contato_nome) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="col-sm">
                            <span class="badge <?= tipoBadgeClass($c->tipo ?? 'autonoma') ?>">
                                <?= tipoLabel($c->tipo ?? 'autonoma') ?>
                            </span>
                        </td>
                        <td class="col-sm" style="color:var(--text-secondary);font-size:.88rem">
                            <?= $c->telefone ? htmlspecialchars($c->telefone) : '<span class="text-muted text-xs">—</span>' ?>
                            <?php if (!empty($c->telefone2)): ?>
                                <div class="c-sub"><?= htmlspecialchars($c->telefone2) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="col-md" style="font-size:.85rem;color:var(--text-secondary)">
                            <?= $loc ? htmlspecialchars($loc) : '<span class="text-muted text-xs">—</span>' ?>
                        </td>
                        <td class="col-md">
                            <?php $pref = $c->preferencia_entrega ?? 'retirada'; ?>
                            <span class="badge <?= $pref==='entrega' ? 'badge-info' : ($pref==='ambas' ? 'badge-gold' : 'badge-neutral') ?>">
                                <?= prefLabel($pref) ?>
                            </span>
                        </td>
                        <td>
                            <?= $c->status==='ativo'
                                ? '<span class="badge badge-success">Ativo</span>'
                                : '<span class="badge badge-neutral">Inativo</span>' ?>
                        </td>
                        <td>
                            <div class="flex-center gap-sm">
                                <button type="button" class="btn-action btn-hist"
                                    title="Histórico de OS"
                                    data-id="<?= $c->id ?>"
                                    data-nome="<?= htmlspecialchars($c->nome, ENT_QUOTES) ?>">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button type="button" class="btn-action btn-edit"
                                    title="Editar"
                                    data-cliente="<?= $cliente_json ?>">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <?php if ($wa): ?>
                                    <a href="<?= $wa ?>" target="_blank" class="btn-action wa" title="WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn-action danger btn-delete"
                                    title="Excluir"
                                    data-id="<?= $c->id ?>"
                                    data-nome="<?= htmlspecialchars($c->nome, ENT_QUOTES) ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal: Novo / Editar cliente ──────────────────────────────────────── -->
<div class="drawer-overlay" id="drawerOverlay">
<div class="drawer" id="drawer">

    <div class="drawer-head">
        <span class="drawer-title" id="drawerTitle">
            <i class="fas fa-user-plus"></i> Novo cliente
        </span>
        <button type="button" class="btn-close-drawer" id="btnCloseDrawer" title="Fechar">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <form method="POST" action="clientes.php" id="formCliente" novalidate>
    <div class="drawer-body">
        <input type="hidden" name="action" value="salvar">
        <input type="hidden" name="id" id="fId">

        <div class="drawer-columns">

            <!-- ══ Coluna esquerda: Identificação + Contato ══ -->
            <div class="drawer-col">

                <div class="fsec">
                    <span class="fsec-title"><i class="fas fa-id-card"></i> Identificação</span>

                    <div class="form-group">
                        <label class="form-label" for="fNome">Nome completo <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="fNome" name="nome" class="form-control"
                               placeholder="Ex: Maria Aparecida" required autocomplete="off">
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="fFantasia">Nome fantasia / Salão</label>
                            <input type="text" id="fFantasia" name="nome_fantasia" class="form-control"
                                   placeholder="Ex: Studio X Beauty">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="fTipo">Tipo</label>
                            <select id="fTipo" name="tipo" class="form-control">
                                <option value="autonoma">Autônoma</option>
                                <option value="salao">Salão</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label" for="fStatus">Status</label>
                            <select id="fStatus" name="status" class="form-control">
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label" for="fAniv">Aniversário (DD/MM)</label>
                            <input type="text" id="fAniv" name="aniversario" class="form-control"
                                   placeholder="15/08" maxlength="5" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="fsec" style="margin-bottom:0">
                    <span class="fsec-title"><i class="fas fa-phone"></i> Contato</span>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="fTel">WhatsApp / Telefone 1</label>
                            <input type="text" id="fTel" name="telefone" class="form-control"
                                   placeholder="(65) 99999-9999" maxlength="20" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="fTel2">Telefone 2</label>
                            <input type="text" id="fTel2" name="telefone2" class="form-control"
                                   placeholder="(65) 99999-9999" maxlength="20" autocomplete="off">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label" for="fContato">Pessoa de contato</label>
                            <input type="text" id="fContato" name="contato_nome" class="form-control"
                                   placeholder="Ex: Responsável do salão">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label" for="fEmail">E-mail</label>
                            <input type="email" id="fEmail" name="email" class="form-control"
                                   placeholder="cliente@email.com">
                        </div>
                    </div>
                </div>

            </div><!-- /.drawer-col esquerda -->

            <!-- ══ Coluna direita: Endereço + Atendimento + Obs ══ -->
            <div class="drawer-col">

                <div class="fsec">
                    <span class="fsec-title"><i class="fas fa-map-marker-alt"></i> Endereço</span>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="fCep">CEP</label>
                            <div style="display:flex;gap:.4rem">
                                <input type="text" id="fCep" name="cep" class="form-control"
                                       placeholder="78300-000" maxlength="9" autocomplete="off">
                                <button type="button" id="btnBuscaCep" class="btn btn-ghost btn-sm" title="Buscar CEP">
                                    <i class="fas fa-search" id="iconBuscaCep"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="fUf">UF</label>
                            <select id="fUf" name="uf" class="form-control">
                                <option value="">—</option>
                                <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                    <option value="<?= $uf ?>"><?= $uf ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label" for="fRua">Rua / Avenida</label>
                            <input type="text" id="fRua" name="rua" class="form-control" placeholder="Rua das Flores">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="fNum">Número</label>
                            <input type="text" id="fNum" name="numero" class="form-control" placeholder="123">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="fCompl">Complemento</label>
                            <input type="text" id="fCompl" name="complemento" class="form-control" placeholder="Ap. 2">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label" for="fBairro">Bairro</label>
                            <input type="text" id="fBairro" name="bairro" class="form-control" placeholder="Centro">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label" for="fCidade">Cidade</label>
                            <input type="text" id="fCidade" name="cidade" class="form-control" placeholder="Tangará da Serra">
                        </div>
                    </div>
                </div>

                <div class="fsec">
                    <span class="fsec-title"><i class="fas fa-truck"></i> Atendimento e Entrega</span>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="fPref">Preferência de entrega</label>
                            <select id="fPref" name="preferencia_entrega" class="form-control">
                                <option value="retirada">Retirada no local</option>
                                <option value="entrega">Entrega a domicílio</option>
                                <option value="ambas">Ambas</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="fHorario">Melhor horário</label>
                            <input type="text" id="fHorario" name="melhor_horario" class="form-control"
                                   placeholder="Ex: manhã, 9h–12h">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="fMaps">Link Google Maps</label>
                        <div style="display:flex;gap:.4rem">
                            <input type="url" id="fMaps" name="link_maps" class="form-control"
                                   placeholder="https://maps.google.com/...">
                            <a id="btnVerMaps" href="#" target="_blank"
                               class="btn btn-ghost btn-sm" title="Abrir no Maps" style="flex-shrink:0;display:none">
                                <i class="fas fa-map-marked-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="fsec" style="margin-bottom:0">
                    <span class="fsec-title"><i class="fas fa-sticky-note"></i> Observações</span>
                    <textarea id="fObs" name="observacoes" class="form-control" rows="2"
                              placeholder="Preferências, histórico, anotações importantes..."></textarea>
                </div>

            </div><!-- /.drawer-col direita -->

        </div><!-- /.drawer-columns -->

    </div><!-- /.drawer-body -->

    <div class="drawer-foot">
        <button type="button" class="btn btn-ghost btn-sm" id="btnCancelDrawer">Cancelar</button>
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save"></i> Salvar cliente
        </button>
    </div>
    </form>

</div><!-- /.drawer -->
</div><!-- /.drawer-overlay -->

<!-- ── Modal: Confirmar exclusão ─────────────────────────────────────────── -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="confirm-title">Excluir cliente?</div>
        <p class="confirm-text">
            Deseja excluir <strong id="confirmNome" style="color:var(--text-primary)"></strong>?<br>
            Se houver OS vinculadas, o cliente será apenas <strong>inativado</strong>.
        </p>
        <div class="confirm-btns">
            <button type="button" class="btn btn-ghost btn-sm" id="btnCancelConfirm">Cancelar</button>
            <form method="POST" action="clientes.php" style="display:inline">
                <input type="hidden" name="action" value="excluir">
                <input type="hidden" name="id" id="confirmId">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash-alt"></i> Confirmar exclusão
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ── Modal: Histórico do cliente ───────────────────────────────────────── -->
<div class="drawer-overlay" id="histOverlay">
<div class="drawer" id="histDrawer" style="max-width:680px">
    <div class="drawer-head">
        <span class="drawer-title" id="histTitle"><i class="fas fa-history"></i> Histórico</span>
        <button type="button" class="btn-close-drawer" id="btnCloseHist"><i class="fas fa-times"></i></button>
    </div>
    <div class="drawer-body" id="histBody" style="min-height:180px">
        <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Carregando...</p></div>
    </div>
</div>
</div>

<!-- ── Modal: Importar CSV ────────────────────────────────────────────────── -->
<div class="confirm-overlay" id="importOverlay">
    <div class="confirm-box" style="max-width:440px">
        <div class="confirm-icon" style="background:rgba(201,168,76,.12);color:var(--gold)">
            <i class="fas fa-upload"></i>
        </div>
        <div class="confirm-title">Importar clientes via CSV</div>
        <p class="confirm-text">
            Selecione um arquivo <strong>.csv</strong> para importar.<br>
            Use o mesmo formato do <strong>Exportar CSV</strong> como modelo.<br>
            Separador aceito: <code>;</code> ou <code>,</code>. Novos registros apenas.
        </p>
        <form method="POST" action="clientes.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="importar">
            <input type="file" name="csv" accept=".csv,text/csv"
                class="form-control" style="margin-bottom:.9rem" required>
            <div class="confirm-btns">
                <button type="button" class="btn btn-ghost btn-sm" id="btnCancelImport">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-upload"></i> Importar
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
// ── Referências ───────────────────────────────────────────────────────────────
const drawer         = document.getElementById('drawer');
const drawerOverlay  = document.getElementById('drawerOverlay');
const drawerTitle    = document.getElementById('drawerTitle');
const confirmOverlay = document.getElementById('confirmOverlay');
const btnVerMaps     = document.getElementById('btnVerMaps');

const F = id => document.getElementById(id);

// ── Abrir / Fechar drawer ─────────────────────────────────────────────────────
function openDrawer(d = null) {
    const edit = !!d?.id;
    drawerTitle.innerHTML = edit
        ? '<i class="fas fa-user-edit"></i> Editar cliente'
        : '<i class="fas fa-user-plus"></i> Novo cliente';

    F('fId').value       = d?.id                  ?? '';
    F('fNome').value     = d?.nome                 ?? '';
    F('fFantasia').value = d?.nome_fantasia        ?? '';
    F('fTipo').value     = d?.tipo                 ?? 'autonoma';
    F('fStatus').value   = d?.status               ?? 'ativo';
    F('fAniv').value     = d?.aniversario          ?? '';
    F('fTel').value      = d?.telefone             ?? '';
    F('fTel2').value     = d?.telefone2            ?? '';
    F('fContato').value  = d?.contato_nome         ?? '';
    F('fEmail').value    = d?.email                ?? '';
    F('fCep').value      = d?.cep                  ?? '';
    F('fUf').value       = d?.uf                   ?? '';
    F('fRua').value      = d?.rua                  ?? '';
    F('fNum').value      = d?.numero               ?? '';
    F('fCompl').value    = d?.complemento          ?? '';
    F('fBairro').value   = d?.bairro               ?? '';
    F('fCidade').value   = d?.cidade               ?? '';
    F('fMaps').value     = d?.link_maps            ?? '';
    F('fPref').value     = d?.preferencia_entrega  ?? 'retirada';
    F('fHorario').value  = d?.melhor_horario       ?? '';
    F('fObs').value      = d?.observacoes          ?? '';

    const maps = d?.link_maps ?? '';
    btnVerMaps.href         = maps || '#';
    btnVerMaps.style.display = maps ? '' : 'none';

    drawerOverlay.classList.add('open');
    requestAnimationFrame(() => F('fNome').focus());
}

function closeDrawer() { drawerOverlay.classList.remove('open'); }

F('btnNovo')?.addEventListener('click', () => openDrawer());
F('btnCloseDrawer').addEventListener('click', closeDrawer);
F('btnCancelDrawer').addEventListener('click', closeDrawer);
drawerOverlay.addEventListener('click', e => { if (e.target === drawerOverlay) closeDrawer(); });

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        try { openDrawer(JSON.parse(btn.dataset.cliente)); }
        catch(e) { openDrawer(); }
    });
});

// Atualizar link Maps
F('fMaps').addEventListener('input', function() {
    const v = this.value.trim();
    btnVerMaps.href         = v || '#';
    btnVerMaps.style.display = v ? '' : 'none';
});

// ── Modal exclusão ────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        F('confirmNome').textContent = btn.dataset.nome;
        F('confirmId').value         = btn.dataset.id;
        confirmOverlay.classList.add('open');
    });
});
F('btnCancelConfirm').addEventListener('click', () => confirmOverlay.classList.remove('open'));
confirmOverlay.addEventListener('click', e => { if (e.target === confirmOverlay) confirmOverlay.classList.remove('open'); });

// ── Máscara de telefone ───────────────────────────────────────────────────────
function maskPhone(el) {
    el.addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'').slice(0,11);
        if (v.length >= 7) v = v.length===11
            ? v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3')
            : v.replace(/(\d{2})(\d{4})(\d{4})/,'($1) $2-$3');
        else if (v.length > 2) v = v.replace(/(\d{2})(\d+)/,'($1) $2');
        this.value = v;
    });
}
maskPhone(F('fTel'));
maskPhone(F('fTel2'));

// ── Máscara CEP ───────────────────────────────────────────────────────────────
F('fCep').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,8);
    if (v.length > 5) v = v.slice(0,5)+'-'+v.slice(5);
    this.value = v;
});

// ── Máscara aniversário DD/MM ─────────────────────────────────────────────────
F('fAniv').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,4);
    if (v.length > 2) v = v.slice(0,2)+'/'+v.slice(2);
    this.value = v;
});

// ── Busca CEP (ViaCEP) ────────────────────────────────────────────────────────
async function buscarCep() {
    const cep = F('fCep').value.replace(/\D/g,'');
    if (cep.length !== 8) { alert('CEP inválido. Digite os 8 dígitos.'); return; }

    const icon = F('iconBuscaCep');
    icon.className = 'fas fa-spinner fa-spin';
    F('btnBuscaCep').disabled = true;

    try {
        const res  = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await res.json();
        if (data.erro) { alert('CEP não encontrado.'); return; }

        F('fRua').value    = data.logradouro || '';
        F('fBairro').value = data.bairro     || '';
        F('fCidade').value = data.localidade || '';
        F('fUf').value     = data.uf         || '';
        F('fNum').focus();
    } catch(e) {
        alert('Não foi possível consultar o CEP. Verifique sua conexão.');
    } finally {
        icon.className = 'fas fa-search';
        F('btnBuscaCep').disabled = false;
    }
}

F('btnBuscaCep').addEventListener('click', buscarCep);
F('fCep').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); buscarCep(); } });

// ── Escape fecha tudo ─────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeDrawer();
    confirmOverlay.classList.remove('open');
    F('histOverlay').classList.remove('open');
    F('importOverlay').classList.remove('open');
});

// ── Importar CSV ──────────────────────────────────────────────────────────────
const importOverlay = F('importOverlay');
F('btnImportar')?.addEventListener('click', () => importOverlay.classList.add('open'));
F('btnCancelImport').addEventListener('click', () => importOverlay.classList.remove('open'));
importOverlay.addEventListener('click', e => { if (e.target === importOverlay) importOverlay.classList.remove('open'); });

// ── Histórico do cliente ──────────────────────────────────────────────────────
const histOverlay = F('histOverlay');

function fmt(v) {
    return 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}
function fmtDate(s) {
    try { return new Date(s.replace(' ', 'T')).toLocaleDateString('pt-BR'); } catch(e) { return s; }
}
function statusBadge(s) {
    var m = { aguardando:'badge-warning', em_andamento:'badge-info', pronto:'badge-success', entregue:'badge-neutral', cancelado:'badge-danger' };
    var l = { aguardando:'Aguardando', em_andamento:'Andamento', pronto:'Pronto', entregue:'Entregue', cancelado:'Cancelado' };
    return '<span class="badge '+(m[s]||'badge-neutral')+'">'+(l[s]||s)+'</span>';
}
function pagBadge(s) {
    var m = { pago:'badge-success', parcial:'badge-warning' };
    var l = { pago:'Pago', parcial:'Parcial', pendente:'Pendente' };
    return '<span class="badge '+(m[s]||'badge-neutral')+'">'+(l[s]||s)+'</span>';
}

document.querySelectorAll('.btn-hist').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id   = btn.dataset.id;
        const nome = btn.dataset.nome;
        F('histTitle').innerHTML = '<i class="fas fa-history"></i> Histórico — ' + nome;
        F('histBody').innerHTML  = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Carregando...</p></div>';
        histOverlay.classList.add('open');
        try {
            const res  = await fetch('clientes.php?ajax=historico&id=' + id);
            const data = await res.json();
            if (data.error) {
                F('histBody').innerHTML = '<p style="color:var(--danger);padding:1rem">' + data.error + '</p>';
                return;
            }
            if (!data.count) {
                F('histBody').innerHTML = '<div class="empty-state"><i class="fas fa-file-invoice"></i><p>Nenhuma OS encontrada.</p></div>';
                return;
            }
            var last = data.os[0];
            var html = '<div style="display:flex;gap:.8rem;margin-bottom:1rem;flex-wrap:wrap">'
                + '<div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-md);padding:.55rem .9rem;flex:1;min-width:110px">'
                + '<div style="font-size:.68rem;color:var(--text-muted);margin-bottom:2px">Total de OS</div>'
                + '<div style="font-size:1.5rem;font-weight:800">'+data.count+'</div></div>'
                + '<div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-md);padding:.55rem .9rem;flex:1;min-width:110px">'
                + '<div style="font-size:.68rem;color:var(--text-muted);margin-bottom:2px">Total gasto</div>'
                + '<div style="font-size:1.5rem;font-weight:800;color:var(--success)">'+fmt(data.total)+'</div></div>'
                + '<div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-md);padding:.55rem .9rem;flex:1;min-width:110px">'
                + '<div style="font-size:.68rem;color:var(--text-muted);margin-bottom:2px">Última visita</div>'
                + '<div style="font-size:1.1rem;font-weight:700">'+fmtDate(last.data_entrada)+'</div></div>'
                + '</div>';
            html += '<table style="width:100%;border-collapse:collapse;font-size:.85rem">'
                + '<thead><tr>'
                + ['OS','Data','Status','Pgto','Total'].map(function(h,i){
                    return '<th style="padding:6px 8px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.71rem;text-transform:uppercase;letter-spacing:.05em;text-align:'+(i===4?'right':'left')+'">'+h+'</th>';
                  }).join('')
                + '</tr></thead><tbody>';
            data.os.forEach(function(os) {
                html += '<tr>'
                    + '<td style="padding:7px 8px;border-bottom:1px solid rgba(255,255,255,.04);font-weight:700;color:var(--gold);font-family:monospace">#'+os.numero+'</td>'
                    + '<td style="padding:7px 8px;border-bottom:1px solid rgba(255,255,255,.04);color:var(--text-secondary)">'+fmtDate(os.data_entrada)+'</td>'
                    + '<td style="padding:7px 8px;border-bottom:1px solid rgba(255,255,255,.04)">'+statusBadge(os.status)+'</td>'
                    + '<td style="padding:7px 8px;border-bottom:1px solid rgba(255,255,255,.04)">'+pagBadge(os.status_pagamento)+'</td>'
                    + '<td style="padding:7px 8px;border-bottom:1px solid rgba(255,255,255,.04);text-align:right;font-weight:600">'+fmt(os.valor_total)+'</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            F('histBody').innerHTML = html;
        } catch(e) {
            F('histBody').innerHTML = '<p style="color:var(--danger);padding:1rem">Erro ao carregar histórico.</p>';
        }
    });
});

F('btnCloseHist').addEventListener('click', () => histOverlay.classList.remove('open'));
histOverlay.addEventListener('click', e => { if (e.target === histOverlay) histOverlay.classList.remove('open'); });
</script>
JS;

include 'includes/footer.php';
?>

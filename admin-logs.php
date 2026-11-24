<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$conexao_pdo = db();
$usuario_logado = exigir_papel($conexao_pdo, 'admin');

$por_pagina = 50;
$filtro_usuario = trim((string) ($_GET['usuario'] ?? ''));
$filtro_acao = trim((string) ($_GET['acao'] ?? ''));
$filtro_data = normalizar_data($_GET['data'] ?? null) ?? '';

$condicoes = [];
$parametros = [];

if ($filtro_usuario !== '') {
    $condicoes[] = '(u.nome_completo LIKE ? OR l.usuario_cpf LIKE ?)';
    $parametros[] = '%' . $filtro_usuario . '%';
    $parametros[] = '%' . $filtro_usuario . '%';
}

if ($filtro_acao !== '') {
    $condicoes[] = 'l.acao = ?';
    $parametros[] = $filtro_acao;
}

if ($filtro_data !== '') {
    $condicoes[] = 'DATE(l.data_hora) = ?';
    $parametros[] = $filtro_data;
}

$onde = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

$comando = $conexao_pdo->prepare(
    "SELECT COUNT(*) FROM log l LEFT JOIN usuario u ON u.cpf = l.usuario_cpf $onde"
);
$comando->execute($parametros);
$total = (int) $comando->fetchColumn();

$total_paginas = max(1, (int) ceil($total / $por_pagina));
$pagina = min($total_paginas, max(1, (int) ($_GET['pagina'] ?? 1)));
$deslocamento = ($pagina - 1) * $por_pagina;

$comando = $conexao_pdo->prepare(
    "SELECT l.data_hora, l.acao, l.descricao, l.usuario_cpf, u.nome_completo
     FROM log l
     LEFT JOIN usuario u ON u.cpf = l.usuario_cpf
     $onde
     ORDER BY l.id DESC
     LIMIT $por_pagina OFFSET $deslocamento"
);
$comando->execute($parametros);
$logs = $comando->fetchAll();

$acoes = $conexao_pdo->query('SELECT DISTINCT acao FROM log ORDER BY acao')->fetchAll(PDO::FETCH_COLUMN);

$filtros = array_filter([
    'usuario' => $filtro_usuario,
    'acao' => $filtro_acao,
    'data' => $filtro_data,
], static fn(string $valor): bool => $valor !== '');

$link_pagina = static fn(int $numero): string => 'admin-logs.php?' . http_build_query($filtros + ['pagina' => $numero]);

renderizar_cabecalho('Logs', $usuario_logado);
?>

<div class="secao-voos">
  <a class="botao-pequeno" href="admin.php" style="margin-bottom: 20px;">← Voltar</a>
  <div class="cartao-busca-voos">
    <h2 class="titulo-secao-busca">Logs do Sistema</h2>

    <form method="get" class="formulario-busca-voos" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
      <div>
        <label class="rotulo-busca-voos">Usuário</label>
        <input class="entrada-busca-voos" name="usuario" value="<?=esc($filtro_usuario)?>" placeholder="Nome ou CPF" autocomplete="off">
      </div>

      <div>
        <label class="rotulo-busca-voos">Ação</label>
        <select class="entrada-busca-voos" name="acao">
          <option value="">Todas</option>
          <?php foreach($acoes as $acao): ?>
            <option value="<?=esc($acao)?>" <?= $filtro_acao === $acao ? 'selected' : '' ?>><?=esc($acao)?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="rotulo-busca-voos">Data</label>
        <input class="entrada-busca-voos" type="date" name="data" value="<?=esc($filtro_data)?>">
      </div>

      <div class="container-botao-busca">
        <button class="botao-busca-voos" type="submit">Buscar</button>
      </div>
    </form>
  </div>

  <h2 class="titulo-lista-voos"><?= $total === 1 ? '1 registro' : $total . ' registros' ?></h2>

  <?php if($logs): ?>
    <div class="cartao-formulario" style="overflow-x: auto;">
      <table class="tabela-operacional">
        <thead>
          <tr>
            <th>Data e hora</th>
            <th>Usuário</th>
            <th>Ação</th>
            <th>Descrição</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($logs as $log): ?>
            <tr>
              <td><?=esc((new DateTime($log['data_hora']))->format('d/m/Y H:i:s'))?></td>
              <td>
                <?=esc($log['nome_completo'] ?? 'Usuário removido')?><br>
                <span class="texto-secundario texto-pequeno"><?=esc($log['usuario_cpf'])?></span>
              </td>
              <td><?=esc($log['acao'])?></td>
              <td><?=esc($log['descricao'])?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if($total_paginas > 1): ?>
      <div class="flex items-center gap-3" style="margin-top: 15px;">
        <?php if($pagina > 1): ?>
          <a class="botao-pequeno" href="<?=esc($link_pagina($pagina - 1))?>">← Anterior</a>
        <?php endif; ?>
        <span class="texto-secundario texto-pequeno">Página <?= $pagina ?> de <?= $total_paginas ?></span>
        <?php if($pagina < $total_paginas): ?>
          <a class="botao-pequeno" href="<?=esc($link_pagina($pagina + 1))?>">Próxima →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="estado-vazio-escala">
      <p>Nenhum registro encontrado.</p>
    </div>
  <?php endif; ?>
</div>

<?php renderizar_rodape(); ?>

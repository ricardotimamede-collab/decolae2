<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gerencia.php';

$conexaoBancoDados = db();
$usuarioLogado = require_role($conexaoBancoDados, 'gerente');
$mensagem = obter_aviso()['texto'] ?? null;
$erro = null;

function contar_voos_do_trajeto(PDO $conexao, array $chave): int
{
    $comando = $conexao->prepare('SELECT COUNT(*) FROM voo WHERE origem = ? AND destino = ? AND h_partida = ?');
    $comando->execute($chave);

    return (int) $comando->fetchColumn();
}

function buscar_trajeto(PDO $conexao, array $chave): ?array
{
    $comando = $conexao->prepare(
        'SELECT origem, destino, h_partida, h_chegada FROM trajeto WHERE origem = ? AND destino = ? AND h_partida = ?'
    );
    $comando->execute($chave);

    return $comando->fetch() ?: null;
}

function registro_do_trajeto(string $origem, string $destino, string $partida): string
{
    return mb_substr('trajeto:' . $origem . '|' . $destino . '|' . substr($partida, 0, 5), 0, 120);
}

function ler_dados_trajeto(): array
{
    $origem = obter_campo_obrigatorio('origem');
    $destino = obter_campo_obrigatorio('destino');
    $partida = obter_campo_obrigatorio('h_partida');
    $chegada = obter_campo_obrigatorio('h_chegada');

    if (mb_strtolower($origem) === mb_strtolower($destino)) {
        throw new InvalidArgumentException('Origem e destino devem ser diferentes.');
    }

    return [$origem, $destino, $partida, $chegada];
}

if (is_post()) {
    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'trajeto') {
            [$origem, $destino, $partida, $chegada] = ler_dados_trajeto();

            $comando = $conexaoBancoDados->prepare(
                'INSERT INTO trajeto (origem, destino, h_partida, h_chegada, gerente_cpf)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $comando->execute([$origem, $destino, $partida, $chegada, $usuarioLogado['cpf']]);

            log_action(
                $conexaoBancoDados,
                'TRAJETO_CRIADO',
                "Trajeto {$origem} → {$destino} cadastrado.",
                registro_do_trajeto($origem, $destino, $partida),
                $usuarioLogado['cpf']
            );

            definir_aviso('Trajeto cadastrado.');
            header('Location: gerente-trajetos.php');
            exit;
        }

        if ($acao === 'trajeto_editar') {
            $chave_atual = decodificar_trajeto(obter_campo_obrigatorio('codigo'));
            [$origem, $destino, $partida, $chegada] = ler_dados_trajeto();

            if (!buscar_trajeto($conexaoBancoDados, $chave_atual)) {
                throw new InvalidArgumentException('Trajeto não encontrado.');
            }

            $voos = contar_voos_do_trajeto($conexaoBancoDados, $chave_atual);
            if ($voos > 0) {
                throw new InvalidArgumentException(
                    'Este trajeto não pode ser alterado porque está em ' . descrever_voos($voos) . '.'
                );
            }

            $comando = $conexaoBancoDados->prepare(
                'UPDATE trajeto SET origem = ?, destino = ?, h_partida = ?, h_chegada = ?
                 WHERE origem = ? AND destino = ? AND h_partida = ?'
            );
            $comando->execute([$origem, $destino, $partida, $chegada, ...$chave_atual]);

            log_action(
                $conexaoBancoDados,
                'TRAJETO_ALTERADO',
                "Trajeto {$origem} → {$destino} alterado.",
                registro_do_trajeto($origem, $destino, $partida),
                $usuarioLogado['cpf']
            );

            definir_aviso('Trajeto alterado.');
            header('Location: gerente-trajetos.php');
            exit;
        }

        if ($acao === 'trajeto_excluir') {
            $chave_atual = decodificar_trajeto(obter_campo_obrigatorio('codigo'));
            $trajeto = buscar_trajeto($conexaoBancoDados, $chave_atual);

            if (!$trajeto) {
                throw new InvalidArgumentException('Trajeto não encontrado.');
            }

            $voos = contar_voos_do_trajeto($conexaoBancoDados, $chave_atual);
            if ($voos > 0) {
                throw new InvalidArgumentException(
                    'Este trajeto não pode ser excluído porque está em ' . descrever_voos($voos) . '.'
                );
            }

            $comando = $conexaoBancoDados->prepare('DELETE FROM trajeto WHERE origem = ? AND destino = ? AND h_partida = ?');
            $comando->execute($chave_atual);

            log_action(
                $conexaoBancoDados,
                'TRAJETO_EXCLUIDO',
                "Trajeto {$trajeto['origem']} → {$trajeto['destino']} excluído.",
                registro_do_trajeto($trajeto['origem'], $trajeto['destino'], $trajeto['h_partida']),
                $usuarioLogado['cpf']
            );

            definir_aviso('Trajeto excluído.');
            header('Location: gerente-trajetos.php');
            exit;
        }
    } catch (Throwable $excecao) {
        if ($conexaoBancoDados->inTransaction()) {
            $conexaoBancoDados->rollBack();
        }

        $erro = $excecao->getMessage();

        if ($excecao instanceof PDOException && (int) ($excecao->errorInfo[1] ?? 0) === 1062) {
            $erro = 'Já existe um trajeto com essa origem, destino e horário de partida.';
        }
    }
}

$busca = trim((string) ($_GET['busca'] ?? ''));
$comando = $conexaoBancoDados->prepare(
    'SELECT t.origem, t.destino, t.h_partida, t.h_chegada,
            (SELECT COUNT(*) FROM voo v
             WHERE v.origem = t.origem AND v.destino = t.destino AND v.h_partida = t.h_partida) AS voos
     FROM trajeto t
     WHERE t.origem LIKE ? OR t.destino LIKE ?
     ORDER BY t.origem, t.destino, t.h_partida'
);
$comando->execute(['%' . $busca . '%', '%' . $busca . '%']);
$trajetos = $comando->fetchAll();

$trajeto_em_edicao = null;
$codigo_edicao = (string) ($_GET['editar'] ?? '');
if ($codigo_edicao !== '') {
    try {
        $chave_edicao = decodificar_trajeto($codigo_edicao);
        $trajeto_em_edicao = buscar_trajeto($conexaoBancoDados, $chave_edicao);
    } catch (InvalidArgumentException $excecao) {
        $trajeto_em_edicao = null;
    }

    if ($trajeto_em_edicao) {
        $voos_em_uso = contar_voos_do_trajeto($conexaoBancoDados, $chave_edicao);
        if ($voos_em_uso > 0) {
            $erro = 'Este trajeto não pode ser alterado porque está em ' . descrever_voos($voos_em_uso) . '.';
            $trajeto_em_edicao = null;
        }
    }
}

$valor = static fn(string $campo): string => esc(
    $_POST[$campo] ?? ($trajeto_em_edicao ? substr((string) $trajeto_em_edicao[$campo], 0, $campo[0] === 'h' ? 5 : 60) : '')
);

renderizar_cabecalho('Trajetos', $usuarioLogado);
?>

<div class="pagina-gerencia">
  <a class="botao-pequeno" href="gerente.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina">Trajetos</h1>

  <?php if($mensagem): ?>
    <div class="aviso aviso-sucesso">
      <?=esc($mensagem)?>
    </div>
  <?php endif; ?>

  <?php if($erro): ?>
    <div class="aviso aviso-erro">
      <?=esc($erro)?>
    </div>
  <?php endif; ?>

  <div class="grade-formularios grade-formularios-gerencia">

    <div class="cartao-formulario">
      <h2 class="titulo-formulario"><?= $trajeto_em_edicao ? 'Editar Trajeto' : 'Cadastrar Trajeto' ?></h2>
      <form method="post" action="gerente-trajetos.php<?= $trajeto_em_edicao ? '?editar=' . esc($codigo_edicao) : '' ?>">
        <input type="hidden" name="csrf_token" value="<?=esc(csrf_token())?>">
        <input type="hidden" name="acao" value="<?= $trajeto_em_edicao ? 'trajeto_editar' : 'trajeto' ?>">
        <?php if($trajeto_em_edicao): ?>
          <input type="hidden" name="codigo" value="<?= esc($codigo_edicao) ?>">
        <?php endif; ?>

        <div class="campo-form">
          <label>Origem</label>
          <input name="origem" maxlength="60" value="<?= $valor('origem') ?>" required>
        </div>

        <div class="campo-form">
          <label>Destino</label>
          <input name="destino" maxlength="60" value="<?= $valor('destino') ?>" required>
        </div>

        <div class="campo-form">
          <label>Horário de Partida</label>
          <input name="h_partida" type="time" value="<?= $valor('h_partida') ?>" required>
        </div>

        <div class="campo-form">
          <label>Horário de Chegada</label>
          <input name="h_chegada" type="time" value="<?= $valor('h_chegada') ?>" required>
        </div>

        <button type="submit" class="botao botao-bloco"><?= $trajeto_em_edicao ? 'Salvar Alterações' : 'Salvar Trajeto' ?></button>
        <?php if($trajeto_em_edicao): ?>
          <a class="botao-pequeno" href="gerente-trajetos.php" style="margin-top: 10px; width: 100%;">Cancelar</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Trajetos Cadastrados</h2>

      <form method="get" class="flex gap-2" style="margin-bottom: 15px;">
        <div class="campo-form" style="flex: 1; margin: 0;">
          <input type="search" name="busca" value="<?=esc($busca)?>" placeholder="Buscar por origem ou destino">
        </div>
        <button type="submit" class="botao-pequeno">Buscar</button>
      </form>

      <?php if(count($trajetos) > 0): ?>
        <div class="lista-gerencia">
          <?php foreach($trajetos as $r): ?>
            <div class="linha-rota">
              <div>
                <strong><?=esc($r['origem'])?> → <?=esc($r['destino'])?></strong><br>
                <span class="texto-secundario"><?=esc(substr($r['h_partida'],0,5))?>–<?=esc(substr($r['h_chegada'],0,5))?></span>
              </div>

              <?php if((int) $r['voos'] > 0): ?>
                <span class="texto-secundario texto-pequeno">Em uso · <?=esc(descrever_voos((int) $r['voos']))?></span>
              <?php else: ?>
                <div class="flex gap-2">
                  <a class="botao-pequeno" href="gerente-trajetos.php?editar=<?= esc(codificar_trajeto($r['origem'], $r['destino'], $r['h_partida'])) ?>">Editar</a>
                  <form method="post" data-confirmar="Excluir o trajeto <?=esc($r['origem'])?> → <?=esc($r['destino'])?>?">
                    <input type="hidden" name="csrf_token" value="<?=esc(csrf_token())?>">
                    <input type="hidden" name="acao" value="trajeto_excluir">
                    <input type="hidden" name="codigo" value="<?= esc(codificar_trajeto($r['origem'], $r['destino'], $r['h_partida'])) ?>">
                    <button type="submit" class="botao-pequeno botao-perigo">Excluir</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="texto-vazio"><?= $busca !== '' ? 'Nenhum trajeto encontrado.' : 'Nenhum trajeto cadastrado.' ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php renderizar_modal_exclusao(); ?>
<?php renderizar_rodape(); ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gerencia.php';

$conexaoBancoDados = db();
$usuarioLogado = require_role($conexaoBancoDados, 'gerente');
$mensagem = obter_aviso()['texto'] ?? null;
$erro = null;

function contar_voos_da_aeronave(PDO $conexao, int $id_aeronave): int
{
    $comando = $conexao->prepare('SELECT COUNT(*) FROM voo WHERE aeronave_id = ?');
    $comando->execute([$id_aeronave]);

    return (int) $comando->fetchColumn();
}

function ler_dados_aeronave(): array
{
    $identificacao = strtoupper(obter_campo_obrigatorio('identificacao'));
    $modelo = obter_campo_obrigatorio('modelo');
    $fileiras = max(1, (int) obter_campo_obrigatorio('fileiras'));
    $assentos_por_fileira = max(1, min(6, (int) obter_campo_obrigatorio('assentos_por_fileira')));

    return [$identificacao, $modelo, $fileiras, $assentos_por_fileira];
}

function criar_assentos(PDO $conexao, int $id_aeronave, int $fileiras, int $assentos_por_fileira): void
{
    $comando = $conexao->prepare(
        'INSERT INTO assento (aeronave_id, codigo_assento, numero_fileira, letra) VALUES (?, ?, ?, ?)'
    );

    for ($fileira = 1; $fileira <= $fileiras; $fileira++) {
        for ($indice = 0; $indice < $assentos_por_fileira; $indice++) {
            $letra = chr(65 + $indice);
            $comando->execute([$id_aeronave, $fileira . $letra, $fileira, $letra]);
        }
    }
}

function traduzir_erro_aeronave(Throwable $excecao, string $identificacao): string
{
    if ($excecao instanceof PDOException && (int) ($excecao->errorInfo[1] ?? 0) === 1062) {
        return "Já existe uma aeronave com a identificação {$identificacao}.";
    }

    return $excecao->getMessage();
}

if (is_post()) {
    $identificacao_enviada = strtoupper(trim((string) ($_POST['identificacao'] ?? '')));

    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'aeronave') {
            [$identificacao, $modelo, $fileiras, $assentos_por_fileira] = ler_dados_aeronave();

            $conexaoBancoDados->beginTransaction();

            $comando = $conexaoBancoDados->prepare(
                'INSERT INTO aeronave (identificacao, modelo, fileiras, assentos_por_fileira, gerente_cpf)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $comando->execute([$identificacao, $modelo, $fileiras, $assentos_por_fileira, $usuarioLogado['cpf']]);
            $id_aeronave = (int) $conexaoBancoDados->lastInsertId();

            criar_assentos($conexaoBancoDados, $id_aeronave, $fileiras, $assentos_por_fileira);

            log_action(
                $conexaoBancoDados,
                'AERONAVE_CRIADA',
                "Aeronave {$identificacao} cadastrada.",
                'aeronave:' . $id_aeronave,
                $usuarioLogado['cpf']
            );

            $conexaoBancoDados->commit();
            definir_aviso('Aeronave e seus assentos cadastrados.');
            header('Location: gerente-aeronaves.php');
            exit;
        }

        if ($acao === 'aeronave_editar') {
            $id_aeronave = (int) obter_campo_obrigatorio('id');
            [$identificacao, $modelo, $fileiras, $assentos_por_fileira] = ler_dados_aeronave();

            $voos = contar_voos_da_aeronave($conexaoBancoDados, $id_aeronave);
            if ($voos > 0) {
                throw new InvalidArgumentException(
                    'Esta aeronave não pode ser alterada porque está em ' . descrever_voos($voos) . '.'
                );
            }

            $comando = $conexaoBancoDados->prepare('SELECT fileiras, assentos_por_fileira FROM aeronave WHERE id = ?');
            $comando->execute([$id_aeronave]);
            $atual = $comando->fetch();

            if (!$atual) {
                throw new InvalidArgumentException('Aeronave não encontrada.');
            }

            $conexaoBancoDados->beginTransaction();

            $comando = $conexaoBancoDados->prepare(
                'UPDATE aeronave SET identificacao = ?, modelo = ?, fileiras = ?, assentos_por_fileira = ? WHERE id = ?'
            );
            $comando->execute([$identificacao, $modelo, $fileiras, $assentos_por_fileira, $id_aeronave]);

            if ((int) $atual['fileiras'] !== $fileiras || (int) $atual['assentos_por_fileira'] !== $assentos_por_fileira) {
                $comando = $conexaoBancoDados->prepare('DELETE FROM assento WHERE aeronave_id = ?');
                $comando->execute([$id_aeronave]);
                criar_assentos($conexaoBancoDados, $id_aeronave, $fileiras, $assentos_por_fileira);
            }

            log_action(
                $conexaoBancoDados,
                'AERONAVE_ALTERADA',
                "Aeronave {$identificacao} alterada.",
                'aeronave:' . $id_aeronave,
                $usuarioLogado['cpf']
            );

            $conexaoBancoDados->commit();
            definir_aviso('Aeronave alterada.');
            header('Location: gerente-aeronaves.php');
            exit;
        }

        if ($acao === 'aeronave_excluir') {
            $id_aeronave = (int) obter_campo_obrigatorio('id');

            $comando = $conexaoBancoDados->prepare('SELECT identificacao FROM aeronave WHERE id = ?');
            $comando->execute([$id_aeronave]);
            $aeronave = $comando->fetch();

            if (!$aeronave) {
                throw new InvalidArgumentException('Aeronave não encontrada.');
            }

            $voos = contar_voos_da_aeronave($conexaoBancoDados, $id_aeronave);
            if ($voos > 0) {
                throw new InvalidArgumentException(
                    'Esta aeronave não pode ser excluída porque está em ' . descrever_voos($voos) . '.'
                );
            }

            $comando = $conexaoBancoDados->prepare('DELETE FROM aeronave WHERE id = ?');
            $comando->execute([$id_aeronave]);

            log_action(
                $conexaoBancoDados,
                'AERONAVE_EXCLUIDA',
                "Aeronave {$aeronave['identificacao']} excluída.",
                'aeronave:' . $id_aeronave,
                $usuarioLogado['cpf']
            );

            definir_aviso('Aeronave excluída.');
            header('Location: gerente-aeronaves.php');
            exit;
        }
    } catch (Throwable $excecao) {
        if ($conexaoBancoDados->inTransaction()) {
            $conexaoBancoDados->rollBack();
        }

        $erro = traduzir_erro_aeronave($excecao, $identificacao_enviada);
    }
}

$busca = trim((string) ($_GET['busca'] ?? ''));
$comando = $conexaoBancoDados->prepare(
    'SELECT a.id, a.identificacao, a.modelo, a.fileiras, a.assentos_por_fileira,
            (SELECT COUNT(*) FROM assento s WHERE s.aeronave_id = a.id) AS capacidade,
            (SELECT COUNT(*) FROM voo v WHERE v.aeronave_id = a.id) AS voos
     FROM aeronave a
     WHERE a.identificacao LIKE ? OR a.modelo LIKE ?
     ORDER BY a.identificacao'
);
$comando->execute(['%' . $busca . '%', '%' . $busca . '%']);
$aeronaves = $comando->fetchAll();

$aeronave_em_edicao = null;
$id_edicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($id_edicao) {
    $comando = $conexaoBancoDados->prepare(
        'SELECT id, identificacao, modelo, fileiras, assentos_por_fileira FROM aeronave WHERE id = ?'
    );
    $comando->execute([$id_edicao]);
    $aeronave_em_edicao = $comando->fetch() ?: null;

    if ($aeronave_em_edicao) {
        $voos_em_uso = contar_voos_da_aeronave($conexaoBancoDados, $id_edicao);
        if ($voos_em_uso > 0) {
            $erro = 'Esta aeronave não pode ser alterada porque está em ' . descrever_voos($voos_em_uso) . '.';
            $aeronave_em_edicao = null;
        }
    }
}

$valor = static fn(string $campo, string $padrao = ''): string => esc(
    $_POST[$campo] ?? ($aeronave_em_edicao ? (string) $aeronave_em_edicao[$campo] : $padrao)
);

renderizar_cabecalho('Aeronaves', $usuarioLogado);
?>

<div class="pagina-gerencia">
  <a class="botao-pequeno" href="gerente.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina">Aeronaves</h1>

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
      <h2 class="titulo-formulario"><?= $aeronave_em_edicao ? 'Editar Aeronave' : 'Cadastrar Aeronave' ?></h2>
      <form method="post" action="gerente-aeronaves.php<?= $aeronave_em_edicao ? '?editar=' . (int) $aeronave_em_edicao['id'] : '' ?>">
        <input type="hidden" name="csrf_token" value="<?=esc(csrf_token())?>">
        <input type="hidden" name="acao" value="<?= $aeronave_em_edicao ? 'aeronave_editar' : 'aeronave' ?>">
        <?php if($aeronave_em_edicao): ?>
          <input type="hidden" name="id" value="<?= (int) $aeronave_em_edicao['id'] ?>">
        <?php endif; ?>

        <div class="campo-form">
          <label>Identificação</label>
          <input name="identificacao" value="<?= $valor('identificacao') ?>" required>
        </div>

        <div class="campo-form">
          <label>Modelo</label>
          <input name="modelo" value="<?= $valor('modelo') ?>" required>
        </div>

        <div class="campo-form">
          <label>Fileiras</label>
          <input name="fileiras" type="number" min="1" value="<?= $valor('fileiras') ?>" required>
        </div>

        <div class="campo-form">
          <label>Assentos por Fileira</label>
          <input name="assentos_por_fileira" type="number" min="1" max="6" value="<?= $valor('assentos_por_fileira') ?>" required>
        </div>

        <button type="submit" class="botao botao-bloco"><?= $aeronave_em_edicao ? 'Salvar Alterações' : 'Salvar Aeronave' ?></button>
        <?php if($aeronave_em_edicao): ?>
          <a class="botao-pequeno" href="gerente-aeronaves.php" style="margin-top: 10px; width: 100%;">Cancelar</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Aeronaves Cadastradas</h2>

      <form method="get" class="flex gap-2" style="margin-bottom: 15px;">
        <div class="campo-form" style="flex: 1; margin: 0;">
          <input type="search" name="busca" value="<?=esc($busca)?>" placeholder="Buscar por identificação ou modelo">
        </div>
        <button type="submit" class="botao-pequeno">Buscar</button>
      </form>

      <?php if(count($aeronaves) > 0): ?>
        <div class="lista-gerencia">
          <?php foreach($aeronaves as $a): ?>
            <div class="linha-rota">
              <div>
                <strong><?=esc($a['identificacao'])?> · <?=esc($a['modelo'])?></strong><br>
                <span class="texto-secundario"><?=esc($a['capacidade'])?> assentos</span>
              </div>

              <?php if((int) $a['voos'] > 0): ?>
                <span class="texto-secundario texto-pequeno">Em uso · <?=esc(descrever_voos((int) $a['voos']))?></span>
              <?php else: ?>
                <div class="flex gap-2">
                  <a class="botao-pequeno" href="gerente-aeronaves.php?editar=<?= (int) $a['id'] ?>">Editar</a>
                  <form method="post" data-confirmar="Excluir a aeronave <?=esc($a['identificacao'])?>?">
                    <input type="hidden" name="csrf_token" value="<?=esc(csrf_token())?>">
                    <input type="hidden" name="acao" value="aeronave_excluir">
                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                    <button type="submit" class="botao-pequeno botao-perigo">Excluir</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="texto-vazio"><?= $busca !== '' ? 'Nenhuma aeronave encontrada.' : 'Nenhuma aeronave cadastrada.' ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php renderizar_modal_exclusao(); ?>
<?php renderizar_rodape(); ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gerencia.php';

$conexaoBancoDados = db();
$usuarioLogado = require_role($conexaoBancoDados, 'gerente');
$mensagem = obter_aviso()['texto'] ?? null;
$erro = null;

$nomes_funcoes = [
    'piloto' => 'Piloto',
    'copiloto' => 'Copiloto',
    'comissario' => 'Comissário de bordo',
];

function contar_escalas(PDO $conexao, string $cpf): int
{
    $comando = $conexao->prepare('SELECT COUNT(*) FROM escala WHERE tripulante_cpf = ?');
    $comando->execute([$cpf]);

    return (int) $comando->fetchColumn();
}

function descrever_escalas(int $total): string
{
    return $total === 1 ? '1 escala' : $total . ' escalas';
}

function ler_funcao(array $funcoes_validas): string
{
    $funcao = obter_campo_obrigatorio('funcao_tripulante');

    if (!isset($funcoes_validas[$funcao])) {
        throw new InvalidArgumentException('Selecione uma função válida para o tripulante.');
    }

    return $funcao;
}

function validar_dados_pessoais(
    PDO $conexao,
    string $nome,
    string $email,
    string $endereco,
    string $telefone,
    string $cpf
): void {
    if ($nome === '' || $email === '' || $endereco === '' || $telefone === '') {
        throw new InvalidArgumentException('Preencha nome, email, telefone e endereço.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email inválido.');
    }

    $comando = $conexao->prepare('SELECT 1 FROM usuario WHERE email = ? AND cpf <> ?');
    $comando->execute([$email, $cpf]);

    if ($comando->fetchColumn()) {
        throw new InvalidArgumentException('Este email já está em uso.');
    }

    $comando = $conexao->prepare('SELECT 1 FROM usuario WHERE telefone = ? AND cpf <> ?');
    $comando->execute([$telefone, $cpf]);

    if ($comando->fetchColumn()) {
        throw new InvalidArgumentException('Este telefone já está cadastrado.');
    }
}

if (is_post()) {
    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'tripulante' || $acao === 'tripulante_existente') {
            $cpf = preg_replace('/\D/', '', (string) ($_POST['cpf'] ?? ''));
            if (strlen($cpf) !== 11) {
                throw new InvalidArgumentException('O CPF deve ter 11 dígitos.');
            }

            $funcao = ler_funcao($nomes_funcoes);

            $comando = $conexaoBancoDados->prepare('SELECT 1 FROM tripulante WHERE cpf = ?');
            $comando->execute([$cpf]);
            if ($comando->fetchColumn()) {
                throw new InvalidArgumentException('Este CPF já está cadastrado como tripulante.');
            }

            $comando = $conexaoBancoDados->prepare('SELECT nome_completo FROM usuario WHERE cpf = ?');
            $comando->execute([$cpf]);
            $conta = $comando->fetch();

            if ($acao === 'tripulante_existente') {
                if (!$conta) {
                    throw new InvalidArgumentException('Não existe conta com esse CPF. Use a opção Novo cadastro.');
                }

                $conexaoBancoDados->beginTransaction();

                $comando = $conexaoBancoDados->prepare('INSERT INTO tripulante (cpf, funcao) VALUES (?, ?)');
                $comando->execute([$cpf, $funcao]);

                log_action(
                    $conexaoBancoDados,
                    'TRIPULANTE_PERFIL_ADICIONADO',
                    "Perfil de tripulante adicionado à conta de {$conta['nome_completo']}.",
                    'tripulante:' . $cpf,
                    $usuarioLogado['cpf']
                );

                $texto = "Perfil de tripulante adicionado à conta de {$conta['nome_completo']}.";
            } else {
                if ($conta) {
                    throw new InvalidArgumentException('Este CPF já tem conta. Use a opção Já tem conta de cliente.');
                }

                $nome = trim((string) ($_POST['nome_completo'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $endereco = trim((string) ($_POST['endereco'] ?? ''));
                $telefone = preg_replace('/\D/', '', (string) ($_POST['telefone'] ?? ''));
                $senha = (string) ($_POST['senha'] ?? '');

                validar_dados_pessoais($conexaoBancoDados, $nome, $email, $endereco, $telefone, $cpf);

                if ($senha === '') {
                    throw new InvalidArgumentException('Informe uma senha para a nova conta.');
                }

                $conexaoBancoDados->beginTransaction();

                $comando = $conexaoBancoDados->prepare(
                    'INSERT INTO usuario (cpf, nome_completo, email, endereco, telefone, senha) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $comando->execute([$cpf, $nome, $email, $endereco, $telefone, password_hash($senha, PASSWORD_BCRYPT)]);

                $comando = $conexaoBancoDados->prepare('INSERT INTO tripulante (cpf, funcao) VALUES (?, ?)');
                $comando->execute([$cpf, $funcao]);

                log_action(
                    $conexaoBancoDados,
                    'TRIPULANTE_CRIADO',
                    "Tripulante {$nome} cadastrado como {$funcao}.",
                    'tripulante:' . $cpf,
                    $usuarioLogado['cpf']
                );

                $texto = 'Tripulante cadastrado.';
            }

            $conexaoBancoDados->commit();
            definir_aviso($texto);
            header('Location: gerente-tripulacao.php');
            exit;
        }

        if ($acao === 'tripulante_editar') {
            $cpf = preg_replace('/\D/', '', (string) ($_POST['cpf'] ?? ''));
            $funcao = ler_funcao($nomes_funcoes);
            $nome = trim((string) ($_POST['nome_completo'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $endereco = trim((string) ($_POST['endereco'] ?? ''));
            $telefone = preg_replace('/\D/', '', (string) ($_POST['telefone'] ?? ''));

            $comando = $conexaoBancoDados->prepare('SELECT 1 FROM tripulante WHERE cpf = ?');
            $comando->execute([$cpf]);
            if (!$comando->fetchColumn()) {
                throw new InvalidArgumentException('Tripulante não encontrado.');
            }

            $escalas = contar_escalas($conexaoBancoDados, $cpf);
            if ($escalas > 0) {
                throw new InvalidArgumentException(
                    'Este tripulante não pode ser alterado porque está em ' . descrever_escalas($escalas) . '.'
                );
            }

            validar_dados_pessoais($conexaoBancoDados, $nome, $email, $endereco, $telefone, $cpf);

            $conexaoBancoDados->beginTransaction();

            $comando = $conexaoBancoDados->prepare(
                'UPDATE usuario SET nome_completo = ?, email = ?, endereco = ?, telefone = ? WHERE cpf = ?'
            );
            $comando->execute([$nome, $email, $endereco, $telefone, $cpf]);

            $comando = $conexaoBancoDados->prepare('UPDATE tripulante SET funcao = ? WHERE cpf = ?');
            $comando->execute([$funcao, $cpf]);

            log_action(
                $conexaoBancoDados,
                'TRIPULANTE_ALTERADO',
                "Tripulante {$nome} alterado.",
                'tripulante:' . $cpf,
                $usuarioLogado['cpf']
            );

            $conexaoBancoDados->commit();
            definir_aviso('Tripulante alterado.');
            header('Location: gerente-tripulacao.php');
            exit;
        }

        if ($acao === 'tripulante_excluir') {
            $cpf = preg_replace('/\D/', '', (string) ($_POST['cpf'] ?? ''));

            $comando = $conexaoBancoDados->prepare(
                'SELECT u.nome_completo FROM tripulante t JOIN usuario u ON u.cpf = t.cpf WHERE t.cpf = ?'
            );
            $comando->execute([$cpf]);
            $tripulante = $comando->fetch();

            if (!$tripulante) {
                throw new InvalidArgumentException('Tripulante não encontrado.');
            }

            $escalas = contar_escalas($conexaoBancoDados, $cpf);
            if ($escalas > 0) {
                throw new InvalidArgumentException(
                    'Este tripulante não pode ser excluído porque está em ' . descrever_escalas($escalas) . '.'
                );
            }

            $conexaoBancoDados->beginTransaction();

            $comando = $conexaoBancoDados->prepare('DELETE FROM tripulante WHERE cpf = ?');
            $comando->execute([$cpf]);

            $comando = $conexaoBancoDados->prepare(
                'SELECT (SELECT COUNT(*) FROM cliente WHERE cpf = ?)
                      + (SELECT COUNT(*) FROM gerente WHERE cpf = ?)
                      + (SELECT COUNT(*) FROM admin WHERE cpf = ?)'
            );
            $comando->execute([$cpf, $cpf, $cpf]);
            $outros_perfis = (int) $comando->fetchColumn();

            $conta_mantida = true;
            if ($outros_perfis === 0) {
                try {
                    $comando = $conexaoBancoDados->prepare('DELETE FROM usuario WHERE cpf = ?');
                    $comando->execute([$cpf]);
                    $conta_mantida = false;
                } catch (PDOException $excecao) {
                    if ((int) ($excecao->errorInfo[1] ?? 0) !== 1451) {
                        throw $excecao;
                    }
                }
            }

            log_action(
                $conexaoBancoDados,
                'TRIPULANTE_EXCLUIDO',
                "Tripulante {$tripulante['nome_completo']} excluído.",
                'tripulante:' . $cpf,
                $usuarioLogado['cpf']
            );

            $conexaoBancoDados->commit();
            definir_aviso(
                $conta_mantida
                    ? 'Tripulante excluído. A conta foi mantida porque a pessoa tem outros vínculos no sistema.'
                    : 'Tripulante excluído.'
            );
            header('Location: gerente-tripulacao.php');
            exit;
        }
    } catch (Throwable $excecao) {
        if ($conexaoBancoDados->inTransaction()) {
            $conexaoBancoDados->rollBack();
        }

        $erro = $excecao->getMessage();
    }
}

$busca = trim((string) ($_GET['busca'] ?? ''));
$termo = '%' . $busca . '%';
$comando = $conexaoBancoDados->prepare(
    'SELECT u.nome_completo, u.email, t.cpf, t.funcao,
            (SELECT COUNT(*) FROM escala e WHERE e.tripulante_cpf = t.cpf) AS escalas
     FROM tripulante t
     JOIN usuario u ON u.cpf = t.cpf
     WHERE u.nome_completo LIKE ? OR u.email LIKE ? OR t.cpf LIKE ?
     ORDER BY u.nome_completo'
);
$comando->execute([$termo, $termo, $termo]);
$tripulantes = $comando->fetchAll();

$tripulante_em_edicao = null;
$cpf_edicao = preg_replace('/\D/', '', (string) ($_GET['editar'] ?? ''));
if ($cpf_edicao !== '') {
    $comando = $conexaoBancoDados->prepare(
        'SELECT u.cpf, u.nome_completo, u.email, u.endereco, u.telefone, t.funcao
         FROM tripulante t JOIN usuario u ON u.cpf = t.cpf WHERE t.cpf = ?'
    );
    $comando->execute([$cpf_edicao]);
    $tripulante_em_edicao = $comando->fetch() ?: null;

    if ($tripulante_em_edicao) {
        $escalas_em_uso = contar_escalas($conexaoBancoDados, $cpf_edicao);
        if ($escalas_em_uso > 0) {
            $erro = 'Este tripulante não pode ser alterado porque está em ' . descrever_escalas($escalas_em_uso) . '.';
            $tripulante_em_edicao = null;
        }
    }
}

$valor = static fn(string $campo, string $coluna): string => esc(
    $_POST[$campo] ?? ($tripulante_em_edicao ? (string) $tripulante_em_edicao[$coluna] : '')
);
$modo_cadastro = ($_GET['modo'] ?? '') === 'existente' ? 'existente' : 'nova';
$campos_completos = $tripulante_em_edicao || $modo_cadastro === 'nova';
$funcao_selecionada = $_POST['funcao_tripulante'] ?? ($tripulante_em_edicao['funcao'] ?? '');

renderizar_cabecalho('Tripulação', $usuarioLogado);
?>

<div class="pagina-gerencia">
  <a class="botao-pequeno" href="gerente.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina">Tripulação</h1>

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
      <h2 class="titulo-formulario"><?= $tripulante_em_edicao ? 'Editar Tripulante' : 'Cadastrar Tripulante' ?></h2>

      <?php if(!$tripulante_em_edicao): ?>
        <div class="flex gap-2" style="margin-bottom: 15px;">
          <a class="botao-pequeno <?= $modo_cadastro === 'nova' ? 'aba-ativa' : '' ?>" href="gerente-tripulacao.php">Novo cadastro</a>
          <a class="botao-pequeno <?= $modo_cadastro === 'existente' ? 'aba-ativa' : '' ?>" href="gerente-tripulacao.php?modo=existente">Já tem conta de cliente</a>
        </div>
      <?php endif; ?>

      <form method="post" action="gerente-tripulacao.php<?= $tripulante_em_edicao ? '?editar=' . esc($tripulante_em_edicao['cpf']) : ($modo_cadastro === 'existente' ? '?modo=existente' : '') ?>">
        <input type="hidden" name="csrf_token" value="<?=esc(csrf_token())?>">
        <input type="hidden" name="acao" value="<?= $tripulante_em_edicao ? 'tripulante_editar' : ($modo_cadastro === 'existente' ? 'tripulante_existente' : 'tripulante') ?>">

        <div class="campo-form">
          <label>CPF</label>
          <?php if($tripulante_em_edicao): ?>
            <input name="cpf" value="<?=esc($tripulante_em_edicao['cpf'])?>" readonly>
          <?php else: ?>
            <input name="cpf" maxlength="14" value="<?= $valor('cpf', 'cpf') ?>" required>
          <?php endif; ?>
        </div>

        <?php if($campos_completos): ?>
          <div class="campo-form">
            <label>Nome completo</label>
            <input name="nome_completo" maxlength="120" value="<?= $valor('nome_completo', 'nome_completo') ?>" required>
          </div>

          <div class="campo-form">
            <label>Email</label>
            <input name="email" type="email" maxlength="160" value="<?= $valor('email', 'email') ?>" required>
          </div>

          <div class="campo-form">
            <label>Telefone</label>
            <input name="telefone" type="tel" maxlength="15" value="<?= $valor('telefone', 'telefone') ?>" required>
          </div>

          <?php if(!$tripulante_em_edicao): ?>
            <div class="campo-form">
              <label>Senha</label>
              <input name="senha" type="password" required>
            </div>
          <?php endif; ?>

          <div class="campo-form">
            <label>Endereço</label>
            <input name="endereco" maxlength="255" value="<?= $valor('endereco', 'endereco') ?>" required>
          </div>
        <?php endif; ?>

        <div class="campo-form">
          <label>Função</label>
          <select name="funcao_tripulante" required>
            <option value="">Selecione...</option>
            <?php foreach($nomes_funcoes as $chave => $rotulo): ?>
              <option value="<?=esc($chave)?>" <?= $funcao_selecionada === $chave ? 'selected' : '' ?>><?=esc($rotulo)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="botao botao-bloco"><?= $tripulante_em_edicao ? 'Salvar Alterações' : 'Salvar Tripulante' ?></button>
        <?php if($tripulante_em_edicao): ?>
          <a class="botao-pequeno" href="gerente-tripulacao.php" style="margin-top: 10px; width: 100%;">Cancelar</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Tripulantes Cadastrados</h2>

      <form method="get" class="flex gap-2" style="margin-bottom: 15px;">
        <div class="campo-form" style="flex: 1; margin: 0;">
          <input type="search" name="busca" value="<?=esc($busca)?>" placeholder="Buscar por nome, CPF ou email">
        </div>
        <button type="submit" class="botao-pequeno">Buscar</button>
      </form>

      <?php if(count($tripulantes) > 0): ?>
        <div class="lista-gerencia">
          <?php foreach($tripulantes as $c): ?>
            <div class="linha-rota">
              <div>
                <strong><?=esc($c['nome_completo'])?></strong><br>
                <span class="texto-secundario texto-secundario-pequeno"><?=esc($c['email'])?> · CPF <?=esc($c['cpf'])?> · <?=esc($nomes_funcoes[$c['funcao']] ?? 'Função não definida')?></span>
              </div>

              <?php if((int) $c['escalas'] > 0): ?>
                <span class="texto-secundario texto-pequeno">Em uso · <?=esc(descrever_escalas((int) $c['escalas']))?></span>
              <?php else: ?>
                <div class="flex gap-2">
                  <a class="botao-pequeno" href="gerente-tripulacao.php?editar=<?=esc($c['cpf'])?>">Editar</a>
                  <form method="post" data-confirmar="Excluir o tripulante <?=esc($c['nome_completo'])?>?">
                    <input type="hidden" name="csrf_token" value="<?=esc(csrf_token())?>">
                    <input type="hidden" name="acao" value="tripulante_excluir">
                    <input type="hidden" name="cpf" value="<?=esc($c['cpf'])?>">
                    <button type="submit" class="botao-pequeno botao-perigo">Excluir</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="texto-vazio"><?= $busca !== '' ? 'Nenhum tripulante encontrado.' : 'Nenhum tripulante cadastrado.' ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php renderizar_modal_exclusao(); ?>
<?php renderizar_rodape(); ?>

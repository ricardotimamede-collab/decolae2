<?php

declare(strict_types=1);

// Aqui a gente carrega as funções e o layout desta página.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gerencia.php';

// Essas variáveis guardam os avisos exibidos depois das ações.
$conexaoBancoDados = db();
$usuarioLogado = require_role($conexaoBancoDados, 'gerente');
$mensagem = null;
$erro = null;

if (is_post()) {
    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        $acao = $_POST['acao'] ?? '';

        /**
         * Aqui a gente cadastra um usuário como tripulante ou atualiza sua função.
         */
        if ($acao === 'tripulante') {
            // Primeiro a gente identifica o usuário que será cadastrado.
            $cpf = obter_campo_obrigatorio('cpf');
            $funcao = obter_campo_obrigatorio('funcao_tripulante');
            $funcoes_validas = ['piloto', 'copiloto', 'comissario'];

            if (!in_array($funcao, $funcoes_validas, true)) {
                throw new InvalidArgumentException('Selecione uma função válida para o tripulante.');
            }
            
            // Se já houver cadastro, a gente atualiza a função; caso contrário, cria um.
            $comando = $conexaoBancoDados->prepare(
                'SELECT funcao FROM tripulante WHERE cpf = ?'
            );
            $comando->execute([$cpf]);
            $funcao_atual = $comando->fetchColumn();

            if ($funcao_atual === false) {
                $comando = $conexaoBancoDados->prepare(
                    'INSERT INTO tripulante (cpf, funcao) VALUES (?, ?)'
                );
                $comando->execute([$cpf, $funcao]);
                $acao_log = 'TRIPULANTE_CRIADO';
                $mensagem = 'Tripulante cadastrado com a função definida.';
            } else {
                $comando = $conexaoBancoDados->prepare(
                    'UPDATE tripulante SET funcao = ? WHERE cpf = ?'
                );
                $comando->execute([$funcao, $cpf]);
                $acao_log = 'TRIPULANTE_FUNCAO_ATUALIZADA';
                $mensagem = 'Função do tripulante atualizada.';
            }
            
            // Também guardamos no log a alteração feita.
            log_action(
                $conexaoBancoDados,
                $acao_log,
                "Função do tripulante definida como {$funcao}.",
                'tripulante:' . $cpf,
                $usuarioLogado['cpf']
            );
        }
    } catch (Throwable $excecao) {
        if ($conexaoBancoDados->inTransaction()) {
            $conexaoBancoDados->rollBack();
        }

        $erro = $excecao->getMessage();
    }
}

$nomes_funcoes_tripulantes = [
    'piloto' => 'Piloto',
    'copiloto' => 'Copiloto',
    'comissario' => 'Comissário de bordo'
];

$tripulantes = $conexaoBancoDados->query(
    'SELECT u.nome_completo, u.email, t.cpf, t.funcao
     FROM tripulante t
     JOIN usuario u ON u.cpf = t.cpf
     ORDER BY u.nome_completo'
)->fetchAll();

renderizar_cabecalho('Tripulação', $usuarioLogado);
?>

<div class="pagina-gerencia">
  <a class="secondary-btn px-4 py-2" href="gerente.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina">Tripulação</h1>

  <?php if($mensagem): ?>
    <div class="aviso aviso-sucesso">
      ✓ <?=esc($mensagem)?>
    </div>
  <?php endif; ?>

  <?php if($erro): ?>
    <div class="aviso aviso-erro">
      ✗ <?=esc($erro)?>
    </div>
  <?php endif; ?>

  <div class="grade-formularios grade-formularios-gerencia">

    <!-- Neste formulário a gente define a função de um tripulante. -->
    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Cadastrar tripulante</h2>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?=esc(csrf_token())?>">
        <input type="hidden" name="acao" value="tripulante">
        
        <p class="texto-ajuda">Informe o CPF e a função fixa do usuário cadastrado.</p>
        
        <div class="campo-form">
          <label>CPF (11 dígitos)</label>
          <input name="cpf" maxlength="11" placeholder="Ex: 12345678901" required>
        </div>

        <div class="campo-form">
          <label>Função</label>
          <small>Pilotos também podem ser escalados como copilotos.</small>
          <select name="funcao_tripulante" required>
            <option value="">Selecione...</option>
            <option value="piloto">Piloto</option>
            <option value="copiloto">Copiloto</option>
            <option value="comissario">Comissário de bordo</option>
          </select>
        </div>
        
        <button type="submit" class="botao botao-bloco">Salvar tripulante</button>
      </form>
    </div>

    <!-- Esta lista mostra a equipe cadastrada. -->
    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Tripulantes Cadastrados</h2>
      <?php if(count($tripulantes) > 0): ?>
        <div class="lista-gerencia">
          <?php foreach($tripulantes as $c): ?>
            <div class="linha-tripulante">
              <strong><?=esc($c['nome_completo'])?></strong><br>
              <span class="texto-secundario texto-secundario-pequeno"><?=esc($c['email'])?> · CPF <?=esc($c['cpf'])?> · <?=esc($nomes_funcoes_tripulantes[$c['funcao']] ?? 'Função não definida')?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="texto-vazio">Nenhum tripulante cadastrado.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php renderizar_rodape(); ?>

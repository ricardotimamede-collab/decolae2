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
         * Aqui a gente confere os códigos dos aeroportos e salva o trajeto.
         */
        if ($acao === 'trajeto') {
            $origem = obter_campo_obrigatorio('origem');
            $destino = obter_campo_obrigatorio('destino');

            if (mb_strtolower($origem) === mb_strtolower($destino)) {
                throw new InvalidArgumentException('Origem e destino devem ser diferentes.');
            }
            
            // Se os dados estiverem certos, a gente grava o trajeto.
            $comando = $conexaoBancoDados->prepare(
                'INSERT INTO trajeto (origem, destino, h_partida, h_chegada, gerente_cpf) 
                 VALUES (?, ?, ?, ?, ?)'
            );
            $comando->execute([
                $origem,
                $destino,
                obter_campo_obrigatorio('h_partida'),
                obter_campo_obrigatorio('h_chegada'),
                $usuarioLogado['cpf']
            ]);
            
            // Também guardamos no log quem cadastrou o trajeto.
            log_action(
                $conexaoBancoDados,
                'TRAJETO_CRIADO',
                "Trajeto {$origem} → {$destino} cadastrado.",
                'trajeto:' . $conexaoBancoDados->lastInsertId(),
                $usuarioLogado['cpf']
            );
            
            $mensagem = 'Trajeto cadastrado.';
        }
    } catch (Throwable $excecao) {
        if ($conexaoBancoDados->inTransaction()) {
            $conexaoBancoDados->rollBack();
        }

        $erro = $excecao->getMessage();
    }
}

$trajetos = $conexaoBancoDados->query(
    'SELECT id, origem, destino, h_partida, h_chegada
     FROM trajeto
     ORDER BY origem, destino'
)->fetchAll();

renderizar_cabecalho('Trajetos', $usuarioLogado);
?>

<div class="pagina-gerencia">
  <a class="secondary-btn px-4 py-2" href="gerente.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina">Trajetos</h1>

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

    <!-- Neste formulário a gente cadastra um trajeto. -->
    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Cadastrar Trajeto</h2>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?=esc(csrf_token())?>">
        <input type="hidden" name="acao" value="trajeto">
        
        <div class="campo-form">
          <label>Origem</label>
          <input name="origem" maxlength="60" placeholder="Ex: Belo Horizonte (CNF)" required>
        </div>
        
        <div class="campo-form">
          <label>Destino</label>
          <input name="destino" maxlength="60" placeholder="Ex: São Paulo (GRU)" required>
        </div>
        
        <div class="campo-form">
          <label>Horário de Partida</label>
          <input name="h_partida" type="time" required>
        </div>
        
        <div class="campo-form">
          <label>Horário de Chegada</label>
          <input name="h_chegada" type="time" required>
        </div>
        
        <button type="submit" class="botao botao-bloco">Salvar Trajeto</button>
      </form>
    </div>

    <!-- Esta lista mostra os trajetos cadastrados. -->
    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Trajetos Cadastrados</h2>
      <?php if(count($trajetos) > 0): ?>
        <div class="lista-gerencia">
          <?php foreach($trajetos as $r): ?>
            <div class="linha-rota">
              <strong><?=esc($r['origem'])?> → <?=esc($r['destino'])?></strong>
              <span class="texto-secundario"><?=esc(substr($r['h_partida'],0,5))?>–<?=esc(substr($r['h_chegada'],0,5))?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="texto-vazio">Nenhum trajeto cadastrado.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php renderizar_rodape(); ?>

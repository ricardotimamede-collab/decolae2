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
         * Aqui a gente cadastra a aeronave e prepara todos os assentos.
         */
        if ($acao === 'aeronave') {
            // Primeiro a gente lê as informações da aeronave.
            $identificacao = strtoupper(obter_campo_obrigatorio('identificacao'));
            $modelo = obter_campo_obrigatorio('modelo');
            $fileiras = max(1, (int)obter_campo_obrigatorio('fileiras'));
            $assentos_por_fileira = max(1, min(6, (int)obter_campo_obrigatorio('assentos_por_fileira')));
            
            // A transação mantém a aeronave e os assentos sincronizados.
            $conexaoBancoDados->beginTransaction();
            
            try {
                // Aqui a gente salva a aeronave antes de criar seus assentos.
                $comando = $conexaoBancoDados->prepare(
                    'INSERT INTO aeronave (identificacao, modelo, fileiras, assentos_por_fileira, gerente_cpf) 
                     VALUES (?, ?, ?, ?, ?)'
                );
                $comando->execute([
                    $identificacao,
                    $modelo,
                    $fileiras,
                    $assentos_por_fileira,
                    $usuarioLogado['cpf']
                ]);
                
                // Precisamos deste ID para vincular os assentos à aeronave.
                $id_aeronave = $conexaoBancoDados->lastInsertId();
                
                // Agora a gente prepara a inclusão dos assentos.
                $comando_assento = $conexaoBancoDados->prepare(
                    'INSERT INTO assento (aeronave_id, codigo_assento, numero_fileira, letra) 
                     VALUES (?, ?, ?, ?)'
                );
                
                // A gente percorre as fileiras e cria as posições de cada uma.
                for ($numeroFileira = 1; $numeroFileira <= $fileiras; $numeroFileira++) {
                    for ($indiceAssento = 0; $indiceAssento < $assentos_por_fileira; $indiceAssento++) {
                        // Cada posição vira uma letra: 0 é A, 1 é B e assim por diante.
                        $letraAssento = chr(65 + $indiceAssento);
                        
                        // E salva a posição vinculada à aeronave.
                        $comando_assento->execute([
                            $id_aeronave,
                            $numeroFileira . $letraAssento,
                            $numeroFileira,
                            $letraAssento
                        ]);
                    }
                }
                
                // Também deixamos registrado quem cadastrou a aeronave.
                log_action(
                    $conexaoBancoDados,
                    'AERONAVE_CRIADA',
                    "Aeronave {$identificacao} cadastrada.",
                    'aeronave:' . $id_aeronave,
                    $usuarioLogado['cpf']
                );
                
                // Com tudo salvo, a gente confirma a transação.
                $conexaoBancoDados->commit();
                $mensagem = 'Aeronave e seus assentos cadastrados.';
            } catch (Throwable $excecao) {
                // Se algo falhar, a gente desfaz o cadastro e os assentos.
                $conexaoBancoDados->rollBack();
                throw $excecao;
            }
        }
    } catch (Throwable $excecao) {
        if ($conexaoBancoDados->inTransaction()) {
            $conexaoBancoDados->rollBack();
        }

        $erro = $excecao->getMessage();
    }
}

$aeronaves = $conexaoBancoDados->query(
    'SELECT a.*, COUNT(s.id) AS capacidade
     FROM aeronave a
     LEFT JOIN assento s ON s.aeronave_id = a.id
     GROUP BY a.id
     ORDER BY a.identificacao'
)->fetchAll();

renderizar_cabecalho('Aeronaves', $usuarioLogado);
?>

<div class="pagina-gerencia">
  <a class="secondary-btn px-4 py-2" href="gerente.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina">Aeronaves</h1>

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

    <!-- Neste formulário a gente cadastra uma aeronave e seus assentos. -->
    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Cadastrar Aeronave</h2>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?=esc(csrf_token())?>">
        <input type="hidden" name="acao" value="aeronave">
        
        <div class="campo-form">
          <label>Identificação</label>
          <input name="identificacao" placeholder="Ex: PT-ABC" required>
        </div>
        
        <div class="campo-form">
          <label>Modelo</label>
          <input name="modelo" placeholder="Ex: Boeing 737" required>
        </div>
        
        <div class="campo-form">
          <label>Fileiras</label>
          <input name="fileiras" type="number" min="1" value="6" required>
        </div>
        
        <div class="campo-form">
          <label>Assentos por Fileira</label>
          <input name="assentos_por_fileira" type="number" min="1" max="6" value="6" required>
        </div>
        
        <button type="submit" class="botao botao-bloco">Salvar Aeronave</button>
      </form>
    </div>

    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Aeronaves Cadastradas</h2>
      <?php if(count($aeronaves) > 0): ?>
        <div class="lista-gerencia">
          <?php foreach($aeronaves as $a): ?>
            <div class="linha-rota">
              <strong><?=esc($a['identificacao'])?> · <?=esc($a['modelo'])?></strong>
              <span class="texto-secundario"><?=esc($a['capacidade'])?> assentos</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="texto-vazio">Nenhuma aeronave cadastrada.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php renderizar_rodape(); ?>

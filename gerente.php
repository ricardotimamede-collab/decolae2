<?php

/**
 * Aqui a gente administra os cadastros usados na operação.
 * 
 * Nesta tela, o gerente cuida de trajetos, aeronaves e tripulantes.
 */

declare(strict_types=1);

// Aqui a gente carrega as funções e o layout desta página.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

// Essas variáveis guardam os avisos exibidos depois das ações.
$conexaoBancoDados = db();
$usuarioLogado = require_role($conexaoBancoDados, 'gerente');
$mensagem = null;
$erro = null;

/**
 * Aqui a gente lê um campo obrigatório enviado pelo formulário.
 * 
 * @param string $nomeCampo Nome do campo a recuperar
 * @return string Valor do campo trimado
 * @throws InvalidArgumentException Se o campo estiver vazio
 */
function obter_campo_obrigatorio(string $nomeCampo): string
{
    // A gente remove espaços extras antes de conferir o valor.
    $valor = trim((string)($_POST[$nomeCampo] ?? ''));
    
    // Sem valor, avisamos que o campo precisa ser preenchido.
    if ($valor === '') {
        throw new InvalidArgumentException('Preencha todos os campos.');
    }
    
    return $valor;
}

/**
 * Aqui a gente confere o token e identifica qual cadastro foi enviado.
 */
if (is_post()) {
    try {
        // Primeiro a gente valida o token de segurança do formulário.
        verify_csrf($_POST['csrf_token'] ?? null);
        
        // Esse campo indica se vamos cadastrar rota, aeronave ou tripulante.
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
        /**
         * Aqui a gente cadastra a aeronave e prepara todos os assentos.
         */
        elseif ($acao === 'aeronave') {
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
        /**
         * Aqui a gente cadastra um usuário como tripulante ou atualiza sua função.
         */
        elseif ($acao === 'tripulante') {
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
        // Se alguma transação ainda estiver aberta, a gente desfaz as alterações.
        if ($conexaoBancoDados->inTransaction()) {
            $conexaoBancoDados->rollBack();
        }
        
        // Este aviso explica o que impediu o cadastro.
        $erro = $excecao->getMessage();
    }
}

/**
 * Aqui a gente lista os trajetos em ordem de origem e destino.
 */
$trajetos = $conexaoBancoDados->query(
    'SELECT id, origem, destino, h_partida, h_chegada 
     FROM trajeto 
     ORDER BY origem, destino'
)->fetchAll();

/**
 * Aqui a gente lista as aeronaves junto com a quantidade de assentos.
 */
$aeronaves = $conexaoBancoDados->query(
    'SELECT a.*, COUNT(s.id) AS capacidade 
     FROM aeronave a 
     LEFT JOIN assento s ON s.aeronave_id = a.id 
     GROUP BY a.id 
     ORDER BY a.identificacao'
)->fetchAll();

/**
 * Aqui a gente reúne os dados das contas cadastradas como tripulantes.
 */
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

// A partir daqui a gente monta a tela da gerência.
renderizar_cabecalho('Gerência', $usuarioLogado);
?>

<div class="pagina-gerencia">
  <h1 class="titulo-pagina">Cadastros da Companhia</h1>
  
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
  
  <!-- Aqui ficam os formulários para incluir e atualizar cadastros. -->
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
  </div>
  
  <!-- Aqui a gente confere os cadastros já existentes. -->
  <div class="grade-listas">
    
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

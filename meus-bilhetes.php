<?php

declare(strict_types=1);

// Aqui a gente carrega as funções compartilhadas e o layout da página.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

// Esta área é exclusiva para quem entrou como cliente.
if (!isset($_SESSION['cpf_usuario']) || $_SESSION['papel_usuario'] !== 'cliente') {
    header('Location: login-cliente.php');
    exit;
}

$conexao_pdo = db();
$usuario = obter_usuario_atual($conexao_pdo);
$aviso = obter_aviso();

renderizar_cabecalho('Meus Bilhetes', $usuario);
?>

<div class="secao-voos">
  <?php if ($aviso): ?>
    <div class="aviso aviso-<?= esc($aviso['tipo']) ?>"><?= $aviso['tipo'] === 'sucesso' ? '✓' : '✗' ?> <?= esc($aviso['texto']) ?></div>
  <?php endif; ?>

<div id="meus-bilhetes" class="secao-bilhetes">
  <h2 class="titulo-secao-bilhetes">Meus Bilhetes</h2>
  
  <div id="bilhetes-container" class="lista-bilhetes">
    <div class="estado-bilhetes">
      <p class="texto-secundario texto-pequeno">Carregando bilhetes...</p>
    </div>
  </div>
</div>
</div>

<!-- Aqui a gente carrega e atualiza os bilhetes sem recarregar a página. -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    carregarBilhetes();
    
    // Os botões de cancelamento recebem seus eventos dentro de carregarBilhetes().
});

async function carregarBilhetes() {
    try {
        const resposta = await fetch('api/minhas_passagens.php', { cache: 'no-store' });
        const dados = await resposta.json();
        
        const container = document.querySelector('#bilhetes-container');
        
        if (!dados.ok) {
            container.innerHTML = `<div class="estado-bilhetes estado-bilhetes-aviso">
                <p class="texto-secundario texto-pequeno">${dados.message || 'Erro ao carregar bilhetes.'}</p>
            </div>`;
            return;
        }
        
        if (dados.total === 0) {
            container.innerHTML = `<div class="estado-bilhetes">
                <p class="estado-bilhetes-titulo">Nenhum bilhete encontrado</p>
                <p class="texto-pequeno">Faça uma reserva para gerar um bilhete.</p>
            </div>`;
            return;
        }
        
        // Aqui a gente atualiza a lista de bilhetes na tela.
        let html = '';
        dados.passagens.forEach(passagem => {
            const dataCriacao = new Date(passagem.data_compra);
            const dataCriacaoFormatada = dataCriacao.toLocaleDateString('pt-BR') + ' ' + dataCriacao.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            
            // Bilhetes cancelados não oferecem outra ação de cancelamento.
            const mostrarBotao = passagem.status !== 'cancelada';
            
            html += `
                <div class="cartao-bilhete">
                    ${mostrarBotao ? `<button class="btn-cancelar-bilhete botao-cancelar-bilhete" data-bilhete-id="${passagem.id}">
                        Cancelar
                    </button>` : ''}
                    
                    <div class="conteudo-bilhete">
                        <div class="linha-bilhete">
                            <strong>Bilhete:</strong> ${passagem.numero_bilhete} | <strong>Status:</strong> ${passagem.status}
                        </div>
                        
                        <div class="linha-bilhete">
                            ${passagem.origem} ${new Date(passagem.partida_em).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })} → ${passagem.destino} ${new Date(passagem.chegada_em).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                        </div>
                        
                        <div class="linha-bilhete">
                            Voo: ${passagem.numero_voo} | Aeronave: ${passagem.modelo} | Assento: <strong>${passagem.codigo_assento}</strong>
                        </div>
                        
                        <div>
                            Valor: <strong>R$ ${parseFloat(passagem.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</strong> | Emitido: ${dataCriacaoFormatada}
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
        // Agora a gente liga cada botão ao processo de cancelamento.
        const botoesCancelar = container.querySelectorAll('.btn-cancelar-bilhete');
        
        botoesCancelar.forEach((botao, indice) => {
            botao.addEventListener('click', async (e) => {
                e.preventDefault();
                
                const idBilhete = parseInt(botao.dataset.bilheteId);
                
                botao.disabled = true;
                botao.innerHTML = 'Cancelando...';
                
                try {
                    const controller = new AbortController();
                    const timeout = setTimeout(() => controller.abort(), 5000);
                    
                    const resposta = await fetch('api/cancelar_bilhete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id_passagem: idBilhete }),
                        signal: controller.signal
                    });
                    
                    clearTimeout(timeout);
                    
                    const dados = await resposta.json();
                    
                    if (!dados.ok) {
                        throw new Error(dados.message || 'Erro ao cancelar bilhete');
                    }
                    
                    setTimeout(() => {
                        carregarBilhetes();
                    }, 1000);
                    
                    mostrarToast('Bilhete cancelado com sucesso!');
                    
                } catch (erro) {
                    if (erro.name === 'AbortError') {
                        mostrarToast('Erro: Timeout ao cancelar', 'erro');
                    } else {
                        mostrarToast(erro.message || 'Erro ao cancelar bilhete', 'erro');
                    }
                    
                    botao.disabled = false;
                    botao.innerHTML = 'Cancelar';
                }
            });
        });
        
    } catch (erro) {
        const container = document.querySelector('#bilhetes-container');
        container.innerHTML = `<div class="estado-bilhetes estado-bilhetes-aviso">
            <p class="texto-secundario texto-pequeno">Erro ao carregar bilhetes. Tente novamente.</p>
        </div>`;
    }
}
</script>

<?php renderizar_rodape(); ?>

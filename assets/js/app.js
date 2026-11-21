/**
 * Aqui a gente cuida da busca de voos, da escolha de assentos e das reservas.
 */

(function() {
    console.log('App DECOLAE iniciado');
    
    // A gente espera a página carregar antes de procurar os elementos.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarApp);
    } else {
        inicializarApp();
    }

    function inicializarApp() {
        console.log('Inicializando aplicação...');
        
        // Aqui ficam os elementos da página que vamos usar.
        const modalAssentos = document.querySelector('#seat-modal');
        const mapaAssentos = document.querySelector('#seat-map');
        const resumoReserva = document.querySelector('#booking-summary');
        const botaoConfirmar = document.querySelector('#confirm-booking');
        const formularioBusca = document.querySelector('#search-form');
        
        // Este token acompanha as requisições que precisam de proteção.
        const tokenCsrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        console.log('Modal encontrado:', !!modalAssentos);
        console.log('Formulário encontrado:', !!formularioBusca);
        
        // Aqui a gente guarda o voo e o assento escolhidos.
        let assentoSelecionado = null;
        let vooAtivo = null;
        let reservaEmAndamento = false;

        /**
         * Aqui a gente escapa o texto antes de colocá-lo no HTML.
         */
        function escaparHtml(valor) {
            const texto = String(valor || '');
            const mapa = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return texto.replace(/[&<>"']/g, char => mapa[char]);
        }

        /**
         * Aqui a gente mostra o valor no formato de moeda brasileira.
         */
        function formatarMoeda(valor) {
            return `R$ ${parseFloat(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
        }

        /**
         * Aqui a gente transforma a data e hora em um horário fácil de ler.
         */
        function formatarHora(datetime) {
            const data = new Date(datetime.replace(' ', 'T'));
            return data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        /**
         * Aqui a gente mostra um aviso rápido na tela.
         */
        function mostrarToast(mensagem, tipo = 'sucesso') {
            const toast = document.querySelector('#toast');
            if (!toast) return;
            
            toast.textContent = mensagem;
            toast.classList.toggle('aviso-flutuante-erro', tipo === 'erro');
            toast.hidden = false;
            
            setTimeout(() => {
                toast.hidden = true;
            }, 4000);
        }

        /**
         * Aqui a gente liga os botões de voo à abertura do mapa de assentos.
         */
        function anexarEventosBotoes() {
            const botoes = document.querySelectorAll('.select-flight-btn');
            console.log('Botões encontrados:', botoes.length);
            
            botoes.forEach((botao, index) => {
                console.log(`Anexando evento ao botão ${index + 1}`, botao.dataset.flightId);
                botao.addEventListener('click', (e) => {
                    e.preventDefault();
                    console.log('Botão clicado, abrindo modal para voo:', botao.dataset.flightId);
                    abrirModalAssentos(botao.dataset.flightId);
                });
            });
        }

        /**
         * Aqui a gente abre o modal do voo escolhido.
         */
        async function abrirModalAssentos(idVoo) {
            console.log('Abrindo modal para voo ID:', idVoo);
            
            if (!modalAssentos || !mapaAssentos) {
                console.error('Modal ou mapa de assentos não encontrado');
                return;
            }
            
            assentoSelecionado = null;
            vooAtivo = null;
            
            if (botaoConfirmar) {
                botaoConfirmar.disabled = true;
            }
            
            mapaAssentos.innerHTML = '<div class="estado-busca">Carregando mapa de assentos…</div>';
            
            if (resumoReserva) {
                resumoReserva.innerHTML = '<p class="texto-resumo-vazio">Selecione um assento para continuar.</p>';
            }
            
            // Agora a pessoa pode escolher um assento.
            modalAssentos.hidden = false;
            document.body.classList.add('modal-aberto');
            
            try {
                console.log('Fazendo requisição para:', `api/assentos_voo.php?id_voo=${encodeURIComponent(idVoo)}`);
                const resposta = await fetch(`api/assentos_voo.php?id_voo=${encodeURIComponent(idVoo)}`);
                console.log('Resposta recebida, status:', resposta.status);
                
                const dados = await resposta.json();
                console.log('Dados JSON:', dados);
                
                if (!dados.ok) {
                    throw new Error(dados.message || 'Erro desconhecido');
                }
                
                vooAtivo = dados.flight;
                const tituloModal = document.querySelector('#modal-title');
                const subtituloModal = document.querySelector('#modal-subtitle');
                
                if (tituloModal) {
                    tituloModal.textContent = `${escaparHtml(vooAtivo.origem)} → ${escaparHtml(vooAtivo.destino)}`;
                }
                
                if (subtituloModal) {
                    subtituloModal.textContent = `${escaparHtml(vooAtivo.numero_voo)} · ${escaparHtml(vooAtivo.modelo)} · ${formatarHora(vooAtivo.partida_em)}`;
                }
                
                renderizarMapaAssentos(dados.seats);
                console.log('Mapa de assentos renderizado com sucesso');
            } catch (erro) {
                console.error('Erro ao carregar assentos:', erro);
                mapaAssentos.innerHTML = `<div class="estado-busca estado-busca-erro">${escaparHtml(erro.message)}</div>`;
                mostrarToast(erro.message, 'erro');
            }
        }

        /**
         * Aqui a gente monta o mapa com os assentos recebidos da API.
         */
        function renderizarMapaAssentos(assentos) {
            console.log('Renderizando mapa com', assentos.length, 'assentos');
            
            const linhas = {};
            
            assentos.forEach(assento => {
                if (!linhas[assento.numero_fileira]) {
                    linhas[assento.numero_fileira] = [];
                }
                linhas[assento.numero_fileira].push(assento);
            });
            
            let html = '';
            Object.entries(linhas).forEach(([numeroLinha, assentosLinha]) => {
                const porsLetra = {};
                assentosLinha.forEach(assento => {
                    porsLetra[assento.letra] = assento;
                });
                
                html += `<div class="seat-row">`;
                html += `<span class="row-label">${escaparHtml(numeroLinha)}</span>`;
                
                // As três primeiras posições ficam deste lado do corredor.
                ['A', 'B', 'C'].forEach(letra => {
                    const assento = porsLetra[letra];
                    if (assento) {
                        const desabilitado = assento.status === 'ocupado' ? 'disabled' : '';
                        const classeOcupado = assento.status === 'ocupado' ? 'occupied' : '';
                        html += `<button class="seat ${classeOcupado}" ${desabilitado} data-seat-id="${assento.id}" data-seat-code="${escaparHtml(assento.codigo_assento)}">${escaparHtml(assento.codigo_assento)}</button>`;
                    } else {
                        html += `<span class="seat-empty"></span>`;
                    }
                });
                
                html += `<span class="aisle">·</span>`;
                
                // As outras três posições ficam do outro lado.
                ['D', 'E', 'F'].forEach(letra => {
                    const assento = porsLetra[letra];
                    if (assento) {
                        const desabilitado = assento.status === 'ocupado' ? 'disabled' : '';
                        const classeOcupado = assento.status === 'ocupado' ? 'occupied' : '';
                        html += `<button class="seat ${classeOcupado}" ${desabilitado} data-seat-id="${assento.id}" data-seat-code="${escaparHtml(assento.codigo_assento)}">${escaparHtml(assento.codigo_assento)}</button>`;
                    } else {
                        html += `<span class="seat-empty"></span>`;
                    }
                });
                
                html += `</div>`;
            });
            
            mapaAssentos.innerHTML = html;
            
            // Cada assento livre recebe o evento de seleção.
            mapaAssentos.querySelectorAll('.seat:not(.occupied)').forEach(botaoAssento => {
                botaoAssento.addEventListener('click', (e) => {
                    e.preventDefault();
                    selecionarAssento(botaoAssento);
                });
            });
        }

        /**
         * Aqui a gente atualiza a seleção para o assento clicado.
         */
        function selecionarAssento(botaoAssento) {
            console.log('Assento selecionado:', botaoAssento.dataset.seatCode);
            
            // Primeiro tiramos o destaque da escolha anterior.
            mapaAssentos.querySelectorAll('.seat.selected').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Depois destacamos o novo assento.
            botaoAssento.classList.add('selected');
            
            assentoSelecionado = {
                id: botaoAssento.dataset.seatId,
                codigo: botaoAssento.dataset.seatCode
            };
            
            if (botaoConfirmar) {
                botaoConfirmar.disabled = false;
            }
            
            // O resumo acompanha a escolha atual.
            if (resumoReserva && vooAtivo) {
                resumoReserva.innerHTML = `
                    <div class="resumo-reserva-linha">
                        <span class="resumo-reserva-legenda">Voo</span>
                        <strong class="resumo-reserva-valor">${escaparHtml(vooAtivo.numero_voo)}</strong>
                    </div>
                    <div class="resumo-reserva-linha">
                        <span class="resumo-reserva-legenda">Assento</span>
                        <strong class="resumo-reserva-assento">${escaparHtml(assentoSelecionado.codigo)}</strong>
                    </div>
                    <div class="resumo-reserva-linha resumo-reserva-total-linha">
                        <span class="resumo-reserva-legenda">Total</span>
                        <strong class="resumo-reserva-total">${formatarMoeda(vooAtivo.valor_base)}</strong>
                    </div>
                `;
            }
        }

        /**
         * Aqui a gente fecha o mapa e limpa a seleção em andamento.
         */
        function fecharModal() {
            if (!modalAssentos) return;
            modalAssentos.hidden = true;
            document.body.classList.remove('modal-aberto');
        }

        // Aqui a gente conecta os eventos aos controles da página.

        // Quando a pessoa busca, a gente atualiza a lista de voos.
        if (formularioBusca) {
            console.log('Anexando listener ao formulário de busca');
            formularioBusca.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const botao = formularioBusca.querySelector('button[type="submit"]');
                const textoOriginal = botao.innerHTML;
                botao.disabled = true;
                botao.innerHTML = 'Buscando…';
                
                const params = new URLSearchParams(new FormData(formularioBusca));
                console.log('Parâmetros de busca:', params.toString());
                
                try {
                    const resposta = await fetch(`api/buscar_voos.php?${params.toString()}`);
                    const dados = await resposta.json();
                    
                    if (!dados.ok) {
                        throw new Error(dados.message);
                    }
                    
                    console.log('Voos encontrados:', dados.voos.length);
                    
                    // Os resultados recebidos viram cartões na tela.
                    const flightList = document.querySelector('#flight-list');
                    const emptyState = document.querySelector('#empty-state');
                    
                    if (flightList) {
                        if (dados.voos.length === 0) {
                            flightList.hidden = true;
                            if (emptyState) {
                                emptyState.hidden = false;
                            }
                        } else {
                            flightList.hidden = false;
                            if (emptyState) {
                                emptyState.hidden = true;
                            }
                            
                            flightList.innerHTML = dados.voos.map(voo => {
                                const dataSaida = new Date(voo.partida_em.replace(' ', 'T'));
                                const dataChegada = new Date(voo.chegada_em.replace(' ', 'T'));
                                const duracao = Math.floor((dataChegada - dataSaida) / (1000 * 60));
                                const horas = Math.floor(duracao / 60);
                                const minutos = String(duracao % 60).padStart(2, '0');
                                
                                const assentosLivres = parseInt(voo.assentos_livres) || 0;
                                const capacidade = parseInt(voo.capacidade) || 0;
                                
                                return `
                                    <div class="flight-card-simples cartao-resultado-voo" 
                                         data-flight-card 
                                         data-flight-id="${escaparHtml(voo.id)}" 
                                         data-origin="${escaparHtml(voo.origem)}" 
                                         data-destination="${escaparHtml(voo.destino)}" 
                                         data-date="${escaparHtml(voo.data_voo)}">
                                        
                                        <div class="cabecalho-resultado-voo">
                                            <div>
                                                <p class="rotulo-resultado-voo">Voo ${escaparHtml(voo.numero_voo)}</p>
                                                <p class="modelo-resultado-voo">${escaparHtml(voo.modelo)}</p>
                                            </div>
                                            <div class="status-resultado-voo">
                                                <p class="rotulo-resultado-voo">Status</p>
                                                <p class="valor-status-resultado-voo">${escaparHtml(voo.status)}</p>
                                            </div>
                                            <div class="assentos-resultado-voo">
                                                <p class="rotulo-resultado-voo">Assentos</p>
                                                <p class="valor-assentos-resultado-voo">${assentosLivres}/${capacidade}</p>
                                            </div>
                                        </div>
                                        
                                        <div class="horarios-resultado-voo">
                                            <div>
                                                <p class="rotulo-resultado-voo">Origem</p>
                                                <p class="local-resultado-voo">${escaparHtml(voo.origem)}</p>
                                                <p class="data-resultado-voo">${new Date(voo.partida_em.replace(' ', 'T')).toLocaleDateString('pt-BR')}</p>
                                                <p class="horario-resultado-voo">${new Date(voo.partida_em.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</p>
                                            </div>
                                            
                                            <div class="duracao-resultado-voo">
                                                <p class="rotulo-resultado-voo">Duração</p>
                                                <p class="data-resultado-voo">${horas}h ${minutos}m</p>
                                                <p class="seta-resultado-voo">→</p>
                                            </div>
                                            
                                            <div>
                                                <p class="rotulo-resultado-voo">Destino</p>
                                                <p class="local-resultado-voo">${escaparHtml(voo.destino)}</p>
                                                <p class="data-resultado-voo">${new Date(voo.chegada_em.replace(' ', 'T')).toLocaleDateString('pt-BR')}</p>
                                                <p class="horario-resultado-voo">${new Date(voo.chegada_em.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</p>
                                            </div>
                                        </div>
                                        
                                        <div class="acoes-resultado-voo">
                                            <div>
                                                <p class="rotulo-resultado-voo">A partir de</p>
                                                <p class="preco-resultado-voo">R$ ${parseFloat(voo.valor_base).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</p>
                                            </div>
                                            <button class="select-flight-btn botao-resultado-voo" data-flight-id="${escaparHtml(voo.id)}">Escolher Assento →</button>
                                        </div>
                                    </div>
                                `;
                            }).join('');
                        }
                    }
                    
                    mostrarToast(`${dados.voos.length} ${dados.voos.length === 1 ? 'voo encontrado' : 'voos encontrados'}.`);
                    
                    // Os novos cartões também precisam dos eventos de seleção.
                    setTimeout(anexarEventosBotoes, 100);
                } catch (erro) {
                    console.error('Erro ao buscar voos:', erro);
                    mostrarToast(erro.message || 'Erro ao buscar voos.', 'erro');
                } finally {
                    botao.disabled = false;
                    botao.innerHTML = textoOriginal;
                }
            });
        }

        // Aqui a gente confirma a reserva do assento escolhido.
        if (botaoConfirmar) {
            botaoConfirmar.addEventListener('click', async () => {
                // Assim a pessoa não envia a mesma reserva várias vezes.
                if (botaoConfirmar.disabled || reservaEmAndamento) return;
                
                console.log('Confirmando reserva');
                
                if (!assentoSelecionado || !vooAtivo) {
                    console.error('Assento ou voo não selecionado');
                    return;
                }
                
                reservaEmAndamento = true;
                botaoConfirmar.disabled = true;
                const textoOriginal = botaoConfirmar.innerHTML;
                botaoConfirmar.innerHTML = 'Confirmando…';
                
                try {
                    const corpo = new URLSearchParams({
                        token_csrf: tokenCsrf,
                        id_voo: vooAtivo.id,
                        id_assento: assentoSelecionado.id
                    });
                    
                    const resposta = await fetch('api/reservar.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: corpo
                    });
                    
                    const dados = await resposta.json();
                    console.log('Resposta da reserva:', dados);
                    
                    if (!dados.ok) {
                        throw new Error(dados.message);
                    }
                    
                    fecharModal();
                    mostrarToast(`Reserva confirmada · bilhete ${dados.ticket}.`);

                    if (typeof carregarBilhetes === 'function') {
                        await carregarBilhetes();
                    }
                } catch (erro) {
                    console.error('Erro ao confirmar reserva:', erro);
                    mostrarToast(erro.message || 'Erro ao confirmar reserva.', 'erro');
                    botaoConfirmar.disabled = false;
                } finally {
                    reservaEmAndamento = false;
                    botaoConfirmar.innerHTML = textoOriginal;
                }
            });
        }

        // O botão de fechar encerra o modal.
        const botaoFechar = document.querySelector('#close-modal');
        if (botaoFechar) {
            botaoFechar.addEventListener('click', fecharModal);
        }

        // Clicar fora do conteúdo também fecha o modal.
        if (modalAssentos) {
            modalAssentos.addEventListener('click', (e) => {
                if (e.target === modalAssentos) {
                    fecharModal();
                }
            });
        }

        // A tecla Escape oferece outra forma de fechar o modal.
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                fecharModal();
            }
        });

        // Por fim, a gente prepara os botões que já vieram na página.
        console.log('Anexando eventos aos botões iniciais');
        setTimeout(() => {
            anexarEventosBotoes();
        }, 100);
    }
})();

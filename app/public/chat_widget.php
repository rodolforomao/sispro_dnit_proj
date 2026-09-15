<!-- CHAT WIDGET - Som ICQ com foco automático e notificações em segundo plano -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
.chat-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* Botão de abrir/fechar */
.chat-widget .btn-chat {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #075e54;
    color: #fff;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    font-size: 30px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.chat-widget .btn-chat:hover { transform: scale(1.05); background: #054a42; }
.chat-widget .badge-notificacao {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: #fff;
    border-radius: 50%;
    padding: 2px 8px;
    font-size: 13px;
    font-weight: bold;
    min-width: 24px;
    text-align: center;
    border: 2px solid #fff;
    display: none;
}

/* Caixa do chat */
.chat-widget .chat-box {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 580px;
    max-height: 700px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e9ecef;
}
.chat-widget .chat-box.aberto { display: flex; }

/* Cabeçalho */
.chat-widget .chat-header {
    background: #075e54;
    color: #fff;
    padding: 10px 14px;
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    min-height: 48px;
    gap: 8px;
}
.chat-widget .chat-header span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
}
.chat-widget .chat-header .fechar {
    background: none;
    border: none;
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    padding: 4px 8px;
    line-height: 1;
    flex-shrink: 0;
    z-index: 10;
    border-radius: 4px;
    transition: background 0.2s;
}
.chat-widget .chat-header .fechar:hover {
    background: rgba(255,255,255,0.15);
}
.chat-widget .chat-header .btn-som {
    font-size: 18px;
    padding: 2px 6px;
}

/* Corpo do chat */
.chat-widget .chat-body {
    display: flex;
    height: 520px;
    min-height: 300px;
}

/* Lista de conversas */
.chat-widget .chat-lista-conversas {
    width: 40%;
    border-right: 1px solid #e9ecef;
    overflow-y: auto;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
}
.chat-widget .chat-lista-conversas .pesquisa {
    padding: 10px 12px;
    border-bottom: 1px solid #e9ecef;
    background: #fff;
    flex-shrink: 0;
}
.chat-widget .chat-lista-conversas .pesquisa input {
    width: 100%;
    border: 1px solid #ced4da;
    border-radius: 20px;
    padding: 8px 14px;
    outline: none;
    font-size: 0.9rem;
}
.chat-widget .chat-lista-conversas .pesquisa input:focus {
    border-color: #075e54;
}
.chat-widget .chat-lista-conversas .lista-scroll {
    flex: 1;
    overflow-y: auto;
}
.chat-widget .chat-lista-conversas .conversa {
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s;
}
.chat-widget .chat-lista-conversas .conversa:hover { background: #e9ecef; }
.chat-widget .chat-lista-conversas .conversa.ativo { background: #d4e3f7; }
.chat-widget .chat-lista-conversas .conversa .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #0d6efd;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
    position: relative;
}
.chat-widget .chat-lista-conversas .conversa .avatar .online-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
}
.chat-widget .chat-lista-conversas .conversa .avatar .online-indicator.online { background: #4caf50; }
.chat-widget .chat-lista-conversas .conversa .avatar .online-indicator.offline { background: #9e9e9e; }
.chat-widget .chat-lista-conversas .conversa .info {
    flex: 1;
    min-width: 0;
}
.chat-widget .chat-lista-conversas .conversa .info .nome {
    font-weight: 600;
    font-size: 0.95rem;
}
.chat-widget .chat-lista-conversas .conversa .info .ultima-msg {
    font-size: 0.85rem;
    color: #6c757d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-widget .chat-lista-conversas .conversa .badge-contato {
    background: #dc3545;
    color: #fff;
    border-radius: 50%;
    padding: 0 8px;
    font-size: 0.75rem;
    min-width: 20px;
    text-align: center;
    flex-shrink: 0;
}

/* Área de mensagens */
.chat-widget .chat-mensagens {
    width: 60%;
    display: flex;
    flex-direction: column;
    background: #fff;
}
.chat-widget .chat-mensagens .mensagens-area {
    flex: 1;
    padding: 12px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #ece5dd;
}
.chat-widget .chat-mensagens .mensagem-item {
    max-width: 75%;
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 0.95rem;
    word-wrap: break-word;
    background: #fff;
    align-self: flex-start;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.chat-widget .chat-mensagens .mensagem-item.minha {
    background: #dcf8c6;
    align-self: flex-end;
}
.chat-widget .chat-mensagens .mensagem-item .remetente {
    font-weight: 600;
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 3px;
}
.chat-widget .chat-mensagens .mensagem-item .hora {
    font-size: 0.7rem;
    color: #868e96;
    text-align: right;
    margin-top: 3px;
}
.chat-widget .chat-input-area {
    display: flex;
    padding: 10px;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
    flex-shrink: 0;
}
.chat-widget .chat-input-area input {
    flex: 1;
    border: 1px solid #ced4da;
    border-radius: 24px;
    padding: 10px 16px;
    outline: none;
    font-size: 0.95rem;
}
.chat-widget .chat-input-area input:focus { border-color: #075e54; }
.chat-widget .chat-input-area button {
    background: #075e54;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 42px;
    height: 42px;
    margin-left: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: background 0.2s;
}
.chat-widget .chat-input-area button:hover { background: #054a42; }
.chat-widget .mensagem-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6c757d;
    font-size: 1rem;
}

/* Responsividade */
@media (max-width: 1024px) {
    .chat-widget .chat-box {
        width: 500px;
        max-height: 600px;
        right: 10px;
        bottom: 75px;
    }
    .chat-widget .chat-body { height: 450px; }
    .chat-widget .chat-header { font-size: 0.95rem; padding: 8px 12px; min-height: 40px; }
    .chat-widget .chat-header .fechar { font-size: 20px; padding: 2px 6px; }
}
@media (max-width: 768px) {
    .chat-widget .chat-box {
        width: 95vw;
        right: 2.5vw;
        bottom: 80px;
        max-height: 80vh;
        border-radius: 12px;
    }
    .chat-widget .chat-body {
        flex-direction: column;
        height: auto;
        min-height: 400px;
    }
    .chat-widget .chat-lista-conversas {
        width: 100%;
        max-height: 150px;
        border-right: none;
        border-bottom: 1px solid #e9ecef;
        flex-shrink: 0;
    }
    .chat-widget .chat-mensagens {
        width: 100%;
        height: 300px;
        flex: 1;
    }
    .chat-widget .chat-header { font-size: 0.9rem; padding: 8px 10px; min-height: 36px; }
    .chat-widget .chat-header .fechar { font-size: 18px; padding: 2px 6px; }
    .chat-widget .btn-chat { width: 56px; height: 56px; font-size: 26px; }
    .chat-widget .chat-lista-conversas .conversa { padding: 8px 12px; }
    .chat-widget .chat-lista-conversas .conversa .info .nome { font-size: 0.9rem; }
    .chat-widget .chat-lista-conversas .conversa .info .ultima-msg { font-size: 0.8rem; }
    .chat-widget .chat-mensagens .mensagem-item { max-width: 85%; font-size: 0.9rem; padding: 8px 12px; }
    .chat-widget .chat-input-area input { font-size: 0.9rem; padding: 8px 12px; }
    .chat-widget .chat-input-area button { width: 38px; height: 38px; font-size: 18px; }
}
@media (max-width: 480px) {
    .chat-widget .chat-box {
        width: 98vw;
        right: 1vw;
        bottom: 70px;
        max-height: 85vh;
        border-radius: 10px;
    }
    .chat-widget .chat-header { font-size: 0.85rem; padding: 6px 10px; min-height: 32px; }
    .chat-widget .chat-header .fechar { font-size: 16px; padding: 2px 4px; }
    .chat-widget .btn-chat { width: 50px; height: 50px; font-size: 22px; }
    .chat-widget .chat-lista-conversas .conversa { padding: 6px 10px; }
    .chat-widget .chat-lista-conversas .conversa .avatar { width: 32px; height: 32px; font-size: 0.8rem; }
    .chat-widget .chat-mensagens .mensagens-area { padding: 8px; }
    .chat-widget .chat-mensagens .mensagem-item { font-size: 0.85rem; padding: 6px 10px; }
    .chat-widget .chat-input-area { padding: 6px 8px; }
    .chat-widget .chat-input-area input { font-size: 0.85rem; padding: 6px 10px; }
    .chat-widget .chat-input-area button { width: 34px; height: 34px; font-size: 16px; margin-left: 6px; }
}

/* Toast */
@keyframes slideIn {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
.chat-toast {
    position: fixed;
    bottom: 100px;
    right: 90px;
    background: #075e54;
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 99999;
    max-width: 300px;
    font-family: sans-serif;
    font-size: 0.95rem;
    animation: slideIn 0.3s ease;
    cursor: pointer;
    transition: opacity 0.5s;
}
</style>

<!-- HTML -->
<div class="chat-widget" id="chatWidget">
    <button class="btn-chat" id="btnAbrirChat" title="Chat">
        <i class="bi bi-chat-dots"></i>
        <span class="badge-notificacao" id="chatBadge">0</span>
    </button>

    <div class="chat-box" id="chatBox">
        <div class="chat-header" id="chatHeader">
            <span><i class="bi bi-chat-dots"></i> Conversas</span>
            <div style="display:flex; align-items:center; gap: 4px;">
                <button id="btnToggleSom" class="fechar btn-som" title="Ativar/Desativar som">
                    <i class="bi bi-volume-up"></i>
                </button>
                <button class="fechar" id="fecharChat"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>

        <div class="chat-body">
            <div class="chat-lista-conversas">
                <div class="pesquisa">
                    <input type="text" id="pesquisaContato" placeholder="Pesquisar contato..." />
                </div>
                <div class="lista-scroll" id="listaConversas"></div>
            </div>

            <div class="chat-mensagens" id="chatMensagens">
                <div class="mensagens-area" id="mensagensArea">
                    <div class="mensagem-placeholder" id="placeholderMsg">
                        <i class="bi bi-chat-dots" style="font-size: 2rem; margin-right: 8px;"></i>
                        Selecione uma conversa
                    </div>
                </div>
                <div class="chat-input-area" style="display:none;" id="chatInputArea">
                    <input type="text" id="inputMensagem" placeholder="Digite sua mensagem..." />
                    <button id="btnEnviar"><i class="bi bi-send"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatBox = document.getElementById('chatBox');
    const btnAbrir = document.getElementById('btnAbrirChat');
    const fechar = document.getElementById('fecharChat');
    const lista = document.getElementById('listaConversas');
    const mensagensArea = document.getElementById('mensagensArea');
    const chatInputArea = document.getElementById('chatInputArea');
    const inputMsg = document.getElementById('inputMensagem');
    const btnEnviar = document.getElementById('btnEnviar');
    const chatBadge = document.getElementById('chatBadge');
    const pesquisaInput = document.getElementById('pesquisaContato');

    let conversaAtiva = null;
    let usuarioId = <?= json_encode($usuario_id ?? 0) ?>;
    let intervaloTimer = null;
    let carregando = false;
    let ultimoIdMensagem = 0;

    // ============================================================
    // CONTROLE DE SOM (estilo ICQ - volume máximo)
    // ============================================================
    let somAtivado = localStorage.getItem('chat_som') !== 'false';
    let audioContext = null;

    // Função para inicializar o áudio (deve ser chamada após clique do usuário)
    function initAudio() {
        if (!audioContext) {
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                console.log('Áudio inicializado, estado:', audioContext.state);
            } catch (e) {
                console.warn('Web Audio API não suportada:', e);
            }
        }
        if (audioContext && audioContext.state === 'suspended') {
            audioContext.resume().then(() => {
                console.log('Áudio reativado, estado:', audioContext.state);
            }).catch(err => console.warn('Erro ao reativar áudio:', err));
        }
        return audioContext;
    }

    // Função interna que realmente toca os tons (já com contexto rodando)
    function playSom() {
        try {
            const now = audioContext.currentTime;
            
            // Primeiro tom (Lá - 440Hz)
            const osc1 = audioContext.createOscillator();
            const gain1 = audioContext.createGain();
            osc1.type = 'sine';
            osc1.frequency.value = 440;
            gain1.gain.setValueAtTime(0, now);
            gain1.gain.linearRampToValueAtTime(1.0, now + 0.02);
            gain1.gain.setValueAtTime(1.0, now + 0.15);
            gain1.gain.linearRampToValueAtTime(0, now + 0.25);
            osc1.connect(gain1);
            gain1.connect(audioContext.destination);
            osc1.start(now);
            osc1.stop(now + 0.25);

            // Segundo tom (Lá agudo - 880Hz) com delay de 100ms
            setTimeout(() => {
                if (!audioContext) return;
                const now2 = audioContext.currentTime;
                const osc2 = audioContext.createOscillator();
                const gain2 = audioContext.createGain();
                osc2.type = 'sine';
                osc2.frequency.value = 880;
                gain2.gain.setValueAtTime(0, now2);
                gain2.gain.linearRampToValueAtTime(1.0, now2 + 0.02);
                gain2.gain.setValueAtTime(1.0, now2 + 0.15);
                gain2.gain.linearRampToValueAtTime(0, now2 + 0.25);
                osc2.connect(gain2);
                gain2.connect(audioContext.destination);
                osc2.start(now2);
                osc2.stop(now2 + 0.25);
            }, 100);

            console.log('Som ICQ tocado (volume 1.0)!');
        } catch (e) {
            console.warn('Erro ao tocar som:', e);
        }
    }

    // Função pública para tocar som (verifica estado e inicializa se necessário)
    function tocarSom() {
        if (!somAtivado) {
            console.log('Som desativado');
            return;
        }
        // Se não houver contexto, tenta inicializar (mas pode falhar se não houve clique)
        if (!audioContext) {
            console.warn('Áudio não inicializado, tentando init...');
            initAudio();
            if (!audioContext) return;
        }
        // Se o contexto estiver suspenso, tenta resume
        if (audioContext.state === 'suspended') {
            audioContext.resume().then(() => {
                console.log('Áudio resumed para tocar som');
                playSom();
            }).catch(err => console.warn('Não foi possível resume:', err));
        } else if (audioContext.state === 'running') {
            playSom();
        } else {
            console.warn('Estado do áudio:', audioContext.state);
        }
    }

    function toggleSom() {
        somAtivado = !somAtivado;
        localStorage.setItem('chat_som', somAtivado ? 'true' : 'false');
        atualizarIconeSom();
        if (somAtivado) {
            // Toca som de teste para confirmar
            initAudio();
            if (audioContext) tocarSom();
        }
    }

    function atualizarIconeSom() {
        const btn = document.getElementById('btnToggleSom');
        if (btn) {
            btn.innerHTML = somAtivado ? '<i class="bi bi-volume-up"></i>' : '<i class="bi bi-volume-mute"></i>';
            btn.title = somAtivado ? 'Desativar som' : 'Ativar som';
        }
    }

    // Inicializa áudio no primeiro clique em qualquer lugar da página
    document.addEventListener('click', function initOnFirstClick() {
        if (!audioContext) {
            initAudio();
        }
        document.removeEventListener('click', initOnFirstClick);
    }, { once: true });

    // Evento do botão de som
    document.getElementById('btnToggleSom').addEventListener('click', function(e) {
        e.stopPropagation();
        initAudio(); // força inicialização
        toggleSom();
    });
    atualizarIconeSom();

    // ============================================================
    // TOGGLE CHAT
    // ============================================================
    function toggleChat(abrir) {
        if (abrir === undefined) {
            chatBox.classList.toggle('aberto');
        } else if (abrir) {
            chatBox.classList.add('aberto');
        } else {
            chatBox.classList.remove('aberto');
        }
        if (chatBox.classList.contains('aberto')) {
            initAudio(); // Garante áudio ao abrir
            conversaAtiva = null;
            mostrarPlaceholder();
            chatInputArea.style.display = 'none';
            carregarConversas();
            atualizarBadgeGlobal();
            fetch('ajax_chat.php?action=atualizar_atividade');
            if (intervaloTimer) clearInterval(intervaloTimer);
            intervaloTimer = setInterval(function() {
                if (chatBox.classList.contains('aberto')) {
                    carregarConversas(true);
                    if (conversaAtiva) {
                        carregarMensagens(conversaAtiva.tipo, conversaAtiva.id, true);
                    }
                    fetch('ajax_chat.php?action=atualizar_atividade');
                }
            }, 8000);
        } else {
            if (intervaloTimer) clearInterval(intervaloTimer);
        }
    }

    btnAbrir.addEventListener('click', function(e) {
        e.stopPropagation();
        initAudio();
        toggleChat();
    });
    fechar.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleChat(false);
    });

    document.addEventListener('click', function(e) {
        const widget = document.getElementById('chatWidget');
        if (chatBox.classList.contains('aberto') && !widget.contains(e.target)) {
            toggleChat(false);
        }
    });

    // ============================================================
    // PLACEHOLDER
    // ============================================================
    function mostrarPlaceholder() {
        mensagensArea.innerHTML = `
            <div class="mensagem-placeholder" id="placeholderMsg">
                <i class="bi bi-chat-dots" style="font-size: 2rem; margin-right: 8px;"></i>
                Selecione uma conversa
            </div>
        `;
        chatInputArea.style.display = 'none';
    }

    // ============================================================
    // CONVERSAS
    // ============================================================
    function carregarConversas(silencioso = false, termoPesquisa = '') {
        let url = 'ajax_chat.php?action=listar_conversas';
        if (termoPesquisa && termoPesquisa.length >= 2) {
            url = 'ajax_chat.php?action=pesquisar_usuarios&termo=' + encodeURIComponent(termoPesquisa);
        }
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (!Array.isArray(data)) return;
                renderizarLista(data);
            })
            .catch(err => console.error('Erro ao carregar conversas:', err));
    }

    function renderizarLista(data) {
        let html = '';
        if (data.length === 0) {
            html = '<div class="text-muted text-center" style="padding:20px;">Nenhum contato encontrado</div>';
        } else {
            data.forEach(conv => {
                const isAtivo = conversaAtiva && (conv.tipo === conversaAtiva.tipo && conv.id == conversaAtiva.id);
                const nome = conv.nome || 'Desconhecido';
                const iniciais = nome.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2);
                const online = conv.is_online ? 'online' : 'offline';
                const badge = conv.nao_lidas > 0 ? `<span class="badge-contato">${conv.nao_lidas}</span>` : '';
                const ultima = conv.ultima_mensagem ? 
                    conv.ultima_mensagem.substring(0, 30) + (conv.ultima_mensagem.length > 30 ? '...' : '') : 
                    'Nova conversa';
                html += `<div class="conversa ${isAtivo ? 'ativo' : ''}" 
                          data-tipo="${conv.tipo}" 
                          data-id="${conv.id}" 
                          data-nome="${nome}">
                            <div class="avatar">
                                ${iniciais}
                                <span class="online-indicator ${online}"></span>
                            </div>
                            <div class="info">
                                <div class="nome">${nome}</div>
                                <div class="ultima-msg">${ultima}</div>
                            </div>
                            ${badge}
                        </div>`;
            });
        }
        lista.innerHTML = html;

        lista.querySelectorAll('.conversa').forEach(el => {
            el.addEventListener('click', function() {
                lista.querySelectorAll('.conversa').forEach(c => c.classList.remove('ativo'));
                this.classList.add('ativo');
                const tipo = this.dataset.tipo;
                const id = this.dataset.id;
                const nome = this.dataset.nome;
                conversaAtiva = { tipo, id, nome };
                chatInputArea.style.display = 'flex';
                const placeholder = mensagensArea.querySelector('.mensagem-placeholder');
                if (placeholder) placeholder.remove();
                carregarMensagens(tipo, id);
                // 🔥 Foco automático no campo de input
                inputMsg.focus();
                const badge = this.querySelector('.badge-contato');
                if (badge) badge.remove();
            });
        });
    }

    // ============================================================
    // PESQUISA
    // ============================================================
    let timeoutPesquisa = null;
    pesquisaInput.addEventListener('input', function() {
        clearTimeout(timeoutPesquisa);
        const termo = this.value.trim();
        timeoutPesquisa = setTimeout(() => {
            if (!conversaAtiva) {
                mostrarPlaceholder();
                chatInputArea.style.display = 'none';
            }
            carregarConversas(false, termo);
        }, 300);
    });

    // ============================================================
    // CARREGAR MENSAGENS
    // ============================================================
    function carregarMensagens(tipo, id, silencioso = false) {
        if (carregando) return;
        carregando = true;
        let params = `action=carregar_mensagens&limit=50&offset=0`;
        if (tipo === 'todos') params += `&destinatario_id=todos`;
        else if (tipo === 'usuario') params += `&destinatario_id=${id}`;
        else if (tipo === 'equipe') params += `&equipe_id=${id}`;
        
        fetch(`ajax_chat.php?${params}`)
            .then(res => res.json())
            .then(data => {
                const placeholder = mensagensArea.querySelector('.mensagem-placeholder');
                if (placeholder) placeholder.remove();
                mensagensArea.innerHTML = '';
                if (data.error) {
                    mensagensArea.innerHTML = `<div class="text-danger">${data.error}</div>`;
                    carregando = false;
                    return;
                }
                if (!data || data.length === 0) {
                    mensagensArea.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Nenhuma mensagem ainda</div>';
                    carregando = false;
                    return;
                }
                data.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = 'mensagem-item' + (msg.remetente_id == usuarioId ? ' minha' : '');
                    const remetente = msg.remetente_nome || 'Usuário';
                    
                    let dataHora = 'Data inválida';
                    try {
                        const dataStr = msg.data_envio.replace(' ', 'T');
                        const dataObj = new Date(dataStr);
                        if (!isNaN(dataObj.getTime())) {
                            const dia = String(dataObj.getDate()).padStart(2, '0');
                            const mes = String(dataObj.getMonth() + 1).padStart(2, '0');
                            const ano = dataObj.getFullYear();
                            const horas = String(dataObj.getHours()).padStart(2, '0');
                            const minutos = String(dataObj.getMinutes()).padStart(2, '0');
                            dataHora = `${dia}/${mes}/${ano} ${horas}:${minutos}`;
                        } else {
                            dataHora = msg.data_envio || 'Data inválida';
                        }
                    } catch (e) {
                        dataHora = msg.data_envio || 'Data inválida';
                    }
                    
                    div.innerHTML = `
                        <div class="remetente">${remetente}</div>
                        ${msg.mensagem}
                        <div class="hora">${dataHora}</div>
                    `;
                    mensagensArea.appendChild(div);
                });
                mensagensArea.scrollTop = mensagensArea.scrollHeight;
                carregando = false;
                atualizarBadgeGlobal();
                if (data.length > 0) {
                    ultimoIdMensagem = data[data.length - 1].id;
                }
            })
            .catch(err => {
                console.error('Erro ao carregar mensagens:', err);
                carregando = false;
            });
    }

    // ============================================================
    // ENVIAR MENSAGEM
    // ============================================================
    function enviarMensagem() {
        if (!conversaAtiva) return;
        const mensagem = inputMsg.value.trim();
        if (!mensagem) return;
        const { tipo, id } = conversaAtiva;
        let destinatario_id = null, equipe_id = null;
        if (tipo === 'todos') destinatario_id = 'todos';
        else if (tipo === 'usuario') destinatario_id = id;
        else if (tipo === 'equipe') equipe_id = id;

        const formData = new FormData();
        formData.append('action', 'enviar');
        formData.append('mensagem', mensagem);
        if (destinatario_id !== null) formData.append('destinatario_id', destinatario_id);
        if (equipe_id !== null) formData.append('equipe_id', equipe_id);

        fetch('ajax_chat.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    inputMsg.value = '';
                    carregarMensagens(tipo, id);
                    carregarConversas(true);
                } else {
                    alert('Erro: ' + (data.error || 'Falha ao enviar'));
                }
            })
            .catch(err => alert('Erro de comunicação: ' + err));
    }

    btnEnviar.addEventListener('click', enviarMensagem);
    inputMsg.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') enviarMensagem();
    });

    // ============================================================
    // BADGE GLOBAL
    // ============================================================
    function atualizarBadgeGlobal() {
        fetch('ajax_chat.php?action=contar_nao_lidas')
            .then(res => res.json())
            .then(data => {
                const total = data.total || 0;
                if (total > 0) {
                    chatBadge.textContent = total > 99 ? '99+' : total;
                    chatBadge.style.display = 'block';
                } else {
                    chatBadge.style.display = 'none';
                }
            })
            .catch(err => console.error('Erro ao contar não lidas:', err));
    }

    // ============================================================
    // VERIFICAR NOVAS MENSAGENS (com som mesmo em segundo plano)
    // ============================================================
    function verificarNovasMensagens() {
        // Mesmo que a aba esteja em segundo plano, a requisição é feita
        fetch('ajax_chat.php?action=verificar_novas&ultimo_id=' + ultimoIdMensagem)
            .then(res => res.json())
            .then(data => {
                if (data.length > 0) {
                    ultimoIdMensagem = data[data.length - 1].id;
                    atualizarBadgeGlobal();
                    // Toca o som ICQ para cada nova mensagem (mesmo em segundo plano)
                    data.forEach(msg => {
                        tocarSom();
                        mostrarNotificacao(msg.remetente_nome);
                    });
                    // Se o chat estiver aberto, recarrega as mensagens
                    if (chatBox.classList.contains('aberto') && conversaAtiva) {
                        carregarMensagens(conversaAtiva.tipo, conversaAtiva.id, true);
                    }
                }
            })
            .catch(err => console.error('Erro ao verificar novas:', err));
    }

    function mostrarNotificacao(nome) {
        document.querySelectorAll('.chat-toast').forEach(el => el.remove());
        const toast = document.createElement('div');
        toast.className = 'chat-toast';
        toast.textContent = nome + ' enviou uma mensagem';
        document.body.appendChild(toast);
        toast.addEventListener('click', function() {
            window.abrirChat && window.abrirChat();
            this.remove();
        });
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 500);
        }, 5000);
    }

    // ============================================================
    // INICIALIZAÇÃO
    // ============================================================
    // Verifica novas mensagens a cada 5 segundos (mais frequente)
    setInterval(verificarNovasMensagens, 5000);
    setInterval(atualizarBadgeGlobal, 15000);
    atualizarBadgeGlobal();

    chatBox.classList.remove('aberto');
    mostrarPlaceholder();

    window.abrirChat = function() {
        if (!chatBox.classList.contains('aberto')) {
            toggleChat(true);
        }
    };
});
</script>
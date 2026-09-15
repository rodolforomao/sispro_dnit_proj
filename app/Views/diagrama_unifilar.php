<?php
// diagrama_unifilar.php
$usuario_nome = $usuario_nome ?? $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
$setor_slug = $_SESSION['setor_slug'] ?? 'atlas-monitoramento';
$setor_nome = 'Atlas/Monitoramento';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagrama Unifilar - SISPRO</title>
    <!-- Bootstrap (necessário para o header) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Estilos originais do diagrama (com .container-diagrama) -->
    <style>
        /* ===== ESTILOS GERAIS ===== */
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f4f6f8 0%, #e8edf2 100%);
            margin: 0;
            padding-top: 70px; /* espaço para o header fixo */
            min-height: 100vh;
        }
        .container-diagrama {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            max-width: 1100px;
            margin: 20px auto;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border: 1px solid rgba(0, 63, 127, 0.1);
        }
        h2 { color: #003f7f; font-size: 22px; margin-top: 0; position: relative; }
        h2::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #003f7f, #4da1ff);
            margin-top: 8px;
            border-radius: 3px;
        }
        .info-box {
            background: linear-gradient(135deg, #e8f4fd 0%, #d4e9fa 100%);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 25px;
            border-left: 5px solid #003f7f;
            box-shadow: 0 3px 10px rgba(0, 63, 127, 0.08);
        }
        .info-box p {
            margin: 0;
            font-size: 14.5px;
            color: #003f7f;
            line-height: 1.5;
        }
        .modo-selector {
            margin: 20px 0;
            padding: 15px 20px;
            background: #f0f7ff;
            border-radius: 8px;
            border: 2px solid #003f7f;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 25px;
        }
        .modo-selector label {
            font-weight: bold;
            color: #003f7f;
            margin-right: 5px;
        }
        .modo-selector .opcoes {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .modo-selector .opcoes label {
            font-weight: normal;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
        }
        .modo-selector .opcoes input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #003f7f;
            cursor: pointer;
        }
        .campos-oae-unica {
            display: none;
            margin: 15px 0 20px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #003f7f;
        }
        .campos-oae-unica h4 {
            margin-top: 0;
            color: #003f7f;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, max-content));
            gap: 20px;
            margin-bottom: 20px;
            align-items: end;
        }
        .field { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 14px; font-weight: 600; color: #2c3e50; }
        input[type="number"], input[type="text"] {
            padding: 10px 12px;
            width: 110px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s;
            background: #fafafa;
        }
        input[type="text"] { width: 200px; }
        input[type="number"]:focus, input[type="text"]:focus {
            outline: none;
            border-color: #003f7f;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 63, 127, 0.15);
        }
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #003f7f;
        }
        .oaes {
            margin-top: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, max-content));
            gap: 15px;
        }
        .oaes .field {
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f0ff 100%);
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e1ecff;
            transition: transform 0.2s;
        }
        .oaes .field:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .options-section {
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #f9fafb 0%, #f0f2f5 100%);
            border-radius: 10px;
            border: 1px solid #e5e9f0;
        }
        .option-group {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #d1d9e6;
        }
        .option-group:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .option-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .option-header:hover {
            background: rgba(0, 63, 127, 0.05);
        }
        .option-header label {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            cursor: pointer;
        }
        .option-details {
            margin-left: 30px;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            box-shadow: 0 3px 8px rgba(0,0,0,0.04);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .controls-section {
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 15px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }
        .zoom-controls button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .zoom-controls button:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        #zoomLevel {
            font-weight: 600;
            color: #003f7f;
            min-width: 60px;
            text-align: center;
        }
        .buttons {
            margin-top: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 20px;
            border-top: 2px solid #f0f2f5;
        }
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 140px;
        }
        button:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        button:active {
            transform: translateY(0) scale(0.98);
        }
        .btn-main {
            background: linear-gradient(135deg, #003f7f 0%, #0056b3 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 63, 127, 0.3);
        }
        .btn-main:hover { background: linear-gradient(135deg, #002d5c 0%, #004085 100%); }
        .btn-sec {
            background: linear-gradient(135deg, #475569 0%, #64748b 100%);
            color: white;
        }
        .btn-sec:hover { background: linear-gradient(135deg, #334155 0%, #475569 100%); }
        .btn-copy {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
        }
        .btn-copy:hover { background: linear-gradient(135deg, #047857 0%, #059669 100%); }
        .canvas-container {
            margin-top: 25px;
            overflow-x: auto;
            background: #fff;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            position: relative;
            min-height: 400px;
        }
        canvas {
            display: block;
            margin: 0 auto;
            transition: all 0.3s ease;
        }
        .error-message {
            background: linear-gradient(135deg, #fee 0%, #fdd 100%);
            color: #c00;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 5px solid #c00;
            animation: fadeIn 0.3s ease;
            font-weight: 500;
        }
        .success-message {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 5px solid #059669;
            animation: fadeIn 0.3s ease;
            font-weight: 500;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tooltip {
            position: relative;
            display: inline-block;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #2c3e50;
            color: #fff;
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            font-weight: normal;
        }
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        .legend-container {
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .legend-color {
            width: 20px;
            height: 10px;
            border-radius: 2px;
        }
        .legend-label {
            font-size: 12px;
            color: #2c3e50;
        }
        .opcao-duas-cores {
            margin: 10px 0 20px 0;
            padding: 12px 15px;
            background: #f0f7ff;
            border-radius: 8px;
            border: 1px solid #cce5ff;
            display: none;
            align-items: center;
            gap: 15px;
        }
        .opcao-duas-cores label {
            font-weight: 600;
            color: #003f7f;
            cursor: pointer;
        }
        .opcao-duas-cores input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #003f7f;
        }
        @media (max-width: 768px) {
            .container-diagrama { padding: 15px; margin: 10px; }
            .grid, .oaes { grid-template-columns: 1fr; gap: 15px; }
            .field input[type="number"], .field input[type="text"] { width: 100%; }
            .buttons { flex-direction: column; }
            button { width: 100%; min-width: unset; }
            .zoom-controls { flex-wrap: wrap; justify-content: center; }
            .modo-selector { flex-direction: column; align-items: stretch; }
            .modo-selector .opcoes { flex-direction: column; gap: 10px; }
            .campos-oae-unica .grid { grid-template-columns: 1fr; }
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px 0 30px 0;
            color: #003f7f;
            border-top: 2px solid #f0f2f5;
        }
        .footer img {
            max-width: 100px;
            height: auto;
            display: block;
            margin: 0 auto 15px;
        }
        .footer div { margin: 5px 0; }
        .version {
            background: #003f7f;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/Views/header_atlas.php'; ?>

    <div class="container-diagrama">
        <!-- LOGO -->
        <div style="margin-bottom:20px; text-align:center;">
            <img 
                src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-simples.png"
                alt="Logo DNIT"
                style="max-width:250px; height:auto;">
        </div>

        <h2>Diagrama Unifilar Rodoviário - DNIT/DIR/CGCONT/COAC</h2>

        <div class="info-box">
            <p><strong>📋 Instruções:</strong> Selecione o modo de representação abaixo. Preencha os KMs e as OAEs conforme o modo escolhido. O diagrama é gerado automaticamente.</p>
            <ul style="margin:8px 0 0 0; font-size:13px; color:#003f7f; list-style:none; padding:0;">
                <li>✅ <strong>Trecho com OAEs:</strong> pista + múltiplas OAEs</li>
                <li>✅ <strong>OAE Única:</strong> pista com uma única OAE nomeada</li>
                <li>✅ <strong>OAEs sem pista:</strong> pista mais clara + OAEs</li>
            </ul>
        </div>

        <!-- ===== SELETOR DE MODO ===== -->
        <div class="modo-selector">
            <label>📌 Modo de representação:</label>
            <div class="opcoes">
                <label>
                    <input type="radio" name="modoDiagrama" value="trecho_oaes" checked onchange="alterarModo()">
                    Trecho com OAEs
                </label>
                <label>
                    <input type="radio" name="modoDiagrama" value="oae_unica" onchange="alterarModo()">
                    OAE Única
                </label>
                <label>
                    <input type="radio" name="modoDiagrama" value="oaes_sem_pista" onchange="alterarModo()">
                    OAEs sem pista
                </label>
            </div>
        </div>

        <!-- ===== CAMPOS PARA OAE ÚNICA ===== -->
        <div id="camposOAEUnica" class="campos-oae-unica">
            <h4>🏗️ Dados da OAE Única</h4>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="field">
                    <label>Nome da OAE</label>
                    <input type="text" id="nomeOAE" placeholder="Ex: Ponte Rio do Peixe" style="width:100%;" oninput="debounceGerarDiagrama()">
                </div>
                <div class="field">
                    <label>KM Inicial da OAE</label>
                    <input type="number" step="0.001" id="kmIniOAE" placeholder="0.000" oninput="debounceGerarDiagrama()">
                </div>
                <div class="field">
                    <label>KM Final da OAE</label>
                    <input type="number" step="0.001" id="kmFimOAE" placeholder="0.000" oninput="debounceGerarDiagrama()">
                </div>
                <div class="field" style="justify-content: flex-end;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 6px;">
                        <input type="checkbox" id="chkAcessos" checked onchange="debounceGerarDiagrama()">
                        <label for="chkAcessos" style="font-weight: normal; font-size: 14px; cursor: pointer;">Contrato contempla acessos</label>
                    </div>
                </div>
            </div>
            <div style="font-size:13px; color:#666; margin-top:5px;">
                ⚠️ A extensão do trecho é definida pelo KM Inicial e Final da OAE.
            </div>
        </div>

        <!-- ===== GRID PRINCIPAL ===== -->
        <div id="gridPrincipal" class="grid">
            <div class="field">
                <label class="tooltip">KM Inicial
                    <span class="tooltiptext">Quilômetro inicial do trecho</span>
                </label>
                <input type="number" step="0.001" id="kmIni" placeholder="0.0" required />
            </div>
            <div class="field">
                <label class="tooltip">KM Final
                    <span class="tooltiptext">Quilômetro final do trecho</span>
                </label>
                <input type="number" step="0.001" id="kmFim" placeholder="0.0" required />
            </div>
            <div class="field" id="campoQtdOae">
                <label class="tooltip">Qtd. OAEs
                    <span class="tooltiptext">Quantidade de Obras de Arte Especiais (máx. 20)</span>
                </label>
                <input type="number" min="0" max="20" id="qtdOae" value="0" />
            </div>
        </div>

        <!-- ===== OAEs Dinâmicas ===== -->
        <div class="oaes" id="oaes"></div>

        <!-- ===== OPÇÃO DUAS CORES ===== -->
        <div id="opcaoDuasCores" class="opcao-duas-cores">
            <input type="checkbox" id="chkDuasCores">
            <label for="chkDuasCores">🎨 Duas cores na OAE (uma em cada lado da pista)</label>
            <span style="font-size:12px; color:#666; margin-left:auto;">(superior: azul, inferior: laranja)</span>
        </div>

        <!-- ===== SEÇÃO DE OPÇÕES ===== -->
        <div class="options-section" id="opcoesTrecho">
            <div class="option-group">
                <div class="option-header" onclick="toggleDup()">
                    <input type="checkbox" id="chkDup">
                    <label for="chkDup">🛣️ Duplicação (Trecho Completo)</label>
                </div>
                <div id="optsDup" class="option-details" style="display:none">
                    <p style="margin:0; color:#666; font-size:14px;">A duplicação será aplicada em todo o trecho.</p>
                </div>
            </div>
        </div>

        <!-- ===== CONTROLES DE ZOOM ===== -->
        <div class="controls-section">
            <div class="zoom-controls">
                <span>🔍 Zoom:</span>
                <button onclick="zoomOut()">-</button>
                <span id="zoomLevel">100%</span>
                <button onclick="zoomIn()">+</button>
                <button onclick="resetZoom()">Resetar</button>
            </div>
        </div>

        <!-- ===== BOTÕES ===== -->
        <div class="buttons">
            <button class="btn-copy" onclick="copiarImagem()">📋 Copiar Imagem</button>
            <button class="btn-main" onclick="salvarConfig()">💾 Salvar Config.</button>
            <button class="btn-sec" onclick="carregarConfig()">📂 Carregar Config.</button>
            <button class="btn-sec" onclick="limpar()">🗑️ Limpar Tudo</button>
        </div>

        <!-- ===== CANVAS ===== -->
        <div class="canvas-container">
            <canvas id="canvas" width="1000" height="350"></canvas>
        </div>

        <!-- ===== LEGENDA ===== -->
        <div class="legend-container" id="legendContainer"></div>
    </div>

    <!-- ===== RODAPÉ ===== -->
    <div class="footer">
        <img 
            src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-simples.png"
            alt="Logo DNIT"
            style="max-width:100px;">
        <div style="font-size:14px; opacity:0.9;">Desenvolvido por:</div>
        <div style="font-size:18px; font-weight:bold;">Bruno Pimenta Resende</div>
        <div style="font-size:14px; opacity:0.9;">Engenheiro de Projetos</div>
        <div style="font-size:12px; opacity:0.85; margin-top:4px;">DNIT / DIR / CGCONT / COAC / Projetos</div>
        <div class="version">Versão 1.0 - Atlas - Data 30/06/2026</div>
    </div>

    <!-- ===== SCRIPTS ===== -->
    <!-- Bootstrap JS (para o header) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jspdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <!-- Código JavaScript do diagrama (original) - AGORA COM CONSOLE LOG PARA DEPURAÇÃO -->
    <script>
        // ============================
        // VARIÁVEIS GLOBAIS
        // ============================
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');
        const legendContainer = document.getElementById('legendContainer');
        const qtdOaeInput = document.getElementById('qtdOae');
        const oaesDiv = document.getElementById('oaes');

        console.log('Diagrama inicializado com sucesso!'); // Depuração

        let modoDuplicacao = false;
        let dupYTop = 0, dupYBottom = 0;
        let zoom = 1;
        const originalWidth = 1000, originalHeight = 350;
        let timeoutId;
        let modoAtual = 'trecho_oaes';
        let offsetLabels = {};

        // ============================
        // FUNÇÃO PARA ALTERAR MODO
        // ============================
        function alterarModo() {
            console.log('alterarModo() chamado'); // Depuração
            const radios = document.querySelectorAll('input[name="modoDiagrama"]');
            for (let radio of radios) {
                if (radio.checked) {
                    modoAtual = radio.value;
                    break;
                }
            }
            
            const camposOAEUnica = document.getElementById('camposOAEUnica');
            const campoQtdOae = document.getElementById('campoQtdOae');
            const oaesDiv = document.getElementById('oaes');
            const opcaoDuasCores = document.getElementById('opcaoDuasCores');
            const gridPrincipal = document.getElementById('gridPrincipal');
            const opcoesTrecho = document.getElementById('opcoesTrecho');
            
            camposOAEUnica.style.display = 'none';
            campoQtdOae.style.display = '';
            oaesDiv.style.display = '';
            gridPrincipal.style.display = '';
            opcaoDuasCores.style.display = 'none';
            opcoesTrecho.style.display = '';
            
            if (modoAtual === 'oae_unica') {
                camposOAEUnica.style.display = 'block';
                campoQtdOae.style.display = 'none';
                oaesDiv.style.display = 'none';
                gridPrincipal.style.display = 'none';
                document.getElementById('chkDup').checked = false;
                document.getElementById('optsDup').style.display = 'none';
                opcaoDuasCores.style.display = 'none';
                document.getElementById('chkDuasCores').checked = false;
                opcoesTrecho.style.display = 'none';
            } else if (modoAtual === 'oaes_sem_pista') {
                campoQtdOae.style.display = '';
                oaesDiv.style.display = '';
                gridPrincipal.style.display = '';
                opcaoDuasCores.style.display = 'none';
                document.getElementById('chkDup').checked = false;
                document.getElementById('optsDup').style.display = 'none';
                document.getElementById('chkDuasCores').checked = false;
                opcoesTrecho.style.display = 'none';
            } else {
                campoQtdOae.style.display = '';
                oaesDiv.style.display = '';
                gridPrincipal.style.display = '';
                opcoesTrecho.style.display = '';
                if (document.getElementById('chkDup').checked) {
                    opcaoDuasCores.style.display = 'flex';
                }
            }
            
            debounceGerarDiagrama();
        }

        // ============================
        // VALIDAÇÃO
        // ============================
        function validarDados() {
            const errors = [];
            let kmIni, kmFim;
            
            if (modoAtual === 'oae_unica') {
                kmIni = Number(document.getElementById('kmIniOAE').value);
                kmFim = Number(document.getElementById('kmFimOAE').value);
                if (isNaN(kmIni) || kmIni < 0) errors.push("KM Inicial da OAE inválido");
                if (isNaN(kmFim) || kmFim <= kmIni) errors.push("KM Final da OAE deve ser maior que o Inicial");
                return errors;
            }
            
            kmIni = Number(document.getElementById('kmIni').value);
            kmFim = Number(document.getElementById('kmFim').value);
            if (isNaN(kmIni) || kmIni < 0) errors.push("KM Inicial inválido");
            if (isNaN(kmFim) || kmFim <= kmIni) errors.push("KM Final deve ser maior que KM Inicial");
            
            const qtdOae = Number(qtdOaeInput.value);
            for (let i = 1; i <= qtdOae; i++) {
                const oaeVal = document.getElementById(`oae${i}`).value;
                if (oaeVal && (Number(oaeVal) < kmIni || Number(oaeVal) > kmFim)) {
                    errors.push(`OAE ${i} fora do intervalo KM`);
                }
            }
            return errors;
        }

        function showMessage(type, message, autoHide = true) {
            const existing = document.querySelector('.error-message, .success-message');
            if (existing) existing.remove();
            if (type === 'error') {
                const div = document.createElement('div');
                div.className = 'error-message';
                div.innerHTML = `⚠️ ${message}`;
                document.querySelector('.container-diagrama').insertBefore(div, document.querySelector('.modo-selector'));
                if (autoHide) setTimeout(() => div.remove(), 5000);
            } else if (type === 'success') {
                const div = document.createElement('div');
                div.className = 'success-message';
                div.innerHTML = `✅ ${message}`;
                document.querySelector('.container-diagrama').insertBefore(div, document.querySelector('.modo-selector'));
                if (autoHide) setTimeout(() => div.remove(), 3000);
            }
        }

        // ============================
        // FUNÇÕES DE INTERFACE
        // ============================
        function toggleDup() {
            console.log('toggleDup() chamado'); // Depuração
            const checkbox = document.getElementById('chkDup');
            const details = document.getElementById('optsDup');
            details.style.display = checkbox.checked ? 'block' : 'none';
            const opcaoDuasCores = document.getElementById('opcaoDuasCores');
            if (checkbox.checked && (modoAtual === 'trecho_oaes')) {
                opcaoDuasCores.style.display = 'flex';
            } else {
                opcaoDuasCores.style.display = 'none';
                document.getElementById('chkDuasCores').checked = false;
            }
            if (checkbox.checked) debounceGerarDiagrama();
        }

        // ============================
        // GERENCIAMENTO DE OAEs
        // ============================
        qtdOaeInput.addEventListener('change', () => {
            oaesDiv.innerHTML = '';
            const qtd = Number(qtdOaeInput.value);
            for (let i = 1; i <= qtd; i++) {
                const div = document.createElement('div');
                div.className = 'field';
                div.innerHTML = `
                    <label class="tooltip">OAE ${i} (KM)
                        <span class="tooltiptext">Posição em quilômetros da OAE ${i}</span>
                    </label>
                    <input type="number" step="0.001" id="oae${i}" placeholder="0.0" 
                           oninput="debounceGerarDiagrama()" />
                `;
                oaesDiv.appendChild(div);
            }
            debounceGerarDiagrama();
        });

        // ============================
        // FUNÇÕES DE DESENHO AUXILIARES
        // ============================
        function desenharLinhaTracejada(ctx, x1, y1, x2, y2, cor, largura) {
            ctx.strokeStyle = cor;
            ctx.lineWidth = largura;
            ctx.setLineDash([20, 12]);
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
            ctx.setLineDash([]);
        }

        function desenharLinhaContinua(ctx, x1, y1, x2, y2, cor, largura) {
            ctx.strokeStyle = cor;
            ctx.lineWidth = largura;
            ctx.setLineDash([]);
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
        }

        function kmToX(km, kmIni, kmFim, margem, largura) {
            return margem + ((km - kmIni) / (kmFim - kmIni)) * largura;
        }

        function desenharTracoOAE(ctx, x, yTop, yBottom, modoDuplicacao, duasCores, corPersonalizada = null) {
            const larguraTraco = 6;
            const corPadrao = corPersonalizada || '#003f7f';
            const corLaranja = '#f59e0b';
            const extensao = 15;
            const yTopExt = yTop - extensao;
            const yBottomExt = yBottom + extensao;
            
            if (!modoDuplicacao || !duasCores) {
                ctx.strokeStyle = corPadrao;
                ctx.lineWidth = larguraTraco;
                ctx.beginPath();
                ctx.moveTo(x, yTopExt);
                ctx.lineTo(x, yBottomExt);
                ctx.stroke();
            } else {
                const yMeio = (yTop + yBottom) / 2;
                ctx.strokeStyle = corPadrao;
                ctx.lineWidth = larguraTraco;
                ctx.beginPath();
                ctx.moveTo(x, yTopExt);
                ctx.lineTo(x, yMeio);
                ctx.stroke();
                ctx.strokeStyle = corLaranja;
                ctx.lineWidth = larguraTraco;
                ctx.beginPath();
                ctx.moveTo(x, yMeio);
                ctx.lineTo(x, yBottomExt);
                ctx.stroke();
            }
        }

        function desenharPista(ctx, margem, largura, kmIni, kmFim, yBase, alturaFaixa, duplicacao, alpha = 0.7) {
            const corPista = `rgba(80, 80, 80, ${alpha})`;
            const corCanteiro = `rgba(34, 139, 34, ${alpha * 0.6})`;
            const corLinha = `rgba(255, 255, 255, ${alpha * 0.7})`;
            const corTracejada = `rgba(255, 255, 255, ${alpha * 0.4})`;
            const corAmarela = `rgba(255, 215, 0, ${alpha * 0.7})`;
            
            if (duplicacao) {
                const espacoCanteiro = 22;
                const yPista2 = yBase + (alturaFaixa * 2) + espacoCanteiro;
                
                ctx.fillStyle = corPista;
                ctx.fillRect(margem, yBase, largura, alturaFaixa * 2);
                ctx.fillStyle = corCanteiro;
                ctx.fillRect(margem, yBase + alturaFaixa * 2, largura, espacoCanteiro);
                ctx.fillStyle = corPista;
                ctx.fillRect(margem, yPista2, largura, alturaFaixa * 2);
                
                desenharLinhaContinua(ctx, margem, yBase, margem + largura, yBase, corLinha, 2);
                desenharLinhaContinua(ctx, margem, yBase + alturaFaixa * 2, margem + largura, yBase + alturaFaixa * 2, corLinha, 2);
                desenharLinhaContinua(ctx, margem, yPista2, margem + largura, yPista2, corLinha, 2);
                desenharLinhaContinua(ctx, margem, yPista2 + alturaFaixa * 2, margem + largura, yPista2 + alturaFaixa * 2, corLinha, 2);
                desenharLinhaTracejada(ctx, margem, yBase + alturaFaixa, margem + largura, yBase + alturaFaixa, corTracejada, 2);
                desenharLinhaTracejada(ctx, margem, yPista2 + alturaFaixa, margem + largura, yPista2 + alturaFaixa, corTracejada, 2);
                
                modoDuplicacao = true;
                dupYTop = yBase;
                dupYBottom = yPista2 + alturaFaixa * 2;
                desenharGrade(ctx, margem, largura, kmIni, kmFim, dupYTop, dupYBottom - dupYTop, alpha);
                
            } else {
                const alturaTotal = alturaFaixa * 2;
                ctx.fillStyle = corPista;
                ctx.fillRect(margem, yBase, largura, alturaTotal);
                
                desenharGrade(ctx, margem, largura, kmIni, kmFim, yBase, alturaTotal, alpha);
                
                desenharLinhaContinua(ctx, margem, yBase, margem + largura, yBase, corLinha, 2);
                desenharLinhaContinua(ctx, margem, yBase + alturaTotal, margem + largura, yBase + alturaTotal, corLinha, 2);
                desenharLinhaTracejada(ctx, margem, yBase + alturaFaixa, margem + largura, yBase + alturaFaixa, corAmarela, 3);
                
                modoDuplicacao = false;
                dupYTop = 0;
                dupYBottom = 0;
            }
        }

        function desenharGrade(ctx, margem, largura, kmIni, kmFim, yBase, alturaTotal, alpha = 0.7) {
            ctx.save();
            ctx.strokeStyle = `rgba(0, 63, 127, ${alpha * 0.2})`;
            ctx.lineWidth = 1;
            ctx.setLineDash([3, 5]);
            const extensaoTotal = kmFim - kmIni;
            const intervaloKm = 5;
            const numMarcacoes = Math.floor(extensaoTotal / intervaloKm);
            for (let i = 0; i <= numMarcacoes; i++) {
                const kmAtual = kmIni + (i * intervaloKm);
                if (kmAtual > kmFim) break;
                const x = margem + ((kmAtual - kmIni) / extensaoTotal) * largura;
                ctx.beginPath();
                ctx.moveTo(x, yBase - 20);
                ctx.lineTo(x, yBase + alturaTotal + 45);
                ctx.stroke();
                if (extensaoTotal >= 5) {
                    ctx.fillStyle = `rgba(0,0,0,${alpha * 0.4})`;
                    ctx.font = '11px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText(kmAtual.toFixed(1), x, yBase + alturaTotal + 60);
                }
            }
            ctx.setLineDash([]);
            ctx.restore();
        }

        // ============================
        // FUNÇÃO DE DESENHO PRINCIPAL
        // ============================
        function desenharDiagramaNoContexto(ctxDestino, mostrarInstrucao) {
            ctxDestino.clearRect(0, 0, canvas.width, canvas.height);
            
            try {
                let kmIni, kmFim;
                let margem = 70;
                let largura = canvas.width - margem * 2;
                const alturaFaixa = 32;
                let temDuplicacao = false;
                if (modoAtual === 'trecho_oaes') {
                    temDuplicacao = document.getElementById('chkDup').checked;
                }
                const duasCoresOAE = document.getElementById('chkDuasCores').checked;
                
                // ===== MODO OAE ÚNICA =====
                if (modoAtual === 'oae_unica') {
                    kmIni = Number(document.getElementById('kmIniOAE').value);
                    kmFim = Number(document.getElementById('kmFimOAE').value);
                    
                    if (!kmIni && !kmFim) {
                        ctxDestino.fillStyle = '#666';
                        ctxDestino.font = '18px Arial';
                        ctxDestino.textAlign = 'center';
                        ctxDestino.fillText('🏗️ Preencha KM Inicial e Final da OAE - Diagrama Gerado Automaticamente', canvas.width / 2, canvas.height / 2);
                        desenharLegenda(['oae_unica', false]);
                        return;
                    }
                    if (isNaN(kmIni) || isNaN(kmFim) || kmFim <= kmIni) {
                        ctxDestino.fillStyle = '#c00';
                        ctxDestino.font = '16px Arial';
                        ctxDestino.textAlign = 'center';
                        ctxDestino.fillText('⚠️ KM Final deve ser maior que KM Inicial', canvas.width / 2, canvas.height / 2);
                        desenharLegenda(['oae_unica', false]);
                        return;
                    }
                    
                    const yBase = 140;
                    desenharPista(ctxDestino, margem, largura, kmIni, kmFim, yBase, alturaFaixa, false);
                    
                    ctxDestino.fillStyle = '#003f7f';
                    ctxDestino.font = 'bold 14px Arial';
                    ctxDestino.textAlign = 'left';
                    ctxDestino.fillText(`KM ${kmIni.toFixed(3)}`, margem, yBase + 2*alturaFaixa + 30);
                    ctxDestino.textAlign = 'right';
                    ctxDestino.fillText(`KM ${kmFim.toFixed(3)}`, margem + largura, yBase + 2*alturaFaixa + 30);
                    
                    const nome = document.getElementById('nomeOAE').value.trim() || 'OAE Única';
                    ctxDestino.fillStyle = '#003f7f';
                    ctxDestino.font = 'bold 22px Arial';
                    ctxDestino.textAlign = 'center';
                    ctxDestino.fillText(nome, canvas.width / 2, 40);
                    
                    const temAcessos = document.getElementById('chkAcessos').checked;
                    const textoAcessos = temAcessos ? '✓ Contrato contempla acessos' : '✗ Contrato não contempla acessos';
                    ctxDestino.fillStyle = temAcessos ? '#059669' : '#dc2626';
                    ctxDestino.font = 'bold 14px Arial';
                    ctxDestino.fillText(textoAcessos, canvas.width / 2, 95);
                    
                    desenharLegenda(['oae_unica', temAcessos]);
                    return;
                }
                
                // ===== MODO OAEs SEM PISTA =====
                if (modoAtual === 'oaes_sem_pista') {
                    kmIni = Number(document.getElementById('kmIni').value);
                    kmFim = Number(document.getElementById('kmFim').value);
                    if (isNaN(kmIni) || isNaN(kmFim) || kmFim <= kmIni) {
                        ctxDestino.fillStyle = '#666';
                        ctxDestino.font = '18px Arial';
                        ctxDestino.textAlign = 'center';
                        ctxDestino.fillText('📏 Preencha KM Inicial e Final - Diagrama Gerado Automaticamente', canvas.width / 2, canvas.height / 2);
                        return;
                    }
                    const qtd = Number(qtdOaeInput.value);
                    const yBase = 140;
                    desenharPista(ctxDestino, margem, largura, kmIni, kmFim, yBase, alturaFaixa, false, 0.4);
                    
                    ctxDestino.fillStyle = '#003f7f';
                    ctxDestino.font = 'bold 18px Arial';
                    ctxDestino.textAlign = 'center';
                    ctxDestino.fillText('OAEs - Sem pista', canvas.width / 2, 40);
                    ctxDestino.font = '14px Arial';
                    ctxDestino.fillText(`Quantidade de OAEs cadastradas: ${qtd}`, canvas.width / 2, 70);
                    
                    if (mostrarInstrucao && qtd > 0) {
                        ctxDestino.fillStyle = 'rgba(0,0,0,0.5)';
                        ctxDestino.font = '12px Arial';
                        ctxDestino.textAlign = 'right';
                        ctxDestino.fillText('🖱️ Clique e arraste os nomes das OAEs', canvas.width - 20, 30);
                    }
                    
                    for (let i = 1; i <= qtd; i++) {
                        const valOae = document.getElementById(`oae${i}`).value;
                        if (valOae) {
                            const x = kmToX(Number(valOae), kmIni, kmFim, margem, largura);
                            const yTop = yBase - 20;
                            const yBottom = yBase + 2 * alturaFaixa + 20;
                            desenharTracoOAE(ctxDestino, x, yTop, yBottom, false, false, '#003f7f');
                            const key = `oae_sem_pista_${i}`;
                            const offset = offsetLabels[key] || {dx: 0, dy: 0};
                            const yTextoNome = yTop - 20 + offset.dy;
                            const xTexto = x + offset.dx;
                            ctxDestino.fillStyle = '#003f7f';
                            ctxDestino.font = 'bold 12px Arial';
                            ctxDestino.textAlign = 'center';
                            ctxDestino.fillText(`OAE ${i}`, xTexto, yTextoNome);
                            const yTextoKm = yBottom + 30 + offset.dy;
                            ctxDestino.fillStyle = '#003f7f';
                            ctxDestino.font = '11px Arial';
                            ctxDestino.fillText(`KM ${Number(valOae).toFixed(3)}`, xTexto, yTextoKm);
                        }
                    }
                    desenharLegenda(['oaes_sem_pista']);
                    return;
                }
                
                // ===== MODO TRECHO COM OAEs =====
                kmIni = Number(document.getElementById('kmIni').value);
                kmFim = Number(document.getElementById('kmFim').value);
                if (isNaN(kmIni) || isNaN(kmFim) || kmFim <= kmIni) {
                    ctxDestino.fillStyle = '#666';
                    ctxDestino.font = '18px Arial';
                    ctxDestino.textAlign = 'center';
                    ctxDestino.fillText('📏 Preencha KM Inicial e Final - Diagrama Gerado Automaticamente', canvas.width / 2, canvas.height / 2);
                    return;
                }
                
                const yBase = 140;
                desenharPista(ctxDestino, margem, largura, kmIni, kmFim, yBase, alturaFaixa, temDuplicacao);
                
                const qtd = Number(qtdOaeInput.value);
                if (mostrarInstrucao && qtd > 0) {
                    ctxDestino.fillStyle = 'rgba(0,0,0,0.5)';
                    ctxDestino.font = '12px Arial';
                    ctxDestino.textAlign = 'right';
                    ctxDestino.fillText('🖱️ Arraste os nomes das OAEs', canvas.width - 20, 30);
                }
                
                for (let i = 1; i <= qtd; i++) {
                    const valOae = document.getElementById(`oae${i}`).value;
                    if (valOae) {
                        const x = kmToX(Number(valOae), kmIni, kmFim, margem, largura);
                        let yTop, yBottom;
                        if (temDuplicacao) {
                            yTop = dupYTop;
                            yBottom = dupYBottom;
                        } else {
                            yTop = yBase;
                            yBottom = yBase + 2 * alturaFaixa;
                        }
                        desenharTracoOAE(ctxDestino, x, yTop, yBottom, temDuplicacao, duasCoresOAE, '#003f7f');
                        
                        const key = `oae_${i}`;
                        const offset = offsetLabels[key] || {dx: 0, dy: 0};
                        const yTextoNome = yTop - 20 + offset.dy;
                        const xTexto = x + offset.dx;
                        ctxDestino.fillStyle = '#003f7f';
                        ctxDestino.font = 'bold 12px Arial';
                        ctxDestino.textAlign = 'center';
                        ctxDestino.fillText(`OAE ${i}`, xTexto, yTextoNome);
                        const yTextoKm = yBottom + 30 + offset.dy;
                        ctxDestino.fillStyle = '#003f7f';
                        ctxDestino.font = '11px Arial';
                        ctxDestino.fillText(`KM ${Number(valOae).toFixed(3)}`, xTexto, yTextoKm);
                    }
                }
                
                desenharLegenda(['trecho_oaes']);
                
            } catch (error) {
                showMessage('error', `Erro ao gerar diagrama: ${error.message}`);
            }
        }

        // ============================
        // FUNÇÃO PRINCIPAL DE GERAÇÃO
        // ============================
        function gerarDiagrama() {
            console.log('gerarDiagrama() chamado'); // Depuração
            desenharDiagramaNoContexto(ctx, true);
        }

        // ============================
        // LEGENDA
        // ============================
        function desenharLegenda(modoInfo) {
            legendContainer.innerHTML = '';
            let items = [];
            
            if (modoInfo[0] === 'oaes_sem_pista') {
                items = [{color: '#003f7f', label: 'OAE (traço vertical)'}];
            } else if (modoInfo[0] === 'oae_unica') {
                const temAcessos = modoInfo[1] || false;
                items = [
                    {color: 'rgba(80,80,80,0.7)', label: 'Pista de Rolamento'},
                    {color: 'rgba(255,215,0,0.5)', label: 'Linha Central Amarela'},
                    {color: temAcessos ? '#059669' : '#dc2626', label: temAcessos ? 'Contrato com acessos' : 'Contrato sem acessos'}
                ];
            } else {
                items = [
                    {color: 'rgba(80,80,80,0.7)', label: 'Pista de Rolamento'},
                    {color: 'rgba(255,215,0,0.5)', label: 'Linha Central Amarela'},
                    {color: '#003f7f', label: 'OAE (traço azul)'}
                ];
                if (document.getElementById('chkDup').checked) {
                    items.push({color: 'rgba(34,139,34,0.4)', label: 'Canteiro Central'});
                    if (document.getElementById('chkDuasCores').checked) {
                        items.push({color: '#f59e0b', label: 'OAE (laranja - lado inferior)'});
                    }
                }
            }
            
            items.forEach(item => {
                const div = document.createElement('div');
                div.className = 'legend-item';
                let style = `background-color:${item.color};`;
                div.innerHTML = `<div class="legend-color" style="${style}"></div><div class="legend-label">${item.label}</div>`;
                legendContainer.appendChild(div);
            });
        }

        // ============================
        // ZOOM
        // ============================
        function zoomIn() { if (zoom < 2) { zoom += 0.25; aplicarZoom(); } }
        function zoomOut() { if (zoom > 0.5) { zoom -= 0.25; aplicarZoom(); } }
        function resetZoom() { zoom = 1; aplicarZoom(); }
        function aplicarZoom() {
            canvas.style.width = `${originalWidth * zoom}px`;
            canvas.style.height = `${originalHeight * zoom}px`;
            document.getElementById('zoomLevel').textContent = `${Math.round(zoom * 100)}%`;
        }

        // ============================
        // DEBOUNCE
        // ============================
        function debounceGerarDiagrama() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(gerarDiagrama, 500);
        }

        // ============================
        // ARRASTAR RÓTULOS
        // ============================
        let dragging = null;
        let dragStartX = 0, dragStartY = 0;
        let dragOriginalOffset = {dx: 0, dy: 0};

        canvas.addEventListener('mousemove', function(e) {
            if (modoAtual !== 'trecho_oaes' && modoAtual !== 'oaes_sem_pista') {
                canvas.style.cursor = 'default';
                return;
            }
            
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const mx = (e.clientX - rect.left) * scaleX;
            const my = (e.clientY - rect.top) * scaleY;
            
            let overLabel = false;
            const qtd = Number(qtdOaeInput.value);
            if (qtd === 0) { canvas.style.cursor = 'default'; return; }
            
            const kmIni = Number(document.getElementById('kmIni').value);
            const kmFim = Number(document.getElementById('kmFim').value);
            if (isNaN(kmIni) || isNaN(kmFim) || kmFim <= kmIni) { canvas.style.cursor = 'default'; return; }
            
            const margem = 70;
            const largura = canvas.width - margem * 2;
            const yBase = 140;
            const temDuplicacao = (modoAtual === 'trecho_oaes') ? document.getElementById('chkDup').checked : false;
            let yTop = yBase;
            if (temDuplicacao && modoAtual === 'trecho_oaes') yTop = dupYTop;
            
            for (let i = 1; i <= qtd; i++) {
                const valOae = document.getElementById(`oae${i}`).value;
                if (!valOae) continue;
                const x = kmToX(Number(valOae), kmIni, kmFim, margem, largura);
                const key = (modoAtual === 'trecho_oaes') ? `oae_${i}` : `oae_sem_pista_${i}`;
                const offset = offsetLabels[key] || {dx: 0, dy: 0};
                const yNome = (modoAtual === 'trecho_oaes') ? yTop - 10 + offset.dy : yTop - 8 + offset.dy;
                const xTexto = x + offset.dx;
                if (Math.abs(mx - xTexto) < 30 && Math.abs(my - yNome) < 20) {
                    overLabel = true;
                    break;
                }
                const yKm = (modoAtual === 'trecho_oaes') ? yTop + 2*32 + 18 + offset.dy : yTop + 2*32 + 18 + offset.dy;
                if (Math.abs(mx - xTexto) < 30 && Math.abs(my - yKm) < 20) {
                    overLabel = true;
                    break;
                }
            }
            canvas.style.cursor = overLabel ? 'grab' : 'default';
        });

        canvas.addEventListener('mousedown', function(e) {
            if (modoAtual !== 'trecho_oaes' && modoAtual !== 'oaes_sem_pista') return;
            
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const mx = (e.clientX - rect.left) * scaleX;
            const my = (e.clientY - rect.top) * scaleY;
            
            const qtd = Number(qtdOaeInput.value);
            if (qtd === 0) return;
            
            const kmIni = Number(document.getElementById('kmIni').value);
            const kmFim = Number(document.getElementById('kmFim').value);
            if (isNaN(kmIni) || isNaN(kmFim) || kmFim <= kmIni) return;
            
            const margem = 70;
            const largura = canvas.width - margem * 2;
            const yBase = 140;
            const temDuplicacao = (modoAtual === 'trecho_oaes') ? document.getElementById('chkDup').checked : false;
            let yTop = yBase;
            if (temDuplicacao && modoAtual === 'trecho_oaes') yTop = dupYTop;
            
            for (let i = 1; i <= qtd; i++) {
                const valOae = document.getElementById(`oae${i}`).value;
                if (!valOae) continue;
                const x = kmToX(Number(valOae), kmIni, kmFim, margem, largura);
                const key = (modoAtual === 'trecho_oaes') ? `oae_${i}` : `oae_sem_pista_${i}`;
                const offset = offsetLabels[key] || {dx: 0, dy: 0};
                const yNome = (modoAtual === 'trecho_oaes') ? yTop - 10 + offset.dy : yTop - 8 + offset.dy;
                const xTexto = x + offset.dx;
                if (Math.abs(mx - xTexto) < 30 && Math.abs(my - yNome) < 20) {
                    dragging = {key, offset};
                    dragStartX = mx;
                    dragStartY = my;
                    dragOriginalOffset = {...offset};
                    canvas.style.cursor = 'grabbing';
                    return;
                }
                const yKm = (modoAtual === 'trecho_oaes') ? yTop + 2*32 + 18 + offset.dy : yTop + 2*32 + 18 + offset.dy;
                if (Math.abs(mx - xTexto) < 30 && Math.abs(my - yKm) < 20) {
                    dragging = {key, offset};
                    dragStartX = mx;
                    dragStartY = my;
                    dragOriginalOffset = {...offset};
                    canvas.style.cursor = 'grabbing';
                    return;
                }
            }
        });

        canvas.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const mx = (e.clientX - rect.left) * scaleX;
            const my = (e.clientY - rect.top) * scaleY;
            const deltaX = mx - dragStartX;
            const deltaY = my - dragStartY;
            offsetLabels[dragging.key] = {
                dx: dragOriginalOffset.dx + deltaX,
                dy: dragOriginalOffset.dy + deltaY
            };
            canvas.style.cursor = 'grabbing';
            debounceGerarDiagrama();
        });

        canvas.addEventListener('mouseup', function() {
            if (dragging) {
                dragging = null;
                canvas.style.cursor = 'default';
            }
        });

        canvas.addEventListener('mouseleave', function() {
            if (dragging) {
                dragging = null;
                canvas.style.cursor = 'default';
            }
        });

        // ============================
        // COPIAR IMAGEM
        // ============================
        function copiarImagem() {
            console.log('copiarImagem() chamado'); // Depuração
            try {
                const escala = 4;
                const tempCanvas = document.createElement('canvas');
                tempCanvas.width = canvas.width * escala;
                tempCanvas.height = canvas.height * escala;
                const tempCtx = tempCanvas.getContext('2d');
                
                tempCtx.clearRect(0, 0, tempCanvas.width, tempCanvas.height);
                tempCtx.scale(escala, escala);
                
                desenharDiagramaNoContexto(tempCtx, false);
                
                tempCanvas.toBlob(async function(blob) {
                    try {
                        await navigator.clipboard.write([
                            new ClipboardItem({ [blob.type]: blob })
                        ]);
                        showMessage('success', 'Imagem copiada (fundo transparente, HD, sem instrução)!', true);
                    } catch (err) {
                        const link = document.createElement('a');
                        link.download = 'diagrama_dnit.png';
                        link.href = tempCanvas.toDataURL('image/png');
                        link.click();
                        showMessage('success', 'Imagem baixada (clique em Copiar novamente para copiar direto).', true);
                    }
                }, 'image/png');
            } catch (error) {
                showMessage('error', 'Erro ao copiar: ' + error.message);
            }
        }

        // ============================
        // SALVAR / CARREGAR
        // ============================
        function salvarConfig() {
            console.log('salvarConfig() chamado'); // Depuração
            try {
                const config = {
                    modo: modoAtual,
                    kmIni: document.getElementById('kmIni').value,
                    kmFim: document.getElementById('kmFim').value,
                    qtdOae: document.getElementById('qtdOae').value,
                    chkDup: document.getElementById('chkDup').checked,
                    chkDuasCores: document.getElementById('chkDuasCores').checked,
                    oaes: [],
                    offsetLabels: offsetLabels,
                    nomeOAE: document.getElementById('nomeOAE').value,
                    kmIniOAE: document.getElementById('kmIniOAE').value,
                    kmFimOAE: document.getElementById('kmFimOAE').value,
                    chkAcessos: document.getElementById('chkAcessos').checked
                };
                const qtdOae = Number(config.qtdOae);
                for (let i = 1; i <= qtdOae; i++) {
                    const oaeVal = document.getElementById(`oae${i}`).value;
                    config.oaes.push(oaeVal || '');
                }
                const dataStr = JSON.stringify(config, null, 2);
                const dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);
                const link = document.createElement('a');
                link.setAttribute('href', dataUri);
                link.setAttribute('download', `diagrama_config_${new Date().toISOString().split('T')[0]}.json`);
                link.click();
            } catch (error) {
                showMessage('error', 'Erro ao salvar: ' + error.message);
            }
        }

        function carregarConfig() {
            console.log('carregarConfig() chamado'); // Depuração
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.onchange = e => {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const config = JSON.parse(e.target.result);
                        if (config.modo) {
                            const radio = document.querySelector(`input[name="modoDiagrama"][value="${config.modo}"]`);
                            if (radio) radio.checked = true;
                            modoAtual = config.modo;
                            alterarModo();
                        }
                        document.getElementById('kmIni').value = config.kmIni || '';
                        document.getElementById('kmFim').value = config.kmFim || '';
                        document.getElementById('qtdOae').value = config.qtdOae || 0;
                        document.getElementById('qtdOae').dispatchEvent(new Event('change'));
                        document.getElementById('chkDup').checked = config.chkDup || false;
                        document.getElementById('chkDuasCores').checked = config.chkDuasCores || false;
                        document.getElementById('optsDup').style.display = config.chkDup ? 'block' : 'none';
                        if (config.offsetLabels) offsetLabels = config.offsetLabels;
                        document.getElementById('nomeOAE').value = config.nomeOAE || '';
                        document.getElementById('kmIniOAE').value = config.kmIniOAE || '';
                        document.getElementById('kmFimOAE').value = config.kmFimOAE || '';
                        document.getElementById('chkAcessos').checked = config.chkAcessos !== undefined ? config.chkAcessos : true;
                        setTimeout(() => {
                            if (config.oaes) {
                                config.oaes.forEach((valor, index) => {
                                    const input = document.getElementById(`oae${index + 1}`);
                                    if (input) input.value = valor;
                                });
                            }
                            gerarDiagrama();
                        }, 300);
                    } catch (error) {
                        showMessage('error', 'Erro ao carregar: ' + error.message);
                    }
                };
                reader.readAsText(file);
            };
            input.click();
        }

        function limpar() {
            console.log('limpar() chamado'); // Depuração
            if (!confirm('Tem certeza que deseja limpar todos os campos e o diagrama?')) return;
            document.querySelectorAll('input[type="number"], input[type="text"]').forEach(i => i.value = '');
            document.getElementById('chkDup').checked = false;
            document.getElementById('chkDuasCores').checked = false;
            document.getElementById('chkAcessos').checked = true;
            document.getElementById('optsDup').style.display = 'none';
            document.getElementById('qtdOae').value = 0;
            oaesDiv.innerHTML = '';
            offsetLabels = {};
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            legendContainer.innerHTML = '';
            resetZoom();
        }

        // ============================
        // INICIALIZAÇÃO
        // ============================
        document.getElementById('kmIni').addEventListener('input', debounceGerarDiagrama);
        document.getElementById('kmFim').addEventListener('input', debounceGerarDiagrama);
        document.getElementById('chkDup').addEventListener('change', debounceGerarDiagrama);
        document.getElementById('chkDuasCores').addEventListener('change', debounceGerarDiagrama);
        document.getElementById('nomeOAE').addEventListener('input', debounceGerarDiagrama);
        document.getElementById('kmIniOAE').addEventListener('input', debounceGerarDiagrama);
        document.getElementById('kmFimOAE').addEventListener('input', debounceGerarDiagrama);
        document.getElementById('chkAcessos').addEventListener('change', debounceGerarDiagrama);

        window.addEventListener('load', () => {
            console.log('DOM completamente carregado, inicializando diagrama...');
            aplicarZoom();
            alterarModo();
            gerarDiagrama();
        });
    </script>
</body>
</html>
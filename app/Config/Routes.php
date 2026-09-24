<?php
// app/Config/Routes.php

return [
    // Página inicial
    '' => ['controller' => 'HomeController', 'method' => 'index'],
    'index' => ['controller' => 'HomeController', 'method' => 'index'],

    // Autenticação
    'login' => ['controller' => 'AuthController', 'method' => 'login'],
    'logout' => ['controller' => 'AuthController', 'method' => 'logout'],
    'solicitar_acesso' => ['controller' => 'AuthController', 'method' => 'solicitarAcesso'],
    'alterar_senha' => ['controller' => 'AuthController', 'method' => 'alterarSenha'],
    'recuperar_senha' => ['controller' => 'AuthController', 'method' => 'recuperarSenha'],
    'redefinir_senha' => ['controller' => 'AuthController', 'method' => 'redefinirSenha'],

    // Setores
    'selecionar_setor' => ['controller' => 'SetorController', 'method' => 'selecionar'],
    'definir_setor' => ['controller' => 'SetorController', 'method' => 'definirSetor'],
    'trocar_setor' => ['controller' => 'SetorController', 'method' => 'trocar'],

    // Processos
    'create' => ['controller' => 'ProcessoController', 'method' => 'create'],
    'edit' => ['controller' => 'ProcessoController', 'method' => 'edit'],
    'view' => ['controller' => 'ProcessoController', 'method' => 'view'],
    'delete' => ['controller' => 'ProcessoController', 'method' => 'delete'],
    'get_contrato_info' => ['controller' => 'ProcessoController', 'method' => 'getContratoInfo'],

    // Exportação
    'exportar_csv' => ['controller' => 'ExportController', 'method' => 'csv'],

    // Admin
    'admin' => ['controller' => 'AdminController', 'method' => 'index'],
    'gerencial' => ['controller' => 'AdminController', 'method' => 'gerencial'],

    // RDCI
    'contratos_rdci' => ['controller' => 'RdciController', 'method' => 'contratos'],
    'rdci' => ['controller' => 'RdciController', 'method' => 'atualizacoes'],
    'notificacoes_rdci' => ['controller' => 'RdciController', 'method' => 'notificacoes'],
    'atualizacoes_rdci' => ['controller' => 'RdciController', 'method' => 'atualizacoes'],
    'comparativo_bases' => ['controller' => 'RdciController', 'method' => 'comparativo'],
    'dashboard_projetos' => ['controller' => 'RdciController', 'method' => 'dashboardProjetos'],

    // ATLAS — Comparativo entre Bancos
    'comparativo_bancos' => ['controller' => 'ComparacaoAtlasController', 'method' => 'comparativoBancos'],
    'avanco_fisico' => ['controller' => 'AvancoFisicoController', 'method' => 'index'],

    // Modelos
    'modelos' => ['controller' => 'ModeloController', 'method' => 'index'],

    // Links
    'links' => ['controller' => 'LinksController', 'method' => 'index'],

    // ===== ATLAS =====
    'home_atlas' => ['controller' => 'HomeController', 'method' => 'index'], // já existe via setor_slug
    'diagrama_unifilar' => ['controller' => 'HomeController', 'method' => 'diagramaUnifilar'],
    'gestao_obras' => ['controller' => 'HomeController', 'method' => 'gestaoObras'], // NOVA ROTA

];
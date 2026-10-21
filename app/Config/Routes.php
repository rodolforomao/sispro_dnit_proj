<?php
// app/Config/Routes.php

return [
    // Página inicial
    ''      => ['controller' => 'HomeController', 'method' => 'index'],
    'index' => ['controller' => 'HomeController', 'method' => 'index'],

    // Autenticação
    'login'            => ['controller' => 'AuthController', 'method' => 'login'],
    'logout'           => ['controller' => 'AuthController', 'method' => 'logout'],
    'solicitar_acesso' => ['controller' => 'AuthController', 'method' => 'solicitarAcesso'],
    'alterar_senha'    => ['controller' => 'AuthController', 'method' => 'alterarSenha'],
    'recuperar_senha'  => ['controller' => 'AuthController', 'method' => 'recuperarSenha'],
    'redefinir_senha'  => ['controller' => 'AuthController', 'method' => 'redefinirSenha'],

    // Setores
    'selecionar_setor' => ['controller' => 'SetorController', 'method' => 'selecionar'],
    'definir_setor'    => ['controller' => 'SetorController', 'method' => 'definirSetor'],
    'trocar_setor'     => ['controller' => 'SetorController', 'method' => 'trocar'],

    // Processos
    'create'            => ['controller' => 'ProcessoController', 'method' => 'create'],
    'edit'              => ['controller' => 'ProcessoController', 'method' => 'edit'],
    'view'              => ['controller' => 'ProcessoController', 'method' => 'view'],
    'delete'            => ['controller' => 'ProcessoController', 'method' => 'delete'],
    'get_contrato_info' => ['controller' => 'ProcessoController', 'method' => 'getContratoInfo'],

    // Exportação
    'exportar_csv' => ['controller' => 'ExportController', 'method' => 'csv'],

    // Admin
    'admin'     => ['controller' => 'AdminController', 'method' => 'index'],
    'gerencial' => ['controller' => 'AdminController', 'method' => 'gerencial'],

    // RDCI (Assessoria e Projetos)
    'contratos_rdci'      => ['controller' => 'RdciController', 'method' => 'contratos'],
    'rdci'                => ['controller' => 'RdciController', 'method' => 'atualizacoes'],
    'notificacoes_rdci'   => ['controller' => 'RdciController', 'method' => 'notificacoes'],
    'atualizacoes_rdci'   => ['controller' => 'RdciController', 'method' => 'atualizacoes'],
    'comparativo_bases'   => ['controller' => 'RdciController', 'method' => 'comparativo'],
    'dashboard_projetos'  => ['controller' => 'RdciController', 'method' => 'dashboardProjetos'],

    // Modelos
    'modelos' => ['controller' => 'ModeloController', 'method' => 'index'],

    // Links
    'links' => ['controller' => 'LinksController', 'method' => 'index'],

    // ============================================================
    // ATLAS / MONITORAMENTO (setor separado)
    // ============================================================
    'home_atlas'                 => ['controller' => 'AtlasController', 'method' => 'index'],
    'comparativo_bancos_1x36x38' => ['controller' => 'ComparacaoAtlasController', 'method' => 'comparativoBancos'],
    'comparativo_bancos_16x40'   => ['controller' => 'ComparacaoBancos16x40Controller', 'method' => 'index'],
    'avanco_fisico'              => ['controller' => 'AvancoFisicoController', 'method' => 'index'],

    // Notificações de Obras (Atlas)
    'notificacoes_obras'                       => ['controller' => 'NotificacoesObrasController', 'method' => 'index'],
    'notificacoes_obras/atualizar_acao'        => ['controller' => 'NotificacoesObrasController', 'method' => 'atualizarAcao'],
    'notificacoes_obras/importar'              => ['controller' => 'NotificacoesObrasController', 'method' => 'importar'],
    'notificacoes_obras/detalhes'              => ['controller' => 'NotificacoesObrasController', 'method' => 'detalhes'],
    'notificacoes_obras/exportar'              => ['controller' => 'NotificacoesObrasController', 'method' => 'exportar'],
    'notificacoes_obras/comentarios'           => ['controller' => 'NotificacoesObrasController', 'method' => 'listarComentarios'],
    'notificacoes_obras/comentarios/adicionar' => ['controller' => 'NotificacoesObrasController', 'method' => 'adicionarComentario'],
    'notificacoes_obras/comentarios/editar'    => ['controller' => 'NotificacoesObrasController', 'method' => 'editarComentario'],
    'notificacoes_obras/comentarios/excluir'   => ['controller' => 'NotificacoesObrasController', 'method' => 'excluirComentario'],
];
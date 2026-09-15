<?php
// index.php (RAIZ do projeto) - Roteador MVC com depuração e fallback

// Define os caminhos
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');

// Inicia a sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carrega a conexão com o banco (config.php)
$configFile = APP_PATH . '/public/config.php';
if (!file_exists($configFile)) {
    die("Arquivo de configuração não encontrado: $configFile");
}
require_once $configFile;

// Pega a URL
$url = isset($_GET['url']) ? $_GET['url'] : '';
$url = rtrim($url, '/');
// Remove possíveis extensões .php da URL
$url = preg_replace('/\.php$/', '', $url);

// Carrega as rotas
$routesFile = APP_PATH . '/Config/Routes.php';
if (!file_exists($routesFile)) {
    die("Arquivo de rotas não encontrado: $routesFile");
}
$routes = require_once $routesFile;

// Verifica se a URL existe no mapa de rotas
if (isset($routes[$url])) {
    $controllerName = $routes[$url]['controller'];
    $methodName = $routes[$url]['method'];
    
    $controllerFile = APP_PATH . "/Controllers/{$controllerName}.php";
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
        // Verifica se a classe existe
        if (class_exists($controllerName)) {
            $controller = new $controllerName($pdo);
            if (method_exists($controller, $methodName)) {
                $controller->$methodName();
                exit;
            } else {
                die("Método '$methodName' não encontrado no Controller '$controllerName'.");
            }
        } else {
            die("Classe '$controllerName' não encontrada no arquivo '$controllerFile'.");
        }
    } else {
        die("Controller não encontrado: $controllerFile");
    }
}

// Se não for uma rota MVC, tenta carregar do sistema legado (public)
chdir(APP_PATH . '/public');
$legacyFile = APP_PATH . '/public/' . ($url ?: 'home') . '.php';
if (file_exists($legacyFile)) {
    require_once $legacyFile;
    exit;
}

// Se nada funcionar, erro 404
http_response_code(404);
echo "Página não encontrada. URL: " . htmlspecialchars($url);
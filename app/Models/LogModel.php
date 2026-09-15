class LogModel {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function registrar($usuario_id, $usuario_nome, $acao, $tabela, $registro_id = null, $dados_anteriores = null, $dados_novos = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $sql = "INSERT INTO logs_auditoria (usuario_id, usuario_nome, acao, tabela, registro_id, dados_anteriores, dados_novos, ip, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $usuario_id,
            $usuario_nome,
            $acao,
            $tabela,
            $registro_id,
            $dados_anteriores ? json_encode($dados_anteriores) : null,
            $dados_novos ? json_encode($dados_novos) : null,
            $ip,
            $user_agent
        ]);
    }
}
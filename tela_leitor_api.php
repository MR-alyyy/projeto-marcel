<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function responderLeitor(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SESSION['tipo'] ?? '') !== 'leitor') {
    responderLeitor(401, ['message' => 'Faça login como leitor para continuar.']);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . '/conexao.php';
    $conexao->set_charset('utf8mb4');
} catch (mysqli_sql_exception $erro) {
    error_log('Falha ao conectar a área do leitor ao banco: ' . $erro->getMessage());
    responderLeitor(500, ['message' => 'Não foi possível conectar ao banco de dados.']);
}

function obterIdLeitor(mysqli $conexao): int
{
    $id = filter_var($_SESSION['id_leitor'] ?? null, FILTER_VALIDATE_INT);
    if ($id !== false && $id > 0) {
        return (int) $id;
    }

    $nome = (string) ($_SESSION['nome'] ?? '');
    if ($nome === '') {
        responderLeitor(401, ['message' => 'Entre novamente para acessar sua conta.']);
    }

    $stmt = $conexao->prepare('SELECT id FROM leitores WHERE nome = ?');
    $stmt->bind_param('s', $nome);
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($resultado->num_rows !== 1) {
        $stmt->close();
        responderLeitor(401, ['message' => 'Entre novamente para acessar sua conta.']);
    }
    $id = (int) $resultado->fetch_assoc()['id'];
    $stmt->close();
    $_SESSION['id_leitor'] = $id;
    return $id;
}

function idLivroRecebido(array $dados): int
{
    $id = filter_var($dados['bookId'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        responderLeitor(422, ['message' => 'Livro inválido.']);
    }
    return (int) $id;
}

function listarAreaLeitor(mysqli $conexao, int $idLeitor): void
{
    $livros = $conexao->query("
        SELECT l.id, l.titulo, l.autor, l.genero, l.quantidade,
               l.quantidade - (
                   SELECT COUNT(*) FROM emprestimos e
                   WHERE e.id_livro = l.id AND e.status = 'emprestado'
               ) AS disponiveis
        FROM livros l
        WHERE l.status = 'ativo'
        ORDER BY l.titulo
    ")->fetch_all(MYSQLI_ASSOC);

    $stmt = $conexao->prepare("
        SELECT e.id, e.id_livro, l.titulo, l.autor, e.data_emprestimo,
               e.data_prevista, e.data_devolucao, e.multa, e.status
        FROM emprestimos e
        JOIN livros l ON l.id = e.id_livro
        WHERE e.id_leitor = ?
        ORDER BY e.data_emprestimo DESC, e.id DESC
    ");
    $stmt->bind_param('i', $idLeitor);
    $stmt->execute();
    $emprestimos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conexao->prepare("
        SELECT l.id, l.titulo, l.autor, l.genero, l.quantidade,
               l.status AS status_livro,
               l.quantidade - (
                   SELECT COUNT(*) FROM emprestimos e
                   WHERE e.id_livro = l.id AND e.status = 'emprestado'
               ) AS disponiveis
        FROM lista_desejos d
        JOIN livros l ON l.id = d.id_livro
        WHERE d.id_leitor = ?
        ORDER BY d.data_adicionado DESC
    ");
    $stmt->bind_param('i', $idLeitor);
    $stmt->execute();
    $desejos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($livros as &$livro) {
        $livro['id'] = (int) $livro['id'];
        $livro['quantidade'] = (int) $livro['quantidade'];
        $livro['disponiveis'] = max(0, (int) $livro['disponiveis']);
    }
    unset($livro);

    foreach ($emprestimos as &$emprestimo) {
        $emprestimo['id'] = (int) $emprestimo['id'];
        $emprestimo['id_livro'] = (int) $emprestimo['id_livro'];
        $emprestimo['multa'] = (float) $emprestimo['multa'];
    }
    unset($emprestimo);

    foreach ($desejos as &$desejo) {
        $desejo['id'] = (int) $desejo['id'];
        $desejo['quantidade'] = (int) $desejo['quantidade'];
        $desejo['disponiveis'] = max(0, (int) $desejo['disponiveis']);
    }
    unset($desejo);

    responderLeitor(200, [
        'nome' => (string) ($_SESSION['nome'] ?? ''),
        'books' => $livros,
        'loans' => $emprestimos,
        'wishlist' => $desejos,
    ]);
}

try {
    $conexao->query("
        CREATE TABLE IF NOT EXISTS lista_desejos (
            id_leitor INT NOT NULL,
            id_livro INT NOT NULL,
            data_adicionado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_leitor, id_livro),
            CONSTRAINT fk_desejo_leitor
                FOREIGN KEY (id_leitor) REFERENCES leitores (id) ON DELETE CASCADE,
            CONSTRAINT fk_desejo_livro
                FOREIGN KEY (id_livro) REFERENCES livros (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $idLeitor = obterIdLeitor($conexao);
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $acao = (string) ($_GET['action'] ?? '');

    if ($metodo === 'GET' && $acao === 'listar') {
        listarAreaLeitor($conexao, $idLeitor);
    }

    if ($metodo !== 'POST') {
        responderLeitor(405, ['message' => 'Método não permitido.']);
    }

    try {
        $dados = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $erro) {
        responderLeitor(400, ['message' => 'O conteúdo enviado é inválido.']);
    }
    if (!is_array($dados)) {
        responderLeitor(400, ['message' => 'O conteúdo enviado é inválido.']);
    }

    $idLivro = idLivroRecebido($dados);
    if ($acao === 'adicionar_desejo') {
        $stmt = $conexao->prepare("SELECT id FROM livros WHERE id = ? AND status = 'ativo'");
        $stmt->bind_param('i', $idLivro);
        $stmt->execute();
        $livroExiste = $stmt->get_result()->num_rows === 1;
        $stmt->close();
        if (!$livroExiste) {
            responderLeitor(404, ['message' => 'Livro não encontrado no acervo.']);
        }

        $stmt = $conexao->prepare('INSERT IGNORE INTO lista_desejos (id_leitor, id_livro) VALUES (?, ?)');
        $stmt->bind_param('ii', $idLeitor, $idLivro);
        $stmt->execute();
        $stmt->close();
        responderLeitor(200, ['message' => 'Livro adicionado à lista de desejos.']);
    }

    if ($acao === 'remover_desejo') {
        $stmt = $conexao->prepare('DELETE FROM lista_desejos WHERE id_leitor = ? AND id_livro = ?');
        $stmt->bind_param('ii', $idLeitor, $idLivro);
        $stmt->execute();
        $stmt->close();
        responderLeitor(200, ['message' => 'Livro removido da lista de desejos.']);
    }

    responderLeitor(404, ['message' => 'Operação desconhecida.']);
} catch (mysqli_sql_exception $erro) {
    error_log('Erro na área do leitor: ' . $erro->getMessage());
    responderLeitor(500, ['message' => 'Não foi possível carregar ou salvar os dados.']);
}

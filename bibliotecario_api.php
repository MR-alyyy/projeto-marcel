<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function responder(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SESSION['tipo'] ?? '') !== 'bibliotecario') {
    responder(401, ['message' => 'Faça login como bibliotecário para continuar.']);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . '/conexao.php';
    $conexao->set_charset('utf8mb4');
} catch (mysqli_sql_exception $erro) {
    error_log('Falha ao conectar ao banco da biblioteca: ' . $erro->getMessage());
    responder(500, ['message' => 'Não foi possível conectar ao banco de dados.']);
}

function garantirColuna(mysqli $conexao, string $tabela, string $coluna, string $definicao): void
{
    $stmt = $conexao->prepare(
        'SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $tabela, $coluna);
    $stmt->execute();
    $existe = (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
    $stmt->close();

    if (!$existe) {
        $conexao->query("ALTER TABLE `$tabela` ADD COLUMN `$coluna` $definicao");
    }
}

function garantirTamanhoCpf(mysqli $conexao, string $tabela): void
{
    $stmt = $conexao->prepare(
        'SELECT CHARACTER_MAXIMUM_LENGTH AS tamanho
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $coluna = 'cpf';
    $stmt->bind_param('ss', $tabela, $coluna);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($linha && (int) $linha['tamanho'] < 24) {
        $conexao->query("ALTER TABLE `$tabela` MODIFY COLUMN `cpf` VARCHAR(100) NOT NULL");
    }
}

function prepararBanco(mysqli $conexao): void
{
    garantirColuna($conexao, 'leitores', 'email', 'VARCHAR(120) NULL');
    garantirColuna($conexao, 'funcionario', 'email', 'VARCHAR(120) NULL');
    garantirColuna($conexao, 'funcionario', 'matricula', 'VARCHAR(30) NULL');
    garantirTamanhoCpf($conexao, 'leitores');
    garantirTamanhoCpf($conexao, 'funcionario');
}

function validarCpf(string $cpf): bool
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += (int) $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int) $cpf[$c] !== $d) {
            return false;
        }
    }
    return true;
}

function criptografarCpf(string $cpf): string
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    $chave = 'minha_chave_secreta_123';
    $iv = substr(hash('sha256', $chave), 0, 16);
    $criptografado = openssl_encrypt($cpf, 'AES-256-CBC', $chave, 0, $iv);
    if ($criptografado === false) {
        throw new RuntimeException('Não foi possível proteger o CPF.');
    }
    return $criptografado;
}

function texto(array $dados, string $campo, int $limite, bool $obrigatorio = true): string
{
    $bruto = $dados[$campo] ?? '';
    if (!is_string($bruto) && !is_numeric($bruto)) {
        responder(422, ['message' => "Confira o campo \"$campo\" e tente novamente."]);
    }
    $valor = trim((string) $bruto);
    $comprimento = function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor);
    if (($obrigatorio && $valor === '') || $comprimento > $limite) {
        responder(422, ['message' => "Confira o campo \"$campo\" e tente novamente."]);
    }
    return $valor;
}

function idValido(array $dados, string $campo): int
{
    $id = filter_var($dados[$campo] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        responder(422, ['message' => 'Identificador inválido.']);
    }
    return $id;
}

function senhaForte(string $senha): bool
{
    return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $senha);
}

function listarDados(mysqli $conexao): void
{
    $livros = $conexao->query("
        SELECT l.id, l.titulo, l.autor, l.genero, l.quantidade,
               (SELECT COUNT(*) FROM emprestimos e
                WHERE e.id_livro = l.id AND e.status = 'emprestado') AS emprestados,
               (SELECT COUNT(*) FROM emprestimos e
                WHERE e.id_livro = l.id AND e.status = 'devolvido') AS devolvidos
        FROM livros l
        WHERE l.status = 'ativo'
        ORDER BY l.titulo
    ")->fetch_all(MYSQLI_ASSOC);

    $funcionarios = $conexao->query("
        SELECT id, nome, telefone, email, matricula
        FROM funcionario
        ORDER BY nome
    ")->fetch_all(MYSQLI_ASSOC);

    $clientes = $conexao->query("
        SELECT id, nome, telefone, email
        FROM leitores
        ORDER BY nome
    ")->fetch_all(MYSQLI_ASSOC);

    $emprestimos = $conexao->query("
        SELECT e.id, e.id_livro, e.id_leitor, e.data_emprestimo, le.nome AS cliente
        FROM emprestimos e
        JOIN leitores le ON le.id = e.id_leitor
        WHERE e.status = 'emprestado'
        ORDER BY e.data_emprestimo
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($livros as &$livro) {
        $livro['id'] = (int) $livro['id'];
        $livro['quantidade'] = (int) $livro['quantidade'];
        $livro['emprestados'] = (int) $livro['emprestados'];
        $livro['devolvidos'] = (int) $livro['devolvidos'];
    }
    unset($livro);

    foreach ($funcionarios as &$funcionario) {
        $funcionario['id'] = (int) $funcionario['id'];
    }
    unset($funcionario);

    foreach ($clientes as &$cliente) {
        $cliente['id'] = (int) $cliente['id'];
    }
    unset($cliente);

    foreach ($emprestimos as &$emprestimo) {
        $emprestimo['id'] = (int) $emprestimo['id'];
        $emprestimo['bookId'] = (int) $emprestimo['id_livro'];
        $emprestimo['clientId'] = (int) $emprestimo['id_leitor'];
        $emprestimo['emprestadoEm'] = $emprestimo['data_emprestimo'];
        unset($emprestimo['id_livro'], $emprestimo['id_leitor'], $emprestimo['data_emprestimo']);
    }
    unset($emprestimo);

    responder(200, [
        'books' => $livros,
        'staff' => $funcionarios,
        'clients' => $clientes,
        'loans' => $emprestimos,
    ]);
}

function cadastrarLivro(mysqli $conexao, array $dados): void
{
    $titulo = texto($dados, 'titulo', 30);
    $autor = texto($dados, 'autor', 30);
    $genero = texto($dados, 'genero', 15, false);
    $quantidade = filter_var($dados['quantidade'] ?? null, FILTER_VALIDATE_INT);
    if ($quantidade === false || $quantidade < 1) {
        responder(422, ['message' => 'Informe uma quantidade de exemplares maior que zero.']);
    }

    $stmt = $conexao->prepare(
        "INSERT INTO livros (titulo, autor, genero, quantidade, descricao)
         VALUES (?, ?, ?, ?, '')"
    );
    $stmt->bind_param('sssi', $titulo, $autor, $genero, $quantidade);
    $stmt->execute();
    $stmt->close();
    responder(201, ['message' => 'Livro cadastrado.']);
}

function cadastrarCliente(mysqli $conexao, array $dados): void
{
    $nome = texto($dados, 'nome', 30);
    $telefone = texto($dados, 'telefone', 15);
    $email = texto($dados, 'email', 120, false);
    $cpfRecebido = texto($dados, 'cpf', 14);
    $endereco = texto($dados, 'endereco', 60);
    $senha = $dados['senha'] ?? null;

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responder(422, ['message' => 'E-mail inválido.']);
    }
    if (!validarCpf($cpfRecebido)) {
        responder(422, ['message' => 'CPF inválido.']);
    }
    if (!is_string($senha) || !senhaForte($senha)) {
        responder(422, ['message' => 'A senha precisa ter 8 caracteres, letra maiúscula, minúscula, número e símbolo.']);
    }

    $cpf = criptografarCpf($cpfRecebido);
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conexao->prepare(
        'INSERT INTO leitores (nome, telefone, cpf, endereco, senha, email)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssss', $nome, $telefone, $cpf, $endereco, $senhaHash, $email);
    $stmt->execute();
    $stmt->close();
    responder(201, ['message' => 'Cliente cadastrado.']);
}

function cadastrarFuncionario(mysqli $conexao, array $dados): void
{
    $nome = texto($dados, 'nome', 30);
    $telefone = texto($dados, 'telefone', 15);
    $cpfRecebido = texto($dados, 'cpf', 14);
    $email = texto($dados, 'email', 120, false);
    $matricula = texto($dados, 'matricula', 30, false);
    $senha = $dados['senha'] ?? null;

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responder(422, ['message' => 'E-mail inválido.']);
    }
    if (!validarCpf($cpfRecebido)) {
        responder(422, ['message' => 'CPF inválido.']);
    }
    if (!is_string($senha) || !senhaForte($senha)) {
        responder(422, ['message' => 'A senha precisa ter 8 caracteres, letra maiúscula, minúscula, número e símbolo.']);
    }

    $cpf = criptografarCpf($cpfRecebido);
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conexao->prepare(
        'INSERT INTO funcionario (nome, senha, telefone, cpf, email, matricula)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssss', $nome, $senhaHash, $telefone, $cpf, $email, $matricula);
    $stmt->execute();
    $stmt->close();
    responder(201, ['message' => 'Funcionário cadastrado.']);
}

function removerLivro(mysqli $conexao, array $dados): void
{
    $id = idValido($dados, 'id');
    $stmt = $conexao->prepare("
        UPDATE livros
        SET status = 'inativo'
        WHERE id = ? AND status = 'ativo'
          AND NOT EXISTS (
              SELECT 1 FROM emprestimos
              WHERE id_livro = ? AND status = 'emprestado'
          )
    ");
    $stmt->bind_param('ii', $id, $id);
    $stmt->execute();
    $removido = $stmt->affected_rows === 1;
    $stmt->close();
    if (!$removido) {
        responder(409, ['message' => 'Livro inexistente ou com empréstimo em aberto.']);
    }
    responder(200, ['message' => 'Livro removido do acervo.']);
}

function removerFuncionario(mysqli $conexao, array $dados): void
{
    $id = idValido($dados, 'id');
    $stmt = $conexao->prepare('DELETE FROM funcionario WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $removido = $stmt->affected_rows === 1;
    $stmt->close();
    if (!$removido) {
        responder(404, ['message' => 'Funcionário não encontrado.']);
    }
    responder(200, ['message' => 'Funcionário removido.']);
}

function emprestarLivro(mysqli $conexao, array $dados): void
{
    $idLivro = idValido($dados, 'bookId');
    $idLeitor = idValido($dados, 'clientId');
    $conexao->begin_transaction();

    try {
        $stmt = $conexao->prepare("SELECT quantidade FROM livros WHERE id = ? AND status = 'ativo' FOR UPDATE");
        $stmt->bind_param('i', $idLivro);
        $stmt->execute();
        $livro = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$livro) {
            $conexao->rollback();
            responder(404, ['message' => 'Livro não encontrado.']);
        }

        $stmt = $conexao->prepare("SELECT COUNT(*) AS total FROM emprestimos WHERE id_livro = ? AND status = 'emprestado'");
        $stmt->bind_param('i', $idLivro);
        $stmt->execute();
        $emprestados = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        if ($emprestados >= (int) $livro['quantidade']) {
            $conexao->rollback();
            responder(409, ['message' => 'Não há exemplares disponíveis para empréstimo.']);
        }

        $stmt = $conexao->prepare('SELECT id FROM leitores WHERE id = ?');
        $stmt->bind_param('i', $idLeitor);
        $stmt->execute();
        $leitorExiste = $stmt->get_result()->num_rows === 1;
        $stmt->close();
        if (!$leitorExiste) {
            $conexao->rollback();
            responder(404, ['message' => 'Cliente não encontrado.']);
        }

        $dataEmprestimo = date('Y-m-d');
        $dataPrevista = date('Y-m-d', strtotime('+7 days'));
        $stmt = $conexao->prepare("
            INSERT INTO emprestimos (id_livro, id_leitor, data_emprestimo, data_prevista, status)
            VALUES (?, ?, ?, ?, 'emprestado')
        ");
        $stmt->bind_param('iiss', $idLivro, $idLeitor, $dataEmprestimo, $dataPrevista);
        $stmt->execute();
        $stmt->close();
        $conexao->commit();
    } catch (mysqli_sql_exception $erro) {
        $conexao->rollback();
        throw $erro;
    }
    responder(201, ['message' => 'Empréstimo registrado.']);
}

function devolverLivro(mysqli $conexao, array $dados): void
{
    $idEmprestimo = idValido($dados, 'loanId');
    $stmt = $conexao->prepare("
        SELECT data_prevista
        FROM emprestimos
        WHERE id = ? AND status = 'emprestado'
    ");
    $stmt->bind_param('i', $idEmprestimo);
    $stmt->execute();
    $emprestimo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$emprestimo) {
        responder(404, ['message' => 'Empréstimo não encontrado ou já devolvido.']);
    }

    $hoje = new DateTimeImmutable('today');
    $dataPrevista = new DateTimeImmutable($emprestimo['data_prevista']);
    $multa = $hoje > $dataPrevista ? $dataPrevista->diff($hoje)->days * 2.00 : 0.00;
    $dataDevolucao = date('Y-m-d');
    $stmt = $conexao->prepare("
        UPDATE emprestimos
        SET data_devolucao = ?, multa = ?, status = 'devolvido'
        WHERE id = ? AND status = 'emprestado'
    ");
    $stmt->bind_param('sdi', $dataDevolucao, $multa, $idEmprestimo);
    $stmt->execute();
    $atualizado = $stmt->affected_rows === 1;
    $stmt->close();
    if (!$atualizado) {
        responder(409, ['message' => 'Este empréstimo já foi devolvido.']);
    }
    responder(200, ['message' => 'Devolução registrada.']);
}

try {
    prepararBanco($conexao);

    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $acao = (string) ($_GET['action'] ?? '');
    if ($metodo === 'GET' && $acao === 'listar') {
        listarDados($conexao);
    }
    if ($metodo !== 'POST') {
        responder(405, ['message' => 'Método não permitido.']);
    }

    try {
        $dados = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $erro) {
        responder(400, ['message' => 'O conteúdo enviado é inválido.']);
    }
    if (!is_array($dados)) {
        responder(400, ['message' => 'O conteúdo enviado é inválido.']);
    }

    switch ($acao) {
        case 'cadastrar_livro':
            cadastrarLivro($conexao, $dados);
            break;
        case 'cadastrar_cliente':
            cadastrarCliente($conexao, $dados);
            break;
        case 'cadastrar_funcionario':
            cadastrarFuncionario($conexao, $dados);
            break;
        case 'remover_livro':
            removerLivro($conexao, $dados);
            break;
        case 'remover_funcionario':
            removerFuncionario($conexao, $dados);
            break;
        case 'emprestar':
            emprestarLivro($conexao, $dados);
            break;
        case 'devolver':
            devolverLivro($conexao, $dados);
            break;
        default:
            responder(404, ['message' => 'Operação desconhecida.']);
    }
} catch (mysqli_sql_exception $erro) {
    error_log('Erro na API do bibliotecário: ' . $erro->getMessage());
    responder(500, ['message' => 'Não foi possível concluir a operação no banco de dados.']);
} catch (RuntimeException $erro) {
    error_log('Erro ao proteger dados na API do bibliotecário: ' . $erro->getMessage());
    responder(500, ['message' => 'Não foi possível proteger os dados enviados.']);
}

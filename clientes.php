<?php
include "conexao.php";

$clientes = $conexao->query("SELECT id, nome, telefone FROM leitores ORDER BY nome");
$emprestimosPorCliente = [];
$resultadoEmprestimos = $conexao->query("
    SELECT e.id_leitor, l.titulo, e.data_emprestimo, e.data_prevista,
           e.data_devolucao, e.status
    FROM emprestimos e
    JOIN livros l ON l.id = e.id_livro
    ORDER BY e.data_emprestimo DESC
");

if ($clientes === false || $resultadoEmprestimos === false) {
    http_response_code(500);
    exit('Não foi possível carregar os clientes e empréstimos.');
}

while ($emprestimo = $resultadoEmprestimos->fetch_assoc()) {
    $emprestimosPorCliente[$emprestimo['id_leitor']][] = $emprestimo;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes Cadastrados</title>
    <link rel="stylesheet" href="biblioteca-ui.css">
</head>
<body class="ui-theme ui-operacional ui-shell-page">
<div class="ui-layout">
<?php $sidebarAtivo = 'clientes.php'; include __DIR__ . '/sidebar.php'; ?>
<main class="ui-content">
    <header class="ui-topbar">
        <a class="ui-back" href="menu_gerente.php">&larr; Voltar ao menu</a>
        <span class="ui-breadcrumb">Gerência / Clientes</span>
    </header>

    <h1>Clientes cadastrados</h1>
    <p class="ui-muted">Consulte os clientes e o histórico de empréstimos de cada um.</p>

    <section class="ui-directory" aria-label="Lista de clientes">
        <?php if ($clientes->num_rows === 0): ?>
            <p class="ui-directory-empty">Ainda não há clientes cadastrados.</p>
        <?php else: ?>
            <?php while ($cliente = $clientes->fetch_assoc()): ?>
                <?php
                $emprestimos = $emprestimosPorCliente[$cliente['id']] ?? [];
                $ativos = count(array_filter($emprestimos, static function ($item) {
                    return $item['status'] === 'emprestado';
                }));
                ?>
                <details class="ui-directory-card" <?php echo $ativos > 0 ? 'open' : ''; ?>>
                    <summary>
                        <span class="ui-directory-person">
                            <strong><?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="ui-directory-meta">
                                <?php echo htmlspecialchars($cliente['telefone'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </span>
                        <span class="ui-directory-meta">
                            <?php echo count($emprestimos); ?> empréstimo(s) · <?php echo $ativos; ?> ativo(s)
                        </span>
                    </summary>
                    <div class="ui-directory-content">
                        <?php if ($emprestimos === []): ?>
                            <p class="ui-muted">Este cliente ainda não pegou livros emprestados.</p>
                        <?php else: ?>
                            <?php foreach ($emprestimos as $emprestimo): ?>
                                <?php
                                $atrasado = $emprestimo['status'] === 'emprestado'
                                    && $emprestimo['data_prevista'] < date('Y-m-d');
                                $status = $atrasado ? 'Atrasado' : ($emprestimo['status'] === 'emprestado' ? 'Emprestado' : 'Devolvido');
                                ?>
                                <div class="ui-loan-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($emprestimo['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="ui-directory-meta">
                                            Retirada: <?php echo date('d/m/Y', strtotime($emprestimo['data_emprestimo'])); ?>
                                            · Prazo: <?php echo date('d/m/Y', strtotime($emprestimo['data_prevista'])); ?>
                                            <?php if ($emprestimo['data_devolucao']): ?>
                                                · Devolvido: <?php echo date('d/m/Y', strtotime($emprestimo['data_devolucao'])); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <span class="ui-loan-status<?php echo $atrasado ? ' overdue' : ''; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endwhile; ?>
        <?php endif; ?>
    </section>
</main>
</div>
</body>
</html>
<?php
include "conexao.php";

$funcionarios = $conexao->query("SELECT id, nome, telefone FROM funcionario ORDER BY nome");

if ($funcionarios === false) {
    http_response_code(500);
    exit('Não foi possível carregar os funcionários.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funcionários Cadastrados</title>
    <link rel="stylesheet" href="biblioteca-ui.css">
</head>
<body class="ui-theme ui-operacional ui-shell-page">
<div class="ui-layout">
<?php $sidebarAtivo = 'funcionarios.php'; include __DIR__ . '/sidebar.php'; ?>
<main class="ui-content">
    <header class="ui-topbar">
        <a class="ui-back" href="menu_gerente.php">&larr; Voltar ao menu</a>
        <span class="ui-breadcrumb">Gerência / Funcionários</span>
    </header>

    <h1>Funcionários cadastrados</h1>
    <p class="ui-muted">Equipe registrada no sistema.</p>

    <?php if ($funcionarios->num_rows === 0): ?>
        <p class="ui-directory-empty">Ainda não há funcionários cadastrados.</p>
    <?php else: ?>
        <div class="ui-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Telefone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($funcionario = $funcionarios->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($funcionario['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($funcionario['telefone'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p style="margin-top: 20px;">
        <a class="ui-back" href="cad_funcionario.php?origem=gerente">＋ Adicionar funcionário</a>
    </p>
</main>
</div>
</body>
</html>
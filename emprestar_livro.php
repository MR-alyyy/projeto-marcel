<?php
include "conexao.php";

$mensagem = "";
$prazoDias = 7; // prazo padrão de devolução em dias
$menuVoltar = ($_GET['origem'] ?? '') === 'gerente' ? 'menu_gerente.php' : 'menu_fun.php';

if (isset($_POST['inserir'])) {

    $conexao->begin_transaction();

    $id_livro  = (int) trim($_POST['id_livro']);
    $id_leitor = (int) trim($_POST['id_leitor']);

    $erro = false;

    // ========================
    // VERIFICAR DISPONIBILIDADE (total - emprestados no momento)
    // ========================
    $stmt = $conexao->prepare("SELECT quantidade FROM livros WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $id_livro);
    $stmt->execute();
    $livro = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$livro) {
        $mensagem .= "<p class='erro'>Livro não encontrado.</p>";
        $erro = true;
    } else {
        $stmt = $conexao->prepare("SELECT COUNT(*) AS total FROM emprestimos WHERE id_livro = ? AND status = 'emprestado'");
        $stmt->bind_param("i", $id_livro);
        $stmt->execute();
        $emprestados = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $disponivel = $livro['quantidade'] - $emprestados;

        if ($disponivel <= 0) {
            $mensagem .= "<p class='erro'>Não há exemplares disponíveis para empréstimo.</p>";
            $erro = true;
        }
    }

    // ========================
    // VERIFICAR SE O LEITOR EXISTE
    // ========================
    if (!$erro) {
        $stmt = $conexao->prepare("SELECT id FROM leitores WHERE id = ?");
        $stmt->bind_param("i", $id_leitor);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $mensagem .= "<p class='erro'>Leitor não encontrado.</p>";
            $erro = true;
        }
        $stmt->close();
    }

    // ========================
    // REGISTRAR EMPRÉSTIMO
    // ========================
    if (!$erro) {

        $dataEmprestimo = date("Y-m-d H:i:s");
        $dataPrevista   = date("Y-m-d", strtotime("+$prazoDias days"));

        $stmt = $conexao->prepare("
            INSERT INTO emprestimos
            (id_livro, id_leitor, data_emprestimo, data_prevista, status)
            VALUES (?, ?, ?, ?, 'emprestado')
        ");
        $stmt->bind_param("iiss", $id_livro, $id_leitor, $dataEmprestimo, $dataPrevista);

        if ($stmt->execute()) {
            $mensagem = "<p class='sucesso'>Empréstimo registrado com sucesso! Devolução prevista para " . date("d/m/Y", strtotime($dataPrevista)) . ".</p>";
            $conexao->commit();
        } else {
            $mensagem = "<p class='erro'>Erro ao registrar empréstimo: " . $stmt->error . "</p>";
            $conexao->rollback();
        }

        $stmt->close();
    } else {
        $conexao->rollback();
    }
}

// ========================
// LISTAR LIVROS COM AO MENOS 1 EXEMPLAR DISPONÍVEL E LEITORES
// ========================
$livros = $conexao->query("
    SELECT l.id, l.titulo,
           l.quantidade - (
               SELECT COUNT(*) FROM emprestimos e
               WHERE e.id_livro = l.id AND e.status = 'emprestado'
           ) AS disponivel
    FROM livros l
    HAVING disponivel > 0
    ORDER BY l.titulo
");
$leitores = $conexao->query("SELECT id, nome FROM leitores ORDER BY nome");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Emprestar Livro</title>
<link rel="stylesheet" href="biblioteca-ui.css">
<script src="biblioteca-ui.js" defer></script>
</head>

<body class="ui-theme ui-operacional ui-shell-page">

<div class="ui-layout">
<?php $sidebarAtivo = 'emprestar_livro.php'; include __DIR__ . '/sidebar.php'; ?>
<main class="ui-content">
    <header class="ui-topbar">
        <a class="ui-back" href="<?php echo $menuVoltar; ?>">&larr; Voltar ao menu</a>
        <span class="ui-breadcrumb">Biblioteca / Empréstimos</span>
    </header>

    <section class="ui-card">
    <h1>Emprestar Livro</h1>

    <?php echo $mensagem; ?>

    <form method="post">

        <label>Livro</label>
        <select name="id_livro" required>
            <option value="">Selecione o livro</option>
            <?php while ($l = $livros->fetch_assoc()): ?>
                <option value="<?php echo $l['id']; ?>">
                    <?php echo htmlspecialchars($l['titulo']); ?> (<?php echo $l['disponivel']; ?> disponível(is))
                </option>
            <?php endwhile; ?>
        </select>

        <label>Leitor</label>
        <select name="id_leitor" required>
            <option value="">Selecione o leitor</option>
            <?php while ($le = $leitores->fetch_assoc()): ?>
                <option value="<?php echo $le['id']; ?>"><?php echo htmlspecialchars($le['nome']); ?></option>
            <?php endwhile; ?>
        </select>

        <button type="submit" name="inserir">Registrar Empréstimo</button>

    </form>


    </section>
</main>
</div>

</body>
</html>

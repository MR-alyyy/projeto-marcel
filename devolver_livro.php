<?php
include "conexao.php";

$mensagem = "";
$valorMultaPorDia = 2.00; // R$ 2,00 por dia de atraso
$menuVoltar = ($_GET['origem'] ?? '') === 'gerente' ? 'menu_gerente.php' : 'menu_fun.php';

if (isset($_POST['devolver'])) {

    $id_emprestimo = (int) trim($_POST['id_emprestimo']);

    // ========================
    // BUSCAR DADOS DO EMPRÉSTIMO
    // ========================
    $stmt = $conexao->prepare("SELECT id_livro, data_prevista FROM emprestimos WHERE id = ? AND status = 'emprestado'");
    $stmt->bind_param("i", $id_emprestimo);
    $stmt->execute();
    $emprestimo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$emprestimo) {

        $mensagem = "<p class='erro'>Empréstimo não encontrado ou já devolvido.</p>";

    } else {

        $hoje = new DateTimeImmutable('today');
        $dataPrevista = new DateTimeImmutable($emprestimo['data_prevista']);

        $multa = 0.00;

        if ($hoje > $dataPrevista) {
            $diasAtraso = $dataPrevista->diff($hoje)->days;
            $multa = $diasAtraso * $valorMultaPorDia;
        }

        $dataDevolucao = date("Y-m-d H:i:s");

        // ========================
        // ATUALIZAR EMPRÉSTIMO (não mexe mais em livros.quantidade,
        // pois ela agora representa o total fixo do título)
        // ========================
        $stmt = $conexao->prepare("
            UPDATE emprestimos
            SET data_devolucao = ?, multa = ?, status = 'devolvido'
            WHERE id = ? AND status = 'emprestado'
        ");
        $stmt->bind_param("sdi", $dataDevolucao, $multa, $id_emprestimo);

        if ($stmt->execute() && $stmt->affected_rows === 1) {

            if ($multa > 0) {
                $mensagem = "<p class='erro'>Devolução registrada com atraso. Multa: R$ " . number_format($multa, 2, ',', '.') . "</p>";
            } else {
                $mensagem = "<p class='sucesso'>Devolução registrada dentro do prazo. Sem multa.</p>";
            }

        } elseif ($stmt->error !== '') {
            $mensagem = "<p class='erro'>Erro ao registrar devolução: " . $stmt->error . "</p>";
        } else {
            $mensagem = "<p class='erro'>Este empréstimo já foi devolvido.</p>";
        }

        $stmt->close();
    }
}

// ========================
// LISTAR EMPRÉSTIMOS EM ABERTO
// ========================
$emprestimos = $conexao->query("
    SELECT e.id, l.titulo, le.nome, e.data_emprestimo, e.data_prevista
    FROM emprestimos e
    JOIN livros l ON l.id = e.id_livro
    JOIN leitores le ON le.id = e.id_leitor
    WHERE e.status = 'emprestado'
    ORDER BY e.data_prevista ASC
");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Devolver Livro</title>
<link rel="stylesheet" href="biblioteca-ui.css">
<script src="biblioteca-ui.js" defer></script>
</head>

<body class="ui-theme ui-operacional ui-shell-page">

<div class="ui-layout">
<?php $sidebarAtivo = 'devolver_livro.php'; include __DIR__ . '/sidebar.php'; ?>
<main class="ui-content">
    <header class="ui-topbar">
        <a class="ui-back" href="<?php echo $menuVoltar; ?>">&larr; Voltar ao menu</a>
        <span class="ui-breadcrumb">Biblioteca / Empréstimos</span>
    </header>

    <section class="ui-card">
    <h1>Devolver Livro</h1>

    <?php echo $mensagem; ?>

    <div class="ui-table-wrap">
    <table>
        <tr>
            <th>Livro</th>
            <th>Leitor</th>
            <th>Emprestado em</th>
            <th>Devolução prevista</th>
            <th>Ação</th>
        </tr>

        <?php while ($e = $emprestimos->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($e['titulo']); ?></td>
            <td><?php echo htmlspecialchars($e['nome']); ?></td>
            <td><?php echo date("d/m/Y", strtotime($e['data_emprestimo'])); ?></td>
            <td><?php echo date("d/m/Y", strtotime($e['data_prevista'])); ?></td>
            <td>
                <form method="post" style="display:inline;" data-confirm="Confirmar a devolução deste exemplar?">
                    <input type="hidden" name="id_emprestimo" value="<?php echo $e['id']; ?>">
                    <button type="submit" name="devolver">Registrar devolução</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    </div>

    </section>
</main>
</div>

</body>
</html>

<?php
$menuVoltar = ($_GET['origem'] ?? '') === 'gerente' ? 'menu_gerente.php' : 'menu_fun.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Livros</title>
    <link rel="stylesheet" href="biblioteca-ui.css">
</head>

<body class="ui-theme ui-operacional ui-shell-page">
<div class="ui-layout">
<?php $sidebarAtivo = 'buscar.php'; include __DIR__ . '/sidebar.php'; ?>
<main class="ui-content">
<header class="ui-topbar">
    <a class="ui-back" href="<?php echo $menuVoltar; ?>">&larr; Voltar ao menu</a>
    <span class="ui-breadcrumb">Biblioteca / Busca</span>
</header>

<h1>Buscar livros</h1>
<section class="ui-card">
<form class="ui-search-form" method="post" action="">
    <label for="buscar-titulo">Título do livro</label>
    <input id="buscar-titulo" name="Buscar" type="search" value="<?php echo htmlspecialchars((string)($_POST['Buscar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
    <button type="submit">Pesquisar</button>
</form>
<div class="ui-result-list">

<?php

if (isset($_POST['Buscar'])) {

    include "conexao.php";

    $buscar = trim($_POST['Buscar']);

    $stmt = $conexao->prepare("SELECT * FROM livros WHERE titulo LIKE ?");
    $termo = "%{$buscar}%";
    $stmt->bind_param("s", $termo);
    $stmt->execute();
    $sql = $stmt->get_result();

    $row = mysqli_num_rows($sql);

    if ($row > 0) {

        while ($linha = mysqli_fetch_assoc($sql)) {

            $status = $linha['status'];
            $titulo = $linha['titulo'];
            $autor = $linha['autor'];
            $genero = $linha['genero'];
            $quantidade = $linha['quantidade'];
            $descricao = $linha['descricao'];

            echo '<article class="ui-result">';
            echo '<h2>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h2>';
            echo '<dl>';
            echo '<div><dt>Autor</dt><dd>' . htmlspecialchars($autor, ENT_QUOTES, 'UTF-8') . '</dd></div>';
            echo '<div><dt>Gênero</dt><dd>' . htmlspecialchars($genero, ENT_QUOTES, 'UTF-8') . '</dd></div>';
            echo '<div><dt>Status</dt><dd>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</dd></div>';
            echo '<div><dt>Quantidade</dt><dd>' . (int)$quantidade . '</dd></div>';
            echo '<div><dt>Descrição</dt><dd>' . htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') . '</dd></div>';
            echo '</dl></article>';

        }

    } else {

        echo '<p class="ui-muted">Nenhum livro encontrado com esse título.</p>';

    }

    $stmt->close();
}

?>
</div>
</section>
</main>
</div>
</body>
</html>

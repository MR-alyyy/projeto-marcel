<?php
$sidebarManager = ($_GET['origem'] ?? '') === 'gerente'
    || basename($_SERVER['PHP_SELF']) === 'menu_gerente.php';
$sidebarCurrent = $sidebarAtivo ?? basename($_SERVER['PHP_SELF']);

if ($sidebarManager) {
    $sidebarLinks = [
        ['page' => 'menu_gerente.php', 'href' => 'menu_gerente.php', 'icon' => '⌂', 'label' => 'Menu gerente'],
        ['page' => 'cad_livros.php', 'href' => 'cad_livros.php?origem=gerente', 'icon' => '＋', 'label' => 'Cadastrar livro'],
        ['page' => 'cad_leitor.php', 'href' => 'cad_leitor.php?origem=gerente', 'icon' => '♙', 'label' => 'Cadastrar cliente'],
        ['page' => 'cad_funcionario.php', 'href' => 'cad_funcionario.php?origem=gerente', 'icon' => '♙', 'label' => 'Adicionar funcionário'],
        ['page' => 'clientes.php', 'href' => 'clientes.php?origem=gerente', 'icon' => '♧', 'label' => 'Clientes cadastrados'],
        ['page' => 'funcionarios.php', 'href' => 'funcionarios.php?origem=gerente', 'icon' => '♙', 'label' => 'Funcionários cadastrados'],
        ['page' => 'gerenciamento.php', 'href' => 'gerenciamento.php?origem=gerente', 'icon' => '▤', 'label' => 'Gerenciamento'],
        ['page' => 'buscar.php', 'href' => 'buscar.php?origem=gerente', 'icon' => '⌕', 'label' => 'Buscar livros'],
        ['page' => 'emprestar_livro.php', 'href' => 'emprestar_livro.php?origem=gerente', 'icon' => '⇥', 'label' => 'Emprestar livro'],
        ['page' => 'devolver_livro.php', 'href' => 'devolver_livro.php?origem=gerente', 'icon' => '↩', 'label' => 'Devolver livro'],
    ];
    $sidebarTitle = 'Área do gerente';
} else {
    $sidebarLinks = [
        ['page' => 'menu_fun.php', 'href' => 'menu_fun.php', 'icon' => '⌂', 'label' => 'Menu funcionário'],
        ['page' => 'cad_livros.php', 'href' => 'cad_livros.php', 'icon' => '＋', 'label' => 'Cadastrar livro'],
        ['page' => 'cad_leitor.php', 'href' => 'cad_leitor.php?origem=funcionario', 'icon' => '♙', 'label' => 'Cadastrar cliente'],
        ['page' => 'gerenciamento.php', 'href' => 'gerenciamento.php', 'icon' => '▤', 'label' => 'Gerenciamento'],
        ['page' => 'buscar.php', 'href' => 'buscar.php', 'icon' => '⌕', 'label' => 'Buscar livros'],
        ['page' => 'emprestar_livro.php', 'href' => 'emprestar_livro.php', 'icon' => '⇥', 'label' => 'Emprestar livro'],
        ['page' => 'devolver_livro.php', 'href' => 'devolver_livro.php', 'icon' => '↩', 'label' => 'Devolver livro'],
    ];
    $sidebarTitle = 'Área do funcionário';
}
?>
<aside class="ui-sidebar" aria-label="Navegação da biblioteca">
    <a class="ui-sidebar-brand" href="<?php echo $sidebarManager ? 'menu_gerente.php' : 'menu_fun.php'; ?>">
        <span class="ui-sidebar-mark" aria-hidden="true">F</span>
        <span>Fichário</span>
    </a>
    <nav aria-label="<?php echo htmlspecialchars($sidebarTitle, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="ui-sidebar-label"><?php echo htmlspecialchars($sidebarTitle, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php foreach ($sidebarLinks as $link): ?>
            <?php $active = $sidebarCurrent === $link['page']; ?>
            <a class="ui-sidebar-link<?php echo $active ? ' active' : ''; ?>"
                href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo $active ? 'aria-current="page"' : ''; ?>>
                <span class="ui-sidebar-icon" aria-hidden="true"><?php echo $link['icon']; ?></span>
                <span><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="ui-sidebar-footer">Sistema da biblioteca<br>Acervo e circulação</div>
</aside>
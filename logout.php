<?php
session_start();
$loginPorTipo = [
    'bibliotecario' => 'login_bibliotecario.php',
    'funcionario' => 'login_funcionario.php',
    'leitor' => 'login_leitor.php',
];
$destino = $loginPorTipo[$_SESSION['tipo'] ?? ''] ?? 'login_funcionario.php';
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
header('Location: ' . $destino);
exit;
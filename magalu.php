<?php
require_once __DIR__ . '/helper.php';

$magalu = getCotacao('MGLU3');

if ($magalu !== null) {
    echo "<h1>A cotação de Magazine Luiza (MGLU3) é " . formatarPreco($magalu, 2) . "</h1>" . PHP_EOL;
} else {
    echo "<h1>Não foi possível obter a cotação de Magazine Luiza</h1>" . PHP_EOL;
}

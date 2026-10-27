<?php
require_once __DIR__ . '/helper.php';

$dolar = getCotacao('USD');

if ($dolar !== null) {
    echo "<h1>Cotação do Dólar é " . formatarPreco($dolar, 2) . "</h1>" . PHP_EOL;
} else {
    echo "<h1>Não foi possível obter a cotação do Dólar</h1>" . PHP_EOL;
}

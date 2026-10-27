<?php
require_once __DIR__ . '/helper.php';

$bitcoin = getCotacao('BTC');

if ($bitcoin !== null) {
    echo "<h1>Cotação do Bitcoin é " . formatarPreco($bitcoin, 2) . "</h1>" . PHP_EOL;
} else {
    echo "<h1>Não foi possível obter a cotação do Bitcoin</h1>" . PHP_EOL;
}

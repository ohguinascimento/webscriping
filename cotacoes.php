<?php
require_once __DIR__ . '/helper.php';

$dolar = getCotacao('USD');
$euro = getCotacao('EUR');
$bitcoin = getCotacao('BTC');
$ethereum = getCotacao('ETH');
$solana = getCotacao('SOL');
$magalu = getCotacao('MGLU3');
$petrobras = getCotacao('PETR4');
$vale = getCotacao('VALE3');

echo "<h1>📊 Painel de Cotações em Tempo Real</h1>" . PHP_EOL;
if ($dolar !== null) echo "<h2>Cotação do Dólar: " . formatarPreco($dolar) . "</h2>" . PHP_EOL;
if ($euro !== null) echo "<h2>Cotação do Euro: " . formatarPreco($euro) . "</h2>" . PHP_EOL;
if ($bitcoin !== null) echo "<h2>Cotação do Bitcoin: " . formatarPreco($bitcoin) . "</h2>" . PHP_EOL;
if ($ethereum !== null) echo "<h2>Cotação do Ethereum: " . formatarPreco($ethereum) . "</h2>" . PHP_EOL;
if ($solana !== null) echo "<h2>Cotação do Solana: " . formatarPreco($solana) . "</h2>" . PHP_EOL;
if ($magalu !== null) echo "<h2>Cotação da Magazine Luiza (MGLU3): " . formatarPreco($magalu) . "</h2>" . PHP_EOL;
if ($petrobras !== null) echo "<h2>Cotação da Petrobras (PETR4): " . formatarPreco($petrobras) . "</h2>" . PHP_EOL;
if ($vale !== null) echo "<h2>Cotação da Vale (VALE3): " . formatarPreco($vale) . "</h2>" . PHP_EOL;

echo "<hr><p><a href='index.php'>Acessar Dashboard Histórico e Gráficos</a></p>" . PHP_EOL;

<?php
require_once __DIR__ . '/helper.php';

$pdo = App\Database::getConnection();
$collector = new App\PriceCollector();

// Get ETH & USD
$real = getCotacao('USD') ?? 1.0;
$ethPrice = getCotacao('ETH') ?? 0;

// Query holdings from database
$sql = "
    SELECT 
        w.id,
        w.wallet_name,
        w.amount,
        w.notes,
        a.symbol,
        a.name,
        ph.price as current_price,
        (w.amount * ph.price) as current_total_brl
    FROM wallet_holdings w
    JOIN assets a ON a.id = w.asset_id
    LEFT JOIN price_history ph ON ph.id = (
        SELECT id FROM price_history 
        WHERE asset_id = a.id 
        ORDER BY recorded_at DESC, id DESC 
        LIMIT 1
    )
";
$holdings = $pdo->query($sql)->fetchAll();
$totalGeral = 0;

echo "<h1>💼 Relatório de Carteira de Criptoativos & Investimentos</h1>" . PHP_EOL;
echo "<p>Dólar Base: " . formatarPreco($real) . " | ETH Atual: " . formatarPreco($ethPrice) . "</p>" . PHP_EOL;

if (empty($holdings)) {
    echo "<p>Nenhuma posição cadastrada na carteira.</p>";
} else {
    echo "<ul>";
    foreach ($holdings as $pos) {
        $subtotal = (float)$pos['current_total_brl'];
        $totalGeral += $subtotal;
        echo "<li><strong>{$pos['wallet_name']}</strong>: {$pos['amount']} {$pos['symbol']} (" . formatarPreco($pos['current_price']) . " cada) = <strong>" . formatarPreco($subtotal) . "</strong></li>";
    }
    echo "</ul>";
}

echo "<h2>Saldo Total Estimado: " . formatarPreco($totalGeral) . "</h2>" . PHP_EOL;
echo "<hr><p><a href='index.php'>Voltar ao Painel Histórico</a></p>";

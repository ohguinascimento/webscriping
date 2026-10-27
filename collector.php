<?php
/**
 * CLI Price Collector
 * Executável via terminal ou agendador de tarefas (Cron / Windows Task Scheduler)
 * Exemplo: php collector.php
 */

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Scraper.php';
require_once __DIR__ . '/src/PriceCollector.php';

use App\Database;
use App\PriceCollector;

$isCli = (php_sapi_name() === 'cli');
$isJson = in_array('--json', $argv ?? []);
$isSeed = in_array('--seed', $argv ?? []);

try {
    $collector = new PriceCollector();

    if ($isSeed) {
        if (!$isJson) echo "🚀 Verificando e importando dados históricos (30 dias)...\n";
        $collector->backfillHistoryIfEmpty();
        if (!$isJson) echo "✅ Importação histórica concluída!\n";
    }

    if (!$isJson) {
        echo "====================================================\n";
        echo "   📊 COLETOR DE PREÇOS HISTÓRICOS - " . date('Y-m-d H:i:s') . "\n";
        echo "====================================================\n";
    }

    $results = $collector->collectAll();

    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'timestamp' => date('c'),
            'data' => $results
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    $successCount = 0;
    $errorCount = 0;

    foreach ($results as $symbol => $item) {
        if (!empty($item['success'])) {
            $successCount++;
            $varSign = ($item['pctChange'] >= 0) ? '+' : '';
            $priceFmt = number_format($item['price'], 2, ',', '.');
            $varFmt = $varSign . number_format($item['pctChange'], 2, ',', '.') . '%';
            printf(" [✓] %-7s | %-20s | R$ %12s | Var: %8s\n", $symbol, substr($item['name'] ?? '', 0, 20), $priceFmt, $varFmt);
        } else {
            $errorCount++;
            printf(" [✗] %-7s | ERRO: %s\n", $symbol, $item['error'] ?? 'Desconhecido');
        }
    }

    echo "----------------------------------------------------\n";
    echo " Sucesso: {$successCount} | Falhas: {$errorCount} | Total gravado no banco de dados.\n";
    echo "====================================================\n";
    exit(0);

} catch (Exception $e) {
    if ($isJson) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } else {
        echo "❌ ERRO CRÍTICO NO COLETOR: " . $e->getMessage() . "\n";
    }
    exit(1);
}

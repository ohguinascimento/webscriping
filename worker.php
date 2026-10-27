<?php
/**
 * Daemon / Worker de Coleta Periódica
 * Roda continuamente em segundo plano executando a coleta no intervalo configurado.
 * 
 * Uso:
 *   php worker.php               (padrão: a cada 60 minutos)
 *   php worker.php --interval=15 (a cada 15 minutos)
 */

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Scraper.php';
require_once __DIR__ . '/src/PriceCollector.php';

use App\PriceCollector;

// Captura argumento de intervalo em minutos (padrão 60 min)
$intervalMinutes = 60;
foreach ($argv as $arg) {
    if (strpos($arg, '--interval=') === 0) {
        $intervalMinutes = max(1, (int)substr($arg, 11));
    }
}

$intervalSeconds = $intervalMinutes * 60;
$logFile = __DIR__ . '/data/cron.log';

echo "========================================================\n";
echo " 🤖 PriceTracker Pro — Worker Contínuo em Execução\n";
echo " Intervalo configurado: a cada {$intervalMinutes} minuto(s)\n";
echo " Arquivo de log: {$logFile}\n";
echo " Pressione Ctrl+C para encerrar o worker.\n";
echo "========================================================\n\n";

$collector = new PriceCollector();

while (true) {
    $now = date('Y-m-d H:i:s');
    $startMsg = "[{$now}] Iniciando ciclo de coleta...";
    echo $startMsg . "\n";
    file_put_contents($logFile, $startMsg . PHP_EOL, FILE_APPEND);

    try {
        $results = $collector->collectAll();
        $success = 0;
        $failed = 0;

        foreach ($results as $sym => $res) {
            if (!empty($res['success'])) {
                $success++;
            } else {
                $failed++;
            }
        }

        $summary = "[{$now}] Coleta finalizada: {$success} ativos salvos com sucesso, {$failed} falhas.";
        echo $summary . "\n";
        file_put_contents($logFile, $summary . PHP_EOL, FILE_APPEND);

    } catch (Exception $e) {
        $err = "[{$now}] ERRO CRÍTICO no ciclo de coleta: " . $e->getMessage();
        echo $err . "\n";
        file_put_contents($logFile, $err . PHP_EOL, FILE_APPEND);
    }

    $nextRun = date('H:i:s', time() + $intervalSeconds);
    echo "💤 Dormindo... Próxima coleta agendada para às {$nextRun}.\n\n";

    sleep($intervalSeconds);
}

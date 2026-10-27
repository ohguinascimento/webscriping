<?php
/**
 * REST API for Historical Price System
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Scraper.php';
require_once __DIR__ . '/src/PriceCollector.php';

use App\Database;
use App\PriceCollector;

$collector = new PriceCollector();
$action = $_GET['action'] ?? $_POST['action'] ?? 'overview';

try {
    switch ($action) {
        case 'overview':
            header('Content-Type: application/json');
            $data = $collector->getAssetsOverview();
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'history':
            header('Content-Type: application/json');
            $symbol = $_GET['symbol'] ?? 'USD';
            $days = isset($_GET['days']) ? (int)$_GET['days'] : null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
            $data = $collector->getHistory($symbol, $limit, $days);
            $stats = $collector->getStatistics($symbol);
            echo json_encode([
                'status' => 'success',
                'symbol' => strtoupper($symbol),
                'stats' => $stats,
                'data' => $data
            ]);
            break;

        case 'stats':
            header('Content-Type: application/json');
            $symbol = $_GET['symbol'] ?? 'USD';
            $stats = $collector->getStatistics($symbol);
            echo json_encode(['status' => 'success', 'data' => $stats]);
            break;

        case 'collect':
            header('Content-Type: application/json');
            $results = $collector->collectAll();
            echo json_encode(['status' => 'success', 'data' => $results]);
            break;

        case 'export':
            $symbol = $_GET['symbol'] ?? 'ALL';
            $filename = "historico_precos_" . strtolower($symbol) . "_" . date('Ymd_His') . ".csv";

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $out = fopen('php://output', 'w');
            // Add UTF-8 BOM for Excel compatibility
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, ['ID', 'Ativo', 'Nome', 'Categoria', 'Preço (BRL)', 'Máx 24h', 'Mín 24h', 'Variação %', 'Fonte', 'Data Coleta'], ';');

            $pdo = Database::getConnection();
            if (strtoupper($symbol) === 'ALL') {
                $stmt = $pdo->query("
                    SELECT ph.id, a.symbol, a.name, a.category, ph.price, ph.high_24h, ph.low_24h, ph.change_pct_24h, ph.source, ph.recorded_at
                    FROM price_history ph
                    JOIN assets a ON a.id = ph.asset_id
                    ORDER BY ph.recorded_at DESC
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT ph.id, a.symbol, a.name, a.category, ph.price, ph.high_24h, ph.low_24h, ph.change_pct_24h, ph.source, ph.recorded_at
                    FROM price_history ph
                    JOIN assets a ON a.id = ph.asset_id
                    WHERE UPPER(a.symbol) = UPPER(?)
                    ORDER BY ph.recorded_at DESC
                ");
                $stmt->execute([$symbol]);
            }

            while ($row = $stmt->fetch()) {
                fputcsv($out, [
                    $row['id'],
                    $row['symbol'],
                    $row['name'],
                    $row['category'],
                    number_format($row['price'], 4, ',', '.'),
                    $row['high_24h'] ? number_format($row['high_24h'], 4, ',', '.') : '-',
                    $row['low_24h'] ? number_format($row['low_24h'], 4, ',', '.') : '-',
                    $row['change_pct_24h'] ? number_format($row['change_pct_24h'], 2, ',', '.') . '%' : '-',
                    $row['source'],
                    $row['recorded_at']
                ], ';');
            }
            fclose($out);
            exit;

        case 'wallet':
            header('Content-Type: application/json');
            $pdo = Database::getConnection();
            $sql = "
                SELECT 
                    w.id,
                    w.wallet_name,
                    w.amount,
                    w.notes,
                    a.symbol,
                    a.name,
                    ph.price as current_price,
                    (w.amount * ph.price) as current_total_brl,
                    ph.recorded_at as price_updated_at
                FROM wallet_holdings w
                JOIN assets a ON a.id = w.asset_id
                LEFT JOIN price_history ph ON ph.id = (
                    SELECT id FROM price_history 
                    WHERE asset_id = a.id 
                    ORDER BY recorded_at DESC, id DESC 
                    LIMIT 1
                )
                ORDER BY current_total_brl DESC
            ";
            $holdings = $pdo->query($sql)->fetchAll();
            $totalPortfolioBrl = 0;
            foreach ($holdings as $h) {
                $totalPortfolioBrl += (float)($h['current_total_brl'] ?? 0);
            }
            echo json_encode([
                'status' => 'success',
                'total_brl' => $totalPortfolioBrl,
                'data' => $holdings
            ]);
            break;

        case 'add_holding':
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $symbol = trim($input['symbol'] ?? '');
            $amount = (float)($input['amount'] ?? 0);
            $walletName = trim($input['wallet_name'] ?? 'Minha Carteira');
            $notes = trim($input['notes'] ?? '');

            if (empty($symbol) || $amount <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Símbolo e quantidade positiva são obrigatórios.']);
                exit;
            }

            $asset = $collector->getAssetBySymbol($symbol);
            if (!$asset) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => "Ativo {$symbol} não encontrado."]);
                exit;
            }

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO wallet_holdings (asset_id, wallet_name, amount, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$asset['id'], $walletName, $amount, $notes]);
            echo json_encode(['status' => 'success', 'message' => 'Posição adicionada com sucesso!']);
            break;

        case 'add_asset':
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $symbol = strtoupper(trim($input['symbol'] ?? ''));
            $name = trim($input['name'] ?? '');
            $category = trim($input['category'] ?? 'stock');
            $fetchType = trim($input['fetch_type'] ?? 'yahoo');
            $config = $input['fetch_config'] ?? [];

            if (empty($symbol) || empty($name)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Símbolo e Nome são obrigatórios.']);
                exit;
            }

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO assets (symbol, name, category, currency, fetch_type, fetch_config)
                VALUES (?, ?, ?, 'BRL', ?, ?)
            ");
            $stmt->execute([
                $symbol,
                $name,
                $category,
                $fetchType,
                is_string($config) ? $config : json_encode($config)
            ]);

            // Attempt first fetch
            $collector->collectAll();

            echo json_encode(['status' => 'success', 'message' => "Ativo {$symbol} cadastrado com sucesso!"]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Ação não reconhecida.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

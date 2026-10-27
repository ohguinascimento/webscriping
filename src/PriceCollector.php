<?php

namespace App;

use PDO;
use Exception;

class PriceCollector
{
    private PDO $pdo;
    private Scraper $scraper;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->scraper = new Scraper();
    }

    /**
     * Run full collection cycle for all active assets
     */
    public function collectAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM assets WHERE is_active = 1");
        $assets = $stmt->fetchAll();
        $results = [];

        // Group awesomeapi assets for batch collection
        $awesomePairs = [];
        $awesomeMap = [];
        $otherAssets = [];

        foreach ($assets as $asset) {
            if ($asset['fetch_type'] === 'awesomeapi') {
                $cfg = json_decode($asset['fetch_config'] ?? '{}', true);
                $pair = $cfg['pair'] ?? ($asset['symbol'] . '-BRL');
                $key = $cfg['key'] ?? str_replace('-', '', $pair);
                $awesomePairs[] = $pair;
                $awesomeMap[$key] = $asset;
            } else {
                $otherAssets[] = $asset;
            }
        }

        // 1. Fetch AwesomeAPI batch
        if (!empty($awesomePairs)) {
            $awesomeResults = $this->fetchAwesomeBatch($awesomePairs, $awesomeMap);
            $results = array_merge($results, $awesomeResults);
        }

        // 2. Fetch other assets (Yahoo Finance or Custom Scraper)
        foreach ($otherAssets as $asset) {
            try {
                if ($asset['fetch_type'] === 'yahoo') {
                    $res = $this->fetchYahoo($asset);
                } elseif ($asset['fetch_type'] === 'scraper') {
                    $res = $this->fetchCustomScraper($asset);
                } else {
                    $res = ['success' => false, 'error' => 'Tipo de fetch desconhecido'];
                }
                $results[$asset['symbol']] = $res;
            } catch (Exception $e) {
                $results[$asset['symbol']] = [
                    'success' => false,
                    'symbol' => $asset['symbol'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Fetch batch quotes from AwesomeAPI
     */
    private function fetchAwesomeBatch(array $pairs, array $map): array
    {
        $url = "https://economia.awesomeapi.com.br/json/last/" . implode(',', $pairs);
        $results = [];

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new Exception("Resposta inválida da AwesomeAPI");
            }

            foreach ($data as $key => $item) {
                if (!isset($map[$key])) {
                    continue;
                }

                $asset = $map[$key];
                $price = (float)($item['bid'] ?? 0);
                $ask = (float)($item['ask'] ?? 0);
                $high = (float)($item['high'] ?? 0);
                $low = (float)($item['low'] ?? 0);
                $pctChange = (float)($item['pctChange'] ?? 0);
                $timestamp = isset($item['timestamp']) ? date('Y-m-d H:i:s', (int)$item['timestamp']) : date('Y-m-d H:i:s');

                $this->insertPriceRecord(
                    $asset['id'],
                    $price,
                    $price,
                    $ask,
                    $high,
                    $low,
                    $pctChange,
                    'AwesomeAPI',
                    $timestamp
                );

                $results[$asset['symbol']] = [
                    'success' => true,
                    'symbol' => $asset['symbol'],
                    'name' => $asset['name'],
                    'price' => $price,
                    'pctChange' => $pctChange,
                    'timestamp' => $timestamp
                ];
            }
        } catch (Exception $e) {
            foreach ($map as $key => $asset) {
                $results[$asset['symbol']] = [
                    'success' => false,
                    'symbol' => $asset['symbol'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Fetch stock quotes from Yahoo Finance Chart API
     */
    private function fetchYahoo(array $asset): array
    {
        $cfg = json_decode($asset['fetch_config'] ?? '{}', true);
        $ticker = $cfg['ticker'] ?? ($asset['symbol'] . '.SA');
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}?interval=1d&range=1d";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$res) {
            throw new Exception("Falha ao consultar Yahoo Finance HTTP {$httpCode}");
        }

        $json = json_decode($res, true);
        $meta = $json['chart']['result'][0]['meta'] ?? null;
        if (!$meta) {
            throw new Exception("Estrutura inesperada de dados do Yahoo Finance");
        }

        $price = (float)($meta['regularMarketPrice'] ?? 0);
        $prevClose = (float)($meta['chartPreviousClose'] ?? $price);
        $pctChange = $prevClose > 0 ? (($price - $prevClose) / $prevClose) * 100 : 0.0;
        $high = (float)($meta['regularMarketDayHigh'] ?? $price);
        $low = (float)($meta['regularMarketDayLow'] ?? $price);
        $timestamp = date('Y-m-d H:i:s');

        $this->insertPriceRecord(
            $asset['id'],
            $price,
            $price,
            $price,
            $high,
            $low,
            $pctChange,
            'Yahoo Finance',
            $timestamp
        );

        return [
            'success' => true,
            'symbol' => $asset['symbol'],
            'name' => $asset['name'],
            'price' => $price,
            'pctChange' => $pctChange,
            'timestamp' => $timestamp
        ];
    }

    /**
     * Fetch quote using custom web scraper (XPath/Regex)
     */
    private function fetchCustomScraper(array $asset): array
    {
        $cfg = json_decode($asset['fetch_config'] ?? '{}', true);
        $url = $cfg['url'] ?? '';
        $xpath = $cfg['xpath'] ?? '';

        if (empty($url) || empty($xpath)) {
            throw new Exception("Configuração de scraper incompleta (url e xpath obrigatórios)");
        }

        $html = $this->scraper->fetchHtml($url);
        $rawText = $this->scraper->queryXPath($html, $xpath);

        if ($rawText === null) {
            throw new Exception("Elemento XPath não encontrado na página {$url}");
        }

        $price = Scraper::parseCurrency($rawText);
        if ($price === null) {
            throw new Exception("Não foi possível extrair valor numérico de: '{$rawText}'");
        }

        $timestamp = date('Y-m-d H:i:s');

        // Calculate change compared to last record
        $last = $this->getLatestPrice($asset['id']);
        $pctChange = 0.0;
        if ($last && $last['price'] > 0) {
            $pctChange = (($price - $last['price']) / $last['price']) * 100;
        }

        $this->insertPriceRecord(
            $asset['id'],
            $price,
            $price,
            $price,
            $price,
            $price,
            $pctChange,
            'Web Scraper',
            $timestamp
        );

        return [
            'success' => true,
            'symbol' => $asset['symbol'],
            'name' => $asset['name'],
            'price' => $price,
            'pctChange' => $pctChange,
            'timestamp' => $timestamp
        ];
    }

    /**
     * Insert a recorded price into the history table
     */
    public function insertPriceRecord(
        int $assetId,
        float $price,
        ?float $bid,
        ?float $ask,
        ?float $high,
        ?float $low,
        ?float $changePct,
        string $source,
        string $recordedAt
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO price_history 
            (asset_id, price, bid, ask, high_24h, low_24h, change_pct_24h, source, recorded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$assetId, $price, $bid, $ask, $high, $low, $changePct, $source, $recordedAt]);
    }

    /**
     * Seed initial 30 days of historical data if history is empty
     */
    public function backfillHistoryIfEmpty(): void
    {
        $count = $this->pdo->query("SELECT COUNT(*) FROM price_history")->fetchColumn();
        if ($count > 10) {
            return; // Already has history
        }

        $stmt = $this->pdo->query("SELECT * FROM assets WHERE is_active = 1");
        $assets = $stmt->fetchAll();

        foreach ($assets as $asset) {
            if ($asset['fetch_type'] === 'awesomeapi') {
                $cfg = json_decode($asset['fetch_config'] ?? '{}', true);
                $pair = $cfg['pair'] ?? ($asset['symbol'] . '-BRL');
                $url = "https://economia.awesomeapi.com.br/json/daily/{$pair}/30";

                $res = @file_get_contents($url);
                if ($res) {
                    $daily = json_decode($res, true);
                    if (is_array($daily)) {
                        // Reverse so oldest is inserted first
                        $daily = array_reverse($daily);
                        foreach ($daily as $day) {
                            $price = (float)($day['bid'] ?? 0);
                            $ask = (float)($day['ask'] ?? 0);
                            $high = (float)($day['high'] ?? 0);
                            $low = (float)($day['low'] ?? 0);
                            $pct = (float)($day['pctChange'] ?? 0);
                            $date = date('Y-m-d H:i:s', (int)($day['timestamp'] ?? time()));

                            $this->insertPriceRecord($asset['id'], $price, $price, $ask, $high, $low, $pct, 'AwesomeAPI Historical', $date);
                        }
                    }
                }
            } elseif ($asset['fetch_type'] === 'yahoo') {
                $cfg = json_decode($asset['fetch_config'] ?? '{}', true);
                $ticker = $cfg['ticker'] ?? ($asset['symbol'] . '.SA');
                $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}?interval=1d&range=1mo";

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
                $res = curl_exec($ch);
                curl_close($ch);

                if ($res) {
                    $json = json_decode($res, true);
                    $result = $json['chart']['result'][0] ?? null;
                    if ($result && isset($result['timestamp']) && isset($result['indicators']['quote'][0])) {
                        $timestamps = $result['timestamp'];
                        $quotes = $result['indicators']['quote'][0];
                        $closes = $quotes['close'] ?? [];
                        $highs = $quotes['high'] ?? [];
                        $lows = $quotes['low'] ?? [];

                        for ($i = 0; $i < count($timestamps); $i++) {
                            $price = isset($closes[$i]) ? (float)$closes[$i] : null;
                            if ($price === null || $price <= 0) continue;

                            $high = isset($highs[$i]) ? (float)$highs[$i] : $price;
                            $low = isset($lows[$i]) ? (float)$lows[$i] : $price;
                            $date = date('Y-m-d H:i:s', $timestamps[$i]);

                            $this->insertPriceRecord($asset['id'], $price, $price, $price, $high, $low, 0.0, 'Yahoo Finance Historical', $date);
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the latest price record for an asset
     */
    public function getLatestPrice(int $assetId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM price_history 
            WHERE asset_id = ? 
            ORDER BY recorded_at DESC, id DESC 
            LIMIT 1
        ");
        $stmt->execute([$assetId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get overview list with latest prices and 24h change for all assets
     */
    public function getAssetsOverview(): array
    {
        $sql = "
            SELECT 
                a.id,
                a.symbol,
                a.name,
                a.category,
                a.currency,
                ph.price as latest_price,
                ph.high_24h,
                ph.low_24h,
                ph.change_pct_24h,
                ph.source,
                ph.recorded_at,
                (SELECT COUNT(*) FROM price_history WHERE asset_id = a.id) as total_records
            FROM assets a
            LEFT JOIN price_history ph ON ph.id = (
                SELECT id FROM price_history 
                WHERE asset_id = a.id 
                ORDER BY recorded_at DESC, id DESC 
                LIMIT 1
            )
            WHERE a.is_active = 1
            ORDER BY a.category ASC, a.symbol ASC
        ";
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Get price history series for an asset
     */
    public function getHistory(string $symbol, int $limit = 100, ?int $days = null): array
    {
        $asset = $this->getAssetBySymbol($symbol);
        if (!$asset) return [];

        $whereDays = "";
        $params = [$asset['id']];

        if ($days !== null && $days > 0) {
            $whereDays = "AND recorded_at >= datetime('now', '-{$days} days')";
        }

        $sql = "
            SELECT price, high_24h, low_24h, change_pct_24h, source, recorded_at
            FROM price_history
            WHERE asset_id = ? {$whereDays}
            ORDER BY recorded_at ASC
            LIMIT {$limit}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Calculate summary statistics for an asset (min, max, avg, volatility)
     */
    public function getStatistics(string $symbol): ?array
    {
        $asset = $this->getAssetBySymbol($symbol);
        if (!$asset) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                MIN(price) as min_price,
                MAX(price) as max_price,
                AVG(price) as avg_price,
                MIN(recorded_at) as first_record,
                MAX(recorded_at) as last_record
            FROM price_history
            WHERE asset_id = ?
        ");
        $stmt->execute([$asset['id']]);
        $stats = $stmt->fetch();

        // Calculate all-time variation percentage
        $firstPriceStmt = $this->pdo->prepare("SELECT price FROM price_history WHERE asset_id = ? ORDER BY recorded_at ASC, id ASC LIMIT 1");
        $firstPriceStmt->execute([$asset['id']]);
        $firstPrice = (float)($firstPriceStmt->fetchColumn() ?: 0);

        $latestPriceStmt = $this->pdo->prepare("SELECT price FROM price_history WHERE asset_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1");
        $latestPriceStmt->execute([$asset['id']]);
        $latestPrice = (float)($latestPriceStmt->fetchColumn() ?: 0);

        $variationAllTime = 0.0;
        if ($firstPrice > 0) {
            $variationAllTime = (($latestPrice - $firstPrice) / $firstPrice) * 100;
        }

        $stats['first_price'] = $firstPrice;
        $stats['latest_price'] = $latestPrice;
        $stats['variation_all_time'] = round($variationAllTime, 2);

        return $stats;
    }

    public function getAssetBySymbol(string $symbol): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM assets WHERE UPPER(symbol) = UPPER(?) LIMIT 1");
        $stmt->execute([$symbol]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

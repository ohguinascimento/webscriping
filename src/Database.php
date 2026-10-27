<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static string $dbFile = __DIR__ . '/../data/prices.db';

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dataDir = dirname(self::$dbFile);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0777, true);
            }

            try {
                self::$instance = new PDO("sqlite:" . self::$dbFile);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                // SQLite performance optimizations
                self::$instance->exec('PRAGMA journal_mode = WAL;');
                self::$instance->exec('PRAGMA foreign_keys = ON;');
                self::$instance->exec('PRAGMA busy_timeout = 5000;');

                self::migrate();
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function migrate(): void
    {
        $pdo = self::$instance;

        // Assets Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                category TEXT NOT NULL, -- 'currency', 'crypto', 'stock'
                currency TEXT DEFAULT 'BRL',
                fetch_type TEXT NOT NULL, -- 'awesomeapi', 'yahoo', 'scraper'
                fetch_config TEXT, -- JSON configuration for custom scrapers
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Price History Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS price_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset_id INTEGER NOT NULL,
                price REAL NOT NULL,
                bid REAL,
                ask REAL,
                high_24h REAL,
                low_24h REAL,
                change_pct_24h REAL,
                source TEXT NOT NULL,
                recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
            )
        ");

        // Indexes for lightning-fast historical queries
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_history_asset_date ON price_history (asset_id, recorded_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_history_recorded_at ON price_history (recorded_at DESC)");

        // Wallet holdings for portfolio tracking
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wallet_holdings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset_id INTEGER NOT NULL,
                wallet_name TEXT NOT NULL,
                amount REAL NOT NULL,
                notes TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
            )
        ");

        self::seedDefaultAssets();
    }

    private static function seedDefaultAssets(): void
    {
        $pdo = self::$instance;
        $count = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $defaultAssets = [
            ['symbol' => 'USD', 'name' => 'Dólar Americano', 'category' => 'currency', 'fetch_type' => 'awesomeapi', 'fetch_config' => '{"pair":"USD-BRL","key":"USDBRL"}'],
            ['symbol' => 'EUR', 'name' => 'Euro', 'category' => 'currency', 'fetch_type' => 'awesomeapi', 'fetch_config' => '{"pair":"EUR-BRL","key":"EURBRL"}'],
            ['symbol' => 'BTC', 'name' => 'Bitcoin', 'category' => 'crypto', 'fetch_type' => 'awesomeapi', 'fetch_config' => '{"pair":"BTC-BRL","key":"BTCBRL"}'],
            ['symbol' => 'ETH', 'name' => 'Ethereum', 'category' => 'crypto', 'fetch_type' => 'awesomeapi', 'fetch_config' => '{"pair":"ETH-BRL","key":"ETHBRL"}'],
            ['symbol' => 'SOL', 'name' => 'Solana', 'category' => 'crypto', 'fetch_type' => 'awesomeapi', 'fetch_config' => '{"pair":"SOL-BRL","key":"SOLBRL"}'],
            ['symbol' => 'MGLU3', 'name' => 'Magazine Luiza ON', 'category' => 'stock', 'fetch_type' => 'yahoo', 'fetch_config' => '{"ticker":"MGLU3.SA"}'],
            ['symbol' => 'PETR4', 'name' => 'Petrobras PN', 'category' => 'stock', 'fetch_type' => 'yahoo', 'fetch_config' => '{"ticker":"PETR4.SA"}'],
            ['symbol' => 'VALE3', 'name' => 'Vale ON', 'category' => 'stock', 'fetch_type' => 'yahoo', 'fetch_config' => '{"ticker":"VALE3.SA"}'],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO assets (symbol, name, category, currency, fetch_type, fetch_config)
            VALUES (:symbol, :name, :category, 'BRL', :fetch_type, :fetch_config)
        ");

        foreach ($defaultAssets as $asset) {
            $stmt->execute($asset);
        }

        // Seed initial wallet holding for testing (matches eth.php and carteira.php)
        $ethAsset = $pdo->query("SELECT id FROM assets WHERE symbol = 'ETH'")->fetch();
        if ($ethAsset) {
            $pdo->prepare("INSERT INTO wallet_holdings (asset_id, wallet_name, amount, notes) VALUES (?, ?, ?, ?)")
                ->execute([$ethAsset['id'], 'Minha Carteira Web3', 0.85, 'Polygon / Ethereum holdings']);
        }
    }
}

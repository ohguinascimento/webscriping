<?php
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Scraper.php';
require_once __DIR__ . '/src/PriceCollector.php';

use App\Database;
use App\PriceCollector;

$collector = new PriceCollector();
// Ensure initial data exists
$collector->backfillHistoryIfEmpty();

$overview = $collector->getAssetsOverview();
$pdo = Database::getConnection();

// Overall stats
$totalRecords = $pdo->query("SELECT COUNT(*) FROM price_history")->fetchColumn();
$totalAssets = count($overview);
$lastUpdateRow = $pdo->query("SELECT recorded_at FROM price_history ORDER BY recorded_at DESC LIMIT 1")->fetch();
$lastUpdate = $lastUpdateRow ? date('d/m/Y H:i:s', strtotime($lastUpdateRow['recorded_at'])) : 'Pendente';

// Fetch recent 35 records for the table
$recentHistory = $pdo->query("
    SELECT ph.id, a.symbol, a.name, a.category, ph.price, ph.high_24h, ph.low_24h, ph.change_pct_24h, ph.source, ph.recorded_at
    FROM price_history ph
    JOIN assets a ON a.id = ph.asset_id
    ORDER BY ph.recorded_at DESC, ph.id DESC
    LIMIT 35
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Base de Dados Histórica de Preços | PriceTracker Pro</title>
    <meta name="description" content="Sistema inteligente de coleta e base de dados histórica de cotações de moedas, criptoativos e ações com análise gráfica em tempo real.">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- App Styles -->
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="glass">
            <div class="brand">
                <div class="brand-icon">📈</div>
                <div class="brand-text">
                    <h1>PriceTracker Pro</h1>
                    <p>Base de Dados Histórica & Monitor de Cotações</p>
                </div>
            </div>

            <div class="header-actions">
                <div class="status-badge">
                    <span class="pulse-dot"></span>
                    <span>Monitoramento Ativo</span>
                </div>
                
                <button id="btnRefresh" class="btn btn-primary" title="Buscar cotações agora e registrar no histórico">
                    <span class="refresh-icon">🔄</span>
                    <span>Atualizar Agora</span>
                </button>

                <button id="btnOpenWallet" class="btn btn-secondary">
                    <span>💼</span>
                    <span>Minha Carteira</span>
                </button>

                <a href="api.php?action=export&symbol=ALL" class="btn btn-secondary" title="Baixar histórico completo em CSV">
                    <span>📥</span>
                    <span>Exportar CSV</span>
                </a>

                <button id="btnOpenAddAsset" class="btn btn-secondary" title="Cadastrar novo ativo para monitorar">
                    <span>➕</span>
                    <span>Novo Ativo</span>
                </button>
            </div>
        </header>

        <!-- KPI Metrics Bar -->
        <section class="stats-bar">
            <div class="stat-box glass">
                <div class="stat-label">
                    <span>📦</span> Total no Histórico
                </div>
                <div class="stat-value"><?= number_format($totalRecords, 0, ',', '.') ?></div>
                <div class="stat-sub">Registros salvos em SQLite</div>
            </div>

            <div class="stat-box glass">
                <div class="stat-label">
                    <span>🎯</span> Ativos Monitorados
                </div>
                <div class="stat-value"><?= $totalAssets ?></div>
                <div class="stat-sub">Câmbio, Criptos e B3</div>
            </div>

            <div class="stat-box glass">
                <div class="stat-label">
                    <span>💼</span> Saldo da Carteira
                </div>
                <div id="statWalletTotal" class="stat-value" style="color: #38bdf8;">Calculando...</div>
                <div class="stat-sub">Atualizado com cotações atuais</div>
            </div>

            <div class="stat-box glass">
                <div class="stat-label">
                    <span>⏱️</span> Última Coleta
                </div>
                <div class="stat-value" style="font-size: 1.25rem; font-weight: 600;"><?= $lastUpdate ?></div>
                <div class="stat-sub">Frequência personalizável</div>
            </div>
        </section>

        <!-- Interactive History Chart Section -->
        <section class="chart-card glass">
            <div class="chart-header">
                <div class="chart-title-group">
                    <h2>Evolução Histórica de Preços</h2>
                    <p>Histórico temporal detalhado com variações, máximas e mínimas registradas</p>
                </div>
                
                <div class="chart-controls">
                    <select id="chartAssetSelect" class="custom-select" aria-label="Selecionar Ativo">
                        <?php foreach ($overview as $item): ?>
                            <option value="<?= htmlspecialchars($item['symbol']) ?>" <?= $item['symbol'] === 'BTC' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($item['symbol']) ?> - <?= htmlspecialchars($item['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="pill-group" role="group" aria-label="Período">
                        <button class="pill-btn" data-days="7">7D</button>
                        <button class="pill-btn active" data-days="30">30D</button>
                        <button class="pill-btn" data-days="90">90D</button>
                        <button class="pill-btn" data-days="365">1 Ano</button>
                        <button class="pill-btn" data-days="0">Tudo</button>
                    </div>
                </div>
            </div>

            <div class="chart-canvas-wrapper">
                <canvas id="priceChart"></canvas>
            </div>

            <div class="chart-stats-summary">
                <div class="summary-item">
                    Menor Preço no Período
                    <strong id="statMinPrice">-</strong>
                </div>
                <div class="summary-item">
                    Maior Preço no Período
                    <strong id="statMaxPrice">-</strong>
                </div>
                <div class="summary-item">
                    Preço Médio
                    <strong id="statAvgPrice">-</strong>
                </div>
                <div class="summary-item">
                    Variação Geral
                    <strong id="statVarAllTime">-</strong>
                </div>
            </div>
        </section>

        <!-- Real-time Asset Cards -->
        <section>
            <div class="section-heading">
                <h2>Cotações Mais Recentes</h2>
                <span style="font-size: 0.85rem; color: #94a3b8;">Clique em qualquer card para carregar o histórico</span>
            </div>

            <div class="assets-grid">
                <?php foreach ($overview as $asset): 
                    $priceFmt = isset($asset['latest_price']) ? 'R$ ' . number_format($asset['latest_price'], 2, ',', '.') : 'R$ 0,00';
                    $pctChange = (float)($asset['change_pct_24h'] ?? 0);
                    $isPositive = $pctChange >= 0;
                    $varClass = $isPositive ? 'variation-up' : 'variation-down';
                    $varSign = $isPositive ? '+' : '';
                    $tagClass = 'tag-' . ($asset['category'] ?? 'currency');
                    
                    $catLabels = [
                        'crypto' => 'Criptomoeda',
                        'currency' => 'Câmbio / Moeda',
                        'stock' => 'Ações B3'
                    ];
                    $catLabel = $catLabels[$asset['category']] ?? ucfirst($asset['category']);
                ?>
                <div class="asset-card glass" onclick="selectAssetForChart('<?= htmlspecialchars($asset['symbol']) ?>')">
                    <div class="asset-top">
                        <div class="asset-identity">
                            <div class="asset-badge-avatar"><?= substr(htmlspecialchars($asset['symbol']), 0, 3) ?></div>
                            <div class="asset-title">
                                <h3><?= htmlspecialchars($asset['symbol']) ?></h3>
                                <span><?= htmlspecialchars($asset['name']) ?></span>
                            </div>
                        </div>
                        <span class="category-tag <?= $tagClass ?>"><?= $catLabel ?></span>
                    </div>

                    <div class="asset-price-row">
                        <div class="asset-price"><?= $priceFmt ?></div>
                        <div class="variation-pill <?= $varClass ?>">
                            <span><?= $isPositive ? '▲' : '▼' ?></span>
                            <span><?= $varSign . number_format($pctChange, 2, ',', '.') ?>%</span>
                        </div>
                    </div>

                    <div class="asset-details">
                        <span>Máx: R$ <?= number_format((float)($asset['high_24h'] ?? 0), 2, ',', '.') ?></span>
                        <span>Mín: R$ <?= number_format((float)($asset['low_24h'] ?? 0), 2, ',', '.') ?></span>
                        <span>Fonte: <?= htmlspecialchars($asset['source'] ?? 'API') ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Historical Database Table -->
        <section class="table-card glass">
            <div class="table-toolbar">
                <div>
                    <h2 style="font-family: var(--font-display); font-size: 1.25rem;">Registros Recentes da Base de Dados</h2>
                    <p style="font-size: 0.82rem; color: #94a3b8;">Histórico gravado com timestamps de auditoria e fontes de dados</p>
                </div>
                
                <input type="text" id="tableSearch" class="search-input" placeholder="🔍 Filtrar por ativo, fonte ou data..." aria-label="Filtrar histórico">
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Data / Hora</th>
                            <th>Ativo</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço Gravado</th>
                            <th>Variação</th>
                            <th>Máx / Mín</th>
                            <th>Fonte</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <?php foreach ($recentHistory as $row): 
                            $var = (float)($row['change_pct_24h'] ?? 0);
                            $varColor = $var >= 0 ? '#34d399' : '#fb7185';
                            $varSign = $var >= 0 ? '+' : '';
                        ?>
                        <tr>
                            <td style="color: #94a3b8; font-size: 0.82rem;">
                                <?= date('d/m/Y H:i:s', strtotime($row['recorded_at'])) ?>
                            </td>
                            <td>
                                <strong style="color: #6366f1;"><?= htmlspecialchars($row['symbol']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td>
                                <span class="category-tag tag-<?= htmlspecialchars($row['category']) ?>">
                                    <?= htmlspecialchars($row['category']) ?>
                                </span>
                            </td>
                            <td>
                                <strong>R$ <?= number_format($row['price'], 2, ',', '.') ?></strong>
                            </td>
                            <td style="color: <?= $varColor ?>; font-weight: 600;">
                                <?= $varSign . number_format($var, 2, ',', '.') ?>%
                            </td>
                            <td style="font-size: 0.8rem; color: #94a3b8;">
                                <?= $row['high_24h'] ? 'R$ ' . number_format($row['high_24h'], 2, ',', '.') : '-' ?> / 
                                <?= $row['low_24h'] ? 'R$ ' . number_format($row['low_24h'], 2, ',', '.') : '-' ?>
                            </td>
                            <td>
                                <span class="badge-source"><?= htmlspecialchars($row['source']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Footer -->
        <footer>
            <p>PriceTracker Pro • Base de Dados Histórica de Preços desenvolvida em PHP 8 + SQLite + Chart.js</p>
            <p style="margin-top: 6px;">Scripts legados compatíveis: <a href="cotacoes.php" style="color: #6366f1;">cotacoes.php</a> | <a href="dolar.php" style="color: #6366f1;">dolar.php</a> | <a href="bitcoin.php" style="color: #6366f1;">bitcoin.php</a> | <a href="eth.php" style="color: #6366f1;">eth.php</a> | <a href="magalu.php" style="color: #6366f1;">magalu.php</a> | <a href="carteira.php" style="color: #6366f1;">carteira.php</a></p>
        </footer>
    </div>

    <!-- Modal: Adicionar Novo Ativo -->
    <div id="modalAddAsset" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Cadastrar Novo Ativo</h3>
                <button type="button" id="btnCloseAddAsset" class="close-btn">&times;</button>
            </div>
            <form id="formAddAsset">
                <div class="form-group">
                    <label for="assetSymbol">Símbolo (Ex: AAPL, ITUB4, DOGE, GBP)</label>
                    <input type="text" id="assetSymbol" name="symbol" class="form-control" required placeholder="Ex: ITUB4">
                </div>
                <div class="form-group">
                    <label for="assetName">Nome Descritivo</label>
                    <input type="text" id="assetName" name="name" class="form-control" required placeholder="Ex: Itaú Unibanco PN">
                </div>
                <div class="form-group">
                    <label for="assetCategory">Categoria</label>
                    <select id="assetCategory" name="category" class="form-control">
                        <option value="stock">Ações B3 / Ações Internacionais</option>
                        <option value="crypto">Criptomoeda</option>
                        <option value="currency">Moeda Fiduciária / Câmbio</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="assetFetchType">Método de Coleta</label>
                    <select id="assetFetchType" name="fetch_type" class="form-control" onchange="toggleFetchFields(this.value)">
                        <option value="yahoo">Yahoo Finance (Ticker de Ação - Automático)</option>
                        <option value="scraper">Web Scraper Personalizado (URL + XPath)</option>
                    </select>
                </div>

                <div id="groupYahoo" class="form-group">
                    <label for="yahooTicker">Ticker Yahoo (Padrão B3 usa .SA, ex: ITUB4.SA)</label>
                    <input type="text" id="yahooTicker" name="yahoo_ticker" class="form-control" placeholder="ITUB4.SA">
                </div>

                <div id="groupScraper" style="display: none;">
                    <div class="form-group">
                        <label for="scraperUrl">URL do Site para Scraping</label>
                        <input type="url" id="scraperUrl" name="scraper_url" class="form-control" placeholder="https://exemplo.com/cotacao">
                    </div>
                    <div class="form-group">
                        <label for="scraperXpath">Expressão XPath do Valor</label>
                        <input type="text" id="scraperXpath" name="scraper_xpath" class="form-control" placeholder=".//span[@class='preco']">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 10px;">
                    Salvar e Iniciar Monitoramento
                </button>
            </form>
        </div>
    </div>

    <!-- Modal: Carteira / Portfolio -->
    <div id="modalWallet" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>💼 Minha Carteira de Investimentos</h3>
                <button type="button" id="btnCloseWallet" class="close-btn">&times;</button>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 0.9rem; color: #94a3b8; margin-bottom: 10px;">Posições Atuais</h4>
                <div id="walletItemsList" style="max-height: 180px; overflow-y: auto;">
                    Carregando posições...
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.08); margin: 16px 0;">

            <h4 style="font-size: 0.9rem; color: #94a3b8; margin-bottom: 12px;">Adicionar Nova Posição</h4>
            <form id="formAddHolding">
                <div class="form-group">
                    <label for="holdingSymbol">Ativo</label>
                    <select id="holdingSymbol" name="holding_symbol" class="form-control" required>
                        <?php foreach ($overview as $item): ?>
                            <option value="<?= htmlspecialchars($item['symbol']) ?>">
                                <?= htmlspecialchars($item['symbol']) ?> - <?= htmlspecialchars($item['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="holdingAmount">Quantidade</label>
                    <input type="number" step="any" id="holdingAmount" name="holding_amount" class="form-control" required placeholder="Ex: 1.5">
                </div>
                <div class="form-group">
                    <label for="holdingWalletName">Nome da Carteira / Corretora</label>
                    <input type="text" id="holdingWalletName" name="holding_wallet_name" class="form-control" placeholder="Ex: MetaMask, Binance, XP">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Adicionar à Carteira
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleFetchFields(val) {
            document.getElementById('groupYahoo').style.display = (val === 'yahoo') ? 'block' : 'none';
            document.getElementById('groupScraper').style.display = (val === 'scraper') ? 'block' : 'none';
        }
    </script>
    <script src="public/js/app.js"></script>
</body>
</html>

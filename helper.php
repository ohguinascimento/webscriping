<?php
/**
 * Helper com suporte a Scraper moderno (cURL com headers de navegador)
 * e integração automática com a base de dados histórica SQLite.
 */

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Scraper.php';
require_once __DIR__ . '/src/PriceCollector.php';

use App\Database;
use App\Scraper;
use App\PriceCollector;

/**
 * Função legado 'webscript' mantida para compatibilidade,
 * agora com motor cURL resiliente e prevenção contra bloqueios 403.
 */
function webscript($site, $path)
{
    $scraper = new Scraper();
    try {
        $html = $scraper->fetchHtml($site);
        $result = $scraper->queryXPath($html, $path);
        return $result ?? '';
    } catch (Exception $e) {
        // Fallback gracioso para evitar crash da aplicação
        return '';
    }
}

/**
 * Obtém a cotação mais recente de um ativo (com atualização em tempo real se necessário)
 */
function getCotacao($symbol)
{
    $collector = new PriceCollector();
    $asset = $collector->getAssetBySymbol($symbol);
    if (!$asset) {
        return null;
    }

    $latest = $collector->getLatestPrice($asset['id']);
    if (!$latest) {
        $collector->collectAll();
        $latest = $collector->getLatestPrice($asset['id']);
    }

    return $latest ? (float)$latest['price'] : null;
}

/**
 * Formata um valor numérico para o padrão de moeda brasileiro R$ X.XXX,XX
 */
function formatarPreco($valor, $decimais = 2)
{
    return 'R$ ' . number_format((float)$valor, $decimais, ',', '.');
}

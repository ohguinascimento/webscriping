<?php

namespace App;

use DOMDocument;
use DOMXPath;
use Exception;

class Scraper
{
    private array $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        'Sec-Ch-Ua: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
        'Sec-Ch-Ua-Mobile: ?0',
        'Sec-Ch-Ua-Platform: "Windows"',
        'Cache-Control: no-cache',
        'Pragma: no-cache'
    ];

    /**
     * Fetch raw HTML from a URL with robust browser impersonation
     */
    public function fetchHtml(string $url, int $timeout = 10): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->defaultHeaders);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // supports gzip/deflate

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($html === false || $httpCode >= 400) {
            throw new Exception("Falha ao buscar URL {$url}. HTTP Status: {$httpCode}. Erro cURL: {$error}");
        }

        return $html;
    }

    /**
     * Extract text using an XPath expression from HTML content
     */
    public function queryXPath(string $html, string $xpathQuery): ?string
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        
        // Ensure UTF-8 parsing
        $encodedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        if (empty($encodedHtml) || !@$dom->loadHTML($encodedHtml)) {
            libxml_clear_errors();
            return null;
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query($xpathQuery);

        libxml_clear_errors();

        if ($nodes && $nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        return null;
    }

    /**
     * Parse and clean messy currency strings into standard float
     * Handles formats like:
     * - "R$ 5,30" -> 5.30
     * - "143.520,00" -> 143520.00
     * - "5.43" -> 5.43
     * - "$ 1,234.56" -> 1234.56
     */
    public static function parseCurrency(string $raw): ?float
    {
        $clean = trim($raw);
        // Remove currency symbols and non-numeric characters except commas, dots, minuses
        $clean = preg_replace('/[^\d,.-]/', '', $clean);

        if ($clean === '' || $clean === null) {
            return null;
        }

        // Case 1: Brazilian format (dots for thousands, comma for decimals: 1.250,50)
        if (preg_match('/\.\d{3},\d+$/', $clean) || (strpos($clean, ',') !== false && strpos($clean, '.') !== false && strrpos($clean, ',') > strrpos($clean, '.'))) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } 
        // Case 2: Only comma used as decimal separator (e.g. 5,30)
        elseif (strpos($clean, ',') !== false && strpos($clean, '.') === false) {
            $clean = str_replace(',', '.', $clean);
        }
        // Case 3: International format (commas for thousands: 1,250.50)
        elseif (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
            $clean = str_replace(',', '', $clean);
        }

        $val = filter_var($clean, FILTER_VALIDATE_FLOAT);
        return $val !== false ? (float)$val : null;
    }
}

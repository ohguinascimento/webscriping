<?php
require_once __DIR__ . '/helper.php';

$cotacao = getCotacao('ETH') ?? 0;
$cotacaoFmt = formatarPreco($cotacao, 2);

echo "<h1>Cotação do Ethereum é " . $cotacaoFmt . "</h1>" . PHP_EOL;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Calculadora Ethereum</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; background: #0f172a; color: #f8fafc; }
        .card { background: #1e293b; padding: 24px; border-radius: 12px; max-width: 450px; border: 1px solid #334155; }
        input { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #fff; font-size: 16px; margin: 10px 0; box-sizing: border-box; }
        .resultado { font-size: 20px; font-weight: bold; color: #38bdf8; margin-top: 15px; }
        a { color: #38bdf8; text-decoration: none; display: inline-block; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Quantos Ethereum você tem?</h2>
        <input type="number" step="any" name="eth" placeholder="Ex: 0.5" autofocus>
        <input type="hidden" name="cotacao" value="<?= htmlspecialchars((string)$cotacao) ?>">
        <div class="resultado">Total: R$ 0,00</div>
        <a href="index.php">← Ir para o Dashboard Completo</a>
    </div>

    <script>
        const ethInput = document.querySelector("input[name=eth]");
        const cotacaoVal = parseFloat(document.querySelector("input[name=cotacao]").value) || 0;
        const resultadoDiv = document.querySelector(".resultado");

        function calcular() {
            const qtd = parseFloat(ethInput.value) || 0;
            const total = qtd * cotacaoVal;
            resultadoDiv.innerHTML = "Total: " + total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        ethInput.addEventListener('input', calcular);
        ethInput.addEventListener('keyup', calcular);
    </script>
</body>
</html>
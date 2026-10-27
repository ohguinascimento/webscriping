# 📈 PriceTracker Pro — Base de Dados Histórica de Preços

Sistema profissional em **PHP 8 + SQLite + Chart.js** para coleta, armazenamento e análise gráfica de preços históricos de moedas fiduciárias, criptoativos e ações da B3.

---

## 🚀 Principais Melhorias Implementadas

1. **Base de Dados Histórica (SQLite + WAL Mode)**:
   - Armazenamento persistente e indexado em `data/prices.db`.
   - Tabelas estruturadas: `assets` (ativos cadastrados), `price_history` (séries temporais com preço, máxima, mínima, variação, fonte e timestamp) e `wallet_holdings` (gestão de carteira).
   - Suporte a consultas temporais (7d, 30d, 90d, 1 ano, tudo) com cálculo instantâneo de médias, máximas, mínimas e volatilidade.

2. **Motor de Coleta Multi-Fonte Resiliente**:
   - **AwesomeAPI**: Cotações de alta frequência e histórico de câmbio/cripto (USD, EUR, BTC, ETH, SOL).
   - **Yahoo Finance**: Cotações e histórico diário de ações da B3 (`MGLU3.SA`, `PETR4.SA`, `VALE3.SA`).
   - **Scraper Inteligente com cURL**: Headers modernos de navegador (User-Agent, sec-ch-ua) com parser XPath e higienizador numérico para evitar bloqueios 403 Forbidden.

3. **Dashboard Web Interativo (Modern Glassmorphism UI)**:
   - Painel escuro moderno com tipografia Inter e Outfit.
   - **Gráficos Interativos (Chart.js)**: Seleção dinâmica de ativo, alternância de período, curva com preenchimento em gradiente e tooltips monetários.
   - **Grid de Cotações em Tempo Real**: Cards com variação percentual de 24h, máximas/mínimas do dia e clique rápido para focar no gráfico.
   - **Tabela Histórica com Filtro em Tempo Real**: Busca rápida por ativo, fonte ou data.
   - **Botão "Atualizar Agora"**: Coleta assíncrona que atualiza o banco e reflete na interface sem travar o navegador.
   - **Gestor de Carteira**: Acompanhamento do patrimônio total calculado com as cotações vigentes.
   - **Exportação de Dados**: Download instantâneo de relatórios em CSV compatíveis com Excel.

4. **100% de Compatibilidade com Scripts Legados**:
   - Os scripts originais (`cotacoes.php`, `dolar.php`, `bitcoin.php`, `eth.php`, `magalu.php`, `carteira.php` e `helper.php`) foram modernizados, utilizam as novas fontes sem travar e gravam automaticamente no histórico!

---

## 🛠️ Como Executar

### 1. Iniciar o Servidor Web Local
Na pasta raiz do projeto, execute:
```bash
php -S localhost:8000
```
Abra o navegador em: [http://localhost:8000](http://localhost:8000)

### 2. Executar Coleta Manual pelo Terminal
Para rodar um ciclo de coleta de todos os ativos e gravar na base de dados:
```bash
php collector.php
```

Para forçar a importação de dados históricos de 30 dias caso o banco esteja novo:
```bash
php collector.php --seed
```

---

## ⏰ Automação da Coleta (Cron / Agendador)

Para manter a base de dados histórica sempre atualizada automaticamente:

### No Windows (Agendador de Tarefas / Task Scheduler):
1. Abra o **Agendador de Tarefas** (`taskschd.msc`).
2. Clique em **Criar Tarefa Básica**.
3. Defina a periodicidade (ex: a cada 1 hora ou diariamente).
4. Em **Ação**, selecione **Iniciar um programa**:
   - Programa: `php.exe`
   - Argumentos: `collector.php`
   - Iniciar em: `D:\Projetos\webscriping`

### No Linux (Cron):
Adicione a seguinte linha no `crontab -e` (executa a cada hora):
```bash
0 * * * * cd /caminho/do/projeto && php collector.php >> collector.log 2>&1
```

---

## 🌐 Endpoints da API REST (`api.php`)

| Endpoint | Método | Descrição |
|---|---|---|
| `api.php?action=overview` | GET | Cotações mais recentes de todos os ativos cadastrados |
| `api.php?action=history&symbol=BTC&days=30` | GET | Série histórica temporal para gráficos |
| `api.php?action=stats&symbol=USD` | GET | Estatísticas de preço (máxima, mínima, média, variação) |
| `api.php?action=collect` | POST | Dispara coleta imediata de todos os ativos |
| `api.php?action=export&symbol=ALL` | GET | Download de arquivo CSV com os registros |
| `api.php?action=wallet` | GET | Posições da carteira e valor patrimonial atualizado |
| `api.php?action=add_asset` | POST | Cadastra um novo ativo (Yahoo Finance ou Scraper) |
| `api.php?action=add_holding` | POST | Adiciona uma nova posição na carteira |

---

## 📁 Estrutura de Arquivos

```
webscriping/
├── data/
│   └── prices.db             # Base de dados SQLite persistente
├── src/
│   ├── Database.php          # Gerenciador de conexão PDO, migrações e índices
│   ├── Scraper.php           # Scraper cURL com headers, XPath e parser de moedas
│   └── PriceCollector.php    # Orquestrador de coleta (AwesomeAPI, Yahoo, Scraper)
├── public/
│   ├── css/
│   │   └── style.css         # Estilização moderna e responsiva (Dark Theme)
│   └── js/
│       └── app.js            # Lógica dos gráficos (Chart.js), API e modais
├── api.php                   # Endpoints REST JSON e exportação CSV
├── index.php                 # Dashboard Web principal
├── collector.php             # Executável CLI para agendamentos automáticos
├── helper.php                # Funções utilitárias e compatibilidade retroativa
├── cotacoes.php              # Visão geral rápida em tempo real
├── carteira.php              # Relatório consolidado da carteira
├── bitcoin.php               # Cotação individual Bitcoin
├── dolar.php                 # Cotação individual Dólar
├── eth.php                   # Cotação individual Ethereum + Calculadora
├── magalu.php                # Cotação individual Magazine Luiza
└── README.md                 # Documentação completa do projeto
```

/**
 * Historical Price Engine - Frontend Application
 */

let priceChart = null;
let currentSymbol = 'BTC';
let currentDays = 30;

document.addEventListener('DOMContentLoaded', () => {
    initChart();
    loadAssetHistory(currentSymbol, currentDays);
    loadWalletSummary();
    setupEventListeners();
});

function setupEventListeners() {
    // Asset Select Change
    const assetSelect = document.getElementById('chartAssetSelect');
    if (assetSelect) {
        assetSelect.addEventListener('change', (e) => {
            currentSymbol = e.target.value;
            loadAssetHistory(currentSymbol, currentDays);
        });
    }

    // Timeframe Pills
    document.querySelectorAll('.pill-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
            e.currentTarget.classList.add('active');
            currentDays = parseInt(e.currentTarget.dataset.days, 10);
            loadAssetHistory(currentSymbol, currentDays);
        });
    });

    // Refresh Button
    const refreshBtn = document.getElementById('btnRefresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', triggerCollect);
    }

    // Search filter for table
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('#historyTableBody tr').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // Modal Triggers
    const openAddAsset = document.getElementById('btnOpenAddAsset');
    const modalAddAsset = document.getElementById('modalAddAsset');
    const closeAddAsset = document.getElementById('btnCloseAddAsset');
    if (openAddAsset && modalAddAsset) {
        openAddAsset.addEventListener('click', () => modalAddAsset.classList.add('active'));
    }
    if (closeAddAsset && modalAddAsset) {
        closeAddAsset.addEventListener('click', () => modalAddAsset.classList.remove('active'));
    }

    const openWallet = document.getElementById('btnOpenWallet');
    const modalWallet = document.getElementById('modalWallet');
    const closeWallet = document.getElementById('btnCloseWallet');
    if (openWallet && modalWallet) {
        openWallet.addEventListener('click', () => {
            loadWalletSummary();
            modalWallet.classList.add('active');
        });
    }
    if (closeWallet && modalWallet) {
        closeWallet.addEventListener('click', () => modalWallet.classList.remove('active'));
    }

    // Form Add Asset
    const formAddAsset = document.getElementById('formAddAsset');
    if (formAddAsset) {
        formAddAsset.addEventListener('submit', handleAddAsset);
    }

    // Form Add Holding
    const formAddHolding = document.getElementById('formAddHolding');
    if (formAddHolding) {
        formAddHolding.addEventListener('submit', handleAddHolding);
    }
}

function initChart() {
    const ctx = document.getElementById('priceChart').getContext('2d');
    
    // Gradient fill
    const gradient = ctx.createLinearGradient(0, 0, 0, 350);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.45)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

    priceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Cotação (R$)',
                data: [],
                borderColor: '#6366f1',
                borderWidth: 2.5,
                backgroundColor: gradient,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#6366f1',
                pointHoverRadius: 6,
                pointRadius: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index',
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleColor: '#94a3b8',
                    bodyColor: '#f8fafc',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            let val = context.parsed.y;
                            return 'Preço: ' + val.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.04)' },
                    ticks: { color: '#64748b', maxTicksLimit: 10 }
                },
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: {
                        color: '#64748b',
                        callback: function(val) {
                            return 'R$ ' + Number(val).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
                        }
                    }
                }
            }
        }
    });
}

async function loadAssetHistory(symbol, days) {
    try {
        const url = `api.php?action=history&symbol=${encodeURIComponent(symbol)}&days=${days}&limit=200`;
        const res = await fetch(url);
        const json = await res.json();

        if (json.status !== 'success' || !json.data) {
            showToast('Erro ao carregar histórico de ' + symbol, 'error');
            return;
        }

        const labels = [];
        const prices = [];

        json.data.forEach(item => {
            const d = new Date(item.recorded_at.replace(' ', 'T'));
            const dateStr = d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + ' ' + 
                            d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            labels.push(dateStr);
            prices.push(item.price);
        });

        // Update chart
        priceChart.data.labels = labels;
        priceChart.data.datasets[0].data = prices;
        priceChart.update();

        // Update Stats Summary
        if (json.stats) {
            document.getElementById('statMinPrice').textContent = formatCurrency(json.stats.min_price);
            document.getElementById('statMaxPrice').textContent = formatCurrency(json.stats.max_price);
            document.getElementById('statAvgPrice').textContent = formatCurrency(json.stats.avg_price);
            
            const varEl = document.getElementById('statVarAllTime');
            const sign = json.stats.variation_all_time >= 0 ? '+' : '';
            varEl.textContent = sign + json.stats.variation_all_time + '%';
            varEl.style.color = json.stats.variation_all_time >= 0 ? '#34d399' : '#fb7185';
        }
    } catch (err) {
        console.error("Falha ao carregar histórico:", err);
    }
}

async function triggerCollect() {
    const btn = document.getElementById('btnRefresh');
    const icon = btn.querySelector('.refresh-icon');
    if (icon) icon.classList.add('spin');
    btn.disabled = true;

    try {
        const res = await fetch('api.php?action=collect', { method: 'POST' });
        const json = await res.json();

        if (json.status === 'success') {
            showToast('✅ Cotações atualizadas e gravadas no histórico com sucesso!', 'success');
            // Refresh chart and reload page data
            await loadAssetHistory(currentSymbol, currentDays);
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            showToast('Falha na coleta de cotações', 'error');
        }
    } catch (err) {
        showToast('Erro de comunicação com o servidor', 'error');
    } finally {
        if (icon) icon.classList.remove('spin');
        btn.disabled = false;
    }
}

async function loadWalletSummary() {
    try {
        const res = await fetch('api.php?action=wallet');
        const json = await res.json();
        if (json.status === 'success') {
            const totalEl = document.getElementById('statWalletTotal');
            if (totalEl) {
                totalEl.textContent = formatCurrency(json.total_brl);
            }

            const listEl = document.getElementById('walletItemsList');
            if (listEl) {
                if (json.data.length === 0) {
                    listEl.innerHTML = '<p style="color: #64748b; font-size: 0.9rem;">Nenhuma posição cadastrada.</p>';
                } else {
                    listEl.innerHTML = json.data.map(item => `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <div>
                                <strong style="color: #f8fafc;">${item.amount} ${item.symbol}</strong>
                                <div style="font-size: 0.75rem; color: #64748b;">${item.wallet_name} ${item.notes ? '• ' + item.notes : ''}</div>
                            </div>
                            <div style="text-align: right;">
                                <strong style="color: #38bdf8;">${formatCurrency(item.current_total_brl)}</strong>
                                <div style="font-size: 0.75rem; color: #64748b;">${formatCurrency(item.current_price)} cada</div>
                            </div>
                        </div>
                    `).join('');
                }
            }
        }
    } catch (err) {
        console.error("Falha ao carregar carteira:", err);
    }
}

async function handleAddAsset(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const payload = {
        symbol: formData.get('symbol'),
        name: formData.get('name'),
        category: formData.get('category'),
        fetch_type: formData.get('fetch_type')
    };

    if (payload.fetch_type === 'yahoo') {
        payload.fetch_config = { ticker: formData.get('yahoo_ticker') || (payload.symbol + '.SA') };
    } else if (payload.fetch_type === 'scraper') {
        payload.fetch_config = {
            url: formData.get('scraper_url'),
            xpath: formData.get('scraper_xpath')
        };
    }

    try {
        const res = await fetch('api.php?action=add_asset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.status === 'success') {
            showToast(json.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(json.message || 'Erro ao cadastrar ativo', 'error');
        }
    } catch (err) {
        showToast('Erro de conexão ao salvar ativo', 'error');
    }
}

async function handleAddHolding(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const payload = {
        symbol: formData.get('holding_symbol'),
        amount: parseFloat(formData.get('holding_amount')),
        wallet_name: formData.get('holding_wallet_name') || 'Minha Carteira',
        notes: formData.get('holding_notes') || ''
    };

    try {
        const res = await fetch('api.php?action=add_holding', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.status === 'success') {
            showToast('Posição adicionada com sucesso!', 'success');
            e.target.reset();
            loadWalletSummary();
        } else {
            showToast(json.message || 'Erro ao adicionar', 'error');
        }
    } catch (err) {
        showToast('Erro ao salvar posição', 'error');
    }
}

function selectAssetForChart(symbol) {
    currentSymbol = symbol;
    const select = document.getElementById('chartAssetSelect');
    if (select) select.value = symbol;
    loadAssetHistory(symbol, currentDays);
    window.scrollTo({ top: document.querySelector('.chart-card').offsetTop - 30, behavior: 'smooth' });
}

function formatCurrency(val) {
    if (val === null || val === undefined) return 'R$ 0,00';
    return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function showToast(message, type = 'success') {
    let toast = document.getElementById('globalToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'globalToast';
        toast.className = 'toast';
        document.body.appendChild(toast);
    }
    toast.className = `toast toast-${type} show`;
    toast.textContent = message;

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}

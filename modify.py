import re
import os

with open('resources/views/dashboard.blade.php', 'r', encoding='utf-8') as f:
    html = f.read()

# Add IDs to KPIs
html = html.replace('<div class="kpi-value">186</div>', '<div class="kpi-value" id="kpi-total">186</div>')
html = html.replace('<div class="kpi-value">14</div>', '<div class="kpi-value" id="kpi-critical">14</div>')
html = html.replace('<div class="kpi-value">37</div>', '<div class="kpi-value" id="kpi-warning">37</div>')
html = html.replace('<div class="kpi-value">135</div>', '<div class="kpi-value" id="kpi-ok">135</div>')

# Add IDs to Donut Chart labels
html = html.replace('<text x="75" y="71" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="26" font-weight="700" fill="#E4E8EE">186</text>', '<text id="donut-total" x="75" y="71" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="26" font-weight="700" fill="#E4E8EE">186</text>')

html = html.replace('<span class="val">135</span>', '<span class="val" id="legend-ok">135</span>')
html = html.replace('<span class="val">37</span>', '<span class="val" id="legend-warning\">37</span>')
html = html.replace('<span class="val">14</span>', '<span class="val" id="legend-critical">14</span>')

# Add ID to alert feed
html = html.replace('<div class="alert-feed">', '<div class="alert-feed" id="alertFeed">')

with open('resources/views/dashboard.blade.php', 'w', encoding='utf-8') as f:
    f.write(html)
print('Blade updated with IDs')

# Modify JS
with open('public/js/dashboard.js', 'r', encoding='utf-8') as f:
    js = f.read()

js = re.sub(r'const INDEX_DATA = \[.*?\];', 'let INDEX_DATA = [];', js, flags=re.DOTALL)

fetch_code = """
async function fetchDashboardData() {
    try {
        const res = await fetch('/api/dashboard/data');
        const data = await res.json();
        
        // Update KPIs
        if(document.getElementById('kpi-total')) document.getElementById('kpi-total').textContent = data.kpis.total;
        if(document.getElementById('kpi-critical')) document.getElementById('kpi-critical').textContent = data.kpis.critical;
        if(document.getElementById('kpi-warning')) document.getElementById('kpi-warning').textContent = data.kpis.warning;
        if(document.getElementById('kpi-ok')) document.getElementById('kpi-ok').textContent = data.kpis.ok;
        
        // Update Legends
        if(document.getElementById('donut-total')) document.getElementById('donut-total').textContent = data.kpis.total;
        if(document.getElementById('legend-ok')) document.getElementById('legend-ok').textContent = data.kpis.ok;
        if(document.getElementById('legend-warning')) document.getElementById('legend-warning').textContent = data.kpis.warning;
        if(document.getElementById('legend-critical')) document.getElementById('legend-critical').textContent = data.kpis.critical;
        
        // Update Alerts
        const alertFeed = document.getElementById('alertFeed');
        if (alertFeed) {
            let html = '';
            data.alerts.forEach(a => {
                let colorVar = a.status === 'CRITICAL' ? 'var(--crit)' : a.status === 'WARNING' ? 'var(--warn)' : 'var(--ok)';
                html += `
                <div class="alert-item">
                  <span class="alert-dot" style="background:${colorVar}"></span>
                  <div class="alert-body">
                    <div class="alert-text">${a.text}</div>
                    <div class="alert-time">${a.time_ago}</div>
                  </div>
                </div>`;
            });
            alertFeed.innerHTML = html;
        }

        // Update Table Data
        INDEX_DATA = data.indexes;
        renderTable();
        
    } catch(e) {
        console.error("Error fetching dashboard data:", e);
    }
}

fetchDashboardData();
setInterval(fetchDashboardData, 3000);
"""

js = js.replace('renderTable();', fetch_code)

with open('public/js/dashboard.js', 'w', encoding='utf-8') as f:
    f.write(js)
print('JS updated with fetch logic')

<?php
/**
 * Admin — Bulk Import
 * Paste one URL per line; the page processes them sequentially via the existing
 * /api/parser.php endpoint, showing a live results table as each completes.
 * Blank lines and lines starting with # are ignored.
 */
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';
AdminAuth::check();
require_once __DIR__ . '/../../includes/SiteSettings.php';
SiteSettings::apply();

$pageTitle = 'Bulk Import';
$activeNav = 'bulk-import';
require __DIR__ . '/_header.php';
?>

<style>
/* ── Bulk Import layout ─────────────────────────────────────────────── */
#bulk-textarea {
    width: 100%;
    min-height: 220px;
    font-family: 'Courier New', Courier, monospace;
    font-size: .82rem;
    line-height: 1.55;
    padding: .65rem .85rem;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    background: var(--color-background);
    color: var(--color-text-primary);
    resize: vertical;
    box-sizing: border-box;
}
#bulk-textarea:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgba(160,120,80,.15);
}
.import-controls {
    display: flex;
    gap: .6rem;
    align-items: center;
    flex-wrap: wrap;
    margin-top: .75rem;
}
.import-hint {
    font-size: .78rem;
    color: var(--color-text-secondary);
    margin-top: .5rem;
}
/* Progress bar */
#progress-bar-wrap {
    height: 6px;
    background: var(--color-border-light, #e9e3d8);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: .75rem;
}
#progress-bar-fill {
    height: 100%;
    width: 0%;
    background: var(--color-accent, #8b6914);
    border-radius: 3px;
    transition: width .35s ease;
}
/* Summary bar */
#summary-bar {
    font-size: .82rem;
    color: var(--color-text-secondary);
    margin-bottom: .85rem;
    min-height: 1.2em;
}
/* Results table */
#results-table-wrap { overflow-x: auto; }
#results-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
}
#results-table th {
    text-align: left;
    padding: .45rem .75rem;
    border-bottom: 2px solid var(--color-border);
    font-size: .75rem;
    font-weight: 600;
    color: var(--color-text-secondary);
    white-space: nowrap;
}
#results-table td {
    padding: .45rem .75rem;
    border-bottom: 1px solid var(--color-border-light);
    vertical-align: middle;
}
#results-table tr:last-child td { border-bottom: none; }
.tr-pending  td:first-child { color: var(--color-text-secondary); }
.tr-fetching td { background: rgba(160,120,80,.04); }
.tr-success  td { background: rgba(80,160,80,.04); }
.tr-error    td { background: rgba(200,60,60,.04); }
.tr-cached   td { background: rgba(100,120,160,.04); }
.tr-invalid  td { background: rgba(160,160,160,.05); }
.url-cell {
    max-width: 340px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: monospace;
    font-size: .77rem;
    color: var(--color-text-secondary);
}
.title-cell {
    max-width: 240px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.badge-pending  { background:#d4c9b0;color:#5a4a2a; }
.badge-fetching { background:#e8d080;color:#5a4a1a; animation: pulse 1.2s infinite; }
.badge-success  { background:#c8e8c8;color:#1a5a1a; }
.badge-cached   { background:#c8d8f0;color:#1a2a5a; }
.badge-error    { background:#f0c8c8;color:#5a1a1a; }
.badge-invalid  { background:#e0e0e0;color:#444; }
.badge-warning  { background:#f0e0a0;color:#5a3a00; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.55} }
.conf-mini { font-size:.75rem; color:var(--color-text-secondary); }
/* Warning banner */
#warn-banner {
    display: none;
    background: #fff8e1;
    border: 1px solid #f0cc60;
    border-radius: 6px;
    padding: .75rem 1rem;
    font-size: .85rem;
    margin-bottom: 1rem;
    display: flex;
    gap: .75rem;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
}
</style>

<!-- ── Input panel ──────────────────────────────────────────────────────── -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Batch URL Import</span>
    </div>
    <div class="panel-body" style="padding:1.25rem 1.5rem;">
        <textarea id="bulk-textarea"
                  placeholder="https://www.allrecipes.com/recipe/123/example-cake/
https://www.bbcgoodfood.com/recipes/banana-bread
# This line is a comment and will be skipped
https://www.foodnetwork.com/recipes/example

Blank lines are also skipped."></textarea>

        <div class="import-hint">
            One URL per line. Lines starting with <code>#</code> and blank lines are ignored.
            Already-cached URLs complete instantly with no delay.
            A 5-second delay is applied between fresh scrapes.
        </div>

        <div class="import-controls">
            <button id="btn-start" class="btn btn-primary">▶ Start Import</button>
            <button id="btn-clear" class="btn btn-ghost">Clear</button>
            <span id="url-count" style="font-size:.8rem;color:var(--color-text-secondary);"></span>
        </div>
    </div>
</div>

<!-- ── Warning banner (shown when > 50 URLs) ───────────────────────────── -->
<div id="warn-banner" style="display:none;">
    <span id="warn-text"></span>
    <div style="display:flex;gap:.5rem;">
        <button id="btn-warn-proceed" class="btn btn-accent btn-sm">Proceed anyway</button>
        <button id="btn-warn-cancel"  class="btn btn-ghost btn-sm">Cancel</button>
    </div>
</div>

<!-- ── Results panel ────────────────────────────────────────────────────── -->
<div id="results-panel" class="panel" style="display:none;">
    <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
        <span class="panel-title">Results</span>
        <button id="btn-stop" class="btn btn-danger btn-sm" style="display:none;">■ Stop</button>
    </div>
    <div class="panel-body" style="padding:1rem 1.5rem;">
        <div id="progress-bar-wrap"><div id="progress-bar-fill"></div></div>
        <div id="summary-bar"></div>
        <div id="results-table-wrap">
            <table id="results-table">
                <thead>
                    <tr>
                        <th style="width:32px;">#</th>
                        <th style="width:90px;">Status</th>
                        <th>URL</th>
                        <th>Title</th>
                        <th style="width:90px;">Confidence</th>
                        <th style="width:60px;">Detail</th>
                    </tr>
                </thead>
                <tbody id="results-body"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const SOFT_LIMIT        = 50;
    const INTER_REQUEST_MS  = 5000; // delay between fresh (non-cached) scrapes

    // ── DOM refs ────────────────────────────────────────────────────────────
    const textarea      = document.getElementById('bulk-textarea');
    const btnStart      = document.getElementById('btn-start');
    const btnClear      = document.getElementById('btn-clear');
    const btnStop       = document.getElementById('btn-stop');
    const urlCountEl    = document.getElementById('url-count');
    const warnBanner    = document.getElementById('warn-banner');
    const warnText      = document.getElementById('warn-text');
    const btnWarnProceed= document.getElementById('btn-warn-proceed');
    const btnWarnCancel = document.getElementById('btn-warn-cancel');
    const resultsPanel  = document.getElementById('results-panel');
    const progressFill  = document.getElementById('progress-bar-fill');
    const summaryBar    = document.getElementById('summary-bar');
    const resultsBody   = document.getElementById('results-body');

    // ── State ───────────────────────────────────────────────────────────────
    let queue     = [];   // { index, url, row } objects
    let stats     = { total:0, done:0, success:0, error:0, cached:0, invalid:0 };
    let stopped   = false;
    let running   = false;

    // ── URL-count hint ──────────────────────────────────────────────────────
    textarea.addEventListener('input', updateCount);
    function updateCount() {
        const urls = parseLines(textarea.value);
        const n = urls.filter(u => u.valid).length;
        const invalid = urls.filter(u => !u.valid && u.raw).length;
        urlCountEl.textContent = n
            ? n + ' URL' + (n !== 1 ? 's' : '') + (invalid ? ' · ' + invalid + ' invalid' : '')
            : '';
    }

    // ── Parse textarea ──────────────────────────────────────────────────────
    function parseLines(text) {
        return text.split('\n').map(raw => {
            const trimmed = raw.trim();
            if (!trimmed || trimmed.startsWith('#')) return { raw: '', skip: true };
            const valid = /^https?:\/\/.+/i.test(trimmed);
            return { raw: trimmed, valid };
        }).filter(l => !l.skip);
    }

    // ── Clear ────────────────────────────────────────────────────────────────
    btnClear.addEventListener('click', () => {
        if (running) return;
        textarea.value = '';
        urlCountEl.textContent = '';
        resultsPanel.style.display = 'none';
        warnBanner.style.display = 'none';
    });

    // ── Start ────────────────────────────────────────────────────────────────
    btnStart.addEventListener('click', () => {
        if (running) return;
        const lines = parseLines(textarea.value);
        if (lines.length === 0) return;

        if (lines.filter(l => l.valid).length === 0 && lines.length > 0) {
            alert('No valid URLs found. Make sure each URL starts with http:// or https://');
            return;
        }

        if (lines.filter(l => l.valid).length > SOFT_LIMIT) {
            const n    = lines.filter(l => l.valid).length;
            const mins = Math.ceil((n * (INTER_REQUEST_MS / 1000)) / 60);
            warnText.textContent = `⚠ This batch has ${n} URLs and could take up to ~${mins} minutes. Proceed?`;
            warnBanner.style.display = 'flex';
            return;
        }

        startImport(lines);
    });

    btnWarnProceed.addEventListener('click', () => {
        warnBanner.style.display = 'none';
        startImport(parseLines(textarea.value));
    });
    btnWarnCancel.addEventListener('click', () => {
        warnBanner.style.display = 'none';
    });

    // ── Stop ─────────────────────────────────────────────────────────────────
    btnStop.addEventListener('click', () => { stopped = true; });

    // ── Main import flow ─────────────────────────────────────────────────────
    function startImport(lines) {
        stopped  = false;
        running  = true;
        stats    = { total: lines.length, done: 0, success: 0, error: 0, cached: 0, invalid: 0 };
        queue    = [];

        // Prepare UI
        resultsBody.innerHTML = '';
        resultsPanel.style.display = 'block';
        btnStart.disabled = true;
        btnClear.disabled = true;
        btnStop.style.display = '';
        progressFill.style.width = '0%';
        updateSummary();

        // Render all rows as pending / invalid immediately
        lines.forEach((line, i) => {
            const tr = document.createElement('tr');
            tr.id = 'row-' + i;
            if (!line.valid) {
                tr.className = 'tr-invalid';
                tr.innerHTML = `
                    <td>${i + 1}</td>
                    <td><span class="badge badge-invalid">Invalid</span></td>
                    <td class="url-cell" title="${esc(line.raw)}">${esc(line.raw) || '<em>blank</em>'}</td>
                    <td colspan="3" style="color:var(--color-text-secondary);font-size:.78rem;">
                        Not a valid http/https URL — skipped
                    </td>`;
                stats.invalid++;
                stats.done++;
            } else {
                tr.className = 'tr-pending';
                tr.innerHTML = `
                    <td>${i + 1}</td>
                    <td><span class="badge badge-pending" id="badge-${i}">Pending</span></td>
                    <td class="url-cell" title="${esc(line.raw)}">${esc(line.raw)}</td>
                    <td id="title-${i}" class="title-cell">—</td>
                    <td id="conf-${i}"  class="conf-mini">—</td>
                    <td id="link-${i}"></td>`;
                queue.push({ index: i, url: line.raw, row: tr });
            }
            resultsBody.appendChild(tr);
        });

        updateSummary();
        processQueue();
    }

    async function processQueue() {
        for (let qi = 0; qi < queue.length; qi++) {
            if (stopped) {
                markStopped(qi);
                break;
            }

            const item = queue[qi];
            setRowFetching(item.index);
            updateSummary('Fetching ' + (qi + 1) + ' / ' + queue.length + '…');

            let wasCached = false;
            try {
                const res  = await fetch('/api/parser.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ url: item.url }),
                });
                const data = await res.json();

                if (res.ok && data.status !== 'error') {
                    wasCached = !!data._cached;
                    setRowSuccess(item.index, data, wasCached);
                    stats.success++;
                    if (wasCached) stats.cached++;
                } else {
                    setRowError(item.index, data.code || 'ERROR', data.userMessage || 'Unknown error');
                    stats.error++;
                }
            } catch (err) {
                setRowError(item.index, 'NETWORK_ERROR', 'Request failed: ' + err.message);
                stats.error++;
            }

            stats.done++;
            updateProgress();
            updateSummary();

            // Wait 5 s before next URL only if this was a fresh scrape (not cached)
            if (!wasCached && qi < queue.length - 1 && !stopped) {
                await sleep(INTER_REQUEST_MS);
            }
        }

        finishImport();
    }

    // ── Row state helpers ────────────────────────────────────────────────────
    function setRowFetching(i) {
        const tr = document.getElementById('row-' + i);
        if (tr) tr.className = 'tr-fetching';
        const badge = document.getElementById('badge-' + i);
        if (badge) { badge.className = 'badge badge-fetching'; badge.textContent = 'Fetching…'; }
    }

    function setRowSuccess(i, data, cached) {
        const tr = document.getElementById('row-' + i);
        if (tr) tr.className = cached ? 'tr-cached' : 'tr-success';

        const badge = document.getElementById('badge-' + i);
        if (badge) {
            if (cached) {
                badge.className = 'badge badge-cached';
                badge.textContent = 'Cached';
            } else {
                badge.className = 'badge badge-success';
                badge.textContent = 'Success';
            }
        }

        const titleEl = document.getElementById('title-' + i);
        if (titleEl) titleEl.textContent = data.title || '(no title)';

        const confEl = document.getElementById('conf-' + i);
        if (confEl) {
            const score = data.confidence_score || data.confidenceScore;
            confEl.textContent = score ? score + '%' : '—';
        }

        // Link to extraction detail — derive from extraction_id if available, else use search
        const linkEl = document.getElementById('link-' + i);
        if (linkEl) {
            const id = data.extraction_id || data.id || null;
            if (id) {
                linkEl.innerHTML = `<a href="/admin/extraction-detail.php?id=${parseInt(id)}" class="btn btn-ghost btn-sm" target="_blank">View</a>`;
            } else {
                const encoded = encodeURIComponent(new URL(queue.find(q=>q.index===i)?.url||'/').hostname);
                linkEl.innerHTML = `<a href="/admin/extractions.php?domain=${encoded}" class="btn btn-ghost btn-sm" target="_blank">Search</a>`;
            }
        }
    }

    function setRowError(i, code, message) {
        const tr = document.getElementById('row-' + i);
        if (tr) tr.className = 'tr-error';

        const badge = document.getElementById('badge-' + i);
        if (badge) { badge.className = 'badge badge-error'; badge.textContent = 'Error'; }

        const titleEl = document.getElementById('title-' + i);
        if (titleEl) {
            titleEl.textContent = code;
            titleEl.style.color = 'var(--color-error, #c0392b)';
            titleEl.title = message;
        }
    }

    function markStopped(fromIndex) {
        for (let qi = fromIndex; qi < queue.length; qi++) {
            const item = queue[qi];
            const badge = document.getElementById('badge-' + item.index);
            if (badge && badge.textContent === 'Pending') {
                badge.className  = 'badge badge-warning';
                badge.textContent = 'Skipped';
            }
        }
    }

    // ── Progress / summary ───────────────────────────────────────────────────
    function updateProgress() {
        const pct = stats.total > 0 ? (stats.done / stats.total) * 100 : 0;
        progressFill.style.width = pct + '%';
    }

    function updateSummary(extra) {
        const parts = [];
        if (stats.total > 0) {
            parts.push(stats.done + ' / ' + stats.total + ' processed');
        }
        if (stats.success > 0) {
            const fresh = stats.success - stats.cached;
            if (fresh > 0)        parts.push('<span style="color:#2a7a2a;">' + fresh + ' new</span>');
            if (stats.cached > 0) parts.push('<span style="color:#2a4a8a;">' + stats.cached + ' cached</span>');
        }
        if (stats.error > 0)   parts.push('<span style="color:#c0392b;">' + stats.error + ' failed</span>');
        if (stats.invalid > 0) parts.push(stats.invalid + ' invalid');
        if (extra)             parts.push('<em>' + extra + '</em>');
        summaryBar.innerHTML = parts.join(' &nbsp;·&nbsp; ');
    }

    function finishImport() {
        running = false;
        btnStart.disabled = false;
        btnClear.disabled = false;
        btnStop.style.display = 'none';
        progressFill.style.width = '100%';

        const msg = stopped
            ? 'Import stopped. '
            : 'Import complete. ';
        updateSummary(msg + stats.success + ' succeeded, ' + stats.error + ' failed.');
    }

    // ── Utilities ─────────────────────────────────────────────────────────────
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // Init count on load
    updateCount();
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>

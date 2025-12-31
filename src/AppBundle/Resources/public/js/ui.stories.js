let activeTab = 'modular';
let activeModularIndex = 0;

function setTab(tab, index) {
    const modularContent = document.getElementById('tab-modular-content');
    const villainContent = document.getElementById('tab-villain-content');

    activeTab = tab;
    if (typeof index !== 'undefined') activeModularIndex = index;

    // reset all tab button styles
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.style.background = '#eee'; b.style.color = '#222'; });

    if (tab === 'modular') {
        // show modular, hide others
        modularContent.style.display = 'block';
        villainContent.style.display = 'none';
        var stdContent = document.getElementById('tab-standard-content'); if (stdContent) stdContent.style.display = 'none';
        var expContent = document.getElementById('tab-expert-content'); if (expContent) expContent.style.display = 'none';
        const btn = document.getElementById('tab-modular-' + activeModularIndex);
        if (btn) { btn.style.background = '#ce7222ff'; btn.style.color = '#fff'; }
        updatePanels('modular', activeModularIndex);
    } else if (tab === 'villain') {
        // show villain, hide others
        modularContent.style.display = 'none';
        villainContent.style.display = 'block';
        var stdContent = document.getElementById('tab-standard-content'); if (stdContent) stdContent.style.display = 'none';
        var expContent = document.getElementById('tab-expert-content'); if (expContent) expContent.style.display = 'none';
        const btn = document.getElementById('tab-villain');
        if (btn) { btn.style.background = '#4b2066'; btn.style.color = '#fff'; }
        updatePanels('villain');
    } else if (tab === 'standard') {
        // show standard, hide others
        modularContent.style.display = 'none';
        villainContent.style.display = 'none';
        const stdContent = document.getElementById('tab-standard-content');
        if (stdContent) stdContent.style.display = 'block';
        var expContent = document.getElementById('tab-expert-content'); if (expContent) expContent.style.display = 'none';
        const btn = document.getElementById('tab-standard');
        if (btn) { btn.style.background = '#2a6f9eff'; btn.style.color = '#fff'; }
        updatePanels('standard');
    } else if (tab === 'expert') {
        // show expert, hide others
        modularContent.style.display = 'none';
        villainContent.style.display = 'none';
        const expContent = document.getElementById('tab-expert-content');
        if (expContent) expContent.style.display = 'block';
        var stdContent2 = document.getElementById('tab-standard-content'); if (stdContent2) stdContent2.style.display = 'none';
        const btn = document.getElementById('tab-expert');
        if (btn) { btn.style.background = '#6f2a9eff'; btn.style.color = '#fff'; }
        updatePanels('expert');
    }
}

function updatePanels(tab, index) {
    if (tab === 'modular') {
        const code = document.getElementById('modular-sets-' + index).value;
        document.querySelectorAll('.set-infos-panel, .set-cards-panel').forEach(el => el.style.display = 'none');
        const infos = document.getElementById('infos-modular-' + code);
        const cards = document.getElementById('cards-modular-' + code);
        if (infos) infos.style.display = '';
        if (cards) cards.style.display = '';
    } else if (tab === 'villain') {
        const code = document.getElementById('villain-sets').value;
        document.querySelectorAll('.villain-cards-panel').forEach(el => el.style.display = 'none');
        const infos = document.getElementById('villain-cards-' + code);
        if (infos) infos.style.display = '';
    } else if (tab === 'standard') {
        const code = document.getElementById('standard-sets').value;
        document.querySelectorAll('.set-infos-panel, .set-cards-panel').forEach(el => el.style.display = 'none');
        const infos = document.getElementById('infos-standard-' + code);
        const cards = document.getElementById('cards-standard-' + code);
        if (infos) infos.style.display = '';
        if (cards) cards.style.display = '';
    } else if (tab === 'expert') {
        const code = document.getElementById('expert-sets').value;
        document.querySelectorAll('.set-infos-panel, .set-cards-panel').forEach(el => el.style.display = 'none');
        const infos = document.getElementById('infos-expert-' + code);
        const cards = document.getElementById('cards-expert-' + code);
        if (infos) infos.style.display = '';
        if (cards) cards.style.display = '';
    }
}

function getActiveTab() { return activeTab; }

function updateCombinedStatsPanel() {
    const villainCode = document.getElementById('villain-sets').value;
    // collect only visible modular selects values to build comma-separated list
    const allModularSelects = Array.prototype.slice.call(document.querySelectorAll('[id^="modular-sets-"]'));
    const modularCodes = allModularSelects.filter(function(s){
        // find nearest wrapper with class 'modular-select' if present
        var wrapper = s.closest ? s.closest('.modular-select') : null;
        if (!wrapper) return true; // if no wrapper, include by default
        var disp = window.getComputedStyle(wrapper).display;
        return disp !== 'none';
    }).map(s => s.value).filter(Boolean);
    const modularParam = modularCodes.join(',');
    const panel = document.getElementById('combined-stats-panel');
    const standardSel = document.getElementById('standard-sets');
    const expertSel = document.getElementById('expert-sets');
    const extra = [];
    if (standardSel && standardSel.value) extra.push(standardSel.value);
    if (expertSel && expertSel.value) extra.push(expertSel.value);
    const allMods = modularCodes.concat(extra);
    const finalParam = allMods.join(',');
    // include show_permanent flag if the control exists
    var showPermanentFlag = '1';
    try { var spbtn = document.getElementById('show-permanent-btn'); if (spbtn && (spbtn.getAttribute('data-show') === '0')) showPermanentFlag = '0'; } catch(e){}
    fetch(`/combined-stats?modular=${encodeURIComponent(finalParam)}&villain=${villainCode}&show_permanent=${showPermanentFlag}`)
        .then(response => response.text())
        .then(html => {
            if (panel) panel.innerHTML = html;
        });
}

function applyShowPermanentFilter() {
    // hide/show rows in all card lists
    var show = true;
    try { var sp = document.getElementById('show-permanent-btn'); if (sp && sp.getAttribute('data-show') === '0') show = false; } catch(e){}
    document.querySelectorAll('tr[data-permanent="1"]').forEach(function(row){ row.style.display = show ? '' : 'none'; });
    // refresh combined stats panel
    updateCombinedStatsPanel();
    // refresh visible per-set stats panels by fetching server-rendered stats for each visible panel
    refreshVisibleSetInfoPanels();
}

function refreshVisibleSetInfoPanels() {
    // For each visible infos-* panel, fetch stats for that set (pass show_permanent)
    var spFlag = '1'; try { var spb = document.getElementById('show-permanent-btn'); if (spb && spb.getAttribute('data-show') === '0') spFlag = '0'; } catch(e){}
    // villain panels (villain-cards-<code>) -> fetch with villain=code and modular empty
    document.querySelectorAll('.villain-cards-panel').forEach(function(panel){ if (panel.style.display === 'none') return; var code = panel.id.replace('villain-cards-',''); fetch(`/combined-stats?villain=${encodeURIComponent(code)}&show_permanent=${spFlag}`).then(r=>r.text()).then(html=>{ try{ var container = panel; var tmp = document.createElement('div'); tmp.innerHTML = html; var stats = tmp.querySelector('.stats-flex-main'); if (stats) { var existing = container.querySelector('.stats-flex-main'); if (existing) existing.parentNode.replaceChild(stats, existing); } }catch(e){} }); });
    // modular/standard/expert panels (infos-<type>-<code>) -> fetch with modular=code and villain empty
    ['infos-modular-','infos-standard-','infos-expert-'].forEach(function(prefix){ document.querySelectorAll('[id^="'+prefix+'"]').forEach(function(panel){ if (panel.style.display === 'none') return; var code = panel.id.replace(prefix,''); fetch(`/combined-stats?modular=${encodeURIComponent(code)}&show_permanent=${spFlag}`).then(r=>r.text()).then(html=>{ try{ var tmp = document.createElement('div'); tmp.innerHTML = html; var stats = tmp.querySelector('.stats-flex-main'); if (stats) { var existing = panel.querySelector('.stats-flex-main'); if (existing) existing.parentNode.replaceChild(stats, existing); } }catch(e){} }); }); });

}
document.addEventListener('DOMContentLoaded', function() {
    console.debug('ui.stories: DOMContentLoaded');
    // bind villain tab
    const tv = document.getElementById('tab-villain'); if (tv) tv.addEventListener('click', function(){ setTab('villain'); });
    // bind modular tab buttons
    document.querySelectorAll('.tab-modular-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            const idx = parseInt(this.getAttribute('data-index'),10) || 0;
            setTab('modular', idx);
        });
    });
    // bind standard and expert tabs
    const ts = document.getElementById('tab-standard'); if (ts) ts.addEventListener('click', function(){ setTab('standard'); });
    const te = document.getElementById('tab-expert'); if (te) te.addEventListener('click', function(){ setTab('expert'); });

    console.debug('ui.stories: tab buttons', { tv: !!tv, ts: !!ts, te: !!te });

    // hide expert tab if expert select is none/empty
    (function(){
        const expertSelLocal = document.getElementById('expert-sets');
        const expertBtn = document.getElementById('tab-expert');
        const expertContent = document.getElementById('tab-expert-content');
        function updateExpertVisibility(){
            try{
                if (!expertSelLocal || !expertBtn) return;
                const v = (expertSelLocal.value || '').trim();
                if (v === '' || v === 'none') {
                    expertBtn.style.display = 'none';
                    if (expertContent) expertContent.style.display = 'none';
                } else {
                    expertBtn.style.display = '';
                }
            }catch(e){}
        }
        updateExpertVisibility();
        if (expertSelLocal) expertSelLocal.addEventListener('change', updateExpertVisibility);
    })();

    // hide standard tab if standard select is none/empty (same behaviour as expert)
    (function(){
        const standardSelLocal = document.getElementById('standard-sets');
        const standardBtn = document.getElementById('tab-standard');
        const standardContent = document.getElementById('tab-standard-content');
        function updateStandardVisibility(){
            try{
                if (!standardSelLocal || !standardBtn) return;
                const v = (standardSelLocal.value || '').trim();
                if (v === '' || v === 'none') {
                    standardBtn.style.display = 'none';
                    if (standardContent) standardContent.style.display = 'none';
                } else {
                    standardBtn.style.display = '';
                }
            }catch(e){}
        }
        updateStandardVisibility();
        if (standardSelLocal) standardSelLocal.addEventListener('change', updateStandardVisibility);
    })();

    // bind modular selects
    document.querySelectorAll('[id^="modular-sets-"]').forEach(function(sel){ sel.addEventListener('change', function(){ updatePanels(getActiveTab(), activeModularIndex); updateCombinedStatsPanel(); }); });

    // bind standard/expert selects to update panels when changed
    const standardSel = document.getElementById('standard-sets'); if (standardSel) standardSel.addEventListener('change', function(){ updatePanels(getActiveTab(), activeModularIndex); updateCombinedStatsPanel(); });
    const expertSel = document.getElementById('expert-sets'); if (expertSel) expertSel.addEventListener('change', function(){ updatePanels(getActiveTab(), activeModularIndex); updateCombinedStatsPanel(); });


    // handle number of modulars input (if present)
    const numInput = document.getElementById('num-modulars');
    function applyNumModulars() {
        if (!numInput) return;
        let n = parseInt(numInput.value, 10) || 1;
        // cap at available selects
        const allSelects = Array.prototype.slice.call(document.querySelectorAll('.modular-select'));
        const max = allSelects.length;
        if (n > max) n = max;
        // show/hide selects
        allSelects.forEach(function(div){ const idx = parseInt(div.getAttribute('data-index'),10); if (idx < n) div.style.display = ''; else div.style.display = 'none'; });
        // show/hide tab buttons
        document.querySelectorAll('.tab-modular-btn').forEach(function(btn){ const idx = parseInt(btn.getAttribute('data-index'),10); if (idx < n) btn.style.display = ''; else btn.style.display = 'none'; });
        // ensure active index valid
        if (activeModularIndex >= n) { activeModularIndex = 0; setTab('modular', activeModularIndex); }
        updatePanels(getActiveTab(), activeModularIndex);
        updateCombinedStatsPanel();
    }
    if (numInput) {
        numInput.addEventListener('change', applyNumModulars);
        // initialize visibility based on current value
        applyNumModulars();
    }

    const vsel = document.getElementById('villain-sets'); if (vsel) vsel.addEventListener('change', function(){ updatePanels(getActiveTab(), activeModularIndex); updateCombinedStatsPanel(); });

    // bind show permanent toggle
    const spbtn = document.getElementById('show-permanent-btn');
    if (spbtn) {
        // default selected
        spbtn.classList.add('active');
        spbtn.setAttribute('data-show','1');
        spbtn.addEventListener('click', function(){
            const cur = spbtn.getAttribute('data-show') === '1';
            spbtn.setAttribute('data-show', cur ? '0' : '1');
            spbtn.classList.toggle('active');
            applyShowPermanentFilter();
        });
    }



    // default to modular 0
    setTab('modular', 0);
    updateCombinedStatsPanel();
});


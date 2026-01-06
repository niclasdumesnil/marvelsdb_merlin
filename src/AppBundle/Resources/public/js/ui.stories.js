let activeTab = 'modular';
let activeModularIndex = 0;
let modularsWarnTimeout = null;

function showModularsWarning(msg) {
    try {
        const el = document.getElementById('modulars-warning');
        if (!el) return;
        if (msg === null || msg === '') {
            el.style.display = 'none';
            if (modularsWarnTimeout) { clearTimeout(modularsWarnTimeout); modularsWarnTimeout = null; }
            return;
        }
        el.textContent = msg || 'Not enough modulars available to satisfy filters';
        el.style.display = '';
        if (modularsWarnTimeout) clearTimeout(modularsWarnTimeout);
        modularsWarnTimeout = setTimeout(function(){ try { el.style.display = 'none'; } catch(e){} modularsWarnTimeout = null; }, 5000);
    } catch(e) { console.warn('showModularsWarning error', e); }
}

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

    // Clear filters button
    const clearBtn = document.getElementById('clear-story-filters-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function(){
            try {
                document.querySelectorAll('.story-type-counter').forEach(function(inp){ inp.value = '0'; inp.classList.remove('no-match'); try{ inp.dispatchEvent(new Event('input')); inp.dispatchEvent(new Event('change')); }catch(e){} });
                try { if (window.filterStoryCounters) window.filterStoryCounters(); } catch(e) {}
                updateTabLabels();
                updateCombinedStatsPanel();
            } catch(e) { console.warn('clear filters error', e); }
        });
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

function updateTabLabels() {
    try {
        // villain
        const villainSel = document.getElementById('villain-sets');
        const villainBtn = document.getElementById('tab-villain');
        if (villainSel && villainBtn) {
            const opt = villainSel.options[villainSel.selectedIndex];
            villainBtn.textContent = opt ? opt.text : 'Villain';
        }
        // standard
        const standardSel = document.getElementById('standard-sets');
        const standardBtn = document.getElementById('tab-standard');
        if (standardSel && standardBtn) {
            const opt = standardSel.options[standardSel.selectedIndex];
            standardBtn.textContent = opt && opt.text.trim() !== '' ? opt.text : 'Standard';
        }
        // expert
        const expertSel = document.getElementById('expert-sets');
        const expertBtn = document.getElementById('tab-expert');
        if (expertSel && expertBtn) {
            const opt = expertSel.options[expertSel.selectedIndex];
            expertBtn.textContent = opt && opt.text.trim() !== '' ? opt.text : 'Expert';
        }
        // modulars
        document.querySelectorAll('[id^="modular-sets-"]').forEach(function(sel){
            const idx = sel.id.replace('modular-sets-','');
            const btn = document.getElementById('tab-modular-' + idx);
            if (!btn) return;
            const opt = sel.options[sel.selectedIndex];
            btn.textContent = opt && opt.text ? opt.text : ('Modular ' + (parseInt(idx,10)+1));
        });
        // update FM badges to reflect selected options
        try { updateFMBadges(); } catch(e) {}
    } catch(e) { console.warn('updateTabLabels error', e); }
}

function updateFMBadges() {
    // villain
    try {
        const vsel = document.getElementById('villain-sets');
        const vb = document.getElementById('badge-villain-sets');
        const vb_creator = document.getElementById('badge-villain-creator');
        if (vsel && vb) {
            const opt = vsel.options[vsel.selectedIndex];
            if (opt && opt.getAttribute('data-fanmade') === '1') { vb.style.display = ''; } else { vb.style.display = 'none'; }
            try {
                if (vb_creator) {
                    const creator = opt ? (opt.getAttribute('data-creator') || '').toLowerCase() : '';
                    if (creator === 'ffg') { vb_creator.style.display = ''; } else { vb_creator.style.display = 'none'; }
                }
            } catch(e){}
        }
    } catch(e){}
    // standard
    try {
        const ssel = document.getElementById('standard-sets');
        const sb = document.getElementById('badge-standard-sets');
        const sb_creator = document.getElementById('badge-standard-creator');
        if (ssel && sb) {
            const opt = ssel.options[ssel.selectedIndex];
            if (opt && opt.getAttribute('data-fanmade') === '1') { sb.style.display = ''; } else { sb.style.display = 'none'; }
            try {
                if (sb_creator) {
                    const creator = opt ? (opt.getAttribute('data-creator') || '').toLowerCase() : '';
                    if (creator === 'ffg') { sb_creator.style.display = ''; } else { sb_creator.style.display = 'none'; }
                }
            } catch(e){}
        }
    } catch(e){}
    // expert
    try {
        const esel = document.getElementById('expert-sets');
        const eb = document.getElementById('badge-expert-sets');
        const eb_creator = document.getElementById('badge-expert-creator');
        if (esel && eb) {
            const opt = esel.options[esel.selectedIndex];
            if (opt && opt.getAttribute('data-fanmade') === '1') { eb.style.display = ''; } else { eb.style.display = 'none'; }
            try {
                if (eb_creator) {
                    const creator = opt ? (opt.getAttribute('data-creator') || '').toLowerCase() : '';
                    if (creator === 'ffg') { eb_creator.style.display = ''; } else { eb_creator.style.display = 'none'; }
                }
            } catch(e){}
        }
    } catch(e){}
    // modulars
    try {
        document.querySelectorAll('[id^="modular-sets-"]').forEach(function(sel){
            const idx = sel.id.replace('modular-sets-','');
            const b = document.getElementById('badge-modular-sets-' + idx);
            const b_creator = document.getElementById('badge-modular-creator-' + idx);
            if (!b) return;
            const wrapper = sel.closest ? sel.closest('.modular-select') : null;
            if (wrapper && window.getComputedStyle(wrapper).display === 'none') { b.style.display = 'none'; return; }
            const opt = sel.options[sel.selectedIndex];
            if (opt && opt.getAttribute('data-fanmade') === '1') { b.style.display = ''; } else { b.style.display = 'none'; }
            try {
                if (b_creator) {
                    const creator = opt ? (opt.getAttribute('data-creator') || '').toLowerCase() : '';
                    if (creator === 'ffg') { b_creator.style.display = ''; } else { b_creator.style.display = 'none'; }
                }
            } catch(e){}
        });
    } catch(e){}
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
    // Debug: trace calls (finalParam logged later after computed)
    try { console.debug('updateCombinedStatsPanel called (initial)', { villain: villainCode, modulars: modularParam }); } catch(e) {}
    const standardSel = document.getElementById('standard-sets');
    const expertSel = document.getElementById('expert-sets');
    const extra = [];
    if (standardSel && standardSel.value) extra.push(standardSel.value);
    if (expertSel && expertSel.value) extra.push(expertSel.value);
    const allMods = modularCodes.concat(extra);
    const finalParam = allMods.join(',');
    // Now we can safely log details including finalParam
    try { console.debug('updateCombinedStatsPanel details', { finalParam: finalParam, modularCount: allMods.length }); } catch(e) {}
    // include show_permanent flag if the control exists
    var showPermanentFlag = '1';
    try { var spbtn = document.getElementById('show-permanent-btn'); if (spbtn && (spbtn.getAttribute('data-show') === '0')) showPermanentFlag = '0'; } catch(e){}
    try {
        // Aggregate stats from existing per-set panels in DOM to avoid server calls.
        const panels = [];
        function tryAdd(sel) {
            // accept selector like '#id' or raw id
            var id = (typeof sel === 'string' && sel.charAt(0) === '#') ? sel.slice(1) : sel;
            var el = document.getElementById(id);
            if (!el) return;
            var stats = el.querySelector('.stats-flex-main');
            if (stats) panels.push({el: el, stats: stats});
        }
        // villain specific
        tryAdd('#villain-cards-' + villainCode);
        tryAdd('#villain-infos-' + villainCode);
        // modular / standard / expert panels
        allMods.forEach(function(code){
            tryAdd('#infos-modular-' + code);
            tryAdd('#infos-standard-' + code);
            tryAdd('#infos-expert-' + code);
            tryAdd('#cards-modular-' + code);
            tryAdd('#cards-standard-' + code);
            tryAdd('#cards-expert-' + code);
        });

        // Remove any server-rendered stats blocks inside the combined panel that are not our combined instance
        try {
            if (panel) {
                Array.prototype.slice.call(panel.querySelectorAll('.stats-flex-main, .stats-flex-row')).forEach(function(n){
                    if (!n.closest('.combined-stats-instance')) {
                        try { n.parentNode && n.parentNode.removeChild(n); } catch(e){}
                    }
                });
            }
        } catch(e) {}

        // fallback: if no panels found, keep server fetch as fallback (rare)
        if (panels.length === 0) {
            fetch(`/combined-stats?modular=${encodeURIComponent(finalParam)}&villain=${villainCode}&show_permanent=${showPermanentFlag}`)
                .then(response => response.text())
                .then(html => { if (panel) panel.innerHTML = html; });
            return;
        }

        // Aggregate numeric values by parsing the .stats-values spans order used by the template
        let agg = { totalCards:0, differentCards:0, totalBoost:0, totalBoostStar:0, avgWeightedSum:0 };
        // collect traits from all panels
        const traitsArr = [];
        panels.forEach(function(p){
            try {
                const spans = p.stats.querySelectorAll('.stats-values span');
                if (spans && spans.length >= 5) {
                    const total = parseInt(spans[0].textContent.trim().replace(/[^0-9\-]/g,''),10) || 0;
                    const diff = parseInt(spans[1].textContent.trim().replace(/[^0-9\-]/g,''),10) || 0;
                    const boost = parseInt(spans[2].textContent.trim().replace(/[^0-9\-]/g,''),10) || 0;
                    const stars = parseInt(spans[3].textContent.trim().replace(/[^0-9\-]/g,''),10) || 0;
                    const avg = parseFloat(spans[4].textContent.trim().replace(/[^0-9\-\.]/g,'')) || 0;
                    agg.totalCards += total;
                    agg.differentCards += diff;
                    agg.totalBoost += boost;
                    agg.totalBoostStar += stars;
                    agg.avgWeightedSum += avg * total;
                }
            } catch(e) { /* ignore per-panel parse errors */ }
            try {
                // collect header trait spans (if any) - template places traits in spans with margin-left style
                const headerSpans = p.stats.querySelectorAll('div > span[style*="margin-left"]');
                if (headerSpans && headerSpans.length > 1) {
                    for (let i = 1; i < headerSpans.length; i++) {
                        try {
                            const txt = headerSpans[i].textContent || '';
                            txt.split(',').map(s=>s.trim()).forEach(function(t){ if (t) traitsArr.push(t); });
                        } catch(e) {}
                    }
                }
            } catch(e) {}
        });
        const averageBoost = (agg.totalCards > 0) ? (agg.avgWeightedSum / agg.totalCards) : 0;

        // Build a new template that includes only the stats blocks (no card lists)
        const tplContainer = document.createElement('div');
        tplContainer.className = 'combined-stats-instance';
        try {
            const statsMainClone = panels[0].stats.cloneNode(true);
            // preserve original color for inner bars, then force banner to black
            const originalBg = statsMainClone.style.background || (statsMainClone.getAttribute('style')||'').match(/background:\s*([^;]+)/i)?.[1] || '#111';
            try { statsMainClone.style.background = '#000'; statsMainClone.style.color = '#fff'; } catch(e) {}
            // replace numeric values in cloned .stats-values while preserving any icon element
            function setValuePreserveIcon(spanEl, val) {
                if (!spanEl) return;
                // find first element child (icon)
                var firstEl = null;
                for (var c = spanEl.firstChild; c; c = c.nextSibling) { if (c.nodeType === 1) { firstEl = c; break; } }
                if (firstEl) {
                    // find existing text node after the icon
                    var found = false;
                    for (var n = firstEl.nextSibling; n; n = n.nextSibling) { if (n.nodeType === 3) { n.nodeValue = ' ' + String(val); found = true; break; } }
                    if (!found) firstEl.parentNode.insertBefore(document.createTextNode(' ' + String(val)), firstEl.nextSibling);
                } else {
                    spanEl.textContent = String(val);
                }
            }
            const tspan = statsMainClone.querySelectorAll('.stats-values span');
            if (tspan && tspan.length >= 5) {
                setValuePreserveIcon(tspan[0], agg.totalCards);
                setValuePreserveIcon(tspan[1], agg.differentCards);
                setValuePreserveIcon(tspan[2], agg.totalBoost);
                setValuePreserveIcon(tspan[3], agg.totalBoostStar);
                setValuePreserveIcon(tspan[4], averageBoost.toFixed(2));
            }
            // set header name: preserve any first element child (icon) and update/add following text node
            const setNameNode = statsMainClone.querySelector('div > span[style*="margin-left"]');
            if (setNameNode) {
                let iconEl = null;
                for (let c = setNameNode.firstChild; c; c = c.nextSibling) { if (c.nodeType === 1) { iconEl = c; break; } }
                if (iconEl) {
                    let foundText = false;
                    for (let n = iconEl.nextSibling; n; n = n.nextSibling) { if (n.nodeType === 3) { n.nodeValue = ' Combined sets'; foundText = true; break; } }
                    if (!foundText) { iconEl.parentNode.insertBefore(document.createTextNode(' Combined sets'), iconEl.nextSibling); }
                } else {
                    setNameNode.textContent = 'Combined sets';
                }
            }
            // ensure combined traits from all selected panels are shown (not only the first panel's traits)
            try {
                const uniqueTraits = Array.from(new Set(traitsArr));
                if (uniqueTraits.length > 0) {
                    const traitsText = uniqueTraits.join(', ');
                    // try find an existing trait span in the clone (icon with fa-tags)
                    const headerSpansClone = statsMainClone.querySelectorAll('div > span[style*="margin-left"]');
                    let traitSpanClone = null;
                    if (headerSpansClone && headerSpansClone.length > 1) {
                        for (let i = 1; i < headerSpansClone.length; i++) {
                            try { if (headerSpansClone[i].querySelector && headerSpansClone[i].querySelector('i.fas.fa-tags')) { traitSpanClone = headerSpansClone[i]; break; } } catch(e){}
                        }
                    }
                    if (traitSpanClone) {
                        // preserve icon element and update text
                        const iconEl = traitSpanClone.querySelector && traitSpanClone.querySelector('i');
                        traitSpanClone.textContent = '';
                        if (iconEl) traitSpanClone.appendChild(iconEl);
                        if (iconEl) traitSpanClone.appendChild(document.createTextNode(' ' + traitsText)); else traitSpanClone.textContent = traitsText;
                    } else {
                        // insert a new span after the set name
                        const span = document.createElement('span'); span.setAttribute('style','margin-left: 18px; font-weight: normal;');
                        span.innerHTML = '<i class="fas fa-tags" style="margin-right: 6px;"></i>' + traitsText;
                        const setNameNode2 = statsMainClone.querySelector('div > span[style*="margin-left"]');
                        if (setNameNode2 && setNameNode2.parentNode) setNameNode2.parentNode.insertBefore(span, setNameNode2.nextSibling);
                        else { const headerDiv = statsMainClone.querySelector('div'); if (headerDiv) headerDiv.appendChild(span); }
                    }
                }
            } catch(e) {}

            tplContainer.appendChild(statsMainClone);

            // build aggregated detailed stats (type + boost) from all panels
            try {
                const typeAgg = {};
                const boostAgg = {};
                panels.forEach(function(p){
                    try {
                        const typeRows = p.el.querySelectorAll('.stats-type > div');
                        if (typeRows) {
                            typeRows.forEach(function(r){
                                const labelEl = r.querySelector('.stats-label');
                                const countSpan = r.querySelector('span[style*="text-align: right"]');
                                if (!labelEl || !countSpan) return;
                                const label = labelEl.textContent.trim();
                                const m = countSpan.textContent.match(/(\d+)\s*\//);
                                const v = m ? parseInt(m[1],10) : (parseInt(countSpan.textContent,10)||0);
                                typeAgg[label] = (typeAgg[label] || 0) + v;
                            });
                        }
                        const boostRows = p.el.querySelectorAll('.stats-boost > div');
                        if (boostRows) {
                            boostRows.forEach(function(r){
                                const labelEl = r.querySelector('span');
                                const countSpan = r.querySelector('span[style*="text-align: right"]');
                                if (!labelEl || !countSpan) return;
                                // detect number of boost icons
                                const icons = labelEl.querySelectorAll('.icon.icon-boost');
                                var key = '0';
                                if (icons && icons.length > 0) {
                                    key = (icons.length >= 3) ? '3+' : String(icons.length);
                                }
                                const m = countSpan.textContent.match(/(\d+)\s*\//);
                                const v = m ? parseInt(m[1],10) : (parseInt(countSpan.textContent,10)||0);
                                boostAgg[key] = (boostAgg[key] || 0) + v;
                            });
                        }
                    } catch(e) {}
                });

                // construct details container similar to template
                const detailsDiv = document.createElement('div');
                detailsDiv.className = 'stats-flex-row';
                detailsDiv.style.display = 'flex';
                detailsDiv.style.gap = '32px';
                detailsDiv.style.marginBottom = '18px';
                detailsDiv.style.flexWrap = 'wrap';

                // types column
                const typesCol = document.createElement('div'); typesCol.className = 'stats-type'; typesCol.style.flex = '1'; typesCol.style.minWidth = '260px';
                const typesTitle = document.createElement('div'); typesTitle.style.fontWeight='bold'; typesTitle.style.marginBottom='8px'; typesTitle.style.display='flex'; typesTitle.style.alignItems='center'; typesTitle.innerHTML = '<i class="fas fa-tags" style="margin-right: 6px;"></i>Type';
                typesCol.appendChild(typesTitle);
                Object.keys(typeAgg).forEach(function(t){
                    const count = typeAgg[t] || 0;
                    const row = document.createElement('div'); row.style.display='flex'; row.style.alignItems='center'; row.style.marginBottom='6px';
                    const labelSpan = document.createElement('span'); labelSpan.className='stats-label'; labelSpan.style.width='120px'; labelSpan.style.minWidth='80px'; labelSpan.style.display='inline-block'; labelSpan.textContent = t;
                    const barWrapper = document.createElement('div'); barWrapper.className='stats-bar'; barWrapper.style.background='#fff'; barWrapper.style.borderRadius='4px'; barWrapper.style.height='18px'; barWrapper.style.flex='1'; barWrapper.style.margin='0 8px'; barWrapper.style.border='1px solid #ccc'; barWrapper.style.position='relative';
                    const innerBar = document.createElement('div'); innerBar.style.background = '#000'; innerBar.style.height='100%'; innerBar.style.width = (agg.totalCards>0?Math.floor(count/agg.totalCards*100):0) + '%'; innerBar.style.borderRadius='4px';
                    const innerSpan = document.createElement('span'); innerSpan.style.position='absolute'; innerSpan.style.left='8px'; innerSpan.style.top='0'; innerSpan.style.color='#fff'; innerSpan.style.fontSize='1em'; innerSpan.style.lineHeight='18px'; innerSpan.textContent = count;
                    barWrapper.appendChild(innerBar); barWrapper.appendChild(innerSpan);
                    const rightSpan = document.createElement('span'); rightSpan.style.width='32px'; rightSpan.style.textAlign='right'; rightSpan.textContent = count + '/' + (agg.totalCards||0);
                    row.appendChild(labelSpan); row.appendChild(barWrapper); row.appendChild(rightSpan);
                    typesCol.appendChild(row);
                });

                // boosts column
                const boostCol = document.createElement('div'); boostCol.className='stats-boost'; boostCol.style.flex='1'; boostCol.style.minWidth='180px';
                const boostTitle = document.createElement('div'); boostTitle.style.fontWeight='bold'; boostTitle.style.marginBottom='8px'; boostTitle.style.display='flex'; boostTitle.style.alignItems='center'; boostTitle.innerHTML = '<i class="fas fa-rocket" style="margin-right: 6px;"></i>Boost';
                boostCol.appendChild(boostTitle);
                // sort keys so 0,1,2,3+
                const boostKeys = Object.keys(boostAgg).sort(function(a,b){
                    const va = a==='3+'?3:parseInt(a,10)||0; const vb = b==='3+'?3:parseInt(b,10)||0; return va-vb;
                });
                boostKeys.forEach(function(k){
                    const count = boostAgg[k] || 0;
                    const row = document.createElement('div'); row.style.display='flex'; row.style.alignItems='center'; row.style.marginBottom='6px';
                    const labelSpan = document.createElement('span'); labelSpan.style.width='60px'; labelSpan.style.display='inline-block';
                    if (k === '0') { labelSpan.innerHTML = ''; } else if (k === '3+') { labelSpan.innerHTML = '<i class="icon icon-boost" style="margin-right:-3px;"></i><i class="icon icon-boost" style="margin-right:-3px;"></i><i class="icon icon-boost" style="margin-right:-3px;"></i> +'; } else { let html=''; for(let i=0;i<parseInt(k,10);i++){ html += '<i class="icon icon-boost" style="margin-right:-3px;"></i>'; } labelSpan.innerHTML = html; }
                    const barWrapper = document.createElement('div'); barWrapper.className='stats-bar'; barWrapper.style.background='#fff'; barWrapper.style.borderRadius='4px'; barWrapper.style.height='18px'; barWrapper.style.flex='1'; barWrapper.style.margin='0 8px'; barWrapper.style.border='1px solid #ccc'; barWrapper.style.position='relative';
                    const innerBar = document.createElement('div'); innerBar.style.background = '#000'; innerBar.style.height='100%'; innerBar.style.width = (agg.totalCards>0?Math.floor(count/agg.totalCards*100):0) + '%'; innerBar.style.borderRadius='4px';
                    const innerSpan = document.createElement('span'); innerSpan.style.position='absolute'; innerSpan.style.left='8px'; innerSpan.style.top='0'; innerSpan.style.color='#fff'; innerSpan.style.fontSize='1em'; innerSpan.style.lineHeight='18px'; innerSpan.textContent = count;
                    barWrapper.appendChild(innerBar); barWrapper.appendChild(innerSpan);
                    const rightSpan = document.createElement('span'); rightSpan.style.width='32px'; rightSpan.style.textAlign='right'; rightSpan.textContent = count + '/' + (agg.totalCards||0);
                    row.appendChild(labelSpan); row.appendChild(barWrapper); row.appendChild(rightSpan);
                    boostCol.appendChild(row);
                });

                detailsDiv.appendChild(typesCol);
                detailsDiv.appendChild(boostCol);
                tplContainer.appendChild(detailsDiv);
            } catch(e) {}
        } catch(e) { /* ignore construction errors */ }

                if (panel) {
                    try {
                        // if we already inserted an instance previously, replace the first and remove duplicates
                        const existingInstances = panel.querySelectorAll('.combined-stats-instance');
                        if (existingInstances && existingInstances.length > 0) {
                            // replace first instance
                            panel.replaceChild(tplContainer, existingInstances[0]);
                            // remove any additional leftover instances
                            for (let i = 1; i < existingInstances.length; i++) {
                                try { existingInstances[i].parentNode && existingInstances[i].parentNode.removeChild(existingInstances[i]); } catch(e) {}
                            }
                        } else {
                            // update existing main/details in-place to avoid flicker when possible
                            const existingMain = panel.querySelector('.stats-flex-main');
                            if (existingMain) {
                                try { existingMain.style.background = '#000'; existingMain.style.color = '#fff'; } catch(e) {}
                                const existingSpans = existingMain.querySelectorAll('.stats-values span');
                                if (existingSpans && existingSpans.length >= 5) {
                                    function setValuePreserveIcon(spanEl, val) {
                                        if (!spanEl) return;
                                        var firstEl = null;
                                        for (var c = spanEl.firstChild; c; c = c.nextSibling) { if (c.nodeType === 1) { firstEl = c; break; } }
                                        if (firstEl) {
                                            var found = false;
                                            for (var n = firstEl.nextSibling; n; n = n.nextSibling) { if (n.nodeType === 3) { n.nodeValue = ' ' + String(val); found = true; break; } }
                                            if (!found) firstEl.parentNode.insertBefore(document.createTextNode(' ' + String(val)), firstEl.nextSibling);
                                        } else {
                                            spanEl.textContent = String(val);
                                        }
                                    }
                                    setValuePreserveIcon(existingSpans[0], agg.totalCards);
                                    setValuePreserveIcon(existingSpans[1], agg.differentCards);
                                    setValuePreserveIcon(existingSpans[2], agg.totalBoost);
                                    setValuePreserveIcon(existingSpans[3], agg.totalBoostStar);
                                    setValuePreserveIcon(existingSpans[4], averageBoost.toFixed(2));
                                }
                                const nameNode = existingMain.querySelector('div > span[style*="margin-left"]');
                                if (nameNode) {
                                    let iconEl = null;
                                    for (let c = nameNode.firstChild; c; c = c.nextSibling) { if (c.nodeType === 1) { iconEl = c; break; } }
                                    if (iconEl) {
                                        let foundText = false;
                                        for (let n = iconEl.nextSibling; n; n = n.nextSibling) { if (n.nodeType === 3) { n.nodeValue = ' Combined sets'; foundText = true; break; } }
                                        if (!foundText) { iconEl.parentNode.insertBefore(document.createTextNode(' Combined sets'), iconEl.nextSibling); }
                                    } else {
                                        nameNode.textContent = 'Combined sets';
                                    }
                                }
                            } else {
                                panel.appendChild(statsMainClone);
                            }
                            const existingDetails = panel.querySelector('.stats-flex-row');
                            if (existingDetails) {
                                existingDetails.parentNode.replaceChild(detailsDiv, existingDetails);
                            } else {
                                panel.appendChild(detailsDiv);
                            }
                        }
                    } catch(e) { try { panel.appendChild(tplContainer); } catch(err){} }
                }
    } catch(e) {
        // fallback to server on unexpected error
        try { fetch(`/combined-stats?modular=${encodeURIComponent(finalParam)}&villain=${villainCode}&show_permanent=${showPermanentFlag}`).then(r=>r.text()).then(html=>{ if (panel) panel.innerHTML = html; }); } catch(_) {}
    }
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

function refreshVisibleSetInfoPanels(force) {
    // Debounce rapid calls
    try {
        if (!window._refreshVisiblePanelsTimer) window._refreshVisiblePanelsTimer = null;
        if (window._refreshVisiblePanelsTimer) { clearTimeout(window._refreshVisiblePanelsTimer); }
        window._refreshVisiblePanelsTimer = setTimeout(function(){ window._refreshVisiblePanelsTimer = null; }, 50);
    } catch(e){}
    force = !!force;
    // For each visible infos-* panel, fetch stats for that set (pass show_permanent)
    var spFlag = '1'; try { var spb = document.getElementById('show-permanent-btn'); if (spb && spb.getAttribute('data-show') === '0') spFlag = '0'; } catch(e){}
    // ensure a simple in-memory cache to avoid repeated fetches during the same page session
    try { if (!window.storyInfoCache) window.storyInfoCache = {}; } catch(e) { window.storyInfoCache = {}; }

    // villain panels (villain-cards-<code>) -> fetch with villain=code and modular empty
    document.querySelectorAll('.villain-cards-panel').forEach(function(panel){
        try {
            if (panel.style.display === 'none') return;
            var code = panel.id.replace('villain-cards-','');
            var cacheKey = 'villain:' + code;
            if (!force && window.storyInfoCache[cacheKey]) {
                try { var tmp = document.createElement('div'); tmp.innerHTML = window.storyInfoCache[cacheKey]; var stats = tmp.querySelector('.stats-flex-main'); if (stats) { var existing = panel.querySelector('.stats-flex-main'); if (existing) existing.parentNode.replaceChild(stats, existing); } } catch(e) {}
                return;
            }
            fetch(`/combined-stats?villain=${encodeURIComponent(code)}&show_permanent=${spFlag}`).then(r=>r.text()).then(html=>{
                try{ window.storyInfoCache[cacheKey] = html; var tmp = document.createElement('div'); tmp.innerHTML = html; var stats = tmp.querySelector('.stats-flex-main'); if (stats) { var existing = panel.querySelector('.stats-flex-main'); if (existing) existing.parentNode.replaceChild(stats, existing); } }catch(e){}
            });
        } catch(e) {}
    });

    // modular/standard/expert panels (infos-<type>-<code>) -> fetch with modular=code and villain empty
    ['infos-modular-','infos-standard-','infos-expert-'].forEach(function(prefix){
        document.querySelectorAll('[id^="'+prefix+'"]').forEach(function(panel){
            try {
                if (panel.style.display === 'none') return;
                var code = panel.id.replace(prefix,'');
                var cacheKey = prefix + code;
                if (!force && window.storyInfoCache[cacheKey]) {
                    try { var tmp = document.createElement('div'); tmp.innerHTML = window.storyInfoCache[cacheKey]; var stats = tmp.querySelector('.stats-flex-main'); if (stats) { var existing = panel.querySelector('.stats-flex-main'); if (existing) existing.parentNode.replaceChild(stats, existing); } } catch(e) {}
                    return;
                }
                fetch(`/combined-stats?modular=${encodeURIComponent(code)}&show_permanent=${spFlag}`).then(r=>r.text()).then(html=>{
                    try{ window.storyInfoCache[cacheKey] = html; var tmp = document.createElement('div'); tmp.innerHTML = html; var stats = tmp.querySelector('.stats-flex-main'); if (stats) { var existing = panel.querySelector('.stats-flex-main'); if (existing) existing.parentNode.replaceChild(stats, existing); } }catch(e){}
                });
            } catch(e) {}
        });
    });

}
document.addEventListener('DOMContentLoaded', function() {
    console.debug('ui.stories: DOMContentLoaded');
    // Initialize num-modulars from data-default-slots if present and manage default hint visibility
    (function(){
        try {
            const numInputLocal = document.getElementById('num-modulars');
            let userTouchedNum = false;
            if (numInputLocal && numInputLocal.dataset && numInputLocal.dataset.defaultSlots) {
                // ensure JS-side value matches template-provided default
                numInputLocal.value = numInputLocal.dataset.defaultSlots;
            }
            if (numInputLocal) {
                numInputLocal.addEventListener('change', function(){ userTouchedNum = true; applyNumModulars(); });
            }
            // parse default_modulars_map exposed by template
            let defaultModMap = {};
            try {
                const panel = document.getElementById('combined-stats-panel');
                if (panel && panel.dataset && panel.dataset.defaultModulars) {
                    defaultModMap = JSON.parse(panel.dataset.defaultModulars);
                }
            } catch(e) { }

            function updateDefaultFromScenario() {
                try {
                    const panel = document.getElementById('combined-stats-panel');
                    if (panel && panel.dataset && panel.dataset.callerProvided === '1') return; // respect caller-provided values
                    const vsel = document.getElementById('villain-sets');
                    const vcode = vsel ? (vsel.value || '') : '';
                    const normalized = (vcode || '').toLowerCase();
                    const def = (defaultModMap && defaultModMap[normalized]) ? parseInt(defaultModMap[normalized],10) : null;
                    const numEl = document.getElementById('num-modulars');
                    if (numEl && def !== null && !userTouchedNum) {
                        numEl.value = def;
                        applyNumModulars();
                    }
                } catch(e) { }
            }
            // expose for other handlers
            window.updateDefaultFromScenario = updateDefaultFromScenario;
            try { if (window.updateDefaultFromScenario) window.updateDefaultFromScenario(); } catch(e) {}
        } catch(e){}
    })();
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
    document.querySelectorAll('[id^="modular-sets-"]').forEach(function(sel){ sel.addEventListener('change', function(){ updatePanels(getActiveTab(), activeModularIndex); updateCombinedStatsPanel(); updateTabLabels(); }); });

    // bind standard/expert selects to update panels when changed
    const standardSel = document.getElementById('standard-sets'); if (standardSel) standardSel.addEventListener('change', function(){ updatePanels(getActiveTab(), activeModularIndex); updateCombinedStatsPanel(); updateTabLabels(); });
    const expertSel = document.getElementById('expert-sets'); if (expertSel) expertSel.addEventListener('change', function(){ updatePanels(getActiveTab(), activeModularIndex); updateCombinedStatsPanel(); updateTabLabels(); });


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

    const vsel = document.getElementById('villain-sets'); if (vsel) vsel.addEventListener('change', function(){ updatePanels(getActiveTab(), activeModularIndex); updateCombinedStatsPanel(); updateTabLabels(); try { if (window.updateDefaultFromScenario) window.updateDefaultFromScenario(); } catch(e){} });

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

    // bind randomize button (trigger only)
    const rndBtn = document.getElementById('randomize-btn');
    if (rndBtn) {
        rndBtn.addEventListener('click', function(){
            try {
                // helper: pick random option from select, respecting visibility, fanmade exclusion and type counters
                function pickRandomOption(sel, excludeFanmade){
                    if (!sel) return;
                    // build counters from current inputs
                    const counters = (window.getStoryCounters && typeof window.getStoryCounters === 'function') ? window.getStoryCounters() : (function(){ const o={}; document.querySelectorAll('.story-type-counter').forEach(i=>{ o[i.getAttribute('data-type')] = parseInt(i.value,10)||0; }); return o; })();
                    let opts = Array.prototype.slice.call(sel.options).filter(function(o){
                        // skip hidden/invisible options
                        if (o.hidden || (o.style && o.style.display === 'none')) return false;
                        if (excludeFanmade && o.getAttribute('data-fanmade') === '1') return false;
                        // ensure option meets all counters
                        for (const t in counters) {
                            const req = parseInt(counters[t] || 0, 10) || 0;
                            if (req <= 0) continue;
                            const attr = o.getAttribute('data-count-' + t) || '0';
                            const c = parseInt(attr, 10) || 0;
                            if (c < req) return false;
                        }
                        return true;
                    });
                    if (opts.length === 0) return;
                    const pick = opts[Math.floor(Math.random() * opts.length)];
                    sel.value = pick.value;
                }

                const excludeFanmadeVillain = document.getElementById('randomize-exclude-fm-villain');
                const excludeFanmadeModular = document.getElementById('randomize-exclude-fm-modular');
                const excludeStdExp = document.getElementById('randomize-exclude-std-exp');

                // villain
                const vsel = document.getElementById('villain-sets');
                pickRandomOption(vsel, excludeFanmadeVillain && excludeFanmadeVillain.checked);
                try { if (window.updateDefaultFromScenario) window.updateDefaultFromScenario(); } catch(e) {}

                // modulars (only visible selects) - pick unique modulars when multiple slots
                (function(){
                    try {
                        const visibleSelects = Array.prototype.slice.call(document.querySelectorAll('[id^="modular-sets-"]')).filter(function(sel){
                            const wrapper = sel.closest ? sel.closest('.modular-select') : null;
                            return !(wrapper && window.getComputedStyle(wrapper).display === 'none');
                        });
                        if (visibleSelects.length === 0) return;
                        // build pool of eligible modular codes
                        const counters = (window.getStoryCounters && typeof window.getStoryCounters === 'function') ? window.getStoryCounters() : (function(){ const o={}; document.querySelectorAll('.story-type-counter').forEach(i=>{ o[i.getAttribute('data-type')] = parseInt(i.value,10)||0; }); return o; })();
                        const poolMap = {};
                        visibleSelects.forEach(function(sel){ Array.prototype.slice.call(sel.options).forEach(function(o){
                            if (o.hidden || (o.style && o.style.display === 'none')) return;
                            if (excludeFanmadeModular && o.getAttribute('data-fanmade') === '1') return;
                            // ensure it meets counters
                            let ok = true;
                            for (const t in counters) {
                                const req = parseInt(counters[t] || 0, 10) || 0;
                                if (req <= 0) continue;
                                const attr = o.getAttribute('data-count-' + t) || '0';
                                const c = parseInt(attr, 10) || 0;
                                if (c < req) { ok = false; break; }
                            }
                            if (ok) poolMap[o.value] = o.value;
                        }); });
                        const pool = Object.keys(poolMap);
                        if (pool.length === 0) {
                            // nothing eligible
                            visibleSelects.forEach(function(sel){ sel.value = ''; });
                            showModularsWarning('Not enough modulars available to satisfy filters');
                            return;
                        }
                        // shuffle pool
                        for (let i=pool.length-1;i>0;i--){ const j=Math.floor(Math.random()*(i+1)); const tmp=pool[i]; pool[i]=pool[j]; pool[j]=tmp; }
                        if (pool.length >= visibleSelects.length) {
                            for (let i=0;i<visibleSelects.length;i++) { visibleSelects[i].value = pool[i]; }
                            showModularsWarning('');
                        } else {
                            // assign unique values for as many as possible, clear remaining
                            for (let i=0;i<pool.length;i++) { visibleSelects[i].value = pool[i]; }
                            for (let i=pool.length;i<visibleSelects.length;i++) { visibleSelects[i].value = ''; }
                            showModularsWarning('Not enough modulars available to satisfy filters');
                        }
                    } catch(e) { console.warn('unique modular randomize error', e); }
                })();

                // standard & expert (skip if exclude option checked)
                if (!(excludeStdExp && excludeStdExp.checked)) {
                    const ssel = document.getElementById('standard-sets'); pickRandomOption(ssel, false);
                    const esel = document.getElementById('expert-sets'); pickRandomOption(esel, false);
                }

                // refresh UI
                updateTabLabels();
                updatePanels(getActiveTab(), activeModularIndex);
                updateCombinedStatsPanel();
            } catch(e){ console.warn('randomize error', e); }
        });
    }

    // bind randomize modulars button (only randomize modular selects, preserve number of visible slots)
    const rndModBtn = document.getElementById('randomize-modulars-btn');
    if (rndModBtn) {
        rndModBtn.addEventListener('click', function(){
            try {
                function pickRandomOption(sel, excludeFanmade){
                    if (!sel) return;
                    const counters = (window.getStoryCounters && typeof window.getStoryCounters === 'function') ? window.getStoryCounters() : (function(){ const o={}; document.querySelectorAll('.story-type-counter').forEach(i=>{ o[i.getAttribute('data-type')] = parseInt(i.value,10)||0; }); return o; })();
                    let opts = Array.prototype.slice.call(sel.options).filter(function(o){
                        if (o.hidden || (o.style && o.style.display === 'none')) return false;
                        if (excludeFanmade && o.getAttribute('data-fanmade') === '1') return false;
                        for (const t in counters) {
                            const req = parseInt(counters[t] || 0, 10) || 0;
                            if (req <= 0) continue;
                            const attr = o.getAttribute('data-count-' + t) || '0';
                            const c = parseInt(attr, 10) || 0;
                            if (c < req) return false;
                        }
                        return true;
                    });
                    if (opts.length === 0) return;
                    const pick = opts[Math.floor(Math.random() * opts.length)];
                    sel.value = pick.value;
                }
                const excludeFanmadeModular = document.getElementById('randomize-exclude-fm-modular');
                // only operate on visible modular selects to preserve number of slots
                (function(){
                    try {
                        const visibleSelects = Array.prototype.slice.call(document.querySelectorAll('[id^="modular-sets-"]')).filter(function(sel){
                            const wrapper = sel.closest ? sel.closest('.modular-select') : null;
                            return !(wrapper && window.getComputedStyle(wrapper).display === 'none');
                        });
                        if (visibleSelects.length === 0) return;
                        const counters = (window.getStoryCounters && typeof window.getStoryCounters === 'function') ? window.getStoryCounters() : (function(){ const o={}; document.querySelectorAll('.story-type-counter').forEach(i=>{ o[i.getAttribute('data-type')] = parseInt(i.value,10)||0; }); return o; })();
                        const poolMap = {};
                        visibleSelects.forEach(function(sel){ Array.prototype.slice.call(sel.options).forEach(function(o){
                            if (o.hidden || (o.style && o.style.display === 'none')) return;
                            if (excludeFanmadeModular && o.getAttribute('data-fanmade') === '1') return;
                            let ok = true;
                            for (const t in counters) {
                                const req = parseInt(counters[t] || 0, 10) || 0;
                                if (req <= 0) continue;
                                const attr = o.getAttribute('data-count-' + t) || '0';
                                const c = parseInt(attr, 10) || 0;
                                if (c < req) { ok = false; break; }
                            }
                            if (ok) poolMap[o.value] = o.value;
                        }); });
                        const pool = Object.keys(poolMap);
                        if (pool.length === 0) {
                            visibleSelects.forEach(function(sel){ sel.value = ''; });
                            showModularsWarning('Not enough modulars available to satisfy filters');
                            return;
                        }
                        // shuffle pool
                        for (let i=pool.length-1;i>0;i--){ const j=Math.floor(Math.random()*(i+1)); const tmp=pool[i]; pool[i]=pool[j]; pool[j]=tmp; }
                        if (pool.length >= visibleSelects.length) {
                            for (let i=0;i<visibleSelects.length;i++) { visibleSelects[i].value = pool[i]; }
                            showModularsWarning('');
                        } else {
                            for (let i=0;i<pool.length;i++) { visibleSelects[i].value = pool[i]; }
                            for (let i=pool.length;i<visibleSelects.length;i++) { visibleSelects[i].value = ''; }
                            showModularsWarning('Not enough modulars available to satisfy filters');
                        }
                    } catch(e) { console.warn('unique modular randomize-modulars error', e); }
                })();

                // refresh UI
                updateTabLabels();
                updatePanels(getActiveTab(), activeModularIndex);
                updateCombinedStatsPanel();
            } catch(e){ console.warn('randomize-modulars error', e); }
        });
    }



    // default to modular 0
    setTab('modular', 0);
    updateTabLabels();
    updateCombinedStatsPanel();
    // ---- Story page: type counters filtering for modular sets ----
    (function(){
        try {
            function getCounters() {
                const out = {};
                document.querySelectorAll('.story-type-counter').forEach(function(inp){
                    const t = inp.getAttribute('data-type');
                    out[t] = parseInt(inp.value, 10) || 0;
                });
                return out;
            }

            function filterModularOptions() {
                const counters = getCounters();
                // for each modular select, hide options that do not satisfy all counters
                document.querySelectorAll('[id^="modular-sets-"]').forEach(function(sel){
                    let needChangeSelection = false;
                    Array.prototype.slice.call(sel.options).forEach(function(opt){
                        const c_minion = parseInt(opt.getAttribute('data-count-minion') || 0, 10);
                        const c_treachery = parseInt(opt.getAttribute('data-count-treachery') || 0, 10);
                        const c_attachment = parseInt(opt.getAttribute('data-count-attachment') || 0, 10);
                        const c_environment = parseInt(opt.getAttribute('data-count-environment') || 0, 10);
                        const c_side = parseInt(opt.getAttribute('data-count-side-scheme') || 0, 10);
                        let hide = false;
                        if ((counters['minion'] || 0) > 0 && c_minion < (counters['minion'] || 0)) hide = true;
                        if ((counters['treachery'] || 0) > 0 && c_treachery < (counters['treachery'] || 0)) hide = true;
                        if ((counters['attachment'] || 0) > 0 && c_attachment < (counters['attachment'] || 0)) hide = true;
                        if ((counters['environment'] || 0) > 0 && c_environment < (counters['environment'] || 0)) hide = true;
                        if ((counters['side-scheme'] || 0) > 0 && c_side < (counters['side-scheme'] || 0)) hide = true;
                        // set hidden attr so option disappears from dropdown while keeping DOM
                        try { opt.hidden = hide; } catch(e) { if (hide) opt.style.display = 'none'; else opt.style.display = ''; }
                        // if current selection has been hidden, note to change
                        if (opt.selected && hide) needChangeSelection = true;
                    });
                    if (needChangeSelection) {
                        // pick first non-hidden option if available
                        const visible = Array.prototype.slice.call(sel.options).find(function(o){ return !o.hidden && (o.style.display !== 'none'); });
                        if (visible) sel.value = visible.value; else sel.value = '';
                    }
                });
                // after filtering options, mark any type-counter that has no matching set
                try {
                    document.querySelectorAll('.story-type-counter').forEach(function(inp){
                        const t = inp.getAttribute('data-type');
                        const req = parseInt((counters[t] || 0), 10) || 0;
                        // clear state by default
                        inp.classList.remove('no-match');
                        if (!t || req <= 0) {
                            try { inp.removeAttribute('title'); } catch(e){}
                            return;
                        }
                        let found = false;
                        // check across all visible modular selects' visible options
                        document.querySelectorAll('[id^="modular-sets-"]').forEach(function(sel2){
                            const wrapper = sel2.closest ? sel2.closest('.modular-select') : null;
                            if (wrapper && window.getComputedStyle(wrapper).display === 'none') return;
                            Array.prototype.slice.call(sel2.options).forEach(function(opt){
                                if (opt.hidden || opt.style.display === 'none') return;
                                const attr = opt.getAttribute('data-count-' + t) || '0';
                                const c = parseInt(attr, 10) || 0;
                                if (c >= req) found = true;
                            });
                        });
                        if (!found) {
                            inp.classList.add('no-match');
                            try { inp.setAttribute('title', 'No set matches the requested minimum for ' + t); } catch(e){}
                        } else {
                            try { inp.removeAttribute('title'); } catch(e){}
                        }
                    });
                } catch(e) { console.warn('story-type no-match check error', e); }

                // refresh UI after filtering
                updateTabLabels();
                updateCombinedStatsPanel();
            }

            // attach events to inputs
            document.querySelectorAll('.story-type-counter').forEach(function(inp){
                inp.addEventListener('input', function(){ filterModularOptions(); });
                inp.addEventListener('change', function(){ filterModularOptions(); });
            });

            // expose counters/filter for external handlers (randomize/clear)
            try { window.getStoryCounters = getCounters; window.filterStoryCounters = filterModularOptions; } catch(e){}

            // initial pass (counters default to 0 so nothing hidden)
            try { filterModularOptions(); } catch(e) {}
        } catch(e) { console.warn('story counters init error', e); }
    })();
});


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
		modularContent.style.display = 'block';
		villainContent.style.display = 'none';
		const btn = document.getElementById('tab-modular-' + activeModularIndex);
		if (btn) { btn.style.background = '#ce7222ff'; btn.style.color = '#fff'; }
		updatePanels('modular', activeModularIndex);
	} else if (tab === 'villain') {
		modularContent.style.display = 'none';
		villainContent.style.display = 'block';
		const btn = document.getElementById('tab-villain');
		if (btn) { btn.style.background = '#4b2066'; btn.style.color = '#fff'; }
		updatePanels('villain');
	}
}

function updatePanels(tab, index) {
	if (tab === 'modular') {
		const code = document.getElementById('modular-sets-' + index).value;
		document.querySelectorAll('.set-infos-panel, .set-cards-panel').forEach(el => el.style.display = 'none');
		const infos = document.getElementById('infos-' + code);
		const cards = document.getElementById('cards-' + code);
		if (infos) infos.style.display = '';
		if (cards) cards.style.display = '';
	} else if (tab === 'villain') {
		const code = document.getElementById('villain-sets').value;
		document.querySelectorAll('.villain-cards-panel').forEach(el => el.style.display = 'none');
		const infos = document.getElementById('villain-cards-' + code);
		if (infos) infos.style.display = '';
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
	fetch(`/combined-stats?modular=${encodeURIComponent(modularParam)}&villain=${villainCode}`)
		.then(response => response.text())
		.then(html => {
			if (panel) panel.innerHTML = html;
		});
}

document.addEventListener('DOMContentLoaded', function() {
	// bind villain tab
	const tv = document.getElementById('tab-villain'); if (tv) tv.addEventListener('click', function(){ setTab('villain'); });
	// bind modular tab buttons
	document.querySelectorAll('.tab-modular-btn').forEach(function(btn){
		btn.addEventListener('click', function(){
			const idx = parseInt(this.getAttribute('data-index'),10) || 0;
			setTab('modular', idx);
		});
	});

	// bind modular selects
	document.querySelectorAll('[id^="modular-sets-"]').forEach(function(sel){ sel.addEventListener('change', function(){ updatePanels(getActiveTab(), activeModularIndex); updateCombinedStatsPanel(); }); });

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

	// default to modular 0
	setTab('modular', 0);
	updateCombinedStatsPanel();
});


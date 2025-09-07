let selectedModularCode, selectedVillainCode;

function setTab(tab) {
    const modularBtn = document.getElementById('tab-modular');
    const villainBtn = document.getElementById('tab-villain');
    const modularContent = document.getElementById('tab-modular-content');
    const villainContent = document.getElementById('tab-villain-content');

    if (tab === 'modular') {
        modularContent.style.display = 'block';
        villainContent.style.display = 'none';
        modularBtn.style.background = '#ce7222ff';
        modularBtn.style.color = '#fff';
        villainBtn.style.background = '#eee';
        villainBtn.style.color = '#222';
        updatePanels('modular');
    } else if (tab === 'villain') {
        modularContent.style.display = 'none';
        villainContent.style.display = 'block';
        modularBtn.style.background = '#eee';
        modularBtn.style.color = '#222';
        villainBtn.style.background = '#4b2066';
        villainBtn.style.color = '#fff';
        updatePanels('villain');
    }
}

function updatePanels(tab) {
    if (tab === 'modular') {
        const code = document.getElementById('modular-sets').value;
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

function getActiveTab() {
    const modularBtn = document.getElementById('tab-modular');
    const villainBtn = document.getElementById('tab-villain');
    if (modularBtn.style.background === 'rgb(206, 114, 34)' || modularBtn.style.background === '#ce7222ff') return 'modular';
    if (villainBtn.style.background === 'rgb(75, 32, 102)' || villainBtn.style.background === '#4b2066') return 'villain';
    return 'modular';
}

function updateCombinedStatsPanel() {
    const modularCode = document.getElementById('modular-sets').value;
    const villainCode = document.getElementById('villain-sets').value;
    fetch(`/combined-stats?modular=${modularCode}&villain=${villainCode}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('combined-stats-panel').innerHTML = html;
        });
}

document.addEventListener('DOMContentLoaded', function() {
    selectedModularCode = document.getElementById('modular-sets').value;
    selectedVillainCode = document.getElementById('villain-sets').value;

    document.getElementById('tab-modular').addEventListener('click', function() { setTab('modular'); });
    document.getElementById('tab-villain').addEventListener('click', function() { setTab('villain'); });

    document.getElementById('modular-sets').addEventListener('change', function() {
        updatePanels(getActiveTab());
        updateCombinedStatsPanel();
    });

    document.getElementById('villain-sets').addEventListener('change', function() {
        updatePanels(getActiveTab());
        updateCombinedStatsPanel();
    });

    setTab('modular');
    updateCombinedStatsPanel();
});
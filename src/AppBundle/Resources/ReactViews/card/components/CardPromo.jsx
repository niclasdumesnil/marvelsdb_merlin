import React, { useState, useEffect } from 'react';

/**
 * CardPromo — on-demand promo probing + localStorage cache
 * Probes promo images only when the user clicks a promo button (not on mount),
 * and caches discovered promo URLs in localStorage to avoid repeated probes.
 */
export default function CardPromo({ card, locale }) {
  const promoButtons = [
    { label: 'PROMO-FR', dir: 'promo-FR' },
    { label: 'PROMO-EN', dir: 'promo-EN' },
    { label: 'FFG-Rework', dir: 'alt-FFG' },
  ];

  const [chosenSrcMap, setChosenSrcMap] = useState({});
  const [activeDir, setActiveDir] = useState(null);
  const [loadingDirs, setLoadingDirs] = useState({});
  const [hiddenDirs, setHiddenDirs] = useState({});

  const imagesrc = card.imagesrc;
  if (!imagesrc) return null;

  const pathParts = imagesrc.split('/');
  const filename = pathParts[pathParts.length - 1];
  const basePath = pathParts.slice(0, -1).join('/');

  // probe helpers
  const probeWithFetch = async (url) => {
    try {
      const r = await fetch(url, { method: 'HEAD' });
      return r && r.ok;
    } catch (e) {
      return null;
    }
  };

  const probeWithImage = (url) =>
    new Promise((resolve) => {
      const im = new Image();
      im.onload = () => resolve(true);
      im.onerror = () => resolve(false);
      im.src = url;
    });

  // localStorage caching (per card code)
  const cacheKey = 'mc_promo_cache_v1';
  const readCache = () => {
    try { return JSON.parse(localStorage.getItem(cacheKey) || '{}'); } catch (e) { return {}; }
  };
  const writeCache = (cardCode, map) => {
    try {
      const all = readCache();
      all[cardCode] = { ts: Date.now(), map };
      localStorage.setItem(cacheKey, JSON.stringify(all));
    } catch (e) { /* ignore */ }
  };

  useEffect(() => {
    // restore cached promos for this card (if any)
    try {
      const all = readCache();
      if (all && all[card.code] && all[card.code].map) {
        setChosenSrcMap(all[card.code].map || {});
      }
    } catch (e) { /* ignore */ }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const setImgSrcAndClearSources = (imgEl, src) => {
    const pic = imgEl && imgEl.closest && imgEl.closest('picture');
    if (pic) pic.querySelectorAll('source').forEach((s) => {
      if (s.dataset && s.dataset.mcOrigSrcset === undefined) s.dataset.mcOrigSrcset = s.srcset || '';
      s.srcset = '';
    });
    imgEl.setAttribute('src', src);
  };

  const activatePromo = (dir, url) => {
    const imgEl = document.getElementById(`card-image-${card.id}`);
    if (!imgEl) return;
    setImgSrcAndClearSources(imgEl, url);
    setActiveDir(dir);
  };

  const handlePromoClick = async (dir) => {
    // toggle off
    if (activeDir === dir) {
      const imgEl = document.getElementById(`card-image-${card.id}`);
      if (!imgEl) return;
      // restore source srcsets
      const pic = imgEl.closest && imgEl.closest('picture');
      if (pic) pic.querySelectorAll('source').forEach((s) => {
        if (s.dataset && s.dataset.mcOrigSrcset !== undefined) {
          s.srcset = s.dataset.mcOrigSrcset;
          delete s.dataset.mcOrigSrcset;
        }
      });
      // restore original
      imgEl.setAttribute('src', chosenSrcMap['__orig'] || imagesrc);
      setActiveDir(null);
      return;
    }

    // if cached
    if (chosenSrcMap[dir]) {
      activatePromo(dir, chosenSrcMap[dir]);
      return;
    }

    if (loadingDirs[dir]) return; // already probing
    setLoadingDirs((p) => ({ ...p, [dir]: true }));

    const candidateWebp = `${basePath}/${dir}/${filename}`.replace(/\.(jpe?g|png)$/i, '.webp');
    const candidateOrig = `${basePath}/${dir}/${filename}`;

    // try HEAD for webp
    let ok = null;
    try { ok = await probeWithFetch(candidateWebp); } catch (e) { ok = null; }
    if (ok === true) {
      const map = { ...chosenSrcMap, [dir]: candidateWebp };
      setChosenSrcMap(map); writeCache(card.code, map); setLoadingDirs((p) => { const np = { ...p }; delete np[dir]; return np; });
      activatePromo(dir, candidateWebp);
      return;
    }

    // try image probe of original promo path
    const probeOrig = await probeWithImage(candidateOrig);
    if (probeOrig) {
      const map = { ...chosenSrcMap, [dir]: candidateOrig };
      setChosenSrcMap(map); writeCache(card.code, map); setLoadingDirs((p) => { const np = { ...p }; delete np[dir]; return np; });
      activatePromo(dir, candidateOrig);
      return;
    }

    // fallback: try webp via Image
    const probeWebp = await probeWithImage(candidateWebp);
    if (probeWebp) {
      const map = { ...chosenSrcMap, [dir]: candidateWebp };
      setChosenSrcMap(map); writeCache(card.code, map); setLoadingDirs((p) => { const np = { ...p }; delete np[dir]; return np; });
      activatePromo(dir, candidateWebp);
      return;
    }

    setLoadingDirs((p) => { const np = { ...p }; delete np[dir]; return np; });
    // nothing found — hide this promo button so the user doesn't click it again
    setHiddenDirs((p) => ({ ...p, [dir]: true }));
  };

  // Render promo buttons (no hidden probes on mount)
  // filter out FFG-specific rework button when pack creator isn't FFG
  const filteredPromoButtons = promoButtons.filter((btn) => {
    if (btn.dir === 'alt-FFG') {
      return (card.creator || '').toString().toUpperCase() === 'FFG';
    }
    return true;
  });

  return (
    <div className="tw-flex tw-flex-wrap tw-gap-2 tw-mt-2">
      {filteredPromoButtons.map((btn) => {
        if (hiddenDirs[btn.dir]) return null;
        const loading = !!loadingDirs[btn.dir];
        const available = !!chosenSrcMap[btn.dir];
        return (
          <button
            key={btn.dir}
            type="button"
            className={`mc-promo-btn ${activeDir === btn.dir ? 'active' : ''}`}
            onClick={() => handlePromoClick(btn.dir)}
            disabled={loading}
          >
            {btn.label}{loading ? ' …' : available ? '' : ''}
          </button>
        );
      })}
    </div>
  );
}



/**
 * ImageWithWebp
 * Renders a <picture> with .webp sources first (FR variants if locale 'qc'),
 * letting the browser choose the best supported/available source and fall back
 * to the original image when webp is not available.
 * Props:
 * - src: original image src (jpg/png)
 * - id: id applied to inner <img> (used elsewhere by CardPromo)
 * - alt, className
 * - locale: optional locale string (e.g., 'qc') to try FR variants first
 */
import React, { useState, useMemo, useEffect } from 'react';

export default function ImageWithWebp({ src, id, alt, className, locale, langDir = '', preferWebpOnly = false }) {
  if (!src) return null;

  // If the server provided an EN path but the mount requested FR, prefer FR immediately
  let effectiveSrc = src;
  try {
    if (langDir && langDir.toUpperCase() === 'FR' && /\/EN\//i.test(src)) {
      effectiveSrc = src.replace(/\/EN\//i, '/FR/');
    }
  } catch (e) {
    // ignore replace errors and fall back to original src
    effectiveSrc = src;
  }

  console.debug('[ImageWithWebp] mount', { src, effectiveSrc, locale, langDir, preferWebpOnly });

  const parts = effectiveSrc.split('/');
  const filename = parts[parts.length - 1];
  const base = parts.slice(0, -1).join('/');

  // handle cases where src already contains a language folder (EN/FR)
  const langMatch = base.match(/(.*)\/(EN|FR)$/i);
  const baseRoot = langMatch ? langMatch[1] : base;
  const hasLangFolder = Boolean(langMatch);

  const origWebp = (baseRoot + '/' + filename).replace(/\.(jpe?g|png)$/i, '.webp');
  const frBase = `${baseRoot}/FR/${filename}`;
  const frWebp = frBase.replace(/\.(jpe?g|png)$/i, '.webp');
  const enBase = `${baseRoot}/EN/${filename}`;
  const enWebp = enBase.replace(/\.(jpe?g|png)$/i, '.webp');
  const lc = (locale || '').toString().toLowerCase();
  const isFrench = lc === 'qc' || lc.startsWith('fr') || (langDir && langDir.toUpperCase() === 'FR');

  // Build candidate list in desired order
  const candidates = useMemo(() => {
    const list = [];
    // If src already contains a language folder (EN/FR), we may still prefer the forced langDir.
    if (hasLangFolder) {
      // If a language override is requested and differs from the src, try the override webp first
      if (langDir && langDir.toUpperCase() === 'FR' && !base.match(/\/FR$/i)) {
        list.push(frWebp, frBase);
      }
      list.push(src);
      // also include normalized root & FR variants after
      if (isFrench) {
        // ensure frWebp is somewhere early in the list
        if (!list.includes(frWebp)) list.push(frWebp);
        list.push(frBase, origWebp);
      } else {
        list.push(origWebp);
      }
    } else if (preferWebpOnly) {
      // Prefer webp variants first, but always include original src as final fallback
      if (isFrench) {
        list.push(frWebp, origWebp, src);
      } else {
        list.push(origWebp, src);
      }
    } else {
      if (isFrench) {
        list.push(frWebp, frBase, origWebp, src);
      } else {
        list.push(origWebp, src);
      }
    }
    return list.filter(Boolean);
  }, [src, locale, frWebp, frBase, origWebp, preferWebpOnly, langDir]);

  // Log the resolved candidates for debugging
  useEffect(() => {
    console.debug('[ImageWithWebp] candidates', { src, locale, langDir, isFrench, candidates });
  }, [src, locale, langDir, isFrench, candidates]);

  const [idx, setIdx] = useState(0);
  const current = candidates[idx] || (preferWebpOnly ? '' : src);

  const handleError = () => {
    if (idx < candidates.length - 1) setIdx((i) => i + 1);
  };

  // Probe candidates programmatically using Image() to detect .webp presence
  useEffect(() => {
    let cancelled = false;

    const isLocalhost = typeof window !== 'undefined' && (window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost');

    const probeWithFetch = async (url) => {
      try {
        if (preferWebpOnly || isLocalhost) console.debug('[ImageWithWebp] HEAD', url);
        const resp = await fetch(url, { method: 'HEAD' });
        if (preferWebpOnly || isLocalhost) console.debug('[ImageWithWebp] HEAD result', url, resp && resp.status);
        return resp && resp.ok;
      } catch (e) {
        if (preferWebpOnly || isLocalhost) console.debug('[ImageWithWebp] HEAD failed', url, e && e.message);
        return null; // fetch not usable (CORS/blocked/etc.)
      }
    };

    const probeWithImage = (url) =>
      new Promise((resolve) => {
        const im = new Image();
        im.onload = () => {
          if (preferWebpOnly || isLocalhost) console.debug('[ImageWithWebp] Image loaded', url);
          resolve(true);
        };
        im.onerror = (ev) => {
          if (preferWebpOnly || isLocalhost) console.debug('[ImageWithWebp] Image error', url, ev && ev.type);
          resolve(false);
        };
        im.src = url;
      });

    (async () => {
      for (let i = 0; i < candidates.length; i++) {
        if (cancelled) break;
        const url = candidates[i];

        // First try a lightweight HEAD request when possible
        let ok = null;
        try {
          ok = await probeWithFetch(url);
        } catch (e) {
          ok = null;
        }

        if (ok === true) {
          if (preferWebpOnly || isLocalhost) console.debug('[ImageWithWebp] selected (HEAD)', url);
          if (!cancelled) setIdx(i);
          break;
        }

        if (ok === false) {
          // explicit 404 or similar, try next candidate
          continue;
        }

        // If fetch couldn't be used, fall back to Image() probing
        try {
          const r = await probeWithImage(url);
          if (r) {
            if (preferWebpOnly || isLocalhost) console.debug('[ImageWithWebp] selected (Image)', url);
            if (!cancelled) setIdx(i);
            break;
          }
        } catch (e) {
          // continue to next candidate
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [src, candidates]);
  const imgClassName = `${className ? className + ' ' : ''}tw-rounded-3xl`;

  return (
    <picture>
      {/* Keep source tags for browser-native webp support */}
        {/* Provide locale-specific webp sources first to let browser choose the correct file */}
        {isFrench ? (
          <>
            <source srcSet={frWebp} type="image/webp" />
            <source srcSet={enWebp} type="image/webp" />
            {!preferWebpOnly && <source srcSet={frBase} />}
          </>
        ) : (
          <>
            <source srcSet={enWebp} type="image/webp" />
            <source srcSet={origWebp} type="image/webp" />
          </>
        )}
      {/* Always render the img element so other components (e.g., CardPromo) can target it */}
      <img id={id} src={current || ''} alt={alt} className={imgClassName} onError={handleError} />
    </picture>
  );
}

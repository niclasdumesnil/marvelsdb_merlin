// Simple test to verify ImageWithWebp candidate order for FR locale
// Run with: node tests/test-webp-fr.js

function buildCandidates(src, locale, preferWebpOnly) {
  const parts = src.split('/');
  const filename = parts[parts.length - 1];
  const base = parts.slice(0, -1).join('/');

  const langMatch = base.match(/(.*)\/(EN|FR)$/i);
  const baseRoot = langMatch ? langMatch[1] : base;
  const hasLangFolder = Boolean(langMatch);

  const origWebp = (baseRoot + '/' + filename).replace(/\.(jpe?g|png)$/i, '.webp');
  const frBase = `${baseRoot}/FR/${filename}`;
  const frWebp = frBase.replace(/\.(jpe?g|png)$/i, '.webp');
  const enBase = `${baseRoot}/EN/${filename}`;
  const enWebp = enBase.replace(/\.(jpe?g|png)$/i, '.webp');

  const lc = (locale || '').toString().toLowerCase();
  const isFrench = lc === 'qc' || lc.startsWith('fr');

  const list = [];
  if (hasLangFolder) {
    list.push(src);
    if (isFrench) {
      list.push(frWebp, frBase, origWebp);
    } else {
      list.push(origWebp);
    }
  } else if (preferWebpOnly) {
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

  return { candidates: list, enWebp, frWebp };
}

// Test case
const src = '/bundles/cards/01002.jpg';
const locale = 'fr';
const preferWebpOnly = true;

const { candidates, enWebp, frWebp } = buildCandidates(src, locale, preferWebpOnly);

console.log('candidates:', candidates);
console.log('expected FR webp:', frWebp);
console.log('expected EN webp:', enWebp);

if (candidates[0] === frWebp) {
  console.log('PASS: FR webp is preferred when locale=fr and preferWebpOnly=true');
  process.exit(0);
} else {
  console.error('FAIL: FR webp was not the first candidate');
  process.exit(2);
}

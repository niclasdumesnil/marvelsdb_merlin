/**
 * Faction color mapping for Marvel Champions card factions.
 */

const FACTION_COLORS = {
  leadership: '#2b80c5',
  protection: '#107116',
  aggression: '#cc3038',
  justice: '#ead51b',
  basic: '#808080',
  determination: '#493f64',
  encounter: '#9b6007',
  hero: '#353b49',
};

/**
 * Get the CSS color for a faction code.
 */
export function getFactionColor(code) {
  return FACTION_COLORS[code] || '#888';
}

/**
 * Get the header class for a faction (handles encounter/villain special case).
 */
export function getHeaderClass(factionCode, typeCode) {
  if (factionCode === 'encounter' && typeCode === 'villain') {
    return 'mc-header-encounter-villain';
  }
  return `mc-header-${factionCode}`;
}

/**
 * Get the border class for a faction's card text.
 */
export function getBorderClass(factionCode) {
  return `mc-border-${factionCode}`;
}

/**
 * Format an integer value with optional star/per_hero/per_group icons.
 * Returns an object { text, star, perHero, perGroup } for rendering.
 */
export function formatInteger(value, star = false, perHero = false, perGroup = false) {
  let text;
  if (value === null || value === undefined) {
    text = star ? '' : '—';
  } else if (value < 0) {
    text = 'X';
  } else {
    text = String(value);
  }
  return { text, star, perHero, perGroup };
}

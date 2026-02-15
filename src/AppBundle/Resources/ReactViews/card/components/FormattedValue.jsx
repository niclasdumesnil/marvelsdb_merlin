import React from 'react';

/**
 * Renders a formatted integer with optional star / per_hero / per_group icons.
 */
export default function FormattedValue({ value, star, perHero, perGroup }) {
  let display;
  if (value === null || value === undefined) {
    display = star ? '' : '—';
  } else if (value < 0) {
    display = 'X';
  } else {
    display = String(value);
  }

  return (
    <span>
      {display}
      {perHero && value !== null && value !== undefined && (
        <span className="icon icon-per_hero" />
      )}
      {perGroup && value !== null && value !== undefined && (
        <span className="icon icon-per_group" />
      )}
      {star && <span className="icon icon-star" />}
    </span>
  );
}

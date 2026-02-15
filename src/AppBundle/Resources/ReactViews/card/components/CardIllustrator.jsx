import React from 'react';

/**
 * Renders the illustrator credit.
 */
export default function CardIllustrator({ card }) {
  if (!card.illustrator) return null;

  return (
    <span className="mc-card-illustrator-name tw-italic">{card.illustrator}</span>
  );
}

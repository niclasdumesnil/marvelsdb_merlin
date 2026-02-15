import React from 'react';
import { getBorderClass } from '../utils/factionUtils.js';

/**
 * Renders the card's rules text, boost icons, and scheme icons.
 */
export default function CardText({ card, showSpoilers }) {
  if (!card.text) return null;
  const spoilerClass = card.spoiler && !showSpoilers ? 'mc-spoiler' : '';

  return (
    <div className={spoilerClass}>
      <div className={`mc-card-text ${getBorderClass(card.faction_code)}`}>
        <div dangerouslySetInnerHTML={{ __html: card.text }} />
        <SchemeIcons card={card} />
      </div>
      <BoostIcons card={card} />
    </div>
  );
}

function SchemeIcons({ card }) {
  const icons = [
    { key: 'acceleration', count: card.scheme_acceleration },
    { key: 'amplify', count: card.scheme_amplify },
    { key: 'crisis', count: card.scheme_crisis },
    { key: 'hazard', count: card.scheme_hazard },
  ];

  return (
    <>
      {icons.map(({ key, count }) =>
        count
          ? Array.from({ length: count }, (_, i) => (
              <span key={`${key}-${i}`} name={key.charAt(0).toUpperCase() + key.slice(1)} className={`icon icon-${key}`} />
            ))
          : null
      )}
    </>
  );
}

function BoostIcons({ card }) {
  if (!card.boost_star && (!card.boost || card.boost <= 0)) return null;

  return (
    <div className="mc-boost-row">
      <span>Boost:</span>
      {card.boost_star && <span className="icon icon-star color-boost" />}
      {card.boost > 0 &&
        Array.from({ length: card.boost }, (_, i) => (
          <span key={i} title="Boost" className="icon icon-boost color-boost" />
        ))}
    </div>
  );
}

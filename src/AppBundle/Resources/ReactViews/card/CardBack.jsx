import React from 'react';
import { getBorderClass, getHeaderClass, getFactionColor } from './utils/factionUtils.js';
import ImageWithWebp from './components/ImageWithWebp';
import CardText from './components/CardText';
import CardFlavor from './components/CardFlavor';

/**
 * CardBack - React component replacing card-back.html.twig
 *
 * Renders the back side of a double-sided card.
 * Only renders if card.double_sided is true.
 */
export default function CardBack({ card, showSpoilers, preferWebpOnly }) {
  if (!card.double_sided) return null;

  const spoilerClass = card.spoiler && !showSpoilers ? 'mc-spoiler' : '';
  const borderClass = getBorderClass(card.faction_code);
  const headerClass = getHeaderClass(card.faction_code, card.type_code);

  // Dual gradient style for dual-faction cards (match CardFront)
  const dualGradientStyle = card.faction2_code
    ? {
        '--dual-gradient': `linear-gradient(90deg, ${getFactionColor(card.faction_code)}, ${getFactionColor(card.faction2_code)})`,
      }
    : {};

  const headerDualStyle = card.faction2_code
    ? {
        background: `linear-gradient(90deg, ${getFactionColor(card.faction_code)} 0%, ${getFactionColor(card.faction_code)} 40%, ${getFactionColor(card.faction2_code)} 60%, ${getFactionColor(card.faction2_code)} 100%)`,
      }
    : {};

  function readableTextColor(hex) {
    if (!hex) return '#fff';
    const h = hex.replace('#','');
    const normalized = h.length === 3 ? h.split('').map(c => c + c).join('') : h;
    const bigint = parseInt(normalized,16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
    return luminance > 150 ? '#000' : '#fff';
  }

  const headerStyle = card.faction2_code
    ? { ...headerDualStyle, color: readableTextColor(getFactionColor(card.faction_code)) }
    : { backgroundColor: getFactionColor(card.faction_code), color: readableTextColor(getFactionColor(card.faction_code)) };

  return (
    <div className="tw-flex tw-flex-col md:tw-flex-row tw-gap-6 tw-mt-6">
      {/* Text column */}
      <div className="tw-flex-1 tw-min-w-0">
        <div className="mc-card-panel" style={dualGradientStyle}>
          {/* Header */}
          <div className={`mc-card-header ${headerClass}`} style={headerStyle}>
            <div className="tw-flex tw-flex-col">
              <div className="tw-flex tw-items-center tw-gap-2">
                <CardBackName card={card} showSpoilers={showSpoilers} />
              </div>
              {/* Faction field removed per UI update */}
            </div>
            <div className="mc-card-type-header tw-ml-auto tw-self-end">
              {card.type_name}{card.stage ? `. Stage ${card.stage}` : ''}
            </div>
          </div>

          {/* Body: support back_* fields or fall back to text/flavor */}
          {((card.back_flavor || card.back_text) || (card.flavor || card.text)) && (
            <div className="mc-card-body">
              {card.type_code === 'main_scheme' ? (
                <>
                  {(card.back_flavor || card.flavor) && (
                    <div className="tw-pt-4 tw-border-t tw-border-slate-800 tw-text-slate-500 tw-italic tw-text-lg">
                      <CardFlavor card={card} showSpoilers={showSpoilers} />
                    </div>
                  )}
                  {(card.back_text || card.text) && (
                    <div className="tw-space-y-4">
                      <div className="tw-text-slate-200 tw-text-xl tw-font-medium tw-leading-relaxed">
                        <CardText card={card} showSpoilers={showSpoilers} />
                      </div>
                    </div>
                  )}
                </>
              ) : (
                <>
                  {(card.back_text || card.text) && (
                    <div className="tw-space-y-4">
                      <div className="tw-text-slate-200 tw-text-xl tw-font-medium tw-leading-relaxed">
                        <CardText card={card} showSpoilers={showSpoilers} />
                      </div>
                    </div>
                  )}
                  {(card.back_flavor || card.flavor) && (
                    <div className="tw-pt-4 tw-border-t tw-border-slate-800 tw-text-slate-500 tw-italic tw-text-lg">
                      <CardFlavor card={card} showSpoilers={showSpoilers} />
                    </div>
                  )}
                </>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Image column */}
      <div className={`tw-flex-shrink-0 tw-text-center tw-p-8 tw-flex tw-flex-col ${spoilerClass}`}>
        {(card.backimagesrc || card.imagesrc) && (
          <ImageWithWebp
            id={`card-image-${card.id}-back`}
            src={card.backimagesrc || card.imagesrc}
            alt={card.name}
            className="tw-relative tw-rounded-3xl tw-overflow-hidden tw-border tw-border-slate-700 shadow-2xl"
            preferWebpOnly={preferWebpOnly}
          />
        )}
      </div>
    </div>
  );
}

/**
 * Renders a back card name with link (uses back_name if available).
 */
function CardBackName({ card, showSpoilers }) {
  const spoilerClass = card.spoiler && !showSpoilers ? 'mc-spoiler' : '';

  return (
    <div>
      {card.is_unique && <span className="icon-unique" />}
      <a
        href={card.url}
        className={`card-name card-tip ${!card.available ? 'card-preview' : ''} ${spoilerClass}`}
        data-code={card.code}
      >
        {card.back_name || card.name}
        {card.stage && (card.type_code === 'villain' || card.type_code === 'leader')
          ? ` (${card.stage})`
          : card.type_code === 'main_scheme'
            ? ` - ${card.stage}`
            : ''}
      </a>
      {card.subname && (
        <div className={`tw-text-sm tw-opacity-80 ${spoilerClass}`}>
          {card.subname}
        </div>
      )}
    </div>
  );
}

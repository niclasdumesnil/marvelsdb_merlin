/**
 * Entry point for React card views.
 * 
 * Mounts CardFront and CardBack components into DOM containers
 * that are prepared by the Twig templates.
 *
 * The Twig templates serialize card data as JSON into data attributes
 * on the mount containers:
 *   <div id="react-card-front-{id}" data-card='{...}' data-show-spoilers="true" data-locale="en"></div>
 *   <div id="react-card-back-{id}" data-card='{...}' data-show-spoilers="true"></div>
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import CardFront from './CardFront';
import CardBack from './CardBack';
import '@css/mc4db.css';

/**
 * Mount all React card components found on the page.
 */
function mountAllCards() {
  // Mount card fronts
  document.querySelectorAll('[data-react-component="CardFront"]').forEach((container) => {
    try {
      const card = JSON.parse(container.getAttribute('data-card'));
      const showSpoilers = container.getAttribute('data-show-spoilers') === 'true';
      // Allow forcing locale via client URL param `?force_locale=fr` when server didn't provide it
      let forcedLocale = container.getAttribute('data-forced-locale');
      try {
        const urlParams = new URLSearchParams(window.location.search);
        const urlForced = urlParams.get('force_locale');
        if ((!forcedLocale || forcedLocale === '') && urlForced) forcedLocale = urlForced;
      } catch (e) {
        // ignore URLSearchParams errors in older browsers
      }

      const locale = forcedLocale && forcedLocale !== '' ? forcedLocale : (container.getAttribute('data-locale') || 'en');
      // Derive langDir from forcedLocale if present, otherwise use server-provided langdir
      const langDir = (forcedLocale && forcedLocale !== '') ? (forcedLocale.toUpperCase() === 'FR' ? 'FR' : 'EN') : (container.getAttribute('data-langdir') || '');

      const preferWebpOnly = container.getAttribute('data-prefer-webp-only') === 'true';

      const root = createRoot(container);
      console.debug('[CardMount] mounting CardFront', { id: card.id, locale, langDir, forcedLocale, preferWebpOnly });
      root.render(
        <CardFront card={card} showSpoilers={showSpoilers} locale={locale} langDir={langDir} preferWebpOnly={preferWebpOnly} />
      );
    } catch (e) {
      console.error('Failed to mount CardFront:', e);
    }
  });

  // Mount card backs
  document.querySelectorAll('[data-react-component="CardBack"]').forEach((container) => {
    try {
      const card = JSON.parse(container.getAttribute('data-card'));
      const showSpoilers = container.getAttribute('data-show-spoilers') === 'true';

      const preferWebpOnly = container.getAttribute('data-prefer-webp-only') === 'true';

      const root = createRoot(container);
      root.render(
        <CardBack card={card} showSpoilers={showSpoilers} preferWebpOnly={preferWebpOnly} />
      );
    } catch (e) {
      console.error('Failed to mount CardBack:', e);
    }
  });
}

// Mount when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountAllCards);
} else {
  mountAllCards();
}

/**
 * MarvelCDB Node.js API — Entry Point
 *
 * Runs alongside the Symfony server on a different port (default 4000).
 * Connects to the same MySQL database.
 */
require('dotenv').config({ path: require('path').resolve(__dirname, '../.env') });

const express = require('express');
const cors = require('cors');
const morgan = require('morgan');
const path = require('path');
const fs = require('fs');

const publicRoutes = require('./routes');
const errorHandler = require('./middleware/errorHandler');
const db = require('./config/database');
const Card = require('./models/Card');
const { serializeCard } = require('./utils/cardSerializer');

const app = express();
const PORT = parseInt(process.env.PORT || '4000', 10);

// ── Middleware ───────────────────────────────────

// CORS — allow the React frontend (served by Symfony on :8000) to call this API
app.use(
  cors({
    origin: [
      'http://127.0.0.1:8000',
      'http://localhost:8000',
      'http://127.0.0.1:4000',
      'http://localhost:4000',
    ],
    methods: ['GET', 'OPTIONS'],
    credentials: true,
  })
);

// Request logging
app.use(morgan(process.env.NODE_ENV === 'production' ? 'combined' : 'dev'));

// JSON body parsing (for future POST/PUT routes)
app.use(express.json());

// ── Health check ────────────────────────────────

app.get('/health', async (req, res) => {
  try {
    await db.raw('SELECT 1');
    res.json({ status: 'ok', db: 'connected', uptime: process.uptime() });
  } catch (err) {
    res.status(503).json({ status: 'error', db: 'disconnected', error: err.message });
  }
});

// ── Public API routes ───────────────────────────

// Serve built React bundle and styles for card views
app.use('/react', express.static(path.resolve(__dirname, '../../web/react')));
// Serve site CSS used by Symfony templates so Node pages match Symfony styling
app.use('/css', express.static(path.resolve(__dirname, '../../web/css')));

// Add a simple HTML view that mounts the React card components
// This runs before the API routes so visiting /card/:code returns the React HTML
app.get(['/card/:code.html', '/card/:code'], async (req, res, next) => {
  try {
    const code = req.params.code;
    const row = await Card.findByCode(code);
    if (!row) return res.status(404).send(`<h1>Card ${code} not found</h1>`);
    // Determine request locale from Accept-Language header (no forced override)
    const urlLocale = (req.acceptsLanguages && req.acceptsLanguages()[0]) || 'en';
    const showSpoilers = (req.query.show_spoilers === '1' || req.query.show_spoilers === 'true') ? true : false;
    const preferWebpOnly = (req.query.prefer_webp_only === '1' || req.query.prefer_webp_only === 'true') ? true : false;

    // resolve linked card
    let linkedCard = null;
    if (row.linked_to_code) {
      const linkedRow = await Card.findByCode(row.linked_to_code);
      if (linkedRow) {
        linkedCard = serializeCard(linkedRow, { api: true });
        // ensure linked card has an id for client-side DOM lookups
        linkedCard.id = linkedRow.id;
      }
    }

    const duplicatedBy = await Card.findDuplicateCodes(row.id);
    const card = serializeCard(row, { api: true, linkedCard, duplicatedBy });
    // ensure card id is present for client-side features (promo buttons reference `card.id`)
    card.id = row.id;
    // Precompute available promo image URLs so the client doesn't need to probe.
    // Determine langDir based on the card's pack language (server-side asset availability).
    // This prevents the client from switching to FR image paths when the card only
    // has EN assets available.
    const langDir = (card.language && card.language.toLowerCase().startsWith('fr')) ? 'FR' : 'EN';

    // Choose the locale we expose to the client: prefer the card's pack language, then Accept-Language.
    const clientLocale = card.language || urlLocale;

    // Precompute available promo image URLs so the client doesn't need to probe.
    try {
      const promoDirs = ['promo-FR', 'promo-EN', 'alt-FFG'];
      const promoUrls = {};
      const imgParts = (card.imagesrc || '').split('/');
      const filename = imgParts[imgParts.length - 1] || '';
      const cardsBase = path.join(__dirname, '../../web/bundles/cards');

      for (const dir of promoDirs) {
        // skip alt-FFG if not official pack
        if (dir === 'alt-FFG' && (card.creator || '').toString().toUpperCase() !== 'FFG') continue;

        const tryPaths = [];
        // Try common extensions for promo images (webp, jpg, png)
        const baseName = filename.replace(/\.(webp|jpe?g|png)$/i, '');
        const exts = ['.webp', '.jpg', '.png'];
        // Prefer top-level promo dir (e.g. promo-FR/15002.jpg)
        for (const ext of exts) tryPaths.push(path.join(cardsBase, dir, `${baseName}${ext}`));
        // Then lang-specific subfolder (e.g. EN/promo-FR/15002.jpg)
        for (const ext of exts) tryPaths.push(path.join(cardsBase, langDir, dir, `${baseName}${ext}`));
        // Finally EN fallback subfolder
        for (const ext of exts) tryPaths.push(path.join(cardsBase, 'EN', dir, `${baseName}${ext}`));

        for (const p of tryPaths) {
          try {
            if (fs.existsSync(p)) {
              // derive URL path from absolute path
              const rel = path.relative(path.join(__dirname, '../../web'), p).replace(/\\/g, '/');
              promoUrls[dir] = `/${rel}`;
              break;
            }
          } catch (e) {
            // ignore
          }
        }
      }

      if (Object.keys(promoUrls).length > 0) card.promo_urls = promoUrls;
    } catch (e) {
      // ignore promo detection errors
    }

    // If prefer_webp_only is requested, try to point to webp bundles but
    // fall back to EN or the previously resolved image when missing.
    if (preferWebpOnly) {
      const tryWebpFor = (code, suffix = '') => {
        const candidateLang = path.join(__dirname, '../../web/bundles/cards', langDir, `${code}${suffix}.webp`);
        if (fs.existsSync(candidateLang)) return `/bundles/cards/${langDir}/${code}${suffix}.webp`;
        const candidateEn = path.join(__dirname, '../../web/bundles/cards', 'EN', `${code}${suffix}.webp`);
        if (fs.existsSync(candidateEn)) return `/bundles/cards/EN/${code}${suffix}.webp`;
        return null;
      };

      const webp = tryWebpFor(card.code, '');
      if (webp) card.imagesrc = webp;

      if (card.backimagesrc !== undefined && card.backimagesrc) {
        const webpBack = tryWebpFor(card.code, 'b');
        if (webpBack) card.backimagesrc = webpBack;
      }

      if (card.linked_card) {
        const linkedWebp = tryWebpFor(card.linked_card.code, '');
        if (linkedWebp) card.linked_card.imagesrc = linkedWebp;
      }
    }

    const rawCardJson = JSON.stringify(card);

    // Prepare back-side JSON for double-sided cards.
    let rawBackJson = null;
    if (card.linked_card) {
      rawBackJson = JSON.stringify(card.linked_card);
    } else if (card.double_sided) {
      // Construct a back-side card object from the same serialized card
      // using the back-specific fields so the frontend can mount CardBack.
      const backCard = { ...card };
      // Ensure we don't pass nested linked/duplicate metadata to the back card
      delete backCard.linked_card;
      delete backCard.duplicated_by;
      backCard.imagesrc = card.backimagesrc || card.imagesrc;
      backCard.name = card.back_name || card.name;
      backCard.text = card.back_text || card.text;
      backCard.real_text = card.back_text || card.real_text || card.real_text;
      backCard.flavor = card.back_flavor || card.flavor;
      rawBackJson = JSON.stringify(backCard);
    }

    // Escape for placing inside an HTML attribute (use double quotes for attribute)
    const escapeAttr = (s) => {
      return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '\\u003c').replace(/>/g, '&gt;');
    };

    const cardJson = escapeAttr(rawCardJson);
    const backJson = rawBackJson ? escapeAttr(rawBackJson) : null;

    const html = `<!doctype html>
<html lang="en">
    <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Card ${card.code}</title>
    <!-- Include site-wide styles (same as Symfony) -->
    <link rel="stylesheet" href="/css/app_bootstrap_1.css">
    <link rel="stylesheet" href="/bundles/app/css/merlinsdb.css">
    <link rel="stylesheet" href="/css/app_style_2.css">
    <link rel="stylesheet" href="/css/app_icons_3.css">
    <link rel="stylesheet" href="/css/app_languages_4.css">
    <!-- Card-specific bundled CSS -->
    <link rel="stylesheet" href="/react/css/card.css">
    <!-- Inline shim: ensure font and icon scoping match Symfony behaviour -->
    <style>
      /* Keep card names white like the Symfony page */
      .card-name { color: #fff !important; }

      /* Apply the panel/game font to common text elements only (avoid using
         the universal selector which would override icon fonts and pseudo
         elements). Do not use !important so the icon font rules from
         app_icons_3.css can still win for icon classes. */
      .mc-card-panel,
      .mc-card-panel h1,
      .mc-card-panel h2,
      .mc-card-panel h3,
      .mc-card-panel p,
      .mc-card-panel div,
      .mc-card-panel a,
      .mc-card-panel li,
      .mc-card-panel span {
        font-family: "Marvel Champions", "Open Sans", Arial, sans-serif;
      }

      /* Respect the application's icon font defined in /css/app_icons_3.css
         which registers 'marvel-icons'. Re-assert it here for any icon
         classes to be safe, but using the same family name so the glyphs
         render correctly. */
      [class^="icon-"],
      [class*=" icon-"],
      .icon,
      .mc-icon,
      .icon-unique {
        font-family: 'marvel-icons' !important;
        speak: none;
        font-style: normal;
        font-weight: normal;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
      }
    </style>
  </head>
  <body>
    <div id="wrapper">
      <div class="main white container">
        <div data-react-component="CardFront" data-card="${cardJson}" data-show-spoilers="${showSpoilers ? 'true' : 'false'}" data-locale="${clientLocale}" data-langdir="${langDir}" data-prefer-webp-only="${preferWebpOnly ? 'true' : 'false'}"></div>
      </div>
    </div>
    ${backJson ? `<div data-react-component="CardBack" data-card="${backJson}" data-show-spoilers="${showSpoilers ? 'true' : 'false'}" data-prefer-webp-only="${preferWebpOnly ? 'true' : 'false'}"></div>` : ''}
    <script>
      // Temporary debug hook: capture runtime errors and show them in-page
      (function(){
        window._mc_debug_errors = [];
        window.addEventListener('error', function(e){
          try{ window._mc_debug_errors.push(e.message); }catch(_){ }
          try{ document.body.innerHTML = '<pre style="color:red; padding:1rem;">Runtime error:\n' + String(e.message) + '</pre>'; }catch(_){ }
        });
        window.addEventListener('unhandledrejection', function(e){
          try{ window._mc_debug_errors.push(String(e.reason)); }catch(_){ }
          try{ document.body.innerHTML = '<pre style="color:red; padding:1rem;">Unhandled rejection:\n' + String(e.reason) + '</pre>'; }catch(_){ }
        });
        console.log('[Card debug] error hook installed');
      })();
    </script>
    <script src="/react/js/card.js"></script>
  </body>
</html>`;

    res.type('html').send(html);
  } catch (err) {
    next(err);
  }
});

// Mount API routes after the HTML route
app.use('/api/public', publicRoutes);

// Also mount at root level so other API paths still work without prefix
app.use('/', publicRoutes);

// ── Static files (optional — serve card images from web/bundles) ──

app.use('/bundles', express.static(path.resolve(__dirname, '../../web/bundles')));

// ── 404 catch-all ───────────────────────────────

app.use((req, res) => {
  res.status(404).json({ error: { status: 404, message: `Route ${req.originalUrl} not found` } });
});

// ── Error handler ───────────────────────────────

app.use(errorHandler);

// ── Start server ────────────────────────────────

async function start() {
  // Verify DB connection before starting
  try {
    await db.raw('SELECT 1');
    console.log(`✓ Database connected (${process.env.DB_NAME || 'symphony_merlin'})`);
  } catch (err) {
    console.error('✗ Database connection failed:', err.message);
    console.error('  Check your .env DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS');
    process.exit(1);
  }

  app.listen(PORT, () => {
    console.log(`\n  ╔══════════════════════════════════════════╗`);
    console.log(`  ║  MarvelCDB Node API                      ║`);
    console.log(`  ║  http://127.0.0.1:${PORT}                    ║`);
    console.log(`  ║  Health: http://127.0.0.1:${PORT}/health      ║`);
    console.log(`  ╚══════════════════════════════════════════╝\n`);
  });
}

start();

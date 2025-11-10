#!/usr/bin/env python3
"""
Fetch decklists from MarvelCDB public API and produce a TSV similar to ListAllDecksCommand.php.

This cleaned version uses ONLY the project's SQL database (app/config/parameters.yml)
to enrich card metadata. It fetches decklists (IDs range), collects the set of card codes,
queries the local DB for those codes (with some code-variant expansion), fills an in-memory
CARD_CACHE and then writes the TSV. If some codes are not found, it writes a *_missing_codes.txt
file for diagnostics.

Usage:
  py tools\marvelcdb_decks_to_tsv.py Xmin Xmax

Requirements:
  - Python 3
  - pymysql (recommended) or mysql-connector-python to talk to the Symfony MySQL DB
  - network access to https://marvelcdb.com for decklist fetches

"""
from __future__ import annotations
import json
import time
import sys
import os
from typing import Dict, Any, List, Tuple

try:
    import requests
except Exception:
    requests = None
    import urllib.request
    from urllib.error import HTTPError, URLError

# Range defaults (can be overridden by CLI args)
XMIN = 1
XMAX = 12

# MarvelCDB endpoints
DECKLIST_URL = "https://marvelcdb.com/api/public/decklist/{}"

# Small delay between HTTP requests to be polite
REQUEST_DELAY = 0.15

# CARD_CACHE populated from SQL
CARD_CACHE: Dict[str, Dict[str, Any]] = {}


def http_get_json(url: str) -> Tuple[int, Any]:
    if requests:
        try:
            r = requests.get(url, timeout=6)
            status = r.status_code
            if status == 200:
                try:
                    return status, r.json()
                except Exception:
                    return status, r.text
            return status, None
        except requests.exceptions.RequestException as e:
            print(f"[http_get_json] request error for {url}: {e}")
            return 0, None
    else:
        try:
            with urllib.request.urlopen(url, timeout=10) as resp:
                raw = resp.read()
                try:
                    return 200, json.loads(raw.decode('utf-8'))
                except Exception:
                    return 200, raw.decode('utf-8')
        except HTTPError as e:
            return e.code, None
        except URLError:
            return 0, None
        except Exception:
            return 0, None


def fetch_decklist(deck_id: int) -> Dict[str, Any] | None:
    status, data = http_get_json(DECKLIST_URL.format(deck_id))
    if status == 200 and data:
        return data
    return None


def _lookup_card_in_cache(code: str) -> Dict[str, Any] | None:
    if not code:
        return None
    # exact
    if code in CARD_CACHE and CARD_CACHE.get(code):
        return CARD_CACHE.get(code)
    # stripped leading zeros
    stripped = code.lstrip('0')
    if stripped and stripped in CARD_CACHE and CARD_CACHE.get(stripped):
        return CARD_CACHE.get(stripped)
    # try numeric then padded forms
    try:
        ival = int(''.join(ch for ch in code if ch.isdigit()))
        for width in (4, 5, 6):
            padded = str(ival).zfill(width)
            if padded in CARD_CACHE and CARD_CACHE.get(padded):
                return CARD_CACHE.get(padded)
        if str(ival) in CARD_CACHE and CARD_CACHE.get(str(ival)):
            return CARD_CACHE.get(str(ival))
    except Exception:
        pass
    return None


def normalize_card_row(code: str, qty: int) -> Dict[str, Any]:
    card_info = _lookup_card_in_cache(code) or {}
    if card_info and isinstance(card_info, dict):
        name = card_info.get('name') or card_info.get('card_name') or code
        pack_name = card_info.get('pack_name') or card_info.get('pack') or ''
        pack_code = card_info.get('pack_code') or card_info.get('pack_code') or ''
        faction_name = card_info.get('faction_name') or card_info.get('faction') or ''
        faction_code = card_info.get('faction_code') or ''
        type_name = card_info.get('type_name') or card_info.get('type') or ''
        permanent = bool(card_info.get('permanent', False))
    else:
        name = code
        pack_name = ''
        pack_code = ''
        faction_name = ''
        faction_code = ''
        type_name = ''
        permanent = False

    return {
        'card_code': code,
        'card_name': name,
        'qty': int(qty),
        'pack_name': pack_name,
        'pack_code': pack_code,
        'faction_name': faction_name,
        'faction_code': faction_code,
        'type_name': type_name,
        'permanent': permanent,
    }


def build_tsv(decks_data: List[Dict[str, Any]], filename: str) -> None:
    all_cards: Dict[int, List[Dict[str, Any]]] = {}
    max_cards = 0

    for d in decks_data:
        deck_id = d.get('id')
        slots = d.get('slots') or {}
        card_rows: List[Dict[str, Any]] = []
        for code, qty in slots.items():
            card_row = normalize_card_row(code, qty)
            card_rows.append(card_row)
        all_cards[deck_id] = card_rows
        if len(card_rows) > max_cards:
            max_cards = len(card_rows)

    header = ["deck_id", "deck_name", "user_name", "creator", "card_count", "hero_name"]
    for i in range(1, max_cards + 1):
        header.append(f"card_{i}")

    missing_codes = set()
    with open(filename, 'w', encoding='utf-8') as fh:
        fh.write('\t'.join(header) + '\n')

        for d in decks_data:
            deck_id = d.get('id')
            deck_name = d.get('name') or ''
            user_name = d.get('user_name') or d.get('author') or str(d.get('user_id', ''))
            hero_name = d.get('hero_name') or ''
            hero_code = d.get('hero_code') or ''

            card_count = 0
            card_cells: List[str] = []
            for card in all_cards.get(deck_id, []):
                type_name = (card.get('type_name') or '').lower()
                is_permanent = bool(card.get('permanent'))
                if type_name != 'hero' and not is_permanent:
                    card_count += int(card.get('qty', 0))
                qty = card.get('qty', 0)
                name = card.get('card_name') or card.get('card_code')
                card_code = card.get('card_code')
                pack_name = card.get('pack_name') or ''
                pack_code = card.get('pack_code') or ''
                faction_name = card.get('faction_name') or ''
                type_name = card.get('type_name') or ''

                ofhero = ''
                if faction_name:
                    ofhero = ' --of' + faction_name.replace(' ', '')

                cell = f"{qty}x {name} [{card_code}] ({pack_name}){ofhero} --pc{pack_code} --ct{type_name}"
                card_cells.append(cell)
                try:
                    if (not name or name == card_code) or (not pack_name and not type_name):
                        missing_codes.add(card_code)
                except Exception:
                    pass

            while len(card_cells) < max_cards:
                card_cells.append('')

            pack_creator = d.get('creator') or 'MCDB'

            row = [str(deck_id), deck_name, str(user_name), pack_creator, str(card_count), f"{hero_name} [{hero_code}]" if hero_name else '']
            row.extend(card_cells)
            fh.write('\t'.join(row) + '\n')

    print(f"Wrote {filename} with {len(decks_data)} deck(s).")
    if missing_codes:
        missfile = filename.replace('.tsv', '') + '_missing_codes.txt'
        try:
            with open(missfile, 'w', encoding='utf-8') as mf:
                for c in sorted(missing_codes):
                    mf.write(c + '\n')
            print(f"{len(missing_codes)} card code(s) lacked metadata. See {missfile} for the list.")
        except Exception as e:
            print(f"Failed to write missing codes file: {e}")


def _read_symfony_db_params() -> Dict[str, str] | None:
    # Try multiple likely locations for parameters.yml so the script works
    # when executed from tools/ or project root.
    cwd = os.getcwd()
    script_dir = os.path.dirname(os.path.abspath(__file__))
    candidates = []
    # common locations relative to cwd
    candidates.append(os.path.join(cwd, 'app', 'config', 'parameters.yml'))
    candidates.append(os.path.join(cwd, 'app', 'config', 'parameters.yml.dist'))
    # check upward from script dir (in case running from tools/)
    p = script_dir
    for _ in range(6):
        candidates.append(os.path.join(p, 'app', 'config', 'parameters.yml'))
        candidates.append(os.path.join(p, 'app', 'config', 'parameters.yml.dist'))
        p = os.path.dirname(p)

    params_file = None
    for c in candidates:
        if c and os.path.isfile(c):
            params_file = c
            break

    # as a last resort, try a shallow walk from script_dir looking for parameters.yml
    if not params_file:
        for root, dirs, files in os.walk(script_dir):
            if 'parameters.yml' in files:
                params_file = os.path.join(root, 'parameters.yml')
                break

    if not params_file:
        return None

    params = {}
    try:
        print(f"Using Symfony parameters file: {params_file}")
        with open(params_file, 'r', encoding='utf-8') as fh:
            for line in fh:
                line = line.strip()
                if not line or line.startswith('#'):
                    continue
                if ':' in line:
                    k, v = line.split(':', 1)
                    k = k.strip()
                    v = v.strip()
                    # remove quotes
                    if v.startswith('"') and v.endswith('"'):
                        v = v[1:-1]
                    if v == '~' or v.lower() == 'null':
                        v = ''
                    params[k] = v
        return params
    except Exception:
        return None


def _load_cards_from_sql_db(codes: set) -> None:
    params = _read_symfony_db_params()
    if not params:
        print("Could not find app/config/parameters.yml or failed to parse DB parameters.")
        return
    host = params.get('database_host', '127.0.0.1')
    dbname = params.get('database_name') or params.get('database')
    user = params.get('database_user')
    password = params.get('database_password') or ''
    port = int(params.get('database_port', 3306)) if params.get('database_port') else 3306

    dbconn = None
    try:
        import pymysql as connector
        dbconn = connector.connect(host=host, user=user, password=password, database=dbname, port=port, charset='utf8')
    except Exception:
        try:
            import mysql.connector as connector
            dbconn = connector.connect(host=host, user=user, password=password, database=dbname, port=port)
        except Exception:
            print("No MySQL driver found. Install pymysql (pip install pymysql) or mysql-connector-python.")
            return

    try:
        cur = dbconn.cursor()
        expanded = set()
        for code in codes:
            if not code:
                continue
            expanded.add(code)
            stripped = code.lstrip('0')
            if stripped:
                expanded.add(stripped)
            # try numeric portion
            digits = ''.join(ch for ch in code if ch.isdigit())
            if digits:
                try:
                    ival = int(digits)
                    expanded.add(str(ival))
                    expanded.add(str(ival).zfill(4))
                    expanded.add(str(ival).zfill(5))
                    expanded.add(str(ival).zfill(6))
                except Exception:
                    pass

        expanded = {str(x) for x in expanded if x}
        if not expanded:
            return

        placeholders = ','.join(['%s'] * len(expanded))
        sql = (
            "SELECT c.code as code, c.name as name, p.name as pack_name, p.code as pack_code, "
            "f.name as faction_name, f.code as faction_code, t.name as type_name, c.permanent as permanent "
            "FROM card c JOIN pack p ON c.pack_id = p.id "
            "LEFT JOIN faction f ON c.faction_id = f.id "
            "LEFT JOIN type t ON c.type_id = t.id WHERE c.code IN (" + placeholders + ")"
        )
        cur.execute(sql, tuple(expanded))
        rows = cur.fetchall()
        cols = [d[0] for d in cur.description]
        count = 0
        for row in rows:
            rec = dict(zip(cols, row))
            code = str(rec.get('code'))
            entry = {
                'name': rec.get('name'),
                'pack_name': rec.get('pack_name') or '',
                'pack_code': rec.get('pack_code') or '',
                'faction_name': rec.get('faction_name') or '',
                'faction_code': rec.get('faction_code') or '',
                'type_name': rec.get('type_name') or '',
                'permanent': bool(rec.get('permanent')),
            }
            variants = set()
            variants.add(code)
            try:
                digits = ''.join(ch for ch in code if ch.isdigit())
                if digits:
                    ival = int(digits)
                    variants.add(str(ival))
                    variants.add(str(ival).zfill(4))
                    variants.add(str(ival).zfill(5))
                    variants.add(str(ival).zfill(6))
            except Exception:
                pass
            stripped = code.lstrip('0')
            if stripped:
                variants.add(stripped)
            for v in variants:
                if v:
                    CARD_CACHE[v] = entry
            count += 1
        print(f"Loaded {count} card rows from SQL DB into CARD_CACHE.")
        # second pass: try per-missing-code LIKE queries to catch variants (suffixes, different padding)
        missing_after_batch = [c for c in codes if not _lookup_card_in_cache(c)]
        if missing_after_batch:
            print(f"{len(missing_after_batch)} codes not found in batch query; trying per-code fallback queries...")
            for oc in missing_after_batch:
                try:
                    # build candidate patterns: exact code, digits-only, zero-padded digits
                    digits = ''.join(ch for ch in oc if ch.isdigit())
                    patterns = []
                    if oc:
                        patterns.append(oc)
                    if digits:
                        patterns.append(digits)
                        patterns.append(str(int(digits)).zfill(4))
                        patterns.append(str(int(digits)).zfill(5))
                    # try LIKE on code to catch suffixes/prefixes
                    found = False
                    for pat in patterns:
                        like = '%' + pat + '%'
                        sql_like = (
                            "SELECT c.code as code, c.name as name, p.name as pack_name, p.code as pack_code, "
                            "f.name as faction_name, f.code as faction_code, t.name as type_name, c.permanent as permanent "
                            "FROM card c JOIN pack p ON c.pack_id = p.id "
                            "LEFT JOIN faction f ON c.faction_id = f.id "
                            "LEFT JOIN type t ON c.type_id = t.id WHERE c.code LIKE %s LIMIT 20"
                        )
                        cur.execute(sql_like, (like,))
                        rows2 = cur.fetchall()
                        if not rows2:
                            continue
                        cols2 = [d[0] for d in cur.description]
                        for row2 in rows2:
                            rec2 = dict(zip(cols2, row2))
                            code2 = str(rec2.get('code'))
                            entry2 = {
                                'name': rec2.get('name'),
                                'pack_name': rec2.get('pack_name') or '',
                                'pack_code': rec2.get('pack_code') or '',
                                'faction_name': rec2.get('faction_name') or '',
                                'faction_code': rec2.get('faction_code') or '',
                                'type_name': rec2.get('type_name') or '',
                                'permanent': bool(rec2.get('permanent')),
                            }
                            # register variants
                            vset = set()
                            vset.add(code2)
                            try:
                                digits2 = ''.join(ch for ch in code2 if ch.isdigit())
                                if digits2:
                                    ival2 = int(digits2)
                                    vset.add(str(ival2))
                                    vset.add(str(ival2).zfill(4))
                                    vset.add(str(ival2).zfill(5))
                                    vset.add(str(ival2).zfill(6))
                            except Exception:
                                pass
                            stripped2 = code2.lstrip('0')
                            if stripped2:
                                vset.add(stripped2)
                            for vv in vset:
                                if vv:
                                    CARD_CACHE[vv] = entry2
                        found = True
                        if found:
                            break
                except Exception:
                    pass
    finally:
        try:
            cur.close()
        except Exception:
            pass
        try:
            dbconn.close()
        except Exception:
            pass


def main(xmin: int = XMIN, xmax: int = XMAX):
    decks: List[Dict[str, Any]] = []

    for deck_id in range(xmin, xmax + 1):
        print(f"Fetching deck {deck_id}...", end=' ', flush=True)
        d = fetch_decklist(deck_id)
        if d:
            decks.append(d)
            print("OK")
        else:
            print("not found or error")
        time.sleep(REQUEST_DELAY)

    if not decks:
        print("No decks fetched in the given range. Exiting.")
        return

    # populate CARD_CACHE from SQL for all codes present in fetched decks
    try:
        codes = set()
        for d in decks:
            slots = d.get('slots') or {}
            for code in slots.keys():
                if code:
                    codes.add(code)
        if codes:
            print(f"Loading metadata for {len(codes)} unique card codes from SQL DB...")
            _load_cards_from_sql_db(codes)
        else:
            print("No card codes found in fetched decks.")
    except Exception as e:
        print(f"SQL metadata loading failed: {e}")

    out_name = f"marvelcdb_{xmin}_{xmax}.tsv"
    build_tsv(decks, out_name)


if __name__ == '__main__':
    if len(sys.argv) >= 3:
        try:
            xmin = int(sys.argv[1])
            xmax = int(sys.argv[2])
        except Exception:
            print("Usage: python marvelcdb_decks_to_tsv.py [xmin xmax]")
            sys.exit(2)
        main(xmin, xmax)
    else:
        main()

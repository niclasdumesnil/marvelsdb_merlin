#!/usr/bin/env python3
import json
from pathlib import Path

ROOT = Path(r"C:/github/marvelsdb_fanmade_data")
PACK_DIR = ROOT / 'pack'
SETS_FILE = ROOT / 'sets_fanmade.json'
PACKS_FILE = ROOT / 'packs_fanmade.json'

def guess_set_type(code: str) -> str:
    lc = code.lower()
    if 'nemesis' in lc:
        return 'nemesis'
    if 'modular' in lc:
        return 'modular'
    if 'expedition' in lc or 'special' in lc:
        return 'hero_special'
    return 'hero'

def load_json(path: Path):
    if not path.exists():
        return []
    return json.loads(path.read_text(encoding='utf-8'))

def save_json(path: Path, data):
    path.write_text(json.dumps(data, ensure_ascii=False, indent=4), encoding='utf-8')

def main():
    sets = load_json(SETS_FILE)
    packs = load_json(PACKS_FILE)
    sets_by_code = {s.get('code'): s for s in sets if isinstance(s, dict) and 'code' in s}
    packs_by_code = {p.get('code'): p for p in packs if isinstance(p, dict) and 'code' in p}

    updated_sets = False
    updated_packs = False

    for pack_file in sorted(PACK_DIR.glob('*.json')):
        name = pack_file.stem
        # skip encounter files (we'll only update pack metadata from main file)
        if name.endswith('_encounter'):
            continue
        try:
            data = json.loads(pack_file.read_text(encoding='utf-8'))
        except Exception as e:
            print(f"Failed to read {pack_file}: {e}")
            continue
        # compute number of cards in this pack (count entries)
        size = len(data)
        if name in packs_by_code:
            if packs_by_code[name].get('size') != size:
                print(f"Updating size for pack {name}: {packs_by_code[name].get('size')} -> {size}")
                packs_by_code[name]['size'] = size
                updated_packs = True
        else:
            # not present in packs_fanmade.json — add minimal entry
            print(f"Adding missing pack entry for {name} with size {size}")
            new_pack = {
                'code': name,
                'name': name,
                'size': size,
                'status': 'unknown'
            }
            packs.append(new_pack)
            packs_by_code[name] = new_pack
            updated_packs = True

        # inspect sets inside pack
        for card in data:
            set_code = card.get('set_code')
            if not set_code:
                continue
            if set_code not in sets_by_code:
                guessed = guess_set_type(set_code)
                new_set = {'code': set_code, 'name': set_code, 'card_set_type_code': guessed}
                print(f"Adding missing set {set_code} guessed type {guessed}")
                sets.append(new_set)
                sets_by_code[set_code] = new_set
                updated_sets = True

    if updated_sets:
        save_json(SETS_FILE, sets)
        print(f"Wrote updated sets to {SETS_FILE}")
    else:
        print("No new sets found.")

    if updated_packs:
        save_json(PACKS_FILE, packs)
        print(f"Wrote updated packs to {PACKS_FILE}")
    else:
        print("No pack size changes.")

if __name__ == '__main__':
    main()

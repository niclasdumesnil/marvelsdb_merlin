# Tools

`generate_fr_translations.php`
- Purpose: generate a French cards JSON file by taking the English reference JSON and replacing translatable fields from French pack JSON files.

Usage example (run from project root `c:\github\marvelsdb_merlin`):

```bash
php tools/generate_fr_translations.php --en=tools/data/cards-all-en.json --fr-dir=..\\marvelsdb_fanmade_data\\translations\\fr\\pack --out=tools/data/cards-all-fr.json
```

To also generate the `cards-player` FR file from an English player file, provide `--player-en` and optionally `--player-out`:

```bash
php tools/generate_fr_translations.php --en=tools/data/cards-all-en.json --fr-dir=..\\marvelsdb_fanmade_data\\translations\\fr\\pack --player-en=tools/data/cards-player-en.json --player-out=tools/data/cards-player-fr.json
```

Optionally, pass `--pretty` to produce a printer-friendly (pretty-printed) JSON. By default the script outputs compact JSON to minimize filesize.

Example (pretty):

```bash
php tools/generate_fr_translations.php --en=tools/data/cards-all-en.json --fr-dir=..\\marvelsdb_fanmade_data\\translations\\fr\\pack --player-en=tools/data/cards-player-en.json --player-out=tools/data/cards-player-fr.json --pretty
```

Options:
- `--en` : path to the English reference JSON (required)
- `--fr-dir` : path to the directory containing French pack JSON files (required)
- `--out` : output path (optional, defaults to `tools/data/cards-all-fr.json`)

Notes:
- The script matches cards by their `code` field and replaces `name`, `text`, `flavor`, `traits`, `subname`, `back_name`, `back_text`, `back_flavor` when present in the French pack files.
- It will create the output directory if needed and writes pretty-printed UTF-8 JSON.

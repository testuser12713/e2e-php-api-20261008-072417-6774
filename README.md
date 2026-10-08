# Lesezeichen-REST-API

Eine frameworkfreie REST-API für Lesezeichen in PHP 8.1+. Jedes Lesezeichen
besteht aus einer URL, einem Titel und beliebig vielen Schlagwörtern und wird in
einer SQLite-Datei über PDO gespeichert. Die API liefert und empfängt
ausschließlich JSON und wird über den eingebauten PHP-Webserver (`php -S`) mit
`public/index.php` als Front-Controller betrieben. Es gibt kein Framework und
außer PHPUnit (nur für Entwicklung/Tests) keine externe Abhängigkeit.

## Tech-Stack

- **Sprache:** PHP 8.1+ (`declare(strict_types=1)`, PSR-12)
- **Framework:** keines – eigener Mini-Router in `src/Routing/Router.php`
- **Web-Runtime:** eingebauter PHP-Webserver, `public/index.php` als Front-Controller
- **Datenbank:** SQLite-Datei über PDO, ausschließlich Prepared Statements, Foreign Keys aktiv
- **Autoload:** Composer, PSR-4: `App\` → `src/`
- **Tests:** PHPUnit gegen eine temporäre SQLite-Datei, ohne laufenden Webserver

## Installation

```bash
composer install
```

## Starten (Entwicklung)

Der eingebaute PHP-Webserver startet die API direkt aus dem Projektverzeichnis:

```bash
php -S 0.0.0.0:8080 -t public public/index.php
```

Danach ist die API unter `http://localhost:8080` erreichbar; der Health-Check
liegt unter `http://localhost:8080/health`.

### Build für Produktion

Das Projekt hat keinen Build-Schritt: PHP wird nicht kompiliert. Für den
Produktivbetrieb genügt derselbe Startbefehl hinter einem Webserver oder
Prozess-Manager, mit gesetztem `DB_PATH` auf einen persistenten Pfad.

## Konfiguration

| Variable | Pflicht | Standard | Beschreibung |
| --- | --- | --- | --- |
| `DB_PATH` | nein | `var/bookmarks.sqlite` | Pfad zur SQLite-Datei. Das Verzeichnis wird beim Start automatisch angelegt, das Schema beim Start automatisch migriert. |

Beispiel:

```bash
DB_PATH=/var/lib/bookmark-api/bookmarks.sqlite php -S 0.0.0.0:8080 -t public public/index.php
```

## Verwendung (Endpunkte)

Alle Antworten tragen `Content-Type: application/json` – ausgenommen die
`204`-Antwort, die keinen Body hat. Fehlerantworten haben immer die Form:

```json
{ "error": { "code": "…", "message": "…" } }
```

Mögliche Fehlercodes: `validation_error`, `invalid_json`, `not_found`,
`method_not_allowed`, `not_implemented`, `internal_error`.

Ein Lesezeichen-Objekt sieht so aus:

```json
{
  "id": 1,
  "url": "https://example.com",
  "title": "Beispiel",
  "tags": ["php", "api"],
  "created_at": "2026-10-08T12:00:00Z",
  "updated_at": "2026-10-08T12:00:00Z"
}
```

| Methode | Pfad | Body | Antwort |
| --- | --- | --- | --- |
| GET | `/health` | – | `200 {"status":"ok"}` |
| POST | `/bookmarks` | `{"url": string, "title": string, "tags": string[]}` | `201` Lesezeichen |
| GET | `/bookmarks` | – | `200 {"bookmarks": Lesezeichen[]}` (neueste zuerst) |
| GET | `/bookmarks?tag=<x>` | – | `200 {"bookmarks": Lesezeichen[]}` (nach Schlagwort gefiltert) |
| PUT | `/bookmarks/{id}` | `{"url": string, "title": string, "tags": string[]}` | `200` Lesezeichen |
| DELETE | `/bookmarks/{id}` | – | `204` ohne Body |

Unbekannte Pfade beantwortet die API mit `404 not_found`, ein bekannter Pfad mit
nicht unterstützter Methode mit `405 method_not_allowed` inklusive
`Allow`-Header.

Beispiele:

```bash
curl -i http://localhost:8080/health

curl -i -X POST http://localhost:8080/bookmarks \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://example.com","title":"Beispiel","tags":["PHP","php","API"]}'

curl -i 'http://localhost:8080/bookmarks?tag=php'
```

Schlagwörter werden getrimmt, kleingeschrieben, dedupliziert und in der
Reihenfolge ihres ersten Auftretens gespeichert.

## Tests

```bash
composer test
# oder
php vendor/bin/phpunit
```

Die Tests laufen ohne Webserver gegen eine temporäre SQLite-Datei.

## Funktionen

- Health-Check `GET /health`
- Mini-Router mit Pfadparametern, `404 not_found` und `405 method_not_allowed` (inkl. `Allow`-Header)
- Einheitlicher JSON-Fehlerkörper `{"error": {"code", "message"}}`
- Validierung und Normalisierung von URL, Titel und Schlagwörtern (`BookmarkInput`)
- SQLite-Speicherung über PDO mit normalisiertem Schema (`bookmarks`, `tags`, `bookmark_tags`, `ON DELETE CASCADE`)
- CRUD auf Lesezeichen inklusive Filter nach Schlagwort (in den Folge-Tickets vervollständigt)

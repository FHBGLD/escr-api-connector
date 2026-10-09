# ESCR API Connector – Deutsch

PHP-Bibliothek zur Kommunikation mit der API v2 des European Student Card Router
(ESCR). Sie verwaltet Karteninhaber und ihre European Student Cards und ruft
SVG-QR-Codes zur Kartenvalidierung ab.

Das Paket stellt einen auf Guzzle basierenden API-Client, Konfigurations- und
Karteninhaberklassen, Enums für akademische Abschlüsse und Kartentypen sowie
Schnittstellen zur Integration bereit.

**Hinweis:** Weitere Informationen zum ESCR-API-Zugang und zu den Funktionen der
European Student Card finden Sie in den offiziellen
[European Student Card Technical Specifications](https://erasmus-plus.ec.europa.eu/resources-and-tools/documents-and-guidelines/european-student-card-technical-specifications).

## Installation

### Voraussetzungen

- PHP >= 8.4 für die Bibliothek.
- Composer.
- Eine ESCR-API-Basis-URL, ein API-Token und die PIC der Organisation für API-Anfragen.

Wenn in Ihrem Projekt ein Composer-Repository konfiguriert ist, das dieses Paket
bereitstellt:

```sh
composer require hawb/escr-api-connector
```

Für die Entwicklung in einer lokalen Kopie dieses Repositorys:

```sh
composer install
```

Für die Bereitstellung in der Produktionsumgebung installieren Sie die
Abhängigkeiten ohne Entwicklungspakete:

```sh
composer install --no-dev --optimize-autoloader
```

`--no-dev` schließt Entwicklungsabhängigkeiten wie PHPUnit und Mockery aus;
`--optimize-autoloader` optimiert den Composer-Autoloader für den Produktivbetrieb.

Laden Sie `vendor/autoload.php` in Ihrer Anwendung, sofern Ihr Framework den
Composer-Autoloader nicht bereits lädt.

## Konfiguration

Alle fünf Konstruktorargumente von `EscConfig` sind erforderlich:

| Argument | Typ | Zweck |
| --- | --- | --- |
| `apiToken` | `string` | Token, das als `Authorization: Bearer <token>` gesendet wird. |
| `baseUrl` | `string` | Basis-URL des API-Servers. Der Client hängt Pfade wie `api/v2/persons` an; dieser Endpunktpfad darf nicht Teil der Basis-URL sein. |
| `organisationPic` | `string` | Organisationskennung, die in den Karteninhaberdaten und als Kennung des Kartenausstellers verwendet wird. |
| `countryCode` | `string` | Ländercode, den `Cardholder::esi()` verwendet, beispielsweise `AT`. |
| `qrCodeRenderSize` | `float` | Gespeicherter Konfigurationswert; wird derzeit vom API-Client nicht verwendet. |

Die Konfigurationseigenschaften sind schreibgeschützt (`readonly`). `EscConfig`
speichert die übergebenen Werte, ohne sie zu validieren oder Umgebungsvariablen
zu laden.

Ersetzen Sie die folgenden Platzhalter für Token, Basis-URL und Organisations-PIC
durch Ihre ESCR-Konfigurationswerte.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Hawb\EscrApiConnector\Classes\EscApiClient;
use Hawb\EscrApiConnector\Classes\EscConfig;

$config = new EscConfig(
    apiToken: 'your-api-token',
    baseUrl: 'https://your-escr-api.example.com/',
    organisationPic: 'your-organisation-pic',
    countryCode: 'AT',
    qrCodeRenderSize: 240.0,
);

$client = new EscApiClient($config);
```

Der standardmäßige HTTP-Client verwendet ein Zeitlimit von 30 Sekunden,
JSON-Header für Anfragen und Antworten sowie `http_errors => false`. Bei Bedarf
wird der Basis-URL ein abschließender Schrägstrich hinzugefügt.

## Verwendung

Die folgenden Beispiele verwenden `$config` und `$client` aus dem
Konfigurationsbeispiel weiter.

### Karteninhaber und Karte anlegen

```php
use Hawb\EscrApiConnector\Classes\Cardholder;
use Hawb\EscrApiConnector\Enums\AcademicLevel;
use Hawb\EscrApiConnector\Enums\CardType;

$uid = '1910783001';
$esi = Cardholder::esi($uid, $config);
// urn:schac:personalUniqueCode:int:esi:AT:1910783001

$cardholder = new Cardholder(
    studentCode: $uid,
    fullName: 'Alex Example',
    email: 'alex@example.com',
    academicLevel: AcademicLevel::BACHELOR,
    cardExpiresAt: '2027-09-30',
    cardType: CardType::SMART_PASSIVE,
);

$client->createCardholder($cardholder, $esi);
$client->createCard($cardholder, $esi);
```

`Cardholder::esi()` entfernt führende und nachfolgende Leerzeichen aus UID und
Ländercode, wandelt den Ländercode in Großbuchstaben um und gibt
`urn:schac:personalUniqueCode:int:esi:<COUNTRY>:<UID>` zurück. Wenn einer der beiden
Werte leer ist, wird eine `InvalidArgumentException` ausgelöst. Die ESI wird
explizit an die Client-Methoden übergeben; `studentCode` wird nicht zur
automatischen Erzeugung verwendet.

Beim Anlegen und Aktualisieren eines Karteninhabers werden die ESI,
`identifierCode: ESI` und ein Organisationseintrag mit PIC, vollständigem Namen,
E-Mail-Adresse und akademischem Abschluss gesendet. Beim Anlegen einer Karte
werden die ESI als `personIdentifier`, die PIC als `issuerIdentifier`, der gewählte
Kartentyp, der Status `ACTIVE`, das heutige Datum als `issuedAt`, das übergebene
Ablaufdatum und `Card of <fullName>` als Anzeigename gesendet. Die Nummer der neu
angelegten Karte wird nicht zurückgegeben.

Der direkte `Cardholder`-Konstruktor speichert Werte, ohne E-Mail-Adresse oder
Ablaufdatum zu validieren. Geben Sie Datumswerte als `Y-m-d` an. `duplicateLabels`
ist standardmäßig ein leeres Array und wird nicht in API-Anfragen übertragen;
`cardType` ist standardmäßig `SMART_PASSIVE`.

### Karteninhaber aus Session-Daten erstellen

`Cardholder::fromSessionData()` akzeptiert die folgenden Studierendendaten:

| Feld | Erforderlich | Verhalten |
| --- | --- | --- |
| `uid` | Ja | Wird als Ersatzwert für die Studierendenkennung und zur Erzeugung der E-Mail-Adresse verwendet. |
| `matrikelnr` | Nein | Studierendenkennung; bei fehlendem oder leerem Wert wird `uid` verwendet. |
| `vorname` | Ja | Vorname. |
| `nachname` | Ja | Nachname; wird mit dem Vornamen zu `fullName` zusammengesetzt. |
| `studiengang_typ` | Ja | `BA`/`B` → Bachelor, `MA`/`M` → Master, `PHD`/`D`/`P` → Doktorat; Groß- und Kleinschreibung wird nicht berücksichtigt. |
| `email` | Nein | Gültige E-Mail-Adresse; bei fehlendem oder leerem Wert als `<uid>@<domain>` erzeugt. |
| `expiryDate` | Ja | Datum im Format `Y-m-d` oder `d.m.Y`, das zu `Y-m-d` vereinheitlicht wird. |

```php
$cardholder = Cardholder::fromSessionData([
    'uid' => '1910783001',
    'matrikelnr' => '1910783001',
    'vorname' => 'Alex',
    'nachname' => 'Example',
    'studiengang_typ' => 'BA',
    'email' => 'alex@example.com',
    'expiryDate' => '30.09.2027',
]);
```

Die Erzeugung der Ersatz-E-Mail-Adresse verwendet `$_ENV['ESC_EMAIL_DOMAIN']`;
der Standardwert ist `hochschule-burgenland.at`. Pflichtfelder akzeptieren
Zeichenketten oder Ganzzahlen; führende und nachfolgende Leerzeichen werden
entfernt. Fehlende oder leere Pflichtwerte, nicht unterstützte Studiengangstypen,
ungültige E-Mail-Adressen und ungültige Datumswerte lösen eine
`InvalidArgumentException` aus.

### Datensätze suchen und abrufen

```php
$persons = $client->listCardholders(page: 0, size: 20, search: 'Example');
$person = $client->getCardholder($esi);
$exists = $client->cardholderExists($esi);

$cards = $client->listCards(
    page: 0,
    size: 20,
    search: $esi,
    searchField: 'person.identifier',
);

foreach ($cards['content'] ?? [] as $card) {
    $details = $client->getCard($card['cardNumber']);
}
```

Listenmethoden verwenden Seitennummern ab null, liefern standardmäßig 20
Datensätze pro Seite und fordern eine aufsteigende Sortierung an. Eine nicht leere
Suchanfrage wird als `<searchField>~<search>` gesendet; das Standardfeld ist
`fullName` für Karteninhaber und `displayName` für Karten. Antworten werden in
assoziative Arrays statt in `Cardholder`-Objekte dekodiert. Die API liefert
Metadaten zur Seiteneinteilung unter `page`, darunter `totalElements` und
`totalPages`.

`getCardholder()` ergänzt die dekodierte Antwort um einen Eintrag `status_code`.
`cardholderExists()` prüft, ob dieser Eintrag den Wert `200` hat.
`cardExistsForCardholder()` durchsucht `person.identifier` mit einer Seitengröße
von einem Datensatz und prüft, ob `page.totalElements` größer als null ist.

### Datensätze aktualisieren und löschen

```php
$cardholder->fullName = 'Alex Updated';
$client->updateCardholder($cardholder, $esi);

// Alle passenden Karten löschen, dann den Karteninhaber löschen.
$client->deleteCardsForCardholder($esi);
$client->deleteCardholder($esi);
```

`deleteCardsForCardholder()` sammelt Kartennummern über alle Ergebnisseiten in
Blöcken von 100 Datensätzen, entfernt doppelte Nummern und löscht jede Karte. Um
eine einzelne Karte zu löschen, rufen Sie `deleteCard($escn)` mit ihrer
Kartennummer auf. `deleteCardholder()` löscht selbst keine Karten. Es gibt keine
Methode zum Aktualisieren einer Karte.

### QR-Code abrufen

```php
$svg = $client->getQrCodeSvgByEsi($esi);
if ($svg !== null) {
    file_put_contents(__DIR__ . '/card-qr.svg', $svg);
}

// Wenn bereits eine Kartennummer vorliegt:
// $svg = $client->getCardQrCode($escn);
```

`getQrCodeSvgByEsi()` prüft, ob der Karteninhaber existiert, wählt die erste
passende Karte aus und ruft deren QR-Code ab. Bei leerer ESI, fehlendem
Karteninhaber oder fehlender beziehungsweise leerer Kartennummer wird `null`
zurückgegeben. Karten werden nicht nach ihrem Status gefiltert.

`getCardQrCode()` fordert `image/svg+xml` mit `orientation=horizontal`,
`colours=normal` und `size=M` an. Die Methode gibt den unverarbeiteten
Antwortinhalt zurück; der konfigurierte Wert `qrCodeRenderSize` verändert diese
Anfrageparameter nicht.

## Referenz der API-Methoden

Alle Methoden gehören zu `Hawb\EscrApiConnector\Classes\EscApiClient`.
`$esi` identifiziert einen Karteninhaber; `$escn` ist eine Kartennummer.

| Methode | Rückgabetyp | Operation |
| --- | --- | --- |
| `listCardholders($page = 0, $size = 20, $search = '', $searchField = 'fullName')` | `array` | `GET api/v2/persons` |
| `getCardholder($esi)` | `array` | `GET api/v2/persons/{esi}`; enthält `status_code`. |
| `cardholderExists($esi)` | `bool` | Prüft den Antwortstatus für den Karteninhaber. |
| `createCardholder($cardholder, $esi)` | `void` | `POST api/v2/persons` |
| `updateCardholder($cardholder, $esi)` | `void` | `PUT api/v2/persons/{esi}` |
| `deleteCardholder($esi)` | `bool` | `DELETE api/v2/persons/{esi}` |
| `listCards($page = 0, $size = 20, $search = '', $searchField = 'displayName')` | `array` | `GET api/v2/cards` |
| `getCard($escn)` | `array` | `GET api/v2/cards/{escn}` |
| `cardExistsForCardholder($esi)` | `bool` | Prüft die Anzahl passender Karten. |
| `createCard($cardholder, $esi)` | `bool` | `POST api/v2/cards` |
| `deleteCard($escn)` | `bool` | `DELETE api/v2/cards/{escn}` |
| `deleteCardsForCardholder($esi)` | `bool` | Listet alle passenden Karten auf und löscht sie. |
| `getCardQrCode($escn)` | `string` | `GET api/v2/cards/{escn}/qr` |
| `getQrCodeSvgByEsi($esi)` | `?string` | Sucht eine Karte und ruft deren QR-Code ab. |

Die Werte von `AcademicLevel` sind `BACHELOR`, `MASTER` und `DOCTORATE`. Die Werte
von `CardType` sind `UNKNOWN`, `PASSIVE`, `SMART_NO_CDZ`, `SMART_CDZ`,
`SMART_MAY_SP`, `SMART_PASSIVE` und `SMART_PASSIVE_EMULATION`.

## Fehlerbehandlung

Ausnahmen bei Guzzle-Anfragen werden in
`Hawb\EscrApiConnector\Exceptions\EscApiException` verpackt. Diese Klasse
erweitert `RuntimeException` und bewahrt die ursprüngliche Ausnahme auf.
Ungültiges JSON löst in Methoden, die Antworten dekodieren, direkt eine
`JsonException` aus.

Beim standardmäßigen Client lösen HTTP-Antworten mit Status 4xx/5xx nicht
automatisch eine Ausnahme aus, da `http_errors` deaktiviert ist. Schreibende
Methoden prüfen die Antwortstatuscodes nicht: `createCardholder()` und
`updateCardholder()` geben nichts zurück; Methoden zum Anlegen von Karten und
zum Löschen geben `true` zurück, solange keine Ausnahme bei der Anfrage auftritt.
Diese Rückgabewerte allein bestätigen nicht, dass die API eine Operation
akzeptiert hat. QR-Methoden geben ebenfalls den Antwortinhalt zurück, ohne den
Status zu prüfen oder das SVG zu validieren.

Für Anwendungen, in denen HTTP-Fehler Ausnahmen auslösen sollen, übergeben Sie
einen Guzzle-Client mit `http_errors => true`:

```php
use GuzzleHttp\Client;

$httpClient = new Client([
    'base_uri' => rtrim($config->baseUrl, '/') . '/',
    'headers' => [
        'Authorization' => 'Bearer ' . $config->apiToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ],
    'timeout' => 30,
    'http_errors' => true,
]);

$client = new EscApiClient($config, $httpClient);
```

Ein übergebener Guzzle-Client ersetzt den standardmäßigen Client vollständig;
konfigurieren Sie dessen Basis-URL, Authentifizierung, Header, Zeitlimit und
Handler selbst. Bei aktivierter HTTP-Fehlerbehandlung löst auch eine Antwort mit
Status `404` eine `EscApiException` aus, einschließlich bei Existenzprüfungen.

## Integrationsschnittstellen

Der Namespace `Contracts` enthält Schnittstellen zur Integration in Anwendungen:

| Schnittstelle | Zweck |
| --- | --- |
| `EscApiLoggerInterface` | Protokolliert eine PSR-HTTP-Anfrage, eine optionale Antwort beziehungsweise einen Fehler sowie die Dauer in Millisekunden. |
| `EscAdminStudentRepositoryInterface` | Listet berechtigte Studierende mit UID, Matrikelnummer, Namen und Studiengangstyp auf. |
| `EscAdminSnapshotRepositoryInterface` | Ersetzt einen Admin-Snapshot atomar und ruft gefilterte Seiten ab. |
| `EscOperationRepositoryInterface` | Reiht Operationen ein, reserviert sie zur Verarbeitung und markiert sie anschließend als abgeschlossen oder fehlgeschlagen, mit einer Wartezeit bis zum erneuten Versuch. |

Implementierungen sind nicht enthalten. Die Snapshot- und Operationsschnittstellen
verweisen auf Typen in `Hawb\MemberCard\Classes`, die weder von diesem Paket
bereitgestellt noch als Composer-Abhängigkeit deklariert werden. Anwendungen,
die diese Schnittstellen verwenden, müssen die entsprechenden Typen bereitstellen.
Der API-Client bindet diese Repositorys oder den Logger nicht automatisch ein.

## Tests lokal ausführen

Führen Sie nach der Installation der Entwicklungsabhängigkeiten im
Stammverzeichnis des Repositorys Folgendes aus:

```sh
composer test
```

Oder rufen Sie PHPUnit direkt auf:

```sh
vendor/bin/phpunit
vendor/bin/phpunit --testdox
```

Die Tests decken die Konfiguration, die Umwandlung von Karteninhaberdaten, die
ESI-Erzeugung und die Operationen des API-Clients ab. HTTP-Anfragen werden mit
Guzzles `MockHandler` simuliert; die Tests benötigen weder API-Zugangsdaten noch
einen laufenden ESCR-Server.

### Testabdeckung

Aktivieren Sie einen Treiber für die Testabdeckung, beispielsweise Xdebug, und
führen Sie anschließend Folgendes aus:

```sh
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text --coverage-html=coverage-html
```

| Option | Zweck |
| --- | --- |
| `--testdox` | Zeigt Testnamen in einem lesbaren Format an. |
| `--coverage-text` | Gibt die Testabdeckung in Prozent auf der Konsole aus. |
| `--coverage-html=coverage-html` | Schreibt einen HTML-Bericht zur Testabdeckung nach `coverage-html/`. |

GitLab CI führt die Unit-Tests unter PHP 8.4 und 8.5 mit `cicd/phpunit.xml` und
Xdebug-Testabdeckung aus. Die Pipeline enthält außerdem SAST und die Erkennung
von Geheimnissen (Secret Detection).

## Lizenz

MIT. Siehe [LICENSE.md](LICENSE.md).

---

# English

# ESCR API Connector

PHP library for communicating with the European Student Card Router (ESCR) API v2.
It manages cardholders and their European Student Cards and retrieves SVG QR codes
for card validation.

The package provides a Guzzle-based API client, configuration and cardholder
classes, academic-level and card-type enums, and integration interfaces.

**Note:** For details on ESCR API access and European Student Card features, refer
to the official
[European Student Card Technical Specifications](https://erasmus-plus.ec.europa.eu/resources-and-tools/documents-and-guidelines/european-student-card-technical-specifications).

## Installation

### Requirements

- PHP >= 8.4 for the library.
- Composer.
- An ESCR API base URL, API token, and organisation PIC for API requests.

With a Composer repository that provides this package configured in your project:

```sh
composer require hawb/escr-api-connector
```

For development from a checkout of this repository:

```sh
composer install
```

For production deployment, install dependencies without development packages:

```sh
composer install --no-dev --optimize-autoloader
```

`--no-dev` excludes development dependencies such as PHPUnit and Mockery;
`--optimize-autoloader` optimizes Composer's autoloader for production.

Load `vendor/autoload.php` in your application, unless your framework already
loads Composer's autoloader.

## Configuration

All five `EscConfig` constructor arguments are required:

| Argument | Type | Purpose |
| --- | --- | --- |
| `apiToken` | `string` | Token sent as `Authorization: Bearer <token>`. |
| `baseUrl` | `string` | API server base URL. The client appends paths such as `api/v2/persons`; omit that endpoint path from the base URL. |
| `organisationPic` | `string` | Organisation identifier used in cardholder payloads and as the card issuer identifier. |
| `countryCode` | `string` | Country code used by `Cardholder::esi()`, for example `AT`. |
| `qrCodeRenderSize` | `float` | Stored configuration value; currently unused by the API client. |

Configuration properties are readonly. `EscConfig` stores the supplied values
without validating them or loading environment variables.

Replace the placeholder token, base URL, and organisation PIC below with your
ESCR configuration values.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Hawb\EscrApiConnector\Classes\EscApiClient;
use Hawb\EscrApiConnector\Classes\EscConfig;

$config = new EscConfig(
    apiToken: 'your-api-token',
    baseUrl: 'https://your-escr-api.example.com/',
    organisationPic: 'your-organisation-pic',
    countryCode: 'AT',
    qrCodeRenderSize: 240.0,
);

$client = new EscApiClient($config);
```

The default HTTP client uses a 30-second timeout, JSON request/response headers,
and `http_errors => false`. A trailing slash is added to the base URL as needed.

## Usage

The examples below continue with `$config` and `$client` from the configuration
example.

### Create a cardholder and card

```php
use Hawb\EscrApiConnector\Classes\Cardholder;
use Hawb\EscrApiConnector\Enums\AcademicLevel;
use Hawb\EscrApiConnector\Enums\CardType;

$uid = '1910783001';
$esi = Cardholder::esi($uid, $config);
// urn:schac:personalUniqueCode:int:esi:AT:1910783001

$cardholder = new Cardholder(
    studentCode: $uid,
    fullName: 'Alex Example',
    email: 'alex@example.com',
    academicLevel: AcademicLevel::BACHELOR,
    cardExpiresAt: '2027-09-30',
    cardType: CardType::SMART_PASSIVE,
);

$client->createCardholder($cardholder, $esi);
$client->createCard($cardholder, $esi);
```

`Cardholder::esi()` trims the UID and country code, uppercases the country code,
and returns `urn:schac:personalUniqueCode:int:esi:<COUNTRY>:<UID>`. It throws
`InvalidArgumentException` if either value is empty. The ESI is passed explicitly
to client methods; `studentCode` is not used to generate it automatically.

Cardholder creation and updates send the ESI, `identifierCode: ESI`, and an
organisation entry containing the PIC, full name, email, and academic level.
Card creation sends the ESI as `personIdentifier`, the PIC as `issuerIdentifier`,
the selected card type, status `ACTIVE`, today's date as `issuedAt`, the supplied
expiration date, and `Card of <fullName>` as the display name. It does not return
the newly created card number.

The direct `Cardholder` constructor stores values without validating email or
expiration date. Supply dates as `Y-m-d`. `duplicateLabels` defaults to an empty
array and is not included in API payloads; `cardType` defaults to `SMART_PASSIVE`.

### Build a cardholder from session data

`Cardholder::fromSessionData()` accepts the following student-data fields:

| Field | Required | Behaviour |
| --- | --- | --- |
| `uid` | Yes | Used for the student-code fallback and generated email address. |
| `matrikelnr` | No | Student code; falls back to `uid` when missing or empty. |
| `vorname` | Yes | First name. |
| `nachname` | Yes | Last name; combined with the first name into `fullName`. |
| `studiengang_typ` | Yes | `BA`/`B` → bachelor, `MA`/`M` → master, `PHD`/`D`/`P` → doctorate; case-insensitive. |
| `email` | No | Valid email address; when missing or empty, generated as `<uid>@<domain>`. |
| `expiryDate` | Yes | Date in `Y-m-d` or `d.m.Y` format, normalized to `Y-m-d`. |

```php
$cardholder = Cardholder::fromSessionData([
    'uid' => '1910783001',
    'matrikelnr' => '1910783001',
    'vorname' => 'Alex',
    'nachname' => 'Example',
    'studiengang_typ' => 'BA',
    'email' => 'alex@example.com',
    'expiryDate' => '30.09.2027',
]);
```

The email fallback uses `$_ENV['ESC_EMAIL_DOMAIN']`, defaulting to
`hochschule-burgenland.at`. Required fields accept strings or integers and are
trimmed. Missing or empty required values, unsupported study types, invalid
emails, and invalid dates throw `InvalidArgumentException`.

### Search and retrieve records

```php
$persons = $client->listCardholders(page: 0, size: 20, search: 'Example');
$person = $client->getCardholder($esi);
$exists = $client->cardholderExists($esi);

$cards = $client->listCards(
    page: 0,
    size: 20,
    search: $esi,
    searchField: 'person.identifier',
);

foreach ($cards['content'] ?? [] as $card) {
    $details = $client->getCard($card['cardNumber']);
}
```

List methods use zero-based pages, default to 20 records, and request ascending
order. A non-empty search is sent as `<searchField>~<search>`; the default field
is `fullName` for cardholders and `displayName` for cards. Responses are decoded
into associative arrays rather than `Cardholder` objects. Pagination metadata
is supplied by the API under `page`, including `totalElements` and `totalPages`.

`getCardholder()` adds a `status_code` entry to the decoded response.
`cardholderExists()` checks whether that entry equals `200`.
`cardExistsForCardholder()` searches `person.identifier` with a page size of one
and checks whether `page.totalElements` is greater than zero.

### Update and delete records

```php
$cardholder->fullName = 'Alex Updated';
$client->updateCardholder($cardholder, $esi);

// Delete all matching cards, then delete the cardholder.
$client->deleteCardsForCardholder($esi);
$client->deleteCardholder($esi);
```

`deleteCardsForCardholder()` collects card numbers across all result pages in
batches of 100, removes duplicate numbers, and deletes each card. To delete an
individual card, call `deleteCard($escn)` with its card number. Calling
`deleteCardholder()` does not itself delete cards. There is no card-update method.

### Retrieve a QR code

```php
$svg = $client->getQrCodeSvgByEsi($esi);
if ($svg !== null) {
    file_put_contents(__DIR__ . '/card-qr.svg', $svg);
}

// If you already have a card number:
// $svg = $client->getCardQrCode($escn);
```

`getQrCodeSvgByEsi()` checks that the cardholder exists, selects the first matching
card, and fetches its QR code. It returns `null` for an empty ESI, a missing
cardholder, or a missing/empty card number. It does not filter cards by status.

`getCardQrCode()` requests `image/svg+xml` with `orientation=horizontal`,
`colours=normal`, and `size=M`. It returns the raw response body; the configured
`qrCodeRenderSize` does not change these request parameters.

## API method reference

All methods belong to `Hawb\EscrApiConnector\Classes\EscApiClient`.
`$esi` identifies a cardholder; `$escn` is a card number.

| Method | Return type | Operation |
| --- | --- | --- |
| `listCardholders($page = 0, $size = 20, $search = '', $searchField = 'fullName')` | `array` | `GET api/v2/persons` |
| `getCardholder($esi)` | `array` | `GET api/v2/persons/{esi}`; includes `status_code`. |
| `cardholderExists($esi)` | `bool` | Checks the cardholder response status. |
| `createCardholder($cardholder, $esi)` | `void` | `POST api/v2/persons` |
| `updateCardholder($cardholder, $esi)` | `void` | `PUT api/v2/persons/{esi}` |
| `deleteCardholder($esi)` | `bool` | `DELETE api/v2/persons/{esi}` |
| `listCards($page = 0, $size = 20, $search = '', $searchField = 'displayName')` | `array` | `GET api/v2/cards` |
| `getCard($escn)` | `array` | `GET api/v2/cards/{escn}` |
| `cardExistsForCardholder($esi)` | `bool` | Checks the matching card count. |
| `createCard($cardholder, $esi)` | `bool` | `POST api/v2/cards` |
| `deleteCard($escn)` | `bool` | `DELETE api/v2/cards/{escn}` |
| `deleteCardsForCardholder($esi)` | `bool` | Lists and deletes all matching cards. |
| `getCardQrCode($escn)` | `string` | `GET api/v2/cards/{escn}/qr` |
| `getQrCodeSvgByEsi($esi)` | `?string` | Finds a card and retrieves its QR code. |

`AcademicLevel` values are `BACHELOR`, `MASTER`, and `DOCTORATE`. `CardType` values
are `UNKNOWN`, `PASSIVE`, `SMART_NO_CDZ`, `SMART_CDZ`, `SMART_MAY_SP`,
`SMART_PASSIVE`, and `SMART_PASSIVE_EMULATION`.

## Error handling

Guzzle request exceptions are wrapped in
`Hawb\EscrApiConnector\Exceptions\EscApiException`, which extends
`RuntimeException` and retains the previous exception. Invalid JSON in methods
that decode responses throws `JsonException` directly.

With the default client, HTTP 4xx/5xx responses do not throw automatically because
`http_errors` is disabled. Write methods do not inspect response status codes:
`createCardholder()` and `updateCardholder()` return nothing, while card creation
and deletion methods return `true` whenever no request exception occurs. Those
return values alone do not confirm that the API accepted an operation. QR methods
also return the response body without checking its status or validating the SVG.

For applications that need HTTP errors to become exceptions, supply a Guzzle
client with `http_errors => true`:

```php
use GuzzleHttp\Client;

$httpClient = new Client([
    'base_uri' => rtrim($config->baseUrl, '/') . '/',
    'headers' => [
        'Authorization' => 'Bearer ' . $config->apiToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ],
    'timeout' => 30,
    'http_errors' => true,
]);

$client = new EscApiClient($config, $httpClient);
```

A supplied Guzzle client replaces the default client entirely; configure its
base URL, authentication, headers, timeout, and handlers yourself. With HTTP
errors enabled, a `404` also throws `EscApiException`, including during existence
checks.

## Integration interfaces

The `Contracts` namespace contains interfaces for application integrations:

| Interface | Purpose |
| --- | --- |
| `EscApiLoggerInterface` | Logs a PSR HTTP request, optional response/error, and duration in milliseconds. |
| `EscAdminStudentRepositoryInterface` | Lists eligible students with UID, matriculation number, names, and study type. |
| `EscAdminSnapshotRepositoryInterface` | Replaces an admin snapshot atomically and retrieves filtered pages. |
| `EscOperationRepositoryInterface` | Enqueues and claims operations, then marks them completed or failed with a retry delay. |

Implementations are not included. The snapshot and operation interfaces reference
types in `Hawb\MemberCard\Classes`, which are not provided by this package or
declared as a Composer dependency. Applications using those interfaces must
provide the corresponding types. The API client does not automatically wire up
these repositories or the logger.

## Run tests locally

From the repository root, after installing development dependencies:

```sh
composer test
```

Or invoke PHPUnit directly:

```sh
vendor/bin/phpunit
vendor/bin/phpunit --testdox
```

Tests cover configuration, cardholder conversion and ESI generation, and API
client operations. HTTP requests are mocked with Guzzle's `MockHandler`; tests
do not require API credentials or a live ESCR server.

### Coverage

Enable a coverage driver such as Xdebug, then run:

```sh
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text --coverage-html=coverage-html
```

| Flag | Purpose |
| --- | --- |
| `--testdox` | Displays test names in a readable format. |
| `--coverage-text` | Prints coverage percentages to the console. |
| `--coverage-html=coverage-html` | Writes an HTML coverage report to `coverage-html/`. |

GitLab CI runs the unit suite on PHP 8.4 and 8.5 using `cicd/phpunit.xml` with
Xdebug coverage. The pipeline also includes SAST and secret detection.

## License

MIT. See [LICENSE.md](LICENSE.md).

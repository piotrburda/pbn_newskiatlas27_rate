# pbn_newskiatlas27_rate

Ocena ośrodków (1–5 gwiazdek) dla atlasu `pbn_newskiatlas27`. Pytania definiujesz w backendzie. Widżet dołączasz w dowolnym szablonie Fluid.

- TYPO3 9.5 LTS, PHP 7.4
- brak zależności od `pbn_newskiatlas27`: oceniany rekord to para `tabela + uid`, więc to samo rozszerzenie oceni też np. trasy rowerowe albo newsy
- strona ośrodka zostaje w cache, a status blokady i zapis głosu obsługuje osobny endpoint JSON (`typeNum`, USER_INT)

## Instalacja

1. Skopiuj rozszerzenie do `typo3conf/ext/pbn_newskiatlas27_rate` (albo dodaj przez Composer) i aktywuj je w Extension Managerze.
2. Uruchom **Maintenance → Analyze Database Structure** (utworzy 4 tabele).
3. W szablonie TypoScript strony dodaj statyczny szablon **Ski atlas – rating (pbn_newskiatlas27_rate)**.
4. Utwórz folder (sysfolder) na pytania i głosy, a w stałych TS ustaw jego UID:

```
plugin.tx_pbnnewskiatlas27rate.settings {
    questionStoragePid = 123
    voteStoragePid = 123
}
```

5. Załóż domyślne pytania (albo dodaj je ręcznie w module List):

```
vendor/bin/typo3 pbnrate:seed --pid=123
# bez Composera: typo3/sysext/core/bin/typo3 pbnrate:seed --pid=123
```

## Użycie we Fluid

Karta ośrodka (`pbn_newskiatlas27/Resources/Private/Templates/Resort/Show.html`), np. pod sekcją z danymi:

```html
<f:cObject typoscriptObjectPath="lib.pbnNewskiatlas27Rate"
           data="{table: 'tx_pbnnewskiatlas27_domain_model_resort', uid: resort.uid}" />
```

`table` można pominąć, wtedy użyta będzie pierwsza tabela z `allowedTables`.

Skrócone podsumowanie (gwiazdki pytania podsumowującego i liczba ocen), np. na kartach listy ośrodków:

```html
<f:cObject typoscriptObjectPath="lib.pbnNewskiatlas27RateSummary"
           data="{table: 'tx_pbnnewskiatlas27_domain_model_resort', uid: resort.uid}" />
```

## Pytania w backendzie

- **Grupa pytań** (`Ocena: grupa pytań`): tytuł, opis, pytania (IRRE, kolejność przez przeciąganie)
- **Pytanie**: tytuł, podpowiedź, `Wymagane`, `Pytanie podsumowujące` (jego średnia trafia do skróconego podsumowania), `Tylko dla tabel` (puste = wszystkie oceniane tabele)
- Ukrycie grupy lub pytania chowa je we frontendzie. Oddane odpowiedzi zostają w bazie.
- **Głosy** (`Ocena: głos`) są tylko do odczytu. Zaznaczenie `Wykluczony z wyników` pomija głos w średnich (np. spam), ale głos nadal liczy się do blokady.

Zestaw domyślny (`pbnrate:seed`):

| Grupa | Pytania |
|---|---|
| Ogólna ocena z Twojego wyjazdu | Ocena ogólna (wymagane, podsumowujące), Różnorodność tras, Wyciągi – nowoczesność, Wyciągi – obłożenie (5 = brak kolejek), Przygotowanie tras |
| Dla kogo jest ten ośrodek | Dla rodzin z dziećmi, Dla wszystkich, Dla dobrych narciarzy |

## Blokada głosów

Jeden głos na rekord w okresie `lockPeriod` (domyślnie 259200 s = 3 dni). Po tym czasie można oddać **nowy** głos. Stare głosy zostają, bo warunki w ośrodku się zmieniają.

Blokada działa warstwowo:

1. **Zalogowany użytkownik (`fe_users`)**: blokada po UID użytkownika, najpewniejsza.
2. **Podpisane cookie** (`HttpOnly`, `SameSite=Lax`, HMAC z `encryptionKey`) z czasem głosu dla każdego rekordu. To główna blokada dla anonimowych użytkowników. Po sfałszowaniu cookie jest ignorowane.
3. **Hash IP**: HMAC z IP i rekordu, z limitem `ipLimit` (domyślnie 3) głosów w okresie blokady. Łapie tryb incognito i usunięte cookie, a jednocześnie pozwala zagłosować rodzinie korzystającej z jednego Wi-Fi. Surowe IP nigdy nie trafia do bazy. Z IPv6 używany jest tylko prefiks /64. Hash zawiera rekord, więc głosów na różne ośrodki nie da się ze sobą powiązać.
4. **Ochrona przed botami**: token formularza wydawany przez niebuforowany request `status` (ważny `tokenLifetime`, wysyłka możliwa najwcześniej po `minSubmitTime` s), ukryte pole honeypot i tylko metoda POST. Formularz wysłany z obcej strony nie przejdzie, bo nie zna tokenu.

Sprzątanie hashy po okresie blokady (minimalizacja danych pod RODO) wykonuje zadanie schedulera **Execute console commands** → `pbnrate:cleanup` z opcją `--age=259200` (ta sama wartość co `lockPeriod`), np. raz dziennie.

## Stałe TypoScript

| Stała | Domyślnie | Opis |
|---|---|---|
| `lockPeriod` | 259200 | czas blokady w sekundach |
| `ipLimit` | 3 | maks. głosów z jednego (zanonimizowanego) IP na rekord w okresie blokady, 0 = wyłączone |
| `minSubmitTime` | 3 | minimalny czas (s) od otwarcia formularza do wysyłki |
| `tokenLifetime` | 7200 | ważność tokenu formularza |
| `cookieName` | pbnrate | nazwa cookie |
| `questionStoragePid` / `voteStoragePid` | – | foldery z pytaniami i głosami |
| `allowedTables` | tx_pbnnewskiatlas27_domain_model_resort | tabele, które można oceniać |
| `ajaxTypeNum` | 2727 | typeNum endpointu JSON |
| `ajaxPid` | 0 | strona endpointu (0 = bieżąca) |
| `maxStars` | 5 | liczba gwiazdek |
| `showResults` | 1 | pokazuj średnie |
| `minVotesToShowResults` | 1 | minimalna liczba głosów przed pokazaniem średnich |
| `resultsMaxAge` | 0 | licz tylko głosy nowsze niż N sekund (0 = wszystkie) |
| `decimalSeparator` | , | separator dziesiętny |
| `includeCss` / `includeJs` | 1 | dołączanie domyślnych CSS i JS |

Własne szablony: `plugin.tx_pbnnewskiatlas27rate.view.templateRootPath` i `partialRootPath` (szablony `Widget.html`, `Summary.html`, partiale `Question.html`, `Meter.html`).

## Routing

Bez dodatkowej konfiguracji endpoint ma adres `…/strona?type=2727`. Jeśli strona używa enhancera `PageTypeSuffix`, dopisz do mapy `ocena.json: 2727` (zob. `Configuration/Site/RouteEnhancer.yaml`).

## Cache

Widżet jest cache'owany razem ze stroną i oznaczony tagiem `pbnrate_<tabela>_<uid>`. Po każdym nowym głosie ten tag jest czyszczony, więc średnie na stronie są aktualne, a sama strona nie traci cache. Przy `resultsMaxAge > 0` ustaw rozsądny `config.cache_period`.

## API endpointu

`POST` na stronę z `type=2727`:

- `pbnrate[action]=status`, `pbnrate[table]`, `pbnrate[uid]` → `{success, locked, lockedUntil, token}`
- `pbnrate[action]=vote`, `pbnrate[table]`, `pbnrate[uid]`, `pbnrate[token]`, `pbnrate[answers][<uid pytania>]=1..5` → `{success, lockedUntil, results}` albo `{success:false, error}`; kody błędów: `locked`, `required`, `no_answers`, `too_fast`, `token_expired`, `token_invalid`, `invalid_target`, `bad_request`, `server_error`

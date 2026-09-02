-- ============================================================
-- DIO Södermalm — databasen (Cloudflare D1, alltså SQLite)
--
-- Körs en gång per miljö:
--   npx wrangler d1 execute dio --local --file=schema.sql   (lokalt)
--   npx wrangler d1 execute dio --remote --file=schema.sql  (skarpt)
--
-- Alla tidsstämplar lagras som ISO 8601 i UTC. Adminvyn räknar om till
-- svensk tid vid visning — sparar man lokal tid i databasen får man
-- problemet tillbaka två gånger om året när klockan ställs om.
-- ============================================================

CREATE TABLE IF NOT EXISTS prenumeranter (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  epost         TEXT NOT NULL UNIQUE,
  status        TEXT NOT NULL DEFAULT 'aktiv',   -- aktiv | avanmald
  token         TEXT NOT NULL,                   -- nyckel till avanmälningslänken
  kalla         TEXT NOT NULL DEFAULT 'startsida',
  samtyckestext TEXT NOT NULL DEFAULT '',        -- vad personen faktiskt godkände
  ip            TEXT NOT NULL DEFAULT '',
  webblasare    TEXT NOT NULL DEFAULT '',
  skapad        TEXT NOT NULL,
  avanmald      TEXT
);

CREATE TABLE IF NOT EXISTS utskick (
  id     INTEGER PRIMARY KEY AUTOINCREMENT,
  amne   TEXT NOT NULL,
  text   TEXT NOT NULL,
  skapad TEXT NOT NULL,
  klart  TEXT
);

-- Loggen gör utskicken återupptagbara: dör anropet mitt i en omgång vet vi
-- ändå exakt vilka som redan fått brevet, så ingen får det två gånger.
CREATE TABLE IF NOT EXISTS utskick_logg (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  utskick_id     INTEGER NOT NULL,
  prenumerant_id INTEGER NOT NULL,
  ok             INTEGER NOT NULL DEFAULT 1,
  fel            TEXT,
  skickad        TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ix_utskick_mottagare
  ON utskick_logg (utskick_id, prenumerant_id);

-- Enkel logg som också bär spärrarna: hur många anmälningar en IP hunnit
-- med den senaste timmen, hur många inloggningsförsök som misslyckats.
CREATE TABLE IF NOT EXISTS handelser (
  id     INTEGER PRIMARY KEY AUTOINCREMENT,
  ip     TEXT NOT NULL DEFAULT '',
  typ    TEXT NOT NULL,
  detalj TEXT NOT NULL DEFAULT '',
  skapad TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS ix_handelser_ip ON handelser (ip, typ, skapad);

-- Inloggade adminsessioner. Vi sparar en hash av kakans värde, aldrig
-- värdet självt — läcker databasen ska ingen kunna logga in med innehållet.
CREATE TABLE IF NOT EXISTS sessioner (
  token_hash  TEXT PRIMARY KEY,
  skapad      TEXT NOT NULL,
  giltig_till TEXT NOT NULL
);

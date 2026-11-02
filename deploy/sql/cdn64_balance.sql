-- CDN64: Lastausgleich der Server als Notbremse (2026-11-02). Mehrfach ausfuehrbar, VOR dem PHP einspielen.
-- Die Server melden jede Minute Verkehr je Name und verbundene Tunnel (POST /cdn64o/edge/report, keine neue Edge-Version);
-- daraus speichert das Portal die Last je Server und je Tunnel. Der Job "cdn64bal" (bots/honeypot_cron.php, alle 15 min)
-- zieht hoechstens einen Tunnel von einem ueberlasteten Server (15 min ueber 70 %) auf einen freien (unter 40 %) um.

-- Kapazitaet je Server in Mbit/s (Bezug fuer 70 % / 40 %; 0 = nie Lastausgleich fuer diesen Server). srv07 bis srv10
-- haben je 2000 Mbit/s (Dennis, 2026-11-02); auch neue Server bekommen 2000, sonst von Hand setzen.
ALTER TABLE `cdn64o_edges` ADD COLUMN IF NOT EXISTS `capacity_mbit` int(6) NOT NULL DEFAULT 2000 AFTER `location`;
ALTER TABLE `cdn64o_edges` ALTER COLUMN `capacity_mbit` SET DEFAULT 2000;
UPDATE `cdn64o_edges` SET `capacity_mbit` = 2000 WHERE `capacity_mbit` = 1000 AND `name` IN ('srv07', 'srv08', 'srv09', 'srv10');

-- Last je Server und Minute (Verlauf 2 Stunden): Durchsatz, Tunnel-Verbindungen, Durchsatz je Tunnel (JSON id => Mbit/s).
CREATE TABLE IF NOT EXISTS `cdn64o_edge_load` (
  `edge_id` int(11) NOT NULL,
  `at` datetime NOT NULL,
  `mbit` decimal(10,2) NOT NULL DEFAULT 0,
  `conns` int(11) NOT NULL DEFAULT 0,
  `tunnels` text NOT NULL,
  PRIMARY KEY (`edge_id`, `at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Wo ein Tunnel zuletzt verbunden war (Umzug: der alte Server faellt erst weg, wenn der neue den Tunnel meldet).
CREATE TABLE IF NOT EXISTS `cdn64o_tunnel_seen` (
  `tunnel_id` varchar(24) NOT NULL,
  `edge_id` int(11) NOT NULL,
  `seen_at` datetime NOT NULL,
  PRIMARY KEY (`tunnel_id`, `edge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Umzuege: moving (neuer Server dazu, alter bleibt), done (alter weg), aborted (neuer meldete den Tunnel nicht).
-- Schutz gegen Hin und Her: ein Tunnel bleibt 24 h fest, Quell- und Ziel-Server sind 1 h gesperrt.
CREATE TABLE IF NOT EXISTS `cdn64o_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tunnel_id` varchar(24) NOT NULL,
  `from_edge` int(11) NOT NULL,
  `to_edge` int(11) NOT NULL,
  `state` varchar(8) NOT NULL DEFAULT 'moving',
  `reason` varchar(255) NOT NULL DEFAULT '',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `done_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tunnel_id` (`tunnel_id`),
  KEY `started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

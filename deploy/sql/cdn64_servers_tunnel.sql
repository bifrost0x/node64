-- CDN64: Anzahl der Server je Tunnel statt je Account (2026-11-02, nach cdn64_servers.sql). Mehrfach ausfuehrbar.
-- VOR dem Hochladen von api_v2_cdn64o.php einspielen (ohne die Tabelle nimmt das neue Portal wieder alle Server).
-- Business waehlt je Tunnel 1 bis 2, Company 1 bis alle (Node64 PROXY64, Spalte CDN64); Namen in Modus 3 (ohne
-- Tunnel) bekommen den Hoechstwert der Klasse. Die bisherige Zuteilung je Account wird uebernommen.

-- Zuteilung je Eigentuemer: Tunnel-ID (tn_...) bzw. "u:<userid>" fuer die Namen ohne Tunnel eines Accounts.
CREATE TABLE IF NOT EXISTS `cdn64o_edge_assign` (
  `owner` varchar(32) NOT NULL,
  `edge_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`owner`, `edge_id`),
  KEY `edge_id` (`edge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- gewaehlte Anzahl je Tunnel (0 = Hoechstwert der Klasse)
ALTER TABLE `cdn64o_tunnels` ADD COLUMN IF NOT EXISTS `edges` int(3) NOT NULL DEFAULT 0;

-- bisherige Zuteilung je Account auf seine Tunnel und seine Namen ohne Tunnel uebertragen (gleiche Server, kein Umbau)
INSERT IGNORE INTO `cdn64o_edge_assign` (`owner`, `edge_id`, `created_at`)
  SELECT t.id, ue.edge_id, ue.created_at FROM `cdn64o_tunnels` t JOIN `cdn64o_user_edges` ue ON ue.userid = t.userid;
INSERT IGNORE INTO `cdn64o_edge_assign` (`owner`, `edge_id`, `created_at`)
  SELECT CONCAT('u:', ue.userid), ue.edge_id, ue.created_at FROM `cdn64o_user_edges` ue;
UPDATE `cdn64o_tunnels` t JOIN `cdn64o_accounts` a ON a.userid = t.userid SET t.edges = a.edges WHERE t.edges = 0 AND a.edges > 0;

-- cdn64o_user_edges und cdn64o_accounts (aus cdn64_servers.sql) nutzt das Portal danach nicht mehr; sie bleiben vorerst
-- stehen (Ruecksprung auf den alten Stand moeglich) und koennen spaeter mit DROP TABLE entfernt werden.

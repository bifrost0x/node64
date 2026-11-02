-- CDN64: Server je Account nach Klasse statt alle Server fuer jeden (2026-11-02). Mehrfach ausfuehrbar.
-- Free 1 Server, Supporter 2, Business waehlt 1 bis 2, Company 1 bis alle (auch kuenftige). Zugeteilt wird je Standort
-- zuerst einer, jeweils der mit den wenigsten Accounts; faellt ein Server aus, springt ein anderer ein, bis er zurueck ist.
-- Ohne dieses SQL laeuft das Portal wie bisher (alle Server); danach stellt es die Namen binnen Minuten um.

-- Standort je Server (nbg = Nuernberg, fsn = Falkenstein): ein Account mit 2 Servern bekommt beide Standorte.
ALTER TABLE `cdn64o_edges` ADD COLUMN IF NOT EXISTS `location` varchar(16) NOT NULL DEFAULT '' AFTER `hostname`;
UPDATE `cdn64o_edges` SET `location` = 'nbg' WHERE `name` IN ('srv07', 'srv10');
UPDATE `cdn64o_edges` SET `location` = 'fsn' WHERE `name` IN ('srv08', 'srv09');

-- Zuteilung je Account (fest, bis sich die Anzahl aendert) und gewaehlte Anzahl (Business/Company; 0 = hoechstens).
CREATE TABLE IF NOT EXISTS `cdn64o_user_edges` (
  `userid` int(11) NOT NULL,
  `edge_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`userid`, `edge_id`),
  KEY `edge_id` (`edge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cdn64o_accounts` (
  `userid` int(11) NOT NULL,
  `edges` int(3) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Klasse: cdn_edges = hoechstens so viele Server (0 = alle), cdn_edges_pick = 1: der Account waehlt 1 bis dahin.
-- 1 Standard (Free), 2 VIP, 3 Premium, 4 Business, 5 Company, 6 Supporter. VIP/Premium wie Supporter.
ALTER TABLE `user_class` ADD COLUMN IF NOT EXISTS `cdn_edges` int(3) NOT NULL DEFAULT 1;
ALTER TABLE `user_class` ADD COLUMN IF NOT EXISTS `cdn_edges_pick` tinyint(1) NOT NULL DEFAULT 0;
UPDATE `user_class` SET `cdn_edges` = 1, `cdn_edges_pick` = 0 WHERE `id` = 1;
UPDATE `user_class` SET `cdn_edges` = 2, `cdn_edges_pick` = 0 WHERE `id` IN (2, 3, 6);
UPDATE `user_class` SET `cdn_edges` = 2, `cdn_edges_pick` = 1 WHERE `id` = 4;
UPDATE `user_class` SET `cdn_edges` = 0, `cdn_edges_pick` = 1 WHERE `id` = 5;

-- Verbindungen je Server (unveraendert, hier nur zur Sicherheit): Free/Supporter 1, Business 2, Company 4.
UPDATE `user_class` SET `cdn_tunnel_sessions` = 1 WHERE `id` IN (1, 2, 3, 6);
UPDATE `user_class` SET `cdn_tunnel_sessions` = 2 WHERE `id` = 4;
UPDATE `user_class` SET `cdn_tunnel_sessions` = 4 WHERE `id` = 5;

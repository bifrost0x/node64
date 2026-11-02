-- CDN64: dritter Server srv09.cdn64.de (Falkenstein, cdn64-edge 0.20.4), 2026-11-02.
-- secret_hash = SHA-256 (hex) des api_secret aus /etc/cdn64-edge/config.yaml auf srv09 (das Geheimnis liegt nur dort).
-- Erst mit disabled = 1 (Wartung): der Server meldet sich noch nicht an und steht in keinem DNS-Eintrag.
-- Zum Einschalten danach:  UPDATE cdn64o_edges SET disabled = 0 WHERE name = 'srv09';
-- Dann holt srv09 seine Konfiguration und die Nodes bauen ihren Tunnel auf. withdrawn = 1: das Portal traegt einen
-- Server nur in die A/AAAA der bestehenden CDN64-Namen ein, wenn er als ausgetragen gilt (c64o_edge_watch, nach 30 s
-- wieder da); neue Namen bekommen ihn ohnehin (c64o_dns_on).
INSERT INTO cdn64o_edges (name, hostname, ipv4, ipv6, secret_hash, disabled, withdrawn) VALUES
  ('srv09', 'srv09.cdn64.de', '91.98.90.234', '2a01:4f8:c010:9a3d::1', '8035b74355588c9fda2dd0374ed34471d7d076646e5ace48d28b7e6572f18aba', 1, 1)
  ON DUPLICATE KEY UPDATE hostname = VALUES(hostname), ipv4 = VALUES(ipv4), ipv6 = VALUES(ipv6), secret_hash = VALUES(secret_hash);

-- Nachtrag 2026-11-02 (srv09 war schon eingetragen und eingeschaltet, aber mit withdrawn = 0 und deshalb in keinem
-- bestehenden Namen): als ausgetragen markieren, nach 30 s traegt c64o_edge_watch ihn in alle Namen ein.
UPDATE cdn64o_edges SET withdrawn = 1, back_since = NULL WHERE name = 'srv09' AND withdrawn = 0;

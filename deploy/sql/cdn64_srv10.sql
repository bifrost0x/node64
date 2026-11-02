-- CDN64: vierter Server srv10.cdn64.de (Nuremberg, cdn64-edge 0.20.4), 2026-11-02.
-- secret_hash = SHA-256 (hex) des api_secret aus /etc/cdn64-edge/config.yaml auf srv10 (das Geheimnis liegt nur dort).
-- srv07, srv08 und srv09 kennen srv10 schon als Nachbarn, deshalb gleich eingeschaltet (disabled = 0): srv10 holt
-- seine Konfiguration, die Nodes bauen ihren Tunnel auf. withdrawn = 1: das Portal traegt ihn nach 30 s in die
-- A/AAAA aller bestehenden CDN64-Namen ein (c64o_edge_watch); neue Namen bekommen ihn ohnehin (c64o_dns_on).
INSERT INTO cdn64o_edges (name, hostname, ipv4, ipv6, secret_hash, disabled, withdrawn) VALUES
  ('srv10', 'srv10.cdn64.de', '116.203.146.186', '2a01:4f8:1c19:6003::1', '855a3ad77198505dc9defdd926ea9fe1f8a0e61261ef5156858fc288dc1dd9ea', 0, 1)
  ON DUPLICATE KEY UPDATE hostname = VALUES(hostname), ipv4 = VALUES(ipv4), ipv6 = VALUES(ipv6), secret_hash = VALUES(secret_hash);

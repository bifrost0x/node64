-- CDN64: dritter Server srv09.cdn64.de (Falkenstein, cdn64-edge 0.20.4), 2026-11-02.
-- secret_hash = SHA-256 (hex) des api_secret aus /etc/cdn64-edge/config.yaml auf srv09 (das Geheimnis liegt nur dort).
-- Erst mit disabled = 1 (Wartung): der Server meldet sich noch nicht an und steht in keinem DNS-Eintrag.
-- Zum Einschalten danach:  UPDATE cdn64o_edges SET disabled = 0 WHERE name = 'srv09';
-- Dann holt srv09 seine Konfiguration, die Nodes bauen ihren Tunnel auf und nach 30 s steht er in den A/AAAA
-- aller CDN64-Namen (wie srv07/srv08).
INSERT INTO cdn64o_edges (name, hostname, ipv4, ipv6, secret_hash, disabled) VALUES
  ('srv09', 'srv09.cdn64.de', '91.98.90.234', '2a01:4f8:c010:9a3d::1', '8035b74355588c9fda2dd0374ed34471d7d076646e5ace48d28b7e6572f18aba', 1)
  ON DUPLICATE KEY UPDATE hostname = VALUES(hostname), ipv4 = VALUES(ipv4), ipv6 = VALUES(ipv6), secret_hash = VALUES(secret_hash);

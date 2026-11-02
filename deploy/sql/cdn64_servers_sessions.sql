-- CDN64: Verbindungen je Server je Tunnel waehlbar (2026-11-02, nach cdn64_servers_tunnel.sql). Mehrfach ausfuehrbar.
-- VOR dem Hochladen von api_v2_cdn64o.php einspielen. Business waehlt 1-2, Company 1-4 Verbindungen je Server
-- (user_class.cdn_tunnel_sessions bleibt die Grenze); 0 = die Grenze der Klasse wie bisher.
ALTER TABLE `cdn64o_tunnels` ADD COLUMN IF NOT EXISTS `sessions` int(3) NOT NULL DEFAULT 0;

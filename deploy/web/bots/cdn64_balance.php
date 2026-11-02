<?php
/**
 * CDN64: Lastausgleich der Server als Notbremse. Alle 15 Minuten ueber honeypot_cron.php (Job "cdn64bal"), nur
 * Kommandozeile:
 *
 *   php cdn64_balance.php          entscheiden und den Umzug anstossen
 *   php cdn64_balance.php --dry    nur anzeigen, nichts aendern
 *
 * Kein Gleichverteilen: Ein Server gilt als ueberlastet, wenn er die letzten 15 Minuten durchgehend ueber 70 % seiner
 * Kapazitaet lag (cdn64o_edges.capacity_mbit, Messung je Minute in cdn64o_edge_load) UND ein anderer Server im Mittel
 * unter 40 % liegt (gleicher Standort zuerst). Liegen alle hoch, passiert nichts. Hoechstens EIN Tunnel je Lauf: einer
 * mit spuerbarem Verkehr auf dem Server, der das Ziel nicht selbst ueber 70 % bringt.
 *
 * Gegen Hin und Her: ein umgezogener Tunnel bleibt 24 Stunden fest, Quell- und Ziel-Server sind 1 Stunde gesperrt.
 * Nie: waehrend eines Failovers (irgendein Server ausgefallen) und fuer Tunnel ohne feste Zuteilung (Company "alle").
 *
 * Der Job entscheidet nur (Zeile in cdn64o_balance, state moving). Den Umzug fuehrt das Portal bei der naechsten Abfrage
 * eines Servers aus (api_v2_cdn64o.php, c64o_balance_step): neuer Server dazu, der alte faellt erst weg, wenn der neue
 * den Tunnel verbunden meldet. SQL: deploy/sql/cdn64_balance.sql.
 */
if(php_sapi_name() !== 'cli') { exit; }
include(__DIR__ . "/../dbconnect.php");

const C64B_HIGH      = 0.70; // ueberlastet ab (Anteil der Kapazitaet), durchgehend
const C64B_LOW       = 0.40; // Ziel hoechstens (Mittel)
const C64B_WINDOW    = 15;   // Minuten Messung
const C64B_SAMPLES   = 12;   // mindestens so viele Messungen im Fenster (Server meldet jede Minute)
const C64B_PIN_H     = 24;   // Stunden fest nach einem Umzug (Tunnel)
const C64B_LOCK_H    = 1;    // Stunden gesperrt nach einem Umzug (Quell- und Ziel-Server)
const C64B_MIN_SHARE = 0.02; // spuerbarer Verkehr: mindestens 2 % der Kapazitaet des Quell-Servers (und 1 Mbit/s)

$dry = in_array('--dry', $argv, true);
$q = function(string $sql, array $a = array()) use ($pdo): PDOStatement { $st = $pdo->prepare($sql); $st->execute($a); return $st; };
$say = function(string $s) { echo date('H:i:s') . " $s\n"; };

try { $q("SELECT 1 FROM cdn64o_balance LIMIT 1"); } catch(Throwable $e) { $say("cdn64_balance.sql noch nicht eingespielt"); exit(0); }
$l = $pdo->query("SELECT GET_LOCK('cdn64_balance', 0) l")->fetch(PDO::FETCH_ASSOC);
if((int)($l['l'] ?? 0) !== 1) { $say("laeuft bereits"); exit(0); }

// Aufraeumen (nicht im Trockenlauf): Umzuege geloeschter Tunnel abbrechen (sonst blockiert die Zeile den Lastausgleich),
// Zuteilungen und Verbindungsstand geloeschter Tunnel entfernen, alte Umzuege nach 7 Tagen loeschen (24 h fest, 1 h Sperre)
if(!$dry) {
	$q("UPDATE cdn64o_balance b LEFT JOIN cdn64o_tunnels t ON t.id = b.tunnel_id set b.state = 'aborted', b.done_at = NOW() WHERE b.state = 'moving' AND t.id IS NULL");
	$q("DELETE a FROM cdn64o_edge_assign a LEFT JOIN cdn64o_tunnels t ON t.id = a.owner WHERE a.owner NOT LIKE 'u:%' AND t.id IS NULL");
	$q("DELETE s FROM cdn64o_tunnel_seen s LEFT JOIN cdn64o_tunnels t ON t.id = s.tunnel_id WHERE t.id IS NULL OR s.seen_at < NOW() - INTERVAL 1 DAY");
	$q("DELETE FROM cdn64o_balance WHERE state != 'moving' AND started_at < NOW() - INTERVAL 7 DAY");
}

$edges = $q("SELECT * FROM cdn64o_edges WHERE disabled = 0")->fetchAll(PDO::FETCH_ASSOC);
foreach($edges as $e) { if((int)$e['withdrawn'] === 1) { $say("Failover aktiv (" . $e['name'] . "): kein Lastausgleich"); exit(0); } }
if($q("SELECT id FROM cdn64o_balance WHERE state = 'moving' LIMIT 1")->fetch()) { $say("Umzug laeuft noch: kein weiterer"); exit(0); }

// Last je Server im Fenster
$since = date('Y-m-d H:i:s', time() - C64B_WINDOW * 60);
$load = array(); // edge_id => array(min, avg, n, tunnels => [id => Mittel])
foreach($q("SELECT * FROM cdn64o_edge_load WHERE at >= :s ORDER BY at", array('s' => $since))->fetchAll(PDO::FETCH_ASSOC) as $r) {
	$x = &$load[(int)$r['edge_id']];
	$x['n'] = ($x['n'] ?? 0) + 1;
	$x['sum'] = ($x['sum'] ?? 0) + (float)$r['mbit'];
	$x['min'] = min($x['min'] ?? INF, (float)$r['mbit']);
	foreach((array)json_decode((string)$r['tunnels'], true) as $tid => $m) { $x['tun'][$tid] = ($x['tun'][$tid] ?? 0) + (float)$m; }
	unset($x);
}
$cap = fn($e) => max(0, (int)($e['capacity_mbit'] ?? 0));
$avg = fn($id) => isset($load[$id]) ? $load[$id]['sum'] / $load[$id]['n'] : 0.0;
$locked = function(int $id) use ($q): bool {
	return (bool)$q("SELECT id FROM cdn64o_balance WHERE (from_edge = :a OR to_edge = :b) AND started_at >= NOW() - INTERVAL " . C64B_LOCK_H . " HOUR LIMIT 1",
		array('a' => $id, 'b' => $id))->fetch();
};
$byId = array_column($edges, null, 'id');

// ueberlastete Server, der hoechste zuerst
$hot = array();
foreach($edges as $e) {
	$id = (int)$e['id'];
	if($cap($e) <= 0 OR !isset($load[$id]) OR $load[$id]['n'] < C64B_SAMPLES) { continue; }
	if($load[$id]['min'] > C64B_HIGH * $cap($e)) { $hot[$id] = $load[$id]['min'] / $cap($e); }
}
arsort($hot);
if(!$hot) { $say("kein Server ueberlastet"); exit(0); }

foreach(array_keys($hot) as $hid) {
	$h = $byId[$hid];
	if($locked($hid)) { $say($h['name'] . " ueberlastet, aber gesperrt (Umzug in der letzten Stunde)"); continue; }
	// Ziele: unter 40 %, nicht gesperrt; gleicher Standort zuerst, dann die geringste Last
	$cands = array();
	foreach($edges as $e) {
		$id = (int)$e['id'];
		if($id === $hid OR $cap($e) <= 0 OR $avg($id) >= C64B_LOW * $cap($e) OR $locked($id)) { continue; }
		$cands[] = $e;
	}
	usort($cands, fn($a, $b) => array((int)(($a['location'] ?? '') !== ($h['location'] ?? '')), $avg((int)$a['id']) / $cap($a))
		<=> array((int)(($b['location'] ?? '') !== ($h['location'] ?? '')), $avg((int)$b['id']) / $cap($b)));
	if(!$cands) { $say($h['name'] . " ueberlastet, aber kein Server unter " . (C64B_LOW * 100) . " %: nichts verschoben"); continue; }
	// Tunnel auf dem Server: feste Zuteilung (Company "alle" hat keine), nicht in den letzten 24 h umgezogen
	$tun = array();
	foreach($q("SELECT a.owner FROM cdn64o_edge_assign a JOIN cdn64o_tunnels t ON t.id = a.owner WHERE a.edge_id = :e AND t.status = 'active'", array('e' => $hid))->fetchAll(PDO::FETCH_COLUMN) as $tid) {
		if($q("SELECT id FROM cdn64o_balance WHERE tunnel_id = :t AND started_at >= NOW() - INTERVAL " . C64B_PIN_H . " HOUR LIMIT 1", array('t' => $tid))->fetch()) { continue; }
		$tun[$tid] = ($load[$hid]['tun'][$tid] ?? 0) / $load[$hid]['n']; // Mittel im Fenster
	}
	arsort($tun);
	foreach($cands as $c) {
		$cid = (int)$c['id'];
		foreach($tun as $tid => $m) {
			if($m < max(1.0, C64B_MIN_SHARE * $cap($h))) { break; } // nur noch kleine: lohnt nicht
			if($avg($cid) + $m > C64B_HIGH * $cap($c)) { continue; } // braechte das Ziel selbst ueber die Grenze
			if($q("SELECT 1 FROM cdn64o_edge_assign WHERE owner = :t AND edge_id = :e", array('t' => $tid, 'e' => $cid))->fetch()) { continue; } // hat ihn schon
			$why = sprintf("%s %.0f %% (15 min > %d %%), %s %.0f %%, Tunnel %.1f Mbit/s", $h['name'], 100 * $avg($hid) / $cap($h), C64B_HIGH * 100,
				$c['name'], 100 * $avg($cid) / $cap($c), $m);
			$say(($dry ? "[dry] " : "") . "Lastausgleich $tid: " . $h['name'] . " -> " . $c['name'] . " ($why)");
			if(!$dry) {
				$q("INSERT INTO cdn64o_balance (tunnel_id, from_edge, to_edge, state, reason) VALUES (:t, :f, :to, 'moving', :r)",
					array('t' => $tid, 'f' => $hid, 'to' => $cid, 'r' => mb_substr($why, 0, 255)));
			}
			exit(0); // hoechstens einer je Lauf
		}
	}
	$say($h['name'] . " ueberlastet, aber kein passender Tunnel (zu klein, fest oder wuerde das Ziel ueberlasten)");
}

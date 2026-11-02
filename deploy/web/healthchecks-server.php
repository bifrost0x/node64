<?php
include("header.php");
// Healthchecks-Server (hubs, Tabelle hc2_hubs in der Datenbank der Healthchecks): Online = Heartbeat in den letzten
// 60 Sekunden (die hubs schreiben ihn in UTC). Adressen aus dem DNS des Namens (name.ipv64.net).
$hcsLoc = array('fsn' => 'Falkenstein', 'nbg' => 'Nuremberg');
$hcsH = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
try {
	$hcsRows = hc2_pdo()->query("SELECT name, location, version, heartbeat, heartbeat >= UTC_TIMESTAMP(3) - INTERVAL 60 SECOND AS fresh FROM hc2_hubs ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log('healthchecks-server: ' . $e->getMessage()); $hcsRows = null; }
/** A- und AAAA-Records des Servers (leer, wenn der Name nicht aufloest). */
function hcs_addrs(string $host): array {
	$v4 = array(); $v6 = array();
	foreach ((@dns_get_record($host, DNS_A) ?: array()) as $r) { if (!empty($r['ip'])) { $v4[] = $r['ip']; } }
	foreach ((@dns_get_record($host, DNS_AAAA) ?: array()) as $r) { if (!empty($r['ipv6'])) { $v6[] = $r['ipv6']; } }
	return array($v4, $v6);
}
?>
		 <div class="jumbotron jumbotron-fluid d-none d-xl-block pt-3 pb-3 mt-0">
			<div class="container">
			  <h1 class="display-3"><img src="https://ipv64.net/img/logo_64.png" height="80" alt="IPv64 Logo" class="mb-3"> IPv64.net Healthcheck Servers</h1>
			  <p class="lead">The online health checks run from these servers. They are monitored by IPv64 and their
			  status can be viewed by anyone here.</p>
			</div>
		  </div>
		<div class="container mt-2">
		<div class="row">
			<div class="col-12 col-xl-7">
				<div class="card">
				  <div class="card-header">
					<i class="fa-solid fa-heart-pulse float-right"></i>
					List of IPv64.net Healthcheck Servers
				  </div>
				  <div class="card-body p-0">
					<?php if ($hcsRows === null) { ?>
						<p class="text-muted small p-3 mb-0">The server list is currently not available.</p>
					<?php } else { ?>
						<table class="table table-striped mb-0">
						<?php foreach ($hcsRows as $s) {
							$host = $s['name'] . '.ipv64.net';
							[$v4, $v6] = hcs_addrs($host);
							$status = (int)$s['fresh'] === 1 ? '<span class="badge badge-success">Online</span> <i class="fa-solid fa-check-double text-success"></i>'
								: '<span class="badge badge-warning">Offline</span> <i class="fa-solid fa-xmark text-danger"></i>';
							$loc = $hcsLoc[$s['location']] ?? $s['location'];
							echo '<tr>
							<td class="p-2"><img src="img/country/de.svg" style="border:1px solid;height:18px;" alt="German flag"> <span class="badge badge-dark">' . $hcsH($loc) . '</span>
							<br><a href="https://hetzner.cloud/?ref=B7SA7EkDfqfL" target="_blank" rel="noopener"><img src="img/hetzner_logo.png" height="11" alt="Hetzner"></a>
							<td class="p-2">' . $hcsH($host) . ($s['version'] !== '' ? '<br><small class="text-muted">' . $hcsH($s['version']) . '</small>' : '') . '
							<td class="p-2">' . $status . '
							<td class="p-2">' . $hcsH(implode(', ', $v4)) . '<br>' . $hcsH(implode(', ', $v6)) . '</tr>';
						} ?>
						</table>
					<?php } ?>
				  </div>
				</div>
			</div>
	  <div class="col-12 col-xl-5">
		<div class="card">
		  <div class="card-header">
			<i class="fa-solid fa-laptop-code float-right"></i>
			Some more IPv64 Healthcheck Server Information
		  </div>
		  <div class="card-body">
			<p>Each location runs your online checks (HTTP, ping, ports, certificates, DNS and more) from its own
			server. You choose the locations per check in the portal.</p>
			<p>If you restrict access to your services, add these addresses to your firewall whitelist so the health
			checks can reach them.</p>
		  </div>
		</div>
	  </div>
	</div>
</div>
<p></p>
<?php
include("footer.php");
?>

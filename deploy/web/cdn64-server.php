<?php
include("header.php");
// CDN64-Server (cdn64o_edges): Online = Lebenszeichen in den letzten 60 Sekunden (die Server melden sich alle 10 s,
// api_v2_cdn64o.php), disabled = Wartung. Standort nach Namen (die Tabelle hat keinen).
$c64sLoc = array('srv07' => 'Nuremberg', 'srv08' => 'Falkenstein', 'srv09' => 'Falkenstein', 'srv10' => 'Nuremberg');
$c64sH = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
try {
	$c64sRows = $pdo->query("SELECT name, hostname, ipv4, ipv6, disabled, version, last_seen, last_seen >= NOW() - INTERVAL 60 SECOND AS fresh FROM cdn64o_edges ORDER BY name")->fetchAll();
} catch (Throwable $e) { error_log('cdn64-server: ' . $e->getMessage()); $c64sRows = null; }
?>
		 <div class="jumbotron jumbotron-fluid d-none d-xl-block pt-3 pb-3 mt-0">
			<div class="container">
			  <h1 class="display-3"><img src="https://ipv64.net/img/logo_64.png" height="80" alt="IPv64 Logo" class="mb-3"> IPv64.net CDN64 Servers</h1>
			  <p class="lead">CDN64 forwards the visitors of your names through these servers to your Node64 at home.
			  They are monitored by IPv64 and their status can be viewed by anyone here.</p>
			</div>
		  </div>
		<div class="container mt-2">
		<div class="row">
			<div class="col-12 col-xl-7">
				<div class="card">
				  <div class="card-header">
					<i class="fa-solid fa-server float-right"></i>
					List of IPv64.net CDN64 Servers
				  </div>
				  <div class="card-body p-0">
					<?php if ($c64sRows === null) { ?>
						<p class="text-muted small p-3 mb-0">The server list is currently not available.</p>
					<?php } else { ?>
						<table class="table table-striped mb-0">
						<?php foreach ($c64sRows as $s) {
							if ((int)$s['disabled'] === 1) { $status = '<span class="badge badge-dark">Maintenance mode</span> <i class="fa-solid fa-hammer text-info"></i>'; }
							elseif ((int)$s['fresh'] === 1) { $status = '<span class="badge badge-success">Online</span> <i class="fa-solid fa-check-double text-success"></i>'; }
							else { $status = '<span class="badge badge-warning">Offline</span> <i class="fa-solid fa-xmark text-danger"></i>'; }
							$loc = $c64sLoc[$s['name']] ?? '';
							echo '<tr>
							<td class="p-2"><img src="img/country/de.svg" style="border:1px solid;height:18px;" alt="German flag">' . ($loc !== '' ? ' <span class="badge badge-dark">' . $c64sH($loc) . '</span>' : '') . '
							<br><a href="https://hetzner.cloud/?ref=B7SA7EkDfqfL" target="_blank" rel="noopener"><img src="img/hetzner_logo.png" height="11" alt="Hetzner"></a>
							<td class="p-2">' . $c64sH($s['hostname']) . ($s['version'] !== '' ? '<br><small class="text-muted">v' . $c64sH($s['version']) . '</small>' : '') . '
							<td class="p-2">' . $status . '
							<td class="p-2">' . $c64sH($s['ipv4']) . '<br>' . $c64sH($s['ipv6']) . '</tr>';
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
			Some more IPv64 CDN64 Server Information
		  </div>
		  <div class="card-body">
			<p>Your Node64 keeps a tunnel to these servers (outgoing, port 7844). Visitors of your CDN64 names reach the
			servers, and the servers pass them through the tunnel to your services – no open port at home is needed.</p>
			<p>If a server is offline, its addresses are taken out of the DNS of your names automatically and the other
			servers take over.</p>
			<p>To be on the safe side, you can add these addresses to your firewall whitelist if you restrict access to
			your services.</p>
		  </div>
		</div>
	  </div>
	</div>
</div>
<p></p>
<?php
include("footer.php");
?>

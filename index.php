<?php


ini_set('max_execution_time', -1);
ini_set('memory_limit', '512M');


$watch_payloads = '/(\/login|\/console|\/manager\/html|\/wp-login\.php|\/admin\/account|\/jenkins\/login|\/common\/info\.cgi|\/api\/jsonws\/invoke|\/mifs\/.*?services\/|\/solr\/admin\/info\/system|\/cgi-bin\/mainfunction\.cgi|\/server-status\?auto|\/dev\/cmdb\/sslvpn_websession\/_ignition\/execute-solution|\/wp-includes\/wlwmanifest\.xml|\/Autodiscover\/Autodiscover\.xml|\?XDEBUG_SESSION_START=phpstorm|\/wp-content\/plugins\/wp-file-manager\/readme\.txt|\/vendor\/phpunit\/phpunit\/src\/Util\/PHP\/eval-stdin\.php|\/Index\\\think\\app\/invokefunction&function=call_user_func_array&vars\[0]=md5&vars\[1]\[]=HelloThinkPHP21|\/Index\/\\think\\app\/invokefunction&function=call_user_func_array&vars\[0]=md5&vars\[1]\[]=__HelloThinkPHP)/i';

$watch_keywords = '/(">|\/\.\.\/|\/\.;\/|\/\/\/\/|\.git|cmdb|sra_|owa\/|<php>|<\/php>|onload|onfocus|onstart|onclick|onerror|websvn|cgi-bin|phpunit|\${jndi|\$%7Bjndi:|\/admin\/|\/administrator\/|\/cpanel|\/iptables\/|\/control\/|\/admin[0-9]\.|\/ccms\/|\/adm\/|<\/title>|info\.cgi|<script>|<\/script>|die\(.*?\)|md5\(.*?\)|scriptlet|accesskey|autofocus|_ignition|HelloThink|alert\(.*?\)|prompt\(.*?\)|javascript:|onmouseover|confirm\(.*?\)|Autodiscover|eval-stdin\.php|invokefunction|wlwmanifest\.xml|wp-file-manager|mainfunction\.cgi|execute-solution|sslvpn_websession|currentsetting\.htm|call_user_func_array|XDEBUG_SESSION_START)/i';


/* Ajouter chaque ip de chaque ligne dans un array */
$ip_list = [];
if ($f = fopen('access.log', 'r')) {

	while(!feof($f)) {
		$line = fgets($f);
		if (preg_match('/((?:[0-9]{1,3}.){4}) -/', $line, $ip)) {
			$ip_list[] = $ip[1];
		}

		//if (preg_match('/((?:[0-9]{1,3}.){4}) - -.* "GET (.*) HTTP\/.*" [0-9]{3} [0-9]+ ".*"/i', $line, $ip)) {
		//	$ip_list_diag[] = $ip[1];
		//}

	}
	fclose($f);
} else {
	echo 'Unable to open logs!';
	exit;
}


/* Compter nombre de fois que chaque ip apparait */
$ip_list      = array_count_values($ip_list);
//$ip_list_diag = array_count_values($ip_list_diag);
arsort($ip_list);
//arsort($ip_list_diag);

/* Ici, ne récpérer que les X premiers */
//$ip_list = array_slice($ip_list, 0, 5);
#echo '<pre>';
#print_r($ip_list);

/* Préparer l'array à envoyer à l'api, quand on n'a pas d'info sur l'ip */
$ip_db = json_decode(file_get_contents('ip_db.json'), True);

$ip_list_api = [];
foreach ($ip_list as $ip => $count) {

	/* Si l'ip n'est pas dans la db, alors chercher les infos */
	if (!isset($ip_db[$ip])) {
		$ip_list_api[] = $ip;
	}

	/* ici, si y'a 100 ip dans ip_list_api, alors appeler ip2infos, et vider ip_list_api */
	if (count($ip_list_api) == 100) {
		$ip_db = ip2infos($ip_list_api, $ip_db);
		$ip_list_api = [];
		sleep(2); /* Pour faire ~20 requêtes/sec (max 45/sec) */
	}
}

/* ici, si on sort avec + de 0 ip, on appelle ip2infos */
if (count($ip_list_api) > 0) {
	$ip_db = ip2infos($ip_list_api, $ip_db);
	$ip_list_api = [];
}


function ip2infos($ip_array, $ip_db) {

	/* Faire la requête (100 ip/batch max, et 45 requêtes http max/minute/ip) */
	$options = [
		'http' => [
			'ignore_errors' => True,
			'method'        => 'POST',
			'header'        => 'Content-Type: application/json',
			'content'       => json_encode($ip_array)
		]
	];
	$ip_api = file_get_contents('http://ip-api.com/batch?lang=fr', false, stream_context_create($options));

	if (!str_contains($ip_api, 'Too Many Requests')) {
		/* Retour de l'api */
		$ip_api = json_decode($ip_api, true);

		/* Ajouter le retour de l'api, dans l'array du json */
		foreach ($ip_api as $key => $value) {
			if ($value['status'] == 'success') {
				$ip_db[$value['query']] = $value;
			}
		}

		/* Sauvegarder le json */
		file_put_contents('ip_db.json', json_encode($ip_db, JSON_PRETTY_PRINT));
	}

	/* Retourner l'array pour après */
	return($ip_db);
}

?>


<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
	<meta name="theme-color" content="#5F46F0">
	<title>AccessMap (IPs: <?=count($ip_db)?>)</title>

	<!-- CDN Leaflet CSS AVANT JS -->
	<link href="https://fonts.googleapis.com/css?family=Montserrat&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css?family=Roboto&display=swap" rel="stylesheet">

	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.3.1/dist/leaflet.css" />
	
	<script src="https://unpkg.com/leaflet@1.3.1/dist/leaflet.js"></script>
	<script src="LeafletTileLayerColorFilter.js"></script>
	<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>

	<style type="text/css">
		html, body {
			margin: 0px;
			font-family: 'Montserrat', sans-serif;
		}

		.leaflet-bar a, .leaflet-bar a:hover {
			background: #734cea !important;
			color: #fff !important;
		}

		.leaflet-control-attribution{
			visibility: hidden !important;
		}

		.leaflet-zoom-anim .leaflet-zoom-animated {
			transition: transform 0.2s cubic-bezier(0, 0, 0.25, 1) !important;
		}

		#map {
			background: #090909;
		}

		#distance {
			position: fixed;
			top: 10px;
			z-index: 1000;
			text-align: center;
			margin: 0 calc(50% - 157px);
			width: 300px;
			color: #ffffff;
			background: linear-gradient(to right,#06C,#639),#06C;
			border-color: #2b1738;
			border-width: 1px;
			border-style: solid;
			font-size: 20px;
			box-shadow: 0 13px 90px -10px #212121;
			padding: 12px;
   			border-radius: 12px;
		}

		#etat {
			position: fixed;
			font-family: monospace;
			bottom: 10px;
			right : 10px;
			z-index: 1000;
			text-align: center;
			margin: 0 10px;
			color: #ffffff;
			font-size: 14px;
		}

		#speed {
			position: fixed;
			bottom: 10px;
			z-index: 1000;
			text-align: center;
			margin: 0 10px;
			width: 80px;
			height: 80px;
			color: #ffffff;
			background: linear-gradient(to right,#06C,#639),#06C;
			border-color: #2b1738;
			border-width: 1px;
			border-style: solid;
			font-size: 20px;
			box-shadow: 0 13px 90px -10px #212121;
			padding: 12px;
			border-radius: 100px;
		}

		#speed:before {
			position: absolute;
			content: "";
			top: -3px;
			bottom: -3px;
			left: -3px;
			right: -3px;
			border-radius: 50%;
			box-shadow: 0 0 rgba(145, 0, 250, 0.1), 0 0 0 16px rgba(145, 0, 250, 0.1),
				0 0 0 32px rgba(145, 0, 250, 0.1), 0 0 0 48px rgba(145, 0, 250, 0.1);
			z-index: -1;
			animation: ripples 1s linear infinite;
			animation-play-state: running;
			transition: 0.5s;
			transform: scale(1);
		}

		@keyframes ripples {
			to {
				box-shadow: 0 0 0 16px rgba(145, 0, 250, 0.1), 0 0 0 32px rgba(145, 0, 250, 0.1),
					0 0 0 48px rgba(145, 0, 250, 0.1), 0 0 0 64px rgba(145, 0, 250, 0);
			}
		}

		#effect {
			visibility: hidden;
			top: 0px;
			position: absolute;
			color: white;
			z-index: 500;
			width: 100%;
			overflow: hidden;
			height: 100vh;
			pointer-events: none;
			margin: 0;
			box-shadow: inset 0 0 200px -100px white;
		}

	</style>

	<script type="text/javascript">
		/* Centre de la carte, dernière position */

		points = {
			<?php 

				//$data = file_get_contents('/var/www/access.log');

				/* Préparer l'array à passer à la carte pour la liste de points avec les infos */
				$i = 0;
				//$ip_db = array_slice($ip_db, 0, 3530);
				foreach ($ip_db as $ip => $value) {
					
					if ($value['status'] == 'success') {
						$i++;

						$type = 0; /* Ip legit */
						if (preg_match('/(google|twitter|facebook|alibaba|amazon|microsoft|apple|limestone networks|ovh sas)/i', str_replace('"', '', $value['isp']),  $temp)) {
							$type = 2; /* Ip provenant d'hébergeurs/trucs connus (limestone : UptimeRobot) */
						}
						
						echo $i.': {"ip": "'.$value['query'].'", "type": "'.$type.'", "count": "'.$ip_list[$ip].'", "lat": "'.$value['lat'].'", "lon": "'.$value['lon'].'", "country": "'.$value['country'].'", "city": "'.$value['city'].'", "isp": "'.str_replace('"', '', $value['isp']).'", },';
					}
				}
			?>
		};

		var lat = 0;
		var lon = 0;
		var carte = null;


		function initMap() {
			/* Créer l'objet "carte" et l'insèrer dans l'élément HTML qui a l'ID "map" 
			
			https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png
			https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png

			https://cartodb-basemaps-{s}.global.ssl.fastly.net/dark_all/{z}/{x}/{y}.png
			http://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png
			
			*/
			carte = L.map('map').setView([lat, lon], 3);

			let filter = [
				'brightness:200%',
			];

			/* Serveur tiles */
			L.tileLayer.colorFilter('https://api.maptiler.com/maps/darkmatter/256/{z}/{x}/{y}.png?key=adm2RZGNsEGzdDbyPZxr', {
				/* Label en bas à droite */
				filter: filter,
				attribution: '',
				minZoom: 1,
				maxZoom: 19
			}).addTo(carte);
			
			var render = L.canvas({padding:0.5});

			/*Les autres points */
			for (i in points) {

				switch (points[i].type) {
					case '0':
						L.circleMarker(
							[points[i].lat, points[i].lon],
							{renderer: render, radius: 1, color: '#734cea'}
						).addTo(carte).bindPopup('IP: '+points[i].ip+' (legit: '+points[i].count+')<br>Pays: '+points[i].country+'<br>Ville: '+points[i].city+'<br>Opérateur: '+points[i].isp);
					break;
					case '1':
						L.circleMarker(
							[points[i].lat, points[i].lon],
							{renderer: render, radius: (points[i].count/2000 < 50) ? (points[i].count/800)+3 : 5, color: '#ff0000'}
						).addTo(carte).bindPopup('IP: '+points[i].ip+' (attack: '+points[i].count+')<br>Pays: '+points[i].country+'<br>Ville: '+points[i].city+'<br>Opérateur: '+points[i].isp);
					break;
					case '2':
						L.circleMarker(
							[points[i].lat, points[i].lon],
							{renderer: render, radius: (points[i].count/2000 < 10) ? (points[i].count/1000)+2 : 10, color: '#00c4ff'}
						).addTo(carte).bindPopup('IP: '+points[i].ip+' (cloud: '+points[i].count+')<br>Pays: '+points[i].country+'<br>Ville: '+points[i].city+'<br>Opérateur: '+points[i].isp);
					break;
					case '3':
						L.circleMarker(
							[points[i].lat, points[i].lon],
							{renderer: render, radius: (points[i].count/2000 < 50) ? points[i].count/800 : 5, color: '#ff6f00'}
						).addTo(carte).bindPopup('IP: '+points[i].ip+' (just dl: '+points[i].count+')<br>Pays: '+points[i].country+'<br>Ville: '+points[i].city+'<br>Opérateur: '+points[i].isp);
					break;
				}
			
			}

			for (i in points) {
				if (points[i].type == '4') {
					L.circleMarker(
						[points[i].lat, points[i].lon],
						{renderer: render, radius: (points[i].count/1000 < 30) ? points[i].count/1000 : 40, color: '#00ff00'}
					).addTo(carte).bindPopup('IP: '+points[i].ip+' (user: '+points[i].count+')<br>Pays: '+points[i].country+'<br>Ville: '+points[i].city+'<br>Opérateur: '+points[i].isp);
				}
			}
			

			<?php

				/* Si l'ip du visiteur est connue, alors l'afficher */
				if (isset($ip_db[$_SERVER['REMOTE_ADDR']])) {
					echo 'console.log("gps ok");'.PHP_EOL;
					echo "window.blue = L.icon({iconUrl: 'blue.png?v1',iconSize: [20, 20],iconAnchor: [10, 10],popupAnchor: [0, -12]});".PHP_EOL;
					echo "L.marker([".$ip_db[$_SERVER['REMOTE_ADDR']]['lat'].", ".$ip_db[$_SERVER['REMOTE_ADDR']]['lon']."], {icon: window.blue}).addTo(carte).bindPopup('IP: ".$_SERVER['REMOTE_ADDR']." (vous)<br>Pays: ".$ip_db[$_SERVER['REMOTE_ADDR']]['country']."<br>Ville: ".$ip_db[$_SERVER['REMOTE_ADDR']]['city']."<br>Opérateur: ".$ip_db[$_SERVER['REMOTE_ADDR']]['isp']."');".PHP_EOL;

				} else {
					echo 'console.log("gps nope");';
				}
			?>

		}

		window.onload = function () {
			initMap();
		};

	</script>
</head>

<body>
	<div id="map">
</body>

</html>

<script>
	resize();
	window.onresize = resize;
	function resize() {
		document.getElementById("map").style.height = (window.innerHeight) + 'px';
	}
</script>
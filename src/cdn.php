<?php
//$query = "jq";
$query = "cloudflare angular";
//$query = "jsdelivr angular";
//$query = "google jq";
//$query = "msn jq";
// ****************
//error_reporting(0);

function console($str) {
	if (true) {
		var_dump($str);
	}
}
//console("DEBUG MODE");
$debug = false;

ini_set('memory_limit', '-1');
error_reporting(0);

require_once('workflows.php');

$w = new Workflows();

if (!isset($query)) { $query = "{query}"; }
$query = strtolower(trim($query));

$cdns = json_decode(file_get_contents("cdns.json"));
$output = array();

// all
if (strpos($query, " ") !== false) {
	$parts = explode(" ", $query);
	$cdn_q = array_shift($parts);
	$string = implode($parts);
	foreach($cdns as $cdn => $params) {
		$count = count( $w->results() );

		$pos = strpos($cdn, $cdn_q);
		if ($pos !== false && $pos == 0) {
			run($params, $string);
			if ($count == count($w->results())) {
				$w->result( "cdn-$cdn", $query, 'No libraries found.', $query, "icon-cache/$cdn.png", 'no' );
			}
		}

	}

}

//
if ( count( $w->results() ) == 0 ) {
	foreach($cdns as $cdn => $params) {
		$count = count( $w->results() );
		run($params, $query);
		if ($count == count($w->results())) {
			$w->result( "cdn-$cdn", $query, "{$params->name}", 'No libraries found.', "icon-cache/$cdn.png", 'no' );
		}
	}
}

function load($params) {
	global $w;
	// get db
	//console("READ {$params->id}CDN.json");
	$pkgs = $w->read("{$params->id}CDN.json");
	$timestamp = $w->filetime("{$params->id}CDN.json");
	if ( debug || !$pkgs || $timestamp < (time() - 7 * 86400) ) {
		//console("SEARCH {$params->id}CDN.json");
		$id = $params->id;
		$data = $id( ($params->db_url) ? $params->db_url : $params->site);
		$w->write($data, "{$params->id}CDN.json");
		//console($data);
		$pkgs = json_decode( $data );
		//console("WRITE {$params->id}CDN.json");
	}/* else if (!$pkgs) {
		// add in db gen scripts

		$data = $id($params->site);


		$data = '{"packages":[]}';
		$pkgs = json_decode( $data );
	}*/
	//console($pkgs);
	$pkgs = $pkgs->packages;
	return $pkgs;
}

function search($plugin, $query) {
	//console("SEARCH");
	//console($plugin);
	if (isset($plugin->name)) {
		$name = strtolower(trim($plugin->name));
		if (strpos($name, $query) === 0) {
			return 1;
		} else if (strpos($name, $query) > 0) {
			return 2;
		} else if(strpos($name, $query) !== false) {
			return 3;
		} else if (isset($plugin->description) && strpos(strtolower(trim($plugin->description)), $query) !== false) {
			return 4;
		} else if (isset($plugin->keywords)) {
			foreach($plugin->keywords as $keyword) {
				if (strpos(strtolower(trim($keyword)), $query) !== false) {
					return 5;
				}
			}
		}
	}
	return 0;
}

function run($params, $query) {
	global $w;

	$pkgs = load($params);
	$output = array();
	for($i = 0, $l = sizeof($pkgs); $i < $l; $i++) {
		$pkg = $pkgs[$i];
		$priority = search($pkg, $query);
		if ($priority) {
			//console("FOUND {$priority}");
			$title = $pkg->name." (".$pkg->version.")"; // remove grunt- from title

			$url = $params->url;
			$url = str_replace("{name}", $pkg->name, $url);
			$url = str_replace("{filename}", $pkg->filename, $url);
			$url = str_replace("{version}", $pkg->version, $url);

			$output[$priority][] = array(
				"id" => "cdn-{$params->id}-{$pkg->name}",
				"value" => $url,
				"title" => $title,
				"details" => $pkg->description,
				"icon" => "icon-cache/{$params->id}.png"
			);

		}
	}

	// print out order
	$count = 15; // 15
	foreach($output as $list) {
		foreach($list as $item) {
			$w->result( $item["id"], $item["value"], $item["title"], $item["details"], $item["icon"], "yes" );
			if (!--$count) { break; }
		}
	}
}

/*if ( count( $w->results() ) == 0 ) {
	$w->result( 'cdn', $query, 'No libraries found.', $query, 'icon.png', 'no' );
}*/

// build cloudflare DB
function cloudflare($url) {
	global $w;
	return $w->request($url);
}

// build google DB
function google($url) {
	global $w;
	$data = $w->request($url);

	if ($debug && $data == '') {
		console('URL has likely changed.');
		console($data);
	}
	preg_match_all('/<h3>(.*?)<\/h3>\s*<dl>[\s\S]*?src="(.*?)"[\s\S]*?>(.*?)<\/a>/i', $data, $matches);
	//console($data);
	//console($matches);
	
	$json = array(
		"packages" => array()
	);
	for($i = 0, $l = sizeof($matches[0]); $i < $l; $i++) {
		// https://ajax.googleapis.com/ajax/libs/angularjs/1.4.9/angular.min.js
		//console($matches[2][$i]);
		preg_match('/ajax\.googleapis\.com\/ajax\/libs\/([\w]*)\/([\d\.]*)\/([\s\S]*)/i', $matches[2][$i], $url_matches);
		//console($url_matches);

		//preg_match_all('/([\d\.]{3,9})/i', $matches[3][$i], $versions);
		//for($j = 0, $k = sizeof($versions[0]); $j < $k; $j++) {
			$json["packages"][] = array(
				"name" => $url_matches[1],			// angularjs
				"description" => $matches[2][$i],	// angularjs.org
				//"version" => $versions[1][$j],
				"version" => $url_matches[2],		// 1.4.9
				"filename" => $url_matches[3],		// 
				"keywords" => array()
			);
		//}
	}
	return json_encode($json);
}

// build msn DB
function msn($url) {
	global $w;
	$data = $w->request($url);

	$json = array(
		"packages" => array()
	);

	preg_match_all('/<h4>([\w\s]*?) version ([\d\.]*)<\/h4>[\s\S]*?<li>http:\/\/ajax.aspnetcdn.com\/ajax\/([\s\S]*?)\/([\w\.]*)<\/li>/i', $data, $matches);
	for($i = 0, $l = sizeof($matches[0]); $i < $l; $i++) {
		$json["packages"][] = array(
			"name" => trim($matches[1][$i]),
			"description" => "",
			"version" => $matches[2][$i],
			"filename" => $matches[4][$i],
			"keywords" => array()
		);
	}
	return json_encode($json);
}

// build jsdelivr DB
function jsdelivr($url) {
	global $w;
	$data = $w->request($url);
	$data = json_decode($data, true);
	$json = array(
		"packages" => array()
	);
	for($i = 0, $l = sizeof($data); $i < $l; $i++) {
		$json["packages"][] = array(
			"name" => $data[$i]['name'],
			"description" => $data[$i]['description'],
			"version" => $data[$i]['lastversion'],
			"filename" => $data[$i]['mainfile'],
			"keywords" => array()
		);
	}
	return json_encode($json);
}

echo $w->toxml();
// ****************
?>
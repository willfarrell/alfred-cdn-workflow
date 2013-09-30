<?php
//$query = "jq";
//$query = "cloudflare jquery";
$query = "google jq";
//$query = "msn jq";
// ****************
//error_reporting(0);
require_once('workflows.php');

$w = new Workflows();
if (!isset($query)) { $query = "{query}"; }
$query = strtolower(trim($query));

$cdns = json_decode(file_get_contents("cdns.json"));
$output = array();

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
	$pkgs = $w->read("{$params->id}CDN.json");
	$timestamp = $w->filetime("{$params->id}CDN.json");
	if ((!$pkgs || $timestamp < (time() - 7 * 86400))) {
		$id = $params->id;
		$data = $id( ($params->db_url) ? $params->db_url : $params->site);
		$w->write($data, "{$params->id}CDN.json");
		$pkgs = json_decode( $data );
	}/* else if (!$pkgs) {
		// add in db gen scripts
		
		$data = $id($params->site);
		
		
		$data = '{"packages":[]}';
		$pkgs = json_decode( $data );
	}*/
	
	$pkgs = $pkgs->packages;
	return $pkgs;
}

function search($plugin, $query) {
	
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
			$title = $pkg->name." (".$pkg->version.")"; // remove grunt- from title
		
			$url = $params->url;
			$url = str_replace("{name}", $pkg->name, $url);
			$url = str_replace("{filename}", $pkg->filename, $url);
			$url = str_replace("{version}", $pkg->version, $url);
			
			//$w->result( "cdn-{$params->id}-{$pkg->name}", $url, $title, $pkg->description, "icon-cache/{$params->id}.png", "yes" );
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
	$count = 15;
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
	
	preg_match_all('/<div id="(.*?)">\s*<dl>[\s\S]*?<dt>(.*?)<\/dt>([\s\S]*?)<\/div>/i', $data, $matches);
	
	$json = array(
		"packages" => array()
	);
	
	for($i = 0, $l = sizeof($matches[0]); $i < $l; $i++) {
		preg_match('/ajax\.googleapis\.com\/ajax\/libs\/([\w]*)\/([\d\.]*)\/([\s\S]*?)"/i', $matches[3][$i], $url_matches);
		
		preg_match_all('/([\d\.]{3,9})/i', $matches[3][$i], $versions);
		for($j = 0, $k = sizeof($versions[0]); $j < $k; $j++) {
			$json["packages"][] = array(
				"name" => $url_matches[1],
				"description" => $matches[2][$i],
				"version" => $versions[1][$j],
				"filename" => $url_matches[3],
				"keywords" => array()
			);
		}
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
	
	preg_match_all('/<br \/>([\w\s]*?) version ([\d\.]*)[\s\S]*?<li>[\s\S]*?<\/li><li>http:\/\/ajax.aspnetcdn.com\/ajax\/([\w]*)\/([\s\S]*?)<\/li>/i', $data, $matches);
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

echo $w->toxml();
// ****************
?>
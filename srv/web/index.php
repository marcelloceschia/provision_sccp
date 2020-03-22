<?php

require(implode(DIRECTORY_SEPARATOR, array(
    __DIR__,
    '..',
    '..',
    'vendor',
    'autoload.php'
)));

use PROVISION\ConfigParser;
use PROVISION\Resolve;
$request = $_REQUEST ?? null;

function send_fallback_html($message) {
	global $request;
	while (ob_get_level()) {ob_end_clean();}
	if (ob_get_length() === false) {
		ob_start();
		header('Content-Description: README');
		header('Content-Type: text/html');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
	}
	$content="
		<html>
		<header>
		</header>
		<body>
			<h1>provision_sccp</h1>
			<p>Request:" . json_encode($request) . "</p>
			<p>Message:" . $message . "</p>
		</body>
		</html>
	";
	print ($content);
	ob_flush();
	flush();
}

function sendfile($filename) {
	if (file_exists($filename)) {
		while (ob_get_level()) {ob_end_clean();}
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . basename($filename));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($filename));

		/* want to stream out, so don't use file_get_contents() in this case */
		return readfile ($filename, FALSE);
	}
}
if (!$request || empty($request) || !array_key_exists('filename',$request) || empty($request['filename'])) {
	send_fallback_html("Empty 'filename' request sent");
	exit();
}
try {
	$base_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . "../..");
    	//global $base_path;
	$req_filename=$request['filename'];
	$configParser = new ConfigParser($base_path, "config.ini");
	$resolve = new Resolve($configParser->getConfiguration());
	if (($filename = $resolve->resolve($req_filename))) {
		sendfile($filename);
	}
	unset($resolve);
} catch(Exception $e) {
	send_fallback_html($e->getMessage());
}
?>

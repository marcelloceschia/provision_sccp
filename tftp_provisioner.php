#!/usr/bin/env php
<?php
require_once("lib/tftp.php");
require_once("lib/config.php");
require_once("lib/resolver.php");

class TFTPProvisioner extends TFTPServer
{
  private $_debug;
  private $_resolver;
  private $_settings_path;

  function __construct($server_url, $config, $logger = NULL, $debug = false)
  {
    $this->_config = $config;
    if (!$logger) {
      $logger = new Logger_NULL('LOG_ERROR');
    }
    parent::__construct($server_url, $logger);
    $this->_debug = $debug;
    $this->max_put_size = 60000000;
    $this->_resolver = new Resolver($config);
    $this->_settings_path = $this->_config['main']['base_path'] . DIRECTORY_SEPARATOR
                . $this->_config['subdirs']['settings']['path'] . DIRECTORY_SEPARATOR;
  }

  public function writable($peer, $req_filename)
  {
    $filename = $this->_settings_path . basename($req_filename);
    if (file_exists($filename)) {
      return is_writable($filename);
    }
    return is_writable($this->_settings_path);
  }

  public function get($peer, $req_filename, $mode)
  {
    $filename = $this->_resolver->resolve($req_filename);
    if (file_exists($filename) && is_readable($filename))
      return file_get_contents($filename);
    return false;
  }

  public function put($peer, $req_filename, $mode, $content)
  {
    // (SPA phones can write to tftpboot -> redirect PUT request to 'settings' folder)
    $filename = $this->_settings_path . basename($req_filename);
    return file_put_contents($filename, $content);
  }

  /*
   * STDOUT Log functions
   */
  private function log($peer, $level, $message)
  {
    echo(date("H:i:s") . " $level $peer $message\n");
  }

  public function log_debug($peer, $message)
  {
    if($this->_debug)
      $this->log($peer, "D", $message);
  }

  public function log_info($peer, $message)
  {
    $this->log($peer, "I", $message);
  }

  public function log_warning($peer, $message)
  {
    $this->log($peer, "W", $message);
  }

  public function log_error($peer, $message)
  {
    $this->log($peer, "E", $message);
  }

}

$host = "127.0.0.1";
$port = 10069;
$url = "udp://$host:$port";

echo "\nStarting TFTP Provisioner...\n";
$server = new TFTPProvisioner($url, $config, $logger);
if(!$server->loop($error))
  die("$error\n");
?>

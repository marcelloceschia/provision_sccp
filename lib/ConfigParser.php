<?php
declare(strict_types=1);
namespace PROVISION;

use PROVISION\Logger;
use PROVISION\Utils;

class ConfigParser {
	private $config = Array();
	private $defaults = '
		[main]
		debug = TRUE
		cache_filename = /tmp/provision_sccp_resolver.cache
		default_language = English_United_States
		log_type = SYSLOG
		log_level = LOG_INFO
		;log_filename = provision.log
		auto_generate_settings = FALSE
		auto_sign = FALSE
		auto_encrypt = FALSE

		[security]
		cert_ca = NULL
		cert_priv = NULL
		cert_pub = NULL
		hash = NULL

		[subdirs]
		etc = etc
		data = data
		firmware = firmware
		settings = settings
		wallpapers = wallpapers
		ringtones = ringtones
		locales = locales
		countries = countries
		languages = languages

		[settings]
		sshUserId = cisco
		sshPassword = cisco
		ipAddress = ipv4|ipv6
		datetime.template = M/D/YA
		datetime.timezone = W. Europe Standard/Daylight Time
		datetime.ipaddress = 10.x.x.x
		datetime.mode = Unicast
		members.myhost.hostname = myhost.domain.com
		members.myhost.ipv4 = 10.x.x.x
		members.myhost.ipv6 = 2001:470::x:x
		members.myhost.port = 2000
		;srts.
		;common.
		;vendor.
		locale.country = United_States
		locale.language = English_United_States
		locale.langcode = en_US
		locale.charset = utf-8
		urls.security = FALSE
		urls.information = NULL
		urls.authentication = NULL
		urls.services = NULL
		urls.direcory = NULL
		urls.messages = NULL
		urls.proxyserver = NULL
		;vpn.
		;phoneservices.
	';

	function __construct($base_path, $config_file) {
		# Merge defaults with ini file
		$default_config = $this->parse_multi_ini_string($this->defaults,true,INI_SCANNER_TYPED);
		$ini_config = $this->parse_multi_ini_file("$base_path/$config_file", true, INI_SCANNER_TYPED);
		if (!empty($ini_config)) {
			$config = array_merge($default_config, $ini_config);
		}
		$config['main']['base_path'] = $base_path;
		$config['main']['data'] = (!empty($config['main']['data'])) ? $base_path . "data" : DIRECTORY_SEPARATOR . 'data';
		$config['subdirs'] = $this->replaceSubdirTreeStructure($config['subdirs']);
		$this->config = $config;
		$this->initializeLogger();
	}
	
	private function initializeLogger() {
		global $logger;
		switch($this->config['main']['log_type']) {
			case 'SYSLOG':
				$logger = new Logger\Syslog($this->config['main']['log_level']);
				break;
			case 'FILE':
				if (!isempty($config['main']['log_file'])) {
					$logger = new Logger\Filename($this->config['main']['log_level'], $this->config['main']['log_file']);
				}
				break;
			case 'STDOUT':
				$logger = new Logger\Stdout($this->config['main']['log_level']);
				break;
			case 'STDERR':
				$logger = new Logger\Stderr($this->config['main']['log_level']);
				break;
			default:
				$logger = new Logger\Null($this->config['main']['log_level']);
		}
		$this->config['main']['logger'] = $logger;
	}
	
	/*!
	 * replace config['subdirs'] paths using tree_structure
	 * method imported from old version (and rewritten)
	 * Note: Still not sure if we actually need to do all this
	 */
	private function replaceSubdirTreeStructure($tmpSubdirs) {
		$tree_structure = Array(
			'etc' => array('parent' => NULL, "strip" => true),
			'data' => array('parent' => NULL, "strip" => true),
			'settings' => array('parent' => 'data', "strip" => true),
			'wallpapers' => array('parent' => 'data', "strip" => false),
			'ringtones' => array('parent' => 'data', "strip" => true),
			'locales' => array('parent' => 'data', "strip" => true),
			'firmware' => array('parent' => 'data', "strip" => true),
			'languages' => array('parent' => 'locales', "strip" => false),
			'countries' => array('parent' => 'locales', "strip" => false),
		);

		$subdirs = Array();
		foreach ($tree_structure as $key => $value) {
			if (!empty($tmpSubdirs[$key])) {
				if (!$value['parent']) {
					$path = $tmpSubdirs[$key];
				} else {
					$path = $subdirs[$value['parent']]['path'] . DIRECTORY_SEPARATOR . $tmpSubdirs[$key];
				}
				$subdirs[$key] = array('path' => $path, 'strip' => $value['strip']);
			}
		}
		return $subdirs;
	}

	/*!
	 * config parser that understands multidimensional ini entries
	 * using "." as the dimension separator
	 * standin replacement for parse_ini_string
	 */
	private function parse_multi_ini_string($string, $process_sections = false, $scanner_mode = INI_SCANNER_NORMAL) {
		$explode_str = '.';
		$escape_char = "'";
		// load ini file the normal way
		$data = parse_ini_string($string, $process_sections, $scanner_mode);
		if (!$process_sections) {
			$data = array($data);
		}
		foreach ($data as $section_key => $section) {
			// loop inside the section
			foreach ($section as $key => $value) {
				if (strpos($key, $explode_str)) {
					if (substr($key, 0, 1) !== $escape_char) {
						// key has a dot. Explode on it, then parse each subkeys
						// and set value at the right place thanks to references
						$sub_keys = explode($explode_str, $key);
						$subs =& $data[$section_key];
						foreach ($sub_keys as $sub_key) {
							if (!isset($subs[$sub_key])) {
								$subs[$sub_key] = [];
							}
							$subs =& $subs[$sub_key];
						}
						// set the value at the right place
						$subs = $value;
						// unset the dotted key, we don't need it anymore
						unset($data[$section_key][$key]);
					}
					// we have escaped the key, so we keep dots as they are
					else {
						$new_key = trim($key, $escape_char);
						$data[$section_key][$new_key] = $value;
						unset($data[$section_key][$key]);
					}
				}
			}
		}
		return $data;
		if (!$process_sections) {
			$data = $data[0];
		}
		return $data;
	}

	/*!	
	 * config file parser that understands multidimensional ini entries
	 * using "." as the dimension separator
	 * standin replacement for parse_ini_file
	 */
	private function parse_multi_ini_file($file, $process_sections = false, $scanner_mode = INI_SCANNER_NORMAL) {
		$string = file_get_contents($file);
		return $this->parse_multi_ini_string($string, $process_sections, $scanner_mode);
		return $data;
	}

	public function getConfiguration() {
		return $this->config;
	}
}
?>

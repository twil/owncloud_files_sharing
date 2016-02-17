<?php

namespace OCA\Files_Sharing;


use OC\Memcache\Memcached;


/**
 * Secret link generator for HTML preview
 * 
 * @author twil
 *
 */
class SecretLink {
	public static function getHTMLPreviewLink($config, $path, $owner) {

		$cache = new Memcached();

		// Check if salt is set
		$secretSalt = $config->getSystemValue('html_preview_salt');
		$htmlPreviewPrefix = $config->getSystemValue('html_preview_prefix');
		if(!$secretSalt || !$htmlPreviewPrefix) {
			$this->log_error('html_preview_salt or html_preview_prefix not set');
			return '';
		}

		// Get path with an owner info
		$secretPath = "/" . $owner . "/files" . $path;

		// preset params
		$token = bin2hex(openssl_random_pseudo_bytes(32));
		$expires = '2020-12-31 23:59:59';
		$fileSaltKey = 'filesalt_' . md5($secretPath);

		$expires = strtotime($expires);

		// set token
		$cache->set($fileSaltKey, $token, 2 * 60); // expire in 2 minutes

		$secretLink = self::getNginxSecretLink($secretPath, $expires, $token,
				                               $secretSalt, $htmlPreviewPrefix);

		return $secretLink;
	}

	public static function getNginxSecretLink($path, $expires, $fileSalt,
										      $secretSalt, $prefix='',
			                                  $suffix='') {
		$hash = md5($expires . $path . $fileSalt . $secretSalt, true);
		$hash = base64_encode($hash);
		$hash = str_replace(array('+', '/', '='), array('-', '_', ''), $hash);

		return $prefix . $path . "?md5=" . $hash . "&expires=" . $expires . $suffix;
	}
}

<?php

namespace OCA\Files_Sharing\Middleware;


use OC_Files;

use OC\Files\Filesystem;
use OC\Memcache\Memcached;
use OC\AppFramework\Utility\ControllerMethodReflector;

use OCP\Share;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\ILogger;
use OCP\IConfig;


class HtmlPreviewMiddleware extends Middleware {

	private $config;
	private $cache; // we need memcached!
	private $reflector;
	private $logger;
	private $appName;

	public function __construct(IConfig $config,
			                    ControllerMethodReflector $reflector,
			                    ILogger $logger, $appName) {
		$this->config = $config;
		//$this->cache = $cache;
		$this->reflector = $reflector;
		$this->logger = $logger;
		$this->appName = $appName;

		// Some problems with DI. init manually
		$this->cache = new Memcached();
	}

	/**
	 * Change Preview For HTML files
	 */
	public function afterController($controller, $methodName,
			                        Response $response) {
		// Check if salt is set
		$secretSalt = $this->config->getSystemValue('html_preview_salt');
		$htmlPreviewPrefix = $this->config->getSystemValue('html_preview_prefix');
		$htmlPreviewDomain = $this->config->getSystemValue('html_preview_domain');
		if(!$secretSalt || !$htmlPreviewPrefix) {
			$this->log_error('html_preview_salt or html_preview_prefix not set');
			return $response;
		}

		if($response instanceof \OCP\AppFramework\Http\NotFoundResponse) {
			return $response;
		}

		if( !($controller instanceof \OCA\Files_Sharing\Controllers\ShareController) ) {
			return $response;
		}

		if($methodName != 'showShare') {
			return $response;
		}

		$params = $response->getParams();

		// We are interested only in text/html files
		if($params['mimetype'] != 'text/html') {
			return $response;
		}

		$token = $params['sharingToken'];
		$linkItem = Share::getShareByToken($token, false);

		// get expiration date
		$expires = $linkItem['expiration'];
		if(!$expires) {
			$expires = '2020-12-31 23:59:59';
		}
		$expires = strtotime($expires);

		// Get path with an owner info
		$path = Filesystem::getPath($linkItem['file_source']);
		$owner = $params['owner'];
		$secretPath = "/" . $owner . "/files" . $path;

		// set token
		$fileSaltKey = 'filesalt_' . $secretPath;
		$this->cache->set($fileSaltKey, $token, 5 * 60); // expire in 5 minutes

		$secretLink = self::getSecretLink($secretPath, $expires, $token,
				                          $secretSalt, $htmlPreviewPrefix);

		$params['secretLink'] = $secretLink;

		// Generate new response
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('\'self\'');
		if($htmlPreviewDomain) {
			$csp->addAllowedFrameDomain($htmlPreviewDomain);
		}
		$response = new TemplateResponse($this->appName, 'html_preview_public',
				                         $params, 'base');
		$response->setContentSecurityPolicy($csp);

		return $response;
	}

	protected function log_error($message) {
		$this->logger->error($message, array('app' => $this->appName));
	}

	public static function getSecretLink($path, $expires, $fileSalt,
			                             $secretSalt, $prefix='', $suffix='') {
		$hash = md5($expires . $path . $fileSalt . $secretSalt, true);
		$hash = base64_encode($hash);
		$hash = str_replace(array('+', '/', '='), array('-', '_', ''), $hash);

		return $prefix . $path . "?md5=" . $hash . "&expires=" . $expires . $suffix;
	}
}

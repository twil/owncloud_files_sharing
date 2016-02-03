<?php

namespace OCA\Files_Sharing\Middleware;


use OC\Memcache\Memcached;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\Response;
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
		if(!$this->config->getSystemValue('html_preview_salt')) {
			$this->log_error('html_preview_salt not set');
			return $response;
		}

		// Check token in Memcached and clear if necessary
		if($response instanceof \OCP\AppFramework\Http\NotFoundResponse) {
			// TODO: unset token in Memcached but how??? Might be cron job?
			return $response;
		}

		if( !($controller instanceof \OCA\Files_Sharing\Controllers\ShareController) ) {
			return $response;
		}

		$params = $response->getParams();
		$token = $params['sharingToken'];

		$path = $controller->_getPath($token);

		// set token
		$fileSaltKey = 'filesalt_' . $path;
		$this->cache->set($fileSaltKey, $token);
		$setToken = $this->cache->get($fileSaltKey);

		$this->log_error($path . "; " . $setToken);

		return $response;
	}

	protected function log_error($message) {
		$this->logger->error($message, array('app' => $this->appName));
	}
}

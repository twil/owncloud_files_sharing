<?php

namespace OCA\Files_Sharing\Middleware;


use OC\Memcache\Memcached;
use OC\AppFramework\Utility\ControllerMethodReflector;

use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\ILogger;
use OCP\IConfig;

use OCA\Files_Sharing\SecretLink;


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
		$params = $response->getParams();

		if($response instanceof \OCP\AppFramework\Http\NotFoundResponse ||
		   !($controller instanceof \OCA\Files_Sharing\Controllers\ShareController) ||
		   $methodName != 'showShare' ||
		   $params['mimetype'] != 'text/html') {
			return $response;
		}

		$params['secretLink'] = SecretLink::getHTMLPreviewLink($this->config,
				                                               $path, $owner);

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
}

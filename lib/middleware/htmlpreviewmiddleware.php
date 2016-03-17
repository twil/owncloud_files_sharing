<?php

namespace OCA\Files_Sharing\Middleware;

use OC\Files\View;
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
		if($response instanceof  \OCP\AppFramework\Http\JSONResponse ||
		   $response instanceof \OCP\AppFramework\Http\NotFoundResponse ||
		   !($controller instanceof \OCA\Files_Sharing\Controllers\ShareController) ||
		   $methodName != 'showShare') {
			return $response;
		}

		$params = $response->getParams();
		if($params['mimetype'] != 'text/html') {
			return $response;
		}

		$token = $params['sharingToken'];
		$linkItem = Share::getShareByToken($token, false);

		$path = Filesystem::getPath($linkItem['file_source']);
        $owner = $params['owner'];

        // need this to get the real owner
        Filesystem::initMountPoints($owner);
        $view = new View('/' . $owner . '/files');
        $owner = $view->getOwner($path);

		$params['secretLink'] = SecretLink::getHTMLPreviewLink($this->config,
				                                               $path, $owner);

		// Generate new response
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('\'self\'');

		$htmlPreviewDomain = $this->config->getSystemValue('html_preview_domain');
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

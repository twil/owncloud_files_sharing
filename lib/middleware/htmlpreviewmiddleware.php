<?php

namespace OCA\Files_Sharing\Middleware;


use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\Response;
use OCP\ILogger;


class HtmlPreviewMiddleware extends Middleware {

	private $reflector;
	private $logger;
	private $appName;

	public function __construct(ControllerMethodReflector $reflector,
			                    ILogger $logger, $appName) {
		$this->reflector = $reflector;
		$this->logger = $logger;
		$this->appName = $appName;

        $this->logger->error('HtmlPreviewMiddleware constructor',
                             array('app' => $this->appName));
	}

	/**
	 * Change Preview For HTML files
	 */
	public function afterController($controller, $methodName,
			                        Response $response){
		$this->logger->error("HtmlPreviewMiddleware " . get_class($controller),
				             array('app' => $this->appName));

		return $response;
	}

    public function beforeOutput($controller, $methodName, $output) {
        return 'ASDFASDFASDFASDFAD';
    }
}

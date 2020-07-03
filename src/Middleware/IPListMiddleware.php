<?php

namespace Madmatt\IPLists\Middleware;

use Madmatt\IPLists\Service\IPListService;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class IPListMiddleware implements HTTPMiddleware
{
    use Configurable, Injectable;

    /**
     * @var bool Defaults to true, set this to false via YML config to globally disable the middleware
     * @config
     */
    private static $enabled = true;

    /**
     * @var bool Defaults to true, set this to false via YML config to disable the middleware on development enviroments
     * @config
     */
    private static $enabled_on_dev = true;

    /**
     * @var bool Defaults to false, set this to true via YML config to enable the middleware on the command-line
     * interface (cli). This isn't generally considered a good idea, and may be removed in future (note: this middleware
     * extends *HTTPMiddleware*, so you'd only expect it to apply to HTTP requests but it also applies to cli).
     * @config
     */
    private static $enabled_on_cli = false;

    /**
     * @var IPListService
     */
    public $service;

    private static $dependencies = [
        'service' => '%$' . IPListService::class
    ];

    /**
     * @inheritDoc
     */
    public function process(HTTPRequest $request, callable $delegate)
    {
        // Don't process middleware if it's globally disabled
        if (!$this->config()->enabled) {
            return $delegate($request);
        }

        // Don't process middleware if this is dev and it's disabled on dev environment
        if (Director::isDev() && !$this->config()->enabled_on_dev) {
            return $delegate($request);
        }

        // Don't process middleware if this is cli and it's disabled in cli-mode
        if (Director::is_cli() && !$this->config()->enabled_on_cli) {
            return $delegate($request);
        }

        // Check if the user can access this request object
        if ($this->service->canAccess($request)) {
            return $delegate($request);
        } else {
            return $this->service->getDenialResponse($request);
        }
    }
}

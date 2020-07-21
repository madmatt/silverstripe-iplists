<?php

namespace Madmatt\IPLists\Service;

use Exception;
use InvalidArgumentException;
use LogicException;
use Madmatt\IPLists\Model\IP;
use Madmatt\IPLists\Model\IPList;
use Monolog\Logger;
use PageController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ErrorPage\ErrorPage;

class IPListService
{
    /**
     * @var Logger
     */
    public $auditLogger;

    private static $dependencies = [
        'auditLogger' => '%$AuditLogger'
    ];

    /**
     * @var IPList canAccess() will populate this with the matched IPList during a request, so it can be used later
     */
    private $matchedList;

    /**
     * Const values that are used to signal whether access was explicitly granted, denied, or ignored by a particular
     * {@link IPList} object.
     */
    public const IP_ACCESS_ALLOWED = 1;
    public const IP_ACCESS_DENIED = 0;
    public const IP_ACCESS_AMBIVALENT = -1;

    /**
     * @var array
     */
    private $validLists = [];

    /**
     * Check all {@link IPList} objects to find any that match the given URL route. If one is found, confirm whether the
     * current user's IP address is allowed to access the route or not.
     *
     * @param HTTPRequest $request The request to extract the current URL and user's IP address from
     * @return bool true if the given IP can access the website
     */
    public function canAccess(HTTPRequest $request)
    {
        // @todo Ideally we don't want to use the ORM here too much, as it results in uncachable queries
        $lists = IPList::get()->filter('Enabled', true);
        $currentIp = $request->getIP();
        $currentRoute = $this->getCurrentRoute($request);
        $context = [
            'CurrentIP' => $currentIp,
            'CurrentRoute' => $currentRoute,
        ];

        /** @var IPList $list */
        foreach ($lists as $list) {
            // We can safely ignore the return value of self::IP_ACCESS_AMBIVALENT, it just means that the IPList
            // doesn't explicitly allow or deny, so we should just move onto the next list
            switch ($this->canAccessWithList($list, $request)) {
                case self::IP_ACCESS_ALLOWED:
                    return true;

                case self::IP_ACCESS_DENIED:
                    return false;
            }
        }

        // By default if no IP list matches, then we allow access
        $this->log(self::IP_ACCESS_ALLOWED, $context);
        return true;
    }

    /**
     * Reviews whether a given IPList would allow or deny access, or whether the list does not match the provided
     * HTTPRequest.
     *
     * @param IPList $list
     * @param HTTPRequest $request
     * @return int One of self::IP_ACCESS_ALLOWED, self::IP_ACCESS_DENIED, or self::IP_ACCESS_AMBIVALENT
     */
    public function canAccessWithList(IPList $list, HTTPRequest $request)
    {
        $currentIp = $request->getIP();
        $currentRoute = $this->getCurrentRoute($request);
        $context = [
            'CurrentIP' => $currentIp,
            'CurrentRoute' => $currentRoute,
        ];

        // early exit if the provided IPList isn't enabled
        if (!$list->Enabled) {
            return self::IP_ACCESS_AMBIVALENT;
        }

        $routes = $list->getProtectedRoutesForService();

        // Loop through all routes, looking for a match with the current route
        foreach ($routes as $route) {
            if (strpos($currentRoute, $route) === 0) {
                // We have a matching route, let's see if the current IP is included or not
                $ips = $list->IPs();
                $context['IPList'] = $list;
                $this->matchedList = $list; // Expected to be overridden if there are multiple matching lists

                /** @var IP $ip */
                foreach ($ips as $ip) {
                    $context['IP'] = null; // Ensure we don't carry over data for some reason from a previous loop

                    if ($ip->contains($currentIp)) {
                        // We have a match for both route *and* IP, so we need to apply the protection method
                        $context['IP'] = $ip;

                        switch ($list->ListType) {
                            case IPList::LIST_TYPE_ALLOW:
                                $this->log(self::IP_ACCESS_ALLOWED, $context);
                                return self::IP_ACCESS_ALLOWED;

                            case IPList::LIST_TYPE_DENY:
                                $this->log(self::IP_ACCESS_DENIED, $context);
                                return self::IP_ACCESS_DENIED;

                            default:
                                $this->auditLogger->error(
                                    sprintf('Invalid list type %s for IP list %d', $list->ListType, $list->ID),
                                    [
                                        'msg_source' => 'silverstripe-iplists',
                                        'msg_type' => 'error'
                                    ]
                                );

                                throw new Exception(
                                    sprintf('IPList #%d type "%s" invalid', $list->ListType, $list->ID)
                                );
                        }
                    }
                }

                // If we reach here, the route matched but the IP didn't. If the route should be protected, we need
                // to deny access
                if ($list->ListType == IPList::LIST_TYPE_ALLOW) {
                    $this->log(self::IP_ACCESS_DENIED, $context);
                    return self::IP_ACCESS_DENIED;
                }
            }
        }

        // If we reach here, then the IPList either didn't match the provided URL route, or we're ambivalent on whether
        // to deny access (for example, if ListType == DENY and the user's IP isn't in the list
        return self::IP_ACCESS_AMBIVALENT;
    }

    public function getDenialResponse(HTTPRequest $request)
    {
        if (!$this->matchedList) {
            throw new LogicException('IPListService::canAccess() must be called before calling getDenialResponse()');
        }

        // Ensure there is a dummy controller and empty session for ErrorPage to use when it looks for a Controller
        if (!Controller::has_curr()) {
            $controller = new Controller();
            $controller->getRequest()->setSession(Injector::inst()->create(Session::class, []));
            $controller->pushCurrent();
        }

        switch ($this->matchedList->DenyMethod) {
            case IPList::DENY_METHOD_404:
                return ErrorPage::response_for(404);

            case IPList::DENY_METHOD_400:
                return ErrorPage::response_for(400);

            default:
                $msg = sprintf(
                    'Invalid deny method %s for list ID %d',
                    $this->matchedList->DenyMethod,
                    $this->matchedList->ID
                );

                throw new LogicException($msg);
        }
    }

    /**
     * Gets the current route URL, without URL params, starting with a leading slash
     *
     * @param HTTPRequest $request
     * @return string
     */
    protected function getCurrentRoute(HTTPRequest $request)
    {
        return Controller::join_links('/', $request->getURL()); // Ensures route always starts with a /
    }

    /**
     * @param int $allowOrDeny
     * @param array $context Context array should include 'CurrentIP', 'CurrentRoute', 'IPList', and 'IP' keys.
     * @return void
     */
    protected function log(int $allowOrDeny = -1, array $context = [])
    {
        $successLogMsg = 'IP %s allowed to access route %s by IP list %d (%s), IP rule %d (IP %s, type %s)';
        $failureLogMsg = 'IP %s denied access to route %s by IP list %d (%s), IP rule %d (IP %s, type %s)';

        switch ($allowOrDeny) {
            case self::IP_ACCESS_ALLOWED:
                $message = $successLogMsg;
                $logLevel = Logger::DEBUG;
                $msgType = 'success';
                break;

            case self::IP_ACCESS_DENIED:
                $message = $failureLogMsg;
                $logLevel = Logger::WARNING;
                $msgType = 'failure';
                break;

            default:
                throw new InvalidArgumentException(sprintf(
                    'Argument 1 passed to IPListService::log() must be 0 (denied) or 1 (allowed), was given %s.',
                    $allowOrDeny
                ));

                break;
        }

        if (isset($context['IPList']) && !$context['IPList'] instanceof IPList) {
            throw new InvalidArgumentException('IPListService::log() expects an IPList object included for context');
        }

        if (isset($context['IP']) && !$context['IP'] instanceof IP) {
            throw new InvalidArgumentException('IPListService::log() expects an IP object included for context');
        }

        $currentIp = $context['CurrentIP'] ?? '<No current IP passed>';
        $currentRoute = $context['CurrentRoute'] ?? '<No current route>';
        $ipListId = (isset($context['IPList']) ? $context['IPList']->ID : 0);
        $ipListTitle = (isset($context['IPList']) ? $context['IPList']->Title : 'No IPList passed');
        $ipID = (isset($context['IP']) ? $context['IP']->ID : 0);
        $ipIP = (isset($context['IP']) ? $context['IP']->IP : 'No IP');
        $ipType = (isset($context['IP']) ? $context['IP']->AddressType : 'No IP type');

        $logContext = [
            'msg_source' => 'silverstripe-iplists',
            'msg_type' => $msgType
        ];

        $this->auditLogger->log(
            $logLevel,
            sprintf($successLogMsg, $currentIp, $currentRoute, $ipListId, $ipListTitle, $ipID, $ipIP, $ipType),
            $logContext
        );
    }
}

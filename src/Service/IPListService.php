<?php

namespace Madmatt\IPLists\Service;

use Madmatt\IPLists\Model\IP;
use Madmatt\IPLists\Model\IPList;
use Monolog\Logger;
use SilverStripe\Control\HTTPRequest;

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
        $currentRoute = $request->getURL();
        $currentIp = $request->getIP();

        $successLogMsg = 'IP %s allowed to access route %s by IP list %d (%s), IP rule %s (type %s)';
        $failureLogMsg = 'IP %s allowed to access route %s by IP list %d (%s), IP rule %s (type %s)';

        /** @var IPList $list */
        foreach ($lists as $list) {
            $routes = $list->getProtectedRoutesForService();

            // Loop through all routes, looking for a match with the current route
            foreach ($routes as $route) {
                if (strpos($currentRoute, $route) === 0) {
                    // We have a matching route, let's see if the current IP is included or not
                    $ips = $list->getIPs();

                    /** @var IP $ip */
                    foreach ($ips as $ip) {
                        if ($ip->contains($currentIp)) {
                            // We have a match for both route *and* IP, so we need to apply the protection method
                            switch ($list->ListType) {
                                case IPList::LIST_TYPE_ALLOW:
                                    $this->auditLogger->debug(
                                        sprintf(
                                            $successLogMsg,
                                            $currentIp,
                                            $currentRoute,
                                            $list->ID,
                                            $list->Title,
                                            $ip->IP,
                                            $ip->AddressType
                                        ),
                                    [
                                        'msg_source' => 'silverstripe-iplists',
                                        'msg_type' => 'success'
                                    ]);

                                    return true;

                                case IPList::LIST_TYPE_DENY:
                                    $this->auditLogger->warn(
                                        sprintf(
                                            $failureLogMsg,
                                            $currentIp,
                                            $currentRoute,
                                            $list->ID,
                                            $list->Title,
                                            $ip->IP,
                                            $ip->AddressType
                                        ),
                                        [
                                            'msg_source' => 'silverstripe-iplists',
                                            'msg_type' => 'failure'
                                        ]
                                    );

                                    return false;

                                default:
                                    $this->auditLogger->error(
                                        sprintf('Invalid list type %s for IP list %d', $list->ListType, $list->ID),
                                        [
                                            'msg_source' => 'silverstripe-iplists',
                                            'msg_type' => 'error'
                                        ]
                                    );
                            }
                        }
                    }

                    // If we reach here, the route matched but the IP didn't. If the route should be protected, we need
                    // to deny access
                    if ($list->ListType == IPList::LIST_TYPE_ALLOW) {
                        $this->auditLogger->warn(
                            sprintf(
                                $failureLogMsg,
                                $currentIp,
                                $currentRoute,
                                $list->ID,
                                $list->Title,
                                $ip->IP,
                                $ip->AddressType
                            ),
                            [
                                'msg_source' => 'silverstripe-iplists',
                                'msg_type' => 'failure'
                            ]
                        );

                        return false;
                    }
                }


            }
        }

        // Default, if no IP list matches, is to allow access
        return true;
    }

    public function getDenialResponse(HTTPRequest $request)
    {

    }
}

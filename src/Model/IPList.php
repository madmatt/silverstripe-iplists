<?php

namespace Madmatt\IPLists\Model;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;

/**
 * Class IPList
 * @package Madmatt\IPLists\Model
 *
 * Allows or denies access to one or more URL routes based on the set of IPs listed inside. Lists are evaluated in
 * priority order, see the implementation in Madmatt\IPLists\Service\IPListService for more.
 */
class IPList extends DataObject
{
    private static $singular_name = 'IP list';

    private static $table_name = 'IPLists_IPList';

    private static $default_sort = 'Priority DESC, ID ASC';

    private static $db = [
        'Title' => 'Varchar(200)',
        'Description' => 'Text',
        'Enabled' => 'Boolean',
        'ListType' => 'Enum(array("","' . self::LIST_TYPE_ALLOW . '","' . self::LIST_TYPE_DENY . '"), "")',
        'DenyMethod' => 'Enum(array("HTTP404","HTTP400"), "HTTP404")',
        'Priority' => 'Int',
        'ProtectedRoutes' => 'Text',
    ];

    private static $many_many = [
        'IPs' => IP::class
    ];

    private static $many_many_extraFields = [
        'IPs' => [
            'Sort' => 'Int'
        ]
    ];

    private static $defaults = [
        'Enabled' => true
    ];

    const LIST_TYPE_DENY = 'Deny';
    const LIST_TYPE_ALLOW = 'Allow';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->dataFieldByName('Title')->setDescription(
            'Not used anywhere except in the CMS - just a title to describe what the IPs are used for.'
        );

        $fields->dataFieldByName('Description')->setDescription(
            'Not used anywhere except in the CMS - provide a longer description for the reason why this IP List exists.'
        );

        $fields->dataFieldByName('Enabled')->setDescription(
            'Whether or not this IP list should be enabled. Untick this box to temporarily disable the IP list while'
                . ' retaining all settings.'
        );

        $fields->dataFieldByName('ListType')->setDescription(
            'Identify the type of list this is.<br /><strong>Allow:</strong> All the given IPs are allowed to the URLs'
                . ' specified, and anyone else is denied access.<br /><strong>Deny:</strong> All the provided IPs are'
                . ' denied access to the URLs specified, and anyone else is allowed access.'
        );

        $fields->dataFieldByName('DenyMethod')->setDescription(
            'If the given IP address is denied access, how should that denial be handled?<br /><strong>HTTP'
                . ' 400:</strong> Returns your "Bad Request" error page content.<br /><strong>HTTP 404:</strong>'
                . 'Returns your "Page not found" error page content.'
        );

        $fields->dataFieldByName('Priority')->setDescription(
            'Provide the relative priority of this IP list among all others. IP lists are evaluated in descending order'
                . ' (e.g. highest priority first), if an IP address is denied access by a higher priority rule, then'
                . ' allowing it under a lower priority list will have no effect.'
        );

        $fields->dataFieldByName('ProtectedRoutes')->setDescription(
            'List all URL routes that should be protected. Each URL route should be on its own line, and each route'
                . ' will be matched based on the start of the string (e.g. "/Security" will match all URLs that begin'
                . ' with /Security - including the login form, lost password form etc). Regular expression syntax'
                . ' support is coming soon.'
        );

        if ($this->exists()) {
            /** @var GridField $ipListField */
            $ipListField = $fields->dataFieldByName('IPs');

            $ipListConfig = GridFieldConfig::create()
                ->addComponent(new GridFieldButtonRow('after'))
                ->addComponent(new GridFieldTitleHeader())
                ->addComponent(new GridFieldEditableColumns())
                ->addComponent(new GridFieldEditButton())
                ->addComponent(new GridFieldDeleteAction())
                ->addComponent(new GridFieldOrderableRows('Sort'))
                ->addComponent(new GridFieldAddNewInlineButton('buttons-after-left'))
                ->addComponent(new GridFieldDetailForm());

            $ipListField->setConfig($ipListConfig);
            $fields->addFieldToTab('Root.Main', $ipListField);
        }

        return $fields;
    }

    /**
     * Returns all protected routes as an array, with all leading/trailing whitespace trimmed. If a route is empty
     * (e.g. a blank line), it is removed.
     *
     * @return array
     */
    public function getProtectedRoutesForService()
    {
        $routes = $this->ProtectedRoutes;

        if (!$routes) {
            return [];
        }

        $routes = explode("\n", $routes);
        $finalRoutes = [];

        foreach ($routes as $uri) {
            $uri = trim($uri);
            if (!$uri) continue; // Skip empty values

            $finalRoutes[] = $uri;
        }

        return $finalRoutes;
    }
}

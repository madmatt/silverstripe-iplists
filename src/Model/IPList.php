<?php

namespace Madmatt\IPLists\Model;

use Madmatt\IPLists\Service\IPListService;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ToggleCompositeField;
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
 *
 * @property string $Title The name of this IP List (e.g. 'Protect admin and login')
 * @property string $Description The longer-form description of this IP list
 * @property bool $Enabled Whether or not this IP List is current enabled (disabled lists are never evaluated)
 * @property string $ListType The type of IP List that this is (e.g. deny-others or allow-others)
 * @property string $DenyMethod The method used to deny a user (e.g. HTTP 404 to keep this endpoint hidden)
 * @property int $Priority The priority of this IP List (higher number = evaluated earlier in chain)
 * @property string $ProtectedRoutes New-line delimited list of URL routes to be protected by this IP List (e.g. /admin)
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
        'ListType' => 'Enum(array("' . self::LIST_TYPE_ALLOW . '","' . self::LIST_TYPE_DENY . '"), "")',
        'DenyMethod' => 'Enum(array("' . self::DENY_METHOD_404 . '","' . self::DENY_METHOD_400 . '"), "' . self::DENY_METHOD_404 . '")',
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
        'Enabled' => true,
        'Priority' => 100,
        'ListType' => self::LIST_TYPE_ALLOW,
        'DenyMethod' => self::DENY_METHOD_404,
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'Description' => 'Description',
        'Enabled.Nice' => 'Is Enabled?',
        'ProtectedRoutes' => 'Protected URLs',
        'IPs.Count' => 'Number of IPs'
    ];

    const LIST_TYPE_DENY = 'Deny';
    const LIST_TYPE_ALLOW = 'Allow';

    const DENY_METHOD_404 = 404;
    const DENY_METHOD_400 = 400;

    public function getCMSFields()
    {
        $fields = new FieldList();

        // Main fields
        $fieldTitle = TextField::create('Title', 'Title')
            ->setDescription('Not used anywhere except in the CMS - used to describe what the IPs are used for.');

        $fieldDescription = TextareaField::create('Description', 'Description')
            ->setRows(2)
            ->setDescription('Not used anywhere except in the CMS - allows you to provide a longer description.');

        $fieldEnabled = CheckboxField::create('Enabled', 'List enabled')
            ->setDescription(
                'Whether or not this IP list should be enabled. Untick this box to temporarily disable the IP list'
                    . ' while retaining all settings.'
            );

        $fieldProtectedRoutes = TextareaField::create('ProtectedRoutes')
            ->setRows(3)
            ->setDescription(
                'List all URL routes that should be protected. Each URL route should be on its own line, and each route'
                . ' will be matched based on the start of the string (e.g. "/Security" will match all URLs that'
                . ' begin with /Security - including the login form, lost password form etc). Regular expression'
                . ' syntax support is coming soon.'
            );

        $fields->push($fieldTitle);
        $fields->push($fieldDescription);
        $fields->push($fieldEnabled);
        $fields->push($fieldProtectedRoutes);

        if ($this->exists()) {
            /** @var GridField $ipListField */
            $ipListField = GridField::create('IPs', 'IPs', $this->IPs());

            $ipListConfig = GridFieldConfig::create()
                ->addComponents([
                    new GridFieldToolbarHeader(),
                    new GridFieldTitleHeader(),
                    $e = new GridFieldEditableColumns(),
                    new GridFieldEditButton(),
                    new GridFieldDeleteAction(false), // Make sure we delete
                    new GridField_ActionMenu(),
                    new GridFieldDetailForm(),
                    new GridFieldButtonRow('after'),
                    $t = new GridFieldAddNewInlineButton('buttons-after-left'),
                    new GridFieldAddExistingAutocompleter('buttons-after-right')
            ]);

            $t->setTitle('Add IP');
            $e->setDisplayFields([
                'Title' => 'Title',
                'IP' => 'IP',
                'AddressType' => 'Address type'
            ]);

            $ipListField->setConfig($ipListConfig);
            $fields->push($ipListField);
        } else {
            $helpMessage = '<p class="message warning">You can add IPs after you save for the first time.</p>';
            $fields->push(LiteralField::create('IPHelpMessage', $helpMessage));
        }

        // Setting fields
        $listTypeValues = $this->dbObject('ListType')->enumValues();
        $fieldListType = OptionsetField::create('ListType', 'List Type', $listTypeValues)
            ->setDescription(
                'Identify the type of list this is.<br /><strong>Allow:</strong> All the given IPs are allowed to the'
                . ' URLs specified, and anyone else is denied access.<br /><strong>Deny:</strong> All the provided'
                . ' IPs are denied access to the URLs specified, and anyone else is allowed access.'
            );

        $denyMethodValues = $this->getDenyMethodValues();
        $fieldDenyMethod = OptionsetField::create('DenyMethod', 'Deny method', $denyMethodValues)
            ->setDescription(
                'If the given IP address is denied access, how should that denial be handled?<br /><strong>HTTP'
                . ' 400:</strong> Returns your "Bad Request" error page content.<br /><strong>HTTP 404:</strong>'
                . 'Returns your "Page not found" error page content.'
            );

        $fieldPriority = NumericField::create('Priority')
            ->setDescription(
                'Provide the relative priority of this IP list among all others. IP lists are evaluated in descending'
                . ' order (e.g. highest priority first), if an IP address is denied access by a higher priority'
                . ' rule, then allowing it under a lower priority list will have no effect.'
            );

        $settingsField = ToggleCompositeField::create('Settings', 'List Settings', FieldList::create([
            $fieldListType,
            $fieldDenyMethod,
            $fieldPriority
        ]));

        $fields->push($settingsField);

        return $fields;
    }

    public function validate()
    {
        $valid = parent::validate();

        if (!$this->Title) {
            $valid->addFieldError('Title', 'You must define a Title for this IP list.');
        }

        if (!$this->ProtectedRoutes) {
            $valid->addFieldError(
                'ProtectedRoutes',
                'You must define some routes to protect, or else the list won\'t do anything.'
            );
        }

        // Ensure that this list doesn't explicitly deny access to the CMS for the current user (otherwise, continuing
        // with the write() will cause the user to lock themselves out of the CMS)
        /** @var IPListService $service */
        $service = Injector::inst()->get(IPListService::class);
        if ($service->canAccessWithList($this, Controller::curr()->getRequest()) === IPListService::IP_ACCESS_DENIED) {
            $valid->addError(
                'I can\'t let you do that - saving this IP List in its current state will lock you out of this admin'
                    . ' panel. Make sure you add your own IP address to the list before saving.'
            );
        }

        return $valid;
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

    /**
     * Pulled out into its own function so that it's easy to override if additional deny methods are added via
     * extensions - just extend this method
     */
    protected function getDenyMethodValues()
    {
        $methods = [
            self::DENY_METHOD_404 => 'HTTP 404 - Page not found error message',
            self::DENY_METHOD_400 => 'HTTP 400 - Bad request error message'
        ];

        $this->extend('updateDenyMethodValues', $methods);

        return $methods;
    }
}

<?php

namespace Madmatt\IPLists\Admin;

use Madmatt\IPLists\Model\IP;
use Madmatt\IPLists\Model\IPList;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\LiteralField;

class IPListAdmin extends ModelAdmin
{
    private static $menu_title = 'IP Lists';

    private static $url_segment = 'ip-lists';

    private static $menu_icon_class = 'font-icon-lock';

    private static $managed_models = [
        IPList::class,
        IP::class
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        if ($this->modelClass == IP::class) {
            $msg = '<div class="alert alert-warning"><strong>Caution:</strong> Removing IPs from this list will remove'
                . ' them from all IP lists and the database.</div>';
            $form->Fields()->insertBefore($this->sanitiseClassName(IP::class), LiteralField::create('IPHelper', $msg));


        }

        return $form;
    }
}

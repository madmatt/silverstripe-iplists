<?php

namespace Madmatt\IPLists\Tests\Model;

use Madmatt\IPLists\Model\IPList;
use SilverStripe\Dev\SapphireTest;

class IPListTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testGetCMSFieldsIPHelpMessage()
    {
        $list = new IPList();
        $fields = $list->getCMSFields();

        // IPHelpMessage should only appear if the list hasn't been saved yet, and should be replaced by the GridField
        $this->assertTrue($fields->hasField('IPHelpMessage'));
        $this->assertFalse($fields->hasField('IPs'));

        $list->write();
        $fields = $list->getCMSFields();
        $this->assertFalse($fields->hasField('IPHelpMessage'));
        $this->assertTrue($fields->hasField('IPs'));
    }
}

<?php

namespace Madmatt\IPLists\Tests\Model;

use Madmatt\IPLists\Model\IP;
use SilverStripe\Dev\SapphireTest;

class IPTest extends SapphireTest
{
    public function testContainsIP()
    {
        $ip = new IP([
            'IP' => '10.1.1.1',
            'AddressType' => 'IP'
        ]);

        $this->assertTrue($ip->contains('10.1.1.1'));
        $this->assertFalse($ip->contains('10.1.2.3'));
        $this->assertFalse($ip->contains('10.1.1.2'));
        $this->assertFalse($ip->contains('10.0.0.0'));
        $this->assertFalse($ip->contains('0.0.0.0'));
        $this->assertFalse($ip->contains('::1'));
    }

    public function testContainsCIDR()
    {
        $ip = new IP([
            'IP' => '10.1.1.1/24',
            'AddressType' => 'CIDR'
        ]);

        $this->assertTrue($ip->contains('10.1.1.0'));
        $this->assertTrue($ip->contains('10.1.1.1'));
        $this->assertTrue($ip->contains('10.1.1.5'));
        $this->assertTrue($ip->contains('10.1.1.128'));
        $this->assertTrue($ip->contains('10.1.1.255'));
        $this->assertFalse($ip->contains('10.1.2.3'));
        $this->assertFalse($ip->contains('10.0.0.0'));
        $this->assertFalse($ip->contains('10.0.1.1'));
        $this->assertFalse($ip->contains('10.2.0.0'));
        $this->assertFalse($ip->contains('11.0.0.0'));
    }
}

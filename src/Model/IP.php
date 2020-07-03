<?php

namespace Madmatt\IPLists\Model;

use SilverStripe\ORM\DataObject;

/**
 * Class IP
 * @package Madmatt\IPLists\Model
 *
 * A single IP (or IP range) included in one or more IPList objects. Individual IPs can be expressed in any form that
 * the S1lentium/IPTools[1] module understands. A single IP object may be attached to multiple lists.
 *
 * An individual IP object can be one of three types:
 * - Single IP: A single IPv4 or IPv6 IP address e.g. 10.1.2.3 or ::1
 * - CIDR range: A single CIDR range e.g. 10.0.0.1/24
 *
 * [1]: https://github.com/S1lentium/IPTools
 */
class IP extends DataObject
{
    private static $singular_name = 'IP';

    private static $table_name = 'IPLists_IP';

    private static $db = [
        'AddressType' => 'Enum(array("' . self::TYPE_IP . '","' . self::TYPE_CIDR . '"), "' . self::TYPE_IP . '")',
        'IP' => 'Varchar(45)', // IPv6 addresses can be up to 45 characters, if they include an IPv4 mapped IPv6 address
        'Title' => 'Varchar(200)',
    ];

    private static $belongs_many_many = [
        'Lists' => IPList::class
    ];

    private static $summary_fields = [
        'Title',
        'IP',
        'AddressType'
    ];

    const TYPE_IP = 'IP';
    const TYPE_CIDR = 'CIDR';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->dataFieldByName('AddressType')->setDescription(
            'The type of IP address this is.<br /><strong>IP:</strong> A single IPv4 or IPv6 IP address (e.g. 10.1.2.3'
                . ' or ::1)<br /><strong>CIDR:</strong> A single CIDR network range (e.g. 10.0.0.1/24)'
        );

        $fields->dataFieldByName('IP')->setDescription('Enter the IP address or range you want to allow or deny.');

        $fields->dataFieldByName('Title')->setDescription(
            'Not used anywhere except in the CMS - use this to identify whose IP address this is (e.g. "Office VPN",'
                . ' "John Smith home IP" etc.)'
        );

        return $fields;
    }

    /**
     * Determine whether or not this IP rule matches the provided IP address. If this AddressType is a single IP address
     * (e.g. 10.1.2.3), then the string is compared exactly (after trimming). If it's a CIDR range, then the address is
     * compared using the s1lentium/iptools module.
     *
     * @param string $ip The IP address to test this IP rule against
     * @return bool
     */
    public function contains(string $ip)
    {
        switch ($this->AddressType) {
            case self::TYPE_IP:
                return trim($ip) === trim($this->IP);

            case self::TYPE_CIDR:
                return N
        }
    }
}

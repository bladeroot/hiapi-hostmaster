<?php
/**
 * hiAPI hEPPy plugin
 *
 * @link      https://github.com/hiqdev/hiapi-heppy
 * @package   hiapi-heppy
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster\modules;

use hiapi\hostmaster\HostmasterTool;
use Exception;

class SecDNSModule extends AbstractModule
{
    /**
    * List of available extensions
    */
    protected $object = 'domain';

    /**
     * Set SecDNS refresh
     *
     * @param array $row
     * @reurn array
     */
    public function secdnsChange(array $row): array
    {
        return $this->command('update', array_filter([
            'domain' => $row['domain'],
            'extensions' => [
                'secDNS'    => array_merge($row, [
                    'command' => 'chg',
                ]),
            ],
        ]));

    }

    /**
     * Create SecDNS record
     *
     * @param array $row
     * @return array
     */
    public function secdnsCreate(array $row): array
    {
        return $this->command('update', array_filter([
            'domain' => $row['domain'],
            'extensions' => [
                'secDNS'    => array_merge($row, [
                    'command' => 'add',
                ]),
            ],
        ]));
    }

    /**
     * Remove SecDNS record
     *
     * @param array $row
     * @return array
     */
    public function secdnsDelete(array $row): array
    {
        return $this->command('update', array_filter([
            'domain' => $row['domain'],
            'extensions' => [
                'secDNS'    => array_merge($row, [
                    'command' => 'rem',
                ]),
            ],
        ]));
    }
}

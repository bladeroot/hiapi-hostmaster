<?php

/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster\modules;

use hiapi\hostmaster\HostmasterTool;
use InvalidParamException;

class ContactModule extends AbstractModule
{
    /** @var array $successCodes */
    protected $successCodes = [1000, 1001];

    protected $object = 'contact';

    public function contactCheck(array $row) : array
    {
        $row['epp_id'] = $this->_fixContactID($row['epp_id']);
        $res = $this->command('check', ['contact' => $row['epp_id']]);

        return $res['contact'][$row['epp_id']];
    }


}

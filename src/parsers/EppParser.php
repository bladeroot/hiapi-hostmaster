<?php

/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster\parsers;

use hiapi\hostmaster\helpers\XmlHelper;
use Exception;

class EppParser extends AbstractParser
{
    /** @var string */
    protected $object = 'epp';

    protected function parse_greeting(array $data) : array
    {
        return $data;
    }

    protected function parse_msgQ(array $data, array $res = null) : array
    {
        return array_merge($res ?? [], array_filter([
            'msgID' => $data['msgQ_attr']['id'],
            'msgMSG' => $data['msgQ']['msg'],
            'count' => $data['msgQ_attr']['count'],
        ]));
    }
}

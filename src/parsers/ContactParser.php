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

class ContactParser extends AbstractParser
{
    /** @var string */
    protected $object = 'contact';

    /** @var array */
    protected $stringField = [
        'roid',
        'id',
        'registrant',
        'crDate',
        'crID',
        'exDate',
        'clID',
        'upID',
        'upDate',
        'trDate',
    ];

    public function parse_infData(array $data, array $res) : array
    {
        foreach ($data as $key => $d) {
            $field = str_replace($this->object . ":", "", $key);
            if (in_array($field, $this->stringField, true)) {
                $res[$field] = $d;
                continue;
            }

            [$obj, $func] = explode(":", $key);
            $res = call_user_func_array([$this, "parse_{$func}"], [$data, $res]);
        }

        return $res;
    }
}

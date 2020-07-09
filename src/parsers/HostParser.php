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

class HostParser extends AbstractParser
{
    /** @var string */
    protected $object = 'host';

    protected function parse_addr(array $data, array $res = null) : ?array
    {
        if (is_string($data['host:addr'])) {
            return array_merge($res, [
                'ips' => $data['host:addr'],
            ]);
        }

        foreach ($data['host:addr'] as $key => $value) {
            if (strpos($key, '_attr') !== false) {
                continue;
            }

            $ips[] = $value;
            $ipsTypes[$data['host:addr']["{$key}_attr"]["ip"]][] = $value;
        }

        return array_merge($res ?? [], [
            'ips' => $ips,
            'ipsTypes' => $ipsTypes,
        ]);
    }
}

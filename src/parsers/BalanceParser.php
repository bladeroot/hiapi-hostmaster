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

class BalanceParser extends AbstractParser
{
    /** @var string */
    protected $object = 'balance';

    protected function parse_balance(array $data, array $res = []) : array
    {
        return array_merge($res, [
            'balance' => $data['balance:balance'],
            'bdate' => $data['balance:balance_attr']['bdate'],
        ]);
    }

    protected function parse_ns(array $data, array $res = []) : ?array
    {
        return array_merge($res, [
            'nameservers' => $data['domain:ns']['domain:hostObj'],
        ]);
    }

    protected function parse_host(array $data, array $res) : ?array
    {
        return array_merge($res, [
            'hosts' => $data['domain:host'] ?? null,
        ]);
    }

    protected function parse_contact(array $data, array $res) : ?array
    {
        foreach ($data['domain:contact'] as $key => $value) {
            if (strpos($key, '_attr') !== false) {
                continue;
            }

            $res[$data['domain:contact']["{$key}_attr"]['type']] = $value;
        }

        return $res;
    }
}

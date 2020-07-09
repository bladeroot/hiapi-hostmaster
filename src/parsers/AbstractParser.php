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

class AbstractParser
{
    /** @var string */
    protected $object = null;

    /** @var array */
    protected $stringField = [
        'roid',
        'id',
        'name',
        'clID',
        'crID',
        'upID',
        'trID',
        'reID',
        'acID',
        'crDate',
        'exDate',
        'upDate',
        'reDate',
        'trDate',
        'acDate',
        'registrant',
        'trStatus',
    ];

    /** @var array */
    protected $successCodes = ['1000', '1001', '1300', '1301', '1500'];

    public function parse(string $func, array $d = null, array $res = [])
    {
        if (empty($d)) {
            return $res;
        }

        return call_user_func_array([$this, "parse_{$func}"], [$d, $res]);
    }

    public function parse_epp(array $data) : array
    {
        return empty($data['response'])
            ? $this->parse_greeting($data)
            : $this->parse_response($data['response']);
    }

    protected function parse_response(array $data)
    {
        foreach (['result', 'trID', 'extension', 'resData', 'msgQ'] as $key) {
            if (empty($data[$key])) {
                continue;
            }

            $res = call_user_func_array([$this, "parse_{$key}"], [in_array($key, ['result', 'msgQ'], true) ? $data : $data[$key], $res]);
        }

        return $res;
    }

    protected function parse_result(array $data, array $res = null) : array
    {
        return array_merge($res ?? [], array_filter([
            'code' => $data['result_attr']['code'] ?? '1000',
            'msg' => $data['result']['msg'] ?? null,
        ]));
    }

    protected function parse_trID(array $data, array $res = [])
    {
        return array_merge($res, $data);
    }

    protected function parse_resData(array $data, array $res = [])
    {
        return $this->parse_object($data, $res);
    }

    protected function parse_extension(array $data, array $res = [])
    {
        return $this->parse_object($data, $res);
    }

    protected function parse_object(array $data, array $res = [])
    {
        if (empty($data)) {
            return $res;
        }

        foreach ($data as $key => $d) {
            if (strpos($key, '_attr') !== false) {
                continue;
            }

            [$obj, $func] = explode(":", $key);
            $res = call_user_func_array([ParserFactory::getParser($obj), "parse"], [$func, $d, $res]);
        }

        return $res;
    }

    protected function parse_infData(array $data, array $res) : array
    {
        foreach ($data as $key => $d) {
            if (strpos($key, '_attr') !== false) {
                continue;
            }

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

    protected function parse_chkData(array $data, array $res)
    {
        foreach ($data as $cd) {
            $_res = $this->parse_cd($cd, []);
            $res[$this->object][$_res[$_res['object']]] = $_res;
        }

        return $res;
    }

    protected function parse_cd(array $data, array $res)
    {
        $name = $this->object === 'contact' ? 'id' : 'name';
        return [
            'object' => $name,
            $name => $data["{$this->object}:{$name}"],
            'avail' =>  $data["{$this->object}:{$name}_attr"]["avail"],
            'reason' => $data["{$this->object}:reason"] ?? null,
        ];
    }

    protected function parse_status(array $data, array $res) : array
    {
        if (isset($data["{$this->object}:status_attr"])) {
            $status = $data["{$this->object}:status_attr"]['s'];
            return array_merge($res, [
                'statuses' =>  [$status => $status],
            ]);
        }

        foreach ($data["{$this->object}:status"] as $key => $value) {
            if (strpos($key, '_attr') === false) {
                continue;
            }

            $statuses[$value['s']] = $value['s'];
        }
        return array_merge($res, [
            'statuses' => $statuses,
        ]);
    }

    protected function parse_authInfo(array $data, array $res) : ?array
    {
        $k1 = $this->object . ':' . 'authInfo';
        $k2 = $this->object . ':' . 'pw';
        return array_merge($res, [
            'password' => $data[$k1][$k2] ?? null,
        ]);
    }

    protected function parse_trnData (array $data, array $res) : ? array
    {
        return $this->parse_infData($data, array_merge(['class' => $this->object], $res));
    }
}

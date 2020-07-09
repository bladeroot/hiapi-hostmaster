<?php

/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster\requests;

class HostRequest extends AbstractRequest
{
    /** @var string $object */
    protected $object = 'host';

    public function create(array $data) : self
    {
        $this->init()->command('create');

        $ips = [];
        $subtags =array_merge([[
            'tag' => 'host:name',
            'value' => $data['host'],
            'attributes' => [],
        ]], $this->prepareIPS($data['ips']));

        $command = $this->appendElementWithSubtags($this->command, "host:create", $subtags, $this->namespaces[$this->object]);

        return $this;
    }

    public function update(array $data) : self
    {
        $this->init()->command('update');

        $command = $this->appendElementWithSubtags($this->command, "host:update", [
            [
                'tag' => 'host:name',
                'value' => $data['host'],
                'attributes' => [],
            ],
        ], $this->namespaces[$this->object]);

        if (!empty($data['chg'])) {
            $this->appendElementWithSubtags($command, "host:chg", [[
                'tag' => "host:name",
                'value' => $data['chg']['host'],
                'attributes' => [],
            ]]);
        }

        foreach (['rem', 'add'] as $action) {
            if (empty($data[$action])) {
                continue;
            }

            $subtags = [];

            foreach (['ip', 'status'] as $obj) {
                if ($data[$action][$obj]) {
                    continue;
                }

                $subtags[] = $obj === 'ip' ? $this->prepareIPS($data[$action]['ip']) : $this->prepareStatus($data[$action]['status']);
            }

            if (empty($subtags)) {
                $continue;
            }

            $this->appendElementWithSubtags($command, "host:{$action}", $subtags);

            return $this;
        }


        return $this;
    }

    protected function prepareIPS(array $data = null) : array
    {
        if (empty($data)) {
            return [];
        }


        foreach ($data as $_ips) {
            if (empty($_ips)) {
                continue ;
            }

            $ips = [];

            if (is_string($_ips)) {
                $_ips = [$_ips];
            }

            foreach ($_ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    continue;
                }

                $ips[] = [
                    'tag' => 'host:addr',
                    'value' => $ip,
                    'attributes' => ['type' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'v4' : 'v6'],
                ];
            }
        }

        return $ips;

        if (empty($ips)) {
            return [];
        }

        $_ips = $ips;
        $ips = [];
        foreach (['v4', 'v6'] as $type) {
            if (empty($_ips[$type])) {
                continue ;
            }

            foreach ($_ips[$type] as $ip) {
                $ips[] = [
                    'tag' => 'host:addr',
                    'value' => $ip,
                    'attributes' => $type,
                ];
            }
        }

        return $ips;


    }
}

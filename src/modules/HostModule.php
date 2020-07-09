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
use Exception;

class HostModule extends AbstractModule
{
    /** @var array $successCodes */
    protected $successCodes = [1000, 1001];

    protected $object = 'host';

    public function hostsCheck(array $rows) : array
    {
        return $this->_bulkCommand('hostCheck', $rows, 'host');
    }

    public function hostsExist(array $rows) : array
    {
        return $this->hostsCheck($rows);
    }

    public function hostCheck(array $row) : int
    {
        try {
            $res = $this->command('check', $row);
        } catch (Exception $e) {
            return 0;
        }

        return (int) $res['host'][$row['host']]['avail'];
    }

    public function hostExist(array $row) : int
    {
        return $this->hostCheck($row);
    }

    public function hostsInfo(array $rows) : array
    {
        return $this->_bulkCommand('hostInfo', $rows, 'host');
    }

    public function hostInfo($row) : array
    {
        $res = $this->command('info', $row);
        return [
            'host' => $res['name'],
            'ips' => implode(",", $res['ips'] ?? []),
            'ip' => implode(",", $res['ips'] ?? []),
            'statuses' => implode(",", $res['statuses'] ?? []),
            'roid' => $res['roid'],
        ];
    }

    public function hostsSet(array $rows) : array
    {
        return $this->_bulkCommand('hostSet', $rows, 'host');
    }

    public function hostSet($row)
    {
        return $this->tool->hostCheck($row)
            ? $this->hostCreate($row)
            : $this->hostUpdate($row);
    }

    public function hostsCreate($rows) : array
    {
        return $this->_bulkCommand('hostCreate', $rows, 'host');
    }

    public function hostCreate($row)
    {

        $res = $this->command('create', array_merge($row, [
            'ip' => $this->hostPrepareIps($row),
        ]));

        return $row;
    }

    public function hostsUpdate($rows)
    {
        return $this->_bulkCommand('hostUpdate', $rows, 'host');
    }

    public function hostUpdate($row)
    {
        $info = $this->tool->hostInfo($row);
        $oldIps = $this->hostPrepareIps($info);
        $newIps = $this->hostPrepareIps($row);

        return $this->command('update', [
            'host' => $row['host'],
            'rem' => [
                'ip' => $this->_validateData($oldIps, $newIps),
            ],
            'add' => [
                'ip' => $this->_validateData($newIps, $oldIps),
            ],
        ]);
    }

    public function hostsDelete($rows)
    {
        return $this->_bulkCommand('hostDelete', $rows, 'host');
    }

    public function hostDelete(array $row) : array
    {
        try {
            return $this->command('delete', $row);
        } catch (Exception $e) {
            if (empty($this->config['hostdomain'])) {
                throw new Exception($e->getMessage());
            }
        }

        return $this->tool->hostRename([
            'host' => $row['host'],
            'new_name' => "{$row['host']}.{$this->config['hostdomain']}",
        ]);
    }

    public function hostsRename(array $rows) : array
    {
        return $this->_bulkCommand('hostRename', $rows, 'host');
    }

    public function hostRename(array $row) : array
    {
        return $this->command('update', [
            'host' => $row['host'],
            'chg' => [
                'host' => $row['new_host'],
            ],
        ]);
    }

    public function hostsEnableLock(array $rows) : array
    {
        return $this->_bulkCommand('hostEnableLock', $rows, 'host');
    }

    public function hostEnableLock(array $row) : array
    {
        return $this->hostSetStatuses($row, 'add');
    }

    public function hostsDisableLock(array $rows) : array
    {
        return $this->_bulkCommand('hostDisableLock', $rows, 'host');
    }

    public function hostDisableLock(array $row) : array
    {
        return $this->hostSetStatuses($row, 'rem');
    }

    protected function hostSetStatuses(array $row, string $action = 'add') : array
    {
        $info = $this->tool->hostInfo($row);

        foreach (['clientDeleteProhibited', 'clientUpdateProhibited'] as $status) {
            if ($action === 'add' && !strpos($info['statuses'], 'clientDeleteProhibited')) {
                $statuses[] = $status;
            } else if ($action === 'rem' && strpos($info['statuses'], 'clientDeleteProhibited')) {
                $statuses[] = $status;
            }
        }

        if (empty($statuses)) {
            return $row;
        }

        $this->command('update', [
            'host' => $row['host'],
            $action => [
               'status' => $statuses,
            ],
        ]);

        return $row;
    }

    protected function hostPrepareIps(array $row) : ?array
    {
        $ips = $row['ips'] ?? ($row['ip'] ?? '');
        $ips = is_string($ips) ? explode(",", $ips) : $ips;

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $_ips[] = $ip;
            }
        }

        return $_ips ?? [];
    }
}

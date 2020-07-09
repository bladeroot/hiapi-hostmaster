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

class DomainModule extends AbstractModule
{
    /** @var array $successCodes */
    protected $successCodes = [1000, 1001];

    /** @var string $object */
    protected $object = 'domain';

    public function domainsCheck(array $jrows) : array
    {
        foreach ($jrows['domains'] as $domain) {
            try {
                $res[$domain] = $this->tool->domainCheck(['domain' => $domain]);
            } catch (\Throwable $e) {
                $res[$domain] = [
                    'avail' => 0,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return $res;
    }

    public function domainCheck(array $row) : array
    {
        try {
            $res = $this->command('check', $row);
        } catch (Exception $e) {
            return [
                'avail' => 0,
                'reason' => $e->getMessage(),
            ];
        }

        if (!in_array((int) $res['code'], [1000, 1001])) {
            return [
                'avail' => 0,
                'reason' => $res['msg'],
            ];
        }

        return $res['domain'][$row['domain']];
    }

    public function domainsInfo(array $rows) : array
    {
        return $this->_bulkCommand('domainInfo', $rows);
    }

    public function domainInfo(array $row) : array
    {
        $rc = $this->command('info', $row);
        if (!in_array((int) $rc['code'], $this->successCodes)) {
            throw new Exception($rc['msg']);
        }

        return array_filter([
            'domain' => $rc['name'],
            'nameservers' => empty($rc["nameservers"]) ? '': (is_array($rc["nameservers"]) ? implode(",", $rc["nameservers"]) : $rc["nameservers"]),
            'registrant' => $rc["registrant"] ?? null,
            'created_date' => date("Y-m-d H:i:s", strtotime($rc['crDate'])),
            'created_by' => $rc['crID'],
            'expiration_date' => date("Y-m-d H:i:s", strtotime($rc['exDate'])),
            'registrar' => $rc['clID'],
            'password' =>  $rc['password'] ?? null,
            'hosts' => empty($rc['hosts']) ? null : (is_array($rc["hosts"]) ? implode(',', $rc['hosts']) : $rc['hosts']),
            'admin' => $rc["admin"] ?? null,
            'billing' => $rc['billing'] ?? null,
            'tech' => $rc['tech'] ?? null,
            'statuses' => empty($rc['statuses']) ? null : (is_array($rc["statuses"]) ? implode(',', $rc['statuses']) : $rc['statuses']),
        ]);
    }

    public function domainsGetInfo(array $rows) : array
    {
        return $this->domainsInfo($rows);
    }

    public function domainsLoadInfo(array $rows)
    {
        return true;
    }

    public function domainsRegister(array $rows) : array
    {
        return $this->_bulkCommand('domainCreate', $rows);
    }

    public function domainRegister(array $row) : array
    {
        return $this->domainCreate($row);
    }

    public function domainsCreate(array $rows) : array
    {
        return $this->_bulkCommand('domainCreate', $rows);
    }

    public function domainCreate(array $row) : array
    {
        $this->_prepareData($row);

        return $row;
    }

    public function domainsRenew(array $rows) : array
    {
        return $this->_bulkCommand('domainRenew', $rows);
    }

    public function domainRenew(array $row) : array
    {
        return $this->command('renew', $row);
    }

    public function domainsDelete(array $rows) : array
    {
        return $this->_bulkCommand('domainDelete', $rows);
    }

    public function domainDelete(array $row) : array
    {
        return $this->command('delete', $row);
    }

    public function domainsRestore(array $rows) : array
    {
        return $this->_bulkCommand('domainRestore', $rows);
    }

    public function domainRestore(array $row) : array
    {
        return $this->command('update', array_filter([
            'domain' => $row['domain'],
            'extensions' => [
                'rgp' => [
                    'op' => 'request',
                ],
            ],
        ]));
    }

    public function domainCheckTransfer(array $row)
    {
        $info = $this->tool->domainInfo($row);
        if (!$info['password'] || $row['password']!=$info['password']) {
            throw new Exception('Invalid authorization information');
        }

        if (strpos($info['statuses'],'TransferProhibited') !== false) {
            throw new Exception("Object status prohibits operation");
        }

        if (strpos($info['statuses'],'pendingTransfer') !== false) {
            throw new Exception("Object pending transfer");
        }

        return $row;
    }

    public function domainsTransfer($rows)
    {
        return $this->_bulkCommand('domainTransfer', $rows);
    }

    public function domainTransfer(array $row, string $op = 'request')
    {
        return$this->command('transfer', array_merge($row, [
            'op' => $row['command'] ?? $op,
        ]));
    }

    public function domainApproveTransfer($row)
    {
        return $this->tool->domainTransfer($row, 'approve');
    }

    public function domainCancelTransfer($row)
    {
        return $this->tool->domainTransfer($row, 'cancel');
    }

    public function domainRejectTransfer($row)
    {
        return $this->tool->domainTransfer($row, 'reject');
    }

    public function domainRequestTransfer($row)
    {
        return $this->tool->domainTransfer($row, 'request');
    }

    public function domainQueryTransfer($row)
    {
        return $this->tool->domainTransfer($row, 'query');
    }

    public function domainApprovePreincoming($row)
    {
        return $this->tool->domainRequestTransfer($row);
    }

    public function domainsSetNss(array $rows) : array
    {
        return $this->_bulkCommand('domainSetNss', $rows);
    }

    public function domainSetNss(array $row) : array
    {
    }

    public function domainsSetContacts(array $rows) : array
    {

    }

    public function domainSetContacts(array $row) : array
    {
    }

    public function domainsSaveContacts(array $rows) : array
    {
    }

    public function domainSaveContacts(array $row) : array
    {
    }

    public function domainsSetPassword(array $rows) : array
    {
        return $this->_bulkCommand('domainSetPassword', $rows);
    }

    public function domainSetPassword(array $row) : array
    {
        $this->domainDisableUpdate($row);
        $this->_update([
            'domain' => $row['domain'],
            'chg' => [
                'password' => $row['password'],
            ],
        ]);

        return $row;
    }

    public function domainsEnableLock(array $rows) : array
    {
        return $this->_bulkCommand('domainEnableLock', $rows);
    }

    public function domainEnableLock(array $row) : array
    {
        return $this->_setStatus($row, ['clientTransferProhibited', 'clientDeleteProhibited'], 'add');
    }

    public function domainsEnableHold(array $rows) : array
    {
        return $this->_bulkCommand('domainEnableHold', $rows);
    }

    public function domainEnableHold(array $row) : array
    {
        return $this->_setStatus($row, ['clientHold'], 'add');
    }

    public function domainsDisableLock(array $rows) : array
    {
        return $this->_bulkCommand('domainDisableLock', $rows);
    }

    public function domainDisableLock(array $row) : array
    {
        return $this->_setStatus($row, ['clientTransferProhibited', 'clientDeleteProhibited'], 'rem');
    }

    public function domainsDisableHold(array $rows) : array
    {
        return $this->_bulkCommand('domainDisableHold', $rows);
    }

    public function domainDisableHold(array $row) : array
    {
        $this->_setStatus($row, ['clientHold'], 'rem');
    }

    public function domainsDisableUpdate(array $rows) : array
    {
        return $this->_bulkCommand('domainDisableUpdate', $rows);
    }

    public function domainDisableUpdate(array $row) : array
    {
        if ($this->_checkStatus($row)) {
            $this->_setStatus($row, ['clientUpdateProhibited'], 'rem');
        }

        return $row;
    }

    protected function _update(array $row) : array
    {
        return $this->command('update', $row);
    }

    protected function _setStatus(array $row, array $statuses, string $op) : array
    {
        return $this->_update([
            'domain' => $row['domain'],
            $op => [
                'statuses' => $statuses,
            ],
        ]);
    }

    protected function _checkStatus(array $row) : bool
    {
        $info = $this->domainInfo($row);

        if (strpos($info['statuses'], 'serverUpdateProhibited') !== false) {
            throw new Exception('Server prohibit operation');
        }

        if (strpos($info['statuses'], 'clientUpdateProhibited') !== false) {
            return true;
        }

        return false;
    }

    protected function _prepareData($row) : self
    {
        return $this
            ->_prepareContacts($row, $row['contacts'])
            ->_prepareNSs($row['nss']);
    }

    protected function _prepareContacts(array $row, array $contacts = null) : self
    {
        $saved = [];
        foreach ($this->tool->getContactTypes() as $type) {
            if (!empty($saved[$contacts["{$type}_eppid"]])) {
                continue ;
            }

            $contact = array_merge([
                'whois_protected' => $row['whois_protected'],
            ], $contacts[$type], [
                'epp_id' => $this->_fixContactID($contacts["{$type}_eppid"]),
            ]);

            if ($this->tool->contactCheck($contact)) {
                var_dump($this->tool->contactCheck($contact));
                var_dump("123");
            } else {
                var_dump("234");
            }
        }
        die();
        throw new Exception("TEST");

        return $this;
    }

    protected function _prepareNSs(array $nss = []) : self
    {
        return $this;
    }
}

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

class EppModule extends AbstractModule
{
    const POLL_QUEUE_EMPTY = 1300;
    const POLL_QUEUE_FULL = 1301;
    /** @var array $successCodes */
    protected $successCodes = [1000, 1300, 1301, 1500];

    /** @var string */
    protected $object = 'epp';

    protected function balance($poll = [])
    {
        if (isset($this->contract) && in_array('balance', $this->extensions, true)) {
            $rc = $this->operation->balance(['contract' => $this->contract]);
            if (!$rc) {
                return $poll;
            }

            if ($this->_isError($rc['result_attr']["code"])) {
                return $poll;
            }

            $balance = (double) $rc['resData']['infData']['balance'];
            if ($this->balancelimit > $balance) {
                if (!$this->base->dbc->value("
                    SELECT      obj_id
                    FROM        poll
                    WHERE       request_client = '{$this->registrator}'
                        AND     time + '1 day'::interval > now()
                        AND     type_id = type_id('poll,lowbalance')
                ") ) {
                    $poll['balance'] = [
                        'type'  => 'lowbalance',
                        'request_client' => $this->registrator,
                        'message' => "Low balance. {$this->registrator} On registrator's balance left {$balance}, minimum - {$this->balancelimit} ",
                    ];
                    $this->base->emailByTemplate('registrator_notify_low_balance', [
                        'seller_id' => $this->base->dbc->valueCall('client_id', ['ahnames']),
                        'to' => 'nika@advancedhosters.com,andre@ahnames.com',
                        'registrator' => $this->registrator,
                        'balance' => $balance,
                        'currency' => $this->balanceCurrency,
                        'language' => 'ru',
                    ]);
                }
            }
        }

        return $poll ? : true;
    }

    public function pollsGetNew ($jrow)
    {
        $polls = [];
        $rc = $this->pollReq();
        while ((int) $rc['code'] === self::POLL_QUEUE_FULL) {
            $poll = $this->_pollPostEvent($rc);
            $this->pollAck($poll);
            $polls[$poll['id']] = $poll;
            $rc = $this->pollReq();
        }

        return empty($polls) ? true : $polls;

        $poll = $this->balance($poll);
        return $poll ? : true;
    }

    protected function pollAck(array $data = null)
    {
        if (empty($data)) {
            return ;
        }

        return $this->poll([
            'op' => 'ack',
            'msgID' => $data['id'],
        ]);
    }

    protected function pollReq(array $data = null) : array
    {
        return $this->poll(['op' => 'req']);
    }

    protected function poll($data)
    {
        try {
            return $this->command('poll', $data);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function eppHello(array $row = null)
    {
        return $this->command('hello', []);
    }

    public function eppLogin(array $row = [])
    {
        return $this->command('login', []);
    }

    public function eppLogout()
    {
        $this->successCodes = [1500];
        return $this->command('logout', (string) $this->request->logout());
    }

    protected function _pollPostEvent($data) : ?array
    {
        return [
            'id' => $data['msgID'],
            'class' => $data['class'],
            'name' => $data['name'],
            'type' => $data['trStatus'] ?? array_shift(explode(",", $data['statuses'])),
            'request_client' => $data['reID'],
            'request_date' => date("Y-m-d H:i:s", strtotime($data['reDate'] ?? $data['upDate'])),
            'action_client' => $data['acID'],
            'action_date' => date("Y-m-d H:i:s", strtotime($data['acDate'] ?? $data['upDate'])),
            'remoteid' => $data['msgID'],
            'message' => $data['msgMSG'],
            'outgoing' => $data['reID'] !== $this->config['registrator'],
        ];
    }
}

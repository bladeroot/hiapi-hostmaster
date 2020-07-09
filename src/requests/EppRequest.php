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

class EppRequest extends AbstractRequest
{
    /** @var string $language */
    protected $language = 'en';

    public function hello(array $row = []) : self
    {
        $this->init();
        $this->appendElement($this->epp, 'hello');
        return $this;
    }

    public function login(array $row = []) : self
    {
        $this->init()->command('login');
        foreach (['clID', 'pw', 'newPW'] as $key) {
            if (!empty($row[$key])) {
                $this->appendElement($this->command, $key, $row[$key]);
            }
        }

        $options = $this->appendElement($this->command, 'options');
        foreach (['version','lang'] as $key) {
            $row['svcMenu'][$key] = $key === 'lang' ? 'en' : $row['svcMenu'][$key];
            $this->appendElement($options, $key, $row['svcMenu'][$key]);
        }

        $svcs = $this->appendElement($this->command, 'svcs');
        foreach ($row['svcMenu']['objURI'] as $objURI) {
            $this->appendElement($svcs, 'objURI', $objURI);
        }

        if (empty($row['svcMenu']['svcExtension'])) {
            return $this;
        }

        $svcExtension = $this->appendElement($svcs, 'svcExtension');

        foreach ($row['svcMenu']['svcExtension']['extURI'] as $extURI) {
            $this->appendElement($svcExtension, 'extURI', $extURI);
        }

        return $this;
    }

    public function logout(array $row = []) : self
    {
        return $this->init()->command('logout');
    }

    public function poll(array $row = []) : self
    {
        $data = array_filter([
            'op' => $row['op'] ?? 'req',
            'msgID' => $row['op'] === 'ack' && $row['msgID'] ? $row['msgID'] : null,
        ]);

        return $this->init()->command('poll', $data);
    }

    public function balance(array $row = []) : self
    {
        return $this->info(['balance' => $row['contract']], [
            'object' => 'balance',
            'objectName' => 'contract',
        ]);
    }
}

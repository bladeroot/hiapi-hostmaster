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

class EppRequest extends AbstractObjectRequest
{
    protected $object = 'epp';

    /** @var string $language */
    protected $language = 'en';

    public function hello(array $row = []) : self
    {
        $this->appendElement($this->epp, 'hello');
        return $this;
    }

    public function login(array $row = []) : self
    {
        $this->command('login');
        foreach (['clID', 'pw', 'newPW'] as $key) {
            if (!empty($row[$key])) {
                $this->appendElement($this->command, $key, $row[$key]);
            }
        }

        $options = $this->appendElement($this->command, 'options');
        foreach (['version','lang'] as $key) {
            $row[$key] = $key === 'lang' ? 'en' : $row[$key];
            $this->appendElement($options, $key, $row[$key]);
        }

        $svcs = $this->appendElement($this->command, 'svcs');
        foreach ($row['objURI'] as $objURI) {
            $this->appendElement($svcs, 'objURI', $objURI);
        }

        if (empty($row['extURI'])) {
            return $this;
        }

        $svcExtension = $this->appendElement($svcs, 'svcExtension');

        foreach ($row['extURI'] as $extURI) {
            $this->appendElement($svcExtension, 'extURI', $extURI);
        }

        return $this;
    }

    public function logout(array $row = []) : self
    {
        return $this->command('logout');
    }

    public function poll(array $row = []) : self
    {
        $data = array_filter([
            'op' => $row['op'] ?? 'req',
            'msgID' => $row['op'] === 'ack' && $row['msgID'] ? $row['msgID'] : null,
        ]);

        return $this->command('poll', $data);
    }

    public function balance(array $row = []) : self
    {
        return $this->info(['balance' => $row['contract']], [
            'object' => 'balance',
            'objectName' => 'contract',
        ]);
    }
}

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

class DomainRequest extends AbstractRequest
{
    /** @var string $object */
    protected $object = 'domain';

    public function create(array $data) : self
    {
        $this->init()->command('create', [], $data['extensions']);
        $command = $this->appendElement($this->command, 'domain:create', null, $this->namespaces['domain']);

        $this->appendElement($command, 'domain:name', $data['domain']);
        $this->appendElement($command, 'domain:period', $data['period'] ?? 1, ['unit' => $data['unit'] ?? 'y']);

        $this->addHost($command, 'domain:ns', $data['host']);

        if (!empty($data['registrant'])) {
            $this->appendElement($command, 'domain:registrant', $data['registrant']);
        }

        if (!empty($data['contact'])) {
            foreach ($data['contact'] as $type => $contacts) {
                if (empty($contacts)) {
                    continue;
                }

                if (is_string($contacts)) {
                    $this->appendElement($command, 'domain:contact', $contacts, ['type' => $type]);
                    continue;
                }

                foreach ($contacts as $contact) {
                    if (empty($contact)) {
                        continue;
                    }

                    $this->appendElement($command, 'domain:contact', $contact, ['type' => $type]);
                }
            }
        }

        $this->addExtensions($data['extensions']);

        return $this;
    }

    public function renew(array $data)
    {
        $this->init()->command('renew', [], $data['extensions']);

        $command = $this->appendElement($this->command, 'domain:renew', null, $this->namespaces['domain']);
        $this->appendElement($command, 'domain:name', $data['domain']);
        $this->appendElement($command, 'domain:curExpDate', $data['expires']);
        $this->appendElement($command, 'domain:period', $data['period']);

        $this->addExtensions($data['extensions']);

        return $this;
    }

    public function update(array $data)
    {
        $this->init()->command('update', [], $data['extensions']);
        $command = $this->appendElement($this->command, 'domain:update', null, $this->namespaces['domain']);

        $this->appendElement($command, 'domain:name', $data['domain']);

        foreach (['add', 'rem', 'chg'] as $part) {
            foreach (['host','contacts','status', 'password'] as $v) {
                if (is_null($data[$part][$v]) || empty($data[$part][$v])) {
                    unset($data[$part][$v]);
                }
            }
            if (empty($data[$part])) {
                unset($data[$part]);
            }
        }

        foreach (['add', 'rem'] as $part) {
            if (empty($data[$part])) {
                continue ;
            }

            foreach (['host', 'contact', 'status'] as $v) {
                if (empty($data[$part][$v])) {
                    continue ;
                }

                $elem = call_user_func([$this, "add" . ucfirst($v)], [$command, "domain:{$part}", $data[$part][$v]]);
            }
        }

        $this->addChange($command, $data['chg']);

        $this->addExtensions($data['extensions']);

        return $this;
    }

    public function restore(array $data)
    {
        $this->init()->command('update', [], $data['extensions']);
        $command = $this->appendElement($this->command, 'domain:update', null, $this->namespaces['domain']);
        $this->appendElement($command, 'domain:name', $data['domain']);

        $this->addExtensions($data['extensions']);

        return $this;
    }

    protected function addHost(DOMElement $command, string $tag, array $data = []) : self
    {
        if (empty($data)) {
            return $this;
        }

        foreach ($data as $host) {
            $subtags[] = [
                'tag' => "domain:hostObj",
                'value' => $data['host'],
                'attributes' => [],
            ];
        }

        $this->appendElementWithSubtags($command, $tag, $subtags);

        return $this;
    }

    protected function addContact(DOMElement $command, string $tag, array $data = []) : self
    {
        if (empty($data)) {
            return $this;
        }

        foreach ($data as $type => $contact) {
            $subtags[] = [
                'tag' => "domain:contact",
                'value' => $contact,
                'attributes' => [
                    'type' => $type,
                ],
            ];
        }

        $this->appendElementWithSubtags($command, $tag, $subtags);

        return $this;
    }

    protected function addChange(DOMElement $command, array $data = [])
    {
        if (empty($data)) {
            return $this;
        }

        $elem = $this->appendChild($command, "domain:chg");

        if ($data['registrant']) {
            $this->appendElement($elem, 'domain:registrant', $data['registrant']);
        }

        $this->addAuthInfo($elem, 'domain', $data['password'], true);

        return $this;
    }
}

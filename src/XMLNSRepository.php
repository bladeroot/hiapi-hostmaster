<?php
/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster;

use hiapi\hostmaster\requests\EppRequest;
use hiapi\hostmaster\requests\DomainRequest;
use hiapi\hostmaster\requests\HostRequest;
use hiapi\hostmaster\requests\ContactRequest;

class XMLNSRepository
{
    protected $objects = [
        'epp' => [
            'urn:ietf:params:xml:ns:epp-1.0' => EppRequest::class,
        ],
        'domain' => [
            'urn:ietf:params:xml:ns:domain-1.0' => DomainRequest::class,
            'http://hostmaster.ua/epp/domain-1.1' => DomainRequest::class,
        ],
        'host' => [
            'urn:ietf:params:xml:ns:host-1.0' => HostRequest::class,
            'http://hostmaster.ua/epp/host-1.1' => HostRequest::class,
        ],
        'contact' => [
            'urn:ietf:params:xml:ns:contact-1.0' => ContactRequest::class,
            'http://hostmaster.ua/epp/contact-1.1' => ContactRequest::class,
        ],
        'balance' => [
            'http://hostmaster.ua/epp/balance-1.0' => EppRequest::class,
        ],
    ];

    protected $xmlns;

    public function __construct(array $uris = null)
    {
        foreach ($this->objects as $object => $xmlnses) {
            foreach ($xmlnses as $xmlns => $class) {
                if (in_array($xmlns, $uris, true)) {
                    $this->add($object, $xmlns);
                    break;
                }
            }
        }
    }

    public function add(string $object, string $xmlns) : self
    {
        if (!empty($this->objects[$object][$xmlns])) {
            $this->xmlns[$object] = $xmlns;
        }

        return $this;
    }

    public function delete(string $object) : self
    {
        unset($this->xmlns[$object]);
        unset($this->requests[$object]);
        return $this;
    }

    public function get(string $object) : ?string
    {
        return $this->xmlns[$object] ?? null;
    }

    public function getAll() : ?array
    {
        return $this->xmlns;
    }

    public function getRequest(string $object)
    {
        $xmlns = $this->get($object);
        if (empty($xmlns)) {
            return null;
        }

        $object = new $this->objects[$object][$xmlns]();
        return $object->setRepository($this)->init();
    }
}

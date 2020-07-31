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

use DOMDocument;
use DOMElement;

class AbstractObjectRequest extends AbstractRequest
{
    /** @var string $objectName */
    protected $objectName = 'name';

    public function init()
    {
        $this->epp = $this->appendElement($this, 'epp', null, [ 'xmlns' => $this->repository->get('epp')]);
        return $this;
    }

    public function getObject()
    {
        return $this->object;
    }

    public function command(string $command = null, array $attributes = [], $extensions = null) : ?self
    {
        $com = $this->appendElement($this->epp, 'command', null);

        $this->command = $this->appendElement($com, $command, null, $attributes);

        $this->appendElement($com, 'clTRID', $this->getClientTRID());
        return $this;
    }

    /**
     * Create request identificator
     *
     * @param string
     * @return string
     */
    public function getClientTRID(string $clID = null) : string
    {
        $clID = $clID ?? $this->clID ?? uniqid();
        return "DRID-". htmlspecialchars(strtoupper($clID))."-". time();
    }

    /**
     * Create XML for check object
     *
     * @param array
     * return AbstractRequest
    */
    public function check(array $data) : self
    {
        $this->command('check');

        foreach ($data as $object) {
            $subtags[] = [
                'tag' => "{$this->object}:{$this->objectName}",
                'value' => $object,
                'attributes' => [],
            ];
        }

        $this->appendElementWithSubtags($this->command, "{$this->object}:check", $subtags, ["xmlns:{$this->object}" => $this->repository->get($this->object)]);

        return $this;
    }

    /**
     * Create XML for info object
     *
     * @param array
     * return AbstractRequest
     */
    public function info(array $data, array $replaces = []) : self
    {
        $this->command('info');
        $object = $replaces['object'] ?? $this->object;
        $objectName = $replaces['objectName'] ?? $this->objectName;
        $command = $this->appendElementWithSubtags($this->command, "{$object}:info", [[
            'tag' => "{$object}:{$objectName}",
            'value' => $data[$object],
            'attributes' => [],
        ]], ["xmlns:{$object}" => $this->repository->get($object)]);
        $this->addAuthInfo($command, $object, $data['password']);

        return $this;
    }

    /**
     * Create XML for delete object
     *
     * @param array
     * return AbstractRequest
     */
    public function delete(array $data) : self
    {
        $this->command('delete');
        $this->appendElementWithSubtags($this->command, "{$this->object}:delete", [[
            'tag' => "{$this->object}:{$this->objectName}",
            'value' => $data[$this->object],
            'attributes' => [],
        ]], ["xmlns:{$this->object}" => $this->repository->get($this->object)]);

        return $this;
    }

    /**
     * Create XML for transfer object
     *
     * @param array
     * return AbstractRequest
     */
    public function transfer(array $data) : self
    {
        $this->command('transfer', ['op' => $data['op'] ?? 'request']);
        $command = $this->appendElementWithSubtags($this->command, "{$this->object}:transfer", [[
            'tag' => "{$this->object}:{$this->objectName}",
            'value' => $data[$this->object],
            'attributes' => [],
        ]], ["xmlns:{$this->object}" => $this->repository->get($this->object)]);

        if (!empty($data['password']) && in_array($data['op'], ['request', 'query'])) {
            $this->addAuthInfo($command, $this->object, $data['password']);
        }

        return $this;
    }

    protected function addAuthInfo(
            DOMElement $element,
            string $object,
            string $password = null,
            $nullAvailable = false) : self
    {
        if (empty($password) && !$nullAvailable) {
            return $this;
        }

        if (empty($password) && $nullAvailable) {
            $this->appendElementWithSubtags($element, "{$object}:authInfo", [[
                'tag' => "{$object}:null",
                'value' => null,
                'attributes' => [],
            ]]);

            return $this;
        }

        $this->appendElementWithSubtags($element, "{$object}:authInfo", [[
            'tag' => "{$object}:pw",
            'value' => $password,
            'attributes' => [],
        ]]);

        return $this;
    }

    protected function addStatus(DOMElement $command, string $tag, array $data)
    {
        $subtags = $this->prepareStatus($data);
        $this->appendElementWithSubtags($command, $tag, $subtags);

        return $this;
    }

    protected function prepareStatus(array $data, array $tags = []) : array
    {
        foreach ($data as $status) {
            $tags[] = [
                'tag' => "{$this->object}:status",
                'value' => null,
                'attributes' => [
                    's' => $status,
                ],
            ];
        }

        return $tags ?? [];
    }
}


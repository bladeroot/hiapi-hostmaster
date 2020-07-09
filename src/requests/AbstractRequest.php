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

class AbstractRequest
{
    /** @var array $namespace */
    protected $namespace = [];

    /** @var DOMDocument $xml **/
    public $xml = null;

    /** @var DOMElement $epp **/
    protected $epp = null;

    /** @var DOMElement $command **/
    protected $command = null;

    /** @var DOMElement $extensions */
    protected $extensions;

    /** @var string $clID */
    protected $clID;

    /** @var string $object */
    protected $object = 'domain';

    /** @var string $objectName */
    protected $objectName = 'name';

    public function __construct(array $namespaces, string $clID)
    {
        $this->namespaces = $namespaces;
        $this->clID = $clID;
        $this->init();
    }

    public function __toString()
    {
        return $this->xml->saveXML();
    }

    public function init($standalone = 'no')
    {
        foreach (['xml', 'epp', 'command'] as $key) {
            $this->{$key} = null;
        }

        $this->xml = new DOMDocument('1.0', 'utf-8');
        $this->xml->standalone = 'no';
        $this->xml->formatOutput = true;
        $this->epp = $this->setNSAttributes($this->xml->createElement('epp'), 'epp');
        $this->epp = $this->xml->appendChild($this->epp);
        return $this;
    }

    public function command(string $command = null, array $attributes = [], $extensions = null) : ?self
    {
        $com = $this->xml->createElement('command');
        $this->epp = $this->epp->appendChild($com);
        $com = $this->setAttributes($this->xml->createElement($command), $attributes);
        $this->command = $this->epp->appendChild($com);

        if ($extensions) {
            $extensions = $this->xml->createElement('extension');
            $this->extensions = $extensions ? $this->epp->appendChild($extensions) : null;
        }

        $trid = $this->xml->createElement('clTRID', $this->getClientTRID());
        $this->epp->appendChild($trid);

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
        $clID = $clID ? : $this->clID;
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
        $this->init()->command('check');

        foreach ($data as $object) {
            $subtags[] = [
                'tag' => "{$this->object}:{$this->objectName}",
                'value' => $object,
                'attributes' => [],
            ];
        }

        $this->appendElementWithSubtags($this->command, "{$this->object}:check", $subtags, $this->namespaces[$this->object]);

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
        $this->init()->command('info');
        $object = $replaces['object'] ?? $this->object;
        $objectName = $replaces['objectName'] ?? $this->objectName;
        $command = $this->appendElementWithSubtags($this->command, "{$object}:info", [[
            'tag' => "{$object}:{$objectName}",
            'value' => $data[$object],
            'attributes' => [],
        ]], $this->namespaces[$object]);
        $this->addAuthInfo($command, $object, $data['password']);

        $this->addExtensions($data['extensions']);

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
        $this->init()->command('delete', [], $data['extensions']);
        $this->appendElementWithSubtags($this->command, "{$this->object}:delete", [[
            'tag' => "{$this->object}:{$this->objectName}",
            'value' => $data[$this->object],
            'attributes' => [],
        ]], $this->namespaces[$this->object]);

        if (empty($data['extensions'])) {
            return $this;
        }

        $this->addExtensions($data['extensions']);
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
        $this->init()->command('transfer', ['op' => $data['op'] ?? 'request'], $data['extensions']);
        $command = $this->appendElementWithSubtags($this->command, "{$this->object}:transfer", [[
            'tag' => "{$this->object}:{$this->objectName}",
            'value' => $data[$this->object],
            'attributes' => [],
        ]], $this->namespaces[$this->object]);

        if (!empty($data['password']) && in_array($data['op'], ['request', 'query'])) {
            $this->addAuthInfo($command, $this->object, $data['password']);
        }

        if (in_array($data['op'], ['request'])) {
            $this->addExtensions($data['extensions']);
        }

        return $this;
    }

    protected function appendElementWithSubtags(DOMElement &$parent, string $tag, array $subtags = [], $attributes = []) : DOMElement
    {
        $element = $this->appendElement($parent, $tag, null, $attributes);

        foreach ($subtags as $subtag) {
            $this->appendElement($element, $subtag['tag'], $subtag['value'], $subtag['attributes']);
        }

        return $element;
    }

    protected function appendElement(DOMElement &$parent, string $tag, $value = null, $attributes = []) : DOMElement
    {
        $element = !is_array($value) ? $this->xml->createElement($tag, $value) : $this->xml->createElement($tag);
        $element = $this->setAttributes($element, $attributes);

        return $parent->appendChild($element);
    }

    protected function setNSAttributes(DOMElement $element, string $namespace) : DOMElement
    {
        if (empty($this->namespaces[$namespace])) {
            return $element;
        }

        return $this->setAttributes($element, $this->namespaces[$namespace]);
    }

    protected function setAttributes(DOMElement $element, array $attributes = []) : DOMElement
    {
        if (empty($attributes)) {
            return $element;
        }

        foreach ($attributes as $attr => $value) {
            $eppAttr = $this->xml->createAttribute($attr);
            $eppAttr->value = $value;
            $element->appendChild($eppAttr);
        }

        return $element;
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


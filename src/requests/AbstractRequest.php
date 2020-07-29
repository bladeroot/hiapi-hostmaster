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

class AbstractRequest extends DOMDocument
{
    /** @var DOMElement $epp **/
    protected $epp = null;

    /** @var string $object */
    protected $object = null;

    /** @var string $objectName */
    protected $objectName = null;

    public function getObject()
    {
        return $this->object;
    }

    public function __construct(string $version = '1.0', string $encoding = 'utf-8')
    {
        parent::__construct($version, $encoding);
        $this->standalone = 'no';
        $this->formatOutput = true;
    }

    public function setNamespace(string $xmlns) : self
    {
        $this->namespace = $xmlns;
        return $this;
    }

    public function setRepository($repository) : self
    {
        $this->repository = $repository;
        return $this;
    }

    public function init()
    {
        return $this;
    }

    public function __toString()
    {
        return $this->saveXML();
    }

    protected function appendElementWithSubtags(DOMElement &$parent, string $tag, array $subtags = [], $attributes = []) : DOMElement
    {
        $element = $this->appendElement($parent, $tag, null, $attributes);

        foreach ($subtags as $subtag) {
            $this->appendElement($element, $subtag['tag'], $subtag['value'], $subtag['attributes']);
        }

        return $element;
    }

    protected function appendElement(&$parent, string $tag, $value = null, $attributes = []) : DOMElement
    {
        try {
            $element = !is_array($value) && !is_null($value) ? $this->createElement($tag, $value) : $this->createElement($tag);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        $element = $this->setAttributes($element, $attributes ?? []);

        return $parent->appendChild($element);
    }

    protected function setAttributes(DOMElement $element, array $attributes = []) : DOMElement
    {
        if (empty($attributes)) {
            return $element;
        }

        foreach ($attributes as $attr => $value) {
            $eppAttr = $this->createAttribute($attr);
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
}


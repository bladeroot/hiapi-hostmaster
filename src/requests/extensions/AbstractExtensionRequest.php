<?php

/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster\requests\extensions;

use hiapi\hostmaster\requests\AbstractRequest;
use DOMDocument;
use DOMElement;

class AbstractExtensionRequest extends AbstractRequest
{
    protected $request;
    protected $extension;

    public function __construct()
    {
        $this->request = $request;
    }

    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    public function isApplyable() : bool
    {
        return false;
    }

    public function getExtensionElement() : ?DOMElement
    {
        $nodes = $this->request->getElementsByTagName('extension');
        if ($nodes->length > 0) {
            return $nodes->item(0);
        }

        return $this->createExtensionElement();
    }

    protected function createExtensionElement() : ?DOMElement
    {
        $clTRID = $this->request->getElementsByTagName('clTRID');
        if ($clTRID->length === 0) {
            return null;
        }

        $command = $this->request->getElementsByTagName('command');
        if ($command->length === 0) {
            return null;
        }

        $extension = $this->request->createElement('extension');
        return $command->item(0)->insertBefore($extension, $clTRID->item(0));
    }
}


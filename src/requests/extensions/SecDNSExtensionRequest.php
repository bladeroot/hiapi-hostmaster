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

class SecDNSExtensionRequest extends AbstractExtensionRequest
{
    public function isApplyable() : bool
    {
        return true;
    }

    public function request($data) : DOMDocument
    {
        if (!$this->isApplyable()) {
            return $this->request;
        }

        $extension = $this->getExtensionElement();

        if (empty($extension)) {
            return $this->request;
        }

        $this->request->appendElementWithSubtags($extension, 'rgp:update', [[
            'tag' => 'rgp:restore',
            'value' => null,
            'attributes' => ['op' => 'request'],
        ]], ['xmlns:rgp' => $this->repository->get('rgp')]);

        return $this->request;
    }

    public function report($data) : DOMDocument
    {
        if (!$this->isApplyable()) {
            return $this->request;
        }

        $extension = $this->getExtensionElement();

        if (empty($extension)) {
            return $this->request;
        }

        $command = $this->request->appendElementWithSubtags($extension, 'rgp:update', null, ['xmlns:rgp' => $this->repository->get('rgp')]);
        $restore = $this->request->appendElementWithSubtags($command, 'rgp:restore', null, ['op' => 'report']);

        $this->request->appendElementWithSubtags($restore, 'rgp:report', [
            [
                'tag' => 'rgp:preData',
                'value' => $data['preData'],
                'attributes' => null,
            ], [
                'tag' => 'rgp:postData',
                'value' => $data['postData'],
                'attributes' => null,
            ], [
                'tag' => 'rgp:delTime',
                'value' => $data['delTime'],
                'attributes' => null,
            ],[
                'tag' => 'rgp:resTime',
                'value' => $data['resTime'],
                'attributes' => null,
            ],[
                'tag' => 'rgp:resReason',
                'value' => $data['resReason'] ?? 'Registrant Error',
                'attributes' => null,
            ],[
                'tag' => 'rgp:statement',
                'value' => $data['statement1'] ?? 'This registrar has not restored the Registered Name in order to assume the rights to use or sell the Registered Name for itself or for any third party',
                'attributes' => null,
            ],[
                'tag' => 'rgp:statement',
                'value' => $data['statement2'] ?? 'The information in this report is true to best of this registrar\'s knowledge, and this registrar acknowledges that intentionally supplying false information in this report shall constitute an incurable material breach of the Registry-Registrar Agreement',
                'attributes' => null,
            ],
        ], []);

        return $this->request;
    }
}


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

class ContactRequest extends AbstractRequest
{
    /** @var string $object */
    protected $object = 'contact';

    /** @var string $objectName */
    protected $objectName = 'id';

    public function update(array $data)
    {
        $this->init()->command('update');

        return $this;
    }
}

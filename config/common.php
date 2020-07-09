<?php
/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

$definitions = [
    'hostmasterTool' => [
        '__class' => \hiapi\hostmaster\HostmasterTool::class,
    ],
];

return class_exists(Yiisoft\Factory\Definitions\Reference::class) ? $definitions : ['container' => ['definitions' => $definitions]];


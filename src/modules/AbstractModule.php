<?php
/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster\modules;

use hiapi\hostmaster\HostmasterTool;
use hiapi\hostmaster\helpers\XmlHelper;
use DOMDocument;
use Exception;

/**
 * General module functions.
 *
 * @author Yurii Myronchuk <bladeroot@gmail.com>
 */
class AbstractModule
{
    /** @const state */
    const STATE_OK = 'ok';

    const OBJECT_DOES_NOT_EXIST = 'Object does not exist';
    const REQUIRED_PARAMETR_MISSING = 'Required parametr missing';

    protected $tool;
    protected $base;
    protected $config;

    protected $object = 'epp';

    protected $successCodes = [1000, 1001, 1500];

    public function __construct(HostmasterTool $tool)
    {
        $this->tool = $tool;
        $this->base = $tool->getBase();
        $this->config = $tool->getConfig();
    }

    /**
     * Performs http GET request
     *
     * @param string $command
     * @param array $data
     * @param array|null $inputs
     * @param array|null $returns
     * @param array|null $auxData
     * @return array
     */
    protected function command(string $command, array $data = [])
    {
        $res = $this->tool->request($this->object, $command, $data);
        if (!in_array((int) $res['code'], $this->successCodes)) {
            throw new \Exception($res['msg']);
        }

        return $res;
    }

    protected function _bulkCommand(string $command, array $rows, string $key = null)
    {
        $key = $key === null ? $this->object : $key;
        foreach ($rows as $row) {
            try {
                $res[$row[$key]] = call_user_func([$this->tool, $command], $data);
            } catch (\Throwable $e) {
                $res[$row[$key]] = array_merge($row, [
                    '_error' => $e->getMessage(),
                ]);
            }
        }

        return $res;
    }

    protected function _validateData($param1 = [], $param2 = []) : array
    {
        $param1 = $this->_stringToArray($param1);
        $param2 = $this->_stringToArray($param2);

        return array_diff($param1, $param2);
    }

    protected function _stringToArray($str) : array
    {
        return is_string($str) ? [$str] : $str;
    }

    protected function _fixContactID(string $epp_id) : string
    {
        $epp_id = str_replace("_", "-", preg_replace('/[^a-z0-9-\s]/', '-',mb_strtolower($epp_id)));
        if (preg_match('/^[0-9-]/', $epp_id)) {
            throw new InvalidParamException('invalid first symbol at contact epp_id');
        }

        if (strlen($epp_id) > 16 ) {
            throw new InvalidParamException('contact epp_id is too long');
        }

        if (strlen($epp_id) < 3) {
            throw new InvalidParamException('contact epp_id is too short');
        }

        if (!preg_match('/^[a-z][a-z0-9-]{2,15}$/', $epp_id)) {
        }

        return $epp_id;
    }
}

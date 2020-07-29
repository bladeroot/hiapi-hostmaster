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

use hiapi\hostmaster\modules\AbstractModule;
use hiapi\hostmaster\modules\ContactModule;
use hiapi\hostmaster\modules\DomainModule;
use hiapi\hostmaster\modules\SecDNSModule;
use hiapi\hostmaster\modules\HostModule;
use hiapi\hostmaster\modules\EppModule;
use yii\base\InvalidCallException;
use yii\base\InvalidParamException;
use FilesystemIterator;
use Exception;

/**
 * Hostmaster EPP tool.
 *
 * https://epp.hostmaster.ua/
 * XXX looks obsolete :(
 *
 * @author Yurii Myronchuk <bladeroot@gmail.com>
 */
class HostmasterTool extends \hiapi\components\AbstractTool
{
    public $data = [];

    /* @var array $config include default values */
    protected $config = [
        'threads'       => 3,
        'url'           => 'test-epp.hostmaster.ua',
        'protocol'      => 'tls',
        'port'          => 700,
        'language'      => 'en',
        'registry'      => 'hostmaster',
        'services'      => ['domain', 'host', 'contact'],
        'extensions'    => ['rgp', 'uaepp', 'balance', 'secDNS'],
        'disabled'      => [
            'contact' => ['billing'],
        ],
        'namespaces'    => [
            'epp'           => [
                'xmlns'         => 'urn:ietf:params:xml:ns:epp-1.0',
            ],
            'domain'        => [
                'xmlns:domain'  => 'http://hostmaster.ua/epp/domain-1.1',
            ],
            'host'          => [
                'xmlns:host'    => 'http://hostmaster.ua/epp/host-1.1',
            ],
            'contact'       => [
                'xmlns:contact' => 'http://hostmaster.ua/epp/contact-1.1',
            ],
            'rgp'           => [
                'xmlns:rgp'     => 'http://hostmaster.ua/epp/rgp-1.1',
            ],
            'secDNS'        => [
                'xmlns:secDNS'  => 'http://hostmaster.ua/epp/secDNS-1.1',
            ],
            'uaepp'        => [
                'xmlns:uaepp'  => 'http://hostmaster.ua/epp/uaepp-1.1',
            ],
            'balance'       => [
                'xmlns:balance' => 'http://hostmaster.ua/epp/balance-1.0',
            ],
        ],
    ];
    /* @var string $language */
    protected $language = 'en';
    /* @var string $registry */
    protected $registry = 'hostmaster';
    /* @var string $protocol */
    protected $protocol = 'tls';
    /* @var string $url */
    protected $url = 'test-epp.hostmaster.ua';
    /* @var int $port */
    protected $port = 700;
    /* @var string $login */
    protected $login;
    /* @var string $password */
    protected $password;
    /* @var string $new_password */
    protected $new_password;
    /** @var string $contract */
    protected $contract;
    /* @var string $certificate */
    protected $cerificate;
    /* @var string $cacertificate */
    protected $cacerificate;
    /* @var EppClient eppClient */
    protected $eppClient = null;
    /** @var StreamSocketConnection $connection */
    protected $connection;
    /* @var array $modules */
    protected $modules = [
        'domain'    => DomainModule::class,
        'domains'   => DomainModule::class,
        'secdns'    => SecDNSModule::class,
        'secdnss'   => SecDNSModule::class,
        'contact'   => ContactModule::class,
        'contacts'  => ContactModule::class,
        'host'      => HostModule::class,
        'hosts'     => HostModule::class,
        'poll'      => EppModule::class,
        'polls'     => EppModule::class,
        'epp'       => EppModule::class,
    ];

    public function __construct($base, $data)
    {
        $d = $data['data'] ?? null;
        $data = array_merge($data, $d === null ? [] : json_decode($d, true));
        parent::__construct($base, $data);
        $this->setConfig($data);
    }

    public function __call($command, $args)
    {
        $parts = preg_split('/(?=[A-Z])/', $command);
        $entity = reset($parts);
        $module = $this->getModule($entity);

        return call_user_func_array([$module, $command], $args);
    }

    public function getModule($name)
    {
        if (empty($this->modules[$name])) {
            throw new InvalidCallException("module `$name` not found");
        }
        $module = $this->modules[$name];
        if (!is_object($module)) {
            $this->modules[$name] = $this->createModule($module);
        }

        return $this->modules[$name];
    }

    /**
     * This method is for testing purpose only
     *
     * @param string $name
     * @param AbstractModule $module
     * @throw InvalidCallException
     */
    public function setModule(string $name, AbstractModule $module): void
    {
        if (!key_exists($name, $this->modules)) {
            throw new InvalidCallException("module `$name` not found");
        }

        $this->modules[$name] = $module;
    }

    /**
     * Create module
     *
     * @param string||object
     * @return object
     */
    public function createModule($class) : AbstractModule
    {
        return new $class($this);
    }

    /**
     * Performs request with specified method
     * Direct usage is deprecated
     *
     * @param string $command
     * @param string $xml
     * @return array
     */
    public function request(string $class, string $command, array $data = []) : array
    {
        $eppClient = $this->getEppClient();
        if (in_array($command, ['login', 'hello'], true)) {
            return call_user_func_array([$eppClient, $command], [$class, $command, $data]);
        }

        if (!$this->getEppClient()->isLoggedIn() && !in_array($command, ['login', 'hello'], true)) {
            throw new \Exception('not logged in');
        }

        try {
            return call_user_func_array([$this->getEppClient(), 'command'], [array_merge(['command' => "{$class}:{$command}"], $data)]);
        } catch (\Throwable $e) {
            echo $e->getMessage();
            $this->deleteEppClient();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Get EppClient
     *
     * @param void
     * @return HttpClient
     */
    public function getEppClient(): EppClient
    {
        $connection = $this->getConnection();
        if (!($connection instanceof StreamSocketConnection)) {
            throw new RuntimeException('Could not create connection');
        }

        if (!($this->eppClient instanceof EppClient)) {
            $this->eppClient = EppClient::getClient($this, $connection);
        }

        return $this->eppClient;
    }

    /**
     * Set EppClient
     *
     * @param EppClient
     * @return self
     */
    public function setEppClient(EppClient $eppClient): self
    {
        unlink($this->config['pidfile']);
        $this->eppClient = $eppClient;
        return $this;
    }

    /**
     * Get StreamSocketConnection
     *
     * @param void
     * @return StreamSocketConnection
     * @threw RuntimeException
     */
    public function getConnection() : StreamSocketConnection
    {
        if ($this->connection instanceof StreamSocketConnection) {
            return $this->connection;
        }

        $fi = new FilesystemIterator($this->config['piddir'], FilesystemIterator::SKIP_DOTS);
        $count = iterator_count($fi);

        while ($count >= $this->config['threads']) {
            $fi = new FilesystemIterator($this->config['piddir'], FilesystemIterator::SKIP_DOTS);
            $count = iterator_count($fi);
        }

        $this->connection = StreamSocketConnection::init($this->getConfig());

        file_put_contents($this->config['pidfile'], $this->config['pidfile']);

        return $this->connection;
    }

    /**
     * Get EPP config
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set EPP config
     *
     * @param array
     * @return self
     */
    public function setConfig(array $config): self
    {
        foreach (['login','password'] as $key) {
            if (empty($config[$key])) {
                throw new InvalidParamException("`$key` must be given for HostmasterTool");
            }

            $this->{$key} = $config[$key];
            $this->config[$key] = $config[$key];
        }

        foreach (['protocol', 'url', 'port', 'registry', 'services', 'extensions', 'namespaces', 'cacertificate', 'certificate', 'new_password', 'language', 'piddir', 'registrar', 'contract'] as $key) {
            if (!empty($config[$key])) {
                $this->config[$key] = $config[$key];
            }
        }

        $this->config['piddir'] = $this->config['piddir'] ?? dirname(__DIR__) . "/pid/{$this->config['registry']}_{$this->config['registrar']}";
        if (!file_exists($this->config['piddir'])) {
            mkdir($this->config['piddir'], 0777, true);
        }

        $this->config['pidfile'] = $this->config['piddir'] . "/" . getmypid();

        return $this;
    }

    public function getContactTypes() : array
    {
        $types = ['registrant', 'admin', 'tech', 'billing'];
        if (empty($this->config['disabled'])) {
            return $types;
        }

        if (empty($this->config['disabled']['contacts'])) {
            return $types;
        }

        foreach ($types as $type) {
            if (!in_array($type, $types, true)) {
                $available[] = $type;
            }
        }

        return $available ?? [];
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getContract()
    {
        return $this->config['contract'];
    }

    public function __destruct()
    {
        unlink($this->config['pidfile']);
    }
}

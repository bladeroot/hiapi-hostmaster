<?php
/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @author: omnix@debian.org.ua
 * @author: bladeroot@gmail.com
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster;

use hiapi\hostmaster\helpers\XmlHelper;
use hiapi\hostmaster\parsers\ParserFactory;

use hiapi\hostmaster\requests\RequestInterface;
use hiapi\hostmaster\generator\BasicGenerator;
use hiapi\hostmaster\response\epp\EppHelloResponse;
use hiapi\hostmaster\exceptions\ConnectionException;

use RuntimeException;

/*
 * EPP Client
 */
class EppClient
{
    /** @var HostmasterTool */
    private $tool;

    /** @var StreamSocketConnection */
    private $connection;

    /** @var bool */
    private $isLoggedIn;

    private $objRepo;

    private $extRepo;

    protected $version;

    protected $language = 'en';

    /** @var NamespaceCollection */
    private $namespaceCollection;

    /** @var NamespaceCollection */
    private $extNamespaceCollection;

    /** @var GeneratorInterface */
    private $idGenerator;

    /** @var ExtensionInterface[] */
    private $extensionStack;

    private $greeting;

    private static $instance;

    public static function getClient(HostmasterTool $tool, StreamSocketConnection $connection) : self
    {
        if (empty(self::$instance)) {
            self::$instance = new static($tool, $connection);
        }

        return self::$instance;
    }

    public function getExtensions()
    {
        return $this->extRepo->getAll();
    }

    public function isLoggedIn()
    {
        return $this->isLoggedIn;
    }

    public function __destruct()
    {
        if (!$this->isLoggedIn()) {
            return ;
        }

        $this->send(["command" => "epp:logout"]);
        $this->isLoggedIn = false;
        $this->connection->disconnect();

    }

    private function __construct(HostmasterTool $tool, StreamSocketConnection $connection)
    {
        $this->tool = $tool;
        $this->connection = $connection;
        $this->greeting = ParserFactory::parse('epp', $this->connection->getGreeting());
        $svcMenu = $this->greeting['greeting']['svcMenu'];

        try {
            $this->objRepo = new XMLNSRepository($svcMenu['objURI']);
            $this->extRepo = new XMLNSExtensionsRepository($svcMenu['svcExtension']['extURI']);
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage());
        }

        $this->version = $svcMenu['version'];
        $this->language = 'en';

        try {
            $login = $this->send([
                'command' => 'epp:login',
                'clID' => $this->tool->getLogin(),
                'pw' => $this->tool->getPassword(),
                'version' => $this->version,
                'language' => $this->language,
                'objURI' => $this->objRepo->getAll(),
                'extURI' => $this->extRepo->getAll(),
            ]);

            if ($login['code'] == 1000) {
                $this->isLoggedIn = true;
            }

            if ($this->extRepo->get('balance')) {
                $this->objRepo->add('balance', $this->extRepo->get('balance'));
                $this->extRepo->delete('balance');
            }
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    public function command(array $data)
    {
        return $this->send($data);
    }

    public function getGreeting()
    {
        return $this->greeting;
    }

    public function send(array $data)
    {
        if (!$this->connection->isConnected()) {
            throw new RuntimeException('Cannot send request to the not open connection');
        }

        [$object, $command] = explode(":", $data['command'], 2);
        unset($data['command']);

        $request = $this->objRepo->getRequest($object);
        $request = call_user_func([$request, $command], $data);

        $request = $this->applyExtensions($request, $data);
        $requestXML = (string) $request;

        $this->connection->sendFrame($requestXML);
        $responseXML = $this->connection->getFrame();

        return ParserFactory::parse($object, $responseXML);
    }

    protected function applyExtensions($request, $data)
    {
        if (empty($data['extensions'])) {
            return $request;
        }

        foreach ($data['extensions'] as $extension => $values) {
            $ext = $this->extRepo->getRequest($extension);
            if ($ext === null) {
                continue;
            }

            $request = call_user_func([$ext->setRequest($request), $values['command'] ?? 'default'], $values);
        }

        return $request;
    }

    /**
     * Getting the URI collection of objects.
     *
     * @return NamespaceCollection
     */
    public function getNamespaceCollection()
    {
        return $this->namespaceCollection;
    }

    /**
     * Setting the URI collection of objects.
     *
     * @param NamespaceCollection $collection Collection object
     */
    public function setNamespaceCollection(NamespaceCollection $collection)
    {
        $this->namespaceCollection = $collection;

        return $this;
    }

    /**
     * Getting the URI collection of extensions.
     *
     * @return NamespaceCollection
     */
    public function getExtNamespaceCollection()
    {
        return $this->extNamespaceCollection;
    }

    /**
     * Setting the URI collection of extensions.
     *
     * @param NamespaceCollection $collection Collection object
     */
    public function setExtNamespaceCollection(NamespaceCollection $collection)
    {
        $this->extNamespaceCollection = $collection;

        return $this;
    }

    /**
     * Getting the identifier generator.
     *
     * @return GeneratorInterface
     */
    public function getIdGenerator()
    {
        return $this->idGenerator;
    }

    /**
     * Setting the identifier generator.
     *
     * @param GeneratorInterface $idGenerator Generator object
     */
    public function setIdGenerator(GeneratorInterface $idGenerator)
    {
        $this->idGenerator = $idGenerator;

        return $this;
    }

    /**
     * Add extension in stack.
     *
     * @param ExtensionInterface $extension instance of extension
     *
     * @return self
     */
    public function pushExtension(ExtensionInterface $extension)
    {
        array_unshift($this->extensionStack, $extension);
        $extension->setupNamespaces($this);

        return $this;
    }

    /**
     * Retrieving extension from the stack.
     *
     * @return ExtensionInterface
     *
     * @throws LogicException
     */
    public function popExtension()
    {
        if (!$this->extensionStack) {
            throw new LogicException('You tried to pop from an empty extension stack.');
        }

        return array_shift($this->extensionStack);
    }
}


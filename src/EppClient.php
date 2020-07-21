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
use hiapi\hostmaster\requests\AbstractRequest;
use hiapi\hostmaster\requests\DomainRequest;
use hiapi\hostmaster\requests\EppRequest;
use hiapi\hostmaster\requests\HostRequest;
use hiapi\hostmaster\requests\ContactRequest;
use hiapi\hostmaster\parsers\ParserFactory;
use hiqdev\yii\compat\yii;


/*
 * EPP Client
 */
class EppClient {
    /** Debug mode */
    const DEBUG_NONE = 1;
    const DEBUG_LOW = 2;
    const DEBUG_HIGH = 3;
    const DEBUG_FULL = 4;
    const DEBUG_TRACE = 5;
    const DEBUG_ALL = 6;

    /** Log mode */
    const MODE_INFO = 0;
    const MODE_CLIENT = 1;
    const MODE_SERVER = 2;

    /** @var array of EppClient $instances */
    private static $instances = [];

    /** @var resource $socket */
    protected $socket;
    /** @var string $url */
    protected $url;
    /** @var bool $is_connected */
    protected $is_connected = false;
    /** @var bool $is_loggedin */
    protected $is_loggedin = false;
    /** @var string $request */
    protected $request;
    /** @var string $answer */
    protected $answer;
    /** @var string $greeting */
    protected $greeting;
    /** @var string $log_file */
    protected $log_file;

    protected $config = [];

    protected $requestMap = [
        'epp' => EppRequest::class,
        'domain' => DomainRequest::class,
        'contact' => ContactRequest::class,
        'host' => HostRequest::class,
    ];

    private function __construct(HostmasterTool $tool, array $config)
    {
        $this->tool = $tool;
        $this->log_file     = $config['log_file'] ?? yii::getAlias("@runtime/var/{$config['log_dir']}/{$config['registrator']}.log");
        $this->login        = $config['login'];
        $this->password     = $config['password'];
        $this->url          = "{$config['protocol']}://{$config['url']}:{$config['port']}";
        $this->certificate  = $config['certificate'];
        $this->timeout      = $config['timeout'] ?? 300;
        $this->is_loggedin  = false;
        $this->is_connected = false;
        $this->config = $config;
    }

    /**
     * Get instance
     *
     * @param array $config
     * @return self
     */
    public static function init(HostmasterTool $tool, array $config) : self
    {
        $key = $config['registy'] . "_" . $config['registrator'];
        if (empty(self::$instances[$key])) {
            self::$instances[$key] = new static($tool, $config);
        }


        return self::$instances[$key];
    }

    public function getTool() : HostmasterTool
    {
        if (empty($this->tool)) {
        }

        return $this->tool;
    }

    /**
     * return bool
     */
    public function isConnected() : bool
    {
        return (bool) ($this->is_connected && $this->socket);
    }

    /**
     * return bool
     */
    public function isLoggedIn() : bool
    {
        return (bool) ($this->is_connected && $this->is_loggedin && $this->socket);
    }

   private function context()
    {
        if ($this->certificate) {
            $options = [
                'ssl' => [
                    'local_cert'        => $this->certificate,
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                ],
            ];
            if ($this->cacertfile) {
                $options['ssl'] = array_merge($options['ssl'], [
                    'verify_peer' => true,
                    'cafile' => $this->cacertfile,
                ]);
            }
        }

        if ($this->bindto) {
            $options['socket'] = ['bindto' => $this->bindto];
        }

        return isset($options) ? stream_context_create($options) : null;
    }

    /**
     * Create connection to EPP server
     *
     * @return self
     * @throw RuntimeException
     */
    public function connect()
    {
        if ($this->isConnected()) {
            return $this;
        }
        $errno  = "";
        $errstr = "";
        $target = $this->url;
        $context = $this->context();
        if ($context === null) {
            $this->socket = @stream_socket_client($target, $errno, $errstr, $this->timeout);
        } else {
            $this->socket = @stream_socket_client($target, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        }

        if ($this->socket === false || $this->socket === null) {
            throw new \RuntimeException("Error connecting to $target: $errno - $errstr cert: $this->certificate");
        }

        if ($this->getFrame() === false) {
            throw new \RuntimeException("Could not get frame from EPP Server");
        }

        $this->is_connected = true;
        $this->greeting = ParserFactory::parse('epp', $this->answer);

        $this->slog($this->answer, self::MODE_SERVER);
        return $this;
    }

    /**
     * Close connection to EPP server
     */
    public function disconnect()
    {
        if ($this->is_connected && ($this->socket !== false)) {
            $this->logout();
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            fclose($this->socket);
            unset($this->socket);
        }
        $this->is_connected = false;

        return false;
    }

    /**
     * Execute command
     *
     * @param string $cml
     * @return string
     * @throw RuntimeException
     */
    public function command(string $object, string $command, array $data = [])
    {
        if (!$this->isLoggedIn() && $object !== 'epp' && !in_array($command, ['hello', 'login', 'logout'], true)) {
            $this->login();
        }

        $request = $this->getRequestObj($object);
        $xmlObj = call_user_func([$request, $command], $data);
        $xml = (string) $xmlObj;

        $this->slog($xml, self::MODE_CLIENT);
        $this->request = trim(preg_replace(['/^[\s]*/uim', '/[\s]*$/uim'], ['', ''],trim($xml)));

        if (strlen($this->request) > 4092) {
            throw new \RuntimeException("Request is too long");
        }

        if (XmlHelper::isXMLContentValid($this->request) === false) {
            throw new \RuntimeException("XML is not valid");
        }

        // --- send request ---
        $this->sendFrame();

        // --- recv answer ---
        if ($this->getFrame() === false) {
            throw new \RuntimeException("Could not get answer from EPP");
        }

        $this->slog($this->answer, self::MODE_SERVER);

        try {
            $this->xml_data = ParserFactory::parse($object, $this->answer);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage());
        }

        if (isset($this->xml_data['code'])) {
            $this->rc = $this->code = $this->xml_data['code'];
        } else {
            $this->rc = $this->code = 1000;
        }

        if (isset($this->xml_data['msg'])) {
            $this->msg = $this->xml_data['msg'];
        } else {
            $this->msg = "";
        }

        if ($this->debug >= DEBUG_TRACE) {
            $this->slog("::: got: ". $this->code .": ". $this->msg);
        }

        return $this->xml_data;
    }

    public function login()
    {
        if ($this->isLoggedIn()) {
            return $this;
        }

        if (!$this->isConnected()) {
            $this->connect();
        }

        $res = $this->command('epp', 'login', array_merge([
                'clID' => $this->config['login'],
                'pw' => $this->config['password'],
            ],
            $this->greeting ? $this->greeting['greeting'] : $this->hello()
        ));

        if ($this->code != 1000) {
            $this->disconnect();
            throw new \RuntimeException($this->msg);
        }

        $this->is_loggedin = true;
        return $this;
    }

    public function hello()
    {
        $cache = $this->tool->di->get('cache');
        return $cache->getOrSet([__METHOD__, $this->config], function() {
            $res = $this->command('epp', 'hello', []);
            return $res['epp']['greeting'];
        }, 600);
    }

    public function logout()
    {
        $this->command('epp', 'logout', []);
        $this->is_loggedin = false;
        return $this;
    }

    /**
     * Get ansfer from EPP server
     *
     * @return bool
     */
    protected function getFrame()
    {
        if (feof($this->socket)) {
            return false;
        }

        $hdr = stream_get_contents($this->socket, 4);
        if (empty($hdr)) {
            return false;
        }

        $unpacked = unpack('N', $hdr);
        $this->answer = stream_get_contents($this->socket, ($unpacked[1] - 4));
        return true;
    }

    /**
     * Send sommand to EPP server
     *
     * @return bool
     */
    protected function sendFrame()
    {
        return @fwrite($this->socket, pack('N', (strlen($this->request)+4)).$this->request);
    }

    public function __destruct () {
        $this->disconnect();
    }

    protected function slog($str, $mode = self::MODE_INFO)
    {
        if (!$this->log_file) {
            return ;
        }

        if (!file_exists($this->log_file)) {
            if (!file_exists(dirname($this->log_file))) {
                if (!mkdir(dirname($this->log_file), 0777, true)) {
                    return ;
                }
            }
        }

        switch ($mode) {
            case self::MODE_SERVER:
                $mode = "S";
                break;
            case self::MODE_CLIENT:
                $mode = "C";
                break;
            default:
                $mode = "I";
        }

        if (is_string($str)) {
            $str = explode("\n", $str);
        }

        if (!is_array($str)) {
            return ;
        }

        foreach($str as $tmp) {
            $dt = date("Y-m-d H:i:s");
            $tmp = sprintf("%s [%5s] %s: %s\n", $dt, posix_getpid(), $mode, $tmp);
            file_put_contents($this->log_file, $tmp, FILE_APPEND);
        }
    }

    public function getRequestObj(string $name) : AbstractRequest
    {
        if (empty($this->requestMap[$name])) {
            throw new \InvalidCallException("Object `$name` not found");
        }

        return $this->createRequestObj($this->requestMap[$name]);
    }

    public function setRequestObj(string $name, AbstractRequest $request) : self
    {
        if (empty($this->requestMap[$name])) {
            throw new InvalidCallException("Object `$name` not found");
        }

        $this->requestMap[$name] = $request;

        return $this;
    }

    public function createRequestObj($class) : AbstractRequest
    {
        if (is_object($class)) {
            return $class;
        }

        return new $class($this->config['namespaces'], $this->config['login']);
    }
}


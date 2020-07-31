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

use hiqdev\yii\compat\yii;
use RuntimeException;

/*
 * StreamSocketConnection
 */
class StreamSocketConnection {
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

    /** @var StreamSocketConnection $instance */
    private static $instance;

    /**
     * Server connection settings
     * @var array $config
     */
    protected $config;

    /**
     * Server address
     * @var string $uri
     */
    protected $uri;

    /**
     * Path to certificate
     * @var string $certificate
     */
    protected $certificate;

    /**
     * Path to cacertfile
     * @var string $cacertfile
     */
    protected $cacertfile;

    /**
     * Server connection timeout in seconds
     * @var int $timeout
     */
    protected $timeout;

    /**
     * Resource of connection to the server
     * @var resource $socket
     */
    protected $socket;

    /**
     * Buffer for stream reading
     * @var string $buffer
     */
    protected $buffer;

    /**
     * Debug level
     * @var int debug
     */
    protected $debug;

    private function __construct(array $config)
    {
        $this->log_file     = $config['log_file'] ?? yii::getAlias("@runtime/var/{$config['log_dir']}/{$config['registrar']}.log");
        $this->uri          = "{$config['protocol']}://{$config['url']}:{$config['port']}";
        $this->certificate  = $config['certificate'];
        $this->timeout      = $config['timeout'] ?? 300;
        $this->debug        = $config['debug'] ?? self::DEBUG_NONE;
        $this->config       = $config;
    }

    /**
     * Get instance
     *
     * @param array $config
     * @return self
     */
    public static function init(array $config) : self
    {
        if (empty(self::$instance)) {
            self::$instance = new static($config);

        }

        return self::$instance->connect();
    }

    /**
     * return bool
     */
    public function isConnected() : bool
    {
        return (bool) $this->socket && is_resource($this->socket) && !feof($this->socket);
    }

    /**
     * Create connection to EPP server
     *
     * @return self
     * @throw RuntimeException
     */
    protected function connect() : self
    {
        if ($this->isConnected()) {
            return $this->greeting;
        }

        $errno  = "";
        $errstr = "";
        $target = $this->uri;
        $context = $this->getContext();
        if ($context === null) {
            $this->socket = @stream_socket_client($target, $errno, $errstr, $this->timeout);
        } else {
            $this->socket = @stream_socket_client($target, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        }

        if ($this->socket === false || $this->socket === null) {
            throw new RuntimeException("Error connecting to $target: $errno - $errstr cert: $this->certificate");
        }

        $this->greeting = $this->getFrame();
        $this->slog($this->greeting, self::MODE_SERVER);

        return $this;
    }

    public function getGreeting()
    {
        return $this->greeting;
    }

    /**
     * Close connection to EPP server
     */
    public function disconnect()
    {
        if ($this->isConnected()) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            fclose($this->socket);
            unset($this->socket);
        }

        return false;
    }

    /**
     * Get ansfer from EPP server
     *
     * @param void
     * @return string
     */
    public function getFrame() : string
    {
        if (feof($this->socket)) {
            throw new RuntimeException('Client not connected to EPP Server');
        }

        $hdr = stream_get_contents($this->socket, 4);
        if (empty($hdr)) {
            throw new RuntimeException('Could not get frame from EPP Server');
        }

        $unpacked = unpack('N', $hdr);
        $answer = stream_get_contents($this->socket, ($unpacked[1] - 4));

        if ($this->debug >= DEBUG_TRACE) {
            $this->slog($answer, self::MODE_SERVER);
        }

        return $answer;
    }

    /**
     * Send sommand to EPP server
     *
     * @param string $xml
     * @return bool
     */
    public function sendFrame(string $xml) : bool
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        if ($this->debug >= DEBUG_TRACE) {
            $this->slog($xml, self::MODE_CLIENT);
        }

        return @fwrite($this->socket, pack('N', (strlen($xml)+4)).$xml);
    }

    public function __destruct ()
    {
    }

    /**
     * Create context for connection
     *
     * @param void
     * @return ?resource
     */
    private function getContext()
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
     * Write a log
     *
     * @param string|array $data
     * @param int $mode
     *
     * @return void
     */

    private function slog($data, int $mode = self::MODE_INFO)
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

        if (is_string($data)) {
            $data = explode("\n", $data);
        }

        if (!is_array($data)) {
            return ;
        }

        foreach($data as $tmp) {
            $dt = date("Y-m-d H:i:s");
            $tmp = sprintf("%s [%5s] %s: %s\n", $dt, posix_getpid(), $mode, $tmp);
            file_put_contents($this->log_file, $tmp, FILE_APPEND);
        }
    }
}


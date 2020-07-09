<?php

/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster\parsers;

use hiapi\hostmaster\helpers\XmlHelper;
use Exception;

class ParserFactory
{
    protected static $parsers = [
        'epp' => EppParser::class,
        'domain' => DomainParser::class,
        'host' => HostParser::class,
        'contact' => ContactParser::class,
        'balance' => BalanceParser::class,
        'rgp' => RGPParser::class,
        'secDNS' => SecDNSParser::class,
        'fee' => FeeParser::class,
        'price' => PriceParser::class,
        'oxrs' => OXRSParser::class,
        'idn' => IDNParser::class,
        'idnLang' => IDNLang::class,
        'namestoreExt' => NameStoreParser::class,
    ];

    public static function parse(string $obj, string $xml) : array
    {
        $data = XmlHelper::xmlToArray($xml);

        return self::getParser($obj)->parse_epp($data['epp']);
    }

    public static function getParser($obj) : AbstractParser
    {
        if (empty(self::$parsers[$obj])) {
            throw new Exception('Parser not found');
        }

        return self::createParser($obj);
    }

    protected static function createParser($obj) : AbstractParser
    {
        if (is_object(self::$parsers[$obj])) {
            return self::$parsers[$obj];
        }

        $parser = new self::$parsers[$obj];
        self::$parsers[$obj] = $parser;

        return self::$parsers[$obj];
    }

}

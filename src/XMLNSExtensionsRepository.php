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

use hiapi\hostmaster\requests\extensions\SecDNSExtensionRequest;
use hiapi\hostmaster\requests\extensions\RGPExtensionRequest;
use hiapi\hostmaster\requests\extensions\FeeExtensionRequest;
use hiapi\hostmaster\requests\extensions\Fee9ExtensionRequest;
use hiapi\hostmaster\requests\extensions\Fee11ExtensionRequest;
use hiapi\hostmaster\requests\extensions\PriceExtensionRequest;
use hiapi\hostmaster\requests\extensions\UAEppExtensionRequest;
use hiapi\hostmaster\requests\extensions\BalanceExtensionRequest;
use hiapi\hostmaster\requests\extensions\OXRSExtensionRequest;
use hiapi\hostmaster\requests\extensions\IDNExtensionRequest;
use hiapi\hostmaster\requests\extensions\IDNLangExtensionRequest;
use hiapi\hostmaster\requests\extensions\NamestoreExtensionRequest;

class XMLNSExtensionsRepository extends XMLNSRepository
{
    protected $objects = [
        'secDNS' => [
            'urn:ietf:params:xml:ns:secDNS-1.1' => SecDNSExtensionRequest::class,
            'http://hostmaster.ua/epp/secDNS-1.1' => SecDNSExtensionRequest::class,
        ],
        'rgp' => [
            'urn:ietf:params:xml:ns:rgp-1.0' => RGPExtensionRequest::class,
            'http://hostmaster.ua/epp/rgp-1.1' => RGPExtensionRequest::class,
        ],
        'fee' => [
            'urn:ietf:params:xml:ns:fee-0.5' => FeeExtensionRequest::class,
            'urn:ietf:params:xml:ns:fee-0.6' => FeeExtensionRequest::class,
            'urn:ietf:params:xml:ns:fee-0.7' => FeeExtensionRequest::class,
            'urn:ietf:params:xml:ns:fee-0.8' => FeeExtensionRequest::class,
            'urn:ietf:params:xml:ns:fee-0.9' => Fee9ExtensionRequest::class,
            'urn:ietf:params:xml:ns:fee-0.11' => Fee11ExtensionRequest::class,
            'urn:ietf:params:xml:ns:fee-0.21' => FeeExtensionRequest::class,
        ],
        'price' => [
            'urn:ar:params:xml:ns:price-1.0' => PriceExtensionRequest::class,
            'urn:ar:params:xml:ns:price-1.1' => PriceExtensionRequest::class,
            'urn:ar:params:xml:ns:price-1.2' => PriceExtensionRequest::class,
        ],
        'uaepp' => [
            'http://hostmaster.ua/epp/uaepp-1.1' => UAEppExtensionRequest::class,
        ],
        'balance' => [
            'http://hostmaster.ua/epp/balance-1.0' => BalanceExtensionRequest::class,
        ],
        'oxrs' => [
            'urn:afilias:params:xml:ns:oxrs-1.1' => OXRSExtensionRequest::class,
        ],
        'namestoreExt' => [
            'http://www.verisign-grs.com/epp/namestoreExt-1.1' => NamestoreExtensionRequest::class,
        ],
        'idn' => [
            'urn:afilias:params:xml:ns:idn-1.0' => IDNExtensionRequest::class,
        ],
        'idnLang' => [
            'http://www.verisign.com/epp/idnLang-1.0' => IDNLangExtensionRequest::class,
        ],
    ];

    public function __construct(array $uris = null)
    {
        parent::__construct($uris);
        if ($this->get('price') && $this->get('fee')) {
            $this->delete('fee');
        }
    }
}

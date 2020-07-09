<?php
/**
 * hiAPI Hostmaster plugin
 *
 * @link      https://github.com/hiqdev/hiapi-hostmaster
 * @package   hiapi-hostmaster
 * @license   BSD-3-Clause
 * @author    Francesco Casula <fra.casula@gmail.com>
 * @author    Yurii Myronchuk <bladeroot@gmail.com>
 * @copyright Copyright (c) 2020, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\hostmaster\helpers;

use DOMDocument;
use DOMException;
use BadFunctionCallException;

/**
 * Class XmlHelper
 */
class XmlHelper
{
    /**
     * @param string $xmlFilename Path to the XML file
     * @param string $version 1.0
     * @param string $encoding utf-8
     * @return bool
     */
    public static function isXMLFileValid($xmlFilename, $version = '1.0', $encoding = 'utf-8')
    {
        $xmlContent = $xmlContent ? : file_get_contents($xmlFilename);
        return self::isXMLContentValid($xmlContent, $version, $encoding);
    }

    /**
     * @param string $xmlContent A well-formed XML string
     * @param string $version 1.0
     * @param string $encoding utf-8
     * @return bool
     */
    public static function isXMLContentValid(string $xmlContent, $version = '1.0', $encoding = 'utf-8')
    {
        libxml_use_internal_errors(true);

        $doc = new DOMDocument($version, $encoding);
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadXML($xmlContent);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        return empty($errors) ? $doc : false;
    }

    /**
     * Prettify XML
     *
     * @param string $xmlContent
     * @return string
     */
    public static function prettifyXMLContent(string $xmlContent, $version = '1.0', $encoding = 'utf-8')
    {
        $doc = self::isXMLContentValid($xmlContent, $version, $encoding);
        if ($doc === false) {
            throw new DOMException("XML document is not valid");
        }

        return $doc->saveXML();
    }

    /**
     * Parse XML to Array
     *
     * @param string $xml
     * @param int $get_attributes
     * @param string $priority
     * @return array
     * @throw BadFunctionCallException
     * @throw DOMException
     */
    public static function xmlToArray(
        string $xml,
        int $get_attributes = 1,
        string $priority = 'tag'
    ) : ?array
    {
        $xml = trim($xml);
        if (!$xml || !function_exists('xml_parser_create')) {
            throw new BadFunctionCallException('`xml_parser_create` does not exists');
        }

        $doc = self::isXMLContentValid($xml, '1.0', 'utf-8');
        if ($doc === false) {
            throw new DOMException("XML document is not valid");
        }

        //Get the XML parser of PHP - PHP must have this module for the parser to work
        $parser = xml_parser_create('');
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        $parsed = xml_parse_into_struct($parser, $xml, $xml_values);
        xml_parser_free($parser);

        if ($parsed === 0) {
            throw new DOMException("XML document could not be parsed");
        }

        //Initializations
        $xml_array = [];
        $parents = [];
        $opened_tags = [];
        $arr = [];
        $current = &$xml_array; //Refference

        //Go through the tags.
        $repeated_tag_index = [];//Multiple tags with same name will be turned into an array
        foreach ($xml_values as $xml_data) {
            unset($attributes,$value);//Remove existing values, or there will be trouble
            //This command will extract these variables into the foreach scope
            // tag(string), type(string), level(int), attributes(array).
            extract($xml_data);//We could use the array by itself, but this cooler.

            $result = [];
            $attributes_data = [];

            if (isset($value)) {
                if($priority == 'tag') {
                    $result = $value;
                } else {
                    $result['value'] = $value; //Put the value in a assoc array if we are in the 'Attribute' mode
                }
            }

            //Set the attributes too.
            if (isset($attributes) && $get_attributes) {
                foreach ($attributes as $attr => $val) {
                    if ($priority == 'tag') {
                        $attributes_data[$attr] = $val;
                    } else {
                        $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
                    }
                }
            }

            //See tag status and do the needed.
            if ($type == "open") { //The starting of the tag '<tag>'
                $parent[$level-1] = &$current;
                if (!is_array($current) || (!in_array($tag, array_keys($current)))) { //Insert New tag
                    $current[$tag] = $result;
                    if($attributes_data) {
                        $current[$tag. '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag.'_'.$level] = 1;
                    $current = &$current[$tag];
                } else { //There was another element with the same tag name
                    if (isset($current[$tag][0]))  {//If there is a 0th element it is already an array
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                        $repeated_tag_index[$tag.'_'.$level]++;
                    } else {//This section will make the value an array if multiple tags with the same name appear together
                        $current[$tag] = [$current[$tag],$result];  //This will combine the existing item and the new item together to make an array
                        $repeated_tag_index[$tag.'_'.$level] = 2;
                        if (isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well
                            $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                            unset($current[$tag.'_attr']);
                        }
                    }
                    $last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
                    $current = &$current[$tag][$last_item_index];
                }
            } elseif ($type == "complete") { //Tags that ends in 1 line '<tag />'
                //See if the key is already taken.
                if (!isset($current[$tag])) { //New Key
                    $current[$tag] = $result;
                    $repeated_tag_index[$tag.'_'.$level] = 1;
                    if($priority == 'tag' and $attributes_data) {
                        $current[$tag. '_attr'] = $attributes_data;
                    }
                } else { //If taken, put all things inside a list(array)
                    if (isset($current[$tag][0]) && is_array($current[$tag])) {//If it is already an array...
                        // ...push the new element into that array.
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                        if ($priority == 'tag' && $get_attributes && $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag.'_'.$level]++;
                    } else { //If it is not an array...
                        $current[$tag] = [$current[$tag],$result]; //...Make it an array using using the existing value and the new value
                        $repeated_tag_index[$tag.'_'.$level] = 1;
                        if ($priority == 'tag' && $get_attributes) {
                            if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well
                                $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                                unset($current[$tag.'_attr']);
                            }
                            if ($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag.'_'.$level]++; //0 and 1 index is already taken
                    }
                }
            } elseif($type == 'close') { //End of tag '</tag>'
                $current = &$parent[$level-1];
            }
        }

        return $xml_array;
    }
}


<?php

namespace esnerda\XML2CsvProcessor;

class XML2JsonConverter
{
    const KEY_ROW_NR = 'row_nr';

    public function xml2json(string $xml_string, bool $addRowNr, $alwaysArray = [], bool $contOnFailure = false, $attributePrefix = 'xml_attr_', $txtContent = 'txt_content_')
    {
        libxml_use_internal_errors(true);
        $xml = $this->simplexml_load_string_nons($xml_string);
        $errMsgs = '';
        if (!$xml) {
            $errors = libxml_get_errors();
            $errMsgs = 'ERR';
            foreach ($errors as $error) {
                $errMsgs .= $this->display_xml_error($error, $xml);
            }

            libxml_clear_errors();
        }

        $settings = ['attributePrefix' => $attributePrefix,
            'textContent' => $txtContent,
            'alwaysArray' => $alwaysArray,
            'addRowNumber' => $addRowNr];

        if ($contOnFailure && $errMsgs) {
            return $errMsgs;
        } else if ($errMsgs) {
            throw new \InvalidArgumentException($errMsgs);
        }

        return json_encode($this->xmlToArray($xml, $settings));

    }


    /**
     * Credits to https://outlandish.com/blog/tutorial/xml-to-json/
     *
     * @param type $xml - xml object
     * @param type $options
     * @return type
     */
    private function xmlToArray($xml, $options = [])
    {
        $defaults = ['namespaceSeparator' => ':', //you may want this to be something other than a colon
            'attributePrefix' => '@', //to distinguish between attributes and nodes with the same name
            'alwaysArray' => array(), //array of xml tag names which should always become arrays
            'autoArray' => true, //only create arrays for tags which appear more than once
            'textContent' => '$', //key used for the text content of elements
            'autoText' => true, //skip textContent key if node has no attributes or child nodes
            'keySearch' => false, //optional search and replace on tag and attribute names
            'keyReplace' => false, //replace values for above search values (as passed to str_replace())
            'addRowNumber' => false,
        ];

        $options = array_merge($defaults, $options);
        $namespaces = $xml->getDocNamespaces();
        $namespaces[''] = null; //add base (empty) namespace
        //get attributes from all namespaces
        $attributesArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                //replace characters in attribute name
                if ($options['keySearch']) {
                    $attributeName = str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
                }
                $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
                $attributesArray[$attributeKey] = (string)$attribute;
            }
        }

        //get child nodes from all namespaces
        $tagsArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->children($namespace) as $childXml) {
                //recurse into child nodes
                $childArray = $this->xmlToArray($childXml, $options);
                //list($childTagName, $childProperties) = each($childArray);
                $childTagName = key($childArray);
                $childProperties = current($childArray);
                //replace characters in tag name
                if ($options['keySearch']) {
                    $childTagName = str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
                }
                //add namespace prefix, if any
                if ($prefix) {
                    $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;
                }

                if (!isset($tagsArray[$childTagName])) {
                    //only entry with this key
                    //test if tags of this type should always be arrays, no matter the element count
                    $tagsArray[$childTagName] = in_array($childTagName, $options['alwaysArray']) || !$options['autoArray'] ? $this->convertToArray($childProperties, $childTagName, $options['addRowNumber']) : $childProperties;
                } elseif (
                    is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName]) === range(0, count($tagsArray[$childTagName]) - 1)
                ) {
                    //key already exists and is integer indexed array
                    if ($options['addRowNumber']) { // add row, nth element
                        $childProperties = $this->addRowNumber($childProperties, $childTagName, sizeof($tagsArray[$childTagName]) + 1);
                    }
                    $tagsArray[$childTagName][] = $childProperties;
                } else {
                    //key exists so convert to integer indexed array with previous value in position 0
                    if ($options['addRowNumber']) { // add row, first element
                        $tagsArray[$childTagName] = $this->addRowNumber($tagsArray[$childTagName], $childTagName, 1);
                        $childProperties = $this->addRowNumber($childProperties, $childTagName, 2);
                    }
                    $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
                }
                /* if (!isset($tagsArray[$childTagName])) {
                  //only entry with this key
                  //test if tags of this type should always be arrays, no matter the element count
                  $tagsArray[$childTagName] = in_array($childTagName, $options['alwaysArray']) || !$options['autoArray'] ? array($childProperties) : $childProperties;
                  } else {
                  $tagsArray[$childTagName][] = $this->appendTagArray($tagsArray[$childTagName], $childProperties, $options['addRowNumber']);
                  } */
            }

            //get text content of node
            $textContentArray = array();
            $plainText = trim((string)$xml);
            if ($plainText !== '') {
                $textContentArray[$options['textContent']] = $plainText;
            }

            //stick it all together
            $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '') ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;
            // set to empty string if empty
            if (is_array($propertiesArray) && count($propertiesArray) == 0) {
                $propertiesArray = "";
            }
            //return node as array
            return array(
                $xml->getName() => $propertiesArray
            );
        }
    }

    private function convertToArray($value, $parentName, $addRowNr)
    {
        if ($addRowNr) {
            $newArr = array($this->addRowNumber($value, $parentName, 1));
        } else {
            $newArr = array($value);
        }

        return $newArr;
    }

    private function addRowNumber($data, $parentName, $rowNr)
    {
        $newArr = [];
        if (is_array($data)) {
            $newArr = $data;
            $newArr[self::KEY_ROW_NR] = $rowNr;
        } else {
            $newArr[$parentName . '_value'] = $data;
            $newArr[self::KEY_ROW_NR] = $rowNr;
        }
        return $newArr;
    }

    /* private function appendTagArray($element, $childProperties, $appendRow) {
      if (is_array($element) && array_keys($element) === range(0, count($element) - 1)) {
      //key already exists and is integer indexed array
      if ($appendRow) { // add row, nth element
      $childProperties[self::KEY_ROW_NR] = sizeof($element) + 1;
      }
      return $childProperties;
      } else {
      //key exists so convert to integer indexed array with previous value in position 0
      if ($appendRow) { // add row, first element
      $element[self::KEY_ROW_NR] = 1;
      $childProperties[self::KEY_ROW_NR] = 2;
      }
      return array($element, $childProperties);
      }
      } */

    private function display_xml_error($error, $xml)
    {
        $return = $xml[$error->line - 1] ?? 'N/A' . "\n";
        $return .= str_repeat('-', $error->column) . "^\n";

        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $return .= "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error $error->code: ";
                break;
        }

        $return .= trim($error->message) .
            "\n  Line: $error->line" .
            "\n  Column: $error->column";

        if ($error->file) {
            $return .= "\n  File: $error->file";
        }

        return "$return\n\n--------------------------------------------\n\n";
    }

    /**
     * Loads XML and kills namespaces in the process.
     * It allows easy usage of namespaced XML code.
     *
     * - NameSpaced tags are renamed from <ns:tag to <ns_tag.
     * - NameSpaced tags are renamed from </ns:tag> to </ns_tag>.
     * - NameSpaced attributes are renamed from ns:tag=... to ns_tag=....
     *
     * @license http://sam.zoy.org/wtfpl/
     *
     * @param string $xml
     * @param string $sxclass
     * @param bool $nsattr
     * @param int $flags
     * @return SimpleXMLElement
     */
    function simplexml_load_string_nons($xml, $sxclass = 'SimpleXMLElement', $nsattr = false, $flags = null)
    {
        // Validate arguments first
        if (!is_string($sxclass) or empty($sxclass) or !class_exists($sxclass)) {
            trigger_error('$sxclass must be a SimpleXMLElement or a derived class.', E_USER_WARNING);
            return false;
        }
        if (!is_string($xml) or empty($xml)) {
            trigger_error('$xml must be a non-empty string.', E_USER_WARNING);
            return false;
        }

        // Load XML if URL is provided as XML
        if (preg_match('~^https?://[^\s]+$~i', $xml) || file_exists($xml)) {
            $xml = file_get_contents($xml);
        }

        // Let's drop namespace definitions
        if (stripos($xml, 'xmlns=') !== false) {
            $xml = preg_replace('~[\s]+xmlns=[\'"].+?[\'"]~i', null, $xml);
        }

        // I know this looks kind of funny but it changes namespaced attributes
        if (preg_match_all('~xmlns:([a-z0-9\-]+)=~i', $xml, $matches)) {
            foreach (($namespaces = array_unique($matches[1])) as $namespace) {
                $escaped_namespace = preg_quote($namespace, '~');
                $xml = preg_replace('~[\s]xmlns:' . $escaped_namespace . '=[\'].+?[\']~i', null, $xml);
                $xml = preg_replace('~[\s]xmlns:' . $escaped_namespace . '=["].+?["]~i', null, $xml);
                $xml = preg_replace('~([\'"\s])' . $escaped_namespace . ':~i', '$1' . $namespace . '_', $xml);
            }
        }

        // Let's change <namespace:tag to <namespace_tag ns="namespace"
        $regexfrom = sprintf('~<([a-z0-9]+):%s~is', !empty($nsattr) ? '([a-z0-9]+)' : null);
        $regexto = strlen($nsattr) ? '<$1_$2 ' . $nsattr . '="$1"' : '<$1_';
        $xml = preg_replace($regexfrom, $regexto, $xml);
        // Let's change </namespace:tag> to </namespace_tag>
        $xml = preg_replace('~</([a-z0-9]+):~is', '</$1_', $xml);

        // Default flags I use
        if (empty($flags)) $flags = LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_PARSEHUGE;
        // Now load and return (namespaceless)
        return $xml = simplexml_load_string($xml, $sxclass, $flags);
    }
}

<?php
namespace Ntuple\Synctree\Util\Markup\Xml;

use DOMAttr;
use DOMNode;
use DOMText;
use DOMElement;
use DOMDocument;
use DOMCdataSection;
use DOMNamedNodeMap;

class XmlDecoder
{
    private $document;
    private $replacePrefixByEmptyStringInNodeNames;

    /**
     * XmlDecoder constructor.
     * @param string $xml
     */
    public function __construct(string $xml)
    {
        $this->document = new DOMDocument();
        $this->document->loadXML($xml);

        // set replace prefix flag
        $this->setReplacePrefixByEmptyStringInNodeNames(false);
    }

    /**
     * @param DOMNamedNodeMap $nodeMap
     * @return array|null
     */
    private function convertAttributes(DOMNamedNodeMap $nodeMap): ?array
    {
        if ($nodeMap->length === 0) {
            return null;
        }

        $result = [];

        /** @var DOMAttr $item */
        foreach ($nodeMap as $item) {
            $result[$item->name] = $item->value;
        }

        return ['_attributes' => $result];
    }

    /**
     * @param array $arr
     * @return array
     */
    private function isHomogenous(array $arr): array
    {
        $resData = [];
        foreach ($arr as $val) {
            if (!array_key_exists($val, $resData)) {
                $resData[$val] = 1;
            } else {
                $resData[$val]++;
            }
        }

        return $resData;
    }

    /**
     * @param DOMElement $element
     * @return array|string|null
     */
    private function convertDomElement(DOMElement $element)
    {
        $sameNames = false;
        $result = $this->convertAttributes($element->attributes);

        if ($element->childNodes->length > 1) {
            $childNodeNames = [];
            foreach ($element->childNodes as $key => $node) {
                $childNodeNames[] = $this->getNodeName($node);
            }
            $sameNames = $this->isHomogenous($childNodeNames);
        }

        foreach ($element->childNodes as $key => $node) {
            if ($node instanceof DOMCdataSection) {
                $result['_cdata'] = $node->data;
                continue;
            }

            if ($node instanceof DOMText) {
                $result = $node->textContent;
                continue;
            }

            if ($node instanceof DOMElement) {
                $nodeName = $this->getNodeName($node);
                if (isset($sameNames[$nodeName]) && $sameNames[$nodeName] > 1) {
                    $result[$nodeName][] = $this->convertDomElement($node);
                } else {
                    $result[$nodeName] = $this->convertDomElement($node);
                }
                continue;
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    private function toArray(): array
    {
        $result = [];

        if ($this->document->hasChildNodes()) {
            $children = $this->document->childNodes;

            foreach ($children as $child) {
                $result[$this->getNodeName($child)] = $this->convertDomElement($child);
            }
        }

        return $result;
    }

    /**
     * @param DOMNode $node
     * @return string
     */
    private function getNodeName(DOMNode $node): string
    {
        $nodeName = $node->nodeName;

        if ($this->replacePrefixByEmptyStringInNodeNames === false) {
            return $nodeName;
        }

        if (!empty($node->prefix)) {
            $nodeName = str_replace($node->prefix.':', '', $nodeName);
        }

        return $nodeName;
    }

    /**
     * @param bool $flag
     * @return XmlDecoder
     */
    public function setReplacePrefixByEmptyStringInNodeNames(bool $flag): XmlDecoder
    {
        $this->replacePrefixByEmptyStringInNodeNames = $flag;
        return $this;
    }

    /**
     * @return array
     */
    public function convert(): array
    {
        return $this->toArray();
    }
}
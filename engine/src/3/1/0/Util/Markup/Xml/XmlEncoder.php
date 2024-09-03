<?php
namespace Ntuple\Synctree\Util\Markup\Xml;

use DOMDocument;
use DOMElement;
use DOMException;
use Exception;
use RuntimeException;

class XmlEncoder
{
    private $document;
    private $replaceSpacesByUnderScoresInKeyNames;
    private $numericTagNamePrefix;

    /**
     * XmlUtil constructor.
     * @param array $data
     * @param string|array $rootElement
     * @param string $xmlEncoding
     * @param string $xmlVersion
     * @param array $domProperties
     * @throws DOMException
     * @throws Exception
     */
    public function __construct(array $data, $rootElement = '', string $xmlEncoding = 'UTF-8', string $xmlVersion = '1.0', array $domProperties = [])
    {
        $this->document = new DOMDocument($xmlVersion, $xmlEncoding);

        if (!empty($domProperties)) {
            $this->setDomProperties($domProperties);
        }

        // set replace space flag
        $this->setSeplaceSpacesByUnderScoresInKeyNames(false);

        // set numeric tag default prefix
        $this->setNumericTagNamePrefix('numeric_');

        if (!empty($data) && $this->isArrayAllKeySequential($data)) {
            throw new DOMException('Invalid Character Error');
        }

        $root = $this->createRootElement($rootElement);
        $this->document->appendChild($root);
        $this->convertElement($root, $data);
    }

    /**
     * @param array $domProperties
     * @throws Exception
     */
    private function ensureValidDomProperties(array $domProperties): void
    {
        foreach ($domProperties as $key => $value) {
            if (!property_exists($this->document, $key)) {
                throw new RuntimeException($key.' is not a valid property of DOMDocument');
            }
        }
    }

    /**
     * @param array $domProperties
     * @throws Exception
     */
    private function setDomProperties(array $domProperties): void
    {
        $this->ensureValidDomProperties($domProperties);

        foreach ($domProperties as $key => $value) {
            $this->document->{$key} = $value;
        }
    }

    /**
     * @param DOMElement $element
     * @param $value
     */
    private function convertElement(DOMElement $element, $value): void
    {
        $sequential = $this->isArrayAllKeySequential($value);

        if (!is_array($value)) {
            $value = htmlspecialchars($value);
            $value = $this->removeControlCharacters($value);
            $element->nodeValue = $value;
            return;
        }

        foreach ($value as $key => $data) {
            if (!$sequential) {
                if (($key === '_attributes') || ($key === '@attributes')) {
                    $this->addAttributes($element, $data);
                } elseif ((($key === '_value') || ($key === '@value')) && is_string($data)) {
                    $element->nodeValue = htmlspecialchars($data);
                } elseif ((($key === '_cdata') || ($key === '@cdata')) && is_string($data)) {
                    $element->appendChild($this->document->createCDATASection($data));
                } elseif ((($key === '_mixed') || ($key === '@mixed')) && is_string($data)) {
                    $fragment = $this->document->createDocumentFragment();
                    $fragment->appendXML($data);
                    $element->appendChild($fragment);
                } elseif ($key === '__numeric') {
                    $this->addNumericNode($element, $data);
                } else {
                    $this->addNode($element, $key, $data);
                }
            } elseif (is_array($data)) {
                $this->addCollectionNode($element, $data);
            } else {
                $this->addSequentialNode($element, $data);
            }
        }
    }

    /**
     * @param DOMElement $element
     * @param $value
     */
    private function addNumericNode(DOMElement $element, $value): void
    {
        foreach ($value as $key => $item) {
            $this->convertElement($element, [$this->numericTagNamePrefix.$key => $item]);
        }
    }

    /**
     * @param DOMElement $element
     * @param $key
     * @param $value
     */
    private function addNode(DOMElement $element, $key, $value): void
    {
        if ($this->replaceSpacesByUnderScoresInKeyNames) {
            $key = str_replace(' ', '_', $key);
        }

        $child = $this->document->createElement($key);
        $element->appendChild($child);
        $this->convertElement($child, $value);
    }

    /**
     * @param DOMElement $element
     * @param $value
     */
    private function addCollectionNode(DOMElement $element, $value): void
    {
        if ($element->childNodes->length === 0 && $element->attributes->length === 0) {
            $this->convertElement($element, $value);
            return;
        }

        $child = $this->document->createElement($element->tagName);
        $element->parentNode->appendChild($child);
        $this->convertElement($child, $value);
    }

    /**
     * @param DOMElement $element
     * @param $value
     */
    private function addSequentialNode(DOMElement $element, $value): void
    {
        if (empty($element->nodeValue) && !is_numeric($element->nodeValue)) {
            $element->nodeValue = htmlspecialchars($value);
            return;
        }

        $child = new DOMElement($element->tagName);
        $child->nodeValue = htmlspecialchars($value);
        $element->parentNode->appendChild($child);
    }

    /**
     * @param $value
     * @return bool
     */
    private function isArrayAllKeySequential($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (count($value) <= 0) {
            return true;
        }

        if (key($value) === '__numeric') {
            return false;
        }

        return array_unique(array_map('is_int', array_keys($value))) === [true];
    }

    /**
     * @param DOMElement $element
     * @param array $data
     */
    private function addAttributes(DOMElement $element, array $data): void
    {
        foreach ($data as $attrKey => $attrVal) {
            $element->setAttribute($attrKey, $attrVal);
        }
    }

    /**
     * @param string|array $rootElement
     * @return DOMElement
     */
    private function createRootElement($rootElement): DOMElement
    {
        if (is_string($rootElement)) {
            $rootElementName = $rootElement ?: 'root';
            return $this->document->createElement($rootElementName);
        }

        $rootElementName = $rootElement['rootElementName'] ?? 'root';
        $element = $this->document->createElement($rootElementName);

        foreach ($rootElement as $key => $value) {
            if ($key !== '_attributes' && $key !== '@attributes') {
                continue;
            }
            $this->addAttributes($element, $rootElement[$key]);
        }

        return $element;
    }

    /**
     * @param string $value
     * @return string
     */
    private function removeControlCharacters(string $value): string
    {
        return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    }

    /**
     * @return string
     */
    private function toXml(): string
    {
        return $this->document->saveXML();
    }

    /**
     * @param string $prefix
     * @return XmlEncoder
     */
    public function setNumericTagNamePrefix(string $prefix): XmlEncoder
    {
        $this->numericTagNamePrefix = $prefix;
        return $this;
    }

    /**
     * @param bool $flag
     * @return XmlEncoder
     */
    public function setSeplaceSpacesByUnderScoresInKeyNames(bool $flag): XmlEncoder
    {
        $this->replaceSpacesByUnderScoresInKeyNames = $flag;
        return $this;
    }

    /**
     * @return string
     */
    public function convert(): string
    {
        return $this->toXml();
    }
}
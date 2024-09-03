<?php
namespace Ntuple\Synctree\Util\Support\String;

class StringSupport
{
    private $data;
    private $encoding;

    /**
     * StringSupport constructor.
     * @param string $data
     * @param string|null $encoding
     */
    public function __construct(string $data, string $encoding = null)
    {
        $this->data = $data;
        $this->encoding = $encoding ?: mb_internal_encoding();
    }

    /**
     * @param string $search
     * @param string $replacement
     * @return false|string
     */
    public function replace(string $search, string $replacement)
    {
        return $this->regexReplace(preg_quote($search, null), $replacement);
    }

    /**
     * @param string $pattern
     * @param string $replacement
     * @param string $options
     * @return false|string
     */
    public function regexReplace(string $pattern, string $replacement, string $options = 'msr')
    {
        $regexEncoding = $this->regexEncoding();
        $this->regexEncoding($this->encoding);

        $resData = $this->eregReplace($pattern, $replacement, $this->data, $options);
        $this->regexEncoding($regexEncoding);

        return $resData;
    }

    /**
     * @param string $delimiter
     * @param int $limit
     * @return array|false
     */
    public function split(string $delimiter, int $limit = -1)
    {
        return $this->regexSplit(preg_quote($delimiter, null), $limit);
    }

    /**
     * @param string $pattern
     * @param int $limit
     * @return array|false
     */
    public function regexSplit(string $pattern, int $limit = -1)
    {
        $regexEncoding = $this->regexEncoding();
        $this->regexEncoding($this->encoding);

        $resData = mb_split($pattern, $this->data, $limit);
        $this->regexEncoding($regexEncoding);

        return $resData;
    }

    /**
     * @param string|null $encoding
     * @return bool|string
     */
    private function regexEncoding(string $encoding = null)
    {
        if ($encoding !== null) {
            return mb_regex_encoding($encoding);
        }

        return mb_regex_encoding();
    }

    /**
     * @param string $pattern
     * @param string $replacement
     * @param string $string
     * @param string $option
     * @return false|string
     */
    private function eregReplace(string $pattern, string $replacement, string $string, string $option = 'msr')
    {
        return mb_ereg_replace($pattern, $replacement, $string, $option);
    }
}
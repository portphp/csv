<?php

namespace Port\Csv;

use Port\Writer\AbstractStreamWriter;

/**
 * Writes to a CSV file
 *
 * @author David de Boer <david@ddeboer.nl>
 */
class CsvWriter extends AbstractStreamWriter
{
    /**
     * @var string
     */
    private $delimiter;

    /**
     * @var string
     */
    private $enclosure;

    /**
     * @var boolean
     */
    private $utf8Encoding = false;

    private $row = 1;

    /**
     * @var boolean
     */
    protected $prependHeaderRow;

    /**
     * @var string
     */
    private $escape;

    /**
     * @param string   $delimiter The delimiter
     * @param string   $enclosure The enclosure
     * @param resource $stream
     * @param boolean  $utf8Encoding
     * @param boolean  $prependHeaderRow
     * @param string   $escape The escape character (pass '' for PHP 8.4+ preferred "no escape" behavior)
     */
    public function __construct($delimiter = ',', $enclosure = '"', $stream = null, $utf8Encoding = false, $prependHeaderRow = false, string $escape = '\\')
    {
        parent::__construct($stream);

        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->utf8Encoding = $utf8Encoding;
        $this->prependHeaderRow = $prependHeaderRow;
        $this->escape = $escape;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(): void
    {
        if ($this->utf8Encoding) {
            fprintf($this->getStream(), chr(0xEF) . chr(0xBB) . chr(0xBF));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeItem(array $item): void
    {
        if ($this->prependHeaderRow && 1 == $this->row++) {
            $headers = array_keys($item);
            fputcsv($this->getStream(), $headers, $this->delimiter, $this->enclosure, $this->escape);
        }

        fputcsv($this->getStream(), $item, $this->delimiter, $this->enclosure, $this->escape);
    }
}

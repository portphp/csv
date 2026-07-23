<?php

namespace Port\Tests\Csv;

use Port\Csv\CsvWriter;
use Port\Test\StreamWriterTest;

class CsvWriterTest extends StreamWriterTest
{
    public function testWriteItem()
    {
        $writer = new CsvWriter(';', '"', $this->getStream());

        $writer->prepare();
        $writer->writeItem(array('first', 'last'));

        $writer->writeItem(array(
            'first' => 'James',
            'last'  => 'Bond'
        ));

        $writer->writeItem(array(
            'first' => '',
            'last'  => 'Dr. No'
        ));

        $this->assertContentsEquals(
            "first;last\nJames;Bond\n;\"Dr. No\"\n",
            $writer
        );

        $writer->finish();
    }

    public function testWriteUtf8Item()
    {
        $writer = new CsvWriter(';', '"', $this->getStream(), true);

        $writer->prepare();
        $writer->writeItem(array('Précédent', 'Suivant'));

        $this->assertContentsEquals(
            chr(0xEF) . chr(0xBB) . chr(0xBF) . "Précédent;Suivant\n",
            $writer
        );

        $writer->finish();
    }

    /**
     * Test that column names not prepended to first row
     * if CsvWriter's 5-th parameter not given
     *
     * @author  Igor Mukhin <igor.mukhin@gmail.com>
     */
    public function testHeaderNotPrependedByDefault()
    {
        $writer = new CsvWriter(';', '"', $this->getStream(), false);
        $writer->prepare();
        $writer->writeItem(array(
            'col 1 name'=>'col 1 value',
            'col 2 name'=>'col 2 value',
            'col 3 name'=>'col 3 value'
        ));

        # Values should be at first line
        $this->assertContentsEquals(
            "\"col 1 value\";\"col 2 value\";\"col 3 value\"\n",
            $writer
        );
        $writer->finish();
    }

    /**
     * Test that column names prepended at first row
     * and values have been written at second line
     * if CsvWriter's 5-th parameter set to true
     *
     * @author  Igor Mukhin <igor.mukhin@gmail.com>
     */
    public function testHeaderPrependedWhenOptionSetToTrue()
    {
        $writer = new CsvWriter(';', '"', $this->getStream(), false, true);
        $writer->prepare();
        $writer->writeItem(array(
            'col 1 name'=>'col 1 value',
            'col 2 name'=>'col 2 value',
            'col 3 name'=>'col 3 value'
        ));

        # Column names should be at second line
        # Values should be at second line
        $this->assertContentsEquals(
            "\"col 1 name\";\"col 2 name\";\"col 3 name\"\n" .
            "\"col 1 value\";\"col 2 value\";\"col 3 value\"\n",
            $writer
        );
        $writer->finish();
    }

    /**
     * Proves escape is applied: default '\\' and empty-string escape produce different CSV
     * for a field that contains a backslash before a quote.
     *
     * Also exercises that fputcsv receives an explicit $escape argument, which is required
     * on PHP 8.4+ (omitting it is deprecated; phpunit.xml converts deprecations to exceptions).
     */
    public function testEscapeParameterAffectsOutput()
    {
        $item = array('a\\"b');

        $defaultWriter = new CsvWriter(',', '"', fopen('php://temp', 'r+'));
        $defaultWriter->setCloseStreamOnFinish(false);
        $defaultWriter->prepare();
        $defaultWriter->writeItem($item);
        $defaultOutput = $this->readWriterContents($defaultWriter);

        $emptyEscapeWriter = new CsvWriter(',', '"', fopen('php://temp', 'r+'), false, false, '');
        $emptyEscapeWriter->setCloseStreamOnFinish(false);
        $emptyEscapeWriter->prepare();
        $emptyEscapeWriter->writeItem($item);
        $emptyOutput = $this->readWriterContents($emptyEscapeWriter);

        $this->assertNotSame(
            $defaultOutput,
            $emptyOutput,
            'Custom escape should change CSV encoding of fields containing backslash/quote'
        );

        $this->assertSame($this->fputcsvString($item, '\\'), $defaultOutput);
        $this->assertSame($this->fputcsvString($item, ''), $emptyOutput);

        fclose($defaultWriter->getStream());
        fclose($emptyEscapeWriter->getStream());
    }

    /**
     * Empty escape is usable for modern "no escape" CSV and does not trigger PHP 8.4+
     * fputcsv deprecation (which phpunit.xml converts to exceptions).
     */
    public function testEmptyEscapeWritesWithoutDeprecation()
    {
        $writer = new CsvWriter(',', '"', $this->getStream(), false, false, '');
        $writer->prepare();
        $writer->writeItem(array('hello', 'world'));
        $writer->writeItem(array('say "hi"', 'path\\to'));

        $this->assertContentsEquals(
            $this->fputcsvString(array('hello', 'world'), '') .
            $this->fputcsvString(array('say "hi"', 'path\\to'), ''),
            $writer
        );

        $writer->finish();
    }

    private function readWriterContents(CsvWriter $writer)
    {
        $stream = $writer->getStream();
        rewind($stream);

        return stream_get_contents($stream);
    }

    private function fputcsvString(array $fields, $escape)
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $fields, ',', '"', $escape);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }
}

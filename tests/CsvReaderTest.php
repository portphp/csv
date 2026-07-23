<?php

namespace Port\Tests\Csv;

use PHPUnit\Framework\TestCase;
use Port\Csv\CsvReader;

class CsvReaderTest extends TestCase
{
    public function testReadCsvFileWithColumnHeaders()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_column_headers.csv');
        $csvReader = new CsvReader($file);
        $csvReader->setHeaderRowNumber(0);

        $this->assertEquals(
            array(
                'id', 'number', 'description'
            ),
            array_keys($csvReader->current())
        );

        foreach ($csvReader as $row) {
            $this->assertNotNull($row['id']);
            $this->assertNotNull($row['number']);
            $this->assertNotNull($row['description']);
        }

        $this->assertEquals(
            array(
                'id'        => 6,
                'number'    => '456',
                'description' => 'Another description'
            ),
            $csvReader->getRow(2)
        );
    }

    public function testReadCsvFileWithoutColumnHeaders()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_no_column_headers.csv');
        $csvReader = new CsvReader($file);

        $this->assertEmpty($csvReader->getColumnHeaders());
    }

    public function testReadCsvFileWithManualColumnHeaders()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_no_column_headers.csv');
        $csvReader = new CsvReader($file);
        $csvReader->setColumnHeaders(array('id', 'number', 'description'));

        foreach ($csvReader as $row) {
            $this->assertNotNull($row['id']);
            $this->assertNotNull($row['number']);
            $this->assertNotNull($row['description']);
        }
    }

    public function testReadCsvFileWithTrailingBlankLines()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_blank_lines.csv');
        $csvReader = new CsvReader($file);
        $csvReader->setColumnHeaders(array('id', 'number', 'description'));

        foreach ($csvReader as $row) {
            $this->assertNotNull($row['id']);
            $this->assertNotNull($row['number']);
            $this->assertNotNull($row['description']);
        }
    }

    public function testCountWithoutHeaders()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_no_column_headers.csv');
        $csvReader = new CsvReader($file);
        $this->assertEquals(3, $csvReader->count());
    }

    public function testCountWithHeaders()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_column_headers.csv');
        $csvReader = new CsvReader($file);
        $csvReader->setHeaderRowNumber(0);
        $this->assertEquals(3, $csvReader->count(), 'Row count should not include header');
    }

    public function testCountWithFewerElementsThanColumnHeadersNotStrict()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_fewer_elements_than_column_headers.csv');
        $csvReader = new CsvReader($file);
        $csvReader->setStrict(false);
        $csvReader->setHeaderRowNumber(0);

        $this->assertEquals(3, $csvReader->count());
    }

    public function testCountWithMoreElementsThanColumnHeadersNotStrict()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_more_elements_than_column_headers.csv');
        $csvReader = new CsvReader($file);
        $csvReader->setStrict(false);
        $csvReader->setHeaderRowNumber(0);

        $this->assertEquals(3, $csvReader->count());
        $this->assertFalse($csvReader->hasErrors());
        $this->assertEquals(array(6, 456, 'Another description'), array_values($csvReader->getRow(2)));
    }

    public function testCountDoesNotMoveFilePointer()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_column_headers.csv');
        $csvReader = new CsvReader($file);
        $csvReader->setHeaderRowNumber(0);

        $key_before_count = $csvReader->key();
        $csvReader->count();
        $key_after_count = $csvReader->key();

        $this->assertEquals($key_after_count, $key_before_count);
    }

    public function testVaryingElementCountWithColumnHeadersNotStrict()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_column_headers_varying_element_count.csv');
        $csvReader = new CsvReader($file);
        $csvReader->setStrict(false);
        $csvReader->setHeaderRowNumber(0);

        $this->assertEquals(4, $csvReader->count());
        $this->assertFalse($csvReader->hasErrors());
    }

    public function testVaryingElementCountWithoutColumnHeadersNotStrict()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_no_column_headers_varying_element_count.csv');
        $csvReader = new CsvReader($file);
        $csvReader->setStrict(false);
        $csvReader->setColumnHeaders(array('id', 'number', 'description'));

        $this->assertEquals(5, $csvReader->count());
        $this->assertFalse($csvReader->hasErrors());
    }

    public function testInvalidCsv()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_column_headers_varying_element_count.csv');
        $reader = new CsvReader($file);
        $reader->setHeaderRowNumber(0);

        $this->assertTrue($reader->hasErrors());

        $this->assertCount(2, $reader->getErrors());

        $errors = $reader->getErrors();
        $this->assertEquals(2, key($errors));
        $this->assertEquals(array('123', 'test'), current($errors));

        next($errors);
        $this->assertEquals(3, key($errors));
        $this->assertEquals(array('7', '7890', 'Some more info', 'too many columns'), current($errors));
    }

    public function testLastRowInvalidCsv()
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/data_no_column_headers_varying_element_count.csv');
        $reader = new CsvReader($file);
        $reader->setColumnHeaders(array('id', 'number', 'description'));

        $this->assertTrue($reader->hasErrors());
        $this->assertCount(3, $reader->getErrors());

        $errors = $reader->getErrors();
        $this->assertEquals(1, key($errors));
        $this->assertEquals(array('6', 'strictly invalid'), current($errors));

        next($errors);
        $this->assertEquals(3, key($errors));
        $this->assertEquals(array('3','230','Yet more info','Even more info'), current($errors));

        next($errors);
        $this->assertEquals(4, key($errors));
        $this->assertEquals(array('strictly invalid'), current($errors));
    }

    public function testDuplicateHeadersThrowsException()
    {
        $this->expectException(\Port\Exception\DuplicateHeadersException::class);
        $reader = $this->getReader('data_column_headers_duplicates.csv');
        $reader->setHeaderRowNumber(0);
    }

    public function testDuplicateHeadersIncrement()
    {
        $reader = $this->getReader('data_column_headers_duplicates.csv');
        $reader->setHeaderRowNumber(0, CsvReader::DUPLICATE_HEADERS_INCREMENT);
        $reader->rewind();
        $current = $reader->current();

        $this->assertEquals(
            array('id', 'description', 'description1', 'description2', 'details', 'details1', 'last'),
            $reader->getColumnHeaders()
        );

        $this->assertEquals(
            array(
                'id'           => '50',
                'description'  => 'First',
                'description1' => 'Second',
                'description2' => 'Third',
                'details'      => 'Details1',
                'details1'     => 'Details2',
                'last'         => 'Last one'
            ),
            $current
        );
    }

    public function testDuplicateHeadersMerge()
    {
        $reader = $this->getReader('data_column_headers_duplicates.csv');
        $reader->setHeaderRowNumber(0, CsvReader::DUPLICATE_HEADERS_MERGE);
        $reader->rewind();
        $current = $reader->current();

        $this->assertCount(4, $reader->getColumnHeaders());

        $expected = array(
            'id'          => '50',
            'description' => array('First', 'Second', 'Third'),
            'details'     => array('Details1', 'Details2'),
            'last'        => 'Last one'
        );
        $this->assertEquals($expected, $current);
    }

    public function testMaximumNesting()
    {
        if (!function_exists('xdebug_is_enabled')) {
            $this->markTestSkipped('xDebug is not installed');
        }

        $xdebug_start = !xdebug_is_enabled();
        if ($xdebug_start) {
            xdebug_enable();
        }

        ini_set('xdebug.max_nesting_level', 200);

        $file = new \SplTempFileObject();
        for($i = 0; $i < 500; $i++) {
            $file->fwrite("1,2,3\n");
        }

        $reader = new CsvReader($file);
        $reader->rewind();
        $reader->setStrict(true);
        $reader->setColumnHeaders(array('one','two'));

        $current = $reader->current();
        $this->assertEquals(null, $current);

        if ($xdebug_start) {
            xdebug_disable();
        }
    }

    /**
     * When the iterator is already at EOF, current() must return null rather than
     * calling count() on SplFileObject's false (do-while entered once while invalid).
     *
     * @see https://github.com/portphp/csv/pull/3
     */
    public function testCurrentAtEndOfFileWithHeadersReturnsNull()
    {
        $file = new \SplTempFileObject();
        $file->fwrite("id,name\n1,Alice\n2,Bob\n");
        $file->rewind();

        $reader = new CsvReader($file);
        $reader->setHeaderRowNumber(0);

        // Exhaust the iterator
        iterator_to_array($reader);

        $this->assertFalse($reader->valid());
        $this->assertNull($reader->current());
    }

    /**
     * getRow() past the last line should not TypeError on count(false).
     *
     * @see https://github.com/portphp/csv/pull/3
     */
    public function testGetRowPastEndWithHeadersReturnsNull()
    {
        $file = new \SplTempFileObject();
        $file->fwrite("id,name\n1,Alice\n");
        $file->rewind();

        $reader = new CsvReader($file);
        $reader->setHeaderRowNumber(0);

        $this->assertNull($reader->getRow(99));
    }

    /**
     * OneToManyReader calls rightReader->current() after next() past the last
     * detail row. Without checking valid() first, CsvReader::current() hit
     * count(false) and broke joins on the last master row.
     *
     * This is the real-world failure reported against PR #3.
     *
     * @see https://github.com/portphp/csv/pull/3
     * @see https://github.com/portphp/csv/pull/3#issuecomment-769855101
     */
    public function testOneToManyReaderConsumesLastDetailRowWithoutError()
    {
        $masterFile = new \SplTempFileObject();
        $masterFile->fwrite("id,name\n1,Alice\n2,Bob\n");
        $masterFile->rewind();
        $masterReader = new CsvReader($masterFile);
        $masterReader->setHeaderRowNumber(0);

        $detailFile = new \SplTempFileObject();
        $detailFile->fwrite("id,item\n1,apple\n1,banana\n2,carrot\n");
        $detailFile->rewind();
        $detailReader = new CsvReader($detailFile);
        $detailReader->setHeaderRowNumber(0);

        $reader = new \Port\Reader\OneToManyReader(
            $masterReader,
            $detailReader,
            'items',
            'id',
            'id'
        );

        $rows = iterator_to_array($reader);

        $this->assertCount(2, $rows);
        $this->assertEquals('Alice', $rows[1]['name']);
        $this->assertEquals(
            array(
                array('id' => '1', 'item' => 'apple'),
                array('id' => '1', 'item' => 'banana'),
            ),
            $rows[1]['items']
        );
        $this->assertEquals('Bob', $rows[2]['name']);
        $this->assertEquals(
            array(
                array('id' => '2', 'item' => 'carrot'),
            ),
            $rows[2]['items']
        );
    }

    protected function getReader($filename)
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/'.$filename);

        return new CsvReader($file);
    }
}

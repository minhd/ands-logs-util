<?php


class DatabaseAdapterTest extends PHPUnit_Framework_TestCase
{

    protected $database = null;

    public function testGetRecord()
    {
        $record = $this->database->getRecord(434522);

        $this->assertEquals(434522, $record['registry_object_id']);
        $expects = ['data_source_id', 'key', 'class', 'type', 'title', 'status', 'slug', 'record_owner', 'group'];
        foreach ($expects as $expect) {
            $this->assertArrayHasKey($expect, $record);
        }
    }

    public function __construct()
    {
        $dotenv = new Dotenv\Dotenv(__DIR__.'/../');
        $dotenv->load();
        $this->database = new MinhD\ANDSLogUtil\DatabaseAdapter($config = [
            'DB_HOST' => getenv('DB_HOST'),
            'DB_USER' => getenv('DB_USER'),
            'DB_PASS' => getenv('DB_PASS'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ]);
        $this->database->setCacheEnabled(false);
        parent::__construct();
    }
}

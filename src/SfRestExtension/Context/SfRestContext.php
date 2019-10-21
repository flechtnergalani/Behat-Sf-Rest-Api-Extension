<?php

namespace SfRestExtension;

use SfRestExtension\Interfaces\SalesforceDBAware;
use Behat\Behat\Context\Context;

class SfRestContext implements Context, SalesforceDBAware
{
    private $SFDB;
    private $timeout;

    public function __construct(int $timeout = 10) {
        $this->timeout = $timeout;
    }

    public function setConnection($connection)
    {
        $this->SFDB = $connection;
    }
  
    /**
     * @Then I execute query :soql
     * @param $soql 
     */
    public function iExecuteQuery($soql)
    {
        $response = $this->SFDB->queryDatabase($soql);
        foreach ($response["records"] as $n => $record)
        {
            echo "[$n] ";
            foreach ($record as $key => $value) {
                echo "$key = $value; ";
            }
            echo "\n";
        }    
    }

    /**
     * @Then I delete the first 10 records of type :object where :where
     */
    public function iDeleteObjects($object, $where)
    {
        $failures = $this->SFDB->deleteRecords($object, $where, 10);
    }

    /**
     * @Then there is a record of type :object named :name in the database
     */
    public function iCheckThatRecordExists($object, $name)
    {
        if ($this->SFDB->recordExistsWithName($object, $name)) {
            echo "$object named $name exists in database.\n";
        } else {
            throw new AssertionError("Failed Assertion: Could not find record of type $object named $name.");
        }
    }

    /**
     * @Then there is no record of type :object named :name in the database
     */
    public function iCheckThatNoRecordExists($object, $name)
    {
        if ($this->SFDB->recordExistsWithName($object, $name)) {
            throw new AssertionError("Failed Assertion: There is a record of type $object named $name.");
        } else {
            echo "No $object named $name exists in database.\n";
        }
    }

}
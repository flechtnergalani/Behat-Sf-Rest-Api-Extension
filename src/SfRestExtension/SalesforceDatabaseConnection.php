<?php

namespace SfRestExtension;

use Exception;
use InvalidArgumentException;
use SforceEnterpriseClient;

class SalesforceDatabaseConnection
{
    private $connection;
    
    private $user;
    private $pw;
    private $token;
    private $wdsl_path;
    private $endpoint;

    private $id_type_prefixes;

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::getInstance() instead
     */
    public function __construct($user, $pw, $token, $wdsl_path, $endpoint)
    {
        $this->user = $user;
        $this->pw = $pw;
        $this->token = $token;
        $this->wdsl_path = $wdsl_path;
        $this->endpoint = $endpoint;
    }

    private function connect()
    {
        if (isset($this->connection))
            return;
        try {
            $mySforceConnection = new SforceEnterpriseClient();
            $mySforceConnection->createConnection($this->wdsl_path);
            $mySforceConnection->setEndpoint($this->endpoint);
            $mySforceConnection->login($this->user, $this->pw.$this->token);
        } catch (\Throwable $th) {
            throw new Exception("Failed to connect to Salesforce Database:\n".$th->getMessage(), 1, $th);
        }
        $this->connection = $mySforceConnection;
    }

    /**
     * prevent the instance from being cloned (which would create a second instance of it)
     */
    private function __clone()
    {
    }

    public function connection()
    {
        $this->connect();
        return $this->connection;
    }

    public function queryDatabase(String $query, bool $get_all=False)
    {
        // send query
        $response = $this->connection()->query($query);
        // return if no results
        if(empty($response->size))
            return array('count' => 0, 'records' => array());
        // otherwise, get records
        $records = $response->records;
        // return if get_all not TRUE
        if(!$get_all)
            return array('count' => $response->size, 'records' => $records);
        // otherwise, keep fetching till server returns done==TRUE
        while (!$response->done) {
            $response = $this->connection()->query($response->queryLocator);
            $records = array_merge($records, $response->records);
        }
        return array('count' => $response->size, 'records' => $records);
    }

    public function getDatabaseRecords(String $object, array $fields=array("id", "name"), 
        String $where=NULL, int $limit=NULL, bool $get_all=False)
    {
        // formulate query
        $fields = implode(", ", $fields);
        $query = "SELECT $fields FROM $object";
        if(!empty($where))
            $query .= " WHERE $where";
        if(!empty($limit))
            $query .= " LIMIT $limit";
        return $this->queryDatabase($query, $get_all);
    }

    /**
     * Returns the requested fields from records whose Ids are passed as $ids. 
     * If the recordtype is not passed to $sobjecttype, will try to infer it from the
     * first three digits of an Id.
     */
    public function getById($ids, array $fields=array("id", "name"), string $sobjecttype=null)
    {
        // makes function accept string or array of strings
        if (!is_array($ids))
            $ids = array($ids);
        // fields must be a string of field names seperated with commas
        $fields = implode(", ", $fields);
        // infer objecttype from the first 3 letters in the ID if not given
        if (empty($sobjecttype)) {
            $sobjecttype = $this->recordTypeFromId(array($ids[0]));
        }
        // call SforceBaseClient method retrieve (requires objecttype argument)
        $res = $this->connection()->retrieve($fields, $sobjecttype, $ids);
        array_walk($res, function(&$e) use ($sobjecttype) {$e->RecordType = $sobjecttype;});
        return $res;
    }

    /**
     * The first three digits of an object's Id can tell you which record type it is.
     * This function retrieves the record type for a list of Ids and returns
     * a) an array of recordtypes if they resolve to more than one record type
     * b) a string containing the recordtype if all Ids resolve to the same record type  
     */
    public function recordTypeFromId(array $ids)
    {
        if (empty($this->id_type_prefixes)) {
            $result = $this->connection()->describeGlobal();
            $this->id_type_prefixes = array();
            foreach ($result->sobjects as $recordtype) {
                $this->id_type_prefixes[$recordtype->keyPrefix] = $recordtype->name;
            }
        }
        $types = array_map(function($id) {
            $prefix = substr($id, 0, 3);
            if (!array_key_exists($prefix,$this->id_type_prefixes))
                throw new InvalidArgumentException("Invalid Id: Prefix $prefix of Id $id could not be matched to a record type");
            return $this->id_type_prefixes[$prefix];
        }, $ids);
        if (count(array_unique($types))===1)
            return array_pop($types);
        return $types;
    }

    public function getLatest(String $object, array $fields=array("id", "name"), 
        String $where=NULL)
    {
        // formulate query
        $fields = implode(", ", $fields);
        $query = "SELECT $fields FROM $object";
        if(!empty($where))
            $query .= " WHERE $where";
        $query .= " ORDER BY CreatedDate DESC LIMIT 1";
        return $this->queryDatabase($query, false);
    }

    public function recordExists(String $object, String $where=NULL)
    {
        $result = $this->getDatabaseRecords($object, array('id'), $where, 1, False);
        return $result['count']>0;
    }

    public function recordExistsWithName(String $object, String $name)
    {
        $where = "Name = '$name'";
        return $this->recordExists($object, $where);
    }

    public function countRecords(String $object, String $where=NULL)
    {
        $result = $this->getDatabaseRecords($object, array('COUNT()'), $where, NULL, False);
        return $result['count'];
    }

    public function deleteRecords(String $object, String $where, int $limit=10)
    {
        // default limit set to 10 to prevent catastrophic unwanted deletions
        $result = $this->getDatabaseRecords($object,
            array('id'), $where, $limit, True);
        extract($result);
        if($count<=0)
        {
            echo "No objects of type $object where $where, nothing to delete!\n";
            return;
        }
        $ids = array();
        foreach ($records as $element) {
            $ids[] = $element->Id;
        }
        if($count>=$limit) {
            echo "deleting first ";
        } else {
            echo "deleting all ";
        }
        echo "$count records of type $object where $where (limit $limit)\n";
        $response = $this->connection()->delete($ids);
        $success = 0;
        $failure = 0;
        $failures = array();
        foreach ($response as $element) {
            if($element->success==1){
                $success += 1;
            } else {
                $failure += 1;
                $failures[] = $element->id;
            };
        }
        echo "$success successes and $failure failures.\n";
        return $failures;
    }
}

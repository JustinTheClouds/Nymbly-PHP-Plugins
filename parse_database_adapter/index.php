<?php

use Parse\ParseClient;
use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseACL;
use Parse\ParsePush;
use Parse\ParseUser;
use Parse\ParseInstallation;
use Parse\ParseException;
use Parse\ParseAnalytics;
use Parse\ParseFile;
use Parse\ParseCloud;

/**
 * A database adapter to store your data in your parse.com apps
 */
class Plugin_parse_database_adapter extends Plugins {
    
    /**
     * Error code translations
     */
    private static $errorMessages = array(
        
    );
    
    /**
     * Initializes the parse client
     */
    public function __construct() {
        
        // Sessions must be started after autoloader
        App::sessionClose();
        
        // Setup parse autoloader
        require_once(self::getPluginPath() . DS . 'parse-php-sdk' . DS . 'autoload.php');
        
        // Restart session
        App::sessionStart();
        
        // Initialize parse
        ParseClient::initialize(self::getPluginSettings('application_id'), self::getPluginSettings('rest_api_key'), self::getPluginSettings('master_api_key'));
        
    }
    
    protected static function onModelLoad($model) {
        $query = new ParseQuery($model->getTable());
        // Set query params
        foreach($model->getData() as $column => $val) {
            // If this is key column, change column name to objectId
            if($model->getKey() == $column) {
                $query->equalTo('objectId', $val);
            } else {
                $query->equalTo($column, $val);
            }
        }
        try {
            $object = $query->first();
            if($object) {
                // Set properties on model
                foreach($model->getProperties() as $prop) {
                    $model->$prop = $object->get($prop);
                }
                // If the model has a key, set the objectId to the key property
                if($key = $model->getKey()) $model->$key = $object->getObjectId();
            }
        } catch (ParseException $ex) {
            $model->addError(self::getErrorMessage($ex));
        }
    }
    
    /**
     * Create or updates the object in the parse.com database
     * 
     */
    protected static function onModelSave($model) {
        // If we have a primary key and it is set, initial object with objectId
        if(($key = $model->getKey()) && $model->$key) {
            $object = new ParseObject($model->getTable(), $model->$key);
        } else {
            $object = new ParseObject($model->getTable());
        }
        // Set properties on parse object
        foreach($model->getProperties() as $prop) {
            // If a value is not set for this prop, then don't update it
            if(!$model->hasValue($prop)) continue;
            // If this is a the id key, then don't set it since, objectId is used for parse.com
            if($model->getKey() === $prop) continue;
            // Set all other props
            // If value is a parse ACL object, call setACL
            if(is_a($model->$prop, 'Parse\ParseACL')) {
                $object->setACL($model->$prop);
            } elseif(is_array($model->$prop)) {
                // Is this an assoc array
                if((bool)count(array_filter(array_keys($model->$prop), 'is_string'))) {
                    $object->setAssociativeArray($prop, $model->$prop);
                } else {
                    $object->setArray($prop, $model->$prop);
                }
            } else {
                $object->set($prop, $model->$prop);
            }
        }
        // Try to save the object
        try {
            $object->save(true);
            // If the model has a key, set the objectId to the key property
            if($key = $model->getKey()) $model->$key = $object->getObjectId();
        } catch (ParseException $ex) {  
            $model->addError(self::getErrorMessage($ex));
        }    
    }
    
    protected static function onModelDelete($model) {
        // If we have a primary key and it is set, initial object with objectId
        if(($key = $model->getKey()) && $model->$key) {
            $object = new ParseObject($model->getTable(), $model->$key);
            $object->destroy();
        }
    }
    
    protected static function onModelQuery($model, $params) {
        $query = new ParseQuery($model->getTable());
        if($params) {
            foreach($params as $key => $param) {
                self::addQueryParam($query, $key, $param);
            }
        }
        try {
            $results = $query->find();
            foreach($results as &$result) {
                $objectId = $result->getObjectId();
                $result = $result->getAll();
                $result[$model->getKey()] = $objectId;
            }
            return $results;
        } catch (ParseException $ex) {
            $model->addError(self::getErrorMessage($ex));
        }
    }
    
    private static function addQueryParam(&$query, $key, $param) {
        if(!is_array($param) || (is_object($param) && is_a($param, 'Parse\ParseObject'))) {
            $query->equalTo($key, $param);
        } else {
            if(is_array($param[0])) {
                foreach($param as $subParam) {
                    self::addQueryParam($query, $key, $subParam);
                }
            }
            switch($param['0']) {
                case '$exists':
                    $query->exists($key);
                break;
                case '$lt':
                    $query->lessThan($key, $param[1]);
                break;
                case '$gt':
                    $query->greaterThan($key, $param[1]);
                break;
                case '$lte':
                    $query->lessThanOrEqualTo($key, $param[1]);
                break;
                case '$gte':
                    $query->greaterThanOrEqualTo($key, $param[1]);
                break;
            }
        }
    }
    
    protected static function onModelRefresh($model) {
        if(($key = $model->getKey()) && $model->$key) {
            $object = new ParseObject($model->getTable(), $model->$key);
            try {
                $object->fetch();
                // Set properties on model
                foreach($model->getProperties() as $prop) {
                    $model->$prop = $object->get($prop);
                    // If this prop is a ParseObject, fetch it also
                    if(is_a($object->get($prop), 'Parse\ParseObject')) {
                        $model->$prop->fetch();
                    }
                }
                // If the model has a key, set the objectId to the key property
                if($key = $model->getKey()) $model->$key = $object->getObjectId();
            } catch(ParseException $ex) {
                $model->addError(self::getErrorMessage($ex));
            }
        }
    }
    
    protected static function getErrorMessage($ex) {
        // Check if we have a custom message for this error code
        if(isset(self::$errorMessages[$ex->getCode()])) {
            return self::$errorMessages[$ex->getCode()];
        }
        return $ex->getMessage();
    }    
}

?>
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
     * Amount of request made
     */
    private static $reqCount = 0;
    
    /**
     * Each req made
     */
    private static $requests = array();
    
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
        if(App::isLive() || (App::isDeveloper() && SEF::getOption() == 'admin' && !App::get('dev', 'request.get')) || App::get('live', 'request.get')) {
            $live = self::getPluginSettings('live');
            ParseClient::initialize($live['application_id'], $live['rest_api_key'], $live['master_api_key']);
        } else {
            $dev = self::getPluginSettings('dev');
            ParseClient::initialize($dev['application_id'], $dev['rest_api_key'], $dev['master_api_key']);
        }
        
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
            self::trackRequest('onModelLoad', func_get_args());
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
     * Takes an array of phrases and converts them to keywords, rmeoving all stop wrods
     * @param   Array $phrases Phrases to grab keywords from
     * @returns Array All keywords from phrases to be saved
     */
    private static function getKeywords($phrases) {
        // Generate keywords
        $keywords = array();
        // Loop each phrase
        foreach($phrases as $phrase) {
            if(!(trim($phrase))) continue;
            $words = str_replace('+', ' ', array_filter(explode(' ', str_replace(self::getPluginSettings('keywordRemovedChars'), ' ', $phrase))));
            $keywords = array_merge($keywords, $words);
        }
        // Lowercase all keywords
        $keywords = array_map('strtolower', $keywords);
        // Filter stop words
        $keywords = array_values(array_filter($keywords, function($val) { return !in_array($val, self::$stopWords);     }));
        return $keywords;
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
            } elseif(is_a($model->$prop, 'Parse\ParseRelation')) {
            
            // Is there a keywords property?
            } elseif($prop == 'keywords') {
                $object->setArray($prop, self::getKeywords($model->$prop));
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
            self::trackRequest('onModelSave', func_get_args());
            // TODO dont alaways user master, need easy way of forcing master when needed
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
            self::trackRequest('onModelDelete', func_get_args());
            $object->destroy();
        }
    }
    
    // TODO compound queires
    protected static function onModelQuery($model, $params, $opts) {
        // Is this a basic query with no contraints
        if(!$params) {
            $query = new ParseQuery($model->getTable());
        // Is this a query with contraints
        } else {
            
            // Is this a single query
            if((bool)count(array_filter(array_keys($params), 'is_string'))) {
                
                $query = new ParseQuery($model->getTable());
                foreach($params as $key => $param) {
                    self::addQueryParam($query, $key, $param, $model);
                }

            // Is this a compound batch query
            } else {
                
                $queries = array();
                
                // Build each query
                foreach($params as $subQuery) {
                    $query = new ParseQuery($model->getTable());
                    foreach($subQuery as $key => $param) {
                        self::addQueryParam($query, $key, $param, $model);
                    }
                    $queries[] = $query;
                }
                
                $query = ParseQuery::orQueries($queries);
            }
        }
        // Add query opts if set
        if($opts) {
            foreach($opts as $type => $value) {
                self::addQueryOpt($query, $type, $value);
            }
        }
        try {
            self::trackRequest('onModelQuery', func_get_args());
            //echo "<pre>"; print_r($query); echo "</pre>";
            $results = $query->find();
            foreach($results as &$result) {
                $objectId = $result->getObjectId();
                $updatedAt = $result->getUpdatedAt();
                $createdAt = $result->getCreatedAt();
                $result = $result->getAll();
                $result['updatedAt'] = $updatedAt;
                $result['createdAt'] = $createdAt;
                $result[$model->getKey()] = $objectId;
            }
            return $results;
        } catch (ParseException $ex) {
            $model->addError(self::getErrorMessage($ex));
        }
    }
    
    private static function addQueryOpt(&$query, $type, $value) {
        switch($type) {
            case '$select':
                if(!$value) break;
                $query->select($value);
            break;
            case '$limit':
                if(!$value) break;
                $query->limit($value);
            break;
            case '$skip':
                if(!$value) break;
                $query->skip($value);
            break;
            case '$order':
                if(!is_array($value)) break;
                if($value[0] == 'desc') {
                    $query->descending($value[1]);
                } elseif($value[0] == 'asc') {
                    $query->ascending($value[1]);
                }
            break;
        }
    }
    
    private static function addQueryParam(&$query, $key, $param, $model) {
        
        if(($key == $model->getKey())) $key = 'objectId';
        
        // Are we checking if the $key == $param?
        if(!is_array($param) || (is_object($param) && is_a($param, 'Parse\ParseObject'))) {
            $query->equalTo($key, $param);
        // Or are we running a more complex check on the $key
        } else {
            
            foreach($param as $type => $subParams) {
                switch($type) {
                    case '$include':
                        if(!$subParams) break;
                        $query->includeKey($key);
                    break;
                    case '$or':
                        $query->containedIn($key, $subParams);
                    break;
                    case '$nor':
                        $query->notContainedIn($key, $subParams);
                    break;
                    case '$all':
                        $query->containsAll($key, $subParams);
                    break;
                    case '$exists':
                        if(!$subParams) break;
                        $query->exists($key);
                    break;
                    case '$doesNotExist':
                        if(!$subParams) break;
                        $query->doesNotExist($key);
                    break;
                    case '$lt':
                        $query->lessThan($key, $subParams);
                    break;
                    case '$gt':
                        $query->greaterThan($key, $subParams);
                    break;
                    case '$lte':
                        $query->lessThanOrEqualTo($key, $subParams);
                    break;
                    case '$gte':
                        $query->greaterThanOrEqualTo($key, $subParams);
                    break;
                    case '$e':
                        $query->equalTo($key, $subParams);
                    break;
                    case '$ne':
                        $query->notEqualTo($key, $subParams);
                    break;
                }
            }
        }
    }
    
    protected static function onModelRefresh($model) {
        if(($key = $model->getKey()) && $model->$key) {
            $object = new ParseObject($model->getTable(), $model->$key);
            try {
                self::trackRequest('onModelRefresh', func_get_args());
                $object->fetch();
                // Set properties on model
                foreach($model->getProperties() as $prop) {
                    $model->$prop = $objectProp = $object->get($prop);
                    // If this prop is a ParseObject, fetch it also 
                    if(is_a($objectProp, 'Parse\ParseObject')) {
                        $model->$prop->fetch();
                    }       
                    if(is_array($objectProp) && is_a(reset($objectProp), 'Parse\ParseObject')) {
                        foreach($objectProp as $arrayObject) {
                            $arrayObject->fetch();
                        }
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
    
    private static function trackRequest($func, $request) {
        if(!App::isDeveloper()) return;
        self::$reqCount++;
        self::$requests[$func][] = array($request, debug_backtrace());
    }
    
    /**
     * Add custom Steam tab to admin bar
     */
    public static function onAdminBarSetUpTabs($tabs) {
        array_unshift($tabs, array(
            'label' => 'Parse',
            'class' => 'Plugin_parse_database_adapter', 
            'labelMethod' => 'getAdminBarParseLabel',
            'contentMethod' => 'getAdminBarParseContent'
        ));
        return $tabs;
    }
    /**
     * Get the name of the custom Steam Tab with count bubble
     */
    public static function getAdminBarParseLabel() {
        $count = self::$reqCount;
        if($count === 0) return false;
        return '<span id="admin-bar-tabs-debugs-count" class="admin-bar-tabs-count">' . $count . '</span>' . App::_('', 'Parse Reqs');
    }
    /**
     * Get content of the steam tab (copied from debug tab content)
     */
    public static function getAdminBarParseContent() {
        $content = '';
        foreach(self::$requests as $func => $requests) {  
            foreach($requests as $request) {  
                $content .= '<div class="admin-bar-debug-wrap">';
                    $content .= '<div class="admin-bar-debug-title">';
                        $content .= $func;
                    $content .= '</div>';
                    $content .= '<div class="admin-bar-debug-content">';
                        $content .= '<div class="admin-bar-debug-location">';
                            $content .= 'Args';
                        $content .= '</div>';
                        $content .= '<pre>' . print_r($request[0], true) . '</pre>';
                        $content .= '<pre>' . print_r($request[1], true) . '</pre>';
                    $content .= '</div>';
                $content .= '</div>';
            }
        }
        return $content;
    }
    
    protected static $stopWords = array("a", "a's", "able", "about", "above", "according", "accordingly", "across", "actually", "after", "afterwards", "again", "against", "ain't", "all", "allow", "allows", "almost", "alone", "along", "already", "also", "although", "always", "am", "among", "amongst", "an", "and", "another", "any", "anybody", "anyhow", "anyone", "anything", "anyway", "anyways", "anywhere", "apart", "appear", "appreciate", "appropriate", "are", "aren't", "around", "as", "aside", "ask", "asking", "associated", "at", "available", "away", "awfully", "be", "became", "because", "become", "becomes", "becoming", "been", "before", "beforehand", "behind", "being", "believe", "below", "beside", "besides", "best", "better", "between", "beyond", "both", "brief", "but", "by", "c'mon", "c's", "came", "can", "can't", "cannot", "cant", "cause", "causes", "certain", "certainly", "changes", "clearly", "co", "com", "come", "comes", "concerning", "consequently", "consider", "considering", "contain", "containing", "contains", "corresponding", "could", "couldn't", "course", "currently", "definitely", "described", "despite", "did", "didn't", "different", "do", "does", "doesn't", "doing", "don't", "done", "down", "downwards", "during", "each", "edu", "eg", "eight", "either", "else", "elsewhere", "enough", "entirely", "especially", "et", "etc", "even", "ever", "every", "everybody", "everyone", "everything", "everywhere", "ex", "exactly", "example", "except", "far", "few", "fifth", "first", "followed", "following", "follows", "for", "former", "formerly", "forth", "four", "from", "further", "furthermore", "get", "gets", "getting", "given", "gives", "go", "goes", "going", "gone", "got", "gotten", "greetings", "had", "hadn't", "happens", "hardly", "has", "hasn't", "have", "haven't", "having", "he", "he's", "hello", "help", "hence", "her", "here", "here's", "hereafter", "hereby", "herein", "hereupon", "hers", "herself", "hi", "him", "himself", "his", "hither", "hopefully", "how", "howbeit", "however", "i'd", "i'll", "i'm", "i've", "ie", "if", "ignored", "immediate", "in", "inasmuch", "inc", "indeed", "indicate", "indicated", "indicates", "inner", "insofar", "instead", "into", "inward", "is", "isn't", "it", "it'd", "it'll", "it's", "its", "itself", "just", "keep", "keeps", "kept", "know", "knows", "known", "last", "lately", "later", "latter", "latterly", "least", "less", "lest", "let", "let's", "like", "liked", "likely", "little", "look", "looking", "looks", "ltd", "mainly", "many", "may", "maybe", "me", "mean", "meanwhile", "merely", "might", "more", "moreover", "most", "mostly", "much", "must", "my", "myself", "name", "namely", "nd", "near", "nearly", "necessary", "need", "needs", "neither", "never", "nevertheless", "next", "nine", "no", "nobody", "non", "none", "noone", "nor", "normally", "not", "nothing", "novel", "now", "nowhere", "obviously", "of", "off", "often", "oh", "ok", "okay", "old", "on", "once", "one", "ones", "only", "onto", "or", "other", "others", "otherwise", "ought", "our", "ours", "ourselves", "out", "outside", "over", "overall", "own", "particular", "particularly", "per", "perhaps", "placed", "please", "plus", "possible", "presumably", "probably", "provides", "que", "quite", "qv", "rather", "rd", "re", "really", "reasonably", "regarding", "regardless", "regards", "relatively", "respectively", "right", "said", "same", "saw", "say", "saying", "says", "second", "secondly", "see", "seeing", "seem", "seemed", "seeming", "seems", "seen", "self", "selves", "sensible", "sent", "serious", "seriously", "several", "shall", "she", "should", "shouldn't", "since", "six", "so", "some", "somebody", "somehow", "someone", "something", "sometime", "sometimes", "somewhat", "somewhere", "soon", "sorry", "specified", "specify", "specifying", "still", "sub", "such", "sup", "sure", "t's", "take", "taken", "tell", "tends", "th", "than", "thank", "thanks", "thanx", "that", "that's", "thats", "the", "their", "theirs", "them", "themselves", "then", "thence", "there", "there's", "thereafter", "thereby", "therefore", "therein", "theres", "thereupon", "these", "they", "they'd", "they'll", "they're", "they've", "think", "third", "this", "thorough", "thoroughly", "those", "though", "three", "through", "throughout", "thru", "thus", "to", "together", "too", "took", "toward", "towards", "tried", "tries", "truly", "try", "trying", "twice", "two", "un", "under", "unfortunately", "unless", "unlikely", "until", "unto", "up", "upon", "us", "use", "used", "useful", "uses", "using", "usually", "value", "various", "very", "via", "viz", "vs", "want", "wants", "was", "wasn't", "way", "we", "we'd", "we'll", "we're", "we've", "welcome", "went", "were", "weren't", "what", "what's", "whatever", "when", "whence", "whenever", "where", "where's", "whereafter", "whereas", "whereby", "wherein", "whereupon", "wherever", "whether", "which", "while", "whither", "who", "who's", "whoever", "whole", "whom", "whose", "why", "will", "willing", "wish", "with", "within", "without", "won't", "wonder", "would", "would", "wouldn't", "yes", "yet", "you", "you'd", "you'll", "you're", "you've", "your", "yours", "yourself", "yourselves", "zero");
}

?>
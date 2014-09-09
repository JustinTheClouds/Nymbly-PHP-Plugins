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
     * Initializes the parse client
     */
    public function __construct() {
        
        // Sessions must be started after autoloader
        session_write_close();
        
        // Setup parse autoloader
        require_once(self::getPluginPath() . DS . 'parse-php-sdk' . DS . 'autoload.php');
        
        // Restart session
        session_start();
        
        // Initialize parse
        ParseClient::initialize(self::getPluginSettings('application_id'), self::getPluginSettings('rest_api_key'), self::getPluginSettings('master_api_key'));
        
    }
    
    protected static function onModelLoad($model) {
        $query = new ParseQuery($model->getTable());
        try {
            $object = $query->get("UprHxvxhFC");
            // Set properties on model
            foreach($model->getProperties() as $prop) {
                $model->$prop = $object->get($prop);
            }
        } catch (ParseException $ex) {
            var_dump($ex);
            // The object was not retrieved successfully.
            // error is a ParseException with an error code and message.
        }
    }
    
    protected static function onModelSave($model) {
        // If we have a primary key and it is set, initial object with objectId
        if(($key = $model->getKey()) && $model->$key) {
            $object = new ParseObject($model->getTable(), $model->$key);
        } else {
            $object = new ParseObject($model->getTable());
        }
        // Set properties on parse object
        foreach($model->getProperties() as $prop) {
            $object->set($prop, $model->$prop);
        }
        // Try to save the object
        try {
            $object->save();
            if($key = $model->getKey()) $model->$key = $object->getObjectId();
        } catch (ParseException $ex) {  
            // TODO handle errors
        }    
    }
    
    
    /**
     * Runtime Hooks
     * 
     * These hooks are executed on every request in the lisetd order
     */
    
    /**
     * Called when all plugins are initialized
     * 
     * @hook Action
     * @param Array $enabledPlugins An array of enabled plugins that app is being intialized with
     */
    protected static function onPluginsInit($enabledPlugins) {return $enabledPlugins;}
    
    /**
     * Called when the specified plugin is initialized. In this case, Plugin_parse_database_adapter.
     * 
     * @hook Action
     * @param Array $configs The plugins configs
     */
    protected static function onPluginInit_parse_database_adapter($configs) {return $configs;}
    
    /**
     * Called whenever the apps theme name is requested
     * 
     * Can be used to change an apps theme during runtime
     * 
     * @hook Filter
     * @param String $theme The theme name
     */
    protected static function onAppGetTheme($theme) {return $theme;}
    
    /**
     * Called before the view is output
     * 
     * @hook Action
     * @param String $method The method that will be used to display the view (displayAll, displayAJAX, displayJSON, displayJSONP)
     */
    protected static function onBeforeViewDisplay($method) {return $method;}
    
    /**
     * Called when generating the pages <title> tag
     * 
     * @hook Filter
     * @param String $title The current title that is set to be output
     */
    protected static function onViewGetHeadTitle($title) {return $title;}
    
    /**
     * Called when generating the pages <meta name="description"> tag
     * 
     * @hook Filter
     * @param String $description The current description that is set to be output
     */
    protected static function onViewGetHeadDescription($description) {return $description;}
    
    /**
     * Called after any core framework styles are appended
     * 
     * Note: This hook is already defined in the Plugin class. It will automatically
     * try to load any styles that are defined in the plugins config settings under
     * the styles property. The path to the style should be relative to the plugins directory.
     * For ex. if your plugin is Nymbly/application/plugins/parse_database_adapter/ and your styles are in
     * Nymbly/application/plugins/parse_database_adapter/css/ then you would add styles 
     * to be automatically loaded by calling
     * 
     * self::setPluginSettings('styles', array(
     *     'css/styles.css',
     *     '//someexternal.styles.com/styles.css'
     * ));
     * 
     * @hook Filter
     * @param Array $styles An array of styles after core styles are added to it
     */
    protected static function onViewGetHeadStyles($styles) {return $styles;}
    
    /**
     * Call when outputting the head tag content
     * 
     * @hook Filter
     * @param Array $headTags An array of each element to be output in the head in order
     */
    protected static function onViewGetHead($headTags) {return $headTags;}
    
    /**
     * Called after any core framework scripts are appended
     * 
     * Note: This hook is already defined in the Plugin class. It will automatically
     * try to load any scripts that are defined in the plugins config settings under
     * the scripts property. The path to the script should be relative to the plugins directory.
     * For ex. if your plugin is Nymbly/application/plugins/parse_database_adapter/ and your scripts are in
     * Nymbly/application/plugins/parse_database_adapter/js/ then you would add scripts
     * to be automatically loaded by calling
     * 
     * self::setPluginSettings('scripts', array(
     *     'js/myFunctions.js',
     *     '//someexternal.scripts.com/js.min.js'
     * ));
     * 
     * @hook Filter
     * @param Array $scripts An array of scripts after core scripts are added to it
     */
    protected static function onViewGetHeadScripts($scripts) {return $scripts;}
    
    /**
     * Currently the same as onViewParseTemplate
     */
    protected static function onViewGetContent ($content) {return $content;}
    
    /**
     * Called after any core framework scripts are appended
     * 
     * Note: This hook is already defined in the Plugin class. It will automatically
     * try to load any styles that are defined in the plugins config settings under
     * the style property. The path to the style should be relative to the plugins directory.
     * For ex. if your plugin is Nymbly/application/plugins/parse_database_adapter/ and your styles are in
     * Nymbly/application/plugins/parse_database_adapter/css/ then you would add styles 
     * to be automatically loaded by calling
     * 
     * self::setPluginSettings('styles', array(
     *     'css/styles.css',
     *     '//someexternal.styles.com/styles.css'
     * ));
     * 
     * @hook Filter
     * @param Array $styles An array of styles after core styles are added to it
     */
    protected static function onViewGetFooterScripts($scripts) {return $scripts;}
    
    /**
     * Called once all content is echoed to the browser
     * 
     * @hook Action
     */
    protected static function onAfterViewDisplay () {}
    
    /**
     * Method hooks
     * 
     * These hooks are applied when certain methods are ran
     */
    
    /**
     * The App::Debug method is useful for developers to debug data while
     * keeping it hidden from users. This hook is in place to allow plugin to build creative
     * things ontop of it.
     * 
     * Returning an empty string will cancel the debug from being displayed. Allowing plugins
     * to transform, relocate, restructure or do whatever they please with the debug.
     * 
     * @hook Filter
     * @param String $output    The original default debug that would be outputted
     * @param Mixed $data       The data passed to App::debug for debugging
     * @param String $title     An optional title given to this debug log
     * @param Array $backtrace  The debug_backtrace report
     */
    protected static function onAppDebug($output, $data, $title, $backtrace) {return $output;}
    
    /**
     * Called when all errors are about to be output
     * 
     * Returning an empty string will cancel the errors from being displayed. Allowing plugins
     * to transform, relocate, restructure or do whatever they please with the errors.
     * 
     * @hook Filter
     * @param Array $errors An array of all errors that occurred during runtime
     */
    protected static function onErrorDisplayErrors($errors) {return $errors;}
    
    /**
     * Called when the main template file is loaded
     * 
     * This hook can be used to add/alter contents of a template before any variables
     * are parsed.
     * 
     * Note: This differs from onViewLoadView because a template is only loaded once.
     * Each page may load many views, but they will all only be placed into 1 template.
     * 
     * @hook Filter
     * @param String $template The returned template before parsing
     * @param String $file     The file name of the template that was loaded
     */
    protected static function onViewLoadTemplate($template, $file) {return $template;}
    
    /**
     * Called when view file is loaded
     * 
     * This hook can be used to add/alter contents of the view before any variables
     * are parsed.
     * 
     * @hook Filter
     * @param String $view The returned view before parsing
     * @param String $file The file name of the template that was loaded
     */
    protected static function onViewLoadView($view, $file) {return $view;}
    
    /**
     * Called after a template or view has been parsed.
     * 
     * @hook Filter
     * $param String $content The content after the template/view has been parsed
     */
    protected static function onViewParseTemplate($content) {return $content;}
    
}

?>
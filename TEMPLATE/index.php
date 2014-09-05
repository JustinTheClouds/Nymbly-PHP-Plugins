<?php

/**
 * Just template for starting your own plugins.
 * This has every hook defined for you. It's recommended that you
 * remove the hooks that are not being used.
 */
class Plugin_TEMPLATE extends Plugins {
    
    public function __construct() {
        
        /**
         * Disabling this plugin since it's only a template.
         * You'll want to remove this line once you copy and rename this plugin
         */
        self::unregisterPlugin();
        
    }
}

?>
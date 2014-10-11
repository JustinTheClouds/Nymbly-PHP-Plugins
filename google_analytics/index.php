<?php

/**
 * Just template for starting your own plugins.
 * This has every hook defined for you. It's recommended that you
 * comment out the hooks that are not being used.
 */
class Plugin_google_analytics extends Plugins {
    
    public function __construct() {
        
    }
    
    /**
     * Runtime Hooks
     * 
     * These hooks are executed on every request in the lisetd order
     */
    
    /**
     * Called when the specified plugin is initialized. In this case, Plugin_google_analytics.
     * 
     * @hook Action
     * @param Array $configs The plugins configs
     */
    protected static function onPluginInit_google_analytics($configs) {
    
        // Make sure the users ID setting is set
        if(!isset($configs['id']) || empty($configs['id']) || !App::isLive()) self::unRegisterPlugin();
    
    }
    
    /**
     * Call when outputting the head tag content
     * 
     * @hook Filter
     * @param Array $headTags An array of each element to be output in the head in order
     */
    protected static function onViewGetHead($headTags) {
        $headTags[] = "<!-- GOOGLE ANALYTICS -->
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
  ga('create', '" . self::getPluginSettings('id') . "', 'auto');
  ga('send', 'pageview');
</script>
<!-- END GOOGLE ANALYTICS -->";
        return $headTags;
    }
    
}

?>
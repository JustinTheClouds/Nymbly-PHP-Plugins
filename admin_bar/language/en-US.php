<?php
/**
 * Use this as an example lang file
 */
return array(
    /**
     * Regular strings
     * 
     * App::_('Updates');
     */
    'Updates' => 'Updates',
    'Run Updates' => 'Run Updates',
    'Failed with code %d' => 'Failed with code %d',
    /**
     * This is how to handle plural forms of phrases
     * 
     * App::_('%s update is available', '%s updates are available', $number);
     */
    '%s update is available' => array(
        /**
         * Will be returned if $number === 1
         */
        '%s update is available',
        /**
         * Willb e returned if $number !== 1
         */
        '%s updates are available'
    )
);
?>
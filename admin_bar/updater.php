<?php

class Plugin_admin_bar_updater {
    
    /**
     * Holds and error if it one occurs during execution
     */
    private static $_error = null;
    
    /**
     * Calls Plugin_admin_bar::get method for namespaced properties
     * 
     * @see Plugins::get()
     */
    public static function get($name, $type='session', $default=null) {
        return Plugin_admin_bar::get($name, $type, $default);
    }
    
    /**
     * Calls Plugin_admin_bar::set method for namespaced properties
     * 
     * @see Plugins::set()
     */
    public static function set($name, $val, $type='session', $timeout=null) {
        return Plugin_admin_bar::set($name, $val, $type, $timeout);
    }
    
    /**
     * Calls Plugin_admin_bar::_unset method for namespaced properties
     * 
     * @see Plugins::_unset()
     */
    public static function _unset($name, $type='session') {
        return Plugin_admin_bar::_unset($name, $type);
    }
    
    public static function _() {
        return call_user_func_array(array('Plugin_admin_bar', '_'), func_get_args());
    }
    
    /**
     * Checks if any updates are available
     * 
     * @returns Array Returns the updates array
     */
    public static function check() {
        
        $updates = array(
            'core' => false,
            'total' => 0
        );
        
        // Check core for updates
        $currentInfo = json_decode(file_get_contents(DIR_ROOT.DS.'version.json'), true);
        $serverInfo = json_decode(file_get_contents($currentInfo['check']), true);
        
        // Is the server version newer
        if(version_compare($serverInfo['version'], $currentInfo['version']) === 1) {
            $serverInfo['current'] = $currentInfo;
            $updates['core']['library'] = $serverInfo;
            $updates['total']++;
        }
        
        // Check for plugin updates
        $plugins = Plugins::getEnabledPlugins();
        
        if(!$plugins) return $updates;
        
        $updates['plugins'] = array();
        foreach($plugins as $plugin => $configs) {
            
            $currentInfo = json_decode(file_get_contents(DIR_PLUGINS.DS.$plugin.DS.'version.json'), true);
            $serverInfo = json_decode(file_get_contents($currentInfo['check']), true);

            // Is the server version newer
            if(version_compare($serverInfo['version'], $currentInfo['version']) === 1) {
                $serverInfo['current'] = $currentInfo;
                $updates['plugins'][$plugin] = $serverInfo;
                $updates['total']++;
            }
            
        }
        
        return $updates;
        
    }

    /**
     * Public method to begin the update process if updates exist
     */
    public static function begin() {
        
        // Don't allow master build to update itself
        if(App::get('appName', 'configs') == 'Nymbly PHP') {
            trigger_error(App::_('', 'Master build cannot update itself'));
            return;
        }
        
        $check = self::check();
        
        // If no updates are available then do nothing
        if(!$check['total']) return;
        
        // Check if we are already updating
        if(self::get('updating_in_progress')) return;
        
        // Begin updating process, timeout in case something errors out, an update can be attemped again in an hour
        self::set('updating_in_progress', true, 'session', 3600);
        
        // Clear update log
        self::set('update_status', array());
        if(self::get('dry_run', 'request.get')) self::log('**** DRYRUN ****');
        self::log('Beginning updates');
        
        // Loop selected updates
        $selected = self::get('updates', 'request.post');
        
        foreach($selected as $type => $updates) {
            
            // Loop updates for this
            foreach($updates as $name => $update) {
                
                // Type specifc configs
                switch($type) {
                    case 'core':
                        $dirFrom = 'library';
                        $extract = 'Nymbly-PHP-master/library';
                        $extractTo = DIR_ROOT.DS.'library'.DS;
                    break;
                    case 'plugins':
                        $dirFrom = 'application'.DS.'plugins'.DS.$name.DS;
                        $extract = $name;
                        $extractTo = DIR_PLUGINS.DS.$name.DS;
                    break;
                    default:
                        self::$_error = sprintf(self::_('No update support for %s:%s yet'), $type, $name);
                        self::handleError(sprintf(Plugin_admin_bar::_('Error updating up %s:%s - Cancelling this update'), $type, $name));
                        continue;
                    break;
                }
                
                self::log(sprintf(self::_('Updating %s:%s'), $type, $name));
                
                // Make sure an update for this selected update actually doe exist
                if(!isset($check[$type][$name])) {
                    self::$_error = sprintf(self::_('No update exists for %s:%s'), $type, $name);
                    self::handleError(sprintf(Plugin_admin_bar::_('Error updating up %s:%s - Cancelling this update'), $type, $name));
                    continue;
                }

                // Run backup for this update
                self::backup($type, $name, $check[$type][$name]);
                
                // Break point: Check for error
                if(self::handleError(sprintf(Plugin_admin_bar::_('Error backing up %s:%s - Cancelling this update'), $type, $name))) continue;
                
                // Download this update
                self::download($type, $name, $check[$type][$name], $extract, $extractTo);
                
                // Break point: Check for error
                if(self::handleError(sprintf(Plugin_admin_bar::_('Error downloading up %s:%s - Cancelling this update'), $type, $name))) continue;
                
                // Update version.json file
                self::updateVersion($check[$type][$name]);
                
            }
        }
        
        // End updates
        self::log('Finishing updates');
        
        var_dump(self::status());
        
        self::_unset('updating_in_progress');
        self::_unset('update_status');
    }
 
    /**
     * Backs up the library dir or specified plugin dir
     * 
     * @param   String   $type Should be 'library' or 'plugin'
     * @param   String   $name The name of the plugin to backup or null for library
     * @param   Array    $name The update version info
     * 
     * @returns Boolean If the backup was a success or not
     */
    private static function backup($type, $name, $update) {
        
        self::log(sprintf(self::_('Backing up %s:%s'), $type, $name));
        
        // Backup file name
        $file = DIR_ROOT.DS.'backups'.DS.$type.DS.$name.'-'.time().'.zip';
        
        self::log(sprintf(self::_('Backing up to: %s'), $file));
        
        // Directory to back up to
        $dirTo = DIR_ROOT.DS.'backups'.DS.$type;
        
        // Directory to backup from
        if($type == 'core') {
            $dirFrom = 'library';
        } elseif($type == 'plugins') {
            $dirFrom = 'application'.DS.'plugins'.DS.$name.DS;
        }
        
        // Create backup dir if doesnt exist
        if(!is_dir(DIR_ROOT.DS.'backups')) mkdir(DIR_ROOT.DS.'backups');
        
        // Create dir for this type of backup
        if(!is_dir($dirTo)) mkdir($dirTo);
        
        // Don't create zip on dryruns
        if(!self::get('dry_run', 'request.get')) {
            
            // Create zip for this backup instance
            $zip = new ZipArchive();
            $zipRet = $zip->open($file, ZipArchive::CREATE);

            if($zipRet !== TRUE) {
                self::$_error = sprintf(Plugin_admin_bar::_('Backup creation for %s:%s failed with code %d'), $type, $name, $zipRet);
            } else {
                self::addDirectoryToZip($zip, $dirFrom, strlen($dirFrom) + 1);
            }

            $zip->close();
            
        }
        
        return self::checkBackup($type, $name, $file, $dirFrom);
    }
    
    /**
     * Checks if a backedup directory succeeded or not.
     * 
     * This is done by verifying that every file in the original directory
     * that is being backed up exists in the backed up directory.
     * 
     * @param String $type Should be 'library' or 'plugin'
     * @param String $name The name of the update
     * 
     * @returns Boolean If the backup succeeded or not
     */
    private static function checkBackup($type, $name, $file, $dirFrom) {
        
        self::log(sprintf(self::_('Verifying backup %s:%s'), $type, $name));
        
        // If dry run, skip check since we know zip doesnt exit
        if(!self::get('dry_run', 'request.get')) {
            
            // Does the backed up file exist
            if(!file_exists($file)) {
                self::$_error = sprintf(Plugin_admin_bar::_('Backup creation for %s:%s failed. The backup file does not exist.'), $type, $name);
                return false;
            }

            // Open backed up zip
            $zip = new ZipArchive();
            $zipRet = $zip->open($file);

            if($zipRet !== TRUE) {
                self::$_error = sprintf(Plugin_admin_bar::_('Backup creation for %s:%s failed. Error opening backup with code %d'), $type, $name, $zipRet);
                return false;
            }

            // Loop original directory
            foreach(glob($dirFrom . '/*') as $file) {
                // Try to read file from zip
                if($zip->locateName(substr($file, strlen($dirFrom) + 1)) === false) {
                    // If file wasn't found in back up, return false
                    self::$_error = sprintf(Plugin_admin_bar::_('Backup missing file: %s'), $file);
                    return false;
                }
            }
            
        }
        
        self::log(sprintf(self::_('Backed up %s:%s successfully'), $type, $name));
    }
    
    /**
     * Downloads and extracts the update files
     * 
     * @param   String   $type Should be 'library' or 'plugin'
     * @param   String   $name The name of the plugin to backup or null for library
     * @param   Array    $name The update version info
     */
    private static function download($type, $name, $update, $extract, $extractTo) {
        
        self::log(sprintf(self::_('Downloading %s:%s'), $type, $name));
        
        self::log(sprintf(self::_('Download From: %s'), $update['download']));
        
        // Don't download updates on dry run
        if(!self::get('dry_run', 'request.get')) {
            
            // Check for tmp dir
            $dir = DIR_ROOT.DS.'tmp';
            if(!is_dir($dir)) mkdir($dir);
            
            // Download update to temp file
            $tempFile = tempnam($dir, 'NYMBLY');
            file_put_contents($tempFile, file_get_contents($update['download']));

            // Open updated zip
            $zip = new ZipArchive();
            $zipReturn = $zip->open($tempFile);
            if($zipReturn !== true) {
                self::$_error = sprintf(Plugin_admin_bar::_('Download for %s:%s failed. Error opening download with code %d'), $type, $name, $zipRet);
                return false;
            }
            
            self::log(sprintf(self::_('Delete files from %s'), $extractTo));
            
            // Delete old files
            self::deleteFromDirectory($extractTo);

            self::log(sprintf(self::_('Extract folder: %s into %s'), $extract, $extractTo));
            
            // Extract updated zip to overwrite old files
            self::extractDirectoryTo($zip, $extract, $extractTo);
            
            // Close zip
            $zip->close();
            
            // Delete temp file
            unlink($tempFile);
            
            // Delete tmp dir if empty
            if(count(scandir($dir)) == 2) rmdir($dir);
        }
    }
    
    private static function updateVersion($update) {
        
    }
       
    /**
     * Recursively add a directory into a zip archive
     * @param ZipArchive &$zip     The ZipArchive to add the directory to
     * @param String $dir      The path of the folder to add
     * @param Number $base = 0 The length of the base path to ignore
     */
    private static function addDirectoryToZip(&$zip, $dir, $base = 0) {
        foreach(glob($dir . '/*') as $file) {
            if(is_dir($file))
                self::addDirectoryToZip($zip, $file, $base);
            else
                $zip->addFile($file, substr($file, $base));
        }
    }
    
    /**
     * Extracts a specific directory from an archive to the specified path
     * 
     * @param ZipArchive $zip The zip archive to extract from
     * @param String $dir The directory to extract
     * @param String $to  The directory to extract to
     */
    private static function extractDirectoryTo($zip, $dir, $to) {
        for($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            //Use strpos() to check if the entry name contains the directory we want to extract
            if (strpos($entry, $dir) !== false) {
                // Extract the file to folder
                var_dump($to . substr($entry, strlen($dir) + 1));
                //file_put_contents($to.$entry, $zip->getFromIndex($i));
            }
        }
    }
    
    /**
     * Deletes all files in directory recursively
     * 
     * @param String $dir The directory to delete all files from
     */
    private static function deleteFromDirectory($dir) {
        foreach ($iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            if($item->isDir()) {
                rmdir($dir.$iterator->getSubPathName());
            } else {
                unlink($dir.$iterator->getSubPathName());
            }
        }                                                    
    }
    
    /**
     * Return the update status log
     * 
     * @returns String The update status logs
     */
    private static function status() {
        return self::get('update_status');
    }
    
    /**
     * Adds new log to the update status log
     * 
     * @param String The message to log
     */
    private static function log($message) {
        self::set('update_status', array_merge(self::get('update_status'), array($message)));
    }
    
    /**
     * Check if an error exist and logs the error and the breakpoint message
     * 
     * @param   String $breakMessage The message to log after the error is logged
     * @returns Boolean  True if and error was found
     */
    private static function handleError($breakMessage) {
        if(self::$_error) {
            self::log(self::$_error);
            self::log($breakMessage);
            self::$_error = null;
            return true;
        }
        return false;
    }
}

?>
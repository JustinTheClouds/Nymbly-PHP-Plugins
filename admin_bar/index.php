<?php

defined('IN_APP') ? NULL : exit();

/**
 * Add an admin bar to the top of the page with tools for developers.
 * 
 * The key feature of the admin bar is it is only shown to developer IP
 * addresses. This allows debugging/management on live sites without the worry of users
 * seeing any of it
 * 
 * This plugin ships with the framework to showcase how to use the event
 * system to write your own plugins. This can be copied, modified, added on
 * to in any way you please.
 * 
 * ** Tabs **
 * - Updates: (Only shows when at least 1 update is available). The updates tab 
 * will let the developer know if there are any
 * core updates, plugin updates, controller updates, library updates.
 * If any updates are available they can be applied automatically by selecting
 * them and pressing the update button.
 * 
 * - Debugs: The debug tab is used when debugging vars as you are writing your code.
 * By calling App::debug($ANY_DATA_HERE); You will have debugged to the debug tab.
 * 
 * - Errors: (Only shows when at least 1 error occurred). The errors tab will list
 * all errors that occurred during run time. This keeps the errors from runing the
 * structure of the site as well as keeping them hidden from end users.
 * 
 * - Events: This list the action/filter events that occurred during the pages execution, in order.
 * It also displays in what arguments we passed with each event. This is helpful
 * for writing/debugging plugins.
 * 
 * - Stats: That stats tab gives you a lot of information of the app during execution.
 * It tells you how long it took to run the app from start to finish, what var, classes, constants
 * that were defined.
 * 
 * - JSON: (Only shows during an AJAX request). This will show the responses from the
 * AJAX requests since the current page loaded
 * 
 * - Docs: This a vey powerful tab. This will show documentation on the whole framework, plugins,
 * and controller files. This is done automatically using the codes DocBlocks.
 *
 * @author Justin Carlson
 * @date 8/19/2014
 *
 */
class Plugin_admin_bar extends Plugins {

    /**
     * HTML content of the admin bar to be appended
     */
	private static $adminBar = '<div class="admin-bar-wrapper">';

    /**
     * Store errors caught
     */
	private static $errors = array();

    /**
     * Store events triggered
     */ 
	private static $events = array();
	
    /**
     * Store debugs sent
     */
	private static $debugs = array();
    
    /**
     * Store stats sent
     */
	private static $stats = array();
	
    /**
     * Store tools tab content
     */
	private static $tools = '';
	
    /**
     * Default tabs to initalize the admin bar with
     */
	private static $defaultTabs = array(
		'Updates'  => '{$admin_bar_updates}',
		'Debugs'   => '{$admin_bar_debugs}',
		'Errors'   => '{$admin_bar_errors}',
		'Events'   => '{$admin_bar_events}',
		'Stats'    => '{$admin_bar_stats}',
		'JSON'     => '{$admin_bar_json}',
		'Docs'     => '{$admin_bar_docs}',
		'Tools'    => '{$admin_bar_tools}'
	);
    
    /**
     * On plugin initialize
     * 
     * Disabled the plugin unless is developer
     * 
     * @return void
     */
    public function __construct() {
        
        // Unregister the plugin if it is not being used
		if(!App::isDeveloper()) {
			self::unRegisterPlugin();
			return;
		}
		
        // Add styles
        self::setPluginSettings('styles', array(
            'css/styles.css'
        ));
        
        // Add scripts
        self::setPluginSettings('scripts', array(
            '//code.jquery.com/jquery-1.11.0.min.js',
            'js/script.js'
        ));
        
		// Setup tabs
		self::setUpTabs();
		
		self::$adminBar .= '</div>';
        
        // Check if we are running an admin_bar_action
        $action = App::getRequest('get.admin_bar_action');
        if($action) {
            // Does action exist?
            if(method_exists(__CLASS__, $action . 'Action')) {
                // Run action
                call_user_func(array(__CLASS__, $action . 'Action'));
            }
            
        }
		
        // Grab update panel content
		self::assign('admin_bar_updates', self::getUpdates());
        
    }
	 
    private static function clearCacheAction() {
        App::_unset(null, 'session');
        self::$tools = "Cached cleared";
        self::$tools .= print_r($_SESSION, 1);
        self::$tools .= print_r(App::get(null), 1);
    }
    
    /**
     * Get the HTML for the update panel
     * 
     * @return string HTML output for the update panel
     * 
     * @author Justin Carlson
     * @date 8/19/2014
     */
    private static function getUpdates() {
        
        $output = '';
        
        // If we are not currently updating
        if(!App::getRequest('get.update')) {
            
            // Check for updates
            $updates = self::checkForUpdates();
            
            // If we have any updates available
            if($updates['total']) {
                
                // Output the title message
                $output .= '<div class="' . self::prefix('tab-title') . '">' . sprintf(self::_('%s update is available', '%s updates are available', $updates['total']), $updates['total']) . '</div>';
                
                // Build the update list
                $output .= '<table class="' . self::prefix('table') . '">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" name="admin_bar_updates_select_all" /></th>
                                        <th>New Version</th>
                                        <th>Current Version</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>';
                
                // Loop types of updates
                foreach($updates as $type => $update) {
                    if($type == 'total') continue;
                    // Output update type section
                    $output .= '    <tr>
                                        <td>' . ucfirst($type) . '</td>
                                    </tr>';
                    // Loop updates for type
                    if(isset($update[0])) {
                        
                        
                            
                    } else {
                        
                        $output .= '<tr>
                                        <td><input type="checkbox" name="" /></td>
                                        <td>' . $update['version'] . '</td>
                                    </tr>';
                        
                    }
                }
                                
                // Close table
                $output .= '
                                </tbody>
                            </table>';
                
                // Add update button
                $output .= '<a href="?admin_bar_action=beginUpdating" id="' . self::prefix('run-updates-button') . '" class="' . self::prefix('button') . '">' . self::_('Run Updates') . '</a>';
                
            // EVerything is up to date
            } else {
                
                $output .= '<div class="admin-bar-tab-title">' . self::_('Everything is up to date') . '</div>';
                
            }
            
        }
        
        return $output;
        
    }
    
    private static function beginUpdatingAction() {
        
        if(!App::get('admin_bar_running_update')) {
            
            // Start the update
            App::set('admin_bar_running_update', true);

            // Start the update log
            App::set('admin_bar_update_status', array('Beginning core update', 'Checking for core updates'));
            
            // Check for updates
            $updates = self::checkForUpdates();
            
            // Checking for core update
            if(is_array($updates['core'])) {
            
                // Backup current library
                App::set('admin_bar_update_status', array_merge(App::get('admin_bar_update_status'), array('Backing up core files')));

                if(!is_dir(DIR_ROOT.DS.'backups')) mkdir(DIR_ROOT.DS.'backups');
                // Create dir for this type of backup
                if(!is_dir(DIR_ROOT.DS.'backups'.DS.'core')) mkdir(DIR_ROOT.DS.'backups'.DS.'core');
                // Create zip for this backup instance
                $zip = new ZipArchive();
                $zipRet = $zip->open(DIR_ROOT.DS.'backups'.DS.'core'.DS.time().'.zip', ZipArchive::CREATE);
                if ($zipRet !== TRUE) {
                    trigger_error(sprintf(self::_('Failed with code %d'), $zipRet));
                } else {
                    $zip->addGlob('library'.DS.'*');
                    //$zip->close();
                }

                sleep(1);

                // Download update
                App::set('admin_bar_update_status', array_merge(App::get('admin_bar_update_status'), array('Downloading updated core files')));
                $updatedZip = file_get_contents($updates['core']['download']);
                
                // Save downlaod
                App::set('admin_bar_update_status', array_merge(App::get('admin_bar_update_status'), array('Saving downloaded zip files')));
                if(!is_dir(DIR_ROOT.DS.'updates')) mkdir(DIR_ROOT.DS.'updates');
                //file_put_contents(DIR_ROOT.DS.'updates'.DS.$updates['core']['version'].'.zip', $updatedZip);
                
                // Extract zip
                App::set('admin_bar_update_status', array_merge(App::get('admin_bar_update_status'), array('Extracting zip files')));
                $updatedZip = new ZipArchive();
                $updatedZip->open(DIR_ROOT.DS.'updates'.DS.$updates['core']['version'].'.zip');
                $updatedZip->extractTo(DIR_ROOT.DS.'updates');
                
                // Copy library directory
                App::set('admin_bar_update_status', array_merge(App::get('admin_bar_update_status'), array('Updating core files')));
                foreach ($iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DIR_ROOT.DS.'updates'.DS.'Nymbly-PHP-master'.DS.'library', RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
                    if($item->isDir()) {
                        mkdir(DIR_ROOT.DS.'updates'.DS.'testing'.DS.$iterator->getSubPathName());
                    } else {
                        copy($item, DIR_ROOT.DS.'updates'.DS.'testing'.DS.$iterator->getSubPathName());
                    }
                }                                                    
                
                App::set('admin_bar_update_status', array_merge(App::get('admin_bar_update_status'), array('Updating core version')));
                
                App::set('admin_bar_update_status', array_merge(App::get('admin_bar_update_status'), array('Deleting downloaded files')));
                
            }
            
            var_dump(App::get('admin_bar_update_status'));
            
            App::set('admin_bar_running_update', false);
            
        }
        App::set('admin_bar_running_update', false);
    }
    
    private static function checkUpdatingStatusAction() {
        var_dump(App::get(null));
    }
    
    /**
     * Checks core, plugins, controllers for updates
     * 
     * @return array Returns array of updates found with the latest versions and dowload urls
     * 
     * @author Justin Carlson
     * @date 8/19/2014
     */
    private static function checkForUpdates() {
        
        $updates = array(
            'core' => false,
            'total' => 0
        );
        
        // Check core for updates
        $currentInfo = json_decode(file_get_contents(DIR_ROOT.DS.'version.json'), true);
        $serverInfo = json_decode(file_get_contents($currentInfo['check']), true);
        
        // Is the server version newer
        if(version_compare($serverInfo['version'], $currentInfo['version']) === 1) {
            $updates['core'] = $serverInfo;
            $updates['total']++;
        }
        
        return $updates;
    }
	
    /**
     * Set up the admin bar tabs
     * 
     * Allow plugins to add their own tabs through the
     * onAdminBarSetUpTabs filter
     * 
     * @return <type>
     */
	private static function setUpTabs() {
	
		$tabs = Plugins::filter('onAdminBarSetUpTabs', self::$defaultTabs);
		
		// Do we have tabs?
		if($total = count($tabs)) {
		
			// Start tab nav list
			self::$adminBar .= '<ul class="admin-bar-tabs">';
			
			$buttons = "";
			$contents = "";
			foreach($tabs as $label => $content) {
				$buttons .= '<li><a href="#admin-bar-tab-' . strtolower($label) . '">' . self::_($label) . '</a></li>';
				$contents .= '<div id="admin-bar-tab-' . strtolower($label) . '" class="admin-bar-tab-content">' . $label . '<div>' . $content . '</div></div>';
			}
            
            // Add close button
            $buttons .= '<li><a href="#admin-bar-close-panel">X Close</a></li>';

			// Add tab buttons
			self::$adminBar .= $buttons;
			// End tab nav list
			self::$adminBar .= '</ul>';
			// Add tab contents
			self::$adminBar .= $contents;
			
			self::$adminBar .= '</div><!-- end .admin-bar-wrapper -->';

		} else {
			self::$adminBar = '';
		}
		
	}
    
    protected static function onBeforeViewDisplay() {
        
        // Create stats content
        $out = '';
        $constants = get_defined_constants(true);
        $constants = $constants['user'];
        $out .= "<strong>Constants</strong>";
        $out .= '<ul>';
        foreach($constants as $k => $v) {
            $out .= "<li><strong>$k:</strong> $v</li>";
        }
        $out .= '</ul>';
        self::$stats[] = $out;
        
        // Create Tools content
        $out = self::$tools;
        $out .= '<a href="?admin_bar_action=clearCache">Clear Cache</a>';
        self::assign('admin_bar_tools', $out);
    }
	
    /**
     * Append the admin bar to the content
     * 
     * @param <type> $content 
     * 
     * @return <type>
     */
	protected static function onViewLoadTemplate($content) {
        
        
		$errors = '';
		foreach(self::$errors as $error) {
			$errors .= print_r($error, 1);
		}
		self::assign('admin_bar_errors', $errors);
		self::assign('admin_bar_debugs', implode('<hr />', self::$debugs));
		self::assign('admin_bar_events', implode('<hr />', self::$events));
		self::assign('admin_bar_stats', implode('<hr />', self::$stats));
	
		return str_replace('{$output_content}', '{$output_content}' . self::$adminBar, $content);
	}
	
    /**
     * Handle errors
     * 
     * Handle erros caughts and append them to the errors tab
     * 
     * @param <type> $default 
     * @param <type> $errno 
     * @param <type> $errstr 
     * @param <type> $errfile 
     * @param <type> $errline 
     * 
     * @return <type>
     */
	protected static function onAppHandleError($default, $errno, $errstr, $errfile, $errline) {
		
		$info = debug_backtrace();
		
		$fh = fopen($errfile, 'r');
		$error = '<table class="error-reporting-table">';

		$errorData = array();
		$errorData['errorstr'] = $errstr;
		$errorData['errline'] = $errline;
		$errorData['errfile'] = $errfile;
		$errorData['errdebug'] = array();

		$i=1;
		while ((feof ($fh) === false) )
		{
			while ((feof ($fh) === false) && $i<($errline-3) )
			{
				fgets($fh);
				$i++;
			}
			
			$class = $i == $errline ? "errorLineError" : "#000000"; 
			
			$theData = fgets($fh);
			$error .= '<tr class="errorLine '.$class.'"><td class="errorLineNumber">' . $i . ':</td><td>' . htmlentities($theData) . '</td></tr>';
			$i++;
			
			while ((feof ($fh) === false) && $i>($errline+3) )
			{
				fgets($fh);
				$i++;
			}
		}
		
		$error .= '</table>';
		foreach($info as $v) {
			$line = isset($v['line']) ? $v['line']: 'empty';
			$file = isset($v['file']) ? $v['file']: 'empty';
			$errorData['errdebug'][] = "From line $line of $file";
		}
		$errorData['erroutput'] = $error;

		self::$errors[] = $errorData;
		
		fclose($fh);
		
		// Is this a fatal error?
		if($errno === 0) {
			return self::handleFatalError();
		} else {
			// This cancels the default error output
			// By returning false, we can cencel the default $returned value if one exists for a filter event
			return false;
		}
	}
		
    /**
     * Handle fatal errors
     * 
     * This cannot handle parse errors but will catch most other
     * fatal errors. The app will still crash but the output will still
     * be nicely formatted helpful error report
     * 
     * @return <type>
     */
	private static function handleFatalError() {
		$errors = '';
		foreach(self::$errors as $error) {
			$errors .= print_r($error, 1);
		}
		return '<style>' . file_get_contents(DIR_APP.DS.self::getPluginSettings('styles')) . '</style>' . $errors;
	}

    /**
     * Track all plugin events fired 
     * 
     * This will populate the events tab will all 
     * plugine events fired with their supplied params
     * 
     * @param <type> $action 
     * @param <type> $event 
     * 
     * @return <type>
     */
	protected static function onEventFired($action, $event) {
		
		$args = func_get_args();
		array_shift($args);

		// Should we only debug a specific event
		if($single = App::getRequest('get.debug_trace_event')) {
			if($event == $single) {
				call_user_func_array(array('self', 'logEvent'), $args);
			}
		} else {
			call_user_func_array(array('self', 'logEvent'), $args);
		}
		
	}

	protected static function onAppDebug($default, $debug) {
		self::$debugs[] = $default;
		return false;
	}
	
    /**
     * Log the event
     * 
     * @return <type>
     */
	private static function logEvent() {
		
		$args = func_get_args();
		$event = array_shift($args);
		$action = array_shift($args);
		
		self::$events[] = '
			<table class="debug-trace-table">
				<tr>
					<td>Event <strong>' . $event . '</strong> Fired (' . $action . ')</td>
				</tr>
				<tr>
					<td><pre>' . print_r($args, 1) . '</pre></td>
				</tr>
			</table>
		';
	
	}
    
    /**
     * Return text prefixed with the plugins name
     */
    private static function prefix($text) {
        return str_replace('_', '-', strtolower(self::getPluginName() . '-' . $text));
    }
	
}

?>
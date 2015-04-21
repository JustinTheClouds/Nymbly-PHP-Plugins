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
 * @TODO Use ajax for tab content instead of loading each tab on page load.
 * @TODO Fx this button for specific errors. EX. No template file error, clicking will create the template file.
 */
class Plugin_admin_bar extends Plugins {

    /**
     * HTML content of the admin bar to be appended
     */
	private static $adminBar = '<div class="admin-bar-wrapper">';
    
    /**
     * Application start time in microtime once plugins are initialized
     */
    private static $startTime;

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
     * Store updates found
     */
	private static $updates = null;
	
    /**
     * Store tools tab content
     */
	private static $tools = '';
	
    /**
     * Default tabs to initalize the admin bar with
     */
	private static $defaultTabs = array(
		'Updates',
		'Debugs',
		'Errors',
		'Events',
		'Stats',
		'JSON',
		'Docs',
		'Tools'
	);
    
    /**
     * On plugin initialize
     * 
     * Disabled the plugin unless is developer
     * 
     * 
     * @return void
     */
    public function __construct() {
        
        // Unregister the plugin if it is not being used
		if(!App::isDeveloper() || (App::isLive() && self::getPluginSettings('disableWhenLive'))) {
			self::unRegisterPlugin();
			return;
		}
        
        // Catch app start time
        self::$startTime = microtime(true);
		
        // Add styles
        self::setPluginSettings('styles', array(
            'css/styles.css'
        ));
        
        // Add scripts
        self::setPluginSettings('scripts', array(
            '//code.jquery.com/jquery-1.11.0.min.js',
            'js/script.js'
        ));
        
        // Check if we are running an admin_bar_action
        $action = self::get('action', 'request.get');
        if($action) {
            // Does action exist?
            if(method_exists(__CLASS__, $action . 'Action')) {
                // Run action
                call_user_func(array(__CLASS__, $action . 'Action'));
            }
            
        }
		
        // Check for updates
        self::$updates = self::checkForUpdates();
        
    }
    
    public static function assign() {
        $args = func_get_args();
        if(!isset($args[2])) array_push($args, null);
        if(!isset($args[3])) array_push($args, array('get', 'post'));
        call_user_func_array(array('parent', 'assign'), $args);
    }
	 
    private static function clearCacheAction() {
        App::_unset(null, 'session');
        self::$tools = "Cached cleared";
        self::$tools .= print_r($_SESSION, 1);
        self::$tools .= print_r(App::get(null), 1);
    }
    
    private static function beginUpdatingAction() {
        require_once(self::getPluginPath() . DS . 'updater.php');
        Plugin_admin_bar_updater::begin();
    }
    
    private static function checkUpdatingStatusAction() {
        require_once(self::getPluginPath() . DS . 'updater.php');
        $status = Plugin_admin_bar_updater::status();
        self::assign('status', $status, null, array('json'));
        if(empty($status)) {
            self::assign('completed', true, null, array('json'));
        }
    }
    
    private static function checkUpdatesAction() {
        self::_unset('updates_last_check');
        
    }
    
    private static function generatePluginAction() {
        $name = self::get('new_plugin_name', 'request.post');
        if(!$name) return;
        $formatted = str_replace(array(' ', '-'), '_', strtolower($name));
        // Create dir
        mkdir(DIR_PLUGINS.DS.$formatted);
        // Copy files
        file_put_contents(DIR_PLUGINS.DS.$formatted.DS.'index.php', str_replace('TEMPLATE', $formatted, file_get_contents(DIR_PLUGINS.DS.'TEMPLATE'.DS.'index.php')));
        file_put_contents(DIR_PLUGINS.DS.$formatted.DS.'version.json', str_replace('TEMPLATE', $formatted, file_get_contents(DIR_PLUGINS.DS.'TEMPLATE'.DS.'version.json')));
    }
    
    /**
     * Checks periodically for updates.
     * 
     * A session var will be stored fo 12 hours to avoid checking every request.
     * Updates will be checked twice daily.
     * 
     * Note: Updates are checked on on page load, NOT throuh a cron. So if the website is not loaded all day,
     * then the updater will not check until the next page visit. And updates are only checked if a developer
     * is loading the page since only a developer can run the updater.
     * 
     * @return array Returns array of updates found with the latest versions and dowload urls
     * 
     * @author Justin Carlson
     * @date 8/19/2014
     */
    private static function checkForUpdates() {
        
        return;
        
        if(!self::get('updates_last_check')) {
        
            require_once(self::getPluginPath().DS.'updater.php');
            
            $updates = Plugin_admin_bar_updater::check();
            
            // TODO Set timeout var for 12 hours
            self::set('updates_last_check', $updates, 'session', 15);
            
        } else {
         
            $updates = self::get('updates_last_check');
            
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
			foreach($tabs as $label) {
                
                $class = is_array($label) ? $label['class'] : 'self';
                $labelMethod = is_array($label) ? $label['labelMethod'] : 'get' . $label . 'Label';
                $contentMethod = is_array($label) ? $label['contentMethod'] : 'get' . $label . 'Content';
                $label = is_array($label) ? $label['label'] : $label;
                
                if(!($labelText = call_user_func(array($class, $labelMethod)))) continue;
                    
				$buttons .= '<li><a href="#admin-bar-tab-' . strtolower($label) . '">' . $labelText . '</a></li>';
				$contents .= '<div id="admin-bar-tab-' . strtolower($label) . '" class="admin-bar-tab-content">' . $label . '<div>' . call_user_func(array($class, $contentMethod)) . '</div></div>';
			}
            
            // Add close button
            $buttons .= '<li id="admin-bar-close-panel"><a href="#" onclick="return false;">X Close</a></li>';

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
    
    protected static function getUpdatesLabel() {
        $count = self::$updates['total'];
        if($count === 0) return false;
        return '<span id="' . self::prefix('tabs-updates-count') . '" class="' . self::prefix('tabs-count') . '">' . $count . '</span>' . self::_('Update', 'Updates', $count);
    }
    
    /**
     * Gets debug tab with number of debugs, tab is hidden if no debugs
     */
    protected static function getDebugsLabel() {
        $count = count(self::$debugs);
        if($count === 0) return false;
        return '<span id="' . self::prefix('tabs-debugs-count') . '" class="' . self::prefix('tabs-count') . '">' . $count . '</span>' . self::_('Debug', 'Debugs', $count);
    }
    
    /**
     * Gets errors tab with number of errors, tab is hidden if no errors
     */
    protected static function getErrorsLabel() {
        $count = count(self::$errors);
        if($count === 0) return false;
        return '<span id="' . self::prefix('tabs-errors-count') . '" class="' . self::prefix('tabs-count') . '">' . $count . '</span>' . self::_('Error', 'Errors', $count);
    }
    
    /**
     * Gets events tab with number of events, tab is hidden if no events
     */
    protected static function getEventsLabel() {
        $count = count(self::$events);
        if($count === 0) return false;
        return '<span id="' . self::prefix('tabs-events-count') . '" class="' . self::prefix('tabs-count') . '">' . $count . '</span>' . self::_('Event', 'Events', $count);
    }
    
    protected static function getStatsLabel() {
        return self::_('Stats');
    }
    
    protected static function getJSONLabel() {
        return self::_('JSON');
    }
    
    protected static function getToolsLabel() {
        return self::_('Tools');
    }
    
    protected static function getDocsLabel() {
        return self::_('Docs');
    }
    
    protected static function getUpdatesContent() {
        
        // Check for updates
        $updates = self::$updates;

        // If we have any updates available
        if($updates['total']) {

            $output = '<form action="?'. self::inputName('action') . '=beginUpdating' . ( App::get('appName', 'configs') == 'Nymbly PHP' ? '&'. self::inputName('dry_run') . '=1' : '' ) . '" method="post">';

            // Output the title message
            $output .= '<div class="' . self::prefix('tab-title') . '">' . sprintf(self::_('%s update is available', '%s updates are available', $updates['total']), $updates['total']) . '</div>';

            // Build the update list
            $output .= '<table class="' . self::prefix('table') . '">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" /></th>
                                    <th>Name</th>
                                    <th>New Version</th>
                                    <th>Current Version</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>';

            // Loop types of updates
            foreach($updates as $type => $update) {
                if($type == 'total' || !is_array($update)) continue;
                // Output update type section
                $output .= '    <tr>
                                    <td>' . ucfirst($type) . '</td>
                                </tr>';
                
                foreach($update as $name => $plugin) {
                        
                    $output .= '<tr>
                                    <td><input type="checkbox" name="' . self::inputName('updates.' . $type . '.' . $name) . '" /></td>
                                    <td>' . $name . '</td>
                                    <td>' . $plugin['version'] . '</td>
                                    <td>' . $plugin['current']['version'] . '</td>
                                </tr>';
                }

            }

            // Close table
            $output .= '
                            </tbody>
                        </table>';

            // Add update button
            $output .= '<input type="submit" id="' . self::prefix('run-updates-button') . '" class="' . self::prefix('button') . '" value="' . self::_('Run Updates') . '" />';
            // Add Check for updates button
            $output .= '<a href="?' . self::inputName('action') . '=checkUpdates" id="' . self::prefix('run-updates-button') . '" class="' . self::prefix('button') . '">' . self::_('Check For Updates') . '</a>';
            
            $output .= '</form>';
            
            return $output;

        // EVerything is up to date
        } else {

            //unset(self::$defaultTabs['Updates']);

        }
    }
    
    protected static function getDebugsContent() {
        $content = '';
        foreach(self::$debugs as $debug) {  
            $content .= '<div class="' . self::prefix('debug-wrap') . '">';
                $content .= '<div class="' . self::prefix('debug-title') . '">';
                    $content .= $debug[2];
                $content .= '</div>';
                $content .= '<div class="' . self::prefix('debug-content') . '">';
                    $content .= '<div class="' . self::prefix('debug-location') . '">';
                        $content .= 'Line: ' . $debug[3][0]['line'] . ' - ' . $debug[3][0]['file'];
                    $content .= '</div>';
                    $content .= '<pre>' . print_r($debug[1], true) . '</pre>';
                $content .= '</div>';
            $content .= '</div>';
        }
        return $content;
    }
    
    protected static function getErrorsContent() {
        $errors = '';
		foreach(self::$errors as $error) {
			$errors .= print_r($error['erroutput'], 1);
		}
		return $errors;
    }
    
    protected static function getEventsContent() {
        // TODO fix problem with html code being output in events tab
        return;
        $events = '';
		foreach(self::$events as $event) {
            array_walk_recursive($event, function(&$v) { $v = htmlspecialchars($v); });
			$events .= $event;
			//$events .= print_r($event, 1);
		}
		return $events;
    }
    
    protected static function getStatsContent() {
        return implode('<hr />', self::$stats);
    }
    
    protected static function getToolsContent() {
        // Create Tools content
        $out = self::$tools;
        $out .= '<a href="?' . self::inputName('action') . '=clearCache">' . self::_('Clear Cache') . '</a><br />';
        $out .= '<strong>' . self::_('Generate new plugin') . '</strong><br />';
        $out .= '<form method="post" action="?' . self::inputName('action') . '=generatePlugin"><input name="' . self::inputName('new_plugin_name') . '" type="text" /><input type="submit" value="' . self::_('Generate') . '" /></form>';
        return $out;
    }
    
    /**
     * Catch errors
     */
    protected static function onErrorHandleError($error) {
        $error = self::customizeError($error);
        self::$errors[] = $error;
        return $error;
    }
    
    private static function customizeError($error) {
        // Handle non static plugin events
        if(strpos($error['errorstr'], 'non-static method Plugin_') !== false && strpos($error['errorstr'], 'should not be called statically') !== false) {
            preg_match("/Plugin_(.+?)::(.+?)\(\)/", $error['errorstr'], $match);
            $error['errorstr'] = sprintf(App::_('', 'The event, %s, for plugin, %s, should be defined as static'), $match[2], ucwords(str_replace('_', ' ', $match[1])));
            $error['errfile'] = DIR_PLUGINS.DS.$match[1].DS.'index.php';
            $class = new ReflectionClass('Plugin_'.$match[1]);
            $method = $class->getMethod($match[2]);
            $error['errline'] = $method->getStartLine();
            $error['erroutput'] = Error::generateOutput($error);
        }
        return $error;
    }
    
    /**
     * Cancel default app error output
     */
    protected static function onErrorDisplayErrors($errors) {
        return;
    }
    
    protected static function onBeforeViewDisplay() {
        
        
    }
    
    protected static function onViewLoadTemplate($content) {
        return str_replace('<html>', '<html class="admin-bar">', $content);
    }
	
    /**
     * Append the admin bar to the content
     * 
     * @param <type> $content 
     * 
     * @return <type>
     */
	protected static function onViewGetFooter($content) {
        
        if(App::get('method', 'request') != 'get' && App::get('method', 'request') != 'post') return;
        
        // Create stats content
        $out = '';
        
        // Get current framework version
        $info = json_decode(file_get_contents(DIR_ROOT.DS.'version.json'), true);
        $out .= '<strong>Current Nymbly Version: ' . $info['version'] . '</strong><br />';
        
        // Get all pluging
        $out .= "<strong>Plugins</strong><br />";
        $plugins = Plugins::getInstalledPlugins();
        array_walk($plugins, function(&$item, $key) { $item = $key . ': ' . ($item ? 'Enabled' : 'Disabled') . ' : ' . Plugins::getPluginVersion($key); } );
        $out .= implode('<br />', $plugins);
        
        // Average load time
        $out .= "<br /><strong>Average Page Runtime</strong><br />";
        $out .= round(self::getData('runTimesAverage'), 5) . "ms<br />";
        
        // Get all defined constants
        $constants = get_defined_constants(true);
        $constants = $constants['user'];
        $out .= "<strong>Constants</strong>";
        $out .= '<ul>';
        foreach($constants as $k => $v) {
            $out .= "<li><strong>$k:</strong> $v</li>";
        }
        $out .= '</ul>';
        self::$stats[] = $out;
        
        // Setup tabs
		self::setUpTabs();
		
		self::$adminBar .= '</div>';
        
		return $content . self::$adminBar;
	}
    
    /**
     * On fatal error shutdown, fallback to frameworks default error display
     */
    protected static function onErrorFatalShutdown($errors) {
        foreach(self::$errors as $error) {
            echo $error['erroutput'];
        }
    }
    
    /**
     * After view has been completely displayed, calculate and store app execution time
     * 
     * The time will be logged and displayed in the stats tab for next page load.
     */
    protected static function onAfterViewDisplay() {
        
        if(App::get('method', 'request') != 'get' && App::get('method', 'request') != 'post') return;
        
        $runTimes = self::getData('runTimes', array());
        $average = array_sum($runTimes) / count($runTimes);
        $runTimes[] = microtime(true) - self::$startTime;
        
        // Limit run times to last 100 entries
        $runTimes = array_slice($runTimes, count($runTimes) - 100);
        self::storeData('runTimes', $runTimes);
        self::storeData('runTimesAverage', $average);
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

		// Should we only debug a specific event
		if($single = App::get('get.debug_trace_event', 'request')) {
			if($event == $single) {
				call_user_func_array(array('self', 'logEvent'), $args);
			}
		} else {
			//call_user_func_array(array('self', 'logEvent'), $args);
		}
		
	}

	protected static function onAppDebug($default, $var, $name, $info) {
		self::$debugs[] = func_get_args();
        // Cancel default framework output
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
    
    public static function storeData($name, $data) {
        if(file_exists(self::getPluginPath().DS.'data-logs.json')) {
            $log = json_decode(file_get_contents(self::getPluginPath().DS.'data-logs.json'), true);
        } else {
            $log = array();
        }
        $log[$name] = $data;
        // Save updated data log
        file_put_contents(self::getPluginPath().DS.'data-logs.json', json_encode($log));
    }
    
    public static function getData($name, $default=null) {
        if(file_exists(self::getPluginPath().DS.'data-logs.json')) {
            $logs = json_decode(file_get_contents(self::getPluginPath().DS.'data-logs.json'), true);
            return isset($logs[$name]) ? $logs[$name] : $default;
        } else {
            return $default;
        }
    }
	
}

?>
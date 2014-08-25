<?php

defined('IN_APP') ? NULL : exit();

/**
 * Debugging plugins
 *
 * This plugin will allow admin users to output
 * and debug plugin events as they occur
 *
 * @author Justin Carlson
 * @date 4/20/2013
 *
 */
class Plugin_debug_trace extends Plugins {
	
	private static $events = array();
	private static $debugs = array();
	
    /**
     * On plugin initialize
     * 
     * Disabled the plugin unless is developer
     * 
     * @return void
     */
	protected static function onPluginInit_debug_trace() {
		
		// Unregister the plugin if it is not being used
		if(!App::isDeveloper()) {
			self::unRegisterPlugin();
		}
		
	}
	
    /**
     * Add the debug and events tabs to the admin bar plugin if it exists
     * Otherwise the debug and event content will be appended to the bottom of the views content
     *	 
     * @param <type> $tabs 
     * 
     * @return <array> The altered tabs array with the events and debug tabs added
     */
	protected static function onAdminBarSetUpTabs($tabs) {
		$tabs['Debugs'] = '{$admin_bar_debugs}';
		$tabs['Events'] = '{$admin_bar_events}';
		return $tabs;
	}
	
    /**
     * 
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
     * 
     * 
     * @param <type> $content 
     * 
     * @return <type>
     */
	protected static function onViewGetContent($content) {
				
		// If admin bar plugin exists, add the Errors tab
		if(Plugins::pluginExists('admin_bar')) {
			self::assign('admin_bar_debugs', implode('<hr />', self::$debugs));
			self::assign('admin_bar_events', implode('<hr />', self::$events));
			return false;
		// Append error reports to body content
		} else {
			return $content . $errors;
		}
	
		//return $content . implode('<br />', self::$logs);
	}

}

?>
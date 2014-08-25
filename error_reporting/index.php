<?php

defined('IN_APP') ? NULL : exit();

/**
 * Plugin for reporting errors
 *
 * This plugin tries to tie into the admin bar plugin if it exists
 * Errors will be added to the Errors tab if it does.
 * Otherwise the errors will be appended to the end of content into the body
 *
 * @author Justin Carlson
 * @date 4/20/2013
 *
 */
class Plugin_error_reporting extends Plugins {
	
	private static $errors = array();
	
    /**
     * On plugin initialize
     * 
     * Disable plugin if is not developer
     * 
     * @return void
     */
	protected static function onPluginInit_error_reporting() {
		
		// Unregister the plugin if it is not being used
		if(!App::isDeveloper()) {
			self::unRegisterPlugin();
		}
		
	}
	
	protected static function onAdminBarSetUpTabs($tabs) {
		$tabs['Errors'] = '{$admin_bar_errors}';
		return $tabs;
	}
	
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
		if($errno === false) {
			return self::handleFatalError();
		} else {
			// This cancels the default error output
			// By returning false, we can cencel the default $returned value if one exists for a filter event
			return false;
		}
	}
	
    /**
     * Assign the errors to the {$admin_bar_errors} var
     * 
     * 
     * 
     * @param <type> $content 
     * 
     * @return <type>
     */
	protected static function onViewGetContent($content) {
		$errors = '';
		foreach(self::$errors as $error) {
			$errors .= print_r($error, 1);
		}
		// If admin bar plugin exists, add the Errors tab
		if(Plugins::pluginExists('admin_bar')) {
			self::assign('admin_bar_errors', $errors);
			return $content;
		// Append error reports to body content
		} else {
			return $content . $errors;
		}
	}
	
	private static function handleFatalError() {
		$errors = '';
		foreach(self::$errors as $error) {
			$errors .= print_r($error, 1);
		}
		return '<style>' . file_get_contents(DIR_APP.DS.self::getPluginSettings('styles')) . '</style>' . $errors;
	}
	
}

?>
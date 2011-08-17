<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// Define the CodeIgniter Version and Branch (Core = TRUE, Reactor = FALSE)
define('CI_VERSION', '2.0.2');
define('CI_CORE', FALSE);

/**
 * CodeIgniter Loader Base Class
 *
 * This class declares the protected loader methods of the application core, below.
 * By deriving both the CodeIgniter class and the Loader class from this common
 * parent, both can access these protected methods. This allows the core loading
 * mechanisms to live in CodeIgniter, where these methods are overloaded with their
 * actual functionality, but forces users to load resources through the Loader API.
 * The reverse works for _autoload(), which is defined in Loader and called from
 * CodeIgniter.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		Darren Hill <dchill42@gmail.com>, St. Petersburg College
 */
class CI_LoaderBase {
	// CodeIgniter methods
	protected function _load($type, $class, $obj_name = '', array $params = NULL, $subdir = '', $path = '') { }
	protected function _run_file($_ci_path, $_ci_view, $_ci_return, array $_ci_vars) { }
	// Loader method
	protected function _autoloader($autoload) { }
}

/**
 * CodeIgniter Router Base Class
 *
 * Just like CI_LoaderBase above, this class allows sharing of protected members
 * and methods between CodeIgniter, URI, and Router.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		Darren Hill <dchill42@gmail.com>, St. Petersburg College
 */
class CI_RouterBase {
	// URI methods
	protected function _load_uri() { }
	protected function _routed(array $rsegments) { }
	// Router method
	protected function _set_routing() { }
}

/**
 * CodeIgniter Application Core Class
 *
 * This class object is the super class that every library in
 * CodeIgniter will be assigned to.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/general/controllers.html
 */
class CodeIgniter extends CI_LoaderBase, CI_RouterBase {
	protected static $_ci_instance		= NULL;
	protected static $_ci_config_paths	= array(APPPATH);
	protected $_ci_app_paths			= array(APPPATH => TRUE);
	protected $_ci_loaded				= array();
	protected $_ci_subclass_prefix		= '';
	protected $_ci_ob_level				= 0;

	/**
	 * Constructor
	 *
	 * This constructor is protected so CodeIgniter can't be instantiated directly.
	 * Instead, the static CodeIgniter::instance() method must be called,
	 * which enforces the singleton behavior of the object.
	 */
	protected function __construct() {
		// Define a custom error handler so we can log PHP errors
		set_error_handler(array($this, '_exception_handler'));

		if (!$this->is_php('5.3')) {
			@set_magic_quotes_runtime(0); // Kill magic quotes
		}

		// Set a liberal script execution time limit
		if (function_exists('set_time_limit') == TRUE && @ini_get('safe_mode') == 0) {
			@set_time_limit(300);
		}

		// Load the framework constants
		self::get_config('constants.php');

		// Set initial output buffering level
		$this->_ci_ob_level = ob_get_level();
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		// Close the DB connection if one exists
		if (isset($this->db)) {
			$this->db->close();
		}

		// Check for failed load of routed Controller
		if (isset($this->routed) && is_string($this->routed)) {
			$this->show_error('Unable to load your default controller: '.$this->routed.
				'. Please make sure the controller specified in your Routes.php file is valid.');
		}
	}

	/**
	 * Get instance
	 *
	 * Returns singleton instance of CodeIgniter object
	 * THERE CAN BE ONLY ONE!! (Mu-ha-ha-ha-haaaaa!!)
	 *
	 * @param	array	$assign_to_config from index.php
	 * @return	object
	 */
	public static function &instance($assign_to_config = NULL) {
		// Check for existing instance
		if (is_null(self::$_ci_instance)) {
			// Get config file contents and check for errors
			$config = self::get_config('config.php', 'config');
			if ($config === FALSE) {
				exit('The configuration file does not exist.');
			}
			else if (is_string($config)) {
				exit('Your config file '.$config.' does not appear to be formatted correctly.');
			}

			// Apply any overrides
			if (is_array($assign_to_config)) {
				foreach ($assign_to_config as $key => $val) {
					$config[$key] = $val;
				}
			}

			// Get autoload file if present
			$autoload = self::get_config('autoload.php', 'autoload');
			if (!is_array($autoload)) {
				$autoload = array();
			}

			// Check for subclass prefix
			$class = 'CodeIgniter';
			$pre = isset($config['subclass_prefix']) ? $config['subclass_prefix'] : '';
			if (!empty($pre)) {
				// Search for a subclass
				$paths = array(APPPATH);

				// Get any autoloaded package paths
				if (isset($autoload['packages'])) {
					foreach ($autoload['packages'] as $package) {
						$paths = array_unshift($paths, $package);
					}
				}

                // Include source
                $subclass = self::_include($paths, 'core/'.$pre, $pre, $class);
                if ($subclass !== FALSE) {
                    // Set subclass to be instantiated
                    $class = $subclass;
                }
			}

			// Instantiate object and assign to static instance
			self::$_ci_instance = new $class();
			self::$_ci_instance->_init($config, $autoload);
		}

		return self::$_ci_instance;
	}

	/**
	 * Initialize configuration
	 *
	 * This function finishes bootstrapping the CodeIgniter object by loading
	 * the Config object and installing the primary configuration.
	 *
	 * @param	array	config array
	 * @param	array	autoload array
	 * @return	void
	 */
	protected function _init($config, $autoload) {
		// Establish configured subclass prefix
		if (isset($config['subclass_prefix'])) {
			$this->_ci_subclass_prefix = $config['subclass_prefix'];
		}

		// Autoload package paths so they can be searched
		if (isset($autoload['packages'])) {
			foreach ($autoload['packages'] as $package_path) {
				$this->add_package_path($package_path);
			}
		}

		// Instantiate the config class and initialize
		$this->_load('core', 'Config', '', $config);

		// Load any custom config files
		if (isset($autoload['config'])) {
			foreach ($autoload['config'] as $val) {
				$this->config->load($val);
			}
		}

		// Save remaining items for run()
		$this->_ci_autoload =& $autoload;
	}

	/**
	 * Run the CodeIgniter application
	 *
	 * @param	array	$routing	from index.php
	 * @return	void
	 */
	public function run($routing) {
		// Check if Benchmark is enabled
		if ($this->config->item('enable_benchmarks')) {
			// Load Benchmark
			$this->_load('core', 'Benchmark');

			// Start the timer... tick tock tick tock...
			$this->benchmark->mark('total_execution_time_start');
			$this->benchmark->mark('loading_time:_base_classes_start');
		}

		// Check if Hooks is enabled and needed
		if ($this->config->item('enable_hooks')) {
			// Grab the "hooks" definition file.
			$hooks = self::get_config('hooks.php', 'hook');
			if (is_array($hooks) && count($hooks) > 0) {
				// Load hooks
				$this->_load('core', 'Hooks', '', $hooks);

				// Call pre_system hook
				$this->hooks->_call_hook('pre_system');
			}
		}

		// Load Loader, Output, URI, and Router
		// Order is important here because Router, which directly depends on URI,
		// may throw an error, which will require Output to display, during which
		// process Output may need Loader to load Profiler
		$this->_load('core', 'Loader', 'load');
		$this->_load('core', 'Output');			// Output depends on Loader
		$this->_load('core', 'URI');
		$this->_load('core', 'Router');			// Router depends on URI and Output

		// Set routing with any overrides from index.php
		$this->router->_set_routing($routing);

		// Check for cache override or failed cache display
		// If not overridden, and a valid cache exists, we're done
		if ((isset($this->hooks) && $this->hooks->_call_hook('cache_override') === TRUE) ||
		$this->output->_display_cache() == FALSE) {
			// Load remaining core classes
			$this->_load('core', 'Security');
			$this->_load('core', 'Utf8');
			$this->_load('core', 'Input');			// Input depends on Security and UTF-8
			$this->_load('core', 'Lang');

			// Autoload any remaining resources
			if (isset($this->_ci_autoload)) {
				$this->load->_autoloader($this->_ci_autoload);
				unset($this->_ci_autoload);
			}

			if (isset($this->benchmark)) {
				$this->benchmark->mark('loading_time:_base_classes_end');
			}

			// Load and run the controller
			$this->run_controller();
		}
	}

	/**
	 * Load and run the routed Controller
	 *
	 * @return	void
	 */
	protected function run_controller() {
		// Call the "pre_controller" hook
		if (isset($this->hooks)) {
			$this->hooks->_call_hook('pre_controller');
		}

		// Get the parsed route and extract segments
		$route = $this->router->fetch_route();
		$path = array_shift($route);
		$subdir = array_shift($route);
		$class = array_shift($route);
		$method = array_shift($route);

		// Mark a start point so we can benchmark the controller
		if (isset($this->benchmark)) {
			$this->benchmark->mark('controller_execution_time_( '.$class.' / '.$method.' )_start');
		}

		// Load the controller object and set special "routed" reference
		$this->_load('controller', $class, '', NULL, $subdir, $path);
		$this->routed =& $this->$class;

		// Call the "post_controller_constructor" hook
		if (isset($this->hooks)) {
			$this->hooks->_call_hook('post_controller_constructor');
		}

		// Call the requested method with remaining route segments as arguments
		if ($this->call_controller($class, $method, $route) == FALSE) {
			// Both _remap and $method failed - go to 404
			$this->show_404($class.'/'.$method);
		}

		// Mark a benchmark end point
		if (isset($this->benchmark)) {
			$this->benchmark->mark('controller_execution_time_( '.$class.' / '.$method.' )_end');
		}

		// Display output
		if (isset($this->hooks)) {
			// Call the "post_controller" hook
			$this->hooks->_call_hook('post_controller');

			// Send the final rendered output to the browser
			if ($this->hooks->_call_hook('display_override') === FALSE) {
				$this->output->_display();
			}

			// Call the "post_system" hook
			$this->hooks->_call_hook('post_system');
		}
		else {
			// Just call display
			$this->output->_display();
		}
	}

	/**
	 * Call a controller method
	 *
	 * Requires that controller already be loaded, validates method name, and calls
	 * _remap if available.
	 *
	 * @param	string	class name
	 * @param	string	method
	 * @param	array	arguments
	 * @param	string	optional object name
	 * @return	boolean	TRUE on success, otherwise FALSE
	 */
	public function call_controller($class, $method, array $args = array(), $obj_name = '') {
		// Default name if not provided
		if (empty($obj_name)) {
			$obj_name = strtolower($class);
		}

		// Class must be loaded, and method cannot start with underscore, nor be a member of the base class
		if (isset($this->$obj_name) && strncmp($method, '_', 1) != 0 &&
		!in_array(strtolower($method), array_map('strtolower', get_class_methods('CI_Controller')))) {
			// Check for _remap
			if ($this->is_callable($this->$obj_name, '_remap')) {
				// Call _remap
				$this->$obj_name->_remap($method, $args);
				return TRUE;
			}
			else if ($this->is_callable($this->$obj_name, $method)) {
				// Call method
				call_user_func_array(array(&$this->$obj_name, $method), $args);
				return TRUE;
			}
		}

		// Neither _remap nor method could be called
		return FALSE;
	}

	/**
	 * Is Loaded
	 *
	 * A utility function to test if a class is in the _ci_loaded array.
	 * This function returns the object name if the class tested for is loaded,
	 * and returns FALSE if it isn't.
	 *
	 * @param	string	class being checked for
	 * @param	string	optional object name to check
	 * @return	mixed	TRUE or class object name on success, otherwise FALSE
	 */
	public function is_loaded($class, $obj_name = '') {
		// Lowercase class
		$class = strtolower($class);

		// See if class is loaded
		if (isset($this->_ci_loaded[$class])) {
			// Yes - are we checking a specific object name?
			if (empty($obj_name)) {
				// Return the first name loaded
				// If not attached, this will just return TRUE
				return reset($this->_ci_loaded[$class]);
			}
			else {
				// Return whether that name was used for this class
				return in_array($obj_name, $this->_ci_loaded[$class]);
			}
		}
				
		// Never loaded
		return FALSE;
	}

	/**
	 * Determines if the current version of PHP is greater then the supplied value
	 *
	 * @param	string
	 * @return	bool	TRUE if the current version is $version or higher
	 */
	public function is_php($version = '5.0.0') {
		// Just return whether version is >= value provided
		return (version_compare(PHP_VERSION, $version) >= 0);
	}

	/**
	 * Tests for file writability
	 *
	 * is_writable() returns TRUE on Windows servers when you really can't write to
	 * the file, based on the read-only attribute. is_writable() is also unreliable
	 * on Unix servers if safe_mode is on.
	 *
	 * @param	string	file path
	 * @return	boolean TRUE if writeable, otherwise FALSE
	 */
	public function is_really_writable($file) {
		// If we're on a Unix server with safe_mode off we call is_writable
		if (DIRECTORY_SEPARATOR == '/' && @ini_get('safe_mode') == FALSE) {
			return is_writable($file);
		}

		// For windows servers and safe_mode "on" installations we'll actually
		// write a file then read it. Bah...
		if (is_dir($file)) {
			$file = rtrim($file, '/').'/'.md5(mt_rand(1,100).mt_rand(1,100));

			if (($fp = @fopen($file, FOPEN_WRITE_CREATE)) === FALSE) {
				return FALSE;
			}

			fclose($fp);
			@chmod($file, DIR_WRITE_MODE);
			@unlink($file);
			return TRUE;
		}
		elseif ( ! is_file($file) || ($fp = @fopen($file, FOPEN_WRITE_CREATE)) === FALSE) {
			return FALSE;
		}

		fclose($fp);
		return TRUE;
	}

	/**
	 * Determine if a class method can actually be called (from outside the class)
	 *
	 * @param	mixed	class name or object
	 * @param	string	method
	 * @return	boolean	TRUE if publicly callable, otherwise FALSE
	 */
	public function is_callable($class, $method) {
		// Just return whether the case-insensitive method is in the public methods
		return in_array(strtolower($method), array_map('strtolower', get_class_methods($class)));
	}

	/**
	 * Error Handler
	 *
	 * This function lets us invoke the exception class and display errors using
	 * the standard error template located in application/errors/errors.php
	 * This function will send the error page directly to the browser and exit.
	 *
	 * @param	string	error message
	 * @param	int	status code
	 * @param	string	heading
	 * @return	void
	 */
	public function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered') {
		// Ensure Exceptions is loaded
		if (!isset($this->exceptions)) {
			$this->_load('core', 'Exceptions');
		}

		// Call show_error, which will exit
		$this->exceptions->show_error($heading, $message, 'error_general', $status_code);
	}

	/**
	 * 404 Page Handler
	 *
	 * This function is similar to the show_error() function above
	 * However, instead of the standard error template it displays 404 errors.
	 *
	 * @param	string	page URL
	 * @param	boolean	FALSE to skip logging
	 * @return	void
	 */
	public function show_404($page = '', $log_error = TRUE) {
		// Ensure Exceptions is loaded
		if (!isset($this->exceptions)) {
			$this->_load('core', 'Exceptions');
		}

		// Call show_404, which will exit
		$this->exceptions->show_404($page, $log_error);
	}

	/**
	 * Error Logging Interface
	 *
	 * We use this as a simple mechanism to access the logging
	 * class and send messages to be logged.
	 *
	 * @param	string	error level
	 * @param	string	error message
	 * @param	boolean	TRUE if native error
	 * @return	void
	 */
	public function log_message($level = 'error', $message, $php_error = FALSE) {
		// Check log threshold
		if ($this->config->item('log_threshold') == 0) {
			return;
		}

		// Ensure Log is loaded
		if (!isset($this->log)) {
			$this->_load('library', 'Log');
		}

		// Write log message
		$this->log->write_log($level, $message, $php_error);
	}

	/**
	 * Remove Invisible Characters
	 *
	 * This prevents sandwiching null characters
	 * between ascii characters, like Java\0script.
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	public function remove_invisible_characters($str, $url_encoded = TRUE) {
		$non_displayables = array();

		// every control character except newline (dec 10)
		// carriage return (dec 13), and horizontal tab (dec 09)
		if ($url_encoded) {
			$non_displayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
			$non_displayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
		}

		$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

		do {
			$str = preg_replace($non_displayables, '', $str, -1, $count);
		}
		while ($count);

		return $str;
	}

	/**
	 * Add Package Path
	 *
	 * Prepends a parent path to the base, app, and config path arrays
	 *
	 * @param	string	path
	 * @param	boolean	view	cascade flag
	 * @return	void
	 */
	public function add_package_path($path, $view_cascade = TRUE) {
		// Resolve path
		$path = $this->_resolve_path($path);

		// Prepend config file path
		array_unshift(self::_ci_config_paths, $path);

		// Prepend app path with view cascade param
		$this->_ci_app_paths = array($path => $view_cascade) + $this->_ci_app_paths;
	}

	/**
	 * Remove Package Path
	 *
	 * Remove a path from the base, app, and config path arrays if it exists
	 * If no path is provided, the most recently added path is removed.
	 *
	 * @param	string	path
	 * @param	boolean	remove	from config path flag
	 * @return	void
	 */
	public function remove_package_path($path = '', $remove_config_path = TRUE) {
		if ($path == '') {
			// Shift last added path from each list
			if ($remove_config_path) {
				array_shift(self::_ci_config_paths);
			}
			array_shift($this->_ci_app_paths);
			return;
		}

		// Resolve path
		$path = $this->_resolve_path($path);

		// Prevent app path removal - it is a default for all lists
		if ($path == APPPATH) {
			return;
		}

		// Unset path from config list
		if ($remove_config_path && ($key = array_search($path, self::_ci_config_paths)) !== FALSE) {
			unset(self::_ci_config_paths[$key]);
		}

		// Unset path from app list
		if (isset($this->_ci_app_paths[$path])) {
			unset($this->_ci_app_paths[$path]);
		}
	}

	/**
	 * Get Package Paths
	 *
	 * Return a list of all package paths, by default it will ignore BASEPATH.
	 *
	 * @param	boolean	include base path flag
	 * @return	array	package path list
	 */
	public function get_package_paths($include_base = FALSE) {
		// Just return path list - only _run_file() needs the cascade feature
		$paths = array_keys($this->_ci_app_paths);
		if ($include_base == TRUE) {
			$paths[] = BASEPATH;
		}
		return $paths;
	}

	/**
	 * Get config file contents
	 *
	 * This function searches the package paths for the named config file.
	 * If $name is defined, it requires the file to contain an array by that name
	 * and merges the arrays if found in multiple files.
	 * Otherwise, it just includes each matching file found.
	 *
	 * @param	string	file name
	 * @param	string	array name
	 * @return	mixed	config array on success (or TRUE if no name), file path string on invalid contents,
	 *					or FALSE if no matching file found
	 */
	public static function get_config($file, $name = NULL) {
		// Prevent extra variable collection and return get_config_ext()
		$extras = FALSE;
		return self::get_config_ext($file, $name, $extras);
	}

	/**
	 * Get config file contents with extra variables
	 *
	 * This function searches the package paths for the named config file.
	 * If $name is defined, it requires the file to contain an array by that name
	 * and merges the arrays if found in multiple files.
	 * Otherwise, it just includes each matching file found.
	 * Any other variables found in each file with names not starting with
	 * an underscore are added to $_extras as an array element with the variable
	 * name as a key. For this reason, all local variables start with an underscore.
	 *
	 * @param	string	file name
	 * @param	string	array name
     * @param   array   reference to array for extra variables (or FALSE to skip collection)
	 * @return	mixed	config array on success (or TRUE if no name), file path string on invalid contents,
	 *					or FALSE if no matching file found
	 */
	public static function get_config_ext($_file, $_name = NULL, &$_extras) {
		// Ensure file starts with a slash and ends with .php
		$_file = '/'.ltrim($_file, '\/');
		if (!preg_match('/\.php$/', $_file)) {
			$_file .= '.php';
		}

		// Set relative file paths to search
		$_files = array();
		if (defined('ENVIRONMENT')) {
			// Check ENVIRONMENT for file
			$_files[] = 'config/'.ENVIRONMENT.$_file;
		}
		$_files[] = 'config'.$_file;

		// Merge arrays from all viable config paths
		$_merged = array();
		foreach (self::_ci_config_paths as $_path) {
			// Check each variation
			foreach ($_files as $_file) {
				$_file_path = $_path.$_file;
				if (file_exists($_file_path)) {
					// Include file
					include($_file_path);

					// See if we're gathering extra variables
					if ($_extras !== FALSE) {
						// Get associative array of extra vars
						foreach (get_defined_vars() as $_key => $_var) {
							if (substr($_key, 0, 1) != '_' && $_key != $_name) {
								$_extras[$_key] = $_var;
							}
						}
					}

					// See if we have an array to check for
					if (empty($_name)) {
						// Nope - just note we found something
						$merged = TRUE;
						continue;
					}

					// Check for named array
					if (!is_array($$_name)) {
						// Invalid - return bad filename
						return $_file_path;
					}

					// Merge config and unset local
					// Here, array_replace_recursive will recursively merge the arrays,
					// adding new elements and replacing existing ones
					$_merged = array_replace_recursive($_merged, $$_name);
					unset($$_name);
				}
			}
		}

		// Test for merged config
		if (empty($_merged)) {
			// None - quit
			return FALSE;
		}

		// Return merged config
		return $_merged;
	}

	/**
	 * Exception Handler
	 *
	 * This is the custom exception handler that is declaired in the constructor.
	 * The main reason we use this is to permit PHP errors to be logged in our
	 * own log files since the user may not have access to server logs. Since
	 * this function effectively intercepts PHP errors, however, we also need
	 * to display errors based on the current error_reporting level.
	 * We do that with the use of a PHP error template.
	 *
	 * @access	private
	 * @return	void
	 */
	public function _exception_handler($severity, $message, $filepath, $line) {
		 // We don't bother with "strict" notices since they tend to fill up
		 // the log file with excess information that isn't normally very helpful.
		 // For example, if you are running PHP 5 and you use version 4 style
		 // class functions (without prefixes like "public", "private", etc.)
		 // you'll get notices telling you that these have been deprecated.
		if ($severity == E_STRICT) {
			return;
		}

		// Ensure the exception class is loaded
		if (!isset($this->exceptions)) {
			$this->_load('core', 'Exceptions');
		}

		// Should we display the error? We'll get the current error_reporting
		// level and add its bits with the severity bits to find out.
		if (($severity & error_reporting()) == $severity) {
			$this->exceptions->show_php_error($severity, $message, $filepath, $line);
		}

		// Should we log the error?
		if ($this->config->item('log_threshold') != 0) {
			$this->exceptions->log_exception($severity, $message, $filepath, $line);
		}
	}

	/**
	 * Core loader
	 *
	 * This function loads a class, along with any subclass, instantiates it
	 * (unless overridden), and attaches the object to the core.
	 *
	 * @param	string	object type ('core', 'library', 'helper', 'model', 'controller')
	 * @param	string	class name
	 * @param	string	object name (or FALSE to prevent attachment)
	 * @param	array	constructor parameters
	 * @param	string	subdirectory
	 * @param	string	routed path for controllers
	 * @return	void
	 */
	protected function _load($type, $class, $obj_name = '', $params = NULL, $subdir = '', $path = '') {
		// Set name default
		if ($obj_name !== FALSE && empty($obj_name)) {
			$obj_name = strtolower($class);
		}

		// Determine if this class is already loaded under this name
		if ($this->is_loaded($class, $obj_name)) {
			return;
		}

		// Match type and set directory
		switch ($type) {
			case 'core':
				$dir = $type.'/';
				break;
			case 'library':
				$dir = 'libraries/';
				break;
			case 'helper':
			case 'model':
			case 'controller':
				$dir = $type.'s/';
				$subclass = $class;
				$class = ucfirst($type);
				break;
			default:
				$this->show_error('Invalid object type in load request: '.$type);
		}

		// Determine if name is in use
		if ($obj_name !== FALSE && isset($this->$obj_name)) {
			$this->show_error('The '.$type.' name you are loading is the name of a resource that is '.
				'already being used: '.$obj_name);
		}

		// Prepare search paths
		$paths = array_keys($this->_ci_app_paths);
		$basepaths[] = BASEPATH;
		if ($type == 'core') {
			// Core classes start with 'CI_' and get searched base first
			$prefix = 'CI_';
			$basepaths = array_reverse($basepaths);
		}
		else {
			// All others have no prefix
			$prefix = '';
		}
		$test = ($type != 'helper');

		// Load base class - this must be found for any load
		$classnm = self::_include($basepaths, $dir.$subdir, $prefix, $class, $test);
		if ($classnm == FALSE) {
			// Not found - see if a subdirectory was specified
			switch ($subdir) {
				case '':
					// None - take one last stab at finding the class in its own subdirectory
					// If found, the subdirectory will apply to the subclass search below, as well.
					$subdir = strtolower($class).'/';
					$classnm = self::_include($basepaths, $dir.$subdir, $prefix, $class, $test);
					if ($classnm !== FALSE) {
						// Found it
						break;
					}
					// Else fall through
				default:
					// No base class is a fatal error
					$msg = ucfirst($type).' base class '.$class.' could not be found';
					$this->log_message('error', $msg);
					$this->show_error($msg);
					break;
			}
		}

		// Load subclass if available
		$subpre = $this->_ci_subclass_prefix;
		if (!empty($subpre)) {
			// Try loading the subclass - none found is not fatal
			$extclass = self::_include($paths, $dir.$subdir.$subpre, $subpre, $class, $test);
			if ($extclass !== FALSE) {
				// Subclass found - override class to instantiate
				$classnm = $extclass;
			}
		}

		// Load final class for model or controller
		if (isset($subclass)) {
			// Check for routed path
			if (empty($path)) {
				// None - search as usual
				$classnm = self::_include($paths, $dir.$subdir, '', $subclass);
			}
			else {
				// Use routed path to include class
				$file_path = $path.$dir.$subdir.$subclass.'.php';
				if (file_exists($file_path)) {
					// Include file and set class to instantiate
					include($file_path);
					$classnm = $subclass;
				}
				else {
					// Mark failure for error below
					$classnm = FALSE;
				}
			}

			// Make sure we found the class
			if ($classnm == FALSE) {
				// No final class is a fatal error
				$msg = ucfirst($type).' class '.$subclass.' could not be found';
				$this->log_message('error', $msg);
				$this->show_error($msg);
			}
		}

		// Get class name for _ci_loaded array
		$name = isset($subclass) ? strtolower($subclass) : strtolower($class);
		if ($obj_name === FALSE) {
			// Mark class as loaded without name
			$this->_ci_loaded[$name][] = TRUE;

			// Nothing to attach - all done here
			return;
		}
		else {
			// Map object name to class
			$this->_ci_loaded[$name][] = $obj_name;
		}

		// Determine parameters
		if ($type == 'core') {
			// Each core class gets a reference to the parent
			if (!is_null($params)) {
				// Pass core params as extras
				$extras = $params;
			}
			$params = $this;
		}
		else if ($type == 'library' && is_null($params)) {
			// See if there's a config file for the class
			$file = strtolower($class).'.php';
			$config = self::get_config($file, 'config');
			if (!is_array($config)) {
				// Try uppercase
				$config = self::get_config(ucfirst($file), 'config');
			}

			// Set params to config if found
			if (is_array($config)) {
				$params =& $config;
			}
		}

		// Attach object
		if (is_null($params)) {
			$this->$obj_name = new $classnm();
		}
		else if (!isset($extras)) {
			$this->$obj_name = new $classnm($params);
		}
		else {
			$this->$obj_name = new $classnm($params, $extras);
		}
	}

	/**
	 * Include source
	 *
	 * This helper function searches the provided paths for a source file in the
	 * specified subdirectory and includes the source if found. It also verifies that
	 * the class exists after including the source.
	 *
	 * @param	array	path list
	 * @param	string	subdirectory
	 * @param	string	class prefix
	 * @param	string	class name
     * @param   boolean FALSE to skip class_exists test
	 * @return	mixed	class name with prefix if found, otherwise FALSE
	 */
	protected static function _include(array &$paths, $dir, $prefix, $class, $test = TRUE) {
		// Assemble class name and see if class already exists
		$classnm = $prefix.$class;
		if ($test && class_exists($classnm)) {
			// No need to include - return prefixed class
			return $classnm;
		}

		// Prepare case options with file extension
		$file = strtolower($class).'.php';
		$files = array(ucfirst($file), $file);

		// Search each path
		foreach ($paths as &$path) {
			// Append subdirectory
			$path .= $dir;

			// Try uppercase and lowercase file names
			foreach ($files as &$file) {
				// See if file exists
				if (file_exists($path.$file)) {
					// Include file and check for class
					include($path.$file);
					if (!$test || class_exists($classnm)) {
						// Good - return prefixed class name
						return $classnm;
					}
					else {
						// Bad file - fail out
						return FALSE;
					}
				}
			}
		}

		// Not found - return failure
		return FALSE;
	}

	/**
	 * Run user file
	 *
	 * This function is used to execute views and files on behalf of the Loader.
	 * Variables are prefixed with _ci_ to avoid symbol collision with
	 * variables made available to view files.
	 * Files automatically have access to all loaded objects via $this->object.
	 *
	 * @access	protected
	 * @param	string	view name or path to file
	 * @param	boolean	is view
	 * @param	boolean	return output as string
	 * @param	array	local vars to declare
	 * @return	mixed	output if $_ci_return is TRUE, otherwise void
	 */
	protected function _run_file($_ci_path, $_ci_view, $_ci_return, array $_ci_vars = NULL) {
		// Set the path to the requested file
		$exists = FALSE;
		if ($_ci_view) {
			// Path is a view name - search for real path
			$file = (pathinfo($_ci_path, PATHINFO_EXTENSION) == '') ? $_ci_path.'.php' : $_ci_path;
			foreach ($this->_ci_app_paths as $path => $cascade) {
				$path .= 'views/'.$file;
				if (file_exists($path)) {
					$_ci_path = $path;
					$exists = TRUE;
					break;
				}

				if ( ! $cascade) {
					break;
				}
			}
			unset($path);
			unset($cascade);
		}
		else {
			// Path points to file - break out filename and check existence
			$file = end(explode('/', $_ci_path));
			$exists = file_exists($_ci_path);
		}
		unset($_ci_view);

		if (!$exists) {
			$this->show_error('Unable to load the requested file: '.$file);
		}
		unset($exists);
		unset($file);

		// Extract local variables
		if (!empty($_ci_vars)) {
			extract($_ci_vars);
		}
		unset($_ci_vars);

		/*
		 * Buffer the output
		 *
		 * We buffer the output for two reasons:
		 * 1. Speed. You get a significant speed boost.
		 * 2. So that the final rendered template can be post-processed by the output class.
		 *	Why do we need post processing? For one thing, in order to show the elapsed page load time.
		 *	Unless we can intercept the content right before it's sent to the browser and then stop
		 *	the timer it won't be accurate.
		 */
		ob_start();

		// If the PHP installation does not support short tags we'll do a little string replacement,
		// changing the short tags to standard PHP echo statements.
		if ((bool) @ini_get('short_open_tag') === FALSE && $this->config->item('rewrite_short_tags') == TRUE) {
			// Assemble strings to help prevent tag interpretation
			$end = '?'.'>';
			$pattern = '/<'.'\?=\s*([^>]*);*\s*\?'.'>/';
			$replace = '<'.'?php echo \1; ?'.'>';
			echo eval($end.preg_replace($pattern, $replace, file_get_contents($_ci_path)));
		}
		else {
			// Include file (include_once would prevent multiple views with the same name)
			include($_ci_path);
		}

		$this->log_message('debug', 'File loaded: '.$_ci_path);

		// Return the file data if requested
		if ($_ci_return === TRUE) {
			$buffer = ob_get_contents();
			@ob_end_clean();
			return $buffer;
		}

		/*
		 * Flush the buffer... or buff the flusher?
		 *
		 * In order to permit views to be nested within other views, we need to
		 * flush the content back out whenever we are beyond the first level of
		 * output buffering so that it can be seen and included properly by the
		 * first included template and any subsequent ones. Oy!
		 */
		if (ob_get_level() > $this->_ci_ob_level + 1) {
			ob_end_flush();
		}
		else {
			$this->output->append_output(ob_get_contents());
			@ob_end_clean();
		}
	}

	/**
	 * Resolves package path
	 *
	 * This function is used to identify absolute paths in the filesystem and include path
	 *
	 * @access	protected
	 * @param	string	initial path
	 * @return	string	resolved path
	 */
	protected function _resolve_path($path) {
		// Assert trailing slash
		$path = rtrim($path, '\/').'/';

		// See if path exists as-is
		if (file_exists($path)) {
			return $path;
		}

		// Strip any leading slash and pair with include directories
		$dir = ltrim($path, "/\\");
		foreach (explode(PATH_SEPARATOR, get_include_path()) as $include) {
			$include = rtrim($include, '\/').'/'.$dir;
			if (file_exists($include)) {
				// Found include path - clean up and return
				return $include;
			}
		}

		// If we got here, it's not a real path - just return as-is
		return $path;
	}
}
// END CodeIgniter class

/**
 * Get instance [DEPRECATED]
 *
 * Global function to return singleton instance of core object
 *
 * @return	object
 */
function &get_instance() {
	// Call static instance
	return CodeIgniter::instance();
}

/**
 * Show error [DEPRECATED]
 *
 * Global function to call core method
 *
 * @param	string	error message
 * @param	int	status code
 * @param	string	heading
 * @return	void
 */
function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered') {
	// Get instance and call show_error
	$CI =& CodeIgniter::instance();
	$CI->show_error($message, $status_code, $heading);
}

/**
 * Log message [DEPRECATED]
 *
 * Global function to call core method
 *
 * @param	string	error level
 * @param	string	error message
 * @param	boolean	TRUE if native error
 * @return	void
 */
function log_message($level, $message, $php_error = FALSE) {
	// Get instance and call log_message
	$CI =& CodeIgniter::instance();
	$CI->log_message($level, $message, $php_error);
}

// Load and run the application
$CI =& CodeIgniter::instance($assign_to_config);
$CI->run($routing);

/* End of file CodeIgniter.php */
/* Location: ./system/core/CodeIgniter.php */

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

// ------------------------------------------------------------------------

/**
 * System Initialization File
 *
 * Loads the base classes and executes the request.
 *
 * @package		CodeIgniter
 * @subpackage	codeigniter
 * @category	Front-controller
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/
 */

/*
 * ------------------------------------------------------
 *  Define the CodeIgniter Version
 * ------------------------------------------------------
 */
	define('CI_VERSION', '2.0.2');

/*
 * ------------------------------------------------------
 *  Define the CodeIgniter Branch (Core = TRUE, Reactor = FALSE)
 * ------------------------------------------------------
 */
	define('CI_CORE', FALSE);

/*
 * ------------------------------------------------------
 *  Load the global functions
 * ------------------------------------------------------
 */
	require(BASEPATH.'core/Common.php');

/*
 * ------------------------------------------------------
 *  Load the framework constants
 * ------------------------------------------------------
 */
	if (defined('ENVIRONMENT') AND file_exists(APPPATH.'config/'.ENVIRONMENT.'/constants.php'))
	{
		require(APPPATH.'config/'.ENVIRONMENT.'/constants.php');
	}
	else
	{
		require(APPPATH.'config/constants.php');
	}

/*
 * ------------------------------------------------------
 *  Define a custom error handler so we can log PHP errors
 * ------------------------------------------------------
 */
	set_error_handler('_exception_handler');

	if ( ! is_php('5.3'))
	{
		@set_magic_quotes_runtime(0); // Kill magic quotes
	}

/*
 * ------------------------------------------------------
 *  Set the subclass_prefix
 * ------------------------------------------------------
 *
 * Normally the "subclass_prefix" is set in the config file.
 * The subclass prefix allows CI to know if a core class is
 * being extended via a library in the local application
 * "libraries" folder. Since CI allows config items to be
 * overriden via data set in the main index. php file,
 * before proceeding we need to know if a subclass_prefix
 * override exists.  If so, we will set this value now,
 * before any classes are loaded
 * Note: Since the config file data is cached it doesn't
 * hurt to load it here.
 */
	if (isset($assign_to_config['subclass_prefix']) AND $assign_to_config['subclass_prefix'] != '')
	{
		get_config(array('subclass_prefix' => $assign_to_config['subclass_prefix']));
	}

/*
 * ------------------------------------------------------
 *  Set a liberal script execution time limit
 * ------------------------------------------------------
 */
	if (function_exists("set_time_limit") == TRUE AND @ini_get("safe_mode") == 0)
	{
		@set_time_limit(300);
	}

/*
 * ------------------------------------------------------
 *  Start the timer... tick tock tick tock...
 * ------------------------------------------------------
 */
	$BM =& load_class('Benchmark', 'core');
	$BM->mark('total_execution_time_start');
	$BM->mark('loading_time:_base_classes_start');

/*
 * ------------------------------------------------------
 *  Instantiate the hooks class
 * ------------------------------------------------------
 */
	$EXT =& load_class('Hooks', 'core');

/*
 * ------------------------------------------------------
 *  Is there a "pre_system" hook?
 * ------------------------------------------------------
 */
	$EXT->_call_hook('pre_system');

/*
 * ------------------------------------------------------
 *  Load the application root
 * ------------------------------------------------------
 */

	// Load the root class
	require BASEPATH.'core/Root.php';

	function &get_instance()
	{
		return CI_Root::get_instance();
	}
	$CI =& get_instance();

/*
 * ------------------------------------------------------
 *  Instantiate the config class
 * ------------------------------------------------------
 */
	$CI->load_core('Config');

	// Do we have any manually set config items in the index.php file?
	if (isset($assign_to_config))
	{
		$CI->config->_assign_to_config($assign_to_config);
	}

/*
 * ------------------------------------------------------
 *  Instantiate the UTF-8 class
 * ------------------------------------------------------
 *
 * Note: Order here is rather important as the UTF-8
 * class needs to be used very early on, but it cannot
 * properly determine if UTf-8 can be supported until
 * after the Config class is instantiated.
 *
 */

	$CI->load_core('Utf8');

/*
 * ------------------------------------------------------
 *  Instantiate the URI class
 * ------------------------------------------------------
 */
	$CI->load_core('URI');

/*
 * ------------------------------------------------------
 *  Instantiate the routing class and set the routing
 * ------------------------------------------------------
 */
	$CI->load_core('Router');
	$CI->router->_set_routing();

	// Set any routing overrides that may exist in the main index file
	if (isset($routing))
	{
		$CI->router->_set_overrides($routing);
	}

/*
 * ------------------------------------------------------
 *  Instantiate the output class
 * ------------------------------------------------------
 */
	$CI->load_core('Output');

/*
 * ------------------------------------------------------
 *	Is there a valid cache file?  If so, we're done...
 * ------------------------------------------------------
 */
	if ($EXT->_call_hook('cache_override') === FALSE)
	{
		if ($CI->output->_display_cache($CI->config, $CI->uri) == TRUE)
		{
			exit;
		}
	}

/*
 * -----------------------------------------------------
 * Load the security class for xss and csrf support
 * -----------------------------------------------------
 */
	$CI->load_core('Security');

/*
 * ------------------------------------------------------
 *  Load the Input class and sanitize globals
 * ------------------------------------------------------
 */
	$CI->load_core('Input');

/*
 * ------------------------------------------------------
 *  Load the Language class
 * ------------------------------------------------------
 */
	$CI->load_core('Lang');

/*
 * ------------------------------------------------------
 *  Autoload libraries, etc.
 * ------------------------------------------------------
 */

	$CI->load->ci_autoloader();

/*
 * ------------------------------------------------------
 *  Load the local controller
 * ------------------------------------------------------
 */

	// Load the Controller base class
	require BASEPATH.'core/Controller.php';

	// Load the Controller subclass, if found
	$file = 'core/'.$CI->config->item('subclass_prefix').'Controller.php';
	$packages = $CI->load->get_package_paths();
	foreach ($packages as $path)
	{
		if (file_exists($path.$file))
		{
			require $path.$file;
			break;
		}
	}

	// Load the local application controller
	// Note: The Router class automatically validates the controller path using the router->_validate_request().
	// If this include fails it means that the default controller in the Routes.php file is not resolving to something valid.
	$file = 'controllers/'.$CI->router->fetch_directory().$CI->router->fetch_class().'.php';
	$found = FALSE;
	foreach ($packages as $path)
	{
		if (file_exists($path.$file))
		{
			include($path.$file);
			$found = TRUE;
			break;
		}
	}

	if ( ! $found)
	{
		show_error('Unable to load your default controller. Please make sure the controller specified in your Routes.php file is valid.');
	}

	// Set a mark point for benchmarking
	$BM->mark('loading_time:_base_classes_end');

/*
 * ------------------------------------------------------
 *  Security check
 * ------------------------------------------------------
 *
 *  None of the functions in the app controller or the
 *  loader class can be called via the URI, nor can
 *  controller functions that begin with an underscore
 */
	$class  = $CI->router->fetch_class();
	$method = $CI->router->fetch_method();

	if ( ! class_exists($class)
		OR strncmp($method, '_', 1) == 0
		OR in_array(strtolower($method), array_map('strtolower', get_class_methods('CI_Controller')))
		)
	{
		show_404("{$class}/{$method}");
	}

/*
 * ------------------------------------------------------
 *  Is there a "pre_controller" hook?
 * ------------------------------------------------------
 */
	$EXT->_call_hook('pre_controller');

/*
 * ------------------------------------------------------
 *  Instantiate the requested controller
 * ------------------------------------------------------
 */
	// Mark a start point so we can benchmark the controller
	$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_start');

	$CI->routed = new $class();
	$name = strtolower($class);
	$CI->$name = $CI->routed;

/*
 * ------------------------------------------------------
 *  Is there a "post_controller_constructor" hook?
 * ------------------------------------------------------
 */
	$EXT->_call_hook('post_controller_constructor');

/*
 * ------------------------------------------------------
 *  Call the requested method
 * ------------------------------------------------------
 */
	// Is there a "remap" function? If so, we call it instead
	if (method_exists($CI->routed, '_remap'))
	{
		$CI->routed->_remap($method, array_slice($CI->uri->rsegments, 2));
	}
	else
	{
		// is_callable() returns TRUE on some versions of PHP 5 for private and protected
		// methods, so we'll use this workaround for consistent behavior
		if ( ! in_array(strtolower($method), array_map('strtolower', get_class_methods($CI->routed))))
		{
			// Check and see if we are using a 404 override and use it.
			if ( ! empty($CI->router->routes['404_override']))
			{
				$x = explode('/', $CI->router->routes['404_override']);
				$class = $x[0];
				$method = (isset($x[1]) ? $x[1] : 'index');
				if ( ! class_exists($class))
				{
					if ( ! file_exists(APPPATH.'controllers/'.$class.'.php'))
					{
						show_404("{$class}/{$method}");
					}

					include_once(APPPATH.'controllers/'.$class.'.php');
					unset($CI->routed);
					$CI->routed = new $class();
				}
			}
			else
			{
				show_404("{$class}/{$method}");
			}
		}

		// Call the requested method.
		// Any URI segments present (besides the class/function) will be passed to the method for convenience
		call_user_func_array(array(&$CI->routed, $method), array_slice($CI->uri->rsegments, 2));
	}


	// Mark a benchmark end point
	$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_end');

/*
 * ------------------------------------------------------
 *  Is there a "post_controller" hook?
 * ------------------------------------------------------
 */
	$EXT->_call_hook('post_controller');

/*
 * ------------------------------------------------------
 *  Send the final rendered output to the browser
 * ------------------------------------------------------
 */
	if ($EXT->_call_hook('display_override') === FALSE)
	{
		$CI->output->_display();
	}

/*
 * ------------------------------------------------------
 *  Is there a "post_system" hook?
 * ------------------------------------------------------
 */
	$EXT->_call_hook('post_system');

/*
 * ------------------------------------------------------
 *  Close the DB connection if one exists
 * ------------------------------------------------------
 */
	if (class_exists('CI_DB') AND isset($CI->db))
	{
		$CI->db->close();
	}


/* End of file CodeIgniter.php */
/* Location: ./system/core/CodeIgniter.php */

<?php defined('SYSPATH') or die('No direct script access.');

/**
 * helper for working with system-generated messages
 *
 * Each message has a type (error/warning/success etc). Also there are two
 * additional types: custom (for data store) and validate (for validation errors)
 *
 * @property	Session		$session
 * @property	View		$template
 *
 * @package    CMS
 * @author     Brotkin Ivan (BIakaVeron) <BIakaVeron@gmail.com>
 * @copyright  Copyright (c) 2009 Brotkin Ivan
 */

class message {

/*
 * @property Session $session
 *
 */

	// all helper data
	static protected $data = array();
	// array for previous requests data
	static protected $loaded = array();
	// array for currently added data only
	static protected $added = array();
	// default template name (use in render() method)
	static protected $template = 'messages/default';
	// this property allow to render all-in-one validation errors
	static protected $show_validation = FALSE;
	// what is the name of session variable
	static protected $session_var;
	// session object
	static protected $session = FALSE;


	/*
	 * Initial data loading
	 */
	public function init() {
		// don't call init() twice!
		if (self::$session !== FALSE) return FALSE;

		// set the session var name
		//self::$session_var = Kohana::config('message.message_key');
		self::$session_var = 'message_container';

		self::$session = Session::instance();

		// load session data
		self::$loaded = self::$session->get(self::$session_var);
		// clear session data
		self::$session->delete(self::$session_var);

		if (empty(self::$loaded)) {
			// create empty array - there is no data
			self::$loaded = array();
		}

		self::$data = self::$loaded;
	}

/**
 *
 * Save data in session. Saves new data only ($added array)
 *
 */
	public function save() {
		self::$session->set(self::$session_var, self::$added);
	}

/**
 *
 * Save all data in session ($data array).
 * Needed if we want to use current data on the next page
 *
 */

	public function save_all() {
		self::$session->set(self::$session_var, self::$data);
	}

/**
 *
 * Sets new template.
 *
 * @param string $template   new template name
 */

	public function set_template($template = 'default') {
		self::$template = 'messages/'.$template;
	}

/**
 *
 * Syncronizes $data, $added and $loaded arrays. Uses when some new data added
 *
 * @param string $type   which type of messages will we syncronize
 */

	private function sync($type = NULL) {
		if (is_null($type)) {
			// sync all data
			self::$data = self::$loaded + self::$added;
		}
		else {
			// sync $type messages only
			self::$data[$type] = (isset(self::$loaded[$type]) ? self::$loaded[$type] : array()) + (isset(self::$added[$type]) ? self::$added[$type] : array());
		}
	}

/**
 *
 * Removes data from helper.
 *
 * Can delete all data ($type is NULL), or $type data, or one value ($tag is set)
 *
 * @param string $type    messages type to remove
 * @param string $tag     tagname to remove one value
 */

	private function remove($type, $tag = NULL) {
		if (is_null($tag)) {
			// remove all data
			if (isset(self::$data[$type])) unset(self::$data[$type]);
			if (isset(self::$added[$type])) unset(self::$added[$type]);
			if (isset(self::$loaded[$type])) unset(self::$loaded[$type]);
		}
		else {
			// remove only $type data (with $tag checking)
			if (isset(self::$data[$type][$tag])) unset(self::$data[$type][$tag]);
			if (isset(self::$added[$type][$tag])) unset(self::$added[$type][$tag]);
			if (isset(self::$loaded[$type][$tag])) unset(self::$loaded[$type][$tag]);
		}
	}

/**
 *
 * Returns all values of supplied type and tagname and removes them from helper
 *
 * @param string $type   messages type
 * @param string $tag    tagname if one value needed
 * @return array
 */

	public function get_type($type = NULL, $tag = NULL) {
		if (is_null($type)) {
			// full data request
			$result = self::$data;
			self::clear();
			return $result;
		}

		// check for data of supplied type
		if (!isset(self::$data[$type])) return array();

		if (isset($tag)) {
			// returns one value if tagname supplied
			if (!isset(self::$data[$type][$tag])) return array();
			else $result = array(self::$data[$type][$tag]);
		}
		else {
			// get all data of this type
			$result = self::$data[$type];
		}

		// delete returned data from helper
		self::remove($type, $tag);

		return $result;
	}

/**
 *
 * @param string|array $message  data to store
 * @param string       $type     data type
 */

	public function add($message, $type = 'info') {
		if (is_array($message)) {
			// save data as $key=>$value
			if (isset(self::$added[$type])) {
				// this data type array already exists
				self::$added[$type] += $message;
			}
			else {
				// its a first data of this type
				self::$added[$type] = $message;
			}
		}
		else {
			// its a string data
			if (!isset(self::$added[$type])) {
				// there is no such type data
				self::$added[$type][] = $message;
			}
			elseif (!in_array($message, self::$added[$type])) {
				// add message string
				self::$added[$type][] = $message;
			}
		}

		// sync data after every change
		self::sync($type);

		self::save();
	}

/**
 *
 * Adds validation errors (usually from Validation->errors() method)
 *
 * Data key will be a fieldname, value - i18n-string with error description
 *
 * @param array  $errors        validation errors
 * @param string $i18n_file   i18n filename
 */

	public function add_validation(array $errors, $i18n_prefix = FALSE) {
		$result = array();
/*
		if ($i18n_file !== FALSE) {
		// collecting error messages in result array
			foreach($errors as $key => $value)
				$result[$key] = Kohana::lang($i18n_file.".".$key.".".$value);
		}
		else $result = $errors;*/
		$prefix = $i18n_prefix ? $i18n_prefix.'.'.$key.'.' : '';

		foreach($errors as $key => $value)
			$result[$key] = __($prefix.$value);
		// add errors to $added array with validation type
		self::add($result, 'validation');

		self::save();
	}

/**
 *
 * Delete all storing data (possible by type and tagname)
 *
 * @param string $type  data type to delete
 */

	public function clear($type = NULL, $tag = NULL) {
		if (is_null($type)) {
			// remove all data
			self::$data = self::$added = self::$loaded = array();
		}
		else {
			// remove supplied type only
			self::remove($type, $tag);
		}
	}

/**
 *
 * Returns custom type data by tagname.
 *
 * @param  string       $tag      tagname
 * @param  mixed        $default  default value if not exists
 * @return string|array
 */

	public function custom($tag = NULL, $default = NULL) {
		if (is_null($tag)) {
			// returns all custom data
			if (!isset(self::$data['custom'])) return array();
			$result = self::$data['custom'];
			// dont forget to clear data!
			self::remove('custom');
			return $result;
		}

		if (!isset(self::$data['custom'])) return $default;
		// check for existing
		if (isset(self::$data['custom'][$tag])) {
			// get tagged value and delete it from data
			$result = self::$data['custom'][$tag];
			self::remove('custom', $tag);
			return $result;
		}
		else return $default;
	}

/**
 * Shows message box with supplied type and tagname
 *
 * @param   string   $type   data type to render
 * @param   string   $tag    tagname if only one value needed
 * @return  boolean          FALSE if there was no rendering
 */

	public function render($type = NULL, $tag = NULL) {
		// check for data
		if (count(self::$data)==0) return FALSE;

		// custom data may use in form submitting for example
		if ($type === 'custom') return FALSE;

		if (is_null($type)) {
			// render all data by existing types
			foreach(self::$data as $type=>$value) {
				self::render($type, $tag);
			}
			return TRUE;
		}

		// don't show validation data without tagname
		if (self::$show_validation===FALSE AND $type == 'validation' AND is_null($tag))
			return FALSE;

		$data = self::get_type($type, $tag);
		// there is no data to render
		if (count($data)==0) return FALSE;

		$view = new View(self::$template);
		$view->data = $data;
		$view->type = $type;
		// if tagname supplied it will be a single box without many message string
		$view->inline = (isset($tag) ? " inline" : "");

		echo $view->render();

		return TRUE;
	}

	public function has_custom($tag = NULL) {
		if (!isset(self::$data['custom'])) return FALSE;
		if (is_null($tag))
			return (bool)count(self::$data['custom']);
		else return isset(self::$data['custom'][$tag]);
	}

}
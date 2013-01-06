<?php defined('SYSPATH') or die('No direct script access.');

/**
 * helper for working with system-generated messages
 *
 * Each message has a type (error/warning/success etc). Also there are two
 * additional types: custom (for data store) and validate (for validation errors)
 *
 * @author     Brotkin Ivan (BIakaVeron) <BIakaVeron@gmail.com>
 * @copyright  Copyright (c) 2009-2011 Brotkin Ivan
 *
 */

class Message
{
	// message types
	const SUCCESS     = 'success';
	const ERROR       = 'error';
	const VALIDATION  = 'validation';
	const NOTICE      = 'notice';
	const INFO        = 'info';
	const CUSTOM      = 'custom';

	protected static $_messages = array();

	protected static $_error_types = array();

	/**
	 * @var  Session
	 */
	protected static $_session;

	protected static $_session_key;

	protected static function _load()
	{
		Message::$_messages = Message::$_session->get(Message::$_session_key, array());
	}

	protected static function _sync()
	{
		if ( Message::$_config['autosave'])
		{
			return;
		}

		Message::save();
	}

	public static $_config = array();

	// default template name (use in render() method)
	public static $template = 'messages/default';

	public static function init()
	{
		Message::$_config = $config = Kohana::$config->load('message');
		Message::$_session = Session::instance($config['driver']);
		// error keys
		Message::$_error_types = (array)$config['errors'];
		if ( ! Arr::is_assoc(Message::$_error_types))
		{
			// convert array('error', 'notice') to array('error' => 'error', 'notice' => 'notice')
			Message::$_error_types = array_combine(Message::$_error_types, Message::$_error_types);
		}

		Message::$_session_key = $config['session_key'];
		Message::_load();

		if ($config['autosave'])
		{
			register_shutdown_function(array('Message', 'save'));
		}

		if ( isset($config['template']))
		{
			Message::$template = $config['template'];
		}
	}

	public static function get($type, $key = NULL, $default = NULL)
	{
		if (empty($key))
		{
			return Arr::get(Message::$_messages, $type, $default);
		}
		else
		{
			return isset(Message::$_messages[$type][$key]) ? Message::$_messages[$type][$key] : $default;
		}
	}

	public static function get_once($type, $key = NULL, $default = NULL)
	{
		$value = Message::get($type, $key, $default);
		Message::delete($type, $key);
		return $value;
	}

	public static function set($type, $key, $value)
	{
		Message::$_messages[$type][$key] = $value;
		Message::_sync();
	}

	public static function add($type, $value)
	{
		$values = (array)Message::get($type, NULL, array());
		$values[] = $value;
		Message::$_messages[$type] = $values;
		Message::_sync();
	}

	public static function delete($type, $key = NULL)
	{
		if ( ! empty($key))
		{
			unset(Message::$_messages[$type][$key]);
		}
		else
		{
			Message::$_messages[$type] = array();
		}

		Message::_sync();
	}

	public static function save()
	{
		Message::$_session->set(Message::$_session_key, Message::$_messages);
	}

	public static function get_errors($type = NULL)
	{
		if ($type === NULL)
		{
			$type = Message::$_error_types;
		}

		if (is_array($type))
		{
			$result = array();
			foreach($type as $error_type)
			{
				if ($errors = Message::get_once($error_type))
				{
					$result[$error_type] = $errors;
				}
			}

			return $result;
		}
		elseif (in_array($type, Message::$_error_types))
		{
			return Message::get_once($type);
		}

		return NULL;
	}

	/**
	 * Shows message box with supplied type and tagname
	 *
	 * @param   string   $type   data type to render
	 * @param   string   $key    tagname if only one value needed
	 * @return  boolean          FALSE if there was no rendering
	 */
	public static function render($type = NULL, $key = NULL)
	{
		// check for data
		if (count(Message::$_messages)==0) return FALSE;

		if (is_null($type))
		{
			// render all data by existing types
			foreach(Message::$_error_types as $type=>$value)
			{
				Message::render($type, $key);
			}
			return TRUE;
		}
		elseif (is_array($type))
		{
			foreach($type as $_type)
			{
				Message::render($_type);
			}
			return TRUE;
		}

		// custom data may use in form submitting for example
		if ($type == message::CUSTOM)
		{
			return FALSE;
		}

		// don't show validation data without tagname
		//if (Message::$_show_validation===FALSE AND $type == message::VALIDATION AND is_null($key))
		//	return FALSE;

		$data = Message::get_once($type, $key);
		// there is no data to render
		if (empty($data))
		{
			return FALSE;
		}

		$view = new View(Message::$template);
		$view->data = $data;
		$view->type = $type;
		// if tagname supplied it will be a single box without many message string
		$view->inline = (isset($key) ? " inline" : "");

		echo $view->render();

		return TRUE;
	}

	/**
	 * Dumps all messages. For tests only!
	 *
	 * @return string
	 */
	public static function dump()
	{
		return Debug::vars(Message::$_messages);
	}

	/**
	 * @param  mixed $value
	 *
	 * @return array|null
	 */
	public static function error($value = NULL)
	{
		if (func_num_args() == 0)
		{
			// its a getter
			return Message::get(Message::ERROR);
		}
		else
		{
			// setter
			Message::add(Message::ERROR, $value);
		}
	}

	/**
	 * @param  mixed $value
	 *
	 * @return array|null
	 */
	public static function notice($value = NULL)
	{
		if (func_num_args() == 0)
		{
			// its a getter
			return Message::get(Message::NOTICE);
		}
		else
		{
			// setter
			Message::add(Message::NOTICE, $value);
		}
	}

	/**
	 * @param  mixed $value
	 *
	 * @return array|null
	 */
	public static function success($value = NULL)
	{
		if (func_num_args() == 0)
		{
			// its a getter
			return Message::get(Message::SUCCESS);
		}
		else
		{
			// setter
			Message::add(Message::SUCCESS, $value);
		}
	}

	/**
	 * @param  mixed $value
	 *
	 * @return array|null
	 */
	public static function info($value = NULL)
	{
		if (func_num_args() == 0)
		{
			// its a getter
			return Message::get(Message::INFO);
		}
		else
		{
			// setter
			Message::add(Message::INFO, $value);
		}
	}

	/**
	 * @param  string|array  $key
	 * @param  mixed         $value
	 *
	 * @return mixed
	 */
	public static function custom($key, $value = NULL)
	{
		if (func_num_args() < 2)
		{
			if (is_array($key))
			{
				foreach($key as $_key => $_value)
				{
					Message::custom($_key, $_value);
				}
			}
			else {
				// its a getter
				return Message::get(Message::CUSTOM, $key);
			}
		}
		else
		{
			// setter
			Message::set(Message::CUSTOM, $key, $value);
		}
	}

	/**
	 *
	 * Use it for short calls:
	 *
	 *  Message::set('custom', 'foo', 'bar')
	 *  // short version:
	 *  Message::custom('foo', 'bar');
	 *
	 *
	 *  foreach(Message::get('error') as $error) {...}
	 *  // short version
	 *  foreach(Message::error() as $error) {...}
	 *
	 * @param $method
	 * @param $params
	 *
	 * @return mixed
	 */
	public static function __callStatic($method, $params)
	{
		$args = array($method) + $params;

		if (in_array($method, Message::$_error_types))
		{
			// error methods dont require tags
			$method = empty($params) ? 'get' : 'add';
		}
		else
		{
			// using tag names
			$method = count($params) > 1 ? 'set' : 'get';
		}

		return call_user_func_array(array('Message', $method), $args);
	}

}
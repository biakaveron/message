<?php

return array(
	'driver'         => NULL, // use default Session driver
	'session_key'    => 'Message_Container',
	'autosave'       => FALSE,
	// error types, handling by render() call
	// declare as type => css class
	'errors'         => array(
		Message::SUCCESS       => 'alert-success',
		Message::ERROR         => 'alert-error',
		Message::NOTICE        => 'alert-notice',
		Message::INFO          => 'alert-info',
	),
);
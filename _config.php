<?php

/**
 *	The misdirection specific configuration settings.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

if(!defined('MISDIRECTION_PATH')) {
	define('MISDIRECTION_PATH', rtrim(basename(dirname(__FILE__))));
}

// Update the current misdirection admin icon.

Config::inst()->update('MisdirectionAdmin', 'menu_icon', MISDIRECTION_PATH . '/images/icon.png');

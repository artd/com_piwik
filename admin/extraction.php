<?php
define('KICKSTART',1);
define('VERSION', '3.3.2');
define('KSDEBUG', 1);

@error_reporting(E_NONE);

// ==========================================================================================
// IIS missing REQUEST_URI workaround
// ==========================================================================================

/*
 * Based REQUEST_URI for IIS Servers 1.0 by NeoSmart Technologies
 * The proper method to solve IIS problems is to take a look at this:
 * http://neosmart.net/dl.php?id=7
 */

//This file should be located in the same directory as php.exe or php5isapi.dll

if (!isset($_SERVER['REQUEST_URI']))
{
	if (isset($_SERVER['HTTP_REQUEST_URI']))
	{
		$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REQUEST_URI'];
		//Good to go!
	}
	else
	{
		//Someone didn't follow the instructions!
		if(isset($_SERVER['SCRIPT_NAME']))
		$_SERVER['HTTP_REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
		else
		$_SERVER['HTTP_REQUEST_URI'] = $_SERVER['PHP_SELF'];
		if($_SERVER['QUERY_STRING']){
			$_SERVER['HTTP_REQUEST_URI'] .=  '?' . $_SERVER['QUERY_STRING'];
		}
		//WARNING: This is a workaround!
		//For guaranteed compatibility, HTTP_REQUEST_URI *MUST* be defined!
		//See product documentation for instructions!
		$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REQUEST_URI'];
	}
}

// Debugging function
function debugMsg($msg)
{
	$fp = fopen('debug.txt','at');
	fwrite($fp, $msg."\n");
	fclose($fp);
}
 ?>
<?php
/**
 * Akeeba Restoration Utility
 * A JSON-powered archive extraction tool
 * @copyright 2010 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license GNU GPL v.3.0 or - at your option - any later version
 * @package akeebabackup
 * @subpackage kickstart
 */

define('_AKEEBA_RESTORATION', 1);
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

// Unarchiver run states
define('AK_STATE_NOFILE',	0); // File header not read yet
define('AK_STATE_HEADER',	1); // File header read; ready to process data
define('AK_STATE_DATA',		2); // Processing file data
define('AK_STATE_DATAREAD',	3); // Finished processing file data; ready to post-process
define('AK_STATE_POSTPROC',	4); // Post-processing
define('AK_STATE_DONE',		5); // Done with post-processing

/* Windows system detection */
if(!defined('_AKEEBA_IS_WINDOWS'))
{
	if (function_exists('php_uname'))
		define('_AKEEBA_IS_WINDOWS', stristr(php_uname(), 'windows'));
	else
		define('_AKEEBA_IS_WINDOWS', DS == '\\');
}

// Make sure the locale is correct for basename() to work
if(function_exists('setlocale'))
{
	@setlocale(LC_ALL, 'en_US.UTF8');
}

// fnmatch not available on non-POSIX systems
// Thanks to soywiz@php.net for this usefull alternative function [http://gr2.php.net/fnmatch]
if (!function_exists('fnmatch')) {
	function fnmatch($pattern, $string) {
		return @preg_match(
			'/^' . strtr(addcslashes($pattern, '/\\.+^$(){}=!<>|'),
		array('*' => '.*', '?' => '.?')) . '$/i', $string
		);
	}
}

// Unicode-safe binary data length function
if(function_exists('mb_strlen')) {
	function akstringlen($string) { return mb_strlen($string,'8bit'); }
} else {
	function akstringlen($string) { return strlen($string); }
}

/**
 * Gets a query parameter from GET or POST data
 * @param $key
 * @param $default
 */
function getQueryParam( $key, $default = null )
{
	$value = null;

	if(array_key_exists($key, $_REQUEST)) {
		$value = $_REQUEST[$key];
	} elseif(array_key_exists($key, $_POST)) {
		$value = $_POST[$key];
	} elseif(array_key_exists($key, $_GET)) {
		$value = $_GET[$key];
	} else {
		return $default;
	}

	if(get_magic_quotes_gpc() && !is_null($value)) $value=stripslashes($value);

	return $value;
}

/**
 * Akeeba Backup's JSON compatibility layer
 *
 * On systems where json_encode and json_decode are not available, Akeeba
 * Backup will attempt to use PEAR's Services_JSON library to emulate them.
 * A copy of this library is included in this file and will be used if and
 * only if it isn't already loaded, e.g. due to PEAR's auto-loading, or a
 * 3PD extension loading it for its own purposes.
 */

/**
 * Converts to and from JSON format.
 *
 * JSON (JavaScript Object Notation) is a lightweight data-interchange
 * format. It is easy for humans to read and write. It is easy for machines
 * to parse and generate. It is based on a subset of the JavaScript
 * Programming Language, Standard ECMA-262 3rd Edition - December 1999.
 * This feature can also be found in  Python. JSON is a text format that is
 * completely language independent but uses conventions that are familiar
 * to programmers of the C-family of languages, including C, C++, C#, Java,
 * JavaScript, Perl, TCL, and many others. These properties make JSON an
 * ideal data-interchange language.
 *
 * This package provides a simple encoder and decoder for JSON notation. It
 * is intended for use with client-side Javascript applications that make
 * use of HTTPRequest to perform server communication functions - data can
 * be encoded into JSON notation for use in a client-side javascript, or
 * decoded from incoming Javascript requests. JSON format is native to
 * Javascript, and can be directly eval()'ed with no further parsing
 * overhead
 *
 * All strings should be in ASCII or UTF-8 format!
 *
 * LICENSE: Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met: Redistributions of source code must retain the
 * above copyright notice, this list of conditions and the following
 * disclaimer. Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
 * NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @category
 * @package     Services_JSON
 * @author      Michal Migurski <mike-json@teczno.com>
 * @author      Matt Knapp <mdknapp[at]gmail[dot]com>
 * @author      Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
 * @copyright   2005 Michal Migurski
 * @version     CVS: $Id: restore.php 612 2011-05-19 08:26:26Z nikosdion $
 * @license     http://www.opensource.org/licenses/bsd-license.php
 * @link        http://pear.php.net/pepr/pepr-proposal-show.php?id=198
 */

if(!defined('JSON_FORCE_OBJECT'))
{
	define('JSON_FORCE_OBJECT', 1);
}

if(!defined('SERVICES_JSON_SLICE'))
{
	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_SLICE',   1);

	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_IN_STR',  2);

	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_IN_ARR',  3);

	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_IN_OBJ',  4);

	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_IN_CMT', 5);

	/**
	 * Behavior switch for Services_JSON::decode()
	 */
	define('SERVICES_JSON_LOOSE_TYPE', 16);

	/**
	 * Behavior switch for Services_JSON::decode()
	 */
	define('SERVICES_JSON_SUPPRESS_ERRORS', 32);
}

/**
 * Converts to and from JSON format.
 *
 * Brief example of use:
 *
 * <code>
 * // create a new instance of Services_JSON
 * $json = new Services_JSON();
 *
 * // convert a complexe value to JSON notation, and send it to the browser
 * $value = array('foo', 'bar', array(1, 2, 'baz'), array(3, array(4)));
 * $output = $json->encode($value);
 *
 * print($output);
 * // prints: ["foo","bar",[1,2,"baz"],[3,[4]]]
 *
 * // accept incoming POST data, assumed to be in JSON notation
 * $input = file_get_contents('php://input', 1000000);
 * $value = $json->decode($input);
 * </code>
 */
if(!class_exists('Akeeba_Services_JSON'))
{
	class Akeeba_Services_JSON
	{
	   /**
	    * constructs a new JSON instance
	    *
	    * @param    int     $use    object behavior flags; combine with boolean-OR
	    *
	    *                           possible values:
	    *                           - SERVICES_JSON_LOOSE_TYPE:  loose typing.
	    *                                   "{...}" syntax creates associative arrays
	    *                                   instead of objects in decode().
	    *                           - SERVICES_JSON_SUPPRESS_ERRORS:  error suppression.
	    *                                   Values which can't be encoded (e.g. resources)
	    *                                   appear as NULL instead of throwing errors.
	    *                                   By default, a deeply-nested resource will
	    *                                   bubble up with an error, so all return values
	    *                                   from encode() should be checked with isError()
	    */
	    function Akeeba_Services_JSON($use = 0)
	    {
	        $this->use = $use;
	    }

	   /**
	    * convert a string from one UTF-16 char to one UTF-8 char
	    *
	    * Normally should be handled by mb_convert_encoding, but
	    * provides a slower PHP-only method for installations
	    * that lack the multibye string extension.
	    *
	    * @param    string  $utf16  UTF-16 character
	    * @return   string  UTF-8 character
	    * @access   private
	    */
	    function utf162utf8($utf16)
	    {
	        // oh please oh please oh please oh please oh please
	        if(function_exists('mb_convert_encoding')) {
	            return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
	        }

	        $bytes = (ord($utf16{0}) << 8) | ord($utf16{1});

	        switch(true) {
	            case ((0x7F & $bytes) == $bytes):
	                // this case should never be reached, because we are in ASCII range
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr(0x7F & $bytes);

	            case (0x07FF & $bytes) == $bytes:
	                // return a 2-byte UTF-8 character
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr(0xC0 | (($bytes >> 6) & 0x1F))
	                     . chr(0x80 | ($bytes & 0x3F));

	            case (0xFFFF & $bytes) == $bytes:
	                // return a 3-byte UTF-8 character
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr(0xE0 | (($bytes >> 12) & 0x0F))
	                     . chr(0x80 | (($bytes >> 6) & 0x3F))
	                     . chr(0x80 | ($bytes & 0x3F));
	        }

	        // ignoring UTF-32 for now, sorry
	        return '';
	    }

	   /**
	    * convert a string from one UTF-8 char to one UTF-16 char
	    *
	    * Normally should be handled by mb_convert_encoding, but
	    * provides a slower PHP-only method for installations
	    * that lack the multibye string extension.
	    *
	    * @param    string  $utf8   UTF-8 character
	    * @return   string  UTF-16 character
	    * @access   private
	    */
	    function utf82utf16($utf8)
	    {
	        // oh please oh please oh please oh please oh please
	        if(function_exists('mb_convert_encoding')) {
	            return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');
	        }

	        switch(strlen($utf8)) {
	            case 1:
	                // this case should never be reached, because we are in ASCII range
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return $utf8;

	            case 2:
	                // return a UTF-16 character from a 2-byte UTF-8 char
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr(0x07 & (ord($utf8{0}) >> 2))
	                     . chr((0xC0 & (ord($utf8{0}) << 6))
	                         | (0x3F & ord($utf8{1})));

	            case 3:
	                // return a UTF-16 character from a 3-byte UTF-8 char
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr((0xF0 & (ord($utf8{0}) << 4))
	                         | (0x0F & (ord($utf8{1}) >> 2)))
	                     . chr((0xC0 & (ord($utf8{1}) << 6))
	                         | (0x7F & ord($utf8{2})));
	        }

	        // ignoring UTF-32 for now, sorry
	        return '';
	    }

	   /**
	    * encodes an arbitrary variable into JSON format
	    *
	    * @param    mixed   $var    any number, boolean, string, array, or object to be encoded.
	    *                           see argument 1 to Services_JSON() above for array-parsing behavior.
	    *                           if var is a strng, note that encode() always expects it
	    *                           to be in ASCII or UTF-8 format!
	    *
	    * @return   mixed   JSON string representation of input var or an error if a problem occurs
	    * @access   public
	    */
	    function encode($var)
	    {
	        switch (gettype($var)) {
	            case 'boolean':
	                return $var ? 'true' : 'false';

	            case 'NULL':
	                return 'null';

	            case 'integer':
	                return (int) $var;

	            case 'double':
	            case 'float':
	                return (float) $var;

	            case 'string':
	                // STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT
	                $ascii = '';
	                $strlen_var = strlen($var);

	               /*
	                * Iterate over every character in the string,
	                * escaping with a slash or encoding to UTF-8 where necessary
	                */
	                for ($c = 0; $c < $strlen_var; ++$c) {

	                    $ord_var_c = ord($var{$c});

	                    switch (true) {
	                        case $ord_var_c == 0x08:
	                            $ascii .= '\b';
	                            break;
	                        case $ord_var_c == 0x09:
	                            $ascii .= '\t';
	                            break;
	                        case $ord_var_c == 0x0A:
	                            $ascii .= '\n';
	                            break;
	                        case $ord_var_c == 0x0C:
	                            $ascii .= '\f';
	                            break;
	                        case $ord_var_c == 0x0D:
	                            $ascii .= '\r';
	                            break;

	                        case $ord_var_c == 0x22:
	                        case $ord_var_c == 0x2F:
	                        case $ord_var_c == 0x5C:
	                            // double quote, slash, slosh
	                            $ascii .= '\\'.$var{$c};
	                            break;

	                        case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
	                            // characters U-00000000 - U-0000007F (same as ASCII)
	                            $ascii .= $var{$c};
	                            break;

	                        case (($ord_var_c & 0xE0) == 0xC0):
	                            // characters U-00000080 - U-000007FF, mask 110XXXXX
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c, ord($var{$c + 1}));
	                            $c += 1;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;

	                        case (($ord_var_c & 0xF0) == 0xE0):
	                            // characters U-00000800 - U-0000FFFF, mask 1110XXXX
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c,
	                                         ord($var{$c + 1}),
	                                         ord($var{$c + 2}));
	                            $c += 2;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;

	                        case (($ord_var_c & 0xF8) == 0xF0):
	                            // characters U-00010000 - U-001FFFFF, mask 11110XXX
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c,
	                                         ord($var{$c + 1}),
	                                         ord($var{$c + 2}),
	                                         ord($var{$c + 3}));
	                            $c += 3;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;

	                        case (($ord_var_c & 0xFC) == 0xF8):
	                            // characters U-00200000 - U-03FFFFFF, mask 111110XX
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c,
	                                         ord($var{$c + 1}),
	                                         ord($var{$c + 2}),
	                                         ord($var{$c + 3}),
	                                         ord($var{$c + 4}));
	                            $c += 4;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;

	                        case (($ord_var_c & 0xFE) == 0xFC):
	                            // characters U-04000000 - U-7FFFFFFF, mask 1111110X
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c,
	                                         ord($var{$c + 1}),
	                                         ord($var{$c + 2}),
	                                         ord($var{$c + 3}),
	                                         ord($var{$c + 4}),
	                                         ord($var{$c + 5}));
	                            $c += 5;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;
	                    }
	                }

	                return '"'.$ascii.'"';

	            case 'array':
	               /*
	                * As per JSON spec if any array key is not an integer
	                * we must treat the the whole array as an object. We
	                * also try to catch a sparsely populated associative
	                * array with numeric keys here because some JS engines
	                * will create an array with empty indexes up to
	                * max_index which can cause memory issues and because
	                * the keys, which may be relevant, will be remapped
	                * otherwise.
	                *
	                * As per the ECMA and JSON specification an object may
	                * have any string as a property. Unfortunately due to
	                * a hole in the ECMA specification if the key is a
	                * ECMA reserved word or starts with a digit the
	                * parameter is only accessible using ECMAScript's
	                * bracket notation.
	                */

	                // treat as a JSON object
	                if (is_array($var) && count($var) && (array_keys($var) !== range(0, sizeof($var) - 1))) {
	                    $properties = array_map(array($this, 'name_value'),
	                                            array_keys($var),
	                                            array_values($var));

	                    foreach($properties as $property) {
	                        if(Akeeba_Services_JSON::isError($property)) {
	                            return $property;
	                        }
	                    }

	                    return '{' . join(',', $properties) . '}';
	                }

	                // treat it like a regular array
	                $elements = array_map(array($this, 'encode'), $var);

	                foreach($elements as $element) {
	                    if(Akeeba_Services_JSON::isError($element)) {
	                        return $element;
	                    }
	                }

	                return '[' . join(',', $elements) . ']';

	            case 'object':
	                $vars = get_object_vars($var);

	                $properties = array_map(array($this, 'name_value'),
	                                        array_keys($vars),
	                                        array_values($vars));

	                foreach($properties as $property) {
	                    if(Akeeba_Services_JSON::isError($property)) {
	                        return $property;
	                    }
	                }

	                return '{' . join(',', $properties) . '}';

	            default:
	                return ($this->use & SERVICES_JSON_SUPPRESS_ERRORS)
	                    ? 'null'
	                    : new Akeeba_Services_JSON_Error(gettype($var)." can not be encoded as JSON string");
	        }
	    }

	   /**
	    * array-walking function for use in generating JSON-formatted name-value pairs
	    *
	    * @param    string  $name   name of key to use
	    * @param    mixed   $value  reference to an array element to be encoded
	    *
	    * @return   string  JSON-formatted name-value pair, like '"name":value'
	    * @access   private
	    */
	    function name_value($name, $value)
	    {
	        $encoded_value = $this->encode($value);

	        if(Akeeba_Services_JSON::isError($encoded_value)) {
	            return $encoded_value;
	        }

	        return $this->encode(strval($name)) . ':' . $encoded_value;
	    }

	   /**
	    * reduce a string by removing leading and trailing comments and whitespace
	    *
	    * @param    $str    string      string value to strip of comments and whitespace
	    *
	    * @return   string  string value stripped of comments and whitespace
	    * @access   private
	    */
	    function reduce_string($str)
	    {
	        $str = preg_replace(array(

	                // eliminate single line comments in '// ...' form
	                '#^\s*//(.+)$#m',

	                // eliminate multi-line comments in '/* ... */' form, at start of string
	                '#^\s*/\*(.+)\*/#Us',

	                // eliminate multi-line comments in '/* ... */' form, at end of string
	                '#/\*(.+)\*/\s*$#Us'

	            ), '', $str);

	        // eliminate extraneous space
	        return trim($str);
	    }

	   /**
	    * decodes a JSON string into appropriate variable
	    *
	    * @param    string  $str    JSON-formatted string
	    *
	    * @return   mixed   number, boolean, string, array, or object
	    *                   corresponding to given JSON input string.
	    *                   See argument 1 to Akeeba_Services_JSON() above for object-output behavior.
	    *                   Note that decode() always returns strings
	    *                   in ASCII or UTF-8 format!
	    * @access   public
	    */
	    function decode($str)
	    {
	        $str = $this->reduce_string($str);

	        switch (strtolower($str)) {
	            case 'true':
	                return true;

	            case 'false':
	                return false;

	            case 'null':
	                return null;

	            default:
	                $m = array();

	                if (is_numeric($str)) {
	                    // Lookie-loo, it's a number

	                    // This would work on its own, but I'm trying to be
	                    // good about returning integers where appropriate:
	                    // return (float)$str;

	                    // Return float or int, as appropriate
	                    return ((float)$str == (integer)$str)
	                        ? (integer)$str
	                        : (float)$str;

	                } elseif (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] == $m[2]) {
	                    // STRINGS RETURNED IN UTF-8 FORMAT
	                    $delim = substr($str, 0, 1);
	                    $chrs = substr($str, 1, -1);
	                    $utf8 = '';
	                    $strlen_chrs = strlen($chrs);

	                    for ($c = 0; $c < $strlen_chrs; ++$c) {

	                        $substr_chrs_c_2 = substr($chrs, $c, 2);
	                        $ord_chrs_c = ord($chrs{$c});

	                        switch (true) {
	                            case $substr_chrs_c_2 == '\b':
	                                $utf8 .= chr(0x08);
	                                ++$c;
	                                break;
	                            case $substr_chrs_c_2 == '\t':
	                                $utf8 .= chr(0x09);
	                                ++$c;
	                                break;
	                            case $substr_chrs_c_2 == '\n':
	                                $utf8 .= chr(0x0A);
	                                ++$c;
	                                break;
	                            case $substr_chrs_c_2 == '\f':
	                                $utf8 .= chr(0x0C);
	                                ++$c;
	                                break;
	                            case $substr_chrs_c_2 == '\r':
	                                $utf8 .= chr(0x0D);
	                                ++$c;
	                                break;

	                            case $substr_chrs_c_2 == '\\"':
	                            case $substr_chrs_c_2 == '\\\'':
	                            case $substr_chrs_c_2 == '\\\\':
	                            case $substr_chrs_c_2 == '\\/':
	                                if (($delim == '"' && $substr_chrs_c_2 != '\\\'') ||
	                                   ($delim == "'" && $substr_chrs_c_2 != '\\"')) {
	                                    $utf8 .= $chrs{++$c};
	                                }
	                                break;

	                            case preg_match('/\\\u[0-9A-F]{4}/i', substr($chrs, $c, 6)):
	                                // single, escaped unicode character
	                                $utf16 = chr(hexdec(substr($chrs, ($c + 2), 2)))
	                                       . chr(hexdec(substr($chrs, ($c + 4), 2)));
	                                $utf8 .= $this->utf162utf8($utf16);
	                                $c += 5;
	                                break;

	                            case ($ord_chrs_c >= 0x20) && ($ord_chrs_c <= 0x7F):
	                                $utf8 .= $chrs{$c};
	                                break;

	                            case ($ord_chrs_c & 0xE0) == 0xC0:
	                                // characters U-00000080 - U-000007FF, mask 110XXXXX
	                                //see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 2);
	                                ++$c;
	                                break;

	                            case ($ord_chrs_c & 0xF0) == 0xE0:
	                                // characters U-00000800 - U-0000FFFF, mask 1110XXXX
	                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 3);
	                                $c += 2;
	                                break;

	                            case ($ord_chrs_c & 0xF8) == 0xF0:
	                                // characters U-00010000 - U-001FFFFF, mask 11110XXX
	                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 4);
	                                $c += 3;
	                                break;

	                            case ($ord_chrs_c & 0xFC) == 0xF8:
	                                // characters U-00200000 - U-03FFFFFF, mask 111110XX
	                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 5);
	                                $c += 4;
	                                break;

	                            case ($ord_chrs_c & 0xFE) == 0xFC:
	                                // characters U-04000000 - U-7FFFFFFF, mask 1111110X
	                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 6);
	                                $c += 5;
	                                break;

	                        }

	                    }

	                    return $utf8;

	                } elseif (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
	                    // array, or object notation

	                    if ($str{0} == '[') {
	                        $stk = array(SERVICES_JSON_IN_ARR);
	                        $arr = array();
	                    } else {
	                        if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
	                            $stk = array(SERVICES_JSON_IN_OBJ);
	                            $obj = array();
	                        } else {
	                            $stk = array(SERVICES_JSON_IN_OBJ);
	                            $obj = new stdClass();
	                        }
	                    }

	                    array_push($stk, array('what'  => SERVICES_JSON_SLICE,
	                                           'where' => 0,
	                                           'delim' => false));

	                    $chrs = substr($str, 1, -1);
	                    $chrs = $this->reduce_string($chrs);

	                    if ($chrs == '') {
	                        if (reset($stk) == SERVICES_JSON_IN_ARR) {
	                            return $arr;

	                        } else {
	                            return $obj;

	                        }
	                    }

	                    //print("\nparsing {$chrs}\n");

	                    $strlen_chrs = strlen($chrs);

	                    for ($c = 0; $c <= $strlen_chrs; ++$c) {

	                        $top = end($stk);
	                        $substr_chrs_c_2 = substr($chrs, $c, 2);

	                        if (($c == $strlen_chrs) || (($chrs{$c} == ',') && ($top['what'] == SERVICES_JSON_SLICE))) {
	                            // found a comma that is not inside a string, array, etc.,
	                            // OR we've reached the end of the character list
	                            $slice = substr($chrs, $top['where'], ($c - $top['where']));
	                            array_push($stk, array('what' => SERVICES_JSON_SLICE, 'where' => ($c + 1), 'delim' => false));
	                            //print("Found split at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

	                            if (reset($stk) == SERVICES_JSON_IN_ARR) {
	                                // we are in an array, so just push an element onto the stack
	                                array_push($arr, $this->decode($slice));

	                            } elseif (reset($stk) == SERVICES_JSON_IN_OBJ) {
	                                // we are in an object, so figure
	                                // out the property name and set an
	                                // element in an associative array,
	                                // for now
	                                $parts = array();

	                                if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
	                                    // "name":value pair
	                                    $key = $this->decode($parts[1]);
	                                    $val = $this->decode($parts[2]);

	                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
	                                        $obj[$key] = $val;
	                                    } else {
	                                        $obj->$key = $val;
	                                    }
	                                } elseif (preg_match('/^\s*(\w+)\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
	                                    // name:value pair, where name is unquoted
	                                    $key = $parts[1];
	                                    $val = $this->decode($parts[2]);

	                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
	                                        $obj[$key] = $val;
	                                    } else {
	                                        $obj->$key = $val;
	                                    }
	                                }

	                            }

	                        } elseif ((($chrs{$c} == '"') || ($chrs{$c} == "'")) && ($top['what'] != SERVICES_JSON_IN_STR)) {
	                            // found a quote, and we are not inside a string
	                            array_push($stk, array('what' => SERVICES_JSON_IN_STR, 'where' => $c, 'delim' => $chrs{$c}));
	                            //print("Found start of string at {$c}\n");

	                        } elseif (($chrs{$c} == $top['delim']) &&
	                                 ($top['what'] == SERVICES_JSON_IN_STR) &&
	                                 ((strlen(substr($chrs, 0, $c)) - strlen(rtrim(substr($chrs, 0, $c), '\\'))) % 2 != 1)) {
	                            // found a quote, we're in a string, and it's not escaped
	                            // we know that it's not escaped becase there is _not_ an
	                            // odd number of backslashes at the end of the string so far
	                            array_pop($stk);
	                            //print("Found end of string at {$c}: ".substr($chrs, $top['where'], (1 + 1 + $c - $top['where']))."\n");

	                        } elseif (($chrs{$c} == '[') &&
	                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
	                            // found a left-bracket, and we are in an array, object, or slice
	                            array_push($stk, array('what' => SERVICES_JSON_IN_ARR, 'where' => $c, 'delim' => false));
	                            //print("Found start of array at {$c}\n");

	                        } elseif (($chrs{$c} == ']') && ($top['what'] == SERVICES_JSON_IN_ARR)) {
	                            // found a right-bracket, and we're in an array
	                            array_pop($stk);
	                            //print("Found end of array at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

	                        } elseif (($chrs{$c} == '{') &&
	                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
	                            // found a left-brace, and we are in an array, object, or slice
	                            array_push($stk, array('what' => SERVICES_JSON_IN_OBJ, 'where' => $c, 'delim' => false));
	                            //print("Found start of object at {$c}\n");

	                        } elseif (($chrs{$c} == '}') && ($top['what'] == SERVICES_JSON_IN_OBJ)) {
	                            // found a right-brace, and we're in an object
	                            array_pop($stk);
	                            //print("Found end of object at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

	                        } elseif (($substr_chrs_c_2 == '/*') &&
	                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
	                            // found a comment start, and we are in an array, object, or slice
	                            array_push($stk, array('what' => SERVICES_JSON_IN_CMT, 'where' => $c, 'delim' => false));
	                            $c++;
	                            //print("Found start of comment at {$c}\n");

	                        } elseif (($substr_chrs_c_2 == '*/') && ($top['what'] == SERVICES_JSON_IN_CMT)) {
	                            // found a comment end, and we're in one now
	                            array_pop($stk);
	                            $c++;

	                            for ($i = $top['where']; $i <= $c; ++$i)
	                                $chrs = substr_replace($chrs, ' ', $i, 1);

	                            //print("Found end of comment at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

	                        }

	                    }

	                    if (reset($stk) == SERVICES_JSON_IN_ARR) {
	                        return $arr;

	                    } elseif (reset($stk) == SERVICES_JSON_IN_OBJ) {
	                        return $obj;

	                    }

	                }
	        }
	    }

	    function isError($data, $code = null)
	    {
	        if (class_exists('pear')) {
	            return PEAR::isError($data, $code);
	        } elseif (is_object($data) && (get_class($data) == 'services_json_error' ||
	                                 is_subclass_of($data, 'services_json_error'))) {
	            return true;
	        }

	        return false;
	    }
	}

    class Akeeba_Services_JSON_Error
    {
        function Akeeba_Services_JSON_Error($message = 'unknown error', $code = null,
                                     $mode = null, $options = null, $userinfo = null)
        {

        }
    }
}

if(!function_exists('json_encode'))
{
	function json_encode($value, $options = 0) {
		$flags = SERVICES_JSON_LOOSE_TYPE;
		if( $options & JSON_FORCE_OBJECT ) $flags = 0;
		$encoder = new Akeeba_Services_JSON($flags);
		return $encoder->encode($value);
	}
}

if(!function_exists('json_decode'))
{
	function json_decode($value, $assoc = false)
	{
		$flags = 0;
		if($assoc) $flags = SERVICES_JSON_LOOSE_TYPE;
		$decoder = new Akeeba_Services_JSON($flags);
		return $decoder->decode($value);
	}
}

/**
 * The base class of Akeeba Engine objects. Allows for error and warnings logging
 * and propagation. Largely based on the Joomla! 1.5 JObject class.
 */
abstract class AKAbstractObject
{
	/** @var	array	An array of errors */
	private $_errors = array();

	/** @var	array	The queue size of the $_errors array. Set to 0 for infinite size. */
	protected $_errors_queue_size = 0;

	/** @var	array	An array of warnings */
	private $_warnings = array();

	/** @var	array	The queue size of the $_warnings array. Set to 0 for infinite size. */
	protected $_warnings_queue_size = 0;

	/**
	 * Public constructor, makes sure we are instanciated only by the factory class
	 */
	public function __construct()
	{
		/*
		// Assisted Singleton pattern
		if(function_exists('debug_backtrace'))
		{
			$caller=debug_backtrace();
			if(
				($caller[1]['class'] != 'AKFactory') &&
				($caller[2]['class'] != 'AKFactory') &&
				($caller[3]['class'] != 'AKFactory') &&
				($caller[4]['class'] != 'AKFactory')
			) {
				var_dump(debug_backtrace());
				trigger_error("You can't create direct descendants of ".__CLASS__, E_USER_ERROR);
			}
		}
		*/
	}

	/**
	 * Get the most recent error message
	 * @param	integer	$i Optional error index
	 * @return	string	Error message
	 */
	public function getError($i = null)
	{
		return $this->getItemFromArray($this->_errors, $i);
	}

	/**
	 * Return all errors, if any
	 * @return	array	Array of error messages
	 */
	public function getErrors()
	{
		return $this->_errors;
	}

	/**
	 * Add an error message
	 * @param	string $error Error message
	 */
	public function setError($error)
	{
		if($this->_errors_queue_size > 0)
		{
			if(count($this->_errors) >= $this->_errors_queue_size)
			{
				array_shift($this->_errors);
			}
		}
		array_push($this->_errors, $error);
	}

	/**
	 * Resets all error messages
	 */
	public function resetErrors()
	{
		$this->_errors = array();
	}

	/**
	 * Get the most recent warning message
	 * @param	integer	$i Optional warning index
	 * @return	string	Error message
	 */
	public function getWarning($i = null)
	{
		return $this->getItemFromArray($this->_warnings, $i);
	}

	/**
	 * Return all warnings, if any
	 * @return	array	Array of error messages
	 */
	public function getWarnings()
	{
		return $this->_warnings;
	}

	/**
	 * Add an error message
	 * @param	string $error Error message
	 */
	public function setWarning($warning)
	{
		if($this->_warnings_queue_size > 0)
		{
			if(count($this->_warnings) >= $this->_warnings_queue_size)
			{
				array_shift($this->_warnings);
			}
		}

		array_push($this->_warnings, $warning);
	}

	/**
	 * Resets all warning messages
	 */
	public function resetWarnings()
	{
		$this->_warnings = array();
	}

	/**
	 * Propagates errors and warnings to a foreign object. The foreign object SHOULD
	 * implement the setError() and/or setWarning() methods but DOESN'T HAVE TO be of
	 * AKAbstractObject type. For example, this can even be used to propagate to a
	 * JObject instance in Joomla!. Propagated items will be removed from ourself.
	 * @param object $object The object to propagate errors and warnings to.
	 */
	public function propagateToObject(&$object)
	{
		// Skip non-objects
		if(!is_object($object)) return;

		if( method_exists($object,'setError') )
		{
			if(!empty($this->_errors))
			{
				foreach($this->_errors as $error)
				{
					$object->setError($error);
				}
				$this->_errors = array();
			}
		}

		if( method_exists($object,'setWarning') )
		{
			if(!empty($this->_warnings))
			{
				foreach($this->_warnings as $warning)
				{
					$object->setWarning($warning);
				}
				$this->_warnings = array();
			}
		}
	}

	/**
	 * Propagates errors and warnings from a foreign object. Each propagated list is
	 * then cleared on the foreign object, as long as it implements resetErrors() and/or
	 * resetWarnings() methods.
	 * @param object $object The object to propagate errors and warnings from
	 */
	public function propagateFromObject(&$object)
	{
		if( method_exists($object,'getErrors') )
		{
			$errors = $object->getErrors();
			if(!empty($errors))
			{
				foreach($errors as $error)
				{
					$this->setError($error);
				}
			}
			if(method_exists($object,'resetErrors'))
			{
				$object->resetErrors();
			}
		}

		if( method_exists($object,'getWarnings') )
		{
			$warnings = $object->getWarnings();
			if(!empty($warnings))
			{
				foreach($warnings as $warning)
				{
					$this->setWarning($warning);
				}
			}
			if(method_exists($object,'resetWarnings'))
			{
				$object->resetWarnings();
			}
		}
	}

	/**
	 * Sets the size of the error queue (acts like a LIFO buffer)
	 * @param int $newSize The new queue size. Set to 0 for infinite length.
	 */
	protected function setErrorsQueueSize($newSize = 0)
	{
		$this->_errors_queue_size = (int)$newSize;
	}

	/**
	 * Sets the size of the warnings queue (acts like a LIFO buffer)
	 * @param int $newSize The new queue size. Set to 0 for infinite length.
	 */
	protected function setWarningsQueueSize($newSize = 0)
	{
		$this->_warnings_queue_size = (int)$newSize;
	}

	/**
	 * Returns the last item of a LIFO string message queue, or a specific item
	 * if so specified.
	 * @param array $array An array of strings, holding messages
	 * @param int $i Optional message index
	 * @return mixed The message string, or false if the key doesn't exist
	 */
	private function getItemFromArray($array, $i = null)
	{
		// Find the item
		if ( $i === null) {
			// Default, return the last item
			$item = end($array);
		}
		else
		if ( ! array_key_exists($i, $array) ) {
			// If $i has been specified but does not exist, return false
			return false;
		}
		else
		{
			$item	= $array[$i];
		}

		return $item;
	}

}

/**
 * File post processor engines base class
 */
abstract class AKAbstractPostproc extends AKAbstractObject
{
	/** @var string The current (real) file path we'll have to process */
	protected $filename = null;

	/** @var int The requested permissions */
	protected $perms = 0755;

	/** @var string The temporary file path we gave to the unarchiver engine */
	protected $tempFilename = null;

	/** @var int The UNIX timestamp of the file's desired modification date */
	public $timestamp = 0;

	/**
	 * Processes the current file, e.g. moves it from temp to final location by FTP
	 */
	abstract public function process();

	/**
	 * The unarchiver tells us the path to the filename it wants to extract and we give it
	 * a different path instead.
	 * @param string $filename The path to the real file
	 * @param int $perms The permissions we need the file to have
	 * @return string The path to the temporary file
	 */
	abstract public function processFilename($filename, $perms = 0755);

	/**
	 * Recursively creates a directory if it doesn't exist
	 * @param string $dirName The directory to create
	 * @param int $perms The permissions to give to that directory
	 */
	abstract public function createDirRecursive( $dirName, $perms );

	abstract public function chmod( $file, $perms );

	abstract public function unlink( $file );

	abstract public function rmdir( $directory );

	abstract public function rename( $from, $to );
}

/**
 * The base class of unarchiver classes
 */
abstract class AKAbstractUnarchiver extends AKAbstractPart
{
	/** @var string Archive filename */
	protected $filename = null;

	/** @var array List of the names of all archive parts */
	public $archiveList = array();

	/** @var int The total size of all archive parts */
	public $totalSize = array();

	/** @var integer Current archive part number */
	protected $currentPartNumber = -1;

	/** @var integer The offset inside the current part */
	protected $currentPartOffset = 0;

	/** @var bool Should I restore permissions? */
	protected $flagRestorePermissions = false;

	/** @var AKAbstractPostproc Post processing class */
	protected $postProcEngine = null;

	/** @var string Absolute path to prepend to extracted files */
	protected $addPath = '';

	/** @var array Which files to rename */
	public $renameFiles = array();

	/** @var array Which directories to rename */
	public $renameDirs = array();

	/** @var array Which files to skip */
	public $skipFiles = array();

	/** @var integer Chunk size for processing */
	protected $chunkSize = 524288;

	/** @var resource File pointer to the current archive part file */
	protected $fp = null;

	/** @var int Run state when processing the current archive file */
	protected $runState = null;

	/** @var stdClass File header data, as read by the readFileHeader() method */
	protected $fileHeader = null;

	/** @var int How much of the uncompressed data we've read so far */
	protected $dataReadLength = 0;

	/**
	 * Public constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Wakeup function, called whenever the class is unserialized
	 */
	public function __wakeup()
	{
		if($this->currentPartNumber >= 0)
		{
			$this->fp = @fopen($this->archiveList[$this->currentPartNumber], 'rb');
			if( (is_resource($this->fp)) && ($this->currentPartOffset > 0) )
			{
				@fseek($this->fp, $this->currentPartOffset);
			}
		}
	}

	/**
	 * Sleep function, called whenever the class is serialized
	 */
	public function shutdown()
	{
		if(is_resource($this->fp))
		{
			$this->currentPartOffset = @ftell($this->fp);
			@fclose($this->fp);
		}
	}

	/**
	 * Implements the abstract _prepare() method
	 */
	final protected function _prepare()
	{
		parent::__construct();

		if( count($this->_parametersArray) > 0 )
		{
			foreach($this->_parametersArray as $key => $value)
			{
				switch($key)
				{
					case 'filename': // Archive's absolute filename
						$this->filename = $value;
						break;

					case 'restore_permissions': // Should I restore permissions?
						$this->flagRestorePermissions = $value;
						break;

					case 'post_proc': // Should I use FTP?
						$this->postProcEngine =& AKFactory::getpostProc($value);
						break;

					case 'add_path': // Path to prepend
						$this->addPath = $value;
						$this->addPath = str_replace('\\','/',$this->addPath);
						$this->addPath = rtrim($this->addPath,'/');
						if(!empty($this->addPath)) $this->addPath .= '/';
						break;

					case 'rename_files': // Which files to rename (hash array)
						$this->renameFiles = $value;
						break;
					
					case 'rename_dirs': // Which files to rename (hash array)
						$this->renameDirs = $value;
						break;

					case 'skip_files': // Which files to skip (indexed array)
						$this->skipFiles = $value;
						break;
				}
			}
		}

		$this->scanArchives();

		$this->readArchiveHeader();
		$errMessage = $this->getError();
		if(!empty($errMessage))
		{
			$this->setState('error', $errMessage);
		}
		else
		{
			$this->runState = AK_STATE_NOFILE;
			$this->setState('prepared');
		}
	}

	protected function _run()
	{
		if($this->getState() == 'postrun') return;

		$this->setState('running');

		$timer =& AKFactory::getTimer();

		$status = true;
		while( $status && ($timer->getTimeLeft() > 0) )
		{
			switch( $this->runState )
			{
				case AK_STATE_NOFILE:
					$status = $this->readFileHeader();
					if($status)
					{
						// Send start of file notification
						$message = new stdClass;
						$message->type = 'startfile';
						$message->content = new stdClass;
						if( array_key_exists('realfile', get_object_vars($this->fileHeader)) ) {
							$message->content->realfile = $this->fileHeader->realFile;
						} else {
							$message->content->realfile = $this->fileHeader->file;
						}
						$message->content->file = $this->fileHeader->file;
						if( array_key_exists('compressed', get_object_vars($this->fileHeader)) ) {
							$message->content->compressed = $this->fileHeader->compressed;
						} else {
							$message->content->compressed = 0;
						}
						$message->content->uncompressed = $this->fileHeader->uncompressed;
						$this->notify($message);
					}
					break;

				case AK_STATE_HEADER:
				case AK_STATE_DATA:
					$status = $this->processFileData();
					break;

				case AK_STATE_DATAREAD:
				case AK_STATE_POSTPROC:
					$this->postProcEngine->timestamp = $this->fileHeader->timestamp;
					$status = $this->postProcEngine->process();
					$this->propagateFromObject( $this->postProcEngine );
					$this->runState = AK_STATE_DONE;
					break;

				case AK_STATE_DONE:
				default:
					if($status)
					{
						// Send end of file notification
						$message = new stdClass;
						$message->type = 'endfile';
						$message->content = new stdClass;
						if( array_key_exists('realfile', get_object_vars($this->fileHeader)) ) {
							$message->content->realfile = $this->fileHeader->realFile;
						} else {
							$message->content->realfile = $this->fileHeader->file;
						}
						$message->content->file = $this->fileHeader->file;
						if( array_key_exists('compressed', get_object_vars($this->fileHeader)) ) {
							$message->content->compressed = $this->fileHeader->compressed;
						} else {
							$message->content->compressed = 0;
						}
						$message->content->uncompressed = $this->fileHeader->uncompressed;
						$this->notify($message);
					}
					$this->runState = AK_STATE_NOFILE;
					continue;
			}
		}

		$error = $this->getError();
		if( !$status && ($this->runState == AK_STATE_NOFILE) && empty( $error ) )
		{
			// We just finished
			$this->setState('postrun');
		}
		elseif( !empty($error) )
		{
			$this->setState( 'error', $error );
		}
	}

	protected function _finalize()
	{
		// Nothing to do
		$this->setState('finished');
	}

	/**
	 * Returns the base extension of the file, e.g. '.jpa'
	 * @return string
	 */
	private function getBaseExtension()
	{
		static $baseextension;

		if(empty($baseextension))
		{
			$basename = basename($this->filename);
			$lastdot = strrpos($basename,'.');
			$baseextension = substr($basename, $lastdot);
		}

		return $baseextension;
	}

	/**
	 * Scans for archive parts
	 */
	private function scanArchives()
	{
		$privateArchiveList = array();

		// Get the components of the archive filename
		$dirname = dirname($this->filename);
		$base_extension = $this->getBaseExtension();
		$basename = basename($this->filename, $base_extension);
		$this->totalSize = 0;

		// Scan for multiple parts until we don't find any more of them
		$count = 0;
		$found = true;
		$this->archiveList = array();
		while($found)
		{
			++$count;
			$extension = substr($base_extension, 0, 2).sprintf('%02d', $count);
			$filename = $dirname.DIRECTORY_SEPARATOR.$basename.$extension;
			$found = file_exists($filename);
			if($found)
			{
				// Add yet another part, with a numeric-appended filename
				$this->archiveList[] = $filename;

				$filesize = @filesize($filename);
				$this->totalSize += $filesize;

				$privateArchiveList[] = array($filename, $filesize);
			}
			else
			{
				// Add the last part, with the regular extension
				$this->archiveList[] = $this->filename;

				$filename = $this->filename;
				$filesize = @filesize($filename);
				$this->totalSize += $filesize;

				$privateArchiveList[] = array($filename, $filesize);
			}
		}

		$this->currentPartNumber = -1;
		$this->currentPartOffset = 0;
		$this->runState = AK_STATE_NOFILE;

		// Send start of file notification
		$message = new stdClass;
		$message->type = 'totalsize';
		$message->content = new stdClass;
		$message->content->totalsize = $this->totalSize;
		$message->content->filelist = $privateArchiveList;
		$this->notify($message);
	}

	/**
	 * Opens the next part file for reading
	 */
	protected function nextFile()
	{
		++$this->currentPartNumber;

		if( $this->currentPartNumber > (count($this->archiveList) - 1) )
		{
			$this->setState('postrun');
			return false;
		}
		else
		{
			if( is_resource($this->fp) ) @fclose($this->fp);
			$this->fp = @fopen( $this->archiveList[$this->currentPartNumber], 'rb' );
			fseek($this->fp, 0);
			$this->currentPartOffset = 0;
			return true;
		}
	}

	/**
	 * Returns true if we have reached the end of file
	 * @param $local bool True to return EOF of the local file, false (default) to return if we have reached the end of the archive set
	 * @return bool True if we have reached End Of File
	 */
	protected function isEOF($local = false)
	{
		$eof = @feof($this->fp);

		if(!$eof)
		{
			// Border case: right at the part's end (eeeek!!!). For the life of me, I don't understand why
			// feof() doesn't report true. It expects the fp to be positioned *beyond* the EOF to report
			// true. Incredible! :(
			$position = @ftell($this->fp);
			$filesize = @filesize( $this->archiveList[$this->currentPartNumber] );
			if( $position >= $filesize  ) $eof = true;
		}

		if($local)
		{
			return $eof;
		}
		else
		{
			return $eof && ($this->currentPartNumber >= (count($this->archiveList)-1) );
		}
	}

	/**
	 * Tries to make a directory user-writable so that we can write a file to it
	 * @param $path string A path to a file
	 */
	protected function setCorrectPermissions($path)
	{
		static $rootDir = null;
		
		if(is_null($rootDir)) {
			$rootDir = rtrim(AKFactory::get('kickstart.setup.destdir',''),'/\\');
		}
		
		$directory = rtrim(dirname($path),'/\\');
		if($directory != $rootDir) {
			// Is this an unwritable directory?
			if(!is_writeable($directory)) {
				$this->postProcEngine->chmod( $directory, 0755 );
			}
		}
		$this->postProcEngine->chmod( $path, 0644 );
	}

	/**
	 * Concrete classes are supposed to use this method in order to read the archive's header and
	 * prepare themselves to the point of being ready to extract the first file.
	 */
	protected abstract function readArchiveHeader();

	/**
	 * Concrete classes must use this method to read the file header
	 * @return bool True if reading the file was successful, false if an error occured or we reached end of archive
	 */
	protected abstract function readFileHeader();

	/**
	 * Concrete classes must use this method to process file data. It must set $runState to AK_STATE_DATAREAD when
	 * it's finished processing the file data.
	 * @return bool True if processing the file data was successful, false if an error occured
	 */
	protected abstract function processFileData();

	/**
	 * Reads data from the archive and notifies the observer with the 'reading' message
	 * @param $fp
	 * @param $length
	 */
	protected function fread($fp, $length = null)
	{
		if(is_numeric($length))
		{
			if($length > 0) {
				$data = fread($fp, $length);
			} else {
				$data = fread($fp);
			}
		}
		else
		{
			$data = fread($fp);
		}
		if($data === false) $data = '';

		// Send start of file notification
		$message = new stdClass;
		$message->type = 'reading';
		$message->content = new stdClass;
		$message->content->length = strlen($data);
		$this->notify($message);

		return $data;
	}
}

/**
 * The superclass of all Akeeba Kickstart parts. The "parts" are intelligent stateful
 * classes which perform a single procedure and have preparation, running and
 * finalization phases. The transition between phases is handled automatically by
 * this superclass' tick() final public method, which should be the ONLY public API
 * exposed to the rest of the Akeeba Engine.
 */
abstract class AKAbstractPart extends AKAbstractObject
{
	/**
	 * Indicates whether this part has finished its initialisation cycle
	 * @var boolean
	 */
	protected $isPrepared = false;

	/**
	 * Indicates whether this part has more work to do (it's in running state)
	 * @var boolean
	 */
	protected $isRunning = false;

	/**
	 * Indicates whether this part has finished its finalization cycle
	 * @var boolean
	 */
	protected $isFinished = false;

	/**
	 * Indicates whether this part has finished its run cycle
	 * @var boolean
	 */
	protected $hasRan = false;

	/**
	 * The name of the engine part (a.k.a. Domain), used in return table
	 * generation.
	 * @var string
	 */
	protected $active_domain = "";

	/**
	 * The step this engine part is in. Used verbatim in return table and
	 * should be set by the code in the _run() method.
	 * @var string
	 */
	protected $active_step = "";

	/**
	 * A more detailed description of the step this engine part is in. Used
	 * verbatim in return table and should be set by the code in the _run()
	 * method.
	 * @var string
	 */
	protected $active_substep = "";

	/**
	 * Any configuration variables, in the form of an array.
	 * @var array
	 */
	protected $_parametersArray = array();

	/** @var string The database root key */
	protected $databaseRoot = array();

	/** @var int Last reported warnings's position in array */
	private $warnings_pointer = -1;

	/** @var array An array of observers */
	protected $observers = array();

	/**
	 * Runs the preparation for this part. Should set _isPrepared
	 * to true
	 */
	abstract protected function _prepare();

	/**
	 * Runs the finalisation process for this part. Should set
	 * _isFinished to true.
	 */
	abstract protected function _finalize();

	/**
	 * Runs the main functionality loop for this part. Upon calling,
	 * should set the _isRunning to true. When it finished, should set
	 * the _hasRan to true. If an error is encountered, setError should
	 * be used.
	 */
	abstract protected function _run();

	/**
	 * Sets the BREAKFLAG, which instructs this engine part that the current step must break immediately,
	 * in fear of timing out.
	 */
	protected function setBreakFlag()
	{
		AKFactory::set('volatile.breakflag', true);
	}

	/**
	 * Sets the engine part's internal state, in an easy to use manner
	 *
	 * @param	string	$state			One of init, prepared, running, postrun, finished, error
	 * @param	string	$errorMessage	The reported error message, should the state be set to error
	 */
	protected function setState($state = 'init', $errorMessage='Invalid setState argument')
	{
		switch($state)
		{
			case 'init':
				$this->isPrepared = false;
				$this->isRunning  = false;
				$this->isFinished = false;
				$this->hasRun     = false;
				break;

			case 'prepared':
				$this->isPrepared = true;
				$this->isRunning  = false;
				$this->isFinished = false;
				$this->hasRun     = false;
				break;

			case 'running':
				$this->isPrepared = true;
				$this->isRunning  = true;
				$this->isFinished = false;
				$this->hasRun     = false;
				break;

			case 'postrun':
				$this->isPrepared = true;
				$this->isRunning  = false;
				$this->isFinished = false;
				$this->hasRun     = true;
				break;

			case 'finished':
				$this->isPrepared = true;
				$this->isRunning  = false;
				$this->isFinished = true;
				$this->hasRun     = false;
				break;

			case 'error':
			default:
				$this->setError($errorMessage);
				break;
		}
	}

	/**
	 * The public interface to an engine part. This method takes care for
	 * calling the correct method in order to perform the initialisation -
	 * run - finalisation cycle of operation and return a proper reponse array.
	 * @return	array	A Reponse Array
	 */
	final public function tick()
	{
		// Call the right action method, depending on engine part state
		switch( $this->getState() )
		{
			case "init":
				$this->_prepare();
				break;
			case "prepared":
				$this->_run();
				break;
			case "running":
				$this->_run();
				break;
			case "postrun":
				$this->_finalize();
				break;
		}

		// Send a Return Table back to the caller
		$out = $this->_makeReturnTable();
		return $out;
	}

	/**
	 * Returns a copy of the class's status array
	 * @return array
	 */
	public function getStatusArray()
	{
		return $this->_makeReturnTable();
	}

	/**
	 * Sends any kind of setup information to the engine part. Using this,
	 * we avoid passing parameters to the constructor of the class. These
	 * parameters should be passed as an indexed array and should be taken
	 * into account during the preparation process only. This function will
	 * set the error flag if it's called after the engine part is prepared.
	 *
	 * @param array $parametersArray The parameters to be passed to the
	 * engine part.
	 */
	final public function setup( $parametersArray )
	{
		if( $this->isPrepared )
		{
			$this->setState('error', "Can't modify configuration after the preparation of " . $this->active_domain);
		}
		else
		{
			$this->_parametersArray = $parametersArray;
			if(array_key_exists('root', $parametersArray))
			{
				$this->databaseRoot = $parametersArray['root'];
			}
		}
	}

	/**
	 * Returns the state of this engine part.
	 *
	 * @return string The state of this engine part. It can be one of
	 * error, init, prepared, running, postrun, finished.
	 */
	final public function getState()
	{
		if( $this->getError() )
		{
			return "error";
		}

		if( !($this->isPrepared) )
		{
			return "init";
		}

		if( !($this->isFinished) && !($this->isRunning) && !( $this->hasRun ) && ($this->isPrepared) )
		{
			return "prepared";
		}

		if ( !($this->isFinished) && $this->isRunning && !( $this->hasRun ) )
		{
			return "running";
		}

		if ( !($this->isFinished) && !($this->isRunning) && $this->hasRun )
		{
			return "postrun";
		}

		if ( $this->isFinished )
		{
			return "finished";
		}
	}

	/**
	 * Constructs a Response Array based on the engine part's state.
	 * @return array The Response Array for the current state
	 */
	final protected function _makeReturnTable()
	{
		// Get a list of warnings
		$warnings = $this->getWarnings();
		// Report only new warnings if there is no warnings queue size
		if( $this->_warnings_queue_size == 0 )
		{
			if( ($this->warnings_pointer > 0) && ($this->warnings_pointer < (count($warnings)) ) )
			{
				$warnings = array_slice($warnings, $this->warnings_pointer + 1);
				$this->warnings_pointer += count($warnings);
			}
			else
			{
				$this->warnings_pointer = count($warnings);
			}
		}

		$out =  array(
			'HasRun'	=> (!($this->isFinished)),
			'Domain'	=> $this->active_domain,
			'Step'		=> $this->active_step,
			'Substep'	=> $this->active_substep,
			'Error'		=> $this->getError(),
			'Warnings'	=> $warnings
		);

		return $out;
	}

	final protected function setDomain($new_domain)
	{
		$this->active_domain = $new_domain;
	}

	final public function getDomain()
	{
		return $this->active_domain;
	}

	final protected function setStep($new_step)
	{
		$this->active_step = $new_step;
	}

	final public function getStep()
	{
		return $this->active_step;
	}

	final protected function setSubstep($new_substep)
	{
		$this->active_substep = $new_substep;
	}

	final public function getSubstep()
	{
		return $this->active_substep;
	}

	/**
	 * Attaches an observer object
	 * @param AKAbstractPartObserver $obs
	 */
	function attach(AKAbstractPartObserver $obs) {
        $this->observers["$obs"] = $obs;
    }

	/**
	 * Dettaches an observer object
	 * @param AKAbstractPartObserver $obs
	 */
    function detach(AKAbstractPartObserver $obs) {
        delete($this->observers["$obs"]);
    }

    /**
     * Notifies observers each time something interesting happened to the part
     * @param mixed $message The event object
     */
	protected function notify($message) {
        foreach ($this->observers as $obs) {
            $obs->update($this, $message);
        }
    }
}

/**
 * Descendants of this class can be used in the unarchiver's observer methods (attach, detach and notify)
 * @author Nicholas
 *
 */
abstract class AKAbstractPartObserver
{
	abstract public function update($object, $message);
}

/**
 * Direct file writer
 */
class AKPostprocDirect extends AKAbstractPostproc
{
	public function process()
	{
		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);
		if($restorePerms)
		{
			@chmod($this->filename, $this->perms);
		}
		else
		{
			if(@is_file($this->filename))
			{
				@chmod($this->filename, 0644);
			}
			else
			{
				@chmod($this->filename, 0755);
			}
		}
		if($this->timestamp > 0)
		{
			@touch($this->filename, $this->timestamp);
		}
		return true;
	}

	public function processFilename($filename, $perms = 0755)
	{
		$this->perms = $perms;
		$this->filename = $filename;
		return $filename;
	}

	public function createDirRecursive( $dirName, $perms )
	{
		if( AKFactory::get('kickstart.setup.dryrun','0') ) return true;
		if (@mkdir($dirName, 0755, true)) {
			@chmod($dirName, 0755);
			return true;
		}

		$root = AKFactory::get('kickstart.setup.destdir');
		$root = rtrim(str_replace('\\','/',$root),'/');
		$dir = rtrim(str_replace('\\','/',$dirName),'/');
		if(strpos($dir, $root) === 0) {
			$dir = ltrim(substr($dir, strlen($root)), '/');
			$root .= '/';
		} else {
			$root = '';
		}
		
		if(empty($dir)) return true;

		$dirArray = explode('/', $dir);
		$path = '';
		foreach( $dirArray as $dir )
		{
			$path .= $dir . '/';
			$ret = is_dir($root.$path) ? true : @mkdir($root.$path);
			if( !$ret ) {
				// Is this a file instead of a directory?
				if(is_file($root.$path) )
				{
					@unlink($root.$path);
					$ret = @mkdir($root.$path);
				}
				if( !$ret ) {
					$this->setError( AKText::sprintf('COULDNT_CREATE_DIR',$path) );
					return false;
				}
			}
			// Try to set new directory permissions to 0755
			@chmod($root.$path, $perms);
		}
		return true;
	}

	public function chmod( $file, $perms )
	{
		if( AKFactory::get('kickstart.setup.dryrun','0') ) return true;

		return @chmod( $file, $perms );
	}

	public function unlink( $file )
	{
		return @unlink( $file );
	}

	public function rmdir( $directory )
	{
		return @rmdir( $directory );
	}

	public function rename( $from, $to )
	{
		return @rename($from, $to);
	}

}

/**
 * FTP file writer
 */
class AKPostprocFTP extends AKAbstractPostproc
{
	/** @var bool Should I use FTP over implicit SSL? */
	public $useSSL = false;
	/** @var bool use Passive mode? */
	public $passive = true;
	/** @var string FTP host name */
	public $host = '';
	/** @var int FTP port */
	public $port = 21;
	/** @var string FTP user name */
	public $user = '';
	/** @var string FTP password */
	public $pass = '';
	/** @var string FTP initial directory */
	public $dir = '';
	/** @var resource The FTP handle */
	private $handle = null;
	/** @var string The temporary directory where the data will be stored */
	private $tempDir = '';

	public function __construct()
	{
		parent::__construct();

		$this->useSSL = AKFactory::get('kickstart.ftp.ssl', false);
		$this->passive = AKFactory::get('kickstart.ftp.passive', true);
		$this->host = AKFactory::get('kickstart.ftp.host', '');
		$this->port = AKFactory::get('kickstart.ftp.port', 21);
		if(trim($this->port) == '') $this->port = 21;
		$this->user = AKFactory::get('kickstart.ftp.user', '');
		$this->pass = AKFactory::get('kickstart.ftp.pass', '');
		$this->dir = AKFactory::get('kickstart.ftp.dir', '');
		$this->tempDir = AKFactory::get('kickstart.ftp.tempdir', '');

		$connected = $this->connect();

		if($connected)
		{
			if(!empty($this->tempDir))
			{
				$tempDir = rtrim($this->tempDir, '/\\').'/';
				$writable = $this->isDirWritable($tempDir);
			}
			else
			{
				$tempDir = '';
				$writable = false;
			}

			if(!$writable) {
				// Default temporary directory is the current root
				$tempDir = function_exists('getcwd') ? getcwd() : dirname(__FILE__);
				if(empty($tempDir))
				{
					// Oh, we have no directory reported!
					$tempDir = '.';
				}
				$absoluteDirToHere = $tempDir;
				$tempDir = rtrim(str_replace('\\','/',$tempDir),'/');
				if(!empty($tempDir)) $tempDir .= '/';
				$this->tempDir = $tempDir;
				// Is this directory writable?
				$writable = $this->isDirWritable($tempDir);
			}

			if(!$writable)
			{
				// Nope. Let's try creating a temporary directory in the site's root.
				$tempDir = $absoluteDirToHere.DS.'kicktemp';
				$this->createDirRecursive($tempDir, 0777);
				// Try making it writable...
				$this->fixPermissions($tempDir);
				$writable = $this->isDirWritable($tempDir);
			}

			// Was the new directory writable?
			if(!$writable)
			{
				// Let's see if the user has specified one
				$userdir = AKFactory::get('kickstart.ftp.tempdir', '');
				if(!empty($userdir))
				{
					// Is it an absolute or a relative directory?
					$absolute = false;
					$absolute = $absolute || ( substr($userdir,0,1) == '/' );
					$absolute = $absolute || ( substr($userdir,1,1) == ':' );
					$absolute = $absolute || ( substr($userdir,2,1) == ':' );
					if(!$absolute)
					{
						// Make absolute
						$tempDir = $absoluteDirToHere.$userdir;
					}
					else
					{
						// it's already absolute
						$tempDir = $userdir;
					}
					// Does the directory exist?
					if( is_dir($tempDir) )
					{
						// Yeah. Is it writable?
						$writable = $this->isDirWritable($tempDir);
					}
				}
			}
			$this->tempDir = $tempDir;

			if(!$writable)
			{
				// No writable directory found!!!
				$this->setError(AKText::_('FTP_TEMPDIR_NOT_WRITABLE'));
			}
			else
			{
				AKFactory::set('kickstart.ftp.tempdir', $tempDir);
				$this->tempDir = $tempDir;
			}
		}
	}

	function __wakeup()
	{
		$this->connect();
	}

	public function connect()
	{
		// Connect to server, using SSL if so required
		if($this->useSSL) {
			$this->handle = @ftp_ssl_connect($this->host, $this->port);
		} else {
			$this->handle = @ftp_connect($this->host, $this->port);
		}
		if($this->handle === false)
		{
			$this->setError(AKText::_('WRONG_FTP_HOST'));
			return false;
		}

		// Login
		if(! @ftp_login($this->handle, $this->user, $this->pass))
		{
			$this->setError(AKText::_('WRONG_FTP_USER'));
			@ftp_close($this->handle);
			return false;
		}

		// Change to initial directory
		if(! @ftp_chdir($this->handle, $this->dir))
		{
			$this->setError(AKText::_('WRONG_FTP_PATH1'));
			@ftp_close($this->handle);
			return false;
		}

		// Enable passive mode if the user requested it
		if( $this->passive )
		{
			@ftp_pasv($this->handle, true);
		}
		else
		{
			@ftp_pasv($this->handle, false);
		}

		return true;
	}

	public function process()
	{
		if( is_null($this->tempFilename) )
		{
			// If an empty filename is passed, it means that we shouldn't do any post processing, i.e.
			// the entity was a directory or symlink
			return true;
		}

		$remotePath = dirname($this->filename);
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$removePath = ltrim($removePath, "/");
			$remotePath = ltrim($remotePath, "/");
			$left = substr($remotePath, 0, strlen($removePath));
			if($left == $removePath)
			{
				$remotePath = substr($remotePath, strlen($removePath));
			}
		}

		$absoluteFSPath = dirname($this->filename);
		$relativeFTPPath = trim($remotePath, '/');
		$absoluteFTPPath = '/'.trim( $this->dir, '/' ).'/'.trim($remotePath, '/');
		$onlyFilename = basename($this->filename);

		$remoteName = $absoluteFTPPath.'/'.$onlyFilename;

		$ret = @ftp_chdir($this->handle, $absoluteFTPPath);
		if($ret === false)
		{
			$ret = $this->createDirRecursive( $absoluteFSPath, 0755);
			if($ret === false) {
				$this->setError(AKText::sprintf('FTP_COULDNT_UPLOAD', $this->filename));
				return false;
			}
			$ret = @ftp_chdir($this->handle, $absoluteFTPPath);
			if($ret === false) {
				$this->setError(AKText::sprintf('FTP_COULDNT_UPLOAD', $this->filename));
				return false;
			}
		}

		$ret = @ftp_put($this->handle, $remoteName, $this->tempFilename, FTP_BINARY);
		if($ret === false)
		{
			// If we couldn't create the file, attempt to fix the permissions in the PHP level and retry!
			$this->fixPermissions($this->filename);
			$this->unlink($this->filename);

			$fp = @fopen($this->tempFilename);
			if($fp !== false)
			{
				$ret = @ftp_fput($this->handle, $remoteName, $fp, FTP_BINARY);
				@fclose($fp);
			}
			else
			{
				$ret = false;
			}
		}
		@unlink($this->tempFilename);

		if($ret === false)
		{
			$this->setError(AKText::sprintf('FTP_COULDNT_UPLOAD', $this->filename));
			return false;
		}
		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);
		if($restorePerms)
		{
			@ftp_chmod($this->_handle, $perms, $remoteName);
		}
		else
		{
			@ftp_chmod($this->_handle, 0644, $remoteName);
		}
		return true;
	}

	public function processFilename($filename, $perms = 0755)
	{
		// Catch some error conditions...
		if($this->getError())
		{
			return false;
		}

		// If a null filename is passed, it means that we shouldn't do any post processing, i.e.
		// the entity was a directory or symlink
		if(is_null($filename))
		{
			$this->filename = null;
			$this->tempFilename = null;
			return null;
		}

		// Strip absolute filesystem path to website's root
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$left = substr($filename, 0, strlen($removePath));
			if($left == $removePath)
			{
				$filename = substr($filename, strlen($removePath));
			}
		}

		// Trim slash on the left
		$filename = ltrim($filename, '/');

		$this->filename = $filename;
		$this->tempFilename = tempnam($this->tempDir, 'kickstart-');
		$this->perms = $perms;

		if( empty($this->tempFilename) )
		{
			// Oops! Let's try something different
			$this->tempFilename = $this->tempDir.DS.'kickstart-'.time().'.dat';
		}

		return $this->tempFilename;
	}

	private function isDirWritable($dir)
	{
		$fp = @fopen($dir.DS.'kickstart.dat', 'wb');
		if($fp === false)
		{
			return false;
		}
		else
		{
			@fclose($fp);
			unlink($dir.DS.'kickstart.dat');
			return true;
		}
	}

	public function createDirRecursive( $dirName, $perms )
	{
		// Strip absolute filesystem path to website's root
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			// UNIXize the paths
			$removePath = str_replace('\\','/',$removePath);
			$dirName = str_replace('\\','/',$dirName);
			// Make sure they both end in a slash
			$removePath = rtrim($removePath,'/\\').'/';
			$dirName = rtrim($dirName,'/\\').'/';
			// Process the path removal
			$left = substr($dirName, 0, strlen($removePath));
			if($left == $removePath)
			{
				$dirName = substr($dirName, strlen($removePath));
			}
		}
		if(empty($dirName)) $dirName = ''; // 'cause the substr() above may return FALSE.
		
		$check = '/'.trim($this->dir,'/').'/'.trim($dirName, '/');
		if($this->is_dir($check)) return true;

		$alldirs = explode('/', $dirName);
		$previousDir = '/'.trim($this->dir);
		foreach($alldirs as $curdir)
		{
			$check = $previousDir.'/'.$curdir;
			if(!$this->is_dir($check))
			{
				// Proactively try to delete a file by the same name
				@ftp_delete($this->handle, $check);

				if(@ftp_mkdir($this->handle, $check) === false)
				{
					// If we couldn't create the directory, attempt to fix the permissions in the PHP level and retry!
					$this->fixPermissions($removePath.$check);
					if(@ftp_mkdir($this->handle, $check) === false)
					{
						// Can we fall back to pure PHP mode, sire?
						if(!@mkdir($check))
						{
							$this->setError(AKText::sprintf('FTP_CANT_CREATE_DIR',$dir));
							return false;
						}
						else
						{
							// Since the directory was built by PHP, change its permissions
							@chmod($check, "0777");
							return true;
						}
					}
				}
				@ftp_chmod($this->handle, $perms, $check);
			}
			$previousDir = $check;
		}

		return true;
	}

	public function close()
	{
		@ftp_close($this->handle);
	}

	/*
	 * Tries to fix directory/file permissions in the PHP level, so that
	 * the FTP operation doesn't fail.
	 * @param $path string The full path to a directory or file
	 */
	private function fixPermissions( $path )
	{
		// Turn off error reportingg
		$oldErrorReporting = @error_reporting(E_NONE);

		// Get UNIX style paths
		$relPath = str_replace('\\','/',$path);
		$basePath = rtrim(str_replace('\\','/',dirname(__FILE__)),'/');
		$basePath = rtrim($basePath,'/');
		if(!empty($basePath)) $basePath .= '/';
		// Remove the leading relative root
		if( substr($relPath,0,strlen($basePath)) == $basePath )
			$relPath = substr($relPath,strlen($basePath));
		$dirArray = explode('/', $relPath);
		$pathBuilt = rtrim($basePath,'/');
		foreach( $dirArray as $dir )
		{
			if(empty($dir)) continue;
			$oldPath = $pathBuilt;
			$pathBuilt .= '/'.$dir;
			if(is_dir($oldPath.$dir))
			{
				@chmod($oldPath.$dir, 0777);
			}
			else
			{
				if(@chmod($oldPath.$dir, 0777) === false)
				{
					@unlink($oldPath.$dir);
				}
			}
		}

		// Restore error reporting
		@error_reporting($oldErrorReporting);
	}

	public function chmod( $file, $perms )
	{
		return @ftp_chmod($this->handle, $perms, $path);
	}

	private function is_dir( $dir )
	{
		return @ftp_chdir( $this->handle, $dir );
	}

	public function unlink( $file )
	{
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$left = substr($file, 0, strlen($removePath));
			if($left == $removePath)
			{
				$file = substr($file, strlen($removePath));
			}
		}

		$check = '/'.trim($this->dir,'/').'/'.trim($file, '/');

		return @ftp_delete( $this->handle, $check );
	}

	public function rmdir( $directory )
	{
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$left = substr($directory, 0, strlen($removePath));
			if($left == $removePath)
			{
				$directory = substr($directory, strlen($removePath));
			}
		}

		$check = '/'.trim($this->dir,'/').'/'.trim($directory, '/');

		return @ftp_rmdir( $this->handle, $check );
	}

	public function rename( $from, $to )
	{
		$originalFrom = $from;
		$originalTo = $to;

		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$left = substr($from, 0, strlen($removePath));
			if($left == $removePath)
			{
				$from = substr($from, strlen($removePath));
			}
		}
		$from = '/'.trim($this->dir,'/').'/'.trim($from, '/');

		if(!empty($removePath))
		{
			$left = substr($to, 0, strlen($removePath));
			if($left == $removePath)
			{
				$to = substr($to, strlen($removePath));
			}
		}
		$to = '/'.trim($this->dir,'/').'/'.trim($to, '/');

		$result = @ftp_rename( $this->handle, $from, $to );
		if($result !== true)
		{
			return @rename($from, $to);
		}
		else
		{
			return true;
		}
	}

}

/**
 * JPA archive extraction class
 */
class AKUnarchiverJPA extends AKAbstractUnarchiver
{
	private $archiveHeaderData = array();

	protected function readArchiveHeader()
	{
		// Initialize header data array
		$this->archiveHeaderData = new stdClass();

		// Open the first part
		$this->nextFile();

		// Fail for unreadable files
		if( $this->fp === false ) return false;

		// Read the signature
		$sig = fread( $this->fp, 3 );

		if ($sig != 'JPA')
		{
			// Not a JPA file
			$this->setError( AKText::_('ERR_NOT_A_JPA_FILE') );
			return false;
		}

		// Read and parse header length
		$header_length_array = unpack( 'v', fread( $this->fp, 2 ) );
		$header_length = $header_length_array[1];

		// Read and parse the known portion of header data (14 bytes)
		$bin_data = fread($this->fp, 14);
		$header_data = unpack('Cmajor/Cminor/Vcount/Vuncsize/Vcsize', $bin_data);

		// Load any remaining header data (forward compatibility)
		$rest_length = $header_length - 19;
		if( $rest_length > 0 )
			$junk = fread($this->fp, $rest_length);
		else
			$junk = '';

		// Temporary array with all the data we read
		$temp = array(
			'signature' => 			$sig,
			'length' => 			$header_length,
			'major' => 				$header_data['major'],
			'minor' => 				$header_data['minor'],
			'filecount' => 			$header_data['count'],
			'uncompressedsize' => 	$header_data['uncsize'],
			'compressedsize' => 	$header_data['csize'],
			'unknowndata' => 		$junk
		);
		// Array-to-object conversion
		foreach($temp as $key => $value)
		{
			$this->archiveHeaderData->{$key} = $value;
		}

		$this->currentPartOffset = @ftell($this->fp);

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to read the file header
	 * @return bool True if reading the file was successful, false if an error occured or we reached end of archive
	 */
	protected function readFileHeader()
	{
		// If the current part is over, proceed to the next part please
		if( $this->isEOF(true) ) {
			$this->nextFile();
		}

		// Get and decode Entity Description Block
		$signature = fread($this->fp, 3);

		$this->fileHeader = new stdClass();
		$this->fileHeader->timestamp = 0;

		// Check signature
		if( $signature != 'JPF' )
		{
			if($this->isEOF(true))
			{
				// This file is finished; make sure it's the last one
				$this->nextFile();
				if(!$this->isEOF(false))
				{
					$this->setError(AKText::sprintf('INVALID_FILE_HEADER', $this->currentPartNumber, $this->currentPartOffset));
					return false;
				}
				// We're just finished
				return false;
			}
			else
			{
				// This is not a file block! The archive is corrupt.
				$this->setError(AKText::sprintf('INVALID_FILE_HEADER', $this->currentPartNumber, $this->currentPartOffset));
				return false;
			}
		}
		// This a JPA Entity Block. Process the header.

		$isBannedFile = false;

		// Read length of EDB and of the Entity Path Data
		$length_array = unpack('vblocksize/vpathsize', fread($this->fp, 4));
		// Read the path data
		if($length_array['pathsize'] > 0) {
			$file = fread( $this->fp, $length_array['pathsize'] );
		} else {
			$file = '';
		}

		// Handle file renaming
		$isRenamed = false;
		if(is_array($this->renameFiles) && (count($this->renameFiles) > 0) )
		{
			if(array_key_exists($file, $this->renameFiles))
			{
				$file = $this->renameFiles[$file];
				$isRenamed = true;
			}
		}
		
		// Handle directory renaming
		$isDirRenamed = false;
		if(is_array($this->renameDirs) && (count($this->renameDirs) > 0)) {
			if(array_key_exists(dirname($file), $this->renameDirs)) {
				$file = rtrim($this->renameDirs[dirname($file)],'/').'/'.basename($file);
				$isRenamed = true;
				$isDirRenamed = true;
			}
		}

		// Read and parse the known data portion
		$bin_data = fread( $this->fp, 14 );
		$header_data = unpack('Ctype/Ccompression/Vcompsize/Vuncompsize/Vperms', $bin_data);
		// Read any unknown data
		$restBytes = $length_array['blocksize'] - (21 + $length_array['pathsize']);
		if( $restBytes > 0 )
		{
			// Start reading the extra fields
			while($restBytes >= 4)
			{
				$extra_header_data = fread($this->fp, 4);
				$extra_header = unpack('vsignature/vlength', $extra_header_data);
				$restBytes -= 4;
				$extra_header['length'] -= 4;
				switch($extra_header['signature'])
				{
					case 256:
						// File modified timestamp
						if($extra_header['length'] > 0)
						{
							$bindata = fread($this->fp, $extra_header['length']);
							$restBytes -= $extra_header['length'];
							$timestamps = unpack('Vmodified', substr($bindata,0,4));
							$filectime = $timestamps['modified'];
							$this->fileHeader->timestamp = $filectime;
						}
						break;

					default:
						// Unknown field
						if($extra_header['length']>0) {
							$junk = fread($this->fp, $extra_header['length']);
							$restBytes -= $extra_header['length'];
						}
						break;
				}
			}
			if($restBytes > 0) $junk = fread($this->fp, $restBytes);
		}

		$compressionType = $header_data['compression'];

		// Populate the return array
		$this->fileHeader->file = $file;
		$this->fileHeader->compressed = $header_data['compsize'];
		$this->fileHeader->uncompressed = $header_data['uncompsize'];
		switch($header_data['type'])
		{
			case 0:
				$this->fileHeader->type = 'dir';
				break;

			case 1:
				$this->fileHeader->type = 'file';
				break;

			case 2:
				$this->fileHeader->type = 'link';
				break;
		}
		switch( $compressionType )
		{
			case 0:
				$this->fileHeader->compression = 'none';
				break;
			case 1:
				$this->fileHeader->compression = 'gzip';
				break;
			case 2:
				$this->fileHeader->compression = 'bzip2';
				break;
		}
		$this->fileHeader->permissions = $header_data['perms'];

		// Find hard-coded banned files
		if( (basename($this->fileHeader->file) == ".") || (basename($this->fileHeader->file) == "..") )
		{
			$isBannedFile = true;
		}

		// Also try to find banned files passed in class configuration
		if((count($this->skipFiles) > 0) && (!$isRenamed) )
		{
			if(in_array($this->fileHeader->file, $this->skipFiles))
			{
				$isBannedFile = true;
			}
		}

		// If we have a banned file, let's skip it
		if($isBannedFile)
		{
			// Advance the file pointer, skipping exactly the size of the compressed data
			$seekleft = $this->fileHeader->compressed;
			while($seekleft > 0)
			{
				// Ensure that we can seek past archive part boundaries
				$curSize = @filesize($this->archiveList[$this->currentPartNumber]);
				$curPos = @ftell($this->fp);
				$canSeek = $curSize - $curPos;
				if($canSeek > $seekleft) $canSeek = $seekleft;
				@fseek( $this->fp, $canSeek, SEEK_CUR );
				$seekleft -= $canSeek;
				if($seekleft) $this->nextFile();
			}

			$this->currentPartOffset = @ftell($this->fp);
			$this->runState = AK_STATE_DONE;
			return true;
		}

		// Last chance to prepend a path to the filename
		if(!empty($this->addPath) && !$isDirRenamed)
		{
			$this->fileHeader->file = $this->addPath.$this->fileHeader->file;
		}

		// Get the translated path name
		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);
		if($this->fileHeader->type == 'file')
		{
			// Regular file; ask the postproc engine to process its filename
			if($restorePerms)
			{
				$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file, $this->fileHeader->permissions );
			}
			else
			{
				$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file );
			}
		}
		elseif($this->fileHeader->type == 'dir')
		{
			$dir = $this->fileHeader->file;

			// Directory; just create it
			if($restorePerms)
			{
				$this->postProcEngine->createDirRecursive( $this->fileHeader->file, $this->fileHeader->permissions );
			}
			else
			{
				$this->postProcEngine->createDirRecursive( $this->fileHeader->file, 0755 );
			}
			$this->postProcEngine->processFilename(null);
		}
		else
		{
			// Symlink; do not post-process
			$this->postProcEngine->processFilename(null);
		}

		$this->createDirectory();

		// Header is read
		$this->runState = AK_STATE_HEADER;

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to process file data. It must set $runState to AK_STATE_DATAREAD when
	 * it's finished processing the file data.
	 * @return bool True if processing the file data was successful, false if an error occured
	 */
	protected function processFileData()
	{
		switch( $this->fileHeader->type )
		{
			case 'dir':
				return $this->processTypeDir();
				break;

			case 'link':
				return $this->processTypeLink();
				break;

			case 'file':
				switch($this->fileHeader->compression)
				{
					case 'none':
						return $this->processTypeFileUncompressed();
						break;

					case 'gzip':
					case 'bzip2':
						return $this->processTypeFileCompressedSimple();
						break;

				}
				break;
		}
	}

	private function processTypeFileUncompressed()
	{
		// Uncompressed files are being processed in small chunks, to avoid timeouts
		if( ($this->dataReadLength == 0) && !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Before processing file data, ensure permissions are adequate
			$this->setCorrectPermissions( $this->fileHeader->file );
		}

		// Open the output file
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false);
			if ($this->dataReadLength == 0) {
				$outfp = @fopen( $this->fileHeader->realFile, 'wb' );
			} else {
				$outfp = @fopen( $this->fileHeader->realFile, 'ab' );
			}

			// Can we write to the file?
			if( ($outfp === false) && (!$ignore) ) {
				// An error occured
				$this->setError( AKText::sprintf('COULDNT_WRITE_FILE', $this->fileHeader->realFile) );
				return false;
			}
		}

		// Does the file have any data, at all?
		if( $this->fileHeader->compressed == 0 )
		{
			// No file data!
			if( !AKFactory::get('kickstart.setup.dryrun','0') && is_resource($outfp) ) @fclose($outfp);
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}

		// Reference to the global timer
		$timer =& AKFactory::getTimer();

		$toReadBytes = 0;
		$leftBytes = $this->fileHeader->compressed - $this->dataReadLength;

		// Loop while there's data to read and enough time to do it
		while( ($leftBytes > 0) && ($timer->getTimeLeft() > 0) )
		{
			$toReadBytes = ($leftBytes > $this->chunkSize) ? $this->chunkSize : $leftBytes;
			$data = $this->fread( $this->fp, $toReadBytes );
			$reallyReadBytes = akstringlen($data);
			$leftBytes -= $reallyReadBytes;
			$this->dataReadLength += $reallyReadBytes;
			if($reallyReadBytes < $toReadBytes)
			{
				// We read less than requested! Why? Did we hit local EOF?
				if( $this->isEOF(true) && !$this->isEOF(false) )
				{
					// Yeap. Let's go to the next file
					$this->nextFile();
				}
				else
				{
					// Nope. The archive is corrupt
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}
			if( !AKFactory::get('kickstart.setup.dryrun','0') )
				if(is_resource($outfp)) @fwrite( $outfp, $data );
		}

		// Close the file pointer
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
			if(is_resource($outfp)) @fclose($outfp);

		// Was this a pre-timeout bail out?
		if( $leftBytes > 0 )
		{
			$this->runState = AK_STATE_DATA;
		}
		else
		{
			// Oh! We just finished!
			$this->runState = AK_STATE_DATAREAD;
			$this->dataReadLength = 0;
		}

		return true;
	}

	private function processTypeFileCompressedSimple()
	{
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Before processing file data, ensure permissions are adequate
			$this->setCorrectPermissions( $this->fileHeader->file );

			// Open the output file
			$outfp = @fopen( $this->fileHeader->realFile, 'wb' );

			// Can we write to the file?
			$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false);
			if( ($outfp === false) && (!$ignore) ) {
				// An error occured
				$this->setError( AKText::sprintf('COULDNT_WRITE_FILE', $this->fileHeader->realFile) );
				return false;
			}
		}

		// Does the file have any data, at all?
		if( $this->fileHeader->compressed == 0 )
		{
			// No file data!
			if( !AKFactory::get('kickstart.setup.dryrun','0') )
				if(is_resource($outfp)) @fclose($outfp);
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}

		// Simple compressed files are processed as a whole; we can't do chunk processing
		$zipData = $this->fread( $this->fp, $this->fileHeader->compressed );
		while( akstringlen($zipData) < $this->fileHeader->compressed )
		{
			// End of local file before reading all data, but have more archive parts?
			if($this->isEOF(true) && !$this->isEOF(false))
			{
				// Yeap. Read from the next file
				$this->nextFile();
				$bytes_left = $this->fileHeader->compressed - akstringlen($zipData);
				$zipData .= $this->fread( $this->fp, $bytes_left );
			}
			else
			{
				$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
				return false;
			}
		}

		if($this->fileHeader->compression == 'gzip')
		{
			$unzipData = gzinflate( $zipData );
		}
		elseif($this->fileHeader->compression == 'bzip2')
		{
			$unzipData = bzdecompress( $zipData );
		}
		unset($zipData);

		// Write to the file.
		if( !AKFactory::get('kickstart.setup.dryrun','0') && is_resource($outfp) )
		{
			@fwrite( $outfp, $unzipData, $this->fileHeader->uncompressed );
			@fclose( $outfp );
		}
		unset($unzipData);

		$this->runState = AK_STATE_DATAREAD;
		return true;
	}

	/**
	 * Process the file data of a link entry
	 * @return bool
	 */
	private function processTypeLink()
	{
		$readBytes = 0;
		$toReadBytes = 0;
		$leftBytes = $this->fileHeader->compressed;
		$data = '';

		while( $leftBytes > 0)
		{
			$toReadBytes = ($leftBytes > $this->chunkSize) ? $this->chunkSize : $leftBytes;
			$mydata = $this->fread( $this->fp, $toReadBytes );
			$reallyReadBytes = akstringlen($mydata);
			$data .= $mydata;
			$leftBytes -= $reallyReadBytes;
			if($reallyReadBytes < $toReadBytes)
			{
				// We read less than requested! Why? Did we hit local EOF?
				if( $this->isEOF(true) && !$this->isEOF(false) )
				{
					// Yeap. Let's go to the next file
					$this->nextFile();
				}
				else
				{
					// Nope. The archive is corrupt
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}
		}

		// Try to remove an existing file or directory by the same name
		if(file_exists($this->fileHeader->realFile)) { @unlink($this->fileHeader->realFile); @rmdir($this->fileHeader->realFile); }
		// Remove any trailing slash
		if(substr($this->fileHeader->realFile, -1) == '/') $this->fileHeader->realFile = substr($this->fileHeader->realFile, 0, -1);
		// Create the symlink - only possible within PHP context. There's no support built in the FTP protocol, so no postproc use is possible here :(
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
			@symlink($data, $this->fileHeader->realFile);

		$this->runState = AK_STATE_DATAREAD;

		return true; // No matter if the link was created!
	}

	/**
	 * Process the file data of a directory entry
	 * @return bool
	 */
	private function processTypeDir()
	{
		// Directory entries in the JPA do not have file data, therefore we're done processing the entry
		$this->runState = AK_STATE_DATAREAD;
		return true;
	}

	/**
	 * Creates the directory this file points to
	 */
	protected function createDirectory()
	{
		if( AKFactory::get('kickstart.setup.dryrun','0') ) return true;

		// Do we need to create a directory?
		if(empty($this->fileHeader->realFile)) $this->fileHeader->realFile = $this->fileHeader->file;
		$lastSlash = strrpos($this->fileHeader->realFile, '/');
		$dirName = substr( $this->fileHeader->realFile, 0, $lastSlash);
		$perms = $this->flagRestorePermissions ? $retArray['permissions'] : 0755;
		$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false);
		if( ($this->postProcEngine->createDirRecursive($dirName, $perms) == false) && (!$ignore) ) {
			$this->setError( AKText::sprintf('COULDNT_CREATE_DIR', $dirName) );
			return false;
		}
		else
		{
			return true;
		}
	}
}

/**
 * ZIP archive extraction class
 *
 * Since the file data portion of ZIP and JPA are similarly structured (it's empty for dirs,
 * linked node name for symlinks, dumped binary data for no compressions and dumped gzipped
 * binary data for gzip compression) we just have to subclass AKUnarchiverJPA and change the
 * header reading bits. Reusable code ;)
 */
class AKUnarchiverZIP extends AKUnarchiverJPA
{
	var $expectDataDescriptor = false;

	protected function readArchiveHeader()
	{
		// Initialize header data array
		$this->archiveHeaderData = new stdClass();

		// Open the first part
		$this->nextFile();

		// Fail for unreadable files
		if( $this->fp === false ) return false;

		// Read a possible multipart signature
		$sigBinary = fread( $this->fp, 4 );
		$headerData = unpack('Vsig', $sigBinary);

		// Roll back if it's not a multipart archive
		if( $headerData['sig'] == 0x04034b50 ) fseek($this->fp, -4, SEEK_CUR);

		$multiPartSigs = array(
			0x08074b50,		// Multi-part ZIP
			0x30304b50,		// Multi-part ZIP (alternate)
			0x04034b50		// Single file
		);
		if( !in_array($headerData['sig'], $multiPartSigs) )
		{
			$this->setError(AKText::_('ERR_CORRUPT_ARCHIVE'));
			return false;
		}

		$this->currentPartOffset = @ftell($this->fp);

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to read the file header
	 * @return bool True if reading the file was successful, false if an error occured or we reached end of archive
	 */
	protected function readFileHeader()
	{
		// If the current part is over, proceed to the next part please
		if( $this->isEOF(true) ) {
			$this->nextFile();
		}

		if($this->expectDataDescriptor)
		{
			// The last file had bit 3 of the general purpose bit flag set. This means that we have a
			// 12 byte data descriptor we need to skip. To make things worse, there might also be a 4
			// byte optional data descriptor header (0x08074b50).
			$junk = @fread($this->fp, 4);
			$junk = unpack('Vsig', $junk);
			if($junk['sig'] == 0x08074b50) {
				// Yes, there was a signature
				$junk = @fread($this->fp, 12);
				if(defined('KSDEBUG')) {
					debugMsg('Data descriptor (w/ header) skipped at '.(ftell($this->fp)-12));
				}
			} else {
				// No, there was no signature, just read another 8 bytes
				$junk = @fread($this->fp, 8);
				if(defined('KSDEBUG')) {
					debugMsg('Data descriptor (w/out header) skipped at '.(ftell($this->fp)-8));
				}
			}

			// And check for EOF, too
			if( $this->isEOF(true) ) {
				if(defined('KSDEBUG')) {
					debugMsg('EOF before reading header');
				}
				
				$this->nextFile();
			}
		}

		// Get and decode Local File Header
		$headerBinary = fread($this->fp, 30);
		$headerData = unpack('Vsig/C2ver/vbitflag/vcompmethod/vlastmodtime/vlastmoddate/Vcrc/Vcompsize/Vuncomp/vfnamelen/veflen', $headerBinary);

		// Check signature
		if(!( $headerData['sig'] == 0x04034b50 ))
		{
			if(defined('KSDEBUG')) {
				debugMsg('Not a file signature at '.(ftell($this->fp)-4));
			}
			
			// The signature is not the one used for files. Is this a central directory record (i.e. we're done)?
			if($headerData['sig'] == 0x02014b50)
			{
				if(defined('KSDEBUG')) {
					debugMsg('EOCD signature at '.(ftell($this->fp)-4));
				}
				// End of ZIP file detected. We'll just skip to the end of file...
				while( $this->nextFile() ) {};
				@fseek($this->fp, 0, SEEK_END); // Go to EOF
				return false;
			}
			else
			{
				if(defined('KSDEBUG')) {
					debugMsg( 'Invalid signature ' . dechex($headerData['sig']) . ' at '.ftell($this->fp) );
				}
				$this->setError(AKText::_('ERR_CORRUPT_ARCHIVE'));
				return false;
			}
		}

		// If bit 3 of the bitflag is set, expectDataDescriptor is true
		$this->expectDataDescriptor = ($headerData['bitflag'] & 4) == 4;

		$this->fileHeader = new stdClass();
		$this->fileHeader->timestamp = 0;

		// Read the last modified data and time
		$lastmodtime = $headerData['lastmodtime'];
		$lastmoddate = $headerData['lastmoddate'];
		
		if($lastmoddate && $lastmodtime)
		{
			// ----- Extract time
			$v_hour = ($lastmodtime & 0xF800) >> 11;
			$v_minute = ($lastmodtime & 0x07E0) >> 5;
			$v_seconde = ($lastmodtime & 0x001F)*2;
			
			// ----- Extract date
			$v_year = (($lastmoddate & 0xFE00) >> 9) + 1980;
			$v_month = ($lastmoddate & 0x01E0) >> 5;
			$v_day = $lastmoddate & 0x001F;
			
			// ----- Get UNIX date format
			$this->fileHeader->timestamp = @mktime($v_hour, $v_minute, $v_seconde, $v_month, $v_day, $v_year);
		}
		
		$isBannedFile = false;

		$this->fileHeader->compressed	= $headerData['compsize'];
		$this->fileHeader->uncompressed	= $headerData['uncomp'];
		$nameFieldLength				= $headerData['fnamelen'];
		$extraFieldLength				= $headerData['eflen'];

		// Read filename field
		$this->fileHeader->file			= fread( $this->fp, $nameFieldLength );

		// Handle file renaming
		$isRenamed = false;
		if(is_array($this->renameFiles) && (count($this->renameFiles) > 0) )
		{
			if(array_key_exists($this->fileHeader->file, $this->renameFiles))
			{
				$this->fileHeader->file = $this->renameFiles[$this->fileHeader->file];
				$isRenamed = true;
			}
		}
		
		// Handle directory renaming
		$isDirRenamed = false;
		if(is_array($this->renameDirs) && (count($this->renameDirs) > 0)) {
			if(array_key_exists(dirname($file), $this->renameDirs)) {
				$file = rtrim($this->renameDirs[dirname($file)],'/').'/'.basename($file);
				$isRenamed = true;
				$isDirRenamed = true;
			}
		}

		// Read extra field if present
		if($extraFieldLength > 0) {
			$extrafield = fread( $this->fp, $extraFieldLength );
		}
		
		if(defined('KSDEBUG')) {
			debugMsg( '*'.ftell($this->fp).' IS START OF '.$this->fileHeader->file. ' ('.$this->fileHeader->compressed.' bytes)' );
		}
		

		// Decide filetype -- Check for directories
		$this->fileHeader->type = 'file';
		if( strrpos($this->fileHeader->file, '/') == strlen($this->fileHeader->file) - 1 ) $this->fileHeader->type = 'dir';
		// Decide filetype -- Check for symbolic links
		if( ($headerData['ver1'] == 10) && ($headerData['ver2'] == 3) )$this->fileHeader->type = 'link';

		switch( $headerData['compmethod'] )
		{
			case 0:
				$this->fileHeader->compression = 'none';
				break;
			case 8:
				$this->fileHeader->compression = 'gzip';
				break;
		}

		// Find hard-coded banned files
		if( (basename($this->fileHeader->file) == ".") || (basename($this->fileHeader->file) == "..") )
		{
			$isBannedFile = true;
		}

		// Also try to find banned files passed in class configuration
		if((count($this->skipFiles) > 0) && (!$isRenamed))
		{
			if(in_array($this->fileHeader->file, $this->skipFiles))
			{
				$isBannedFile = true;
			}
		}

		// If we have a banned file, let's skip it
		if($isBannedFile)
		{
			// Advance the file pointer, skipping exactly the size of the compressed data
			$seekleft = $this->fileHeader->compressed;
			while($seekleft > 0)
			{
				// Ensure that we can seek past archive part boundaries
				$curSize = @filesize($this->archiveList[$this->currentPartNumber]);
				$curPos = @ftell($this->fp);
				$canSeek = $curSize - $curPos;
				if($canSeek > $seekleft) $canSeek = $seekleft;
				@fseek( $this->fp, $canSeek, SEEK_CUR );
				$seekleft -= $canSeek;
				if($seekleft) $this->nextFile();
			}

			$this->currentPartOffset = @ftell($this->fp);
			$this->runState = AK_STATE_DONE;
			return true;
		}

		// Last chance to prepend a path to the filename
		if(!empty($this->addPath) && !$isDirRenamed)
		{
			$this->fileHeader->file = $this->addPath.$this->fileHeader->file;
		}

		// Get the translated path name
		if($this->fileHeader->type == 'file')
		{
			$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file );
		}
		elseif($this->fileHeader->type == 'dir')
		{
			$this->fileHeader->timestamp = 0;

			$dir = $this->fileHeader->file;

			$this->postProcEngine->createDirRecursive( $this->fileHeader->file, 0755 );
			$this->postProcEngine->processFilename(null);
		}
		else
		{
			// Symlink; do not post-process
			$this->fileHeader->timestamp = 0;
			$this->postProcEngine->processFilename(null);
		}

		$this->createDirectory();

		// Header is read
		$this->runState = AK_STATE_HEADER;

		return true;
	}

}

/**
 * Timer class
 */
class AKCoreTimer extends AKAbstractObject
{
	/** @var int Maximum execution time allowance per step */
	private $max_exec_time = null;

	/** @var int Timestamp of execution start */
	private $start_time = null;

	/**
	 * Public constructor, creates the timer object and calculates the execution time limits
	 * @return AECoreTimer
	 */
	public function __construct()
	{
		parent::__construct();

		// Initialize start time
		$this->start_time = $this->microtime_float();

		// Get configured max time per step and bias
		$config_max_exec_time	= AKFactory::get('kickstart.tuning.max_exec_time', 14);
		$bias					= AKFactory::get('kickstart.tuning.run_time_bias', 75)/100;

		// Get PHP's maximum execution time (our upper limit)
		if(@function_exists('ini_get'))
		{
			$php_max_exec_time = @ini_get("maximum_execution_time");
			if ( (!is_numeric($php_max_exec_time)) || ($php_max_exec_time == 0) ) {
				// If we have no time limit, set a hard limit of about 10 seconds
				// (safe for Apache and IIS timeouts, verbose enough for users)
				$php_max_exec_time = 14;
			}
		}
		else
		{
			// If ini_get is not available, use a rough default
			$php_max_exec_time = 14;
		}

		// Apply an arbitrary correction to counter CMS load time
		$php_max_exec_time--;

		// Apply bias
		$php_max_exec_time = $php_max_exec_time * $bias;
		$config_max_exec_time = $config_max_exec_time * $bias;

		// Use the most appropriate time limit value
		if( $config_max_exec_time > $php_max_exec_time )
		{
			$this->max_exec_time = $php_max_exec_time;
		}
		else
		{
			$this->max_exec_time = $config_max_exec_time;
		}
	}

	/**
	 * Wake-up function to reset internal timer when we get unserialized
	 */
	public function __wakeup()
	{
		// Re-initialize start time on wake-up
		$this->start_time = $this->microtime_float();
	}

	/**
	 * Gets the number of seconds left, before we hit the "must break" threshold
	 * @return float
	 */
	public function getTimeLeft()
	{
		return $this->max_exec_time - $this->getRunningTime();
	}

	/**
	 * Gets the time elapsed since object creation/unserialization, effectively how
	 * long Akeeba Engine has been processing data
	 * @return float
	 */
	public function getRunningTime()
	{
		return $this->microtime_float() - $this->start_time;
	}

	/**
	 * Returns the current timestampt in decimal seconds
	 */
	private function microtime_float()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

	/**
	 * Enforce the minimum execution time
	 */
	public function enforce_min_exec_time()
	{
		// Try to get a sane value for PHP's maximum_execution_time INI parameter
		if(@function_exists('ini_get'))
		{
			$php_max_exec = @ini_get("maximum_execution_time");
		}
		else
		{
			$php_max_exec = 10;
		}
		if ( ($php_max_exec == "") || ($php_max_exec == 0) ) {
			$php_max_exec = 10;
		}
		// Decrease $php_max_exec time by 500 msec we need (approx.) to tear down
		// the application, as well as another 500msec added for rounding
		// error purposes. Also make sure this is never gonna be less than 0.
		$php_max_exec = max($php_max_exec * 1000 - 1000, 0);

		// Get the "minimum execution time per step" Akeeba Backup configuration variable
		$minexectime = AKFactory::get('kickstart.tuning.min_exec_time',0);
		if(!is_numeric($minexectime)) $minexectime = 0;

		// Make sure we are not over PHP's time limit!
		if($minexectime > $php_max_exec) $minexectime = $php_max_exec;

		// Get current running time
		$elapsed_time = $this->getRunningTime() * 1000;

			// Only run a sleep delay if we haven't reached the minexectime execution time
		if( ($minexectime > $elapsed_time) && ($elapsed_time > 0) )
		{
			$sleep_msec = $minexectime - $elapsed_time;
			if(function_exists('usleep'))
			{
				usleep(1000 * $sleep_msec);
			}
			elseif(function_exists('time_nanosleep'))
			{
				$sleep_sec = floor($sleep_msec / 1000);
				$sleep_nsec = 1000000 * ($sleep_msec - ($sleep_sec * 1000));
				time_nanosleep($sleep_sec, $sleep_nsec);
			}
			elseif(function_exists('time_sleep_until'))
			{
				$until_timestamp = time() + $sleep_msec / 1000;
				time_sleep_until($until_timestamp);
			}
			elseif(function_exists('sleep'))
			{
				$sleep_sec = ceil($sleep_msec/1000);
				sleep( $sleep_sec );
			}
		}
		elseif( $elapsed_time > 0 )
		{
			// No sleep required, even if user configured us to be able to do so.
		}
	}

	/**
	 * Reset the timer. It should only be used in CLI mode!
	 */
	public function resetTime()
	{
		$this->start_time = $this->microtime_float();
	}
}

/**
 * JPS archive extraction class
 */
class AKUnarchiverJPS extends AKUnarchiverJPA
{
	private $archiveHeaderData = array();

	private $password = '';

	public function __construct()
	{
		parent::__construct();

		$this->password = AKFactory::get('kickstart.jps.password','');
	}

	protected function readArchiveHeader()
	{
		// Initialize header data array
		$this->archiveHeaderData = new stdClass();

		// Open the first part
		$this->nextFile();

		// Fail for unreadable files
		if( $this->fp === false ) return false;

		// Read the signature
		$sig = fread( $this->fp, 3 );

		if ($sig != 'JPS')
		{
			// Not a JPA file
			$this->setError( AKText::_('ERR_NOT_A_JPS_FILE') );
			return false;
		}

		// Read and parse the known portion of header data (5 bytes)
		$bin_data = fread($this->fp, 5);
		$header_data = unpack('Cmajor/Cminor/cspanned/vextra', $bin_data);

		// Load any remaining header data (forward compatibility)
		$rest_length = $header_data['extra'];
		if( $rest_length > 0 )
			$junk = fread($this->fp, $rest_length);
		else
			$junk = '';

		// Temporary array with all the data we read
		$temp = array(
			'signature' => 			$sig,
			'major' => 				$header_data['major'],
			'minor' => 				$header_data['minor'],
			'spanned' => 			$header_data['spanned']
		);
		// Array-to-object conversion
		foreach($temp as $key => $value)
		{
			$this->archiveHeaderData->{$key} = $value;
		}

		$this->currentPartOffset = @ftell($this->fp);

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to read the file header
	 * @return bool True if reading the file was successful, false if an error occured or we reached end of archive
	 */
	protected function readFileHeader()
	{
		// If the current part is over, proceed to the next part please
		if( $this->isEOF(true) ) {
			$this->nextFile();
		}

		// Get and decode Entity Description Block
		$signature = fread($this->fp, 3);

		// Check for end-of-archive siganture
		if($signature == 'JPE')
		{
			$this->setState('postrun');
			return true;
		}

		$this->fileHeader = new stdClass();
		$this->fileHeader->timestamp = 0;

		// Check signature
		if( $signature != 'JPF' )
		{
			if($this->isEOF(true))
			{
				// This file is finished; make sure it's the last one
				$this->nextFile();
				if(!$this->isEOF(false))
				{
					$this->setError(AKText::sprintf('INVALID_FILE_HEADER', $this->currentPartNumber, $this->currentPartOffset));
					return false;
				}
				// We're just finished
				return false;
			}
			else
			{
				fseek($this->fp, -6, SEEK_CUR);
				$signature = fread($this->fp, 3);
				if($signature == 'JPE')
				{
					return false;
				}

				$this->setError(AKText::sprintf('INVALID_FILE_HEADER', $this->currentPartNumber, $this->currentPartOffset));
				return false;
			}
		}
		// This a JPA Entity Block. Process the header.

		$isBannedFile = false;

		// Read and decrypt the header
		$edbhData = fread($this->fp, 4);
		$edbh = unpack('vencsize/vdecsize', $edbhData);
		$bin_data = fread($this->fp, $edbh['encsize']);

		// Decrypt and truncate
		$bin_data = AKEncryptionAES::AESDecryptCBC($bin_data, $this->password, 128);
		$bin_data = substr($bin_data,0,$edbh['decsize']);

		// Read length of EDB and of the Entity Path Data
		$length_array = unpack('vpathsize', substr($bin_data,0,2) );
		// Read the path data
		$file = substr($bin_data,2,$length_array['pathsize']);

		// Handle file renaming
		$isRenamed = false;
		if(is_array($this->renameFiles) && (count($this->renameFiles) > 0) )
		{
			if(array_key_exists($file, $this->renameFiles))
			{
				$file = $this->renameFiles[$file];
				$isRenamed = true;
			}
		}
		
		// Handle directory renaming
		$isDirRenamed = false;
		if(is_array($this->renameDirs) && (count($this->renameDirs) > 0)) {
			if(array_key_exists(dirname($file), $this->renameDirs)) {
				$file = rtrim($this->renameDirs[dirname($file)],'/').'/'.basename($file);
				$isRenamed = true;
				$isDirRenamed = true;
			}
		}

		// Read and parse the known data portion
		$bin_data = substr($bin_data, 2 + $length_array['pathsize']);
		$header_data = unpack('Ctype/Ccompression/Vuncompsize/Vperms/Vfilectime', $bin_data);

		$this->fileHeader->timestamp = $header_data['filectime'];
		$compressionType = $header_data['compression'];

		// Populate the return array
		$this->fileHeader->file = $file;
		$this->fileHeader->uncompressed = $header_data['uncompsize'];
		switch($header_data['type'])
		{
			case 0:
				$this->fileHeader->type = 'dir';
				break;

			case 1:
				$this->fileHeader->type = 'file';
				break;

			case 2:
				$this->fileHeader->type = 'link';
				break;
		}
		switch( $compressionType )
		{
			case 0:
				$this->fileHeader->compression = 'none';
				break;
			case 1:
				$this->fileHeader->compression = 'gzip';
				break;
			case 2:
				$this->fileHeader->compression = 'bzip2';
				break;
		}
		$this->fileHeader->permissions = $header_data['perms'];

		// Find hard-coded banned files
		if( (basename($this->fileHeader->file) == ".") || (basename($this->fileHeader->file) == "..") )
		{
			$isBannedFile = true;
		}

		// Also try to find banned files passed in class configuration
		if((count($this->skipFiles) > 0) && (!$isRenamed) )
		{
			if(in_array($this->fileHeader->file, $this->skipFiles))
			{
				$isBannedFile = true;
			}
		}

		// If we have a banned file, let's skip it
		if($isBannedFile)
		{
			$done = false;
			while(!$done)
			{
				// Read the Data Chunk Block header
				$binMiniHead = fread($this->fp, 8);
				if( in_array( substr($binMiniHead,0,3), array('JPF','JPE') ) )
				{
					// Not a Data Chunk Block header, I am done skipping the file
					@fseek($this->fp,-8,SEEK_CUR); // Roll back the file pointer
					$done = true; // Mark as done
					continue; // Exit loop
				}
				else
				{
					// Skip forward by the amount of compressed data
					$miniHead = uncomp('Vencsize/Vdecsize');
					@fseek($this->fp, $miniHead['encsize'], SEEK_CUR);
				}
			}

			$this->currentPartOffset = @ftell($this->fp);
			$this->runState = AK_STATE_DONE;
			return true;
		}

		// Last chance to prepend a path to the filename
		if(!empty($this->addPath) && !$isDirRenamed)
		{
			$this->fileHeader->file = $this->addPath.$this->fileHeader->file;
		}

		// Get the translated path name
		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);
		if($this->fileHeader->type == 'file')
		{
			// Regular file; ask the postproc engine to process its filename
			if($restorePerms)
			{
				$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file, $this->fileHeader->permissions );
			}
			else
			{
				$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file );
			}
		}
		elseif($this->fileHeader->type == 'dir')
		{
			$dir = $this->fileHeader->file;
			$this->fileHeader->realFile = $dir;

			// Directory; just create it
			if($restorePerms)
			{
				$this->postProcEngine->createDirRecursive( $this->fileHeader->file, $this->fileHeader->permissions );
			}
			else
			{
				$this->postProcEngine->createDirRecursive( $this->fileHeader->file, 0755 );
			}
			$this->postProcEngine->processFilename(null);
		}
		else
		{
			// Symlink; do not post-process
			$this->postProcEngine->processFilename(null);
		}

		$this->createDirectory();

		// Header is read
		$this->runState = AK_STATE_HEADER;

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to process file data. It must set $runState to AK_STATE_DATAREAD when
	 * it's finished processing the file data.
	 * @return bool True if processing the file data was successful, false if an error occured
	 */
	protected function processFileData()
	{
		switch( $this->fileHeader->type )
		{
			case 'dir':
				return $this->processTypeDir();
				break;

			case 'link':
				return $this->processTypeLink();
				break;

			case 'file':
				switch($this->fileHeader->compression)
				{
					case 'none':
						return $this->processTypeFileUncompressed();
						break;

					case 'gzip':
					case 'bzip2':
						return $this->processTypeFileCompressedSimple();
						break;

				}
				break;
		}
	}

	private function processTypeFileUncompressed()
	{
		// Uncompressed files are being processed in small chunks, to avoid timeouts
		if( ($this->dataReadLength == 0) && !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Before processing file data, ensure permissions are adequate
			$this->setCorrectPermissions( $this->fileHeader->file );
		}

		// Open the output file
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false);
			if ($this->dataReadLength == 0) {
				$outfp = @fopen( $this->fileHeader->realFile, 'wb' );
			} else {
				$outfp = @fopen( $this->fileHeader->realFile, 'ab' );
			}

			// Can we write to the file?
			if( ($outfp === false) && (!$ignore) ) {
				// An error occured
				$this->setError( AKText::sprintf('COULDNT_WRITE_FILE', $this->fileHeader->realFile) );
				return false;
			}
		}

		// Does the file have any data, at all?
		if( $this->fileHeader->uncompressed == 0 )
		{
			// No file data!
			if( !AKFactory::get('kickstart.setup.dryrun','0') && is_resource($outfp) ) @fclose($outfp);
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}
		else
		{
			$this->setError('An uncompressed file was detected; this is not supported by this archive extraction utility');
			return false;
		}

		return true;
	}

	private function processTypeFileCompressedSimple()
	{
		$timer =& AKFactory::getTimer();

		// Files are being processed in small chunks, to avoid timeouts
		if( ($this->dataReadLength == 0) && !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Before processing file data, ensure permissions are adequate
			$this->setCorrectPermissions( $this->fileHeader->file );
		}

		// Open the output file
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Open the output file
			$outfp = @fopen( $this->fileHeader->realFile, 'wb' );

			// Can we write to the file?
			$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false);
			if( ($outfp === false) && (!$ignore) ) {
				// An error occured
				$this->setError( AKText::sprintf('COULDNT_WRITE_FILE', $this->fileHeader->realFile) );
				return false;
			}
		}

		// Does the file have any data, at all?
		if( $this->fileHeader->uncompressed == 0 )
		{
			// No file data!
			if( !AKFactory::get('kickstart.setup.dryrun','0') )
				if(is_resource($outfp)) @fclose($outfp);
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}

		$leftBytes = $this->fileHeader->uncompressed - $this->dataReadLength;

		// Loop while there's data to write and enough time to do it
		while( ($leftBytes > 0) && ($timer->getTimeLeft() > 0) )
		{
			// Read the mini header
			$binMiniHeader = fread($this->fp, 8);
			$reallyReadBytes = akstringlen($binMiniHeader);
			if($reallyReadBytes < 8)
			{
				// We read less than requested! Why? Did we hit local EOF?
				if( $this->isEOF(true) && !$this->isEOF(false) )
				{
					// Yeap. Let's go to the next file
					$this->nextFile();
					// Retry reading the header
					$binMiniHeader = fread($this->fp, 8);
					$reallyReadBytes = akstringlen($binMiniHeader);
					// Still not enough data? If so, the archive is corrupt or missing parts.
					if($reallyReadBytes < 8) {
						$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
						return false;
					}
				}
				else
				{
					// Nope. The archive is corrupt
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}

			// Read the encrypted data
			$miniHeader = unpack('Vencsize/Vdecsize', $binMiniHeader);
			$toReadBytes = $miniHeader['encsize'];
			$data = $this->fread( $this->fp, $toReadBytes );
			$reallyReadBytes = akstringlen($data);
			if($reallyReadBytes < $toReadBytes)
			{
				// We read less than requested! Why? Did we hit local EOF?
				if( $this->isEOF(true) && !$this->isEOF(false) )
				{
					// Yeap. Let's go to the next file
					$this->nextFile();
					// Read the rest of the data
					$toReadBytes -= $reallyReadBytes;
					$restData = $this->fread( $this->fp, $toReadBytes );
					$reallyReadBytes = akstringlen($restData);
					if($reallyReadBytes < $toReadBytes) {
						$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
						return false;
					}
					if(akstringlen($data) == 0) {
						$data = $restData;
					} else {
						$data .= $restData;
					}
				}
				else
				{
					// Nope. The archive is corrupt
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}

			// Decrypt the data
			$data = AKEncryptionAES::AESDecryptCBC($data, $this->password, 128);

			// Is the length of the decrypted data less than expected?
			$data_length = akstringlen($data);
			if($data_length < $miniHeader['decsize']) {
				$this->setError(AKText::_('ERR_INVALID_JPS_PASSWORD'));
				return false;
			}

			// Trim the data
			$data = substr($data,0,$miniHeader['decsize']);

			// Decompress
			$data = gzinflate($data);
			$unc_len = akstringlen($data);

			// Write the decrypted data
			if( !AKFactory::get('kickstart.setup.dryrun','0') )
				if(is_resource($outfp)) @fwrite( $outfp, $data, akstringlen($data) );

			// Update the read length
			$this->dataReadLength += $unc_len;
			$leftBytes = $this->fileHeader->uncompressed - $this->dataReadLength;
		}

		// Close the file pointer
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
			if(is_resource($outfp)) @fclose($outfp);

		// Was this a pre-timeout bail out?
		if( $leftBytes > 0 )
		{
			$this->runState = AK_STATE_DATA;
		}
		else
		{
			// Oh! We just finished!
			$this->runState = AK_STATE_DATAREAD;
			$this->dataReadLength = 0;
		}
	}

	/**
	 * Process the file data of a link entry
	 * @return bool
	 */
	private function processTypeLink()
	{

		// Does the file have any data, at all?
		if( $this->fileHeader->uncompressed == 0 )
		{
			// No file data!
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}

		// Read the mini header
		$binMiniHeader = fread($this->fp, 8);
		$reallyReadBytes = akstringlen($binMiniHeader);
		if($reallyReadBytes < 8)
		{
			// We read less than requested! Why? Did we hit local EOF?
			if( $this->isEOF(true) && !$this->isEOF(false) )
			{
				// Yeap. Let's go to the next file
				$this->nextFile();
				// Retry reading the header
				$binMiniHeader = fread($this->fp, 8);
				$reallyReadBytes = akstringlen($binMiniHeader);
				// Still not enough data? If so, the archive is corrupt or missing parts.
				if($reallyReadBytes < 8) {
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}
			else
			{
				// Nope. The archive is corrupt
				$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
				return false;
			}
		}

		// Read the encrypted data
		$miniHeader = unpack('Vencsize/Vdecsize', $binMiniHeader);
		$toReadBytes = $miniHeader['encsize'];
		$data = $this->fread( $this->fp, $toReadBytes );
		$reallyReadBytes = akstringlen($data);
		if($reallyReadBytes < $toReadBytes)
		{
			// We read less than requested! Why? Did we hit local EOF?
			if( $this->isEOF(true) && !$this->isEOF(false) )
			{
				// Yeap. Let's go to the next file
				$this->nextFile();
				// Read the rest of the data
				$toReadBytes -= $reallyReadBytes;
				$restData = $this->fread( $this->fp, $toReadBytes );
				$reallyReadBytes = akstringlen($data);
				if($reallyReadBytes < $toReadBytes) {
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
				$data .= $restData;
			}
			else
			{
				// Nope. The archive is corrupt
				$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
				return false;
			}
		}

		// Decrypt the data
		$data = AKEncryptionAES::AESDecryptCBC($data, $this->password, 128);

		// Is the length of the decrypted data less than expected?
		$data_length = akstringlen($data);
		if($data_length < $miniHeader['decsize']) {
			$this->setError(AKText::_('ERR_INVALID_JPS_PASSWORD'));
			return false;
		}

		// Trim the data
		$data = substr($data,0,$miniHeader['decsize']);

		// Try to remove an existing file or directory by the same name
		if(file_exists($this->fileHeader->realFile)) { @unlink($this->fileHeader->realFile); @rmdir($this->fileHeader->realFile); }
		// Remove any trailing slash
		if(substr($this->fileHeader->realFile, -1) == '/') $this->fileHeader->realFile = substr($this->fileHeader->realFile, 0, -1);
		// Create the symlink - only possible within PHP context. There's no support built in the FTP protocol, so no postproc use is possible here :(
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
			@symlink($data, $this->fileHeader->realFile);

		$this->runState = AK_STATE_DATAREAD;

		return true; // No matter if the link was created!
	}

	/**
	 * Process the file data of a directory entry
	 * @return bool
	 */
	private function processTypeDir()
	{
		// Directory entries in the JPA do not have file data, therefore we're done processing the entry
		$this->runState = AK_STATE_DATAREAD;
		return true;
	}

	/**
	 * Creates the directory this file points to
	 */
	protected function createDirectory()
	{
		if( AKFactory::get('kickstart.setup.dryrun','0') ) return true;

		// Do we need to create a directory?
		$lastSlash = strrpos($this->fileHeader->realFile, '/');
		$dirName = substr( $this->fileHeader->realFile, 0, $lastSlash);
		$perms = $this->flagRestorePermissions ? $retArray['permissions'] : 0755;
		$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false);
		if( ($this->postProcEngine->createDirRecursive($dirName, $perms) == false) && (!$ignore) ) {
			$this->setError( AKText::sprintf('COULDNT_CREATE_DIR', $dirName) );
			return false;
		}
		else
		{
			return true;
		}
	}
}

/**
 * A filesystem scanner which uses opendir()
 */
class AKUtilsLister extends AKAbstractObject
{
	public function &getFiles($folder, $pattern = '*')
	{
		// Initialize variables
		$arr = array();
		$false = false;

		if(!is_dir($folder)) return $false;

		$handle = @opendir($folder);
		// If directory is not accessible, just return FALSE
		if ($handle === FALSE) {
			$this->setWarning( 'Unreadable directory '.$folder);
			return $false;
		}

		while (($file = @readdir($handle)) !== false)
		{
			if( !fnmatch($pattern, $file) ) continue;

			if (($file != '.') && ($file != '..'))
			{
				$ds = ($folder == '') || ($folder == '/') || (@substr($folder, -1) == '/') || (@substr($folder, -1) == DIRECTORY_SEPARATOR) ? '' : DIRECTORY_SEPARATOR;
				$dir = $folder . $ds . $file;
				$isDir = is_dir($dir);
				if (!$isDir) {
					$arr[] = $dir;
				}
			}
		}
		@closedir($handle);

		return $arr;
	}

	public function &getFolders($folder, $pattern = '*')
	{
		// Initialize variables
		$arr = array();
		$false = false;

		if(!is_dir($folder)) return $false;

		$handle = @opendir($folder);
		// If directory is not accessible, just return FALSE
		if ($handle === FALSE) {
			$this->setWarning( 'Unreadable directory '.$folder);
			return $false;
		}

		while (($file = @readdir($handle)) !== false)
		{
			if( !fnmatch($pattern, $file) ) continue;

			if (($file != '.') && ($file != '..'))
			{
				$ds = ($folder == '') || ($folder == '/') || (@substr($folder, -1) == '/') || (@substr($folder, -1) == DIRECTORY_SEPARATOR) ? '' : DIRECTORY_SEPARATOR;
				$dir = $folder . $ds . $file;
				$isDir = is_dir($dir);
				if ($isDir) {
					$arr[] = $dir;
				}
			}
		}
		@closedir($handle);

		return $arr;
	}
}

/**
 * A simple INI-based i18n engine
 */

class AKText extends AKAbstractObject
{
	/**
	 * The default (en_GB) translation used when no other translation is available
	 * @var array
	 */
	private $default_translation = array(
		'AUTOMODEON' => 'Auto-mode enabled',
		'ERR_NOT_A_JPA_FILE' => 'The file is not a JPA archive',
		'ERR_CORRUPT_ARCHIVE' => 'The archive file is corrupt, truncated or archive parts are missing',
		'ERR_INVALID_LOGIN' => 'Invalid login',
		'COULDNT_CREATE_DIR' => 'Could not create %s folder',
		'COULDNT_WRITE_FILE' => 'Could not open %s for writing.',
		'WRONG_FTP_HOST' => 'Wrong FTP host or port',
		'WRONG_FTP_USER' => 'Wrong FTP username or password',
		'WRONG_FTP_PATH1' => 'Wrong FTP initial directory - the directory doesn\'t exist',
		'FTP_CANT_CREATE_DIR' => 'Could not create directory %s',
		'FTP_TEMPDIR_NOT_WRITABLE' => 'Could not find or create a writable temporary directory',
		'FTP_COULDNT_UPLOAD' => 'Could not upload %s',
		'THINGS_HEADER' => 'Things you should know about Akeeba Kickstart',
		'THINGS_01' => 'Kickstart is not an installer. It is an archive extraction tool. The actual installer was put inside the archive file at backup time.',
		'THINGS_02' => 'Kickstart is not the only way to extract the backup archive. You can use Akeeba eXtract Wizard and upload the extracted files using FTP instead.',
		'THINGS_03' => 'Kickstart is bound by your server\'s configuration. As such, it may not work at all.',
		'THINGS_04' => 'You should download and upload your archive files using FTP in Binary transfer mode. Any other method could lead to a corrupt backup archive and restoratio failure.',
		'THINGS_05' => 'Post-restoration site load errors are usually caused by .htaccess or php.ini directives. You should understand that blank pages, 404 and 500 errors can usually be worked around by editing the aforementioned files. It is not our job to mess with your configuration files, because this could be dangerous for your site.',
		'THINGS_06' => 'Kickstart overwrites files without a warning. If you are not sure that you are OK with that do not continue.',
		'THINGS_07' => 'Trying to restore to the temporary URL of a cPanel host (e.g. http://1.2.3.4/~username) will lead to restoration failure and your site will appear to be not working. This is normal and it\'s just how your server and CMS software work.',
		'THINGS_08' => 'You are supposed to read the documentation before using this software. Most issues can be avoided, or easily worked around, by understanding how this software works.',
		'THINGS_09' => 'This text does not imply that there is a problem detected. It is standard text displayed every time you launch Kickstart.',
		'CLOSE_LIGHTBOX' => 'Click here or press ESC to close this message',
		'SELECT_ARCHIVE' => 'Select a backup archive',
		'ARCHIVE_FILE' => 'Archive file:',
		'SELECT_EXTRACTION' => 'Select an extraction method',
		'WRITE_TO_FILES' => 'Write to files:',
		'WRITE_DIRECTLY' => 'Directly',
		'WRITE_FTP' => 'Use FTP',
		'FTP_HOST' => 'FTP host name:',
		'FTP_PORT' => 'FTP port:',
		'FTP_FTPS' => 'Use FTP over SSL (FTPS)',
		'FTP_PASSIVE' => 'Use FTP Passive Mode',
		'FTP_USER' => 'FTP user name:',
		'FTP_PASS' => 'FTP password:',
		'FTP_DIR' => 'FTP directory:',
		'FTP_TEMPDIR' => 'Temporary directory:',
		'FTP_CONNECTION_OK' => 'FTP Connection Established',
		'FTP_CONNECTION_FAILURE' => 'The FTP Connection Failed',
		'FTP_TEMPDIR_WRITABLE' => 'The temporary directory is writable.',
		'FTP_TEMPDIR_UNWRITABLE' => 'The temporary directory is not writable. Please check the permissions.',
		'BTN_CHECK' => 'Check',
		'BTN_RESET' => 'Reset',
		'BTN_TESTFTPCON' => 'Test FTP connection',
		'BTN_GOTOSTART' => 'Start over',
		'FINE_TUNE' => 'Fine tune',
		'MIN_EXEC_TIME' => 'Minimum execution time:',
		'MAX_EXEC_TIME' => 'Maximum execution time:',
		'SECONDS_PER_STEP' => 'seconds per step',
		'EXTRACT_FILES' => 'Extract files',
		'BTN_START' => 'Start',
		'EXTRACTING' => 'Extracting',
		'DO_NOT_CLOSE_EXTRACT' => 'Do not close this window while the extraction is in progress',
		'RESTACLEANUP' => 'Restoration and Clean Up',
		'BTN_RUNINSTALLER' => 'Run the Installer',
		'BTN_CLEANUP' => 'Clean Up',
		'BTN_SITEFE' => 'Visit your site\'s front-end',
		'BTN_SITEBE' => 'Visit your site\'s back-end',
		'WARNINGS' => 'Extraction Warnings',
		'ERROR_OCCURED' => 'An error occured',
		'STEALTH_MODE' => 'Stealth mode',
		'STEALTH_URL' => 'HTML file to show to web visitors',
		'ERR_NOT_A_JPS_FILE' => 'The file is not a JPA archive',
		'ERR_INVALID_JPS_PASSWORD' => 'The password you gave is wrong or the archive is corrupt',
		'JPS_PASSWORD' => 'Archive Password (for JPS files)',
		'INVALID_FILE_HEADER' => 'Invalid header in archive file, part %s, offset %s',
		'NEEDSOMEHELPKS' => 'Want some help to use this tool? Read this first:',
		'QUICKSTART' => 'Quick Start Guide',
		'CANTGETITTOWORK' => 'Can\'t get it to work? Click me!',
		'NOARCHIVESCLICKHERE' => 'No archives detected. Click here for troubleshooting instructions.',
		'POSTRESTORATIONTROUBLESHOOTING' => 'Something not working after the restoration? Click here for troubleshooting instructions.',
		'UPDATE_HEADER' => 'An updated version of Akeeba Kickstart (<span id="update-version">unknown</span>) is available!',
		'UPDATE_NOTICE' => 'You are advised to always use the latest version of Akeeba Kickstart available. Older versions may be subject to bugs and will not be supported.',
		'UPDATE_DLNOW' => 'Download now',
		'UPDATE_MOREINFO' => 'More information'
	);

	/**
	 * The array holding the translation keys
	 * @var array
	 */
	private $strings;

	/**
	 * The currently detected language (ISO code)
	 * @var string
	 */
	private $language;

	/*
	 * Initializes the translation engine
	 * @return AKText
	 */
	public function __construct()
	{
		// Start with the default translation
		$this->strings = $this->default_translation;
		// Try loading the translation file in English, if it exists
		$this->loadTranslation('en-GB');
		// Try loading the translation file in the browser's preferred language, if it exists
		$this->getBrowserLanguage();
		if(!is_null($this->language))
		{
			$this->loadTranslation();
		}
	}

	/**
	 * Singleton pattern for Language
	 * @return Language The global Language instance
	 */
	public static function &getInstance()
	{
		static $instance;

		if(!is_object($instance))
		{
			$instance = new AKText();
		}

		return $instance;
	}

	public static function _($string)
	{
		$text =& self::getInstance();

		$key = strtoupper($string);
		$key = substr($key, 0, 1) == '_' ? substr($key, 1) : $key;

		if (isset ($text->strings[$key]))
		{
			$string = $text->strings[$key];
		}
		else
		{
			if (defined($string))
			{
				$string = constant($string);
			}
		}

		return $string;
	}

	public static function sprintf($key)
	{
		$text =& self::getInstance();
		$args = func_get_args();
		if (count($args) > 0) {
			$args[0] = $text->_($args[0]);
			return @call_user_func_array('sprintf', $args);
		}
		return '';
	}

	public function dumpLanguage()
	{
		$out = '';
		foreach($this->strings as $key => $value)
		{
			$out .= "$key=$value\n";
		}
		return $out;
	}

	public function asJavascript()
	{
		$out = '';
		foreach($this->strings as $key => $value)
		{
			$key = addcslashes($key, '\\\'"');
			$value = addcslashes($value, '\\\'"');
			if(!empty($out)) $out .= ",\n";
			$out .= "'$key':\t'$value'";
		}
		return $out;
	}

	public function resetTranslation()
	{
		$this->strings = $this->default_translation;
	}

	public function getBrowserLanguage()
	{
		// Detection code from Full Operating system language detection, by Harald Hope
		// Retrieved from http://techpatterns.com/downloads/php_language_detection.php
		$user_languages = array();
		//check to see if language is set
		if ( isset( $_SERVER["HTTP_ACCEPT_LANGUAGE"] ) )
		{
			$languages = strtolower( $_SERVER["HTTP_ACCEPT_LANGUAGE"] );
			// $languages = ' fr-ch;q=0.3, da, en-us;q=0.8, en;q=0.5, fr;q=0.3';
			// need to remove spaces from strings to avoid error
			$languages = str_replace( ' ', '', $languages );
			$languages = explode( ",", $languages );

			foreach ( $languages as $language_list )
			{
				// pull out the language, place languages into array of full and primary
				// string structure:
				$temp_array = array();
				// slice out the part before ; on first step, the part before - on second, place into array
				$temp_array[0] = substr( $language_list, 0, strcspn( $language_list, ';' ) );//full language
				$temp_array[1] = substr( $language_list, 0, 2 );// cut out primary language
				if( (strlen($temp_array[0]) == 5) && ( (substr($temp_array[0],2,1) == '-') || (substr($temp_array[0],2,1) == '_') ) )
				{
					$langLocation = strtoupper(substr($temp_array[0],3,2));
					$temp_array[0] = $temp_array[1].'-'.$langLocation;
				}
				//place this array into main $user_languages language array
				$user_languages[] = $temp_array;
			}
		}
		else// if no languages found
		{
			$user_languages[0] = array( '','' ); //return blank array.
		}

		$this->language = null;
		// First scan for full languages
		$basename=basename(__FILE__, '.php') . '.ini';
		foreach($user_languages as $languageStruct)
		{
			if (@file_exists($languageStruct[0].'.'.$basename) && is_null($this->language)) {
				$this->language = $languageStruct[0];
			}
		}

		// If we matched a full filename, there's no point going on
		if(!is_null($this->language)) return;

		// Try to match main language part of the filename, irrespective of the location, e.g. de_DE will do if de_CH doesn't exist.
		$fs = new AKUtilsLister();
		$iniFiles = $fs->getFiles( '.', '*.'.$basename );
		if (!is_array($iniFiles)) return; // Get out of here if no Kickstart Translation INI's were found

		foreach($user_languages as $languageStruct)
		{
			if(is_null($this->language))
			{
				// Get files matching the main lang part
				$iniFiles = $fs->getFiles( '.', $languageStruct[1].'-??.'.$basename );
				if (count($iniFiles) > 0) {
					$this->language = substr(basename($iniFiles[0]['name']), 0, 5);
				}
				else
				$this->language = null;
			}
		}
	}

	private function loadTranslation( $lang = null )
	{
		$dirname = function_exists('getcwd') ? getcwd() : dirname(__FILE__);
		$basename=basename(__FILE__, '.php') . '.ini';
		if( empty($lang) ) $lang = $this->language;

		$translationFilename = $dirname.DIRECTORY_SEPARATOR.$lang.'.'.$basename;
		if(!@file_exists($translationFilename)) return;
		$temp = self::parse_ini_file($translationFilename, false);

		if(!is_array($this->strings)) $this->strings = array();
		if(empty($temp)) {
			$this->strings = array_merge($this->default_translation, $this->strings);
		} else {
			$this->strings = array_merge($this->strings, $temp);
		}
	}

	/**
	 * A PHP based INI file parser.
	 *
	 * Thanks to asohn ~at~ aircanopy ~dot~ net for posting this handy function on
	 * the parse_ini_file page on http://gr.php.net/parse_ini_file
	 *
	 * @param string $file Filename to process
	 * @param bool $process_sections True to also process INI sections
	 * @return array An associative array of sections, keys and values
	 * @access private
	 */
	public static function parse_ini_file($file, $process_sections = false, $raw_data = false)
	{
		$process_sections = ($process_sections !== true) ? false : true;

		if(!$raw_data)
		{
			$ini = @file($file);
		}
		else
		{
			$ini = $file;
		}
		if (count($ini) == 0) {return array();}

		$sections = array();
		$values = array();
		$result = array();
		$globals = array();
		$i = 0;
		if(!empty($ini)) foreach ($ini as $line) {
			$line = trim($line);
			$line = str_replace("\t", " ", $line);

			// Comments
			if (!preg_match('/^[a-zA-Z0-9[]/', $line)) {continue;}

			// Sections
			if ($line{0} == '[') {
				$tmp = explode(']', $line);
				$sections[] = trim(substr($tmp[0], 1));
				$i++;
				continue;
			}

			// Key-value pair
			list($key, $value) = explode('=', $line, 2);
			$key = trim($key);
			$value = trim($value);
			if (strstr($value, ";")) {
				$tmp = explode(';', $value);
				if (count($tmp) == 2) {
					if ((($value{0} != '"') && ($value{0} != "'")) ||
					preg_match('/^".*"\s*;/', $value) || preg_match('/^".*;[^"]*$/', $value) ||
					preg_match("/^'.*'\s*;/", $value) || preg_match("/^'.*;[^']*$/", $value) ){
						$value = $tmp[0];
					}
				} else {
					if ($value{0} == '"') {
						$value = preg_replace('/^"(.*)".*/', '$1', $value);
					} elseif ($value{0} == "'") {
						$value = preg_replace("/^'(.*)'.*/", '$1', $value);
					} else {
						$value = $tmp[0];
					}
				}
			}
			$value = trim($value);
			$value = trim($value, "'\"");

			if ($i == 0) {
				if (substr($line, -1, 2) == '[]') {
					$globals[$key][] = $value;
				} else {
					$globals[$key] = $value;
				}
			} else {
				if (substr($line, -1, 2) == '[]') {
					$values[$i-1][$key][] = $value;
				} else {
					$values[$i-1][$key] = $value;
				}
			}
		}

		for($j = 0; $j < $i; $j++) {
			if ($process_sections === true) {
				$result[$sections[$j]] = $values[$j];
			} else {
				$result[] = $values[$j];
			}
		}

		return $result + $globals;
	}
}

/**
 * The Akeeba Kickstart Factory class
 * This class is reponssible for instanciating all Akeeba Kicsktart classes
 */
class AKFactory {
	/** @var array A list of instanciated objects */
	private $objectlist = array();

	/** @var array Simple hash data storage */
	private $varlist = array();

	/** Private constructor makes sure we can't directly instanciate the class */
	private function __construct() {}

	/**
	 * Gets a single, internally used instance of the Factory
	 * @param string $serialized_data [optional] Serialized data to spawn the instance from
	 * @return AKFactory A reference to the unique Factory object instance
	 */
	protected static function &getInstance( $serialized_data = null ) {
		static $myInstance;
		if(!is_object($myInstance) || !is_null($serialized_data))
			if(!is_null($serialized_data))
			{
				$myInstance = unserialize($serialized_data);
			}
			else
			{
				$myInstance = new self();
			}
		return $myInstance;
	}

	/**
	 * Internal function which instanciates a class named $class_name.
	 * The autoloader
	 * @param object $class_name
	 * @return
	 */
	protected static function &getClassInstance($class_name) {
		$self =& self::getInstance();
		if(!isset($self->objectlist[$class_name]))
		{
			$self->objectlist[$class_name] = new $class_name;
		}
		return $self->objectlist[$class_name];
	}

	// ========================================================================
	// Public factory interface
	// ========================================================================

	/**
	 * Gets a serialized snapshot of the Factory for safekeeping (hibernate)
	 * @return string The serialized snapshot of the Factory
	 */
	public static function serialize() {
		$engine =& self::getUnarchiver();
		$engine->shutdown();
		$serialized = serialize(self::getInstance());

		if(function_exists('base64_encode') && function_exists('base64_decode'))
		{
			$serialized = base64_encode($serialized);
		}
		return $serialized;
	}

	/**
	 * Regenerates the full Factory state from a serialized snapshot (resume)
	 * @param string $serialized_data The serialized snapshot to resume from
	 */
	public static function unserialize($serialized_data) {
		if(function_exists('base64_encode') && function_exists('base64_decode'))
		{
			$serialized_data = base64_decode($serialized_data);
		}
		self::getInstance($serialized_data);
	}

	/**
	 * Reset the internal factory state, freeing all previously created objects
	 */
	public static function nuke()
	{
		$self =& self::getInstance();
		foreach($self->objectlist as $key => $object)
		{
			$self->objectlist[$key] = null;
		}
		$self->objectlist = array();
	}

	// ========================================================================
	// Public hash data storage interface
	// ========================================================================

	public static function set($key, $value)
	{
		$self =& self::getInstance();
		$self->varlist[$key] = $value;
	}

	public static function get($key, $default = null)
	{
		$self =& self::getInstance();
		if( array_key_exists($key, $self->varlist) )
		{
			return $self->varlist[$key];
		}
		else
		{
			return $default;
		}
	}

	// ========================================================================
	// Akeeba Kickstart classes
	// ========================================================================

	/**
	 * Gets the post processing engine
	 * @param string $proc_engine
	 */
	public static function &getPostProc($proc_engine = null)
	{
		static $class_name;
		if( empty($class_name) )
		{
			if(empty($proc_engine))
			{
				$proc_engine = self::get('kickstart.procengine','direct');
			}
			$class_name = 'AKPostproc'.ucfirst($proc_engine);
		}
		return self::getClassInstance($class_name);
	}

	/**
	 * Gets the unarchiver engine
	 */
	public static function &getUnarchiver( $configOverride = null )
	{
		static $class_name;

		if(!empty($configOverride))
		{
			if($configOverride['reset']) {
				$class_name = null;
			}
		}

		if( empty($class_name) )
		{
			$filetype = self::get('kickstart.setup.filetype', null);

			if(empty($filetype))
			{
				$filename = self::get('kickstart.setup.sourcefile', null);
				$basename = basename($filename);
				$baseextension = strtoupper(substr($basename,-3));
				switch($baseextension)
				{
					case 'JPA':
						$filetype = 'JPA';
						break;

					case 'JPS':
						$filetype = 'JPS';
						break;

					case 'ZIP':
						$filetype = 'ZIP';
						break;

					default:
						die('Invalid archive type or extension in file '.$filename);
						break;
				}
			}

			$class_name = 'AKUnarchiver'.ucfirst($filetype);
		}

		$destdir = self::get('kickstart.setup.destdir', null);
		if(empty($destdir))
		{
			$destdir = function_exists('getcwd') ? getcwd() : dirname(__FILE__);
		}

		$object =& self::getClassInstance($class_name);
		if( $object->getState() == 'init')
		{
			// Initialize the object
			$config = array(
				'filename'				=> self::get('kickstart.setup.sourcefile', ''),
				'restore_permissions'	=> self::get('kickstart.setup.restoreperms', 0),
				'post_proc'				=> self::get('kickstart.procengine', 'direct'),
				'add_path'				=> $destdir,
				'rename_files'			=> array( '.htaccess' => 'htaccess.bak', 'php.ini' => 'php.ini.bak' ),
				'skip_files'			=> array( basename(__FILE__), 'kickstart.php', 'abiautomation.ini', 'htaccess.bak', 'php.ini.bak' )
			);

			if(!defined('KICKSTART'))
			{
				// In restore.php mode we have to exclude some more files
				$config['skip_files'][] = 'administrator/components/com_akeeba/restore.php';
				$config['skip_files'][] = 'administrator/components/com_akeeba/restoration.php';
			}

			if(!empty($configOverride))
			{
				foreach($configOverride as $key => $value)
				{
					$config[$key] = $value;
				}
			}

			$object->setup($config);
		}

		return $object;
	}

	/**
	 * Get the a reference to the Akeeba Engine's timer
	 * @return AKCoreTimer
	 */
	public static function &getTimer()
	{
		return self::getClassInstance('AKCoreTimer');
	}

}

/**
 * AES implementation in PHP (c) Chris Veness 2005-2011.
 * Right to use and adapt is granted for under a simple creative commons attribution
 * licence. No warranty of any form is offered.
 *
 * Modified for Akeeba Backup by Nicholas K. Dionysopoulos
 */
class AKEncryptionAES
{
	// Sbox is pre-computed multiplicative inverse in GF(2^8) used in SubBytes and KeyExpansion [�5.1.1]
	protected static $Sbox =
			 array(0x63,0x7c,0x77,0x7b,0xf2,0x6b,0x6f,0xc5,0x30,0x01,0x67,0x2b,0xfe,0xd7,0xab,0x76,
	               0xca,0x82,0xc9,0x7d,0xfa,0x59,0x47,0xf0,0xad,0xd4,0xa2,0xaf,0x9c,0xa4,0x72,0xc0,
	               0xb7,0xfd,0x93,0x26,0x36,0x3f,0xf7,0xcc,0x34,0xa5,0xe5,0xf1,0x71,0xd8,0x31,0x15,
	               0x04,0xc7,0x23,0xc3,0x18,0x96,0x05,0x9a,0x07,0x12,0x80,0xe2,0xeb,0x27,0xb2,0x75,
	               0x09,0x83,0x2c,0x1a,0x1b,0x6e,0x5a,0xa0,0x52,0x3b,0xd6,0xb3,0x29,0xe3,0x2f,0x84,
	               0x53,0xd1,0x00,0xed,0x20,0xfc,0xb1,0x5b,0x6a,0xcb,0xbe,0x39,0x4a,0x4c,0x58,0xcf,
	               0xd0,0xef,0xaa,0xfb,0x43,0x4d,0x33,0x85,0x45,0xf9,0x02,0x7f,0x50,0x3c,0x9f,0xa8,
	               0x51,0xa3,0x40,0x8f,0x92,0x9d,0x38,0xf5,0xbc,0xb6,0xda,0x21,0x10,0xff,0xf3,0xd2,
	               0xcd,0x0c,0x13,0xec,0x5f,0x97,0x44,0x17,0xc4,0xa7,0x7e,0x3d,0x64,0x5d,0x19,0x73,
	               0x60,0x81,0x4f,0xdc,0x22,0x2a,0x90,0x88,0x46,0xee,0xb8,0x14,0xde,0x5e,0x0b,0xdb,
	               0xe0,0x32,0x3a,0x0a,0x49,0x06,0x24,0x5c,0xc2,0xd3,0xac,0x62,0x91,0x95,0xe4,0x79,
	               0xe7,0xc8,0x37,0x6d,0x8d,0xd5,0x4e,0xa9,0x6c,0x56,0xf4,0xea,0x65,0x7a,0xae,0x08,
	               0xba,0x78,0x25,0x2e,0x1c,0xa6,0xb4,0xc6,0xe8,0xdd,0x74,0x1f,0x4b,0xbd,0x8b,0x8a,
	               0x70,0x3e,0xb5,0x66,0x48,0x03,0xf6,0x0e,0x61,0x35,0x57,0xb9,0x86,0xc1,0x1d,0x9e,
	               0xe1,0xf8,0x98,0x11,0x69,0xd9,0x8e,0x94,0x9b,0x1e,0x87,0xe9,0xce,0x55,0x28,0xdf,
	               0x8c,0xa1,0x89,0x0d,0xbf,0xe6,0x42,0x68,0x41,0x99,0x2d,0x0f,0xb0,0x54,0xbb,0x16);

	// Rcon is Round Constant used for the Key Expansion [1st col is 2^(r-1) in GF(2^8)] [�5.2]
	protected static $Rcon = array(
				   array(0x00, 0x00, 0x00, 0x00),
	               array(0x01, 0x00, 0x00, 0x00),
	               array(0x02, 0x00, 0x00, 0x00),
	               array(0x04, 0x00, 0x00, 0x00),
	               array(0x08, 0x00, 0x00, 0x00),
	               array(0x10, 0x00, 0x00, 0x00),
	               array(0x20, 0x00, 0x00, 0x00),
	               array(0x40, 0x00, 0x00, 0x00),
	               array(0x80, 0x00, 0x00, 0x00),
	               array(0x1b, 0x00, 0x00, 0x00),
	               array(0x36, 0x00, 0x00, 0x00) );

	protected static $passwords = array();

	/**
	 * AES Cipher function: encrypt 'input' with Rijndael algorithm
	 *
	 * @param input message as byte-array (16 bytes)
	 * @param w     key schedule as 2D byte-array (Nr+1 x Nb bytes) -
	 *              generated from the cipher key by KeyExpansion()
	 * @return      ciphertext as byte-array (16 bytes)
	 */
	protected static function Cipher($input, $w) {    // main Cipher function [�5.1]
	  $Nb = 4;                 // block size (in words): no of columns in state (fixed at 4 for AES)
	  $Nr = count($w)/$Nb - 1; // no of rounds: 10/12/14 for 128/192/256-bit keys

	  $state = array();  // initialise 4xNb byte-array 'state' with input [�3.4]
	  for ($i=0; $i<4*$Nb; $i++) $state[$i%4][floor($i/4)] = $input[$i];

	  $state = self::AddRoundKey($state, $w, 0, $Nb);

	  for ($round=1; $round<$Nr; $round++) {  // apply Nr rounds
	    $state = self::SubBytes($state, $Nb);
	    $state = self::ShiftRows($state, $Nb);
	    $state = self::MixColumns($state, $Nb);
	    $state = self::AddRoundKey($state, $w, $round, $Nb);
	  }

	  $state = self::SubBytes($state, $Nb);
	  $state = self::ShiftRows($state, $Nb);
	  $state = self::AddRoundKey($state, $w, $Nr, $Nb);

	  $output = array(4*$Nb);  // convert state to 1-d array before returning [�3.4]
	  for ($i=0; $i<4*$Nb; $i++) $output[$i] = $state[$i%4][floor($i/4)];
	  return $output;
	}

	protected static function AddRoundKey($state, $w, $rnd, $Nb) {  // xor Round Key into state S [�5.1.4]
	  for ($r=0; $r<4; $r++) {
	    for ($c=0; $c<$Nb; $c++) $state[$r][$c] ^= $w[$rnd*4+$c][$r];
	  }
	  return $state;
	}

	protected static function SubBytes($s, $Nb) {    // apply SBox to state S [�5.1.1]
	  for ($r=0; $r<4; $r++) {
	    for ($c=0; $c<$Nb; $c++) $s[$r][$c] = self::$Sbox[$s[$r][$c]];
	  }
	  return $s;
	}

	protected static function ShiftRows($s, $Nb) {    // shift row r of state S left by r bytes [�5.1.2]
	  $t = array(4);
	  for ($r=1; $r<4; $r++) {
	    for ($c=0; $c<4; $c++) $t[$c] = $s[$r][($c+$r)%$Nb];  // shift into temp copy
	    for ($c=0; $c<4; $c++) $s[$r][$c] = $t[$c];         // and copy back
	  }          // note that this will work for Nb=4,5,6, but not 7,8 (always 4 for AES):
	  return $s;  // see fp.gladman.plus.com/cryptography_technology/rijndael/aes.spec.311.pdf
	}

	protected static function MixColumns($s, $Nb) {   // combine bytes of each col of state S [�5.1.3]
	  for ($c=0; $c<4; $c++) {
	    $a = array(4);  // 'a' is a copy of the current column from 's'
	    $b = array(4);  // 'b' is a�{02} in GF(2^8)
	    for ($i=0; $i<4; $i++) {
	      $a[$i] = $s[$i][$c];
	      $b[$i] = $s[$i][$c]&0x80 ? $s[$i][$c]<<1 ^ 0x011b : $s[$i][$c]<<1;
	    }
	    // a[n] ^ b[n] is a�{03} in GF(2^8)
	    $s[0][$c] = $b[0] ^ $a[1] ^ $b[1] ^ $a[2] ^ $a[3]; // 2*a0 + 3*a1 + a2 + a3
	    $s[1][$c] = $a[0] ^ $b[1] ^ $a[2] ^ $b[2] ^ $a[3]; // a0 * 2*a1 + 3*a2 + a3
	    $s[2][$c] = $a[0] ^ $a[1] ^ $b[2] ^ $a[3] ^ $b[3]; // a0 + a1 + 2*a2 + 3*a3
	    $s[3][$c] = $a[0] ^ $b[0] ^ $a[1] ^ $a[2] ^ $b[3]; // 3*a0 + a1 + a2 + 2*a3
	  }
	  return $s;
	}

	/**
	 * Key expansion for Rijndael Cipher(): performs key expansion on cipher key
	 * to generate a key schedule
	 *
	 * @param key cipher key byte-array (16 bytes)
	 * @return    key schedule as 2D byte-array (Nr+1 x Nb bytes)
	 */
	protected static function KeyExpansion($key) {  // generate Key Schedule from Cipher Key [�5.2]
	  $Nb = 4;              // block size (in words): no of columns in state (fixed at 4 for AES)
	  $Nk = count($key)/4;  // key length (in words): 4/6/8 for 128/192/256-bit keys
	  $Nr = $Nk + 6;        // no of rounds: 10/12/14 for 128/192/256-bit keys

	  $w = array();
	  $temp = array();

	  for ($i=0; $i<$Nk; $i++) {
	    $r = array($key[4*$i], $key[4*$i+1], $key[4*$i+2], $key[4*$i+3]);
	    $w[$i] = $r;
	  }

	  for ($i=$Nk; $i<($Nb*($Nr+1)); $i++) {
	    $w[$i] = array();
	    for ($t=0; $t<4; $t++) $temp[$t] = $w[$i-1][$t];
	    if ($i % $Nk == 0) {
	      $temp = self::SubWord(self::RotWord($temp));
	      for ($t=0; $t<4; $t++) $temp[$t] ^= self::$Rcon[$i/$Nk][$t];
	    } else if ($Nk > 6 && $i%$Nk == 4) {
	      $temp = self::SubWord($temp);
	    }
	    for ($t=0; $t<4; $t++) $w[$i][$t] = $w[$i-$Nk][$t] ^ $temp[$t];
	  }
	  return $w;
	}

	protected static function SubWord($w) {    // apply SBox to 4-byte word w
	  for ($i=0; $i<4; $i++) $w[$i] = self::$Sbox[$w[$i]];
	  return $w;
	}

	protected static function RotWord($w) {    // rotate 4-byte word w left by one byte
	  $tmp = $w[0];
	  for ($i=0; $i<3; $i++) $w[$i] = $w[$i+1];
	  $w[3] = $tmp;
	  return $w;
	}

	/*
	 * Unsigned right shift function, since PHP has neither >>> operator nor unsigned ints
	 *
	 * @param a  number to be shifted (32-bit integer)
	 * @param b  number of bits to shift a to the right (0..31)
	 * @return   a right-shifted and zero-filled by b bits
	 */
	protected static function urs($a, $b) {
	  $a &= 0xffffffff; $b &= 0x1f;  // (bounds check)
	  if ($a&0x80000000 && $b>0) {   // if left-most bit set
	    $a = ($a>>1) & 0x7fffffff;   //   right-shift one bit & clear left-most bit
	    $a = $a >> ($b-1);           //   remaining right-shifts
	  } else {                       // otherwise
	    $a = ($a>>$b);               //   use normal right-shift
	  }
	  return $a;
	}

	/**
	 * Encrypt a text using AES encryption in Counter mode of operation
	 *  - see http://csrc.nist.gov/publications/nistpubs/800-38a/sp800-38a.pdf
	 *
	 * Unicode multi-byte character safe
	 *
	 * @param plaintext source text to be encrypted
	 * @param password  the password to use to generate a key
	 * @param nBits     number of bits to be used in the key (128, 192, or 256)
	 * @return          encrypted text
	 */
	public static function AESEncryptCtr($plaintext, $password, $nBits) {
	  $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
	  if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
	  // note PHP (5) gives us plaintext and password in UTF8 encoding!

	  // use AES itself to encrypt password to get cipher key (using plain password as source for
	  // key expansion) - gives us well encrypted key
	  $nBytes = $nBits/8;  // no bytes in key
	  $pwBytes = array();
	  for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
	  $key = self::Cipher($pwBytes, self::KeyExpansion($pwBytes));
	  $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long

	  // initialise counter block (NIST SP800-38A �B.2): millisecond time-stamp for nonce in
	  // 1st 8 bytes, block counter in 2nd 8 bytes
	  $counterBlock = array();
	  $nonce = floor(microtime(true)*1000);   // timestamp: milliseconds since 1-Jan-1970
	  $nonceSec = floor($nonce/1000);
	  $nonceMs = $nonce%1000;
	  // encode nonce with seconds in 1st 4 bytes, and (repeated) ms part filling 2nd 4 bytes
	  for ($i=0; $i<4; $i++) $counterBlock[$i] = self::urs($nonceSec, $i*8) & 0xff;
	  for ($i=0; $i<4; $i++) $counterBlock[$i+4] = $nonceMs & 0xff;
	  // and convert it to a string to go on the front of the ciphertext
	  $ctrTxt = '';
	  for ($i=0; $i<8; $i++) $ctrTxt .= chr($counterBlock[$i]);

	  // generate key schedule - an expansion of the key into distinct Key Rounds for each round
	  $keySchedule = self::KeyExpansion($key);

	  $blockCount = ceil(strlen($plaintext)/$blockSize);
	  $ciphertxt = array();  // ciphertext as array of strings

	  for ($b=0; $b<$blockCount; $b++) {
	    // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
	    // done in two stages for 32-bit ops: using two words allows us to go past 2^32 blocks (68GB)
	    for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
	    for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs($b/0x100000000, $c*8);

	    $cipherCntr = self::Cipher($counterBlock, $keySchedule);  // -- encrypt counter block --

	    // block size is reduced on final block
	    $blockLength = $b<$blockCount-1 ? $blockSize : (strlen($plaintext)-1)%$blockSize+1;
	    $cipherByte = array();

	    for ($i=0; $i<$blockLength; $i++) {  // -- xor plaintext with ciphered counter byte-by-byte --
	      $cipherByte[$i] = $cipherCntr[$i] ^ ord(substr($plaintext, $b*$blockSize+$i, 1));
	      $cipherByte[$i] = chr($cipherByte[$i]);
	    }
	    $ciphertxt[$b] = implode('', $cipherByte);  // escape troublesome characters in ciphertext
	  }

	  // implode is more efficient than repeated string concatenation
	  $ciphertext = $ctrTxt . implode('', $ciphertxt);
	  $ciphertext = base64_encode($ciphertext);
	  return $ciphertext;
	}

	/**
	 * Decrypt a text encrypted by AES in counter mode of operation
	 *
	 * @param ciphertext source text to be decrypted
	 * @param password   the password to use to generate a key
	 * @param nBits      number of bits to be used in the key (128, 192, or 256)
	 * @return           decrypted text
	 */
	public static function AESDecryptCtr($ciphertext, $password, $nBits) {
	  $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
	  if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
	  $ciphertext = base64_decode($ciphertext);

	  // use AES to encrypt password (mirroring encrypt routine)
	  $nBytes = $nBits/8;  // no bytes in key
	  $pwBytes = array();
	  for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
	  $key = self::Cipher($pwBytes, self::KeyExpansion($pwBytes));
	  $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long

	  // recover nonce from 1st element of ciphertext
	  $counterBlock = array();
	  $ctrTxt = substr($ciphertext, 0, 8);
	  for ($i=0; $i<8; $i++) $counterBlock[$i] = ord(substr($ctrTxt,$i,1));

	  // generate key schedule
	  $keySchedule = self::KeyExpansion($key);

	  // separate ciphertext into blocks (skipping past initial 8 bytes)
	  $nBlocks = ceil((strlen($ciphertext)-8) / $blockSize);
	  $ct = array();
	  for ($b=0; $b<$nBlocks; $b++) $ct[$b] = substr($ciphertext, 8+$b*$blockSize, 16);
	  $ciphertext = $ct;  // ciphertext is now array of block-length strings

	  // plaintext will get generated block-by-block into array of block-length strings
	  $plaintxt = array();

	  for ($b=0; $b<$nBlocks; $b++) {
	    // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
	    for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
	    for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs(($b+1)/0x100000000-1, $c*8) & 0xff;

	    $cipherCntr = self::Cipher($counterBlock, $keySchedule);  // encrypt counter block

	    $plaintxtByte = array();
	    for ($i=0; $i<strlen($ciphertext[$b]); $i++) {
	      // -- xor plaintext with ciphered counter byte-by-byte --
	      $plaintxtByte[$i] = $cipherCntr[$i] ^ ord(substr($ciphertext[$b],$i,1));
	      $plaintxtByte[$i] = chr($plaintxtByte[$i]);

	    }
	    $plaintxt[$b] = implode('', $plaintxtByte);
	  }

	  // join array of blocks into single plaintext string
	  $plaintext = implode('',$plaintxt);

	  return $plaintext;
	}

	/**
	 * AES decryption in CBC mode. This is the standard mode (the CTR methods
	 * actually use Rijndael-128 in CTR mode, which - technically - isn't AES).
	 *
	 * Supports AES-128, AES-192 and AES-256. It supposes that the last 4 bytes
	 * contained a little-endian unsigned long integer representing the unpadded
	 * data length.
	 *
	 * @since 3.0.1
	 * @author Nicholas K. Dionysopoulos
	 *
	 * @param string $ciphertext The data to encrypt
	 * @param string $password Encryption password
	 * @param int $nBits Encryption key size. Can be 128, 192 or 256
	 * @return string The plaintext
	 */
	public static function AESDecryptCBC($ciphertext, $password, $nBits = 128)
	{
		if (!($nBits==128 || $nBits==192 || $nBits==256)) return false;  // standard allows 128/192/256 bit keys
		if(!function_exists('mcrypt_module_open')) return false;

		// Try to fetch cached key/iv or create them if they do not exist
		$lookupKey = $password.'-'.$nBits;
		if(array_key_exists($lookupKey, self::$passwords))
		{
			$key	= self::$passwords[$lookupKey]['key'];
			$iv		= self::$passwords[$lookupKey]['iv'];
		}
		else
		{
			// use AES itself to encrypt password to get cipher key (using plain password as source for
			// key expansion) - gives us well encrypted key
			$nBytes = $nBits/8;  // no bytes in key
			$pwBytes = array();
			for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
			$key = self::Cipher($pwBytes, self::KeyExpansion($pwBytes));
			$key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long
			$newKey = '';
			foreach($key as $int) { $newKey .= chr($int); }
			$key = $newKey;

			// Create an Initialization Vector (IV) based on the password, using the same technique as for the key
			$nBytes = 16;  // AES uses a 128 -bit (16 byte) block size, hence the IV size is always 16 bytes
			$pwBytes = array();
			for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
			$iv = self::Cipher($pwBytes, self::KeyExpansion($pwBytes));
			$newIV = '';
			foreach($iv as $int) { $newIV .= chr($int); }
			$iv = $newIV;

			self::$passwords[$lookupKey]['key'] = $key;
			self::$passwords[$lookupKey]['iv'] = $iv;
		}

		// Read the data size
		$data_size = unpack('V', substr($ciphertext,-4) );

		// Decrypt
		$td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
		mcrypt_generic_init($td, $key, $iv);
		$plaintext = mdecrypt_generic($td, substr($ciphertext,0,-4));
		mcrypt_generic_deinit($td);

		// Trim padding, if necessary
		if(strlen($plaintext) > $data_size)
		{
			$plaintext = substr($plaintext, 0, $data_size);
		}

		return $plaintext;
	}
}

/**
 * The Master Setup will read the configuration parameters from restoration.php, abiautomation.ini, or
 * the JSON-encoded "configuration" input variable and return the status.
 * @return bool True if the master configuration was applied to the Factory object
 */
function masterSetup()
{
	// ------------------------------------------------------------
	// 1. Import basic setup parameters
	// ------------------------------------------------------------

	$ini_data = null;

	// In restore.php mode, require restoration.php or fail
	if(!defined('KICKSTART'))
	{
		// This is the standalone mode, used by Akeeba Backup Professional. It looks for a restoration.php
		// file to perform its magic. If the file is not there, we will abort.
		$setupFile = 'restoration.php';

		if( !file_exists($setupFile) )
		{
			// Uh oh... Somebody tried to pooh on our back yard. Lock the gates! Don't let the traitor inside!
			AKFactory::set('kickstart.enabled', false);
			return false;
		}

		// Load restoration.php. It creates a global variable named $restoration_setup
		require_once $setupFile;
		$ini_data = $restoration_setup;
		if(empty($ini_data))
		{
			// No parameters fetched. Darn, how am I supposed to work like that?!
			AKFactory::set('kickstart.enabled', false);
			return false;
		}

		AKFactory::set('kickstart.enabled', true);
	}
	else
	{
		// Maybe we have $restoration_setup defined in the head of kickstart.php
		global $restoration_setup;
		if(!empty($restoration_setup) && !is_array($restoration_setup)) {
			$ini_data = AKText::parse_ini_file($restoration_setup, false, true);
		} elseif(is_array($restoration_setup)) {
			$ini_data = $restoration_setup;
		}
	}

	// Import any data from $restoration_setup
	if(!empty($ini_data))
	{
		foreach($ini_data as $key => $value)
		{
			AKFactory::set($key, $value);
		}
		AKFactory::set('kickstart.enabled', true);
	}

	// Reinitialize $ini_data
	$ini_data = null;

	// ------------------------------------------------------------
	// 2. Explode JSON parameters into $_REQUEST scope
	// ------------------------------------------------------------

	// Detect a JSON string in the request variable and store it.
	$json = getQueryParam('json', null);

	// Remove everything from the request array
	if(!empty($_REQUEST))
	{
		foreach($_REQUEST as $key => $value)
		{
			unset($_REQUEST[$key]);
		}
	}
	// Decrypt a possibly encrypted JSON string
	if(!empty($json))
	{
		$password = AKFactory::get('kickstart.security.password', null);
		if(!empty($password))
		{
			$json = AKEncryptionAES::AESDecryptCtr($json, $password, 128);
		}

		// Get the raw data
		$raw = json_decode( $json, true );
		// Pass all JSON data to the request array
		if(!empty($raw))
		{
			foreach($raw as $key => $value)
			{
				$_REQUEST[$key] = $value;
			}
		}
	}

	// ------------------------------------------------------------
	// 3. Try the "factory" variable
	// ------------------------------------------------------------
	// A "factory" variable will override all other settings.
	$serialized = getQueryParam('factory', null);
	if( !is_null($serialized) )
	{
		// Get the serialized factory
		AKFactory::unserialize($serialized);
		AKFactory::set('kickstart.enabled', true);
		return true;
	}

	// ------------------------------------------------------------
	// 4. Try abiautomation.ini and the configuration variable for Kickstart
	// ------------------------------------------------------------
	if(defined('KICKSTART'))
	{
		// We are in Kickstart mode. abiautomation.ini has precedence.
		$setupFile = 'abiautomation.ini';
		if( file_exists($setupFile) )
		{
			// abiautomation.ini was found
			$ini_data = AKText::parse_ini_file('restoration.ini', false);
		}
		else
		{
			// abiautomation.ini was not found. Let's try input parameters.
			$configuration = getQueryParam('configuration');
			if( !is_null($configuration) )
			{
				// Let's decode the configuration from JSON to array
				$ini_data = json_decode($configuration, true);
			}
			else
			{
				// Neither exists. Enable Kickstart's interface anyway.
				$ini_data = array('kickstart.enabled'=>true);
			}
		}

		// Import any INI data we might have from other sources
		if(!empty($ini_data))
		{
			foreach($ini_data as $key => $value)
			{
				AKFactory::set($key, $value);
			}
			AKFactory::set('kickstart.enabled', true);
			return true;
		}
	}
}

// Mini-controller for restore.php
if(!defined('KICKSTART'))
{
	// The observer class, used to report number of files and bytes processed
	class RestorationObserver extends AKAbstractPartObserver
	{
		public $compressedTotal = 0;
		public $uncompressedTotal = 0;
		public $filesProcessed = 0;

		public function update($object, $message)
		{
			if(!is_object($message)) return;

			if( !array_key_exists('type', get_object_vars($message)) ) return;

			if( $message->type == 'startfile' )
			{
				$this->filesProcessed++;
				$this->compressedTotal += $message->content->compressed;
				$this->uncompressedTotal += $message->content->uncompressed;
			}
		}

		public function __toString()
		{
			return __CLASS__;
		}

	}

	// Import configuration
	masterSetup();

	$retArray = array(
		'status'	=> true,
		'message'	=> null
	);

	$enabled = AKFactory::get('kickstart.enabled', false);

	if($enabled)
	{
		$task = getQueryParam('task');

		switch($task)
		{
			case 'ping':
				// ping task - realy does nothing!
				$timer =& AKFactory::getTimer();
				$timer->enforce_min_exec_time();
				break;

			case 'startRestore':
				AKFactory::nuke(); // Reset the factory

				// Let the control flow to the next step (the rest of the code is common!!)

			case 'stepRestore':
				$engine =& AKFactory::getUnarchiver(); // Get the engine
				$observer = new RestorationObserver(); // Create a new observer
				$engine->attach($observer); // Attach the observer
				$engine->tick();
				$ret = $engine->getStatusArray();

				if( $ret['Error'] != '' )
				{
					$retArray['status'] = false;
					$retArray['done'] = true;
					$retArray['message'] = $ret['Error'];
				}
				elseif( !$ret['HasRun'] )
				{
					$retArray['files'] = $observer->filesProcessed;
					$retArray['bytesIn'] = $observer->compressedTotal;
					$retArray['bytesOut'] = $observer->uncompressedTotal;
					$retArray['status'] = true;
					$retArray['done'] = true;
				}
				else
				{
					$retArray['files'] = $observer->filesProcessed;
					$retArray['bytesIn'] = $observer->compressedTotal;
					$retArray['bytesOut'] = $observer->uncompressedTotal;
					$retArray['status'] = true;
					$retArray['done'] = false;
					$retArray['factory'] = AKFactory::serialize();
				}
				break;

			case 'finalizeRestore':
				$root = AKFactory::get('kickstart.setup.destdir');
				// Remove the installation directory
				recursive_remove_directory( $root.DS.'installation' );

				$postproc =& AKFactory::getPostProc();

				// Rename htaccess.bak to .htaccess
				if(file_exists($root.DS.'htaccess.bak'))
				{
					if( file_exists($root.DS.'.htaccess')  )
					{
						$postproc->unlink($root.DS.'.htaccess');
					}
					$postproc->rename( $root.DS.'htaccess.bak', $root.DS.'.htaccess' );
				}

				// Remove restoration.php
				$basepath = dirname(__FILE__);
				$basepath = rtrim( str_replace('\\','/',$basepath), '/' );
				if(!empty($basepath)) $basepath .= '/';
				$postproc->unlink( $basepath.'restoration.php' );
				break;

			default:
				// Invalid task!
				$enabled = false;
				break;
		}
	}

	// Maybe we weren't authorized or the task was invalid?
	if(!$enabled)
	{
		// Maybe the user failed to enter any information
		$retArray['status'] = false;
		$retArray['message'] = AKText::_('ERR_INVALID_LOGIN');
	}

	// JSON encode the message
	$json = json_encode($retArray);
	// Do I have to encrypt?
	$password = AKFactory::get('kickstart.security.password', null);
	if(!empty($password))
	{
		$json = AKEncryptionAES::AESEncryptCtr($json, $password, 128);
	}

	// Return the message
	echo "###$json###";

}

// ------------ lixlpixel recursive PHP functions -------------
// recursive_remove_directory( directory to delete, empty )
// expects path to directory and optional TRUE / FALSE to empty
// of course PHP has to have the rights to delete the directory
// you specify and all files and folders inside the directory
// ------------------------------------------------------------
function recursive_remove_directory($directory)
{
	// if the path has a slash at the end we remove it here
	if(substr($directory,-1) == '/')
	{
		$directory = substr($directory,0,-1);
	}
	// if the path is not valid or is not a directory ...
	if(!file_exists($directory) || !is_dir($directory))
	{
		// ... we return false and exit the function
		return FALSE;
	// ... if the path is not readable
	}elseif(!is_readable($directory))
	{
		// ... we return false and exit the function
		return FALSE;
	// ... else if the path is readable
	}else{
		// we open the directory
		$handle = opendir($directory);
		$postproc =& AKFactory::getPostProc();
		// and scan through the items inside
		while (FALSE !== ($item = readdir($handle)))
		{
			// if the filepointer is not the current directory
			// or the parent directory
			if($item != '.' && $item != '..')
			{
				// we build the new path to delete
				$path = $directory.'/'.$item;
				// if the new path is a directory
				if(is_dir($path))
				{
					// we call this function with the new path
					recursive_remove_directory($path);
				// if the new path is a file
				}else{
					// we remove the file
					$postproc->unlink($path);
				}
			}
		}
		// close the directory
		closedir($handle);
		// try to delete the now empty directory
		if(!$postproc->rmdir($directory))
		{
			// return false if not possible
			return FALSE;
		}
		// return success
		return TRUE;
	}
}
?><?php
if(!defined('KICKSTART')) {
	require_once 'defines.php';
	require_once 'restore.php';
}

class AKAutomation
{
	/**
	 * @var bool Is there automation information available?
	 */
	private $_hasAutomation = false;

	/**
	 * @var array The abiautomation.ini contents, in array format
	 */
	private $_automation = array();

	/**
	 * Singleton implementation
	 * @return ABIAutomation
	 */
	public static function &getInstance()
	{
		static $instance;

		if(empty($instance))
		{
			$instance = new AKAutomation();
		}

		return $instance;
	}

	/**
	 * Loads and parses the automation INI file
	 * @return AKAutomation
	 */
	public function __construct()
	{
		// Initialize
		$this->_hasAutomation = false;
		$this->_automation = array();

		$filenames = array('abiautomation.ini', 'kickstart.ini', 'jpi4automation');

		foreach($filenames as $filename)
		{
			// Try to load the abiautomation.ini file
			if(@file_exists($filename))
			{
				$this->_automation = $this->_parse_ini_file($filename, true);
				if(!isset($this->_automation['kickstart']))
				{
					$this->_automation = array();
				}
				else
				{
					$this->_hasAutomation = true;
					break;
				}
			}
		}

	}

	/**
	 * Do we have automation?
	 * @return bool True if abiautomation.ini exists and has a abi section
	 */
	public function hasAutomation()
	{
		return $this->_hasAutomation;
	}

	/**
	 * Returns an automation section. If the section doesn't exist, it returns an empty array.
	 * @param string $section [optional] The name of the section to load, defaults to 'kickstart'
	 * @return array
	 */
	public function getSection($section = 'kickstart')
	{
		if(!$this->_hasAutomation)
		{
			return array();
		}
		else
		{
			if(isset($this->_automation[$section]))
			{
				return $this->_automation[$section];
			} else {
				return array();
			}
		}
	}

	private function _parse_ini_file($file, $process_sections = false, $rawdata = false)
	{
		$process_sections = ($process_sections !== true) ? false : true;

		if(!$rawdata)
		{
			$ini = file($file);
		}
		else
		{
			$file = str_replace("\r","",$file);
			$ini = explode("\n", $file);
		}

		if (count($ini) == 0) {return array();}

		$sections = array();
		$values = array();
		$result = array();
		$globals = array();
		$i = 0;
		foreach ($ini as $line) {
			$line = trim($line);
			$line = str_replace("\t", " ", $line);

			// Comments
			if (!preg_match('/^[a-zA-Z0-9[]/', $line)) {continue;}

			// Sections
			if ($line{0} == '[') {
				$tmp = explode(']', $line);
				$sections[] = trim(substr($tmp[0], 1));
				$i++;
				continue;
			}

			// Key-value pair
			list($key, $value) = explode('=', $line, 2);
			$key = trim($key);
			$value = trim($value);
			if (strstr($value, ";")) {
				$tmp = explode(';', $value);
				if (count($tmp) == 2) {
					if ((($value{0} != '"') && ($value{0} != "'")) ||
					preg_match('/^".*"\s*;/', $value) || preg_match('/^".*;[^"]*$/', $value) ||
					preg_match("/^'.*'\s*;/", $value) || preg_match("/^'.*;[^']*$/", $value) ){
						$value = $tmp[0];
					}
				} else {
					if ($value{0} == '"') {
						$value = preg_replace('/^"(.*)".*/', '$1', $value);
					} elseif ($value{0} == "'") {
						$value = preg_replace("/^'(.*)'.*/", '$1', $value);
					} else {
						$value = $tmp[0];
					}
				}
			}
			$value = trim($value);
			$value = trim($value, "'\"");

			if ($i == 0) {
				if (substr($line, -1, 2) == '[]') {
					$globals[$key][] = $value;
				} else {
					$globals[$key] = $value;
				}
			} else {
				if (substr($line, -1, 2) == '[]') {
					$values[$i-1][$key][] = $value;
				} else {
					$values[$i-1][$key] = $value;
				}
			}
		}

		for($j = 0; $j < $i; $j++) {
			if ($process_sections === true) {
				$result[$sections[$j]] = $values[$j];
			} else {
				$result[] = $values[$j];
			}
		}

		return $result + $globals;
	}
}

class AKKickstartUtils
{
	/**
	 * Gets the directory the file is in
	 * @return string
	 */
	public static function getPath()
	{
		if(function_exists('getcwd'))
		{
			$path = getcwd();
		}
		else
		{
			$path = dirname(__FILE__);
		}
		$path = rtrim(str_replace('\\','/',$path),'/');
		if(!empty($path)) $path .= '/';
		return $path;
	}

	/**
	 * Scans the current directory for archive files (JPA, JPS and ZIP formet)
	 * @return array
	 */
	public static function findArchives()
	{
		$ret = array();
		$path = self::getPath();
		if(empty($path)) $path = '.';
		$dh = @opendir($path);
		if($dh === false) return $ret;
		while( false !== $file = @readdir($dh) )
		{
			$dotpos = strrpos($file,'.');
			if($dotpos === false) continue;
			if($dotpos == strlen($file)) continue;
			$extension = strtolower( substr($file,$dotpos+1) );
			if(in_array($extension,array('jpa','zip','jps')))
			{
				$ret[] = $file;
			}
		}
		closedir($dh);
		if(!empty($ret)) return $ret;
		
		// On some hosts using opendir doesn't work. Let's try Dir instead
		$d = dir($path);
		while(false != ($file = $d->read()))
		{
			$dotpos = strrpos($file,'.');
			if($dotpos === false) continue;
			if($dotpos == strlen($file)) continue;
			$extension = strtolower( substr($file,$dotpos+1) );
			if(in_array($extension,array('jpa','zip','jps')))
			{
				$ret[] = $file;
			}
		}
		return $ret;
	}

	/**
	 * Scans the current directory for archive files and returns them as <OPTION> tags
	 * @return string
	 */
	public static function getArchivesAsOptions()
	{
		$ret = '';
		$archives = self::findArchives();
		if(empty($archives)) return $ret;
		foreach($archives as $file)
		{
			//$file = htmlentities($file);
			$ret .= '<option value="'.$file.'">'.$file.'</option>'."\n";
		}
		return $ret;
	}
}

// --- Amazon S3 suppport - BEGIN
class S3Exception extends Exception {
	
}

/**
 * Based on S3.php by Donovan Schönknecht.
 * Refactored by Nicholas K. Dionysopoulos to use exceptions instead of user
 * errors & warnigns.
 * 
 * Original copyright notice:
 * Copyright (c) 2008, Donovan Schönknecht.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * Amazon S3 is a trademark of Amazon.com, Inc. or its affiliates.
 */

/**
* Amazon S3 PHP class
*
* @link http://undesigned.org.za/2007/10/22/amazon-s3-php-class
* @version 0.4.0
*/
class S3Adapter {
	// ACL flags
	const ACL_PRIVATE = 'private';
	const ACL_PUBLIC_READ = 'public-read';
	const ACL_PUBLIC_READ_WRITE = 'public-read-write';
	const ACL_AUTHENTICATED_READ = 'authenticated-read';

	public static $useSSL = true;

	private static $__accessKey; // AWS Access key
	private static $__secretKey; // AWS Secret key


	/**
	* Constructor - if you're not using the class statically
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @param boolean $useSSL Enable SSL
	* @return void
	*/
	public function __construct($accessKey = null, $secretKey = null, $useSSL = true) {
		if ($accessKey !== null && $secretKey !== null)
			self::setAuth($accessKey, $secretKey);
		self::$useSSL = $useSSL;
	}


	/**
	* Set AWS access key and secret key
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @return void
	*/
	public static function setAuth($accessKey, $secretKey) {
		self::$__accessKey = $accessKey;
		self::$__secretKey = $secretKey;
	}


	/**
	* Get a list of buckets
	*
	* @param boolean $detailed Returns detailed bucket list when true
	* @return array | false
	*/
	public static function listBuckets($detailed = false) {
		$rest = new S3Request('GET', '', '');
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::listBuckets(): [%s] %s", $rest->error['code'], $rest->error['message']));
			return false;
		}
		$results = array();
		if (!isset($rest->body->Buckets)) return $results;

		if ($detailed) {
			if (isset($rest->body->Owner, $rest->body->Owner->ID, $rest->body->Owner->DisplayName))
			$results['owner'] = array(
				'id' => (string)$rest->body->Owner->ID, 'name' => (string)$rest->body->Owner->ID
			);
			$results['buckets'] = array();
			foreach ($rest->body->Buckets->Bucket as $b)
				$results['buckets'][] = array(
					'name' => (string)$b->Name, 'time' => strtotime((string)$b->CreationDate)
				);
		} else
			foreach ($rest->body->Buckets->Bucket as $b) $results[] = (string)$b->Name;

		return $results;
	}


	/*
	* Get contents for a bucket
	*
	* If maxKeys is null this method will loop through truncated result sets
	*
	* @param string $bucket Bucket name
	* @param string $prefix Prefix
	* @param string $marker Marker (last file listed)
	* @param string $maxKeys Max keys (maximum number of keys to return)
	* @param string $delimiter Delimiter
	* @param boolean $returnCommonPrefixes Set to true to return CommonPrefixes
	* @return array | false
	*/
	public static function getBucket($bucket, $prefix = null, $marker = null, $maxKeys = null, $delimiter = null, $returnCommonPrefixes = false) {
		$rest = new S3Request('GET', $bucket, '');
		if ($prefix !== null && $prefix !== '') $rest->setParameter('prefix', $prefix);
		if ($marker !== null && $marker !== '') $rest->setParameter('marker', $marker);
		if ($maxKeys !== null && $maxKeys !== '') $rest->setParameter('max-keys', $maxKeys);
		if ($delimiter !== null && $delimiter !== '') $rest->setParameter('delimiter', $delimiter);
		$response = $rest->getResponse();
		if ($response->error === false && $response->code !== 200)
			$response->error = array('code' => $response->code, 'message' => 'Unexpected HTTP status');
		if ($response->error !== false) {
			throw new S3Exception(sprintf("S3::getBucket(): [%s] %s", $response->error['code'], $response->error['message']));
			return false;
		}

		$results = array();

		$nextMarker = null;
		if (isset($response->body, $response->body->Contents))
		foreach ($response->body->Contents as $c) {
			$results[(string)$c->Key] = array(
				'name' => (string)$c->Key,
				'time' => strtotime((string)$c->LastModified),
				'size' => (int)$c->Size,
				'hash' => substr((string)$c->ETag, 1, -1)
			);
			$nextMarker = (string)$c->Key;
		}

		if ($returnCommonPrefixes && isset($response->body, $response->body->CommonPrefixes))
			foreach ($response->body->CommonPrefixes as $c)
				$results[(string)$c->Prefix] = array('prefix' => (string)$c->Prefix);

		if (isset($response->body, $response->body->IsTruncated) &&
		(string)$response->body->IsTruncated == 'false') return $results;

		if (isset($response->body, $response->body->NextMarker))
			$nextMarker = (string)$response->body->NextMarker;

		// Loop through truncated results if maxKeys isn't specified
		if ($maxKeys == null && $nextMarker !== null && (string)$response->body->IsTruncated == 'true')
		do {
			$rest = new S3Request('GET', $bucket, '');
			if ($prefix !== null && $prefix !== '') $rest->setParameter('prefix', $prefix);
			$rest->setParameter('marker', $nextMarker);
			if ($delimiter !== null && $delimiter !== '') $rest->setParameter('delimiter', $delimiter);

			if (($response = $rest->getResponse(true)) == false || $response->code !== 200) break;

			if (isset($response->body, $response->body->Contents))
			foreach ($response->body->Contents as $c) {
				$results[(string)$c->Key] = array(
					'name' => (string)$c->Key,
					'time' => strtotime((string)$c->LastModified),
					'size' => (int)$c->Size,
					'hash' => substr((string)$c->ETag, 1, -1)
				);
				$nextMarker = (string)$c->Key;
			}

			if ($returnCommonPrefixes && isset($response->body, $response->body->CommonPrefixes))
				foreach ($response->body->CommonPrefixes as $c)
					$results[(string)$c->Prefix] = array('prefix' => (string)$c->Prefix);

			if (isset($response->body, $response->body->NextMarker))
				$nextMarker = (string)$response->body->NextMarker;

		} while ($response !== false && (string)$response->body->IsTruncated == 'true');

		return $results;
	}


	/**
	* Put a bucket
	*
	* @param string $bucket Bucket name
	* @param constant $acl ACL flag
	* @param string $location Set as "EU" to create buckets hosted in Europe
	* @return boolean
	*/
	public static function putBucket($bucket, $acl = self::ACL_PRIVATE, $location = false) {
		$rest = new S3Request('PUT', $bucket, '');
		$rest->setAmzHeader('x-amz-acl', $acl);

		if ($location !== false) {
			$dom = new DOMDocument;
			$createBucketConfiguration = $dom->createElement('CreateBucketConfiguration');
			$locationConstraint = $dom->createElement('LocationConstraint', strtoupper($location));
			$createBucketConfiguration->appendChild($locationConstraint);
			$dom->appendChild($createBucketConfiguration);
			$rest->data = $dom->saveXML();
			$rest->size = strlen($rest->data);
			$rest->setHeader('Content-Type', 'application/xml');
		}
		$rest = $rest->getResponse();

		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::putBucket({$bucket}, {$acl}, {$location}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		return true;
	}


	/**
	* Delete an empty bucket
	*
	* @param string $bucket Bucket name
	* @return boolean
	*/
	public static function deleteBucket($bucket) {
		$rest = new S3Request('DELETE', $bucket);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 204)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::deleteBucket({$bucket}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		return true;
	}


	/**
	* Create input info array for putObject()
	*
	* @param string $file Input file
	* @param mixed $md5sum Use MD5 hash (supply a string if you want to use your own)
	* @return array | false
	*/
	public static function inputFile($file, $md5sum = true) {
		if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
			throw new S3Exception('S3::inputFile(): Unable to open input file: '.$file);
			return false;
		}
		return array('file' => $file, 'size' => filesize($file),
		'md5sum' => $md5sum !== false ? (is_string($md5sum) ? $md5sum :
		base64_encode(md5_file($file, true))) : '');
	}


	/**
	* Create input array info for putObject() with a resource
	*
	* @param string $resource Input resource to read from
	* @param integer $bufferSize Input byte size
	* @param string $md5sum MD5 hash to send (optional)
	* @return array | false
	*/
	public static function inputResource(&$resource, $bufferSize, $md5sum = '') {
		if (!is_resource($resource) || $bufferSize < 0) {
			throw new S3Exception('S3::inputResource(): Invalid resource or buffer size');
			return false;
		}
		$input = array('size' => $bufferSize, 'md5sum' => $md5sum);
		$input['fp'] =& $resource;
		return $input;
	}


	/**
	* Put an object
	*
	* @param mixed $input Input data
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param array $requestHeaders Array of request headers or content type as a string
	* @return boolean
	*/
	public static function putObject($input, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $requestHeaders = array()) {
		if ($input === false) return false;
		$rest = new S3Request('PUT', $bucket, $uri);

		if (is_string($input)) $input = array(
			'data' => $input, 'size' => strlen($input),
			'md5sum' => base64_encode(md5($input, true))
		);

		// Data
		if (isset($input['fp']))
			$rest->fp =& $input['fp'];
		elseif (isset($input['file']))
			$rest->fp = @fopen($input['file'], 'rb');
		elseif (isset($input['data']))
			$rest->data = $input['data'];

		// Content-Length (required)
		if (isset($input['size']) && $input['size'] >= 0)
			$rest->size = $input['size'];
		else {
			if (isset($input['file']))
				$rest->size = filesize($input['file']);
			elseif (isset($input['data']))
				$rest->size = strlen($input['data']);
		}

		// Custom request headers (Content-Type, Content-Disposition, Content-Encoding)
		if (is_array($requestHeaders))
			foreach ($requestHeaders as $h => $v) $rest->setHeader($h, $v);
		elseif (is_string($requestHeaders)) // Support for legacy contentType parameter
			$input['type'] = $requestHeaders;

		// Content-Type
		if (!isset($input['type'])) {
			if (isset($requestHeaders['Content-Type']))
				$input['type'] =& $requestHeaders['Content-Type'];
			elseif (isset($input['file']))
				$input['type'] = self::__getMimeType($input['file']);
			else
				$input['type'] = 'application/octet-stream';
		}

		// We need to post with Content-Length and Content-Type, MD5 is optional
		if ($rest->size >= 0 && ($rest->fp !== false || $rest->data !== false)) {
			$rest->setHeader('Content-Type', $input['type']);
			if (isset($input['md5sum'])) $rest->setHeader('Content-MD5', $input['md5sum']);

			$rest->setAmzHeader('x-amz-acl', $acl);
			foreach ($metaHeaders as $h => $v) $rest->setAmzHeader('x-amz-meta-'.$h, $v);
			$rest->getResponse();
		} else
			$rest->response->error = array('code' => 0, 'message' => 'Missing input parameters');

		if ($rest->response->error === false && $rest->response->code !== 200)
			$rest->response->error = array('code' => $rest->response->code, 'message' => 'Unexpected HTTP status');
		if ($rest->response->error !== false) {
			throw new S3Exception(sprintf("S3::putObject(): [%s] %s", $rest->response->error['code'], $rest->response->error['message']));
			return false;
		}
		return true;
	}


	/**
	* Put an object from a file (legacy function)
	*
	* @param string $file Input file path
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param string $contentType Content type
	* @return boolean
	*/
	public static function putObjectFile($file, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = null) {
		return self::putObject(self::inputFile($file), $bucket, $uri, $acl, $metaHeaders, $contentType);
	}


	/**
	* Put an object from a string (legacy function)
	*
	* @param string $string Input data
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param string $contentType Content type
	* @return boolean
	*/
	public static function putObjectString($string, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = 'text/plain') {
		return self::putObject($string, $bucket, $uri, $acl, $metaHeaders, $contentType);
	}


	/**
	* Get an object
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param mixed $saveTo Filename or resource to write to
	* @return mixed
	*/
	public static function getObject($bucket, $uri, $saveTo = false, $from = null, $to = null) {
		$rest = new S3Request('GET', $bucket, $uri);
		if ($saveTo !== false) {
			if (is_resource($saveTo))
				$rest->fp =& $saveTo;
			else
				if (($rest->fp = @fopen($saveTo, 'wb')) !== false)
					$rest->file = realpath($saveTo);
				else
					$rest->response->error = array('code' => 0, 'message' => 'Unable to open save file for writing: '.$saveTo);
		}
		if ($rest->response->error === false) {
			// Set the range header
			if(!is_null($from) && !is_null($to))
			{
				$rest->setHeader('Range',"bytes=$from-$to");
			}
			$rest->getResponse();
		}

		if ($rest->response->error === false && (($rest->response->code !== 200) && ($rest->response->code !== 206)))
			$rest->response->error = array('code' => $rest->response->code, 'message' => 'Unexpected HTTP status');
		if ($rest->response->error !== false) {
			throw new S3Exception(sprintf("S3::getObject({$bucket}, {$uri}): [%s] %s",
			$rest->response->error['code'], $rest->response->error['message']));
			return false;
		}
		return $rest->response;
	}


	/**
	* Get object information
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param boolean $returnInfo Return response information
	* @return mixed | false
	*/
	public static function getObjectInfo($bucket, $uri, $returnInfo = true) {
		$rest = new S3Request('HEAD', $bucket, $uri);
		$rest = $rest->getResponse();
		if ($rest->error === false && ($rest->code !== 200 && $rest->code !== 404))
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::getObjectInfo({$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		return $rest->code == 200 ? ($returnInfo ? $rest->headers : true) : false;
	}


	/**
	* Copy an object
	*
	* @param string $bucket Source bucket name
	* @param string $uri Source object URI
	* @param string $bucket Destination bucket name
	* @param string $uri Destination object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Optional array of x-amz-meta-* headers
	* @param array $requestHeaders Optional array of request headers (content type, disposition, etc.)
	* @return mixed | false
	*/
	public static function copyObject($srcBucket, $srcUri, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $requestHeaders = array()) {
		$rest = new S3Request('PUT', $bucket, $uri);
		$rest->setHeader('Content-Length', 0);
		foreach ($requestHeaders as $h => $v) $rest->setHeader($h, $v);
		foreach ($metaHeaders as $h => $v) $rest->setAmzHeader('x-amz-meta-'.$h, $v);
		$rest->setAmzHeader('x-amz-acl', $acl);
		$rest->setAmzHeader('x-amz-copy-source', sprintf('/%s/%s', $srcBucket, $srcUri));
		if (sizeof($requestHeaders) > 0 || sizeof($metaHeaders) > 0)
			$rest->setAmzHeader('x-amz-metadata-directive', 'REPLACE');
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::copyObject({$srcBucket}, {$srcUri}, {$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		return isset($rest->body->LastModified, $rest->body->ETag) ? array(
			'time' => strtotime((string)$rest->body->LastModified),
			'hash' => substr((string)$rest->body->ETag, 1, -1)
		) : false;
	}


	/**
	* Set logging for a bucket
	*
	* @param string $bucket Bucket name
	* @param string $targetBucket Target bucket (where logs are stored)
	* @param string $targetPrefix Log prefix (e,g; domain.com-)
	* @return boolean
	*/
	public static function setBucketLogging($bucket, $targetBucket, $targetPrefix = null) {
		// The S3 log delivery group has to be added to the target bucket's ACP
		if ($targetBucket !== null && ($acp = self::getAccessControlPolicy($targetBucket, '')) !== false) {
			// Only add permissions to the target bucket when they do not exist
			$aclWriteSet = false;
			$aclReadSet = false;
			foreach ($acp['acl'] as $acl)
			if ($acl['type'] == 'Group' && $acl['uri'] == 'http://acs.amazonaws.com/groups/s3/LogDelivery') {
				if ($acl['permission'] == 'WRITE') $aclWriteSet = true;
				elseif ($acl['permission'] == 'READ_ACP') $aclReadSet = true;
			}
			if (!$aclWriteSet) $acp['acl'][] = array(
				'type' => 'Group', 'uri' => 'http://acs.amazonaws.com/groups/s3/LogDelivery', 'permission' => 'WRITE'
			);
			if (!$aclReadSet) $acp['acl'][] = array(
				'type' => 'Group', 'uri' => 'http://acs.amazonaws.com/groups/s3/LogDelivery', 'permission' => 'READ_ACP'
			);
			if (!$aclReadSet || !$aclWriteSet) self::setAccessControlPolicy($targetBucket, '', $acp);
		}

		$dom = new DOMDocument;
		$bucketLoggingStatus = $dom->createElement('BucketLoggingStatus');
		$bucketLoggingStatus->setAttribute('xmlns', 'http://s3.amazonaws.com/doc/2006-03-01/');
		if ($targetBucket !== null) {
			if ($targetPrefix == null) $targetPrefix = $bucket . '-';
			$loggingEnabled = $dom->createElement('LoggingEnabled');
			$loggingEnabled->appendChild($dom->createElement('TargetBucket', $targetBucket));
			$loggingEnabled->appendChild($dom->createElement('TargetPrefix', $targetPrefix));
			// TODO: Add TargetGrants?
			$bucketLoggingStatus->appendChild($loggingEnabled);
		}
		$dom->appendChild($bucketLoggingStatus);

		$rest = new S3Request('PUT', $bucket, '');
		$rest->setParameter('logging', null);
		$rest->data = $dom->saveXML();
		$rest->size = strlen($rest->data);
		$rest->setHeader('Content-Type', 'application/xml');
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::setBucketLogging({$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		return true;
	}


	/**
	* Get logging status for a bucket
	*
	* This will return false if logging is not enabled.
	* Note: To enable logging, you also need to grant write access to the log group
	*
	* @param string $bucket Bucket name
	* @return array | false
	*/
	public static function getBucketLogging($bucket) {
		$rest = new S3Request('GET', $bucket, '');
		$rest->setParameter('logging', null);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::getBucketLogging({$bucket}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		if (!isset($rest->body->LoggingEnabled)) return false; // No logging
		return array(
			'targetBucket' => (string)$rest->body->LoggingEnabled->TargetBucket,
			'targetPrefix' => (string)$rest->body->LoggingEnabled->TargetPrefix,
		);
	}


	/**
	* Disable bucket logging
	*
	* @param string $bucket Bucket name
	* @return boolean
	*/
	public static function disableBucketLogging($bucket) {
		return self::setBucketLogging($bucket, null);
	}


	/**
	* Get a bucket's location
	*
	* @param string $bucket Bucket name
	* @return string | false
	*/
	public static function getBucketLocation($bucket) {
		$rest = new S3Request('GET', $bucket, '');
		$rest->setParameter('location', null);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::getBucketLocation({$bucket}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		return (isset($rest->body[0]) && (string)$rest->body[0] !== '') ? (string)$rest->body[0] : 'US';
	}


	/**
	* Set object or bucket Access Control Policy
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param array $acp Access Control Policy Data (same as the data returned from getAccessControlPolicy)
	* @return boolean
	*/
	public static function setAccessControlPolicy($bucket, $uri = '', $acp = array()) {
		$dom = new DOMDocument;
		$dom->formatOutput = true;
		$accessControlPolicy = $dom->createElement('AccessControlPolicy');
		$accessControlList = $dom->createElement('AccessControlList');

		// It seems the owner has to be passed along too
		$owner = $dom->createElement('Owner');
		$owner->appendChild($dom->createElement('ID', $acp['owner']['id']));
		$owner->appendChild($dom->createElement('DisplayName', $acp['owner']['name']));
		$accessControlPolicy->appendChild($owner);

		foreach ($acp['acl'] as $g) {
			$grant = $dom->createElement('Grant');
			$grantee = $dom->createElement('Grantee');
			$grantee->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
			if (isset($g['id'])) { // CanonicalUser (DisplayName is omitted)
				$grantee->setAttribute('xsi:type', 'CanonicalUser');
				$grantee->appendChild($dom->createElement('ID', $g['id']));
			} elseif (isset($g['email'])) { // AmazonCustomerByEmail
				$grantee->setAttribute('xsi:type', 'AmazonCustomerByEmail');
				$grantee->appendChild($dom->createElement('EmailAddress', $g['email']));
			} elseif ($g['type'] == 'Group') { // Group
				$grantee->setAttribute('xsi:type', 'Group');
				$grantee->appendChild($dom->createElement('URI', $g['uri']));
			}
			$grant->appendChild($grantee);
			$grant->appendChild($dom->createElement('Permission', $g['permission']));
			$accessControlList->appendChild($grant);
		}

		$accessControlPolicy->appendChild($accessControlList);
		$dom->appendChild($accessControlPolicy);

		$rest = new S3Request('PUT', $bucket, $uri);
		$rest->setParameter('acl', null);
		$rest->data = $dom->saveXML();
		$rest->size = strlen($rest->data);
		$rest->setHeader('Content-Type', 'application/xml');
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::setAccessControlPolicy({$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		return true;
	}


	/**
	* Get object or bucket Access Control Policy
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @return mixed | false
	*/
	public static function getAccessControlPolicy($bucket, $uri = '') {
		$rest = new S3Request('GET', $bucket, $uri);
		$rest->setParameter('acl', null);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::getAccessControlPolicy({$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}

		$acp = array();
		if (isset($rest->body->Owner, $rest->body->Owner->ID, $rest->body->Owner->DisplayName)) {
			$acp['owner'] = array(
				'id' => (string)$rest->body->Owner->ID, 'name' => (string)$rest->body->Owner->DisplayName
			);
		}
		if (isset($rest->body->AccessControlList)) {
			$acp['acl'] = array();
			foreach ($rest->body->AccessControlList->Grant as $grant) {
				foreach ($grant->Grantee as $grantee) {
					if (isset($grantee->ID, $grantee->DisplayName)) // CanonicalUser
						$acp['acl'][] = array(
							'type' => 'CanonicalUser',
							'id' => (string)$grantee->ID,
							'name' => (string)$grantee->DisplayName,
							'permission' => (string)$grant->Permission
						);
					elseif (isset($grantee->EmailAddress)) // AmazonCustomerByEmail
						$acp['acl'][] = array(
							'type' => 'AmazonCustomerByEmail',
							'email' => (string)$grantee->EmailAddress,
							'permission' => (string)$grant->Permission
						);
					elseif (isset($grantee->URI)) // Group
						$acp['acl'][] = array(
							'type' => 'Group',
							'uri' => (string)$grantee->URI,
							'permission' => (string)$grant->Permission
						);
					else continue;
				}
			}
		}
		return $acp;
	}


	/**
	* Delete an object
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @return boolean
	*/
	public static function deleteObject($bucket, $uri) {
		$rest = new S3Request('DELETE', $bucket, $uri);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 204)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::deleteObject(): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		return true;
	}


	/**
	* Get a query string authenticated URL
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param integer $lifetime Lifetime in seconds
	* @param boolean $hostBucket Use the bucket name as the hostname
	* @param boolean $https Use HTTPS ($hostBucket should be false for SSL verification)
	* @return string
	*/
	public static function getAuthenticatedURL($bucket, $uri, $lifetime, $hostBucket = false, $https = false) {
		$expires = time() + $lifetime;
		$uri = str_replace('%2F', '/', rawurlencode($uri)); // URI should be encoded (thanks Sean O'Dea)
		return sprintf(($https ? 'https' : 'http').'://%s/%s?AWSAccessKeyId=%s&Expires=%u&Signature=%s',
		$hostBucket ? $bucket : $bucket.'.s3.amazonaws.com', $uri, self::$__accessKey, $expires,
		urlencode(self::__getHash("GET\n\n\n{$expires}\n/{$bucket}/{$uri}")));
	}

	/**
	* Get upload POST parameters for form uploads
	*
	* @param string $bucket Bucket name
	* @param string $uriPrefix Object URI prefix
	* @param constant $acl ACL constant
	* @param integer $lifetime Lifetime in seconds
	* @param integer $maxFileSize Maximum filesize in bytes (default 5MB)
	* @param string $successRedirect Redirect URL or 200 / 201 status code
	* @param array $amzHeaders Array of x-amz-meta-* headers
	* @param array $headers Array of request headers or content type as a string
	* @param boolean $flashVars Includes additional "Filename" variable posted by Flash
	* @return object
	*/
	public static function getHttpUploadPostParams($bucket, $uriPrefix = '', $acl = self::ACL_PRIVATE, $lifetime = 3600, $maxFileSize = 5242880, $successRedirect = "201", $amzHeaders = array(), $headers = array(), $flashVars = false) {
		// Create policy object
		$policy = new stdClass;
		$policy->expiration = gmdate('Y-m-d\TH:i:s\Z', (time() + $lifetime));
		$policy->conditions = array();
		$obj = new stdClass; $obj->bucket = $bucket; array_push($policy->conditions, $obj);
		$obj = new stdClass; $obj->acl = $acl; array_push($policy->conditions, $obj);

		$obj = new stdClass; // 200 for non-redirect uploads
		if (is_numeric($successRedirect) && in_array((int)$successRedirect, array(200, 201)))
			$obj->success_action_status = (string)$successRedirect;
		else // URL
			$obj->success_action_redirect = $successRedirect;
		array_push($policy->conditions, $obj);

		array_push($policy->conditions, array('starts-with', '$key', $uriPrefix));
		if ($flashVars) array_push($policy->conditions, array('starts-with', '$Filename', ''));
		foreach (array_keys($headers) as $headerKey)
			array_push($policy->conditions, array('starts-with', '$'.$headerKey, ''));
		foreach ($amzHeaders as $headerKey => $headerVal) {
			$obj = new stdClass; $obj->{$headerKey} = (string)$headerVal; array_push($policy->conditions, $obj);
		}
		array_push($policy->conditions, array('content-length-range', 0, $maxFileSize));
		$policy = base64_encode(str_replace('\/', '/', json_encode($policy)));
	
		// Create parameters
		$params = new stdClass;
		$params->AWSAccessKeyId = self::$__accessKey;
		$params->key = $uriPrefix.'${filename}';
		$params->acl = $acl;
		$params->policy = $policy; unset($policy);
		$params->signature = self::__getHash($params->policy);
		if (is_numeric($successRedirect) && in_array((int)$successRedirect, array(200, 201)))
			$params->success_action_status = (string)$successRedirect;
		else
			$params->success_action_redirect = $successRedirect;
		foreach ($headers as $headerKey => $headerVal) $params->{$headerKey} = (string)$headerVal;
		foreach ($amzHeaders as $headerKey => $headerVal) $params->{$headerKey} = (string)$headerVal;
		return $params;
	}

	/**
	* Create a CloudFront distribution
	*
	* @param string $bucket Bucket name
	* @param boolean $enabled Enabled (true/false)
	* @param array $cnames Array containing CNAME aliases
	* @param string $comment Use the bucket name as the hostname
	* @return array | false
	*/
	public static function createDistribution($bucket, $enabled = true, $cnames = array(), $comment = '') {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('POST', '', '2008-06-30/distribution', 'cloudfront.amazonaws.com');
		$rest->data = self::__getCloudFrontDistributionConfigXML($bucket.'.s3.amazonaws.com', $enabled, $comment, (string)microtime(true), $cnames);
		$rest->size = strlen($rest->data);
		$rest->setHeader('Content-Type', 'application/xml');
		$rest = self::__getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 201)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::createDistribution({$bucket}, ".(int)$enabled.", '$comment'): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		} elseif ($rest->body instanceof SimpleXMLElement)
			return self::__parseCloudFrontDistributionConfig($rest->body);
		return false;
	}


	/**
	* Get CloudFront distribution info
	*
	* @param string $distributionId Distribution ID from listDistributions()
	* @return array | false
	*/
	public static function getDistribution($distributionId) {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('GET', '', '2008-06-30/distribution/'.$distributionId, 'cloudfront.amazonaws.com');
		$rest = self::__getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::getDistribution($distributionId): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		} elseif ($rest->body instanceof SimpleXMLElement) {
			$dist = self::__parseCloudFrontDistributionConfig($rest->body);
			$dist['hash'] = $rest->headers['hash'];
			return $dist;
		}
		return false;
	}


	/**
	* Update a CloudFront distribution
	*
	* @param array $dist Distribution array info identical to output of getDistribution()
	* @return array | false
	*/
	public static function updateDistribution($dist) {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('PUT', '', '2008-06-30/distribution/'.$dist['id'].'/config', 'cloudfront.amazonaws.com');
		$rest->data = self::__getCloudFrontDistributionConfigXML($dist['origin'], $dist['enabled'], $dist['comment'], $dist['callerReference'], $dist['cnames']);
		$rest->size = strlen($rest->data);
		$rest->setHeader('If-Match', $dist['hash']);
		$rest = self::__getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::updateDistribution({$dist['id']}, ".(int)$enabled.", '$comment'): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		} else {
			$dist = self::__parseCloudFrontDistributionConfig($rest->body);
			$dist['hash'] = $rest->headers['hash'];
			return $dist;
		}
		return false;
	}


	/**
	* Delete a CloudFront distribution
	*
	* @param array $dist Distribution array info identical to output of getDistribution()
	* @return boolean
	*/
	public static function deleteDistribution($dist) {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('DELETE', '', '2008-06-30/distribution/'.$dist['id'], 'cloudfront.amazonaws.com');
		$rest->setHeader('If-Match', $dist['hash']);
		$rest = self::__getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 204)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::deleteDistribution({$dist['id']}): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		}
		return true;
	}


	/**
	* Get a list of CloudFront distributions
	*
	* @return array
	*/
	public static function listDistributions() {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('GET', '', '2008-06-30/distribution', 'cloudfront.amazonaws.com');
		$rest = self::__getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			throw new S3Exception(sprintf("S3::listDistributions(): [%s] %s",
			$rest->error['code'], $rest->error['message']));
			return false;
		} elseif ($rest->body instanceof SimpleXMLElement && isset($rest->body->DistributionSummary)) {
			$list = array();
			if (isset($rest->body->Marker, $rest->body->MaxItems, $rest->body->IsTruncated)) {
				//$info['marker'] = (string)$rest->body->Marker;
				//$info['maxItems'] = (int)$rest->body->MaxItems;
				//$info['isTruncated'] = (string)$rest->body->IsTruncated == 'true' ? true : false;
			}
			foreach ($rest->body->DistributionSummary as $summary) {
				$list[(string)$summary->Id] = self::__parseCloudFrontDistributionConfig($summary);
			}
			return $list;
		}
		return array();
	}


	/**
	* Get a DistributionConfig DOMDocument
	*
	* @internal Used to create XML in createDistribution() and updateDistribution()
	* @param string $bucket Origin bucket
	* @param boolean $enabled Enabled (true/false)
	* @param string $comment Comment to append
	* @param string $callerReference Caller reference
	* @param array $cnames Array of CNAME aliases
	* @return string
	*/
	private static function __getCloudFrontDistributionConfigXML($bucket, $enabled, $comment, $callerReference = '0', $cnames = array()) {
		$dom = new DOMDocument('1.0', 'UTF-8');
		$dom->formatOutput = true;
		$distributionConfig = $dom->createElement('DistributionConfig');
		$distributionConfig->setAttribute('xmlns', 'http://cloudfront.amazonaws.com/doc/2008-06-30/');
		$distributionConfig->appendChild($dom->createElement('Origin', $bucket));
		$distributionConfig->appendChild($dom->createElement('CallerReference', $callerReference));
		foreach ($cnames as $cname)
			$distributionConfig->appendChild($dom->createElement('CNAME', $cname));
		if ($comment !== '') $distributionConfig->appendChild($dom->createElement('Comment', $comment));
		$distributionConfig->appendChild($dom->createElement('Enabled', $enabled ? 'true' : 'false'));
		$dom->appendChild($distributionConfig);
		return $dom->saveXML();
	}


	/**
	* Parse a CloudFront distribution config
	*
	* @internal Used to parse the CloudFront DistributionConfig node to an array
	* @param object &$node DOMNode
	* @return array
	*/
	private static function __parseCloudFrontDistributionConfig(&$node) {
		$dist = array();
		if (isset($node->Id, $node->Status, $node->LastModifiedTime, $node->DomainName)) {
			$dist['id'] = (string)$node->Id;
			$dist['status'] = (string)$node->Status;
			$dist['time'] = strtotime((string)$node->LastModifiedTime);
			$dist['domain'] = (string)$node->DomainName;
		}
		if (isset($node->CallerReference))
			$dist['callerReference'] = (string)$node->CallerReference;
		if (isset($node->Comment))
			$dist['comment'] = (string)$node->Comment;
		if (isset($node->Enabled, $node->Origin)) {
			$dist['origin'] = (string)$node->Origin;
			$dist['enabled'] = (string)$node->Enabled == 'true' ? true : false;
		} elseif (isset($node->DistributionConfig)) {
			$dist = array_merge($dist, self::__parseCloudFrontDistributionConfig($node->DistributionConfig));
		}
		if (isset($node->CNAME)) {
			$dist['cnames'] = array();
			foreach ($node->CNAME as $cname) $dist['cnames'][(string)$cname] = (string)$cname;
		}
		return $dist;
	}


	/**
	* Grab CloudFront response
	*
	* @internal Used to parse the CloudFront S3Request::getResponse() output
	* @param object &$rest S3Request instance
	* @return object
	*/
	private static function __getCloudFrontResponse(&$rest) {
		$rest->getResponse();
		if ($rest->response->error === false && isset($rest->response->body) &&
		is_string($rest->response->body) && substr($rest->response->body, 0, 5) == '<?xml') {
			$rest->response->body = simplexml_load_string($rest->response->body);
			// Grab CloudFront errors
			if (isset($rest->response->body->Error, $rest->response->body->Error->Code,
			$rest->response->body->Error->Message)) {
				$rest->response->error = array(
					'code' => (string)$rest->response->body->Error->Code,
					'message' => (string)$rest->response->body->Error->Message
				);
				unset($rest->response->body);
			}
		}
		return $rest->response;
	}


	/**
	* Get MIME type for file
	*
	* @internal Used to get mime types
	* @param string &$file File path
	* @return string
	*/
	public static function __getMimeType(&$file) {
		$type = false;
		// Fileinfo documentation says fileinfo_open() will use the
		// MAGIC env var for the magic file
		if (extension_loaded('fileinfo') && isset($_ENV['MAGIC']) &&
		($finfo = finfo_open(FILEINFO_MIME, $_ENV['MAGIC'])) !== false) {
			if (($type = finfo_file($finfo, $file)) !== false) {
				// Remove the charset and grab the last content-type
				$type = explode(' ', str_replace('; charset=', ';charset=', $type));
				$type = array_pop($type);
				$type = explode(';', $type);
				$type = trim(array_shift($type));
			}
			finfo_close($finfo);

		// If anyone is still using mime_content_type()
		} elseif (function_exists('mime_content_type'))
			$type = trim(mime_content_type($file));

		if ($type !== false && strlen($type) > 0) return $type;

		// Otherwise do it the old fashioned way
		static $exts = array(
			'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png',
			'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'ico' => 'image/x-icon',
			'swf' => 'application/x-shockwave-flash', 'pdf' => 'application/pdf',
			'zip' => 'application/zip', 'gz' => 'application/x-gzip',
			'tar' => 'application/x-tar', 'bz' => 'application/x-bzip',
			'bz2' => 'application/x-bzip2', 'txt' => 'text/plain',
			'asc' => 'text/plain', 'htm' => 'text/html', 'html' => 'text/html',
			'css' => 'text/css', 'js' => 'text/javascript',
			'xml' => 'text/xml', 'xsl' => 'application/xsl+xml',
			'ogg' => 'application/ogg', 'mp3' => 'audio/mpeg', 'wav' => 'audio/x-wav',
			'avi' => 'video/x-msvideo', 'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg',
			'mov' => 'video/quicktime', 'flv' => 'video/x-flv', 'php' => 'text/x-php'
		);
		$ext = strtolower(pathInfo($file, PATHINFO_EXTENSION));
		return isset($exts[$ext]) ? $exts[$ext] : 'application/octet-stream';
	}


	/**
	* Generate the auth string: "AWS AccessKey:Signature"
	*
	* @internal Used by S3Request::getResponse()
	* @param string $string String to sign
	* @return string
	*/
	public static function __getSignature($string) {
		return 'AWS '.self::$__accessKey.':'.self::__getHash($string);
	}


	/**
	* Creates a HMAC-SHA1 hash
	*
	* This uses the hash extension if loaded
	*
	* @internal Used by __getSignature()
	* @param string $string String to sign
	* @return string
	*/
	private static function __getHash($string) {
		return base64_encode(extension_loaded('hash') ?
		hash_hmac('sha1', $string, self::$__secretKey, true) : pack('H*', sha1(
		(str_pad(self::$__secretKey, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
		pack('H*', sha1((str_pad(self::$__secretKey, 64, chr(0x00)) ^
		(str_repeat(chr(0x36), 64))) . $string)))));
	}

}

/**
 * A high-level API abstraction for the Amazon S3 adapter
 */
class S3Engine
{
	private $accessKey = '';
	private $secretKey = '';
	private $useSSL = false;
	private $bucket = '';
	
	private $_lastDirectory = null;
	private $lastListing = null;
	
	private $_isConfigured = false;
	
	/**
	 * Creates and configures the engine class
	 * 
	 * @param array $config 
	 */
	public function __construct($config)
	{
		$configParams = array('accessKey','secretKey','useSSL','bucket');
		foreach($configParams as $param) {
			if(array_key_exists($param, $config)) $this->$param = $config[$param];
		}
		
		S3Adapter::setAuth($this->accessKey, $this->secretKey);
		S3Adapter::$useSSL = $this->useSSL;
		
		$this->_isConfigured = true;
	}
	
	/**
	 * Lists the files in a directory. Each file record is an array consisting
	 * of the following keys: filename, time, size.
	 * 
	 * @param string $directory Directory relative to the bucket's root
	 * @param bool $useCache (optional, def. true) When true, S3Engine "remembers" the contents of the last directory and if you ask to list it again it will not contact S3.
	 * @return array 
	 */
	public function getFiles($directory, $useCache = true)
	{
		// name, time, size, hash
		if(!$this->_isConfigured) {
			throw new S3Exception(__CLASS__.' is not configured yet');
		}
		$everything = $this->_listContents($directory, $useCache);
		
		if($directory != '/') {
			$directory = trim($directory,'/').'/';
		}
		
		$files = array();
		$dirLength = strlen($directory);
		
		if(count($everything)) foreach($everything as $path => $info) {
			if(array_key_exists('size', $info) && (substr($path, -1) != '/')) {
				if(substr($path, 0, $dirLength) == $directory) {
					$path = substr($path, $dirLength);
				}
				$path = trim($path,'/');
				$files[] = array(
					'filename'	=> $path,
					'time'		=> $info['time'],
					'size'		=> $info['size']
				);
			}
		}
		return $files;
	}
	
	/**
	 * Lists the folders in a directory.
	 * 
	 * @param string $directory Directory relative to the bucket's root
	 * @param bool $useCache (optional, def. true) When true, S3Engine "remembers" the contents of the last directory and if you ask to list it again it will not contact S3.
	 * @return array List of folders
	 */
	public function getFolders($directory, $useCache = true)
	{
		if(!$this->_isConfigured) {
			throw new S3Exception(__CLASS__.' is not configured yet');
		}
		
		if($directory != '/') {
			$directory = trim($directory,'/').'/';
		}
		
		$everything = $this->_listContents($directory, $useCache);
		
		$folders = array();
		$dirLength = strlen($directory);
		if(count($everything)) foreach($everything as $path => $info) {
			if(!array_key_exists('size', $info) && (substr($path, -1) == '/')) {
				if(substr($path, 0, $dirLength) == $directory) {
					$path = substr($path, $dirLength);
				}
				$path = trim($path,'/');
				$folders[] = $path;
			}
		}
		return $folders;
	}
	
	/**
	 * Lists all the buckets owned by the current user
	 * 
	 * @param bool $useCache If true, subsequent calls will not cause an Amazon S3 API call
	 * @return array List of bucket names
	 */
	public function listBuckets($useCache = true)
	{
		static $buckets = null;
		
		if(is_null($buckets) || !$useCache) {
			$buckets = S3Adapter::listBuckets(false);
		}
		
		return $buckets;
	}
	
	/**
	 * Change the active bucket
	 * 
	 * @param string $bucket 
	 */
	public function setBucket($bucket)
	{
		$this->bucket = $bucket;
	}
	
	/**
	 * Return the number of parts you need to download a given file
	 * 
	 * @param string $file The file you want
	 * @param int $partSize Part size, in bytes
	 */
	public function partsForFile($file, $partSize = 524378)
	{
		$file = trim($file,'/');
		$info = S3Adapter::getObjectInfo($this->bucket, $file, true);
		if($info === false) {
			throw new S3Exception('File not found in S3 bucket', 404);
		}
		$size = $info['size'];
		
		return ceil($size / $partSize);
	}
	
	/**
	 * Downloads a part of a file to disk. If it's the first part, the file is
	 * created afresh. If it is any other part, it is appended to the target file.
	 * 
	 * @param string $file The path of the S3 object (file) to download, relative to the bucket
	 * @param string $target Absolute path of the file to be written to
	 * @param int $part The part to download, default 1, up to the max number of parts for this object
	 * @param int $partSize Part size, in bytes, default is 512Kb
	 */
	public function downloadPart($file, $target, $part = 1, $partSize = 524378)
	{
		if($part < 1) throw new S3Exception('Invalid part number '.$part);
		$parts = $this->partsForFile($file, $partSize);
		if($part > $parts) throw new S3Exception('Invalid part number '.$part);
		
		if($part == 1) {
			$fp = @fopen($target, 'wb');
		} else {
			$fp = @fopen($target, 'ab');
		}
		if($fp === false) throw new S3Exception("Can not open $target for writing; download failed");
		
		$from = $partSize*($part-1);
		$to = $from + $partSize - 1;
		
		$file = trim($file,'/');
		$data = S3Adapter::getObject($this->bucket, $file, false, $from, $to);
		
		if($data === false) {
			throw new S3Exception('Unspecified error downloading '.$file);
		}
		
		fwrite($fp, $data->body);
		unset($data);
		
		fclose($fp);
	}
	
	/**
	 * Internal function to list the contents of a directory inside a bucket. To
	 * list the contents of the bucket's root, use a directory value of '/' or
	 * null.
	 * 
	 * @param string|null $directory The directory to list
	 * @param bool $useCache If true (default), repeated requests with the same directory do not result in Amazon S3 API calls
	 * @return array
	 */
	private function _listContents($directory = null, $useCache = true)
	{
		if( ($this->_lastDirectory != $directory) || !$useCache ) {
			if($directory == '/') {
				$directory = null;
			} else {
				$directory = trim($directory,'/').'/';
			}
			$this->lastListing = S3Adapter::getBucket($this->bucket, $directory, null, null, '/', true);
		}
		return $this->lastListing;
	}
}
// --- Amazon S3 suppport - END

function echoCSS() {
	echo <<<ENDCSS
body {
	font-family: "MgOpen Moderna", Calibri, Helvetica, sans-serif;
	font-size: 10pt;
	margin: 0;
	padding: 0;
}

#page-container {
	margin: 1em;
	background: #f9f9f9;
	-moz-border-radius: 8px;
	-webkit-border-radius: 8px;
	border: 2px solid #000000;
}

#header {
	font-size: 18pt;
	font-weight: bold;
	font-style: italic;
	color: #233b53;
	text-shadow: 1px 1px 4px #99f;
	border-bottom: 2px solid #333;
	padding: 0.3em;
	margin-bottom: 0.5em;
	background-color: #ccf;
	-moz-border-radius-topleft: 8px;
	-moz-border-radius-topright: 8px;
	-webkit-border-top-left-radius: 8px;
	-webkit-border-top-right-radius: 8px;
}

#footer {
	font-size: 8pt;
	color: #233b53;
	text-align: center;
	border-top: 2px solid #333;
	padding: 0.3em;
	margin-top: 0.5em;
	background-color: #ccf;
	-moz-border-radius-bottomleft: 8px;
	-moz-border-radius-bottomright: 8px;
	-webkit-border-bottom-left-radius: 8px;
	-webkit-border-bottom-right-radius: 8px;
	clear: both;
}

#error {
	display: none;
	margin: 2em;
	border: 2px solid #cc0000;
	background-color: #fff8ad;
	color: #990000;
	padding: 0.5em;
	-moz-border-radius: 8px;
	-webkit-border-radius: 8px;
	-webkit-box-shadow: 5px 5px 5px #cbb;
}

#error h3 {
	margin: 0;
	padding: 0;
	font-size: 12pt;
}

.clr {
	clear: both;
}

.circle {
	display: block;
	position: relative;
	float: left;
	top: 0.1em;
	-moz-border-radius: 2em;
	-webkit-border-radius: 2em;
	width: 0.6em;
	height: 1em;
	border: 2px solid #333;
	font-size: 14pt;
	font-weight: bold;
	padding: 0.1em 0.35em 0.2em 0.25em;
	background-color: navy;
	color: white;
	margin: 0.2em 0.5em 0 0.2em;
	-webkit-box-shadow: 0px 0px 6px #99f;
}

.area-container {
	padding: 0.2em 0.5em 0.2em 2.3em;
	background: white;
}

h2 {
	font-weight: bold;
	font-size: 14pt;
	color: navy;
	text-shadow: 1px 1px 6px #99f;
	border-bottom: 2px solid navy;
	border-top: 2px solid navy;
	padding: 0.3em 0 0.3em 0;
	margin: 0.2em;
	background: #f6f6ff;
}

label {
	display: inline-block;
	font-weight: bold;
	width: 25%;
	margin-bottom: 0.4em;
}

select, input {
	border: thin solid #333;
	width: 30%;
	height: 1.4em;
	background-color: #ffffe3;
}

input:focus, input:hover {
	background-color: #fffbb3;
	-webkit-box-shadow: 0px 0px 8px #ccc;
}

.button {
	display: inline-block;
	margin-left: 0.5em;
	font-size: 0.95em;
	font-weight: bold;
	border: thin solid #333;
	padding: 0.1em 0.3em;
	background: #e6e6ff;
	cursor: pointer;
	-moz-border-radius: 5px;
	-webkit-border-radius: 5px;
}

div.button {
	padding: 0.7em 1em;
	font-size: 150%;
	background: #33cc33;
}

span.button:hover {
	background-color: #f0f0ff;
}

#gobutton {
	display: block;
	float: left;
	font-size: 18pt;
	padding: 0.5em 3em;
	margin: 0;
	background: #33cc33;
	border: 2px solid #333;
	-moz-border-radius: 8px;
	-webkit-border-radius: 8px;
	cursor: pointer;
}

div.button:hover, #gobutton:hover {
	background-color: #00ff00;
}

#ftp-options {
	display: none;
}

.black_overlay{
	display: none;
	position: absolute;
	top: 0%;
	left: 0%;
	width: 100%;
	height: 100%;
	background-color: black;
	z-index:1001;
	-moz-opacity: 0.8;
	opacity:.80;
	filter: alpha(opacity=80);
}

.white_content {
	display: none;
	position: absolute;
	top: 12.5%;
	left: 12.5%;
	width: 75%;
	height: 75%;
	padding: 0.5em;
	-moz-border-radius: 8px;
	-webkit-border-radius: 8px;
	border: 4px solid #ccf;
	background-color: white;
	z-index:1002;
	overflow: auto;
}

#genericerror {
	background-color: #f0f000 !important;
	border: 4px solid #fcc !important;
}

#genericerrorInner {
	font-size: 110%;
	color: #33000;
}

#warn-not-close {
	padding: 0.2em 0.5em;
	text-align: center;
	background: #fcfc00;
	font-size: smaller;
	font-weight: bold;
}

#progressbar {
	display: block;
	width: 80%;
	height: 32px;
	border: thin solid black;
	margin: 1em 10% 0.2em;
	-moz-border-radius: 5px;
	-webkit-border-radius: 5px;
}

#progressbar-inner {
	display: block;
	width: 100%;
	height: 100%;
	background-color: #1e90ff;
	-moz-border-radius: 5px;
	-webkit-border-radius: 5px;
}

#currentFile {
	font-family: Consolas, "Courier New", Courier, monospace;
	font-size: 9pt;
	height: 10pt;
	overflow: hidden;
	text-overflow: ellipsis;
	background: #ccc;
	margin: 0 10% 1em;
}

#extractionComplete {
	margin: 0 0 2em 0;
}

#warningsContainer {
	border-bottom: 2px solid brown;
	border-left: 2px solid brown;
	border-right: 2px solid brown;
	padding: 5px 0;
	background: #ffffcc;
	-webkit-border-bottom-right-radius: 5px;
	-webkit-border-bottom-left-radius: 5px;
	-moz-border-radius-bottomleft: 5px;
	-moz-border-radius-bottomright: 5px;
}

#warningsHeader h2 {
	color: black;
	text-shadow: 2px 2px 5px #999999;
	border-top: 2px solid brown;
	border-left: 2px solid brown;
	border-right: 2px solid brown;
	border-bottom: thin solid brown;
	-webkit-border-top-right-radius: 5px;
	-webkit-border-top-left-radius: 5px;
	-moz-border-radius-topleft: 5px;
	-moz-border-radius-topright: 5px;
	background: yellow;
	font-size: large;
	padding: 2px 5px;
	margin: 0px;
}

#warnings {
	height: 200px;
	overflow-y: scroll;
}

#warnings div {
	background: #eeeeee;
	font-size: small;
	padding: 2px 4px;
	border-bottom: thin solid #333333;
}

#automode {
	display: inline-block;
	padding: 6pt 12pt;
	background-color: #cc0000;
	border: thick solid yellow;
	color: white;
	font-weight: bold;
	font-size: 125%;
	position: absolute;
	float: right;
	top: 1em;
	right: 1em;
}

.helpme {
	background: #ffff99;
	color: #333;
	margin: 1em;
	padding: 0.75em 0.5em;
	border-top: thick solid #990;
	border-bottom: thick solid #990;
	text-align: center;
}

#update-notification {
	margin: 1em;
	padding: 0.5em;
	background-color: #FF9;
	color: #F33;
	text-align: center;
	border-radius: 20px;
	border: medium solid red;
	box-shadow: 5px 5px 5px black;
}

.update-notify {
	font-size: 20pt;
	font-weight: bold;
}

.update-links {
	color: #333;
	font-size: 14pt;
}

#update-dlnow {
	text-decoration: none;
	color: #333;
	border: thin solid #333;
	padding: 0.5em;
	border-radius: 5px;
	background-color: #f0f0f0;
	text-shadow: 1px 1px 1px #999;
}

#update-dlnow:hover {
	background-color: #fff;
}

#update-whatsnew {
	font-size: 11pt;
	color: blue;
	text-decoration: underline;
}

.update-whyupdate {
	color: #333;
	font-size: 9pt;
}
ENDCSS;

}

function echoTranslationStrings()
{
	$translation =& AKText::getInstance();
	echo $translation->asJavascript();
}

function autoVar($key, $default = '')
{
	$automation = AKAutomation::getInstance();
	$vars = $automation->getSection('kickstart');
	if(array_key_exists($key, $vars))
	{
		return "'".addcslashes($vars[$key], "'\"\\")."'";
	}
	else
	{
		return "'".addcslashes($default, "'\"\\")."'";
	}
}

function selfURL(){
    if(!isset($_SERVER['REQUEST_URI'])){
        $serverrequri = $_SERVER['PHP_SELF'];
    }else{
        $serverrequri =    $_SERVER['REQUEST_URI'];
    }
    $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
    $protocol = strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
    $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
    return $protocol."://".$_SERVER['SERVER_NAME'].$port.$serverrequri;   
}

function strleft($s1, $s2) {
	return substr($s1, 0, strpos($s1, $s2));
}

function echoPage()
{
	$automation = AKAutomation::getInstance();
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Piwik Installer</title>
<style type="text/css" media="all" rel="stylesheet">
<?php echoCSS();?>
</style>
<?php if(function_exists('base64_decode')): ?>
<script type="text/javascript">
<?php
$data = <<<ENDDATA
KGZ1bmN0aW9uKEEsdyl7ZnVuY3Rpb24gbWEoKXtpZighYy5pc1JlYWR5KXt0cnl7cy5kb2N1bWVudEVsZW1lbnQuZG9TY3JvbGwoImxlZnQiKX1jYXRjaChhKXtzZXRUaW1lb3V0KG1hLDEpO3JldHVybn1jLnJlYWR5KCl9fWZ1bmN0aW9uIFFhKGEsYil7Yi5zcmM/Yy5hamF4KHt1cmw6Yi5zcmMsYXN5bmM6ZmFsc2UsZGF0YVR5cGU6InNjcmlwdCJ9KTpjLmdsb2JhbEV2YWwoYi50ZXh0fHxiLnRleHRDb250ZW50fHxiLmlubmVySFRNTHx8IiIpO2IucGFyZW50Tm9kZSYmYi5wYXJlbnROb2RlLnJlbW92ZUNoaWxkKGIpfWZ1bmN0aW9uIFgoYSxiLGQsZixlLGope3ZhciBpPWEubGVuZ3RoO2lmKHR5cGVvZiBiPT09Im9iamVjdCIpe2Zvcih2YXIgbyBpbiBiKVgoYSxvLGJbb10sZixlLGQpO3JldHVybiBhfWlmKGQhPT13KXtmPSFqJiZmJiZjLmlzRnVuY3Rpb24oZCk7Zm9yKG89MDtvPGk7bysrKWUoYVtvXSxiLGY/ZC5jYWxsKGFbb10sbyxlKGFbb10sYikpOmQsaik7cmV0dXJuIGF9cmV0dXJuIGk/DQplKGFbMF0sYik6d31mdW5jdGlvbiBKKCl7cmV0dXJuKG5ldyBEYXRlKS5nZXRUaW1lKCl9ZnVuY3Rpb24gWSgpe3JldHVybiBmYWxzZX1mdW5jdGlvbiBaKCl7cmV0dXJuIHRydWV9ZnVuY3Rpb24gbmEoYSxiLGQpe2RbMF0udHlwZT1hO3JldHVybiBjLmV2ZW50LmhhbmRsZS5hcHBseShiLGQpfWZ1bmN0aW9uIG9hKGEpe3ZhciBiLGQ9W10sZj1bXSxlPWFyZ3VtZW50cyxqLGksbyxrLG4scjtpPWMuZGF0YSh0aGlzLCJldmVudHMiKTtpZighKGEubGl2ZUZpcmVkPT09dGhpc3x8IWl8fCFpLmxpdmV8fGEuYnV0dG9uJiZhLnR5cGU9PT0iY2xpY2siKSl7YS5saXZlRmlyZWQ9dGhpczt2YXIgdT1pLmxpdmUuc2xpY2UoMCk7Zm9yKGs9MDtrPHUubGVuZ3RoO2srKyl7aT11W2tdO2kub3JpZ1R5cGUucmVwbGFjZShPLCIiKT09PWEudHlwZT9mLnB1c2goaS5zZWxlY3Rvcik6dS5zcGxpY2Uoay0tLDEpfWo9YyhhLnRhcmdldCkuY2xvc2VzdChmLGEuY3VycmVudFRhcmdldCk7bj0wO2ZvcihyPQ0Kai5sZW5ndGg7bjxyO24rKylmb3Ioaz0wO2s8dS5sZW5ndGg7aysrKXtpPXVba107aWYoaltuXS5zZWxlY3Rvcj09PWkuc2VsZWN0b3Ipe289altuXS5lbGVtO2Y9bnVsbDtpZihpLnByZVR5cGU9PT0ibW91c2VlbnRlciJ8fGkucHJlVHlwZT09PSJtb3VzZWxlYXZlIilmPWMoYS5yZWxhdGVkVGFyZ2V0KS5jbG9zZXN0KGkuc2VsZWN0b3IpWzBdO2lmKCFmfHxmIT09bylkLnB1c2goe2VsZW06byxoYW5kbGVPYmo6aX0pfX1uPTA7Zm9yKHI9ZC5sZW5ndGg7bjxyO24rKyl7aj1kW25dO2EuY3VycmVudFRhcmdldD1qLmVsZW07YS5kYXRhPWouaGFuZGxlT2JqLmRhdGE7YS5oYW5kbGVPYmo9ai5oYW5kbGVPYmo7aWYoai5oYW5kbGVPYmoub3JpZ0hhbmRsZXIuYXBwbHkoai5lbGVtLGUpPT09ZmFsc2Upe2I9ZmFsc2U7YnJlYWt9fXJldHVybiBifX1mdW5jdGlvbiBwYShhLGIpe3JldHVybiJsaXZlLiIrKGEmJmEhPT0iKiI/YSsiLiI6IiIpK2IucmVwbGFjZSgvXC4vZywiYCIpLnJlcGxhY2UoLyAvZywNCiImIil9ZnVuY3Rpb24gcWEoYSl7cmV0dXJuIWF8fCFhLnBhcmVudE5vZGV8fGEucGFyZW50Tm9kZS5ub2RlVHlwZT09PTExfWZ1bmN0aW9uIHJhKGEsYil7dmFyIGQ9MDtiLmVhY2goZnVuY3Rpb24oKXtpZih0aGlzLm5vZGVOYW1lPT09KGFbZF0mJmFbZF0ubm9kZU5hbWUpKXt2YXIgZj1jLmRhdGEoYVtkKytdKSxlPWMuZGF0YSh0aGlzLGYpO2lmKGY9ZiYmZi5ldmVudHMpe2RlbGV0ZSBlLmhhbmRsZTtlLmV2ZW50cz17fTtmb3IodmFyIGogaW4gZilmb3IodmFyIGkgaW4gZltqXSljLmV2ZW50LmFkZCh0aGlzLGosZltqXVtpXSxmW2pdW2ldLmRhdGEpfX19KX1mdW5jdGlvbiBzYShhLGIsZCl7dmFyIGYsZSxqO2I9YiYmYlswXT9iWzBdLm93bmVyRG9jdW1lbnR8fGJbMF06cztpZihhLmxlbmd0aD09PTEmJnR5cGVvZiBhWzBdPT09InN0cmluZyImJmFbMF0ubGVuZ3RoPDUxMiYmYj09PXMmJiF0YS50ZXN0KGFbMF0pJiYoYy5zdXBwb3J0LmNoZWNrQ2xvbmV8fCF1YS50ZXN0KGFbMF0pKSl7ZT0NCnRydWU7aWYoaj1jLmZyYWdtZW50c1thWzBdXSlpZihqIT09MSlmPWp9aWYoIWYpe2Y9Yi5jcmVhdGVEb2N1bWVudEZyYWdtZW50KCk7Yy5jbGVhbihhLGIsZixkKX1pZihlKWMuZnJhZ21lbnRzW2FbMF1dPWo/ZjoxO3JldHVybntmcmFnbWVudDpmLGNhY2hlYWJsZTplfX1mdW5jdGlvbiBLKGEsYil7dmFyIGQ9e307Yy5lYWNoKHZhLmNvbmNhdC5hcHBseShbXSx2YS5zbGljZSgwLGIpKSxmdW5jdGlvbigpe2RbdGhpc109YX0pO3JldHVybiBkfWZ1bmN0aW9uIHdhKGEpe3JldHVybiJzY3JvbGxUbyJpbiBhJiZhLmRvY3VtZW50P2E6YS5ub2RlVHlwZT09PTk/YS5kZWZhdWx0Vmlld3x8YS5wYXJlbnRXaW5kb3c6ZmFsc2V9dmFyIGM9ZnVuY3Rpb24oYSxiKXtyZXR1cm4gbmV3IGMuZm4uaW5pdChhLGIpfSxSYT1BLmpRdWVyeSxTYT1BLiQscz1BLmRvY3VtZW50LFQsVGE9L15bXjxdKig8W1x3XFddKz4pW14+XSokfF4jKFtcdy1dKykkLyxVYT0vXi5bXjojXFtcLixdKiQvLFZhPS9cUy8sDQpXYT0vXihcc3xcdTAwQTApK3woXHN8XHUwMEEwKSskL2csWGE9L148KFx3KylccypcLz8+KD86PFwvXDE+KT8kLyxQPW5hdmlnYXRvci51c2VyQWdlbnQseGE9ZmFsc2UsUT1bXSxMLCQ9T2JqZWN0LnByb3RvdHlwZS50b1N0cmluZyxhYT1PYmplY3QucHJvdG90eXBlLmhhc093blByb3BlcnR5LGJhPUFycmF5LnByb3RvdHlwZS5wdXNoLFI9QXJyYXkucHJvdG90eXBlLnNsaWNlLHlhPUFycmF5LnByb3RvdHlwZS5pbmRleE9mO2MuZm49Yy5wcm90b3R5cGU9e2luaXQ6ZnVuY3Rpb24oYSxiKXt2YXIgZCxmO2lmKCFhKXJldHVybiB0aGlzO2lmKGEubm9kZVR5cGUpe3RoaXMuY29udGV4dD10aGlzWzBdPWE7dGhpcy5sZW5ndGg9MTtyZXR1cm4gdGhpc31pZihhPT09ImJvZHkiJiYhYil7dGhpcy5jb250ZXh0PXM7dGhpc1swXT1zLmJvZHk7dGhpcy5zZWxlY3Rvcj0iYm9keSI7dGhpcy5sZW5ndGg9MTtyZXR1cm4gdGhpc31pZih0eXBlb2YgYT09PSJzdHJpbmciKWlmKChkPVRhLmV4ZWMoYSkpJiYNCihkWzFdfHwhYikpaWYoZFsxXSl7Zj1iP2Iub3duZXJEb2N1bWVudHx8YjpzO2lmKGE9WGEuZXhlYyhhKSlpZihjLmlzUGxhaW5PYmplY3QoYikpe2E9W3MuY3JlYXRlRWxlbWVudChhWzFdKV07Yy5mbi5hdHRyLmNhbGwoYSxiLHRydWUpfWVsc2UgYT1bZi5jcmVhdGVFbGVtZW50KGFbMV0pXTtlbHNle2E9c2EoW2RbMV1dLFtmXSk7YT0oYS5jYWNoZWFibGU/YS5mcmFnbWVudC5jbG9uZU5vZGUodHJ1ZSk6YS5mcmFnbWVudCkuY2hpbGROb2Rlc31yZXR1cm4gYy5tZXJnZSh0aGlzLGEpfWVsc2V7aWYoYj1zLmdldEVsZW1lbnRCeUlkKGRbMl0pKXtpZihiLmlkIT09ZFsyXSlyZXR1cm4gVC5maW5kKGEpO3RoaXMubGVuZ3RoPTE7dGhpc1swXT1ifXRoaXMuY29udGV4dD1zO3RoaXMuc2VsZWN0b3I9YTtyZXR1cm4gdGhpc31lbHNlIGlmKCFiJiYvXlx3KyQvLnRlc3QoYSkpe3RoaXMuc2VsZWN0b3I9YTt0aGlzLmNvbnRleHQ9czthPXMuZ2V0RWxlbWVudHNCeVRhZ05hbWUoYSk7cmV0dXJuIGMubWVyZ2UodGhpcywNCmEpfWVsc2UgcmV0dXJuIWJ8fGIuanF1ZXJ5PyhifHxUKS5maW5kKGEpOmMoYikuZmluZChhKTtlbHNlIGlmKGMuaXNGdW5jdGlvbihhKSlyZXR1cm4gVC5yZWFkeShhKTtpZihhLnNlbGVjdG9yIT09dyl7dGhpcy5zZWxlY3Rvcj1hLnNlbGVjdG9yO3RoaXMuY29udGV4dD1hLmNvbnRleHR9cmV0dXJuIGMubWFrZUFycmF5KGEsdGhpcyl9LHNlbGVjdG9yOiIiLGpxdWVyeToiMS40LjIiLGxlbmd0aDowLHNpemU6ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5sZW5ndGh9LHRvQXJyYXk6ZnVuY3Rpb24oKXtyZXR1cm4gUi5jYWxsKHRoaXMsMCl9LGdldDpmdW5jdGlvbihhKXtyZXR1cm4gYT09bnVsbD90aGlzLnRvQXJyYXkoKTphPDA/dGhpcy5zbGljZShhKVswXTp0aGlzW2FdfSxwdXNoU3RhY2s6ZnVuY3Rpb24oYSxiLGQpe3ZhciBmPWMoKTtjLmlzQXJyYXkoYSk/YmEuYXBwbHkoZixhKTpjLm1lcmdlKGYsYSk7Zi5wcmV2T2JqZWN0PXRoaXM7Zi5jb250ZXh0PXRoaXMuY29udGV4dDtpZihiPT09DQoiZmluZCIpZi5zZWxlY3Rvcj10aGlzLnNlbGVjdG9yKyh0aGlzLnNlbGVjdG9yPyIgIjoiIikrZDtlbHNlIGlmKGIpZi5zZWxlY3Rvcj10aGlzLnNlbGVjdG9yKyIuIitiKyIoIitkKyIpIjtyZXR1cm4gZn0sZWFjaDpmdW5jdGlvbihhLGIpe3JldHVybiBjLmVhY2godGhpcyxhLGIpfSxyZWFkeTpmdW5jdGlvbihhKXtjLmJpbmRSZWFkeSgpO2lmKGMuaXNSZWFkeSlhLmNhbGwocyxjKTtlbHNlIFEmJlEucHVzaChhKTtyZXR1cm4gdGhpc30sZXE6ZnVuY3Rpb24oYSl7cmV0dXJuIGE9PT0tMT90aGlzLnNsaWNlKGEpOnRoaXMuc2xpY2UoYSwrYSsxKX0sZmlyc3Q6ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5lcSgwKX0sbGFzdDpmdW5jdGlvbigpe3JldHVybiB0aGlzLmVxKC0xKX0sc2xpY2U6ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5wdXNoU3RhY2soUi5hcHBseSh0aGlzLGFyZ3VtZW50cyksInNsaWNlIixSLmNhbGwoYXJndW1lbnRzKS5qb2luKCIsIikpfSxtYXA6ZnVuY3Rpb24oYSl7cmV0dXJuIHRoaXMucHVzaFN0YWNrKGMubWFwKHRoaXMsDQpmdW5jdGlvbihiLGQpe3JldHVybiBhLmNhbGwoYixkLGIpfSkpfSxlbmQ6ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5wcmV2T2JqZWN0fHxjKG51bGwpfSxwdXNoOmJhLHNvcnQ6W10uc29ydCxzcGxpY2U6W10uc3BsaWNlfTtjLmZuLmluaXQucHJvdG90eXBlPWMuZm47Yy5leHRlbmQ9Yy5mbi5leHRlbmQ9ZnVuY3Rpb24oKXt2YXIgYT1hcmd1bWVudHNbMF18fHt9LGI9MSxkPWFyZ3VtZW50cy5sZW5ndGgsZj1mYWxzZSxlLGosaSxvO2lmKHR5cGVvZiBhPT09ImJvb2xlYW4iKXtmPWE7YT1hcmd1bWVudHNbMV18fHt9O2I9Mn1pZih0eXBlb2YgYSE9PSJvYmplY3QiJiYhYy5pc0Z1bmN0aW9uKGEpKWE9e307aWYoZD09PWIpe2E9dGhpczstLWJ9Zm9yKDtiPGQ7YisrKWlmKChlPWFyZ3VtZW50c1tiXSkhPW51bGwpZm9yKGogaW4gZSl7aT1hW2pdO289ZVtqXTtpZihhIT09bylpZihmJiZvJiYoYy5pc1BsYWluT2JqZWN0KG8pfHxjLmlzQXJyYXkobykpKXtpPWkmJihjLmlzUGxhaW5PYmplY3QoaSl8fA0KYy5pc0FycmF5KGkpKT9pOmMuaXNBcnJheShvKT9bXTp7fTthW2pdPWMuZXh0ZW5kKGYsaSxvKX1lbHNlIGlmKG8hPT13KWFbal09b31yZXR1cm4gYX07Yy5leHRlbmQoe25vQ29uZmxpY3Q6ZnVuY3Rpb24oYSl7QS4kPVNhO2lmKGEpQS5qUXVlcnk9UmE7cmV0dXJuIGN9LGlzUmVhZHk6ZmFsc2UscmVhZHk6ZnVuY3Rpb24oKXtpZighYy5pc1JlYWR5KXtpZighcy5ib2R5KXJldHVybiBzZXRUaW1lb3V0KGMucmVhZHksMTMpO2MuaXNSZWFkeT10cnVlO2lmKFEpe2Zvcih2YXIgYSxiPTA7YT1RW2IrK107KWEuY2FsbChzLGMpO1E9bnVsbH1jLmZuLnRyaWdnZXJIYW5kbGVyJiZjKHMpLnRyaWdnZXJIYW5kbGVyKCJyZWFkeSIpfX0sYmluZFJlYWR5OmZ1bmN0aW9uKCl7aWYoIXhhKXt4YT10cnVlO2lmKHMucmVhZHlTdGF0ZT09PSJjb21wbGV0ZSIpcmV0dXJuIGMucmVhZHkoKTtpZihzLmFkZEV2ZW50TGlzdGVuZXIpe3MuYWRkRXZlbnRMaXN0ZW5lcigiRE9NQ29udGVudExvYWRlZCIsDQpMLGZhbHNlKTtBLmFkZEV2ZW50TGlzdGVuZXIoImxvYWQiLGMucmVhZHksZmFsc2UpfWVsc2UgaWYocy5hdHRhY2hFdmVudCl7cy5hdHRhY2hFdmVudCgib25yZWFkeXN0YXRlY2hhbmdlIixMKTtBLmF0dGFjaEV2ZW50KCJvbmxvYWQiLGMucmVhZHkpO3ZhciBhPWZhbHNlO3RyeXthPUEuZnJhbWVFbGVtZW50PT1udWxsfWNhdGNoKGIpe31zLmRvY3VtZW50RWxlbWVudC5kb1Njcm9sbCYmYSYmbWEoKX19fSxpc0Z1bmN0aW9uOmZ1bmN0aW9uKGEpe3JldHVybiAkLmNhbGwoYSk9PT0iW29iamVjdCBGdW5jdGlvbl0ifSxpc0FycmF5OmZ1bmN0aW9uKGEpe3JldHVybiAkLmNhbGwoYSk9PT0iW29iamVjdCBBcnJheV0ifSxpc1BsYWluT2JqZWN0OmZ1bmN0aW9uKGEpe2lmKCFhfHwkLmNhbGwoYSkhPT0iW29iamVjdCBPYmplY3RdInx8YS5ub2RlVHlwZXx8YS5zZXRJbnRlcnZhbClyZXR1cm4gZmFsc2U7aWYoYS5jb25zdHJ1Y3RvciYmIWFhLmNhbGwoYSwiY29uc3RydWN0b3IiKSYmIWFhLmNhbGwoYS5jb25zdHJ1Y3Rvci5wcm90b3R5cGUsDQoiaXNQcm90b3R5cGVPZiIpKXJldHVybiBmYWxzZTt2YXIgYjtmb3IoYiBpbiBhKTtyZXR1cm4gYj09PXd8fGFhLmNhbGwoYSxiKX0saXNFbXB0eU9iamVjdDpmdW5jdGlvbihhKXtmb3IodmFyIGIgaW4gYSlyZXR1cm4gZmFsc2U7cmV0dXJuIHRydWV9LGVycm9yOmZ1bmN0aW9uKGEpe3Rocm93IGE7fSxwYXJzZUpTT046ZnVuY3Rpb24oYSl7aWYodHlwZW9mIGEhPT0ic3RyaW5nInx8IWEpcmV0dXJuIG51bGw7YT1jLnRyaW0oYSk7aWYoL15bXF0sOnt9XHNdKiQvLnRlc3QoYS5yZXBsYWNlKC9cXCg/OlsiXFxcL2JmbnJ0XXx1WzAtOWEtZkEtRl17NH0pL2csIkAiKS5yZXBsYWNlKC8iW14iXFxcblxyXSoifHRydWV8ZmFsc2V8bnVsbHwtP1xkKyg/OlwuXGQqKT8oPzpbZUVdWytcLV0/XGQrKT8vZywiXSIpLnJlcGxhY2UoLyg/Ol58OnwsKSg/OlxzKlxbKSsvZywiIikpKXJldHVybiBBLkpTT04mJkEuSlNPTi5wYXJzZT9BLkpTT04ucGFyc2UoYSk6KG5ldyBGdW5jdGlvbigicmV0dXJuICIrDQphKSkoKTtlbHNlIGMuZXJyb3IoIkludmFsaWQgSlNPTjogIithKX0sbm9vcDpmdW5jdGlvbigpe30sZ2xvYmFsRXZhbDpmdW5jdGlvbihhKXtpZihhJiZWYS50ZXN0KGEpKXt2YXIgYj1zLmdldEVsZW1lbnRzQnlUYWdOYW1lKCJoZWFkIilbMF18fHMuZG9jdW1lbnRFbGVtZW50LGQ9cy5jcmVhdGVFbGVtZW50KCJzY3JpcHQiKTtkLnR5cGU9InRleHQvamF2YXNjcmlwdCI7aWYoYy5zdXBwb3J0LnNjcmlwdEV2YWwpZC5hcHBlbmRDaGlsZChzLmNyZWF0ZVRleHROb2RlKGEpKTtlbHNlIGQudGV4dD1hO2IuaW5zZXJ0QmVmb3JlKGQsYi5maXJzdENoaWxkKTtiLnJlbW92ZUNoaWxkKGQpfX0sbm9kZU5hbWU6ZnVuY3Rpb24oYSxiKXtyZXR1cm4gYS5ub2RlTmFtZSYmYS5ub2RlTmFtZS50b1VwcGVyQ2FzZSgpPT09Yi50b1VwcGVyQ2FzZSgpfSxlYWNoOmZ1bmN0aW9uKGEsYixkKXt2YXIgZixlPTAsaj1hLmxlbmd0aCxpPWo9PT13fHxjLmlzRnVuY3Rpb24oYSk7aWYoZClpZihpKWZvcihmIGluIGEpe2lmKGIuYXBwbHkoYVtmXSwNCmQpPT09ZmFsc2UpYnJlYWt9ZWxzZSBmb3IoO2U8ajspe2lmKGIuYXBwbHkoYVtlKytdLGQpPT09ZmFsc2UpYnJlYWt9ZWxzZSBpZihpKWZvcihmIGluIGEpe2lmKGIuY2FsbChhW2ZdLGYsYVtmXSk9PT1mYWxzZSlicmVha31lbHNlIGZvcihkPWFbMF07ZTxqJiZiLmNhbGwoZCxlLGQpIT09ZmFsc2U7ZD1hWysrZV0pO3JldHVybiBhfSx0cmltOmZ1bmN0aW9uKGEpe3JldHVybihhfHwiIikucmVwbGFjZShXYSwiIil9LG1ha2VBcnJheTpmdW5jdGlvbihhLGIpe2I9Ynx8W107aWYoYSE9bnVsbClhLmxlbmd0aD09bnVsbHx8dHlwZW9mIGE9PT0ic3RyaW5nInx8Yy5pc0Z1bmN0aW9uKGEpfHx0eXBlb2YgYSE9PSJmdW5jdGlvbiImJmEuc2V0SW50ZXJ2YWw/YmEuY2FsbChiLGEpOmMubWVyZ2UoYixhKTtyZXR1cm4gYn0saW5BcnJheTpmdW5jdGlvbihhLGIpe2lmKGIuaW5kZXhPZilyZXR1cm4gYi5pbmRleE9mKGEpO2Zvcih2YXIgZD0wLGY9Yi5sZW5ndGg7ZDxmO2QrKylpZihiW2RdPT09DQphKXJldHVybiBkO3JldHVybi0xfSxtZXJnZTpmdW5jdGlvbihhLGIpe3ZhciBkPWEubGVuZ3RoLGY9MDtpZih0eXBlb2YgYi5sZW5ndGg9PT0ibnVtYmVyIilmb3IodmFyIGU9Yi5sZW5ndGg7ZjxlO2YrKylhW2QrK109YltmXTtlbHNlIGZvcig7YltmXSE9PXc7KWFbZCsrXT1iW2YrK107YS5sZW5ndGg9ZDtyZXR1cm4gYX0sZ3JlcDpmdW5jdGlvbihhLGIsZCl7Zm9yKHZhciBmPVtdLGU9MCxqPWEubGVuZ3RoO2U8ajtlKyspIWQhPT0hYihhW2VdLGUpJiZmLnB1c2goYVtlXSk7cmV0dXJuIGZ9LG1hcDpmdW5jdGlvbihhLGIsZCl7Zm9yKHZhciBmPVtdLGUsaj0wLGk9YS5sZW5ndGg7ajxpO2orKyl7ZT1iKGFbal0saixkKTtpZihlIT1udWxsKWZbZi5sZW5ndGhdPWV9cmV0dXJuIGYuY29uY2F0LmFwcGx5KFtdLGYpfSxndWlkOjEscHJveHk6ZnVuY3Rpb24oYSxiLGQpe2lmKGFyZ3VtZW50cy5sZW5ndGg9PT0yKWlmKHR5cGVvZiBiPT09InN0cmluZyIpe2Q9YTthPWRbYl07Yj13fWVsc2UgaWYoYiYmDQohYy5pc0Z1bmN0aW9uKGIpKXtkPWI7Yj13fWlmKCFiJiZhKWI9ZnVuY3Rpb24oKXtyZXR1cm4gYS5hcHBseShkfHx0aGlzLGFyZ3VtZW50cyl9O2lmKGEpYi5ndWlkPWEuZ3VpZD1hLmd1aWR8fGIuZ3VpZHx8Yy5ndWlkKys7cmV0dXJuIGJ9LHVhTWF0Y2g6ZnVuY3Rpb24oYSl7YT1hLnRvTG93ZXJDYXNlKCk7YT0vKHdlYmtpdClbIFwvXShbXHcuXSspLy5leGVjKGEpfHwvKG9wZXJhKSg/Oi4qdmVyc2lvbik/WyBcL10oW1x3Ll0rKS8uZXhlYyhhKXx8Lyhtc2llKSAoW1x3Ll0rKS8uZXhlYyhhKXx8IS9jb21wYXRpYmxlLy50ZXN0KGEpJiYvKG1vemlsbGEpKD86Lio/IHJ2OihbXHcuXSspKT8vLmV4ZWMoYSl8fFtdO3JldHVybnticm93c2VyOmFbMV18fCIiLHZlcnNpb246YVsyXXx8IjAifX0sYnJvd3Nlcjp7fX0pO1A9Yy51YU1hdGNoKFApO2lmKFAuYnJvd3Nlcil7Yy5icm93c2VyW1AuYnJvd3Nlcl09dHJ1ZTtjLmJyb3dzZXIudmVyc2lvbj1QLnZlcnNpb259aWYoYy5icm93c2VyLndlYmtpdCljLmJyb3dzZXIuc2FmYXJpPQ0KdHJ1ZTtpZih5YSljLmluQXJyYXk9ZnVuY3Rpb24oYSxiKXtyZXR1cm4geWEuY2FsbChiLGEpfTtUPWMocyk7aWYocy5hZGRFdmVudExpc3RlbmVyKUw9ZnVuY3Rpb24oKXtzLnJlbW92ZUV2ZW50TGlzdGVuZXIoIkRPTUNvbnRlbnRMb2FkZWQiLEwsZmFsc2UpO2MucmVhZHkoKX07ZWxzZSBpZihzLmF0dGFjaEV2ZW50KUw9ZnVuY3Rpb24oKXtpZihzLnJlYWR5U3RhdGU9PT0iY29tcGxldGUiKXtzLmRldGFjaEV2ZW50KCJvbnJlYWR5c3RhdGVjaGFuZ2UiLEwpO2MucmVhZHkoKX19OyhmdW5jdGlvbigpe2Muc3VwcG9ydD17fTt2YXIgYT1zLmRvY3VtZW50RWxlbWVudCxiPXMuY3JlYXRlRWxlbWVudCgic2NyaXB0IiksZD1zLmNyZWF0ZUVsZW1lbnQoImRpdiIpLGY9InNjcmlwdCIrSigpO2Quc3R5bGUuZGlzcGxheT0ibm9uZSI7ZC5pbm5lckhUTUw9IiAgIDxsaW5rLz48dGFibGU+PC90YWJsZT48YSBocmVmPScvYScgc3R5bGU9J2NvbG9yOnJlZDtmbG9hdDpsZWZ0O29wYWNpdHk6LjU1Oyc+YTwvYT48aW5wdXQgdHlwZT0nY2hlY2tib3gnLz4iOw0KdmFyIGU9ZC5nZXRFbGVtZW50c0J5VGFnTmFtZSgiKiIpLGo9ZC5nZXRFbGVtZW50c0J5VGFnTmFtZSgiYSIpWzBdO2lmKCEoIWV8fCFlLmxlbmd0aHx8IWopKXtjLnN1cHBvcnQ9e2xlYWRpbmdXaGl0ZXNwYWNlOmQuZmlyc3RDaGlsZC5ub2RlVHlwZT09PTMsdGJvZHk6IWQuZ2V0RWxlbWVudHNCeVRhZ05hbWUoInRib2R5IikubGVuZ3RoLGh0bWxTZXJpYWxpemU6ISFkLmdldEVsZW1lbnRzQnlUYWdOYW1lKCJsaW5rIikubGVuZ3RoLHN0eWxlOi9yZWQvLnRlc3Qoai5nZXRBdHRyaWJ1dGUoInN0eWxlIikpLGhyZWZOb3JtYWxpemVkOmouZ2V0QXR0cmlidXRlKCJocmVmIik9PT0iL2EiLG9wYWNpdHk6L14wLjU1JC8udGVzdChqLnN0eWxlLm9wYWNpdHkpLGNzc0Zsb2F0OiEhai5zdHlsZS5jc3NGbG9hdCxjaGVja09uOmQuZ2V0RWxlbWVudHNCeVRhZ05hbWUoImlucHV0IilbMF0udmFsdWU9PT0ib24iLG9wdFNlbGVjdGVkOnMuY3JlYXRlRWxlbWVudCgic2VsZWN0IikuYXBwZW5kQ2hpbGQocy5jcmVhdGVFbGVtZW50KCJvcHRpb24iKSkuc2VsZWN0ZWQsDQpwYXJlbnROb2RlOmQucmVtb3ZlQ2hpbGQoZC5hcHBlbmRDaGlsZChzLmNyZWF0ZUVsZW1lbnQoImRpdiIpKSkucGFyZW50Tm9kZT09PW51bGwsZGVsZXRlRXhwYW5kbzp0cnVlLGNoZWNrQ2xvbmU6ZmFsc2Usc2NyaXB0RXZhbDpmYWxzZSxub0Nsb25lRXZlbnQ6dHJ1ZSxib3hNb2RlbDpudWxsfTtiLnR5cGU9InRleHQvamF2YXNjcmlwdCI7dHJ5e2IuYXBwZW5kQ2hpbGQocy5jcmVhdGVUZXh0Tm9kZSgid2luZG93LiIrZisiPTE7IikpfWNhdGNoKGkpe31hLmluc2VydEJlZm9yZShiLGEuZmlyc3RDaGlsZCk7aWYoQVtmXSl7Yy5zdXBwb3J0LnNjcmlwdEV2YWw9dHJ1ZTtkZWxldGUgQVtmXX10cnl7ZGVsZXRlIGIudGVzdH1jYXRjaChvKXtjLnN1cHBvcnQuZGVsZXRlRXhwYW5kbz1mYWxzZX1hLnJlbW92ZUNoaWxkKGIpO2lmKGQuYXR0YWNoRXZlbnQmJmQuZmlyZUV2ZW50KXtkLmF0dGFjaEV2ZW50KCJvbmNsaWNrIixmdW5jdGlvbiBrKCl7Yy5zdXBwb3J0Lm5vQ2xvbmVFdmVudD0NCmZhbHNlO2QuZGV0YWNoRXZlbnQoIm9uY2xpY2siLGspfSk7ZC5jbG9uZU5vZGUodHJ1ZSkuZmlyZUV2ZW50KCJvbmNsaWNrIil9ZD1zLmNyZWF0ZUVsZW1lbnQoImRpdiIpO2QuaW5uZXJIVE1MPSI8aW5wdXQgdHlwZT0ncmFkaW8nIG5hbWU9J3JhZGlvdGVzdCcgY2hlY2tlZD0nY2hlY2tlZCcvPiI7YT1zLmNyZWF0ZURvY3VtZW50RnJhZ21lbnQoKTthLmFwcGVuZENoaWxkKGQuZmlyc3RDaGlsZCk7Yy5zdXBwb3J0LmNoZWNrQ2xvbmU9YS5jbG9uZU5vZGUodHJ1ZSkuY2xvbmVOb2RlKHRydWUpLmxhc3RDaGlsZC5jaGVja2VkO2MoZnVuY3Rpb24oKXt2YXIgaz1zLmNyZWF0ZUVsZW1lbnQoImRpdiIpO2suc3R5bGUud2lkdGg9ay5zdHlsZS5wYWRkaW5nTGVmdD0iMXB4IjtzLmJvZHkuYXBwZW5kQ2hpbGQoayk7Yy5ib3hNb2RlbD1jLnN1cHBvcnQuYm94TW9kZWw9ay5vZmZzZXRXaWR0aD09PTI7cy5ib2R5LnJlbW92ZUNoaWxkKGspLnN0eWxlLmRpc3BsYXk9Im5vbmUifSk7YT1mdW5jdGlvbihrKXt2YXIgbj0NCnMuY3JlYXRlRWxlbWVudCgiZGl2Iik7az0ib24iK2s7dmFyIHI9ayBpbiBuO2lmKCFyKXtuLnNldEF0dHJpYnV0ZShrLCJyZXR1cm47Iik7cj10eXBlb2YgbltrXT09PSJmdW5jdGlvbiJ9cmV0dXJuIHJ9O2Muc3VwcG9ydC5zdWJtaXRCdWJibGVzPWEoInN1Ym1pdCIpO2Muc3VwcG9ydC5jaGFuZ2VCdWJibGVzPWEoImNoYW5nZSIpO2E9Yj1kPWU9aj1udWxsfX0pKCk7Yy5wcm9wcz17ImZvciI6Imh0bWxGb3IiLCJjbGFzcyI6ImNsYXNzTmFtZSIscmVhZG9ubHk6InJlYWRPbmx5IixtYXhsZW5ndGg6Im1heExlbmd0aCIsY2VsbHNwYWNpbmc6ImNlbGxTcGFjaW5nIixyb3dzcGFuOiJyb3dTcGFuIixjb2xzcGFuOiJjb2xTcGFuIix0YWJpbmRleDoidGFiSW5kZXgiLHVzZW1hcDoidXNlTWFwIixmcmFtZWJvcmRlcjoiZnJhbWVCb3JkZXIifTt2YXIgRz0ialF1ZXJ5IitKKCksWWE9MCx6YT17fTtjLmV4dGVuZCh7Y2FjaGU6e30sZXhwYW5kbzpHLG5vRGF0YTp7ZW1iZWQ6dHJ1ZSxvYmplY3Q6dHJ1ZSwNCmFwcGxldDp0cnVlfSxkYXRhOmZ1bmN0aW9uKGEsYixkKXtpZighKGEubm9kZU5hbWUmJmMubm9EYXRhW2Eubm9kZU5hbWUudG9Mb3dlckNhc2UoKV0pKXthPWE9PUE/emE6YTt2YXIgZj1hW0ddLGU9Yy5jYWNoZTtpZighZiYmdHlwZW9mIGI9PT0ic3RyaW5nIiYmZD09PXcpcmV0dXJuIG51bGw7Znx8KGY9KytZYSk7aWYodHlwZW9mIGI9PT0ib2JqZWN0Iil7YVtHXT1mO2VbZl09Yy5leHRlbmQodHJ1ZSx7fSxiKX1lbHNlIGlmKCFlW2ZdKXthW0ddPWY7ZVtmXT17fX1hPWVbZl07aWYoZCE9PXcpYVtiXT1kO3JldHVybiB0eXBlb2YgYj09PSJzdHJpbmciP2FbYl06YX19LHJlbW92ZURhdGE6ZnVuY3Rpb24oYSxiKXtpZighKGEubm9kZU5hbWUmJmMubm9EYXRhW2Eubm9kZU5hbWUudG9Mb3dlckNhc2UoKV0pKXthPWE9PUE/emE6YTt2YXIgZD1hW0ddLGY9Yy5jYWNoZSxlPWZbZF07aWYoYil7aWYoZSl7ZGVsZXRlIGVbYl07Yy5pc0VtcHR5T2JqZWN0KGUpJiZjLnJlbW92ZURhdGEoYSl9fWVsc2V7aWYoYy5zdXBwb3J0LmRlbGV0ZUV4cGFuZG8pZGVsZXRlIGFbYy5leHBhbmRvXTsNCmVsc2UgYS5yZW1vdmVBdHRyaWJ1dGUmJmEucmVtb3ZlQXR0cmlidXRlKGMuZXhwYW5kbyk7ZGVsZXRlIGZbZF19fX19KTtjLmZuLmV4dGVuZCh7ZGF0YTpmdW5jdGlvbihhLGIpe2lmKHR5cGVvZiBhPT09InVuZGVmaW5lZCImJnRoaXMubGVuZ3RoKXJldHVybiBjLmRhdGEodGhpc1swXSk7ZWxzZSBpZih0eXBlb2YgYT09PSJvYmplY3QiKXJldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXtjLmRhdGEodGhpcyxhKX0pO3ZhciBkPWEuc3BsaXQoIi4iKTtkWzFdPWRbMV0/Ii4iK2RbMV06IiI7aWYoYj09PXcpe3ZhciBmPXRoaXMudHJpZ2dlckhhbmRsZXIoImdldERhdGEiK2RbMV0rIiEiLFtkWzBdXSk7aWYoZj09PXcmJnRoaXMubGVuZ3RoKWY9Yy5kYXRhKHRoaXNbMF0sYSk7cmV0dXJuIGY9PT13JiZkWzFdP3RoaXMuZGF0YShkWzBdKTpmfWVsc2UgcmV0dXJuIHRoaXMudHJpZ2dlcigic2V0RGF0YSIrZFsxXSsiISIsW2RbMF0sYl0pLmVhY2goZnVuY3Rpb24oKXtjLmRhdGEodGhpcywNCmEsYil9KX0scmVtb3ZlRGF0YTpmdW5jdGlvbihhKXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7Yy5yZW1vdmVEYXRhKHRoaXMsYSl9KX19KTtjLmV4dGVuZCh7cXVldWU6ZnVuY3Rpb24oYSxiLGQpe2lmKGEpe2I9KGJ8fCJmeCIpKyJxdWV1ZSI7dmFyIGY9Yy5kYXRhKGEsYik7aWYoIWQpcmV0dXJuIGZ8fFtdO2lmKCFmfHxjLmlzQXJyYXkoZCkpZj1jLmRhdGEoYSxiLGMubWFrZUFycmF5KGQpKTtlbHNlIGYucHVzaChkKTtyZXR1cm4gZn19LGRlcXVldWU6ZnVuY3Rpb24oYSxiKXtiPWJ8fCJmeCI7dmFyIGQ9Yy5xdWV1ZShhLGIpLGY9ZC5zaGlmdCgpO2lmKGY9PT0iaW5wcm9ncmVzcyIpZj1kLnNoaWZ0KCk7aWYoZil7Yj09PSJmeCImJmQudW5zaGlmdCgiaW5wcm9ncmVzcyIpO2YuY2FsbChhLGZ1bmN0aW9uKCl7Yy5kZXF1ZXVlKGEsYil9KX19fSk7Yy5mbi5leHRlbmQoe3F1ZXVlOmZ1bmN0aW9uKGEsYil7aWYodHlwZW9mIGEhPT0ic3RyaW5nIil7Yj1hO2E9ImZ4In1pZihiPT09DQp3KXJldHVybiBjLnF1ZXVlKHRoaXNbMF0sYSk7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciBkPWMucXVldWUodGhpcyxhLGIpO2E9PT0iZngiJiZkWzBdIT09ImlucHJvZ3Jlc3MiJiZjLmRlcXVldWUodGhpcyxhKX0pfSxkZXF1ZXVlOmZ1bmN0aW9uKGEpe3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXtjLmRlcXVldWUodGhpcyxhKX0pfSxkZWxheTpmdW5jdGlvbihhLGIpe2E9Yy5meD9jLmZ4LnNwZWVkc1thXXx8YTphO2I9Ynx8ImZ4IjtyZXR1cm4gdGhpcy5xdWV1ZShiLGZ1bmN0aW9uKCl7dmFyIGQ9dGhpcztzZXRUaW1lb3V0KGZ1bmN0aW9uKCl7Yy5kZXF1ZXVlKGQsYil9LGEpfSl9LGNsZWFyUXVldWU6ZnVuY3Rpb24oYSl7cmV0dXJuIHRoaXMucXVldWUoYXx8ImZ4IixbXSl9fSk7dmFyIEFhPS9bXG5cdF0vZyxjYT0vXHMrLyxaYT0vXHIvZywkYT0vaHJlZnxzcmN8c3R5bGUvLGFiPS8oYnV0dG9ufGlucHV0KS9pLGJiPS8oYnV0dG9ufGlucHV0fG9iamVjdHxzZWxlY3R8dGV4dGFyZWEpL2ksDQpjYj0vXihhfGFyZWEpJC9pLEJhPS9yYWRpb3xjaGVja2JveC87Yy5mbi5leHRlbmQoe2F0dHI6ZnVuY3Rpb24oYSxiKXtyZXR1cm4gWCh0aGlzLGEsYix0cnVlLGMuYXR0cil9LHJlbW92ZUF0dHI6ZnVuY3Rpb24oYSl7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe2MuYXR0cih0aGlzLGEsIiIpO3RoaXMubm9kZVR5cGU9PT0xJiZ0aGlzLnJlbW92ZUF0dHJpYnV0ZShhKX0pfSxhZGRDbGFzczpmdW5jdGlvbihhKXtpZihjLmlzRnVuY3Rpb24oYSkpcmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbihuKXt2YXIgcj1jKHRoaXMpO3IuYWRkQ2xhc3MoYS5jYWxsKHRoaXMsbixyLmF0dHIoImNsYXNzIikpKX0pO2lmKGEmJnR5cGVvZiBhPT09InN0cmluZyIpZm9yKHZhciBiPShhfHwiIikuc3BsaXQoY2EpLGQ9MCxmPXRoaXMubGVuZ3RoO2Q8ZjtkKyspe3ZhciBlPXRoaXNbZF07aWYoZS5ub2RlVHlwZT09PTEpaWYoZS5jbGFzc05hbWUpe2Zvcih2YXIgaj0iICIrZS5jbGFzc05hbWUrIiAiLA0KaT1lLmNsYXNzTmFtZSxvPTAsaz1iLmxlbmd0aDtvPGs7bysrKWlmKGouaW5kZXhPZigiICIrYltvXSsiICIpPDApaSs9IiAiK2Jbb107ZS5jbGFzc05hbWU9Yy50cmltKGkpfWVsc2UgZS5jbGFzc05hbWU9YX1yZXR1cm4gdGhpc30scmVtb3ZlQ2xhc3M6ZnVuY3Rpb24oYSl7aWYoYy5pc0Z1bmN0aW9uKGEpKXJldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oayl7dmFyIG49Yyh0aGlzKTtuLnJlbW92ZUNsYXNzKGEuY2FsbCh0aGlzLGssbi5hdHRyKCJjbGFzcyIpKSl9KTtpZihhJiZ0eXBlb2YgYT09PSJzdHJpbmcifHxhPT09dylmb3IodmFyIGI9KGF8fCIiKS5zcGxpdChjYSksZD0wLGY9dGhpcy5sZW5ndGg7ZDxmO2QrKyl7dmFyIGU9dGhpc1tkXTtpZihlLm5vZGVUeXBlPT09MSYmZS5jbGFzc05hbWUpaWYoYSl7Zm9yKHZhciBqPSgiICIrZS5jbGFzc05hbWUrIiAiKS5yZXBsYWNlKEFhLCIgIiksaT0wLG89Yi5sZW5ndGg7aTxvO2krKylqPWoucmVwbGFjZSgiICIrYltpXSsiICIsDQoiICIpO2UuY2xhc3NOYW1lPWMudHJpbShqKX1lbHNlIGUuY2xhc3NOYW1lPSIifXJldHVybiB0aGlzfSx0b2dnbGVDbGFzczpmdW5jdGlvbihhLGIpe3ZhciBkPXR5cGVvZiBhLGY9dHlwZW9mIGI9PT0iYm9vbGVhbiI7aWYoYy5pc0Z1bmN0aW9uKGEpKXJldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oZSl7dmFyIGo9Yyh0aGlzKTtqLnRvZ2dsZUNsYXNzKGEuY2FsbCh0aGlzLGUsai5hdHRyKCJjbGFzcyIpLGIpLGIpfSk7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe2lmKGQ9PT0ic3RyaW5nIilmb3IodmFyIGUsaj0wLGk9Yyh0aGlzKSxvPWIsaz1hLnNwbGl0KGNhKTtlPWtbaisrXTspe289Zj9vOiFpLmhhc0NsYXNzKGUpO2lbbz8iYWRkQ2xhc3MiOiJyZW1vdmVDbGFzcyJdKGUpfWVsc2UgaWYoZD09PSJ1bmRlZmluZWQifHxkPT09ImJvb2xlYW4iKXt0aGlzLmNsYXNzTmFtZSYmYy5kYXRhKHRoaXMsIl9fY2xhc3NOYW1lX18iLHRoaXMuY2xhc3NOYW1lKTt0aGlzLmNsYXNzTmFtZT0NCnRoaXMuY2xhc3NOYW1lfHxhPT09ZmFsc2U/IiI6Yy5kYXRhKHRoaXMsIl9fY2xhc3NOYW1lX18iKXx8IiJ9fSl9LGhhc0NsYXNzOmZ1bmN0aW9uKGEpe2E9IiAiK2ErIiAiO2Zvcih2YXIgYj0wLGQ9dGhpcy5sZW5ndGg7YjxkO2IrKylpZigoIiAiK3RoaXNbYl0uY2xhc3NOYW1lKyIgIikucmVwbGFjZShBYSwiICIpLmluZGV4T2YoYSk+LTEpcmV0dXJuIHRydWU7cmV0dXJuIGZhbHNlfSx2YWw6ZnVuY3Rpb24oYSl7aWYoYT09PXcpe3ZhciBiPXRoaXNbMF07aWYoYil7aWYoYy5ub2RlTmFtZShiLCJvcHRpb24iKSlyZXR1cm4oYi5hdHRyaWJ1dGVzLnZhbHVlfHx7fSkuc3BlY2lmaWVkP2IudmFsdWU6Yi50ZXh0O2lmKGMubm9kZU5hbWUoYiwic2VsZWN0Iikpe3ZhciBkPWIuc2VsZWN0ZWRJbmRleCxmPVtdLGU9Yi5vcHRpb25zO2I9Yi50eXBlPT09InNlbGVjdC1vbmUiO2lmKGQ8MClyZXR1cm4gbnVsbDt2YXIgaj1iP2Q6MDtmb3IoZD1iP2QrMTplLmxlbmd0aDtqPGQ7aisrKXt2YXIgaT0NCmVbal07aWYoaS5zZWxlY3RlZCl7YT1jKGkpLnZhbCgpO2lmKGIpcmV0dXJuIGE7Zi5wdXNoKGEpfX1yZXR1cm4gZn1pZihCYS50ZXN0KGIudHlwZSkmJiFjLnN1cHBvcnQuY2hlY2tPbilyZXR1cm4gYi5nZXRBdHRyaWJ1dGUoInZhbHVlIik9PT1udWxsPyJvbiI6Yi52YWx1ZTtyZXR1cm4oYi52YWx1ZXx8IiIpLnJlcGxhY2UoWmEsIiIpfXJldHVybiB3fXZhciBvPWMuaXNGdW5jdGlvbihhKTtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKGspe3ZhciBuPWModGhpcykscj1hO2lmKHRoaXMubm9kZVR5cGU9PT0xKXtpZihvKXI9YS5jYWxsKHRoaXMsayxuLnZhbCgpKTtpZih0eXBlb2Ygcj09PSJudW1iZXIiKXIrPSIiO2lmKGMuaXNBcnJheShyKSYmQmEudGVzdCh0aGlzLnR5cGUpKXRoaXMuY2hlY2tlZD1jLmluQXJyYXkobi52YWwoKSxyKT49MDtlbHNlIGlmKGMubm9kZU5hbWUodGhpcywic2VsZWN0Iikpe3ZhciB1PWMubWFrZUFycmF5KHIpO2MoIm9wdGlvbiIsdGhpcykuZWFjaChmdW5jdGlvbigpe3RoaXMuc2VsZWN0ZWQ9DQpjLmluQXJyYXkoYyh0aGlzKS52YWwoKSx1KT49MH0pO2lmKCF1Lmxlbmd0aCl0aGlzLnNlbGVjdGVkSW5kZXg9LTF9ZWxzZSB0aGlzLnZhbHVlPXJ9fSl9fSk7Yy5leHRlbmQoe2F0dHJGbjp7dmFsOnRydWUsY3NzOnRydWUsaHRtbDp0cnVlLHRleHQ6dHJ1ZSxkYXRhOnRydWUsd2lkdGg6dHJ1ZSxoZWlnaHQ6dHJ1ZSxvZmZzZXQ6dHJ1ZX0sYXR0cjpmdW5jdGlvbihhLGIsZCxmKXtpZighYXx8YS5ub2RlVHlwZT09PTN8fGEubm9kZVR5cGU9PT04KXJldHVybiB3O2lmKGYmJmIgaW4gYy5hdHRyRm4pcmV0dXJuIGMoYSlbYl0oZCk7Zj1hLm5vZGVUeXBlIT09MXx8IWMuaXNYTUxEb2MoYSk7dmFyIGU9ZCE9PXc7Yj1mJiZjLnByb3BzW2JdfHxiO2lmKGEubm9kZVR5cGU9PT0xKXt2YXIgaj0kYS50ZXN0KGIpO2lmKGIgaW4gYSYmZiYmIWope2lmKGUpe2I9PT0idHlwZSImJmFiLnRlc3QoYS5ub2RlTmFtZSkmJmEucGFyZW50Tm9kZSYmYy5lcnJvcigidHlwZSBwcm9wZXJ0eSBjYW4ndCBiZSBjaGFuZ2VkIik7DQphW2JdPWR9aWYoYy5ub2RlTmFtZShhLCJmb3JtIikmJmEuZ2V0QXR0cmlidXRlTm9kZShiKSlyZXR1cm4gYS5nZXRBdHRyaWJ1dGVOb2RlKGIpLm5vZGVWYWx1ZTtpZihiPT09InRhYkluZGV4IilyZXR1cm4oYj1hLmdldEF0dHJpYnV0ZU5vZGUoInRhYkluZGV4IikpJiZiLnNwZWNpZmllZD9iLnZhbHVlOmJiLnRlc3QoYS5ub2RlTmFtZSl8fGNiLnRlc3QoYS5ub2RlTmFtZSkmJmEuaHJlZj8wOnc7cmV0dXJuIGFbYl19aWYoIWMuc3VwcG9ydC5zdHlsZSYmZiYmYj09PSJzdHlsZSIpe2lmKGUpYS5zdHlsZS5jc3NUZXh0PSIiK2Q7cmV0dXJuIGEuc3R5bGUuY3NzVGV4dH1lJiZhLnNldEF0dHJpYnV0ZShiLCIiK2QpO2E9IWMuc3VwcG9ydC5ocmVmTm9ybWFsaXplZCYmZiYmaj9hLmdldEF0dHJpYnV0ZShiLDIpOmEuZ2V0QXR0cmlidXRlKGIpO3JldHVybiBhPT09bnVsbD93OmF9cmV0dXJuIGMuc3R5bGUoYSxiLGQpfX0pO3ZhciBPPS9cLiguKikkLyxkYj1mdW5jdGlvbihhKXtyZXR1cm4gYS5yZXBsYWNlKC9bXlx3XHNcLlx8YF0vZywNCmZ1bmN0aW9uKGIpe3JldHVybiJcXCIrYn0pfTtjLmV2ZW50PXthZGQ6ZnVuY3Rpb24oYSxiLGQsZil7aWYoIShhLm5vZGVUeXBlPT09M3x8YS5ub2RlVHlwZT09PTgpKXtpZihhLnNldEludGVydmFsJiZhIT09QSYmIWEuZnJhbWVFbGVtZW50KWE9QTt2YXIgZSxqO2lmKGQuaGFuZGxlcil7ZT1kO2Q9ZS5oYW5kbGVyfWlmKCFkLmd1aWQpZC5ndWlkPWMuZ3VpZCsrO2lmKGo9Yy5kYXRhKGEpKXt2YXIgaT1qLmV2ZW50cz1qLmV2ZW50c3x8e30sbz1qLmhhbmRsZTtpZighbylqLmhhbmRsZT1vPWZ1bmN0aW9uKCl7cmV0dXJuIHR5cGVvZiBjIT09InVuZGVmaW5lZCImJiFjLmV2ZW50LnRyaWdnZXJlZD9jLmV2ZW50LmhhbmRsZS5hcHBseShvLmVsZW0sYXJndW1lbnRzKTp3fTtvLmVsZW09YTtiPWIuc3BsaXQoIiAiKTtmb3IodmFyIGssbj0wLHI7az1iW24rK107KXtqPWU/Yy5leHRlbmQoe30sZSk6e2hhbmRsZXI6ZCxkYXRhOmZ9O2lmKGsuaW5kZXhPZigiLiIpPi0xKXtyPWsuc3BsaXQoIi4iKTsNCms9ci5zaGlmdCgpO2oubmFtZXNwYWNlPXIuc2xpY2UoMCkuc29ydCgpLmpvaW4oIi4iKX1lbHNle3I9W107ai5uYW1lc3BhY2U9IiJ9ai50eXBlPWs7ai5ndWlkPWQuZ3VpZDt2YXIgdT1pW2tdLHo9Yy5ldmVudC5zcGVjaWFsW2tdfHx7fTtpZighdSl7dT1pW2tdPVtdO2lmKCF6LnNldHVwfHx6LnNldHVwLmNhbGwoYSxmLHIsbyk9PT1mYWxzZSlpZihhLmFkZEV2ZW50TGlzdGVuZXIpYS5hZGRFdmVudExpc3RlbmVyKGssbyxmYWxzZSk7ZWxzZSBhLmF0dGFjaEV2ZW50JiZhLmF0dGFjaEV2ZW50KCJvbiIrayxvKX1pZih6LmFkZCl7ei5hZGQuY2FsbChhLGopO2lmKCFqLmhhbmRsZXIuZ3VpZClqLmhhbmRsZXIuZ3VpZD1kLmd1aWR9dS5wdXNoKGopO2MuZXZlbnQuZ2xvYmFsW2tdPXRydWV9YT1udWxsfX19LGdsb2JhbDp7fSxyZW1vdmU6ZnVuY3Rpb24oYSxiLGQsZil7aWYoIShhLm5vZGVUeXBlPT09M3x8YS5ub2RlVHlwZT09PTgpKXt2YXIgZSxqPTAsaSxvLGssbixyLHUsej1jLmRhdGEoYSksDQpDPXomJnouZXZlbnRzO2lmKHomJkMpe2lmKGImJmIudHlwZSl7ZD1iLmhhbmRsZXI7Yj1iLnR5cGV9aWYoIWJ8fHR5cGVvZiBiPT09InN0cmluZyImJmIuY2hhckF0KDApPT09Ii4iKXtiPWJ8fCIiO2ZvcihlIGluIEMpYy5ldmVudC5yZW1vdmUoYSxlK2IpfWVsc2V7Zm9yKGI9Yi5zcGxpdCgiICIpO2U9YltqKytdOyl7bj1lO2k9ZS5pbmRleE9mKCIuIik8MDtvPVtdO2lmKCFpKXtvPWUuc3BsaXQoIi4iKTtlPW8uc2hpZnQoKTtrPW5ldyBSZWdFeHAoIihefFxcLikiK2MubWFwKG8uc2xpY2UoMCkuc29ydCgpLGRiKS5qb2luKCJcXC4oPzouKlxcLik/IikrIihcXC58JCkiKX1pZihyPUNbZV0paWYoZCl7bj1jLmV2ZW50LnNwZWNpYWxbZV18fHt9O2ZvcihCPWZ8fDA7QjxyLmxlbmd0aDtCKyspe3U9cltCXTtpZihkLmd1aWQ9PT11Lmd1aWQpe2lmKGl8fGsudGVzdCh1Lm5hbWVzcGFjZSkpe2Y9PW51bGwmJnIuc3BsaWNlKEItLSwxKTtuLnJlbW92ZSYmbi5yZW1vdmUuY2FsbChhLHUpfWlmKGYhPQ0KbnVsbClicmVha319aWYoci5sZW5ndGg9PT0wfHxmIT1udWxsJiZyLmxlbmd0aD09PTEpe2lmKCFuLnRlYXJkb3dufHxuLnRlYXJkb3duLmNhbGwoYSxvKT09PWZhbHNlKUNhKGEsZSx6LmhhbmRsZSk7ZGVsZXRlIENbZV19fWVsc2UgZm9yKHZhciBCPTA7QjxyLmxlbmd0aDtCKyspe3U9cltCXTtpZihpfHxrLnRlc3QodS5uYW1lc3BhY2UpKXtjLmV2ZW50LnJlbW92ZShhLG4sdS5oYW5kbGVyLEIpO3Iuc3BsaWNlKEItLSwxKX19fWlmKGMuaXNFbXB0eU9iamVjdChDKSl7aWYoYj16LmhhbmRsZSliLmVsZW09bnVsbDtkZWxldGUgei5ldmVudHM7ZGVsZXRlIHouaGFuZGxlO2MuaXNFbXB0eU9iamVjdCh6KSYmYy5yZW1vdmVEYXRhKGEpfX19fX0sdHJpZ2dlcjpmdW5jdGlvbihhLGIsZCxmKXt2YXIgZT1hLnR5cGV8fGE7aWYoIWYpe2E9dHlwZW9mIGE9PT0ib2JqZWN0Ij9hW0ddP2E6Yy5leHRlbmQoYy5FdmVudChlKSxhKTpjLkV2ZW50KGUpO2lmKGUuaW5kZXhPZigiISIpPj0wKXthLnR5cGU9DQplPWUuc2xpY2UoMCwtMSk7YS5leGNsdXNpdmU9dHJ1ZX1pZighZCl7YS5zdG9wUHJvcGFnYXRpb24oKTtjLmV2ZW50Lmdsb2JhbFtlXSYmYy5lYWNoKGMuY2FjaGUsZnVuY3Rpb24oKXt0aGlzLmV2ZW50cyYmdGhpcy5ldmVudHNbZV0mJmMuZXZlbnQudHJpZ2dlcihhLGIsdGhpcy5oYW5kbGUuZWxlbSl9KX1pZighZHx8ZC5ub2RlVHlwZT09PTN8fGQubm9kZVR5cGU9PT04KXJldHVybiB3O2EucmVzdWx0PXc7YS50YXJnZXQ9ZDtiPWMubWFrZUFycmF5KGIpO2IudW5zaGlmdChhKX1hLmN1cnJlbnRUYXJnZXQ9ZDsoZj1jLmRhdGEoZCwiaGFuZGxlIikpJiZmLmFwcGx5KGQsYik7Zj1kLnBhcmVudE5vZGV8fGQub3duZXJEb2N1bWVudDt0cnl7aWYoIShkJiZkLm5vZGVOYW1lJiZjLm5vRGF0YVtkLm5vZGVOYW1lLnRvTG93ZXJDYXNlKCldKSlpZihkWyJvbiIrZV0mJmRbIm9uIitlXS5hcHBseShkLGIpPT09ZmFsc2UpYS5yZXN1bHQ9ZmFsc2V9Y2F0Y2goail7fWlmKCFhLmlzUHJvcGFnYXRpb25TdG9wcGVkKCkmJg0KZiljLmV2ZW50LnRyaWdnZXIoYSxiLGYsdHJ1ZSk7ZWxzZSBpZighYS5pc0RlZmF1bHRQcmV2ZW50ZWQoKSl7Zj1hLnRhcmdldDt2YXIgaSxvPWMubm9kZU5hbWUoZiwiYSIpJiZlPT09ImNsaWNrIixrPWMuZXZlbnQuc3BlY2lhbFtlXXx8e307aWYoKCFrLl9kZWZhdWx0fHxrLl9kZWZhdWx0LmNhbGwoZCxhKT09PWZhbHNlKSYmIW8mJiEoZiYmZi5ub2RlTmFtZSYmYy5ub0RhdGFbZi5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpXSkpe3RyeXtpZihmW2VdKXtpZihpPWZbIm9uIitlXSlmWyJvbiIrZV09bnVsbDtjLmV2ZW50LnRyaWdnZXJlZD10cnVlO2ZbZV0oKX19Y2F0Y2gobil7fWlmKGkpZlsib24iK2VdPWk7Yy5ldmVudC50cmlnZ2VyZWQ9ZmFsc2V9fX0saGFuZGxlOmZ1bmN0aW9uKGEpe3ZhciBiLGQsZixlO2E9YXJndW1lbnRzWzBdPWMuZXZlbnQuZml4KGF8fEEuZXZlbnQpO2EuY3VycmVudFRhcmdldD10aGlzO2I9YS50eXBlLmluZGV4T2YoIi4iKTwwJiYhYS5leGNsdXNpdmU7DQppZighYil7ZD1hLnR5cGUuc3BsaXQoIi4iKTthLnR5cGU9ZC5zaGlmdCgpO2Y9bmV3IFJlZ0V4cCgiKF58XFwuKSIrZC5zbGljZSgwKS5zb3J0KCkuam9pbigiXFwuKD86LipcXC4pPyIpKyIoXFwufCQpIil9ZT1jLmRhdGEodGhpcywiZXZlbnRzIik7ZD1lW2EudHlwZV07aWYoZSYmZCl7ZD1kLnNsaWNlKDApO2U9MDtmb3IodmFyIGo9ZC5sZW5ndGg7ZTxqO2UrKyl7dmFyIGk9ZFtlXTtpZihifHxmLnRlc3QoaS5uYW1lc3BhY2UpKXthLmhhbmRsZXI9aS5oYW5kbGVyO2EuZGF0YT1pLmRhdGE7YS5oYW5kbGVPYmo9aTtpPWkuaGFuZGxlci5hcHBseSh0aGlzLGFyZ3VtZW50cyk7aWYoaSE9PXcpe2EucmVzdWx0PWk7aWYoaT09PWZhbHNlKXthLnByZXZlbnREZWZhdWx0KCk7YS5zdG9wUHJvcGFnYXRpb24oKX19aWYoYS5pc0ltbWVkaWF0ZVByb3BhZ2F0aW9uU3RvcHBlZCgpKWJyZWFrfX19cmV0dXJuIGEucmVzdWx0fSxwcm9wczoiYWx0S2V5IGF0dHJDaGFuZ2UgYXR0ck5hbWUgYnViYmxlcyBidXR0b24gY2FuY2VsYWJsZSBjaGFyQ29kZSBjbGllbnRYIGNsaWVudFkgY3RybEtleSBjdXJyZW50VGFyZ2V0IGRhdGEgZGV0YWlsIGV2ZW50UGhhc2UgZnJvbUVsZW1lbnQgaGFuZGxlciBrZXlDb2RlIGxheWVyWCBsYXllclkgbWV0YUtleSBuZXdWYWx1ZSBvZmZzZXRYIG9mZnNldFkgb3JpZ2luYWxUYXJnZXQgcGFnZVggcGFnZVkgcHJldlZhbHVlIHJlbGF0ZWROb2RlIHJlbGF0ZWRUYXJnZXQgc2NyZWVuWCBzY3JlZW5ZIHNoaWZ0S2V5IHNyY0VsZW1lbnQgdGFyZ2V0IHRvRWxlbWVudCB2aWV3IHdoZWVsRGVsdGEgd2hpY2giLnNwbGl0KCIgIiksDQpmaXg6ZnVuY3Rpb24oYSl7aWYoYVtHXSlyZXR1cm4gYTt2YXIgYj1hO2E9Yy5FdmVudChiKTtmb3IodmFyIGQ9dGhpcy5wcm9wcy5sZW5ndGgsZjtkOyl7Zj10aGlzLnByb3BzWy0tZF07YVtmXT1iW2ZdfWlmKCFhLnRhcmdldClhLnRhcmdldD1hLnNyY0VsZW1lbnR8fHM7aWYoYS50YXJnZXQubm9kZVR5cGU9PT0zKWEudGFyZ2V0PWEudGFyZ2V0LnBhcmVudE5vZGU7aWYoIWEucmVsYXRlZFRhcmdldCYmYS5mcm9tRWxlbWVudClhLnJlbGF0ZWRUYXJnZXQ9YS5mcm9tRWxlbWVudD09PWEudGFyZ2V0P2EudG9FbGVtZW50OmEuZnJvbUVsZW1lbnQ7aWYoYS5wYWdlWD09bnVsbCYmYS5jbGllbnRYIT1udWxsKXtiPXMuZG9jdW1lbnRFbGVtZW50O2Q9cy5ib2R5O2EucGFnZVg9YS5jbGllbnRYKyhiJiZiLnNjcm9sbExlZnR8fGQmJmQuc2Nyb2xsTGVmdHx8MCktKGImJmIuY2xpZW50TGVmdHx8ZCYmZC5jbGllbnRMZWZ0fHwwKTthLnBhZ2VZPWEuY2xpZW50WSsoYiYmYi5zY3JvbGxUb3B8fA0KZCYmZC5zY3JvbGxUb3B8fDApLShiJiZiLmNsaWVudFRvcHx8ZCYmZC5jbGllbnRUb3B8fDApfWlmKCFhLndoaWNoJiYoYS5jaGFyQ29kZXx8YS5jaGFyQ29kZT09PTA/YS5jaGFyQ29kZTphLmtleUNvZGUpKWEud2hpY2g9YS5jaGFyQ29kZXx8YS5rZXlDb2RlO2lmKCFhLm1ldGFLZXkmJmEuY3RybEtleSlhLm1ldGFLZXk9YS5jdHJsS2V5O2lmKCFhLndoaWNoJiZhLmJ1dHRvbiE9PXcpYS53aGljaD1hLmJ1dHRvbiYxPzE6YS5idXR0b24mMj8zOmEuYnV0dG9uJjQ/MjowO3JldHVybiBhfSxndWlkOjFFOCxwcm94eTpjLnByb3h5LHNwZWNpYWw6e3JlYWR5OntzZXR1cDpjLmJpbmRSZWFkeSx0ZWFyZG93bjpjLm5vb3B9LGxpdmU6e2FkZDpmdW5jdGlvbihhKXtjLmV2ZW50LmFkZCh0aGlzLGEub3JpZ1R5cGUsYy5leHRlbmQoe30sYSx7aGFuZGxlcjpvYX0pKX0scmVtb3ZlOmZ1bmN0aW9uKGEpe3ZhciBiPXRydWUsZD1hLm9yaWdUeXBlLnJlcGxhY2UoTywiIik7Yy5lYWNoKGMuZGF0YSh0aGlzLA0KImV2ZW50cyIpLmxpdmV8fFtdLGZ1bmN0aW9uKCl7aWYoZD09PXRoaXMub3JpZ1R5cGUucmVwbGFjZShPLCIiKSlyZXR1cm4gYj1mYWxzZX0pO2ImJmMuZXZlbnQucmVtb3ZlKHRoaXMsYS5vcmlnVHlwZSxvYSl9fSxiZWZvcmV1bmxvYWQ6e3NldHVwOmZ1bmN0aW9uKGEsYixkKXtpZih0aGlzLnNldEludGVydmFsKXRoaXMub25iZWZvcmV1bmxvYWQ9ZDtyZXR1cm4gZmFsc2V9LHRlYXJkb3duOmZ1bmN0aW9uKGEsYil7aWYodGhpcy5vbmJlZm9yZXVubG9hZD09PWIpdGhpcy5vbmJlZm9yZXVubG9hZD1udWxsfX19fTt2YXIgQ2E9cy5yZW1vdmVFdmVudExpc3RlbmVyP2Z1bmN0aW9uKGEsYixkKXthLnJlbW92ZUV2ZW50TGlzdGVuZXIoYixkLGZhbHNlKX06ZnVuY3Rpb24oYSxiLGQpe2EuZGV0YWNoRXZlbnQoIm9uIitiLGQpfTtjLkV2ZW50PWZ1bmN0aW9uKGEpe2lmKCF0aGlzLnByZXZlbnREZWZhdWx0KXJldHVybiBuZXcgYy5FdmVudChhKTtpZihhJiZhLnR5cGUpe3RoaXMub3JpZ2luYWxFdmVudD0NCmE7dGhpcy50eXBlPWEudHlwZX1lbHNlIHRoaXMudHlwZT1hO3RoaXMudGltZVN0YW1wPUooKTt0aGlzW0ddPXRydWV9O2MuRXZlbnQucHJvdG90eXBlPXtwcmV2ZW50RGVmYXVsdDpmdW5jdGlvbigpe3RoaXMuaXNEZWZhdWx0UHJldmVudGVkPVo7dmFyIGE9dGhpcy5vcmlnaW5hbEV2ZW50O2lmKGEpe2EucHJldmVudERlZmF1bHQmJmEucHJldmVudERlZmF1bHQoKTthLnJldHVyblZhbHVlPWZhbHNlfX0sc3RvcFByb3BhZ2F0aW9uOmZ1bmN0aW9uKCl7dGhpcy5pc1Byb3BhZ2F0aW9uU3RvcHBlZD1aO3ZhciBhPXRoaXMub3JpZ2luYWxFdmVudDtpZihhKXthLnN0b3BQcm9wYWdhdGlvbiYmYS5zdG9wUHJvcGFnYXRpb24oKTthLmNhbmNlbEJ1YmJsZT10cnVlfX0sc3RvcEltbWVkaWF0ZVByb3BhZ2F0aW9uOmZ1bmN0aW9uKCl7dGhpcy5pc0ltbWVkaWF0ZVByb3BhZ2F0aW9uU3RvcHBlZD1aO3RoaXMuc3RvcFByb3BhZ2F0aW9uKCl9LGlzRGVmYXVsdFByZXZlbnRlZDpZLGlzUHJvcGFnYXRpb25TdG9wcGVkOlksDQppc0ltbWVkaWF0ZVByb3BhZ2F0aW9uU3RvcHBlZDpZfTt2YXIgRGE9ZnVuY3Rpb24oYSl7dmFyIGI9YS5yZWxhdGVkVGFyZ2V0O3RyeXtmb3IoO2ImJmIhPT10aGlzOyliPWIucGFyZW50Tm9kZTtpZihiIT09dGhpcyl7YS50eXBlPWEuZGF0YTtjLmV2ZW50LmhhbmRsZS5hcHBseSh0aGlzLGFyZ3VtZW50cyl9fWNhdGNoKGQpe319LEVhPWZ1bmN0aW9uKGEpe2EudHlwZT1hLmRhdGE7Yy5ldmVudC5oYW5kbGUuYXBwbHkodGhpcyxhcmd1bWVudHMpfTtjLmVhY2goe21vdXNlZW50ZXI6Im1vdXNlb3ZlciIsbW91c2VsZWF2ZToibW91c2VvdXQifSxmdW5jdGlvbihhLGIpe2MuZXZlbnQuc3BlY2lhbFthXT17c2V0dXA6ZnVuY3Rpb24oZCl7Yy5ldmVudC5hZGQodGhpcyxiLGQmJmQuc2VsZWN0b3I/RWE6RGEsYSl9LHRlYXJkb3duOmZ1bmN0aW9uKGQpe2MuZXZlbnQucmVtb3ZlKHRoaXMsYixkJiZkLnNlbGVjdG9yP0VhOkRhKX19fSk7aWYoIWMuc3VwcG9ydC5zdWJtaXRCdWJibGVzKWMuZXZlbnQuc3BlY2lhbC5zdWJtaXQ9DQp7c2V0dXA6ZnVuY3Rpb24oKXtpZih0aGlzLm5vZGVOYW1lLnRvTG93ZXJDYXNlKCkhPT0iZm9ybSIpe2MuZXZlbnQuYWRkKHRoaXMsImNsaWNrLnNwZWNpYWxTdWJtaXQiLGZ1bmN0aW9uKGEpe3ZhciBiPWEudGFyZ2V0LGQ9Yi50eXBlO2lmKChkPT09InN1Ym1pdCJ8fGQ9PT0iaW1hZ2UiKSYmYyhiKS5jbG9zZXN0KCJmb3JtIikubGVuZ3RoKXJldHVybiBuYSgic3VibWl0Iix0aGlzLGFyZ3VtZW50cyl9KTtjLmV2ZW50LmFkZCh0aGlzLCJrZXlwcmVzcy5zcGVjaWFsU3VibWl0IixmdW5jdGlvbihhKXt2YXIgYj1hLnRhcmdldCxkPWIudHlwZTtpZigoZD09PSJ0ZXh0Inx8ZD09PSJwYXNzd29yZCIpJiZjKGIpLmNsb3Nlc3QoImZvcm0iKS5sZW5ndGgmJmEua2V5Q29kZT09PTEzKXJldHVybiBuYSgic3VibWl0Iix0aGlzLGFyZ3VtZW50cyl9KX1lbHNlIHJldHVybiBmYWxzZX0sdGVhcmRvd246ZnVuY3Rpb24oKXtjLmV2ZW50LnJlbW92ZSh0aGlzLCIuc3BlY2lhbFN1Ym1pdCIpfX07DQppZighYy5zdXBwb3J0LmNoYW5nZUJ1YmJsZXMpe3ZhciBkYT0vdGV4dGFyZWF8aW5wdXR8c2VsZWN0L2ksZWEsRmE9ZnVuY3Rpb24oYSl7dmFyIGI9YS50eXBlLGQ9YS52YWx1ZTtpZihiPT09InJhZGlvInx8Yj09PSJjaGVja2JveCIpZD1hLmNoZWNrZWQ7ZWxzZSBpZihiPT09InNlbGVjdC1tdWx0aXBsZSIpZD1hLnNlbGVjdGVkSW5kZXg+LTE/Yy5tYXAoYS5vcHRpb25zLGZ1bmN0aW9uKGYpe3JldHVybiBmLnNlbGVjdGVkfSkuam9pbigiLSIpOiIiO2Vsc2UgaWYoYS5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpPT09InNlbGVjdCIpZD1hLnNlbGVjdGVkSW5kZXg7cmV0dXJuIGR9LGZhPWZ1bmN0aW9uKGEsYil7dmFyIGQ9YS50YXJnZXQsZixlO2lmKCEoIWRhLnRlc3QoZC5ub2RlTmFtZSl8fGQucmVhZE9ubHkpKXtmPWMuZGF0YShkLCJfY2hhbmdlX2RhdGEiKTtlPUZhKGQpO2lmKGEudHlwZSE9PSJmb2N1c291dCJ8fGQudHlwZSE9PSJyYWRpbyIpYy5kYXRhKGQsIl9jaGFuZ2VfZGF0YSIsDQplKTtpZighKGY9PT13fHxlPT09ZikpaWYoZiE9bnVsbHx8ZSl7YS50eXBlPSJjaGFuZ2UiO3JldHVybiBjLmV2ZW50LnRyaWdnZXIoYSxiLGQpfX19O2MuZXZlbnQuc3BlY2lhbC5jaGFuZ2U9e2ZpbHRlcnM6e2ZvY3Vzb3V0OmZhLGNsaWNrOmZ1bmN0aW9uKGEpe3ZhciBiPWEudGFyZ2V0LGQ9Yi50eXBlO2lmKGQ9PT0icmFkaW8ifHxkPT09ImNoZWNrYm94Inx8Yi5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpPT09InNlbGVjdCIpcmV0dXJuIGZhLmNhbGwodGhpcyxhKX0sa2V5ZG93bjpmdW5jdGlvbihhKXt2YXIgYj1hLnRhcmdldCxkPWIudHlwZTtpZihhLmtleUNvZGU9PT0xMyYmYi5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpIT09InRleHRhcmVhInx8YS5rZXlDb2RlPT09MzImJihkPT09ImNoZWNrYm94Inx8ZD09PSJyYWRpbyIpfHxkPT09InNlbGVjdC1tdWx0aXBsZSIpcmV0dXJuIGZhLmNhbGwodGhpcyxhKX0sYmVmb3JlYWN0aXZhdGU6ZnVuY3Rpb24oYSl7YT1hLnRhcmdldDtjLmRhdGEoYSwNCiJfY2hhbmdlX2RhdGEiLEZhKGEpKX19LHNldHVwOmZ1bmN0aW9uKCl7aWYodGhpcy50eXBlPT09ImZpbGUiKXJldHVybiBmYWxzZTtmb3IodmFyIGEgaW4gZWEpYy5ldmVudC5hZGQodGhpcyxhKyIuc3BlY2lhbENoYW5nZSIsZWFbYV0pO3JldHVybiBkYS50ZXN0KHRoaXMubm9kZU5hbWUpfSx0ZWFyZG93bjpmdW5jdGlvbigpe2MuZXZlbnQucmVtb3ZlKHRoaXMsIi5zcGVjaWFsQ2hhbmdlIik7cmV0dXJuIGRhLnRlc3QodGhpcy5ub2RlTmFtZSl9fTtlYT1jLmV2ZW50LnNwZWNpYWwuY2hhbmdlLmZpbHRlcnN9cy5hZGRFdmVudExpc3RlbmVyJiZjLmVhY2goe2ZvY3VzOiJmb2N1c2luIixibHVyOiJmb2N1c291dCJ9LGZ1bmN0aW9uKGEsYil7ZnVuY3Rpb24gZChmKXtmPWMuZXZlbnQuZml4KGYpO2YudHlwZT1iO3JldHVybiBjLmV2ZW50LmhhbmRsZS5jYWxsKHRoaXMsZil9Yy5ldmVudC5zcGVjaWFsW2JdPXtzZXR1cDpmdW5jdGlvbigpe3RoaXMuYWRkRXZlbnRMaXN0ZW5lcihhLA0KZCx0cnVlKX0sdGVhcmRvd246ZnVuY3Rpb24oKXt0aGlzLnJlbW92ZUV2ZW50TGlzdGVuZXIoYSxkLHRydWUpfX19KTtjLmVhY2goWyJiaW5kIiwib25lIl0sZnVuY3Rpb24oYSxiKXtjLmZuW2JdPWZ1bmN0aW9uKGQsZixlKXtpZih0eXBlb2YgZD09PSJvYmplY3QiKXtmb3IodmFyIGogaW4gZCl0aGlzW2JdKGosZixkW2pdLGUpO3JldHVybiB0aGlzfWlmKGMuaXNGdW5jdGlvbihmKSl7ZT1mO2Y9d312YXIgaT1iPT09Im9uZSI/Yy5wcm94eShlLGZ1bmN0aW9uKGspe2ModGhpcykudW5iaW5kKGssaSk7cmV0dXJuIGUuYXBwbHkodGhpcyxhcmd1bWVudHMpfSk6ZTtpZihkPT09InVubG9hZCImJmIhPT0ib25lIil0aGlzLm9uZShkLGYsZSk7ZWxzZXtqPTA7Zm9yKHZhciBvPXRoaXMubGVuZ3RoO2o8bztqKyspYy5ldmVudC5hZGQodGhpc1tqXSxkLGksZil9cmV0dXJuIHRoaXN9fSk7Yy5mbi5leHRlbmQoe3VuYmluZDpmdW5jdGlvbihhLGIpe2lmKHR5cGVvZiBhPT09Im9iamVjdCImJg0KIWEucHJldmVudERlZmF1bHQpZm9yKHZhciBkIGluIGEpdGhpcy51bmJpbmQoZCxhW2RdKTtlbHNle2Q9MDtmb3IodmFyIGY9dGhpcy5sZW5ndGg7ZDxmO2QrKyljLmV2ZW50LnJlbW92ZSh0aGlzW2RdLGEsYil9cmV0dXJuIHRoaXN9LGRlbGVnYXRlOmZ1bmN0aW9uKGEsYixkLGYpe3JldHVybiB0aGlzLmxpdmUoYixkLGYsYSl9LHVuZGVsZWdhdGU6ZnVuY3Rpb24oYSxiLGQpe3JldHVybiBhcmd1bWVudHMubGVuZ3RoPT09MD90aGlzLnVuYmluZCgibGl2ZSIpOnRoaXMuZGllKGIsbnVsbCxkLGEpfSx0cmlnZ2VyOmZ1bmN0aW9uKGEsYil7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe2MuZXZlbnQudHJpZ2dlcihhLGIsdGhpcyl9KX0sdHJpZ2dlckhhbmRsZXI6ZnVuY3Rpb24oYSxiKXtpZih0aGlzWzBdKXthPWMuRXZlbnQoYSk7YS5wcmV2ZW50RGVmYXVsdCgpO2Euc3RvcFByb3BhZ2F0aW9uKCk7Yy5ldmVudC50cmlnZ2VyKGEsYix0aGlzWzBdKTtyZXR1cm4gYS5yZXN1bHR9fSwNCnRvZ2dsZTpmdW5jdGlvbihhKXtmb3IodmFyIGI9YXJndW1lbnRzLGQ9MTtkPGIubGVuZ3RoOyljLnByb3h5KGEsYltkKytdKTtyZXR1cm4gdGhpcy5jbGljayhjLnByb3h5KGEsZnVuY3Rpb24oZil7dmFyIGU9KGMuZGF0YSh0aGlzLCJsYXN0VG9nZ2xlIithLmd1aWQpfHwwKSVkO2MuZGF0YSh0aGlzLCJsYXN0VG9nZ2xlIithLmd1aWQsZSsxKTtmLnByZXZlbnREZWZhdWx0KCk7cmV0dXJuIGJbZV0uYXBwbHkodGhpcyxhcmd1bWVudHMpfHxmYWxzZX0pKX0saG92ZXI6ZnVuY3Rpb24oYSxiKXtyZXR1cm4gdGhpcy5tb3VzZWVudGVyKGEpLm1vdXNlbGVhdmUoYnx8YSl9fSk7dmFyIEdhPXtmb2N1czoiZm9jdXNpbiIsYmx1cjoiZm9jdXNvdXQiLG1vdXNlZW50ZXI6Im1vdXNlb3ZlciIsbW91c2VsZWF2ZToibW91c2VvdXQifTtjLmVhY2goWyJsaXZlIiwiZGllIl0sZnVuY3Rpb24oYSxiKXtjLmZuW2JdPWZ1bmN0aW9uKGQsZixlLGope3ZhciBpLG89MCxrLG4scj1qfHx0aGlzLnNlbGVjdG9yLA0KdT1qP3RoaXM6Yyh0aGlzLmNvbnRleHQpO2lmKGMuaXNGdW5jdGlvbihmKSl7ZT1mO2Y9d31mb3IoZD0oZHx8IiIpLnNwbGl0KCIgIik7KGk9ZFtvKytdKSE9bnVsbDspe2o9Ty5leGVjKGkpO2s9IiI7aWYoail7az1qWzBdO2k9aS5yZXBsYWNlKE8sIiIpfWlmKGk9PT0iaG92ZXIiKWQucHVzaCgibW91c2VlbnRlciIraywibW91c2VsZWF2ZSIrayk7ZWxzZXtuPWk7aWYoaT09PSJmb2N1cyJ8fGk9PT0iYmx1ciIpe2QucHVzaChHYVtpXStrKTtpKz1rfWVsc2UgaT0oR2FbaV18fGkpK2s7Yj09PSJsaXZlIj91LmVhY2goZnVuY3Rpb24oKXtjLmV2ZW50LmFkZCh0aGlzLHBhKGkscikse2RhdGE6ZixzZWxlY3RvcjpyLGhhbmRsZXI6ZSxvcmlnVHlwZTppLG9yaWdIYW5kbGVyOmUscHJlVHlwZTpufSl9KTp1LnVuYmluZChwYShpLHIpLGUpfX1yZXR1cm4gdGhpc319KTtjLmVhY2goImJsdXIgZm9jdXMgZm9jdXNpbiBmb2N1c291dCBsb2FkIHJlc2l6ZSBzY3JvbGwgdW5sb2FkIGNsaWNrIGRibGNsaWNrIG1vdXNlZG93biBtb3VzZXVwIG1vdXNlbW92ZSBtb3VzZW92ZXIgbW91c2VvdXQgbW91c2VlbnRlciBtb3VzZWxlYXZlIGNoYW5nZSBzZWxlY3Qgc3VibWl0IGtleWRvd24ga2V5cHJlc3Mga2V5dXAgZXJyb3IiLnNwbGl0KCIgIiksDQpmdW5jdGlvbihhLGIpe2MuZm5bYl09ZnVuY3Rpb24oZCl7cmV0dXJuIGQ/dGhpcy5iaW5kKGIsZCk6dGhpcy50cmlnZ2VyKGIpfTtpZihjLmF0dHJGbiljLmF0dHJGbltiXT10cnVlfSk7QS5hdHRhY2hFdmVudCYmIUEuYWRkRXZlbnRMaXN0ZW5lciYmQS5hdHRhY2hFdmVudCgib251bmxvYWQiLGZ1bmN0aW9uKCl7Zm9yKHZhciBhIGluIGMuY2FjaGUpaWYoYy5jYWNoZVthXS5oYW5kbGUpdHJ5e2MuZXZlbnQucmVtb3ZlKGMuY2FjaGVbYV0uaGFuZGxlLmVsZW0pfWNhdGNoKGIpe319KTsoZnVuY3Rpb24oKXtmdW5jdGlvbiBhKGcpe2Zvcih2YXIgaD0iIixsLG09MDtnW21dO20rKyl7bD1nW21dO2lmKGwubm9kZVR5cGU9PT0zfHxsLm5vZGVUeXBlPT09NCloKz1sLm5vZGVWYWx1ZTtlbHNlIGlmKGwubm9kZVR5cGUhPT04KWgrPWEobC5jaGlsZE5vZGVzKX1yZXR1cm4gaH1mdW5jdGlvbiBiKGcsaCxsLG0scSxwKXtxPTA7Zm9yKHZhciB2PW0ubGVuZ3RoO3E8djtxKyspe3ZhciB0PW1bcV07DQppZih0KXt0PXRbZ107Zm9yKHZhciB5PWZhbHNlO3Q7KXtpZih0LnNpemNhY2hlPT09bCl7eT1tW3Quc2l6c2V0XTticmVha31pZih0Lm5vZGVUeXBlPT09MSYmIXApe3Quc2l6Y2FjaGU9bDt0LnNpenNldD1xfWlmKHQubm9kZU5hbWUudG9Mb3dlckNhc2UoKT09PWgpe3k9dDticmVha310PXRbZ119bVtxXT15fX19ZnVuY3Rpb24gZChnLGgsbCxtLHEscCl7cT0wO2Zvcih2YXIgdj1tLmxlbmd0aDtxPHY7cSsrKXt2YXIgdD1tW3FdO2lmKHQpe3Q9dFtnXTtmb3IodmFyIHk9ZmFsc2U7dDspe2lmKHQuc2l6Y2FjaGU9PT1sKXt5PW1bdC5zaXpzZXRdO2JyZWFrfWlmKHQubm9kZVR5cGU9PT0xKXtpZighcCl7dC5zaXpjYWNoZT1sO3Quc2l6c2V0PXF9aWYodHlwZW9mIGghPT0ic3RyaW5nIil7aWYodD09PWgpe3k9dHJ1ZTticmVha319ZWxzZSBpZihrLmZpbHRlcihoLFt0XSkubGVuZ3RoPjApe3k9dDticmVha319dD10W2ddfW1bcV09eX19fXZhciBmPS8oKD86XCgoPzpcKFteKCldK1wpfFteKCldKykrXCl8XFsoPzpcW1teW1xdXSpcXXxbJyJdW14nIl0qWyciXXxbXltcXSciXSspK1xdfFxcLnxbXiA+K34sKFxbXFxdKykrfFs+K35dKShccyosXHMqKT8oKD86LnxccnxcbikqKS9nLA0KZT0wLGo9T2JqZWN0LnByb3RvdHlwZS50b1N0cmluZyxpPWZhbHNlLG89dHJ1ZTtbMCwwXS5zb3J0KGZ1bmN0aW9uKCl7bz1mYWxzZTtyZXR1cm4gMH0pO3ZhciBrPWZ1bmN0aW9uKGcsaCxsLG0pe2w9bHx8W107dmFyIHE9aD1ofHxzO2lmKGgubm9kZVR5cGUhPT0xJiZoLm5vZGVUeXBlIT09OSlyZXR1cm5bXTtpZighZ3x8dHlwZW9mIGchPT0ic3RyaW5nIilyZXR1cm4gbDtmb3IodmFyIHA9W10sdix0LHksUyxIPXRydWUsTT14KGgpLEk9ZzsoZi5leGVjKCIiKSx2PWYuZXhlYyhJKSkhPT1udWxsOyl7ST12WzNdO3AucHVzaCh2WzFdKTtpZih2WzJdKXtTPXZbM107YnJlYWt9fWlmKHAubGVuZ3RoPjEmJnIuZXhlYyhnKSlpZihwLmxlbmd0aD09PTImJm4ucmVsYXRpdmVbcFswXV0pdD1nYShwWzBdK3BbMV0saCk7ZWxzZSBmb3IodD1uLnJlbGF0aXZlW3BbMF1dP1toXTprKHAuc2hpZnQoKSxoKTtwLmxlbmd0aDspe2c9cC5zaGlmdCgpO2lmKG4ucmVsYXRpdmVbZ10pZys9cC5zaGlmdCgpOw0KdD1nYShnLHQpfWVsc2V7aWYoIW0mJnAubGVuZ3RoPjEmJmgubm9kZVR5cGU9PT05JiYhTSYmbi5tYXRjaC5JRC50ZXN0KHBbMF0pJiYhbi5tYXRjaC5JRC50ZXN0KHBbcC5sZW5ndGgtMV0pKXt2PWsuZmluZChwLnNoaWZ0KCksaCxNKTtoPXYuZXhwcj9rLmZpbHRlcih2LmV4cHIsdi5zZXQpWzBdOnYuc2V0WzBdfWlmKGgpe3Y9bT97ZXhwcjpwLnBvcCgpLHNldDp6KG0pfTprLmZpbmQocC5wb3AoKSxwLmxlbmd0aD09PTEmJihwWzBdPT09In4ifHxwWzBdPT09IisiKSYmaC5wYXJlbnROb2RlP2gucGFyZW50Tm9kZTpoLE0pO3Q9di5leHByP2suZmlsdGVyKHYuZXhwcix2LnNldCk6di5zZXQ7aWYocC5sZW5ndGg+MCl5PXoodCk7ZWxzZSBIPWZhbHNlO2Zvcig7cC5sZW5ndGg7KXt2YXIgRD1wLnBvcCgpO3Y9RDtpZihuLnJlbGF0aXZlW0RdKXY9cC5wb3AoKTtlbHNlIEQ9IiI7aWYodj09bnVsbCl2PWg7bi5yZWxhdGl2ZVtEXSh5LHYsTSl9fWVsc2UgeT1bXX15fHwoeT10KTt5fHxrLmVycm9yKER8fA0KZyk7aWYoai5jYWxsKHkpPT09IltvYmplY3QgQXJyYXldIilpZihIKWlmKGgmJmgubm9kZVR5cGU9PT0xKWZvcihnPTA7eVtnXSE9bnVsbDtnKyspe2lmKHlbZ10mJih5W2ddPT09dHJ1ZXx8eVtnXS5ub2RlVHlwZT09PTEmJkUoaCx5W2ddKSkpbC5wdXNoKHRbZ10pfWVsc2UgZm9yKGc9MDt5W2ddIT1udWxsO2crKyl5W2ddJiZ5W2ddLm5vZGVUeXBlPT09MSYmbC5wdXNoKHRbZ10pO2Vsc2UgbC5wdXNoLmFwcGx5KGwseSk7ZWxzZSB6KHksbCk7aWYoUyl7ayhTLHEsbCxtKTtrLnVuaXF1ZVNvcnQobCl9cmV0dXJuIGx9O2sudW5pcXVlU29ydD1mdW5jdGlvbihnKXtpZihCKXtpPW87Zy5zb3J0KEIpO2lmKGkpZm9yKHZhciBoPTE7aDxnLmxlbmd0aDtoKyspZ1toXT09PWdbaC0xXSYmZy5zcGxpY2UoaC0tLDEpfXJldHVybiBnfTtrLm1hdGNoZXM9ZnVuY3Rpb24oZyxoKXtyZXR1cm4gayhnLG51bGwsbnVsbCxoKX07ay5maW5kPWZ1bmN0aW9uKGcsaCxsKXt2YXIgbSxxO2lmKCFnKXJldHVybltdOw0KZm9yKHZhciBwPTAsdj1uLm9yZGVyLmxlbmd0aDtwPHY7cCsrKXt2YXIgdD1uLm9yZGVyW3BdO2lmKHE9bi5sZWZ0TWF0Y2hbdF0uZXhlYyhnKSl7dmFyIHk9cVsxXTtxLnNwbGljZSgxLDEpO2lmKHkuc3Vic3RyKHkubGVuZ3RoLTEpIT09IlxcIil7cVsxXT0ocVsxXXx8IiIpLnJlcGxhY2UoL1xcL2csIiIpO209bi5maW5kW3RdKHEsaCxsKTtpZihtIT1udWxsKXtnPWcucmVwbGFjZShuLm1hdGNoW3RdLCIiKTticmVha319fX1tfHwobT1oLmdldEVsZW1lbnRzQnlUYWdOYW1lKCIqIikpO3JldHVybntzZXQ6bSxleHByOmd9fTtrLmZpbHRlcj1mdW5jdGlvbihnLGgsbCxtKXtmb3IodmFyIHE9ZyxwPVtdLHY9aCx0LHksUz1oJiZoWzBdJiZ4KGhbMF0pO2cmJmgubGVuZ3RoOyl7Zm9yKHZhciBIIGluIG4uZmlsdGVyKWlmKCh0PW4ubGVmdE1hdGNoW0hdLmV4ZWMoZykpIT1udWxsJiZ0WzJdKXt2YXIgTT1uLmZpbHRlcltIXSxJLEQ7RD10WzFdO3k9ZmFsc2U7dC5zcGxpY2UoMSwxKTtpZihELnN1YnN0cihELmxlbmd0aC0NCjEpIT09IlxcIil7aWYodj09PXApcD1bXTtpZihuLnByZUZpbHRlcltIXSlpZih0PW4ucHJlRmlsdGVyW0hdKHQsdixsLHAsbSxTKSl7aWYodD09PXRydWUpY29udGludWV9ZWxzZSB5PUk9dHJ1ZTtpZih0KWZvcih2YXIgVT0wOyhEPXZbVV0pIT1udWxsO1UrKylpZihEKXtJPU0oRCx0LFUsdik7dmFyIEhhPW1eISFJO2lmKGwmJkkhPW51bGwpaWYoSGEpeT10cnVlO2Vsc2UgdltVXT1mYWxzZTtlbHNlIGlmKEhhKXtwLnB1c2goRCk7eT10cnVlfX1pZihJIT09dyl7bHx8KHY9cCk7Zz1nLnJlcGxhY2Uobi5tYXRjaFtIXSwiIik7aWYoIXkpcmV0dXJuW107YnJlYWt9fX1pZihnPT09cSlpZih5PT1udWxsKWsuZXJyb3IoZyk7ZWxzZSBicmVhaztxPWd9cmV0dXJuIHZ9O2suZXJyb3I9ZnVuY3Rpb24oZyl7dGhyb3ciU3ludGF4IGVycm9yLCB1bnJlY29nbml6ZWQgZXhwcmVzc2lvbjogIitnO307dmFyIG49ay5zZWxlY3RvcnM9e29yZGVyOlsiSUQiLCJOQU1FIiwiVEFHIl0sbWF0Y2g6e0lEOi8jKCg/Oltcd1x1MDBjMC1cdUZGRkYtXXxcXC4pKykvLA0KQ0xBU1M6L1wuKCg/Oltcd1x1MDBjMC1cdUZGRkYtXXxcXC4pKykvLE5BTUU6L1xbbmFtZT1bJyJdKigoPzpbXHdcdTAwYzAtXHVGRkZGLV18XFwuKSspWyciXSpcXS8sQVRUUjovXFtccyooKD86W1x3XHUwMGMwLVx1RkZGRi1dfFxcLikrKVxzKig/OihcUz89KVxzKihbJyJdKikoLio/KVwzfClccypcXS8sVEFHOi9eKCg/Oltcd1x1MDBjMC1cdUZGRkZcKi1dfFxcLikrKS8sQ0hJTEQ6Lzoob25seXxudGh8bGFzdHxmaXJzdCktY2hpbGQoPzpcKChldmVufG9kZHxbXGRuKy1dKilcKSk/LyxQT1M6LzoobnRofGVxfGd0fGx0fGZpcnN0fGxhc3R8ZXZlbnxvZGQpKD86XCgoXGQqKVwpKT8oPz1bXi1dfCQpLyxQU0VVRE86LzooKD86W1x3XHUwMGMwLVx1RkZGRi1dfFxcLikrKSg/OlwoKFsnIl0/KSgoPzpcKFteXCldK1wpfFteXChcKV0qKSspXDJcKSk/L30sbGVmdE1hdGNoOnt9LGF0dHJNYXA6eyJjbGFzcyI6ImNsYXNzTmFtZSIsImZvciI6Imh0bWxGb3IifSxhdHRySGFuZGxlOntocmVmOmZ1bmN0aW9uKGcpe3JldHVybiBnLmdldEF0dHJpYnV0ZSgiaHJlZiIpfX0sDQpyZWxhdGl2ZTp7IisiOmZ1bmN0aW9uKGcsaCl7dmFyIGw9dHlwZW9mIGg9PT0ic3RyaW5nIixtPWwmJiEvXFcvLnRlc3QoaCk7bD1sJiYhbTtpZihtKWg9aC50b0xvd2VyQ2FzZSgpO209MDtmb3IodmFyIHE9Zy5sZW5ndGgscDttPHE7bSsrKWlmKHA9Z1ttXSl7Zm9yKDsocD1wLnByZXZpb3VzU2libGluZykmJnAubm9kZVR5cGUhPT0xOyk7Z1ttXT1sfHxwJiZwLm5vZGVOYW1lLnRvTG93ZXJDYXNlKCk9PT1oP3B8fGZhbHNlOnA9PT1ofWwmJmsuZmlsdGVyKGgsZyx0cnVlKX0sIj4iOmZ1bmN0aW9uKGcsaCl7dmFyIGw9dHlwZW9mIGg9PT0ic3RyaW5nIjtpZihsJiYhL1xXLy50ZXN0KGgpKXtoPWgudG9Mb3dlckNhc2UoKTtmb3IodmFyIG09MCxxPWcubGVuZ3RoO208cTttKyspe3ZhciBwPWdbbV07aWYocCl7bD1wLnBhcmVudE5vZGU7Z1ttXT1sLm5vZGVOYW1lLnRvTG93ZXJDYXNlKCk9PT1oP2w6ZmFsc2V9fX1lbHNle209MDtmb3IocT1nLmxlbmd0aDttPHE7bSsrKWlmKHA9Z1ttXSlnW21dPQ0KbD9wLnBhcmVudE5vZGU6cC5wYXJlbnROb2RlPT09aDtsJiZrLmZpbHRlcihoLGcsdHJ1ZSl9fSwiIjpmdW5jdGlvbihnLGgsbCl7dmFyIG09ZSsrLHE9ZDtpZih0eXBlb2YgaD09PSJzdHJpbmciJiYhL1xXLy50ZXN0KGgpKXt2YXIgcD1oPWgudG9Mb3dlckNhc2UoKTtxPWJ9cSgicGFyZW50Tm9kZSIsaCxtLGcscCxsKX0sIn4iOmZ1bmN0aW9uKGcsaCxsKXt2YXIgbT1lKysscT1kO2lmKHR5cGVvZiBoPT09InN0cmluZyImJiEvXFcvLnRlc3QoaCkpe3ZhciBwPWg9aC50b0xvd2VyQ2FzZSgpO3E9Yn1xKCJwcmV2aW91c1NpYmxpbmciLGgsbSxnLHAsbCl9fSxmaW5kOntJRDpmdW5jdGlvbihnLGgsbCl7aWYodHlwZW9mIGguZ2V0RWxlbWVudEJ5SWQhPT0idW5kZWZpbmVkIiYmIWwpcmV0dXJuKGc9aC5nZXRFbGVtZW50QnlJZChnWzFdKSk/W2ddOltdfSxOQU1FOmZ1bmN0aW9uKGcsaCl7aWYodHlwZW9mIGguZ2V0RWxlbWVudHNCeU5hbWUhPT0idW5kZWZpbmVkIil7dmFyIGw9W107DQpoPWguZ2V0RWxlbWVudHNCeU5hbWUoZ1sxXSk7Zm9yKHZhciBtPTAscT1oLmxlbmd0aDttPHE7bSsrKWhbbV0uZ2V0QXR0cmlidXRlKCJuYW1lIik9PT1nWzFdJiZsLnB1c2goaFttXSk7cmV0dXJuIGwubGVuZ3RoPT09MD9udWxsOmx9fSxUQUc6ZnVuY3Rpb24oZyxoKXtyZXR1cm4gaC5nZXRFbGVtZW50c0J5VGFnTmFtZShnWzFdKX19LHByZUZpbHRlcjp7Q0xBU1M6ZnVuY3Rpb24oZyxoLGwsbSxxLHApe2c9IiAiK2dbMV0ucmVwbGFjZSgvXFwvZywiIikrIiAiO2lmKHApcmV0dXJuIGc7cD0wO2Zvcih2YXIgdjsodj1oW3BdKSE9bnVsbDtwKyspaWYodilpZihxXih2LmNsYXNzTmFtZSYmKCIgIit2LmNsYXNzTmFtZSsiICIpLnJlcGxhY2UoL1tcdFxuXS9nLCIgIikuaW5kZXhPZihnKT49MCkpbHx8bS5wdXNoKHYpO2Vsc2UgaWYobCloW3BdPWZhbHNlO3JldHVybiBmYWxzZX0sSUQ6ZnVuY3Rpb24oZyl7cmV0dXJuIGdbMV0ucmVwbGFjZSgvXFwvZywiIil9LFRBRzpmdW5jdGlvbihnKXtyZXR1cm4gZ1sxXS50b0xvd2VyQ2FzZSgpfSwNCkNISUxEOmZ1bmN0aW9uKGcpe2lmKGdbMV09PT0ibnRoIil7dmFyIGg9LygtPykoXGQqKW4oKD86XCt8LSk/XGQqKS8uZXhlYyhnWzJdPT09ImV2ZW4iJiYiMm4ifHxnWzJdPT09Im9kZCImJiIybisxInx8IS9cRC8udGVzdChnWzJdKSYmIjBuKyIrZ1syXXx8Z1syXSk7Z1syXT1oWzFdKyhoWzJdfHwxKS0wO2dbM109aFszXS0wfWdbMF09ZSsrO3JldHVybiBnfSxBVFRSOmZ1bmN0aW9uKGcsaCxsLG0scSxwKXtoPWdbMV0ucmVwbGFjZSgvXFwvZywiIik7aWYoIXAmJm4uYXR0ck1hcFtoXSlnWzFdPW4uYXR0ck1hcFtoXTtpZihnWzJdPT09In49IilnWzRdPSIgIitnWzRdKyIgIjtyZXR1cm4gZ30sUFNFVURPOmZ1bmN0aW9uKGcsaCxsLG0scSl7aWYoZ1sxXT09PSJub3QiKWlmKChmLmV4ZWMoZ1szXSl8fCIiKS5sZW5ndGg+MXx8L15cdy8udGVzdChnWzNdKSlnWzNdPWsoZ1szXSxudWxsLG51bGwsaCk7ZWxzZXtnPWsuZmlsdGVyKGdbM10saCxsLHRydWVecSk7bHx8bS5wdXNoLmFwcGx5KG0sDQpnKTtyZXR1cm4gZmFsc2V9ZWxzZSBpZihuLm1hdGNoLlBPUy50ZXN0KGdbMF0pfHxuLm1hdGNoLkNISUxELnRlc3QoZ1swXSkpcmV0dXJuIHRydWU7cmV0dXJuIGd9LFBPUzpmdW5jdGlvbihnKXtnLnVuc2hpZnQodHJ1ZSk7cmV0dXJuIGd9fSxmaWx0ZXJzOntlbmFibGVkOmZ1bmN0aW9uKGcpe3JldHVybiBnLmRpc2FibGVkPT09ZmFsc2UmJmcudHlwZSE9PSJoaWRkZW4ifSxkaXNhYmxlZDpmdW5jdGlvbihnKXtyZXR1cm4gZy5kaXNhYmxlZD09PXRydWV9LGNoZWNrZWQ6ZnVuY3Rpb24oZyl7cmV0dXJuIGcuY2hlY2tlZD09PXRydWV9LHNlbGVjdGVkOmZ1bmN0aW9uKGcpe3JldHVybiBnLnNlbGVjdGVkPT09dHJ1ZX0scGFyZW50OmZ1bmN0aW9uKGcpe3JldHVybiEhZy5maXJzdENoaWxkfSxlbXB0eTpmdW5jdGlvbihnKXtyZXR1cm4hZy5maXJzdENoaWxkfSxoYXM6ZnVuY3Rpb24oZyxoLGwpe3JldHVybiEhayhsWzNdLGcpLmxlbmd0aH0saGVhZGVyOmZ1bmN0aW9uKGcpe3JldHVybi9oXGQvaS50ZXN0KGcubm9kZU5hbWUpfSwNCnRleHQ6ZnVuY3Rpb24oZyl7cmV0dXJuInRleHQiPT09Zy50eXBlfSxyYWRpbzpmdW5jdGlvbihnKXtyZXR1cm4icmFkaW8iPT09Zy50eXBlfSxjaGVja2JveDpmdW5jdGlvbihnKXtyZXR1cm4iY2hlY2tib3giPT09Zy50eXBlfSxmaWxlOmZ1bmN0aW9uKGcpe3JldHVybiJmaWxlIj09PWcudHlwZX0scGFzc3dvcmQ6ZnVuY3Rpb24oZyl7cmV0dXJuInBhc3N3b3JkIj09PWcudHlwZX0sc3VibWl0OmZ1bmN0aW9uKGcpe3JldHVybiJzdWJtaXQiPT09Zy50eXBlfSxpbWFnZTpmdW5jdGlvbihnKXtyZXR1cm4iaW1hZ2UiPT09Zy50eXBlfSxyZXNldDpmdW5jdGlvbihnKXtyZXR1cm4icmVzZXQiPT09Zy50eXBlfSxidXR0b246ZnVuY3Rpb24oZyl7cmV0dXJuImJ1dHRvbiI9PT1nLnR5cGV8fGcubm9kZU5hbWUudG9Mb3dlckNhc2UoKT09PSJidXR0b24ifSxpbnB1dDpmdW5jdGlvbihnKXtyZXR1cm4vaW5wdXR8c2VsZWN0fHRleHRhcmVhfGJ1dHRvbi9pLnRlc3QoZy5ub2RlTmFtZSl9fSwNCnNldEZpbHRlcnM6e2ZpcnN0OmZ1bmN0aW9uKGcsaCl7cmV0dXJuIGg9PT0wfSxsYXN0OmZ1bmN0aW9uKGcsaCxsLG0pe3JldHVybiBoPT09bS5sZW5ndGgtMX0sZXZlbjpmdW5jdGlvbihnLGgpe3JldHVybiBoJTI9PT0wfSxvZGQ6ZnVuY3Rpb24oZyxoKXtyZXR1cm4gaCUyPT09MX0sbHQ6ZnVuY3Rpb24oZyxoLGwpe3JldHVybiBoPGxbM10tMH0sZ3Q6ZnVuY3Rpb24oZyxoLGwpe3JldHVybiBoPmxbM10tMH0sbnRoOmZ1bmN0aW9uKGcsaCxsKXtyZXR1cm4gbFszXS0wPT09aH0sZXE6ZnVuY3Rpb24oZyxoLGwpe3JldHVybiBsWzNdLTA9PT1ofX0sZmlsdGVyOntQU0VVRE86ZnVuY3Rpb24oZyxoLGwsbSl7dmFyIHE9aFsxXSxwPW4uZmlsdGVyc1txXTtpZihwKXJldHVybiBwKGcsbCxoLG0pO2Vsc2UgaWYocT09PSJjb250YWlucyIpcmV0dXJuKGcudGV4dENvbnRlbnR8fGcuaW5uZXJUZXh0fHxhKFtnXSl8fCIiKS5pbmRleE9mKGhbM10pPj0wO2Vsc2UgaWYocT09PSJub3QiKXtoPQ0KaFszXTtsPTA7Zm9yKG09aC5sZW5ndGg7bDxtO2wrKylpZihoW2xdPT09ZylyZXR1cm4gZmFsc2U7cmV0dXJuIHRydWV9ZWxzZSBrLmVycm9yKCJTeW50YXggZXJyb3IsIHVucmVjb2duaXplZCBleHByZXNzaW9uOiAiK3EpfSxDSElMRDpmdW5jdGlvbihnLGgpe3ZhciBsPWhbMV0sbT1nO3N3aXRjaChsKXtjYXNlICJvbmx5IjpjYXNlICJmaXJzdCI6Zm9yKDttPW0ucHJldmlvdXNTaWJsaW5nOylpZihtLm5vZGVUeXBlPT09MSlyZXR1cm4gZmFsc2U7aWYobD09PSJmaXJzdCIpcmV0dXJuIHRydWU7bT1nO2Nhc2UgImxhc3QiOmZvcig7bT1tLm5leHRTaWJsaW5nOylpZihtLm5vZGVUeXBlPT09MSlyZXR1cm4gZmFsc2U7cmV0dXJuIHRydWU7Y2FzZSAibnRoIjpsPWhbMl07dmFyIHE9aFszXTtpZihsPT09MSYmcT09PTApcmV0dXJuIHRydWU7aD1oWzBdO3ZhciBwPWcucGFyZW50Tm9kZTtpZihwJiYocC5zaXpjYWNoZSE9PWh8fCFnLm5vZGVJbmRleCkpe3ZhciB2PTA7Zm9yKG09cC5maXJzdENoaWxkO207bT0NCm0ubmV4dFNpYmxpbmcpaWYobS5ub2RlVHlwZT09PTEpbS5ub2RlSW5kZXg9Kyt2O3Auc2l6Y2FjaGU9aH1nPWcubm9kZUluZGV4LXE7cmV0dXJuIGw9PT0wP2c9PT0wOmclbD09PTAmJmcvbD49MH19LElEOmZ1bmN0aW9uKGcsaCl7cmV0dXJuIGcubm9kZVR5cGU9PT0xJiZnLmdldEF0dHJpYnV0ZSgiaWQiKT09PWh9LFRBRzpmdW5jdGlvbihnLGgpe3JldHVybiBoPT09IioiJiZnLm5vZGVUeXBlPT09MXx8Zy5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpPT09aH0sQ0xBU1M6ZnVuY3Rpb24oZyxoKXtyZXR1cm4oIiAiKyhnLmNsYXNzTmFtZXx8Zy5nZXRBdHRyaWJ1dGUoImNsYXNzIikpKyIgIikuaW5kZXhPZihoKT4tMX0sQVRUUjpmdW5jdGlvbihnLGgpe3ZhciBsPWhbMV07Zz1uLmF0dHJIYW5kbGVbbF0/bi5hdHRySGFuZGxlW2xdKGcpOmdbbF0hPW51bGw/Z1tsXTpnLmdldEF0dHJpYnV0ZShsKTtsPWcrIiI7dmFyIG09aFsyXTtoPWhbNF07cmV0dXJuIGc9PW51bGw/bT09PSIhPSI6bT09PQ0KIj0iP2w9PT1oOm09PT0iKj0iP2wuaW5kZXhPZihoKT49MDptPT09In49Ij8oIiAiK2wrIiAiKS5pbmRleE9mKGgpPj0wOiFoP2wmJmchPT1mYWxzZTptPT09IiE9Ij9sIT09aDptPT09Il49Ij9sLmluZGV4T2YoaCk9PT0wOm09PT0iJD0iP2wuc3Vic3RyKGwubGVuZ3RoLWgubGVuZ3RoKT09PWg6bT09PSJ8PSI/bD09PWh8fGwuc3Vic3RyKDAsaC5sZW5ndGgrMSk9PT1oKyItIjpmYWxzZX0sUE9TOmZ1bmN0aW9uKGcsaCxsLG0pe3ZhciBxPW4uc2V0RmlsdGVyc1toWzJdXTtpZihxKXJldHVybiBxKGcsbCxoLG0pfX19LHI9bi5tYXRjaC5QT1M7Zm9yKHZhciB1IGluIG4ubWF0Y2gpe24ubWF0Y2hbdV09bmV3IFJlZ0V4cChuLm1hdGNoW3VdLnNvdXJjZSsvKD8hW15cW10qXF0pKD8hW15cKF0qXCkpLy5zb3VyY2UpO24ubGVmdE1hdGNoW3VdPW5ldyBSZWdFeHAoLyheKD86LnxccnxcbikqPykvLnNvdXJjZStuLm1hdGNoW3VdLnNvdXJjZS5yZXBsYWNlKC9cXChcZCspL2csZnVuY3Rpb24oZywNCmgpe3JldHVybiJcXCIrKGgtMCsxKX0pKX12YXIgej1mdW5jdGlvbihnLGgpe2c9QXJyYXkucHJvdG90eXBlLnNsaWNlLmNhbGwoZywwKTtpZihoKXtoLnB1c2guYXBwbHkoaCxnKTtyZXR1cm4gaH1yZXR1cm4gZ307dHJ5e0FycmF5LnByb3RvdHlwZS5zbGljZS5jYWxsKHMuZG9jdW1lbnRFbGVtZW50LmNoaWxkTm9kZXMsMCl9Y2F0Y2goQyl7ej1mdW5jdGlvbihnLGgpe2g9aHx8W107aWYoai5jYWxsKGcpPT09IltvYmplY3QgQXJyYXldIilBcnJheS5wcm90b3R5cGUucHVzaC5hcHBseShoLGcpO2Vsc2UgaWYodHlwZW9mIGcubGVuZ3RoPT09Im51bWJlciIpZm9yKHZhciBsPTAsbT1nLmxlbmd0aDtsPG07bCsrKWgucHVzaChnW2xdKTtlbHNlIGZvcihsPTA7Z1tsXTtsKyspaC5wdXNoKGdbbF0pO3JldHVybiBofX12YXIgQjtpZihzLmRvY3VtZW50RWxlbWVudC5jb21wYXJlRG9jdW1lbnRQb3NpdGlvbilCPWZ1bmN0aW9uKGcsaCl7aWYoIWcuY29tcGFyZURvY3VtZW50UG9zaXRpb258fA0KIWguY29tcGFyZURvY3VtZW50UG9zaXRpb24pe2lmKGc9PWgpaT10cnVlO3JldHVybiBnLmNvbXBhcmVEb2N1bWVudFBvc2l0aW9uPy0xOjF9Zz1nLmNvbXBhcmVEb2N1bWVudFBvc2l0aW9uKGgpJjQ/LTE6Zz09PWg/MDoxO2lmKGc9PT0wKWk9dHJ1ZTtyZXR1cm4gZ307ZWxzZSBpZigic291cmNlSW5kZXgiaW4gcy5kb2N1bWVudEVsZW1lbnQpQj1mdW5jdGlvbihnLGgpe2lmKCFnLnNvdXJjZUluZGV4fHwhaC5zb3VyY2VJbmRleCl7aWYoZz09aClpPXRydWU7cmV0dXJuIGcuc291cmNlSW5kZXg/LTE6MX1nPWcuc291cmNlSW5kZXgtaC5zb3VyY2VJbmRleDtpZihnPT09MClpPXRydWU7cmV0dXJuIGd9O2Vsc2UgaWYocy5jcmVhdGVSYW5nZSlCPWZ1bmN0aW9uKGcsaCl7aWYoIWcub3duZXJEb2N1bWVudHx8IWgub3duZXJEb2N1bWVudCl7aWYoZz09aClpPXRydWU7cmV0dXJuIGcub3duZXJEb2N1bWVudD8tMToxfXZhciBsPWcub3duZXJEb2N1bWVudC5jcmVhdGVSYW5nZSgpLG09DQpoLm93bmVyRG9jdW1lbnQuY3JlYXRlUmFuZ2UoKTtsLnNldFN0YXJ0KGcsMCk7bC5zZXRFbmQoZywwKTttLnNldFN0YXJ0KGgsMCk7bS5zZXRFbmQoaCwwKTtnPWwuY29tcGFyZUJvdW5kYXJ5UG9pbnRzKFJhbmdlLlNUQVJUX1RPX0VORCxtKTtpZihnPT09MClpPXRydWU7cmV0dXJuIGd9OyhmdW5jdGlvbigpe3ZhciBnPXMuY3JlYXRlRWxlbWVudCgiZGl2IiksaD0ic2NyaXB0IisobmV3IERhdGUpLmdldFRpbWUoKTtnLmlubmVySFRNTD0iPGEgbmFtZT0nIitoKyInLz4iO3ZhciBsPXMuZG9jdW1lbnRFbGVtZW50O2wuaW5zZXJ0QmVmb3JlKGcsbC5maXJzdENoaWxkKTtpZihzLmdldEVsZW1lbnRCeUlkKGgpKXtuLmZpbmQuSUQ9ZnVuY3Rpb24obSxxLHApe2lmKHR5cGVvZiBxLmdldEVsZW1lbnRCeUlkIT09InVuZGVmaW5lZCImJiFwKXJldHVybihxPXEuZ2V0RWxlbWVudEJ5SWQobVsxXSkpP3EuaWQ9PT1tWzFdfHx0eXBlb2YgcS5nZXRBdHRyaWJ1dGVOb2RlIT09InVuZGVmaW5lZCImJg0KcS5nZXRBdHRyaWJ1dGVOb2RlKCJpZCIpLm5vZGVWYWx1ZT09PW1bMV0/W3FdOnc6W119O24uZmlsdGVyLklEPWZ1bmN0aW9uKG0scSl7dmFyIHA9dHlwZW9mIG0uZ2V0QXR0cmlidXRlTm9kZSE9PSJ1bmRlZmluZWQiJiZtLmdldEF0dHJpYnV0ZU5vZGUoImlkIik7cmV0dXJuIG0ubm9kZVR5cGU9PT0xJiZwJiZwLm5vZGVWYWx1ZT09PXF9fWwucmVtb3ZlQ2hpbGQoZyk7bD1nPW51bGx9KSgpOyhmdW5jdGlvbigpe3ZhciBnPXMuY3JlYXRlRWxlbWVudCgiZGl2Iik7Zy5hcHBlbmRDaGlsZChzLmNyZWF0ZUNvbW1lbnQoIiIpKTtpZihnLmdldEVsZW1lbnRzQnlUYWdOYW1lKCIqIikubGVuZ3RoPjApbi5maW5kLlRBRz1mdW5jdGlvbihoLGwpe2w9bC5nZXRFbGVtZW50c0J5VGFnTmFtZShoWzFdKTtpZihoWzFdPT09IioiKXtoPVtdO2Zvcih2YXIgbT0wO2xbbV07bSsrKWxbbV0ubm9kZVR5cGU9PT0xJiZoLnB1c2gobFttXSk7bD1ofXJldHVybiBsfTtnLmlubmVySFRNTD0iPGEgaHJlZj0nIyc+PC9hPiI7DQppZihnLmZpcnN0Q2hpbGQmJnR5cGVvZiBnLmZpcnN0Q2hpbGQuZ2V0QXR0cmlidXRlIT09InVuZGVmaW5lZCImJmcuZmlyc3RDaGlsZC5nZXRBdHRyaWJ1dGUoImhyZWYiKSE9PSIjIiluLmF0dHJIYW5kbGUuaHJlZj1mdW5jdGlvbihoKXtyZXR1cm4gaC5nZXRBdHRyaWJ1dGUoImhyZWYiLDIpfTtnPW51bGx9KSgpO3MucXVlcnlTZWxlY3RvckFsbCYmZnVuY3Rpb24oKXt2YXIgZz1rLGg9cy5jcmVhdGVFbGVtZW50KCJkaXYiKTtoLmlubmVySFRNTD0iPHAgY2xhc3M9J1RFU1QnPjwvcD4iO2lmKCEoaC5xdWVyeVNlbGVjdG9yQWxsJiZoLnF1ZXJ5U2VsZWN0b3JBbGwoIi5URVNUIikubGVuZ3RoPT09MCkpe2s9ZnVuY3Rpb24obSxxLHAsdil7cT1xfHxzO2lmKCF2JiZxLm5vZGVUeXBlPT09OSYmIXgocSkpdHJ5e3JldHVybiB6KHEucXVlcnlTZWxlY3RvckFsbChtKSxwKX1jYXRjaCh0KXt9cmV0dXJuIGcobSxxLHAsdil9O2Zvcih2YXIgbCBpbiBnKWtbbF09Z1tsXTtoPW51bGx9fSgpOw0KKGZ1bmN0aW9uKCl7dmFyIGc9cy5jcmVhdGVFbGVtZW50KCJkaXYiKTtnLmlubmVySFRNTD0iPGRpdiBjbGFzcz0ndGVzdCBlJz48L2Rpdj48ZGl2IGNsYXNzPSd0ZXN0Jz48L2Rpdj4iO2lmKCEoIWcuZ2V0RWxlbWVudHNCeUNsYXNzTmFtZXx8Zy5nZXRFbGVtZW50c0J5Q2xhc3NOYW1lKCJlIikubGVuZ3RoPT09MCkpe2cubGFzdENoaWxkLmNsYXNzTmFtZT0iZSI7aWYoZy5nZXRFbGVtZW50c0J5Q2xhc3NOYW1lKCJlIikubGVuZ3RoIT09MSl7bi5vcmRlci5zcGxpY2UoMSwwLCJDTEFTUyIpO24uZmluZC5DTEFTUz1mdW5jdGlvbihoLGwsbSl7aWYodHlwZW9mIGwuZ2V0RWxlbWVudHNCeUNsYXNzTmFtZSE9PSJ1bmRlZmluZWQiJiYhbSlyZXR1cm4gbC5nZXRFbGVtZW50c0J5Q2xhc3NOYW1lKGhbMV0pfTtnPW51bGx9fX0pKCk7dmFyIEU9cy5jb21wYXJlRG9jdW1lbnRQb3NpdGlvbj9mdW5jdGlvbihnLGgpe3JldHVybiEhKGcuY29tcGFyZURvY3VtZW50UG9zaXRpb24oaCkmMTYpfToNCmZ1bmN0aW9uKGcsaCl7cmV0dXJuIGchPT1oJiYoZy5jb250YWlucz9nLmNvbnRhaW5zKGgpOnRydWUpfSx4PWZ1bmN0aW9uKGcpe3JldHVybihnPShnP2cub3duZXJEb2N1bWVudHx8ZzowKS5kb2N1bWVudEVsZW1lbnQpP2cubm9kZU5hbWUhPT0iSFRNTCI6ZmFsc2V9LGdhPWZ1bmN0aW9uKGcsaCl7dmFyIGw9W10sbT0iIixxO2ZvcihoPWgubm9kZVR5cGU/W2hdOmg7cT1uLm1hdGNoLlBTRVVETy5leGVjKGcpOyl7bSs9cVswXTtnPWcucmVwbGFjZShuLm1hdGNoLlBTRVVETywiIil9Zz1uLnJlbGF0aXZlW2ddP2crIioiOmc7cT0wO2Zvcih2YXIgcD1oLmxlbmd0aDtxPHA7cSsrKWsoZyxoW3FdLGwpO3JldHVybiBrLmZpbHRlcihtLGwpfTtjLmZpbmQ9aztjLmV4cHI9ay5zZWxlY3RvcnM7Yy5leHByWyI6Il09Yy5leHByLmZpbHRlcnM7Yy51bmlxdWU9ay51bmlxdWVTb3J0O2MudGV4dD1hO2MuaXNYTUxEb2M9eDtjLmNvbnRhaW5zPUV9KSgpO3ZhciBlYj0vVW50aWwkLyxmYj0vXig/OnBhcmVudHN8cHJldlVudGlsfHByZXZBbGwpLywNCmdiPS8sLztSPUFycmF5LnByb3RvdHlwZS5zbGljZTt2YXIgSWE9ZnVuY3Rpb24oYSxiLGQpe2lmKGMuaXNGdW5jdGlvbihiKSlyZXR1cm4gYy5ncmVwKGEsZnVuY3Rpb24oZSxqKXtyZXR1cm4hIWIuY2FsbChlLGosZSk9PT1kfSk7ZWxzZSBpZihiLm5vZGVUeXBlKXJldHVybiBjLmdyZXAoYSxmdW5jdGlvbihlKXtyZXR1cm4gZT09PWI9PT1kfSk7ZWxzZSBpZih0eXBlb2YgYj09PSJzdHJpbmciKXt2YXIgZj1jLmdyZXAoYSxmdW5jdGlvbihlKXtyZXR1cm4gZS5ub2RlVHlwZT09PTF9KTtpZihVYS50ZXN0KGIpKXJldHVybiBjLmZpbHRlcihiLGYsIWQpO2Vsc2UgYj1jLmZpbHRlcihiLGYpfXJldHVybiBjLmdyZXAoYSxmdW5jdGlvbihlKXtyZXR1cm4gYy5pbkFycmF5KGUsYik+PTA9PT1kfSl9O2MuZm4uZXh0ZW5kKHtmaW5kOmZ1bmN0aW9uKGEpe2Zvcih2YXIgYj10aGlzLnB1c2hTdGFjaygiIiwiZmluZCIsYSksZD0wLGY9MCxlPXRoaXMubGVuZ3RoO2Y8ZTtmKyspe2Q9Yi5sZW5ndGg7DQpjLmZpbmQoYSx0aGlzW2ZdLGIpO2lmKGY+MClmb3IodmFyIGo9ZDtqPGIubGVuZ3RoO2orKylmb3IodmFyIGk9MDtpPGQ7aSsrKWlmKGJbaV09PT1iW2pdKXtiLnNwbGljZShqLS0sMSk7YnJlYWt9fXJldHVybiBifSxoYXM6ZnVuY3Rpb24oYSl7dmFyIGI9YyhhKTtyZXR1cm4gdGhpcy5maWx0ZXIoZnVuY3Rpb24oKXtmb3IodmFyIGQ9MCxmPWIubGVuZ3RoO2Q8ZjtkKyspaWYoYy5jb250YWlucyh0aGlzLGJbZF0pKXJldHVybiB0cnVlfSl9LG5vdDpmdW5jdGlvbihhKXtyZXR1cm4gdGhpcy5wdXNoU3RhY2soSWEodGhpcyxhLGZhbHNlKSwibm90IixhKX0sZmlsdGVyOmZ1bmN0aW9uKGEpe3JldHVybiB0aGlzLnB1c2hTdGFjayhJYSh0aGlzLGEsdHJ1ZSksImZpbHRlciIsYSl9LGlzOmZ1bmN0aW9uKGEpe3JldHVybiEhYSYmYy5maWx0ZXIoYSx0aGlzKS5sZW5ndGg+MH0sY2xvc2VzdDpmdW5jdGlvbihhLGIpe2lmKGMuaXNBcnJheShhKSl7dmFyIGQ9W10sZj10aGlzWzBdLGUsaj0NCnt9LGk7aWYoZiYmYS5sZW5ndGgpe2U9MDtmb3IodmFyIG89YS5sZW5ndGg7ZTxvO2UrKyl7aT1hW2VdO2pbaV18fChqW2ldPWMuZXhwci5tYXRjaC5QT1MudGVzdChpKT9jKGksYnx8dGhpcy5jb250ZXh0KTppKX1mb3IoO2YmJmYub3duZXJEb2N1bWVudCYmZiE9PWI7KXtmb3IoaSBpbiBqKXtlPWpbaV07aWYoZS5qcXVlcnk/ZS5pbmRleChmKT4tMTpjKGYpLmlzKGUpKXtkLnB1c2goe3NlbGVjdG9yOmksZWxlbTpmfSk7ZGVsZXRlIGpbaV19fWY9Zi5wYXJlbnROb2RlfX1yZXR1cm4gZH12YXIgaz1jLmV4cHIubWF0Y2guUE9TLnRlc3QoYSk/YyhhLGJ8fHRoaXMuY29udGV4dCk6bnVsbDtyZXR1cm4gdGhpcy5tYXAoZnVuY3Rpb24obixyKXtmb3IoO3ImJnIub3duZXJEb2N1bWVudCYmciE9PWI7KXtpZihrP2suaW5kZXgocik+LTE6YyhyKS5pcyhhKSlyZXR1cm4gcjtyPXIucGFyZW50Tm9kZX1yZXR1cm4gbnVsbH0pfSxpbmRleDpmdW5jdGlvbihhKXtpZighYXx8dHlwZW9mIGE9PT0NCiJzdHJpbmciKXJldHVybiBjLmluQXJyYXkodGhpc1swXSxhP2MoYSk6dGhpcy5wYXJlbnQoKS5jaGlsZHJlbigpKTtyZXR1cm4gYy5pbkFycmF5KGEuanF1ZXJ5P2FbMF06YSx0aGlzKX0sYWRkOmZ1bmN0aW9uKGEsYil7YT10eXBlb2YgYT09PSJzdHJpbmciP2MoYSxifHx0aGlzLmNvbnRleHQpOmMubWFrZUFycmF5KGEpO2I9Yy5tZXJnZSh0aGlzLmdldCgpLGEpO3JldHVybiB0aGlzLnB1c2hTdGFjayhxYShhWzBdKXx8cWEoYlswXSk/YjpjLnVuaXF1ZShiKSl9LGFuZFNlbGY6ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5hZGQodGhpcy5wcmV2T2JqZWN0KX19KTtjLmVhY2goe3BhcmVudDpmdW5jdGlvbihhKXtyZXR1cm4oYT1hLnBhcmVudE5vZGUpJiZhLm5vZGVUeXBlIT09MTE/YTpudWxsfSxwYXJlbnRzOmZ1bmN0aW9uKGEpe3JldHVybiBjLmRpcihhLCJwYXJlbnROb2RlIil9LHBhcmVudHNVbnRpbDpmdW5jdGlvbihhLGIsZCl7cmV0dXJuIGMuZGlyKGEsInBhcmVudE5vZGUiLA0KZCl9LG5leHQ6ZnVuY3Rpb24oYSl7cmV0dXJuIGMubnRoKGEsMiwibmV4dFNpYmxpbmciKX0scHJldjpmdW5jdGlvbihhKXtyZXR1cm4gYy5udGgoYSwyLCJwcmV2aW91c1NpYmxpbmciKX0sbmV4dEFsbDpmdW5jdGlvbihhKXtyZXR1cm4gYy5kaXIoYSwibmV4dFNpYmxpbmciKX0scHJldkFsbDpmdW5jdGlvbihhKXtyZXR1cm4gYy5kaXIoYSwicHJldmlvdXNTaWJsaW5nIil9LG5leHRVbnRpbDpmdW5jdGlvbihhLGIsZCl7cmV0dXJuIGMuZGlyKGEsIm5leHRTaWJsaW5nIixkKX0scHJldlVudGlsOmZ1bmN0aW9uKGEsYixkKXtyZXR1cm4gYy5kaXIoYSwicHJldmlvdXNTaWJsaW5nIixkKX0sc2libGluZ3M6ZnVuY3Rpb24oYSl7cmV0dXJuIGMuc2libGluZyhhLnBhcmVudE5vZGUuZmlyc3RDaGlsZCxhKX0sY2hpbGRyZW46ZnVuY3Rpb24oYSl7cmV0dXJuIGMuc2libGluZyhhLmZpcnN0Q2hpbGQpfSxjb250ZW50czpmdW5jdGlvbihhKXtyZXR1cm4gYy5ub2RlTmFtZShhLCJpZnJhbWUiKT8NCmEuY29udGVudERvY3VtZW50fHxhLmNvbnRlbnRXaW5kb3cuZG9jdW1lbnQ6Yy5tYWtlQXJyYXkoYS5jaGlsZE5vZGVzKX19LGZ1bmN0aW9uKGEsYil7Yy5mblthXT1mdW5jdGlvbihkLGYpe3ZhciBlPWMubWFwKHRoaXMsYixkKTtlYi50ZXN0KGEpfHwoZj1kKTtpZihmJiZ0eXBlb2YgZj09PSJzdHJpbmciKWU9Yy5maWx0ZXIoZixlKTtlPXRoaXMubGVuZ3RoPjE/Yy51bmlxdWUoZSk6ZTtpZigodGhpcy5sZW5ndGg+MXx8Z2IudGVzdChmKSkmJmZiLnRlc3QoYSkpZT1lLnJldmVyc2UoKTtyZXR1cm4gdGhpcy5wdXNoU3RhY2soZSxhLFIuY2FsbChhcmd1bWVudHMpLmpvaW4oIiwiKSl9fSk7Yy5leHRlbmQoe2ZpbHRlcjpmdW5jdGlvbihhLGIsZCl7aWYoZClhPSI6bm90KCIrYSsiKSI7cmV0dXJuIGMuZmluZC5tYXRjaGVzKGEsYil9LGRpcjpmdW5jdGlvbihhLGIsZCl7dmFyIGY9W107Zm9yKGE9YVtiXTthJiZhLm5vZGVUeXBlIT09OSYmKGQ9PT13fHxhLm5vZGVUeXBlIT09MXx8IWMoYSkuaXMoZCkpOyl7YS5ub2RlVHlwZT09PQ0KMSYmZi5wdXNoKGEpO2E9YVtiXX1yZXR1cm4gZn0sbnRoOmZ1bmN0aW9uKGEsYixkKXtiPWJ8fDE7Zm9yKHZhciBmPTA7YTthPWFbZF0paWYoYS5ub2RlVHlwZT09PTEmJisrZj09PWIpYnJlYWs7cmV0dXJuIGF9LHNpYmxpbmc6ZnVuY3Rpb24oYSxiKXtmb3IodmFyIGQ9W107YTthPWEubmV4dFNpYmxpbmcpYS5ub2RlVHlwZT09PTEmJmEhPT1iJiZkLnB1c2goYSk7cmV0dXJuIGR9fSk7dmFyIEphPS8galF1ZXJ5XGQrPSIoPzpcZCt8bnVsbCkiL2csVj0vXlxzKy8sS2E9Lyg8KFtcdzpdKylbXj5dKj8pXC8+L2csaGI9L14oPzphcmVhfGJyfGNvbHxlbWJlZHxocnxpbWd8aW5wdXR8bGlua3xtZXRhfHBhcmFtKSQvaSxMYT0vPChbXHc6XSspLyxpYj0vPHRib2R5L2ksamI9Lzx8JiM/XHcrOy8sdGE9LzxzY3JpcHR8PG9iamVjdHw8ZW1iZWR8PG9wdGlvbnw8c3R5bGUvaSx1YT0vY2hlY2tlZFxzKig/OltePV18PVxzKi5jaGVja2VkLikvaSxNYT1mdW5jdGlvbihhLGIsZCl7cmV0dXJuIGhiLnRlc3QoZCk/DQphOmIrIj48LyIrZCsiPiJ9LEY9e29wdGlvbjpbMSwiPHNlbGVjdCBtdWx0aXBsZT0nbXVsdGlwbGUnPiIsIjwvc2VsZWN0PiJdLGxlZ2VuZDpbMSwiPGZpZWxkc2V0PiIsIjwvZmllbGRzZXQ+Il0sdGhlYWQ6WzEsIjx0YWJsZT4iLCI8L3RhYmxlPiJdLHRyOlsyLCI8dGFibGU+PHRib2R5PiIsIjwvdGJvZHk+PC90YWJsZT4iXSx0ZDpbMywiPHRhYmxlPjx0Ym9keT48dHI+IiwiPC90cj48L3Rib2R5PjwvdGFibGU+Il0sY29sOlsyLCI8dGFibGU+PHRib2R5PjwvdGJvZHk+PGNvbGdyb3VwPiIsIjwvY29sZ3JvdXA+PC90YWJsZT4iXSxhcmVhOlsxLCI8bWFwPiIsIjwvbWFwPiJdLF9kZWZhdWx0OlswLCIiLCIiXX07Ri5vcHRncm91cD1GLm9wdGlvbjtGLnRib2R5PUYudGZvb3Q9Ri5jb2xncm91cD1GLmNhcHRpb249Ri50aGVhZDtGLnRoPUYudGQ7aWYoIWMuc3VwcG9ydC5odG1sU2VyaWFsaXplKUYuX2RlZmF1bHQ9WzEsImRpdjxkaXY+IiwiPC9kaXY+Il07Yy5mbi5leHRlbmQoe3RleHQ6ZnVuY3Rpb24oYSl7aWYoYy5pc0Z1bmN0aW9uKGEpKXJldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oYil7dmFyIGQ9DQpjKHRoaXMpO2QudGV4dChhLmNhbGwodGhpcyxiLGQudGV4dCgpKSl9KTtpZih0eXBlb2YgYSE9PSJvYmplY3QiJiZhIT09dylyZXR1cm4gdGhpcy5lbXB0eSgpLmFwcGVuZCgodGhpc1swXSYmdGhpc1swXS5vd25lckRvY3VtZW50fHxzKS5jcmVhdGVUZXh0Tm9kZShhKSk7cmV0dXJuIGMudGV4dCh0aGlzKX0sd3JhcEFsbDpmdW5jdGlvbihhKXtpZihjLmlzRnVuY3Rpb24oYSkpcmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbihkKXtjKHRoaXMpLndyYXBBbGwoYS5jYWxsKHRoaXMsZCkpfSk7aWYodGhpc1swXSl7dmFyIGI9YyhhLHRoaXNbMF0ub3duZXJEb2N1bWVudCkuZXEoMCkuY2xvbmUodHJ1ZSk7dGhpc1swXS5wYXJlbnROb2RlJiZiLmluc2VydEJlZm9yZSh0aGlzWzBdKTtiLm1hcChmdW5jdGlvbigpe2Zvcih2YXIgZD10aGlzO2QuZmlyc3RDaGlsZCYmZC5maXJzdENoaWxkLm5vZGVUeXBlPT09MTspZD1kLmZpcnN0Q2hpbGQ7cmV0dXJuIGR9KS5hcHBlbmQodGhpcyl9cmV0dXJuIHRoaXN9LA0Kd3JhcElubmVyOmZ1bmN0aW9uKGEpe2lmKGMuaXNGdW5jdGlvbihhKSlyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKGIpe2ModGhpcykud3JhcElubmVyKGEuY2FsbCh0aGlzLGIpKX0pO3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgYj1jKHRoaXMpLGQ9Yi5jb250ZW50cygpO2QubGVuZ3RoP2Qud3JhcEFsbChhKTpiLmFwcGVuZChhKX0pfSx3cmFwOmZ1bmN0aW9uKGEpe3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXtjKHRoaXMpLndyYXBBbGwoYSl9KX0sdW53cmFwOmZ1bmN0aW9uKCl7cmV0dXJuIHRoaXMucGFyZW50KCkuZWFjaChmdW5jdGlvbigpe2Mubm9kZU5hbWUodGhpcywiYm9keSIpfHxjKHRoaXMpLnJlcGxhY2VXaXRoKHRoaXMuY2hpbGROb2Rlcyl9KS5lbmQoKX0sYXBwZW5kOmZ1bmN0aW9uKCl7cmV0dXJuIHRoaXMuZG9tTWFuaXAoYXJndW1lbnRzLHRydWUsZnVuY3Rpb24oYSl7dGhpcy5ub2RlVHlwZT09PTEmJnRoaXMuYXBwZW5kQ2hpbGQoYSl9KX0sDQpwcmVwZW5kOmZ1bmN0aW9uKCl7cmV0dXJuIHRoaXMuZG9tTWFuaXAoYXJndW1lbnRzLHRydWUsZnVuY3Rpb24oYSl7dGhpcy5ub2RlVHlwZT09PTEmJnRoaXMuaW5zZXJ0QmVmb3JlKGEsdGhpcy5maXJzdENoaWxkKX0pfSxiZWZvcmU6ZnVuY3Rpb24oKXtpZih0aGlzWzBdJiZ0aGlzWzBdLnBhcmVudE5vZGUpcmV0dXJuIHRoaXMuZG9tTWFuaXAoYXJndW1lbnRzLGZhbHNlLGZ1bmN0aW9uKGIpe3RoaXMucGFyZW50Tm9kZS5pbnNlcnRCZWZvcmUoYix0aGlzKX0pO2Vsc2UgaWYoYXJndW1lbnRzLmxlbmd0aCl7dmFyIGE9Yyhhcmd1bWVudHNbMF0pO2EucHVzaC5hcHBseShhLHRoaXMudG9BcnJheSgpKTtyZXR1cm4gdGhpcy5wdXNoU3RhY2soYSwiYmVmb3JlIixhcmd1bWVudHMpfX0sYWZ0ZXI6ZnVuY3Rpb24oKXtpZih0aGlzWzBdJiZ0aGlzWzBdLnBhcmVudE5vZGUpcmV0dXJuIHRoaXMuZG9tTWFuaXAoYXJndW1lbnRzLGZhbHNlLGZ1bmN0aW9uKGIpe3RoaXMucGFyZW50Tm9kZS5pbnNlcnRCZWZvcmUoYiwNCnRoaXMubmV4dFNpYmxpbmcpfSk7ZWxzZSBpZihhcmd1bWVudHMubGVuZ3RoKXt2YXIgYT10aGlzLnB1c2hTdGFjayh0aGlzLCJhZnRlciIsYXJndW1lbnRzKTthLnB1c2guYXBwbHkoYSxjKGFyZ3VtZW50c1swXSkudG9BcnJheSgpKTtyZXR1cm4gYX19LHJlbW92ZTpmdW5jdGlvbihhLGIpe2Zvcih2YXIgZD0wLGY7KGY9dGhpc1tkXSkhPW51bGw7ZCsrKWlmKCFhfHxjLmZpbHRlcihhLFtmXSkubGVuZ3RoKXtpZighYiYmZi5ub2RlVHlwZT09PTEpe2MuY2xlYW5EYXRhKGYuZ2V0RWxlbWVudHNCeVRhZ05hbWUoIioiKSk7Yy5jbGVhbkRhdGEoW2ZdKX1mLnBhcmVudE5vZGUmJmYucGFyZW50Tm9kZS5yZW1vdmVDaGlsZChmKX1yZXR1cm4gdGhpc30sZW1wdHk6ZnVuY3Rpb24oKXtmb3IodmFyIGE9MCxiOyhiPXRoaXNbYV0pIT1udWxsO2ErKylmb3IoYi5ub2RlVHlwZT09PTEmJmMuY2xlYW5EYXRhKGIuZ2V0RWxlbWVudHNCeVRhZ05hbWUoIioiKSk7Yi5maXJzdENoaWxkOyliLnJlbW92ZUNoaWxkKGIuZmlyc3RDaGlsZCk7DQpyZXR1cm4gdGhpc30sY2xvbmU6ZnVuY3Rpb24oYSl7dmFyIGI9dGhpcy5tYXAoZnVuY3Rpb24oKXtpZighYy5zdXBwb3J0Lm5vQ2xvbmVFdmVudCYmIWMuaXNYTUxEb2ModGhpcykpe3ZhciBkPXRoaXMub3V0ZXJIVE1MLGY9dGhpcy5vd25lckRvY3VtZW50O2lmKCFkKXtkPWYuY3JlYXRlRWxlbWVudCgiZGl2Iik7ZC5hcHBlbmRDaGlsZCh0aGlzLmNsb25lTm9kZSh0cnVlKSk7ZD1kLmlubmVySFRNTH1yZXR1cm4gYy5jbGVhbihbZC5yZXBsYWNlKEphLCIiKS5yZXBsYWNlKC89KFtePSInPlxzXStcLyk+L2csJz0iJDEiPicpLnJlcGxhY2UoViwiIildLGYpWzBdfWVsc2UgcmV0dXJuIHRoaXMuY2xvbmVOb2RlKHRydWUpfSk7aWYoYT09PXRydWUpe3JhKHRoaXMsYik7cmEodGhpcy5maW5kKCIqIiksYi5maW5kKCIqIikpfXJldHVybiBifSxodG1sOmZ1bmN0aW9uKGEpe2lmKGE9PT13KXJldHVybiB0aGlzWzBdJiZ0aGlzWzBdLm5vZGVUeXBlPT09MT90aGlzWzBdLmlubmVySFRNTC5yZXBsYWNlKEphLA0KIiIpOm51bGw7ZWxzZSBpZih0eXBlb2YgYT09PSJzdHJpbmciJiYhdGEudGVzdChhKSYmKGMuc3VwcG9ydC5sZWFkaW5nV2hpdGVzcGFjZXx8IVYudGVzdChhKSkmJiFGWyhMYS5leGVjKGEpfHxbIiIsIiJdKVsxXS50b0xvd2VyQ2FzZSgpXSl7YT1hLnJlcGxhY2UoS2EsTWEpO3RyeXtmb3IodmFyIGI9MCxkPXRoaXMubGVuZ3RoO2I8ZDtiKyspaWYodGhpc1tiXS5ub2RlVHlwZT09PTEpe2MuY2xlYW5EYXRhKHRoaXNbYl0uZ2V0RWxlbWVudHNCeVRhZ05hbWUoIioiKSk7dGhpc1tiXS5pbm5lckhUTUw9YX19Y2F0Y2goZil7dGhpcy5lbXB0eSgpLmFwcGVuZChhKX19ZWxzZSBjLmlzRnVuY3Rpb24oYSk/dGhpcy5lYWNoKGZ1bmN0aW9uKGUpe3ZhciBqPWModGhpcyksaT1qLmh0bWwoKTtqLmVtcHR5KCkuYXBwZW5kKGZ1bmN0aW9uKCl7cmV0dXJuIGEuY2FsbCh0aGlzLGUsaSl9KX0pOnRoaXMuZW1wdHkoKS5hcHBlbmQoYSk7cmV0dXJuIHRoaXN9LHJlcGxhY2VXaXRoOmZ1bmN0aW9uKGEpe2lmKHRoaXNbMF0mJg0KdGhpc1swXS5wYXJlbnROb2RlKXtpZihjLmlzRnVuY3Rpb24oYSkpcmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbihiKXt2YXIgZD1jKHRoaXMpLGY9ZC5odG1sKCk7ZC5yZXBsYWNlV2l0aChhLmNhbGwodGhpcyxiLGYpKX0pO2lmKHR5cGVvZiBhIT09InN0cmluZyIpYT1jKGEpLmRldGFjaCgpO3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgYj10aGlzLm5leHRTaWJsaW5nLGQ9dGhpcy5wYXJlbnROb2RlO2ModGhpcykucmVtb3ZlKCk7Yj9jKGIpLmJlZm9yZShhKTpjKGQpLmFwcGVuZChhKX0pfWVsc2UgcmV0dXJuIHRoaXMucHVzaFN0YWNrKGMoYy5pc0Z1bmN0aW9uKGEpP2EoKTphKSwicmVwbGFjZVdpdGgiLGEpfSxkZXRhY2g6ZnVuY3Rpb24oYSl7cmV0dXJuIHRoaXMucmVtb3ZlKGEsdHJ1ZSl9LGRvbU1hbmlwOmZ1bmN0aW9uKGEsYixkKXtmdW5jdGlvbiBmKHUpe3JldHVybiBjLm5vZGVOYW1lKHUsInRhYmxlIik/dS5nZXRFbGVtZW50c0J5VGFnTmFtZSgidGJvZHkiKVswXXx8DQp1LmFwcGVuZENoaWxkKHUub3duZXJEb2N1bWVudC5jcmVhdGVFbGVtZW50KCJ0Ym9keSIpKTp1fXZhciBlLGosaT1hWzBdLG89W10saztpZighYy5zdXBwb3J0LmNoZWNrQ2xvbmUmJmFyZ3VtZW50cy5sZW5ndGg9PT0zJiZ0eXBlb2YgaT09PSJzdHJpbmciJiZ1YS50ZXN0KGkpKXJldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXtjKHRoaXMpLmRvbU1hbmlwKGEsYixkLHRydWUpfSk7aWYoYy5pc0Z1bmN0aW9uKGkpKXJldHVybiB0aGlzLmVhY2goZnVuY3Rpb24odSl7dmFyIHo9Yyh0aGlzKTthWzBdPWkuY2FsbCh0aGlzLHUsYj96Lmh0bWwoKTp3KTt6LmRvbU1hbmlwKGEsYixkKX0pO2lmKHRoaXNbMF0pe2U9aSYmaS5wYXJlbnROb2RlO2U9Yy5zdXBwb3J0LnBhcmVudE5vZGUmJmUmJmUubm9kZVR5cGU9PT0xMSYmZS5jaGlsZE5vZGVzLmxlbmd0aD09PXRoaXMubGVuZ3RoP3tmcmFnbWVudDplfTpzYShhLHRoaXMsbyk7az1lLmZyYWdtZW50O2lmKGo9ay5jaGlsZE5vZGVzLmxlbmd0aD09PQ0KMT8oaz1rLmZpcnN0Q2hpbGQpOmsuZmlyc3RDaGlsZCl7Yj1iJiZjLm5vZGVOYW1lKGosInRyIik7Zm9yKHZhciBuPTAscj10aGlzLmxlbmd0aDtuPHI7bisrKWQuY2FsbChiP2YodGhpc1tuXSxqKTp0aGlzW25dLG4+MHx8ZS5jYWNoZWFibGV8fHRoaXMubGVuZ3RoPjE/ay5jbG9uZU5vZGUodHJ1ZSk6ayl9by5sZW5ndGgmJmMuZWFjaChvLFFhKX1yZXR1cm4gdGhpc319KTtjLmZyYWdtZW50cz17fTtjLmVhY2goe2FwcGVuZFRvOiJhcHBlbmQiLHByZXBlbmRUbzoicHJlcGVuZCIsaW5zZXJ0QmVmb3JlOiJiZWZvcmUiLGluc2VydEFmdGVyOiJhZnRlciIscmVwbGFjZUFsbDoicmVwbGFjZVdpdGgifSxmdW5jdGlvbihhLGIpe2MuZm5bYV09ZnVuY3Rpb24oZCl7dmFyIGY9W107ZD1jKGQpO3ZhciBlPXRoaXMubGVuZ3RoPT09MSYmdGhpc1swXS5wYXJlbnROb2RlO2lmKGUmJmUubm9kZVR5cGU9PT0xMSYmZS5jaGlsZE5vZGVzLmxlbmd0aD09PTEmJmQubGVuZ3RoPT09MSl7ZFtiXSh0aGlzWzBdKTsNCnJldHVybiB0aGlzfWVsc2V7ZT0wO2Zvcih2YXIgaj1kLmxlbmd0aDtlPGo7ZSsrKXt2YXIgaT0oZT4wP3RoaXMuY2xvbmUodHJ1ZSk6dGhpcykuZ2V0KCk7Yy5mbltiXS5hcHBseShjKGRbZV0pLGkpO2Y9Zi5jb25jYXQoaSl9cmV0dXJuIHRoaXMucHVzaFN0YWNrKGYsYSxkLnNlbGVjdG9yKX19fSk7Yy5leHRlbmQoe2NsZWFuOmZ1bmN0aW9uKGEsYixkLGYpe2I9Ynx8cztpZih0eXBlb2YgYi5jcmVhdGVFbGVtZW50PT09InVuZGVmaW5lZCIpYj1iLm93bmVyRG9jdW1lbnR8fGJbMF0mJmJbMF0ub3duZXJEb2N1bWVudHx8cztmb3IodmFyIGU9W10saj0wLGk7KGk9YVtqXSkhPW51bGw7aisrKXtpZih0eXBlb2YgaT09PSJudW1iZXIiKWkrPSIiO2lmKGkpe2lmKHR5cGVvZiBpPT09InN0cmluZyImJiFqYi50ZXN0KGkpKWk9Yi5jcmVhdGVUZXh0Tm9kZShpKTtlbHNlIGlmKHR5cGVvZiBpPT09InN0cmluZyIpe2k9aS5yZXBsYWNlKEthLE1hKTt2YXIgbz0oTGEuZXhlYyhpKXx8WyIiLA0KIiJdKVsxXS50b0xvd2VyQ2FzZSgpLGs9RltvXXx8Ri5fZGVmYXVsdCxuPWtbMF0scj1iLmNyZWF0ZUVsZW1lbnQoImRpdiIpO2ZvcihyLmlubmVySFRNTD1rWzFdK2kra1syXTtuLS07KXI9ci5sYXN0Q2hpbGQ7aWYoIWMuc3VwcG9ydC50Ym9keSl7bj1pYi50ZXN0KGkpO289bz09PSJ0YWJsZSImJiFuP3IuZmlyc3RDaGlsZCYmci5maXJzdENoaWxkLmNoaWxkTm9kZXM6a1sxXT09PSI8dGFibGU+IiYmIW4/ci5jaGlsZE5vZGVzOltdO2ZvcihrPW8ubGVuZ3RoLTE7az49MDstLWspYy5ub2RlTmFtZShvW2tdLCJ0Ym9keSIpJiYhb1trXS5jaGlsZE5vZGVzLmxlbmd0aCYmb1trXS5wYXJlbnROb2RlLnJlbW92ZUNoaWxkKG9ba10pfSFjLnN1cHBvcnQubGVhZGluZ1doaXRlc3BhY2UmJlYudGVzdChpKSYmci5pbnNlcnRCZWZvcmUoYi5jcmVhdGVUZXh0Tm9kZShWLmV4ZWMoaSlbMF0pLHIuZmlyc3RDaGlsZCk7aT1yLmNoaWxkTm9kZXN9aWYoaS5ub2RlVHlwZSllLnB1c2goaSk7ZWxzZSBlPQ0KYy5tZXJnZShlLGkpfX1pZihkKWZvcihqPTA7ZVtqXTtqKyspaWYoZiYmYy5ub2RlTmFtZShlW2pdLCJzY3JpcHQiKSYmKCFlW2pdLnR5cGV8fGVbal0udHlwZS50b0xvd2VyQ2FzZSgpPT09InRleHQvamF2YXNjcmlwdCIpKWYucHVzaChlW2pdLnBhcmVudE5vZGU/ZVtqXS5wYXJlbnROb2RlLnJlbW92ZUNoaWxkKGVbal0pOmVbal0pO2Vsc2V7ZVtqXS5ub2RlVHlwZT09PTEmJmUuc3BsaWNlLmFwcGx5KGUsW2orMSwwXS5jb25jYXQoYy5tYWtlQXJyYXkoZVtqXS5nZXRFbGVtZW50c0J5VGFnTmFtZSgic2NyaXB0IikpKSk7ZC5hcHBlbmRDaGlsZChlW2pdKX1yZXR1cm4gZX0sY2xlYW5EYXRhOmZ1bmN0aW9uKGEpe2Zvcih2YXIgYixkLGY9Yy5jYWNoZSxlPWMuZXZlbnQuc3BlY2lhbCxqPWMuc3VwcG9ydC5kZWxldGVFeHBhbmRvLGk9MCxvOyhvPWFbaV0pIT1udWxsO2krKylpZihkPW9bYy5leHBhbmRvXSl7Yj1mW2RdO2lmKGIuZXZlbnRzKWZvcih2YXIgayBpbiBiLmV2ZW50cyllW2tdPw0KYy5ldmVudC5yZW1vdmUobyxrKTpDYShvLGssYi5oYW5kbGUpO2lmKGopZGVsZXRlIG9bYy5leHBhbmRvXTtlbHNlIG8ucmVtb3ZlQXR0cmlidXRlJiZvLnJlbW92ZUF0dHJpYnV0ZShjLmV4cGFuZG8pO2RlbGV0ZSBmW2RdfX19KTt2YXIga2I9L3otP2luZGV4fGZvbnQtP3dlaWdodHxvcGFjaXR5fHpvb218bGluZS0/aGVpZ2h0L2ksTmE9L2FscGhhXChbXildKlwpLyxPYT0vb3BhY2l0eT0oW14pXSopLyxoYT0vZmxvYXQvaSxpYT0vLShbYS16XSkvaWcsbGI9LyhbQS1aXSkvZyxtYj0vXi0/XGQrKD86cHgpPyQvaSxuYj0vXi0/XGQvLG9iPXtwb3NpdGlvbjoiYWJzb2x1dGUiLHZpc2liaWxpdHk6ImhpZGRlbiIsZGlzcGxheToiYmxvY2sifSxwYj1bIkxlZnQiLCJSaWdodCJdLHFiPVsiVG9wIiwiQm90dG9tIl0scmI9cy5kZWZhdWx0VmlldyYmcy5kZWZhdWx0Vmlldy5nZXRDb21wdXRlZFN0eWxlLFBhPWMuc3VwcG9ydC5jc3NGbG9hdD8iY3NzRmxvYXQiOiJzdHlsZUZsb2F0IixqYT0NCmZ1bmN0aW9uKGEsYil7cmV0dXJuIGIudG9VcHBlckNhc2UoKX07Yy5mbi5jc3M9ZnVuY3Rpb24oYSxiKXtyZXR1cm4gWCh0aGlzLGEsYix0cnVlLGZ1bmN0aW9uKGQsZixlKXtpZihlPT09dylyZXR1cm4gYy5jdXJDU1MoZCxmKTtpZih0eXBlb2YgZT09PSJudW1iZXIiJiYha2IudGVzdChmKSllKz0icHgiO2Muc3R5bGUoZCxmLGUpfSl9O2MuZXh0ZW5kKHtzdHlsZTpmdW5jdGlvbihhLGIsZCl7aWYoIWF8fGEubm9kZVR5cGU9PT0zfHxhLm5vZGVUeXBlPT09OClyZXR1cm4gdztpZigoYj09PSJ3aWR0aCJ8fGI9PT0iaGVpZ2h0IikmJnBhcnNlRmxvYXQoZCk8MClkPXc7dmFyIGY9YS5zdHlsZXx8YSxlPWQhPT13O2lmKCFjLnN1cHBvcnQub3BhY2l0eSYmYj09PSJvcGFjaXR5Iil7aWYoZSl7Zi56b29tPTE7Yj1wYXJzZUludChkLDEwKSsiIj09PSJOYU4iPyIiOiJhbHBoYShvcGFjaXR5PSIrZCoxMDArIikiO2E9Zi5maWx0ZXJ8fGMuY3VyQ1NTKGEsImZpbHRlciIpfHwiIjtmLmZpbHRlcj0NCk5hLnRlc3QoYSk/YS5yZXBsYWNlKE5hLGIpOmJ9cmV0dXJuIGYuZmlsdGVyJiZmLmZpbHRlci5pbmRleE9mKCJvcGFjaXR5PSIpPj0wP3BhcnNlRmxvYXQoT2EuZXhlYyhmLmZpbHRlcilbMV0pLzEwMCsiIjoiIn1pZihoYS50ZXN0KGIpKWI9UGE7Yj1iLnJlcGxhY2UoaWEsamEpO2lmKGUpZltiXT1kO3JldHVybiBmW2JdfSxjc3M6ZnVuY3Rpb24oYSxiLGQsZil7aWYoYj09PSJ3aWR0aCJ8fGI9PT0iaGVpZ2h0Iil7dmFyIGUsaj1iPT09IndpZHRoIj9wYjpxYjtmdW5jdGlvbiBpKCl7ZT1iPT09IndpZHRoIj9hLm9mZnNldFdpZHRoOmEub2Zmc2V0SGVpZ2h0O2YhPT0iYm9yZGVyIiYmYy5lYWNoKGosZnVuY3Rpb24oKXtmfHwoZS09cGFyc2VGbG9hdChjLmN1ckNTUyhhLCJwYWRkaW5nIit0aGlzLHRydWUpKXx8MCk7aWYoZj09PSJtYXJnaW4iKWUrPXBhcnNlRmxvYXQoYy5jdXJDU1MoYSwibWFyZ2luIit0aGlzLHRydWUpKXx8MDtlbHNlIGUtPXBhcnNlRmxvYXQoYy5jdXJDU1MoYSwNCiJib3JkZXIiK3RoaXMrIldpZHRoIix0cnVlKSl8fDB9KX1hLm9mZnNldFdpZHRoIT09MD9pKCk6Yy5zd2FwKGEsb2IsaSk7cmV0dXJuIE1hdGgubWF4KDAsTWF0aC5yb3VuZChlKSl9cmV0dXJuIGMuY3VyQ1NTKGEsYixkKX0sY3VyQ1NTOmZ1bmN0aW9uKGEsYixkKXt2YXIgZixlPWEuc3R5bGU7aWYoIWMuc3VwcG9ydC5vcGFjaXR5JiZiPT09Im9wYWNpdHkiJiZhLmN1cnJlbnRTdHlsZSl7Zj1PYS50ZXN0KGEuY3VycmVudFN0eWxlLmZpbHRlcnx8IiIpP3BhcnNlRmxvYXQoUmVnRXhwLiQxKS8xMDArIiI6IiI7cmV0dXJuIGY9PT0iIj8iMSI6Zn1pZihoYS50ZXN0KGIpKWI9UGE7aWYoIWQmJmUmJmVbYl0pZj1lW2JdO2Vsc2UgaWYocmIpe2lmKGhhLnRlc3QoYikpYj0iZmxvYXQiO2I9Yi5yZXBsYWNlKGxiLCItJDEiKS50b0xvd2VyQ2FzZSgpO2U9YS5vd25lckRvY3VtZW50LmRlZmF1bHRWaWV3O2lmKCFlKXJldHVybiBudWxsO2lmKGE9ZS5nZXRDb21wdXRlZFN0eWxlKGEsbnVsbCkpZj0NCmEuZ2V0UHJvcGVydHlWYWx1ZShiKTtpZihiPT09Im9wYWNpdHkiJiZmPT09IiIpZj0iMSJ9ZWxzZSBpZihhLmN1cnJlbnRTdHlsZSl7ZD1iLnJlcGxhY2UoaWEsamEpO2Y9YS5jdXJyZW50U3R5bGVbYl18fGEuY3VycmVudFN0eWxlW2RdO2lmKCFtYi50ZXN0KGYpJiZuYi50ZXN0KGYpKXtiPWUubGVmdDt2YXIgaj1hLnJ1bnRpbWVTdHlsZS5sZWZ0O2EucnVudGltZVN0eWxlLmxlZnQ9YS5jdXJyZW50U3R5bGUubGVmdDtlLmxlZnQ9ZD09PSJmb250U2l6ZSI/IjFlbSI6Znx8MDtmPWUucGl4ZWxMZWZ0KyJweCI7ZS5sZWZ0PWI7YS5ydW50aW1lU3R5bGUubGVmdD1qfX1yZXR1cm4gZn0sc3dhcDpmdW5jdGlvbihhLGIsZCl7dmFyIGY9e307Zm9yKHZhciBlIGluIGIpe2ZbZV09YS5zdHlsZVtlXTthLnN0eWxlW2VdPWJbZV19ZC5jYWxsKGEpO2ZvcihlIGluIGIpYS5zdHlsZVtlXT1mW2VdfX0pO2lmKGMuZXhwciYmYy5leHByLmZpbHRlcnMpe2MuZXhwci5maWx0ZXJzLmhpZGRlbj1mdW5jdGlvbihhKXt2YXIgYj0NCmEub2Zmc2V0V2lkdGgsZD1hLm9mZnNldEhlaWdodCxmPWEubm9kZU5hbWUudG9Mb3dlckNhc2UoKT09PSJ0ciI7cmV0dXJuIGI9PT0wJiZkPT09MCYmIWY/dHJ1ZTpiPjAmJmQ+MCYmIWY/ZmFsc2U6Yy5jdXJDU1MoYSwiZGlzcGxheSIpPT09Im5vbmUifTtjLmV4cHIuZmlsdGVycy52aXNpYmxlPWZ1bmN0aW9uKGEpe3JldHVybiFjLmV4cHIuZmlsdGVycy5oaWRkZW4oYSl9fXZhciBzYj1KKCksdGI9LzxzY3JpcHQoLnxccykqP1wvc2NyaXB0Pi9naSx1Yj0vc2VsZWN0fHRleHRhcmVhL2ksdmI9L2NvbG9yfGRhdGV8ZGF0ZXRpbWV8ZW1haWx8aGlkZGVufG1vbnRofG51bWJlcnxwYXNzd29yZHxyYW5nZXxzZWFyY2h8dGVsfHRleHR8dGltZXx1cmx8d2Vlay9pLE49Lz1cPygmfCQpLyxrYT0vXD8vLHdiPS8oXD98JilfPS4qPygmfCQpLyx4Yj0vXihcdys6KT9cL1wvKFteXC8/I10rKS8seWI9LyUyMC9nLHpiPWMuZm4ubG9hZDtjLmZuLmV4dGVuZCh7bG9hZDpmdW5jdGlvbihhLGIsZCl7aWYodHlwZW9mIGEhPT0NCiJzdHJpbmciKXJldHVybiB6Yi5jYWxsKHRoaXMsYSk7ZWxzZSBpZighdGhpcy5sZW5ndGgpcmV0dXJuIHRoaXM7dmFyIGY9YS5pbmRleE9mKCIgIik7aWYoZj49MCl7dmFyIGU9YS5zbGljZShmLGEubGVuZ3RoKTthPWEuc2xpY2UoMCxmKX1mPSJHRVQiO2lmKGIpaWYoYy5pc0Z1bmN0aW9uKGIpKXtkPWI7Yj1udWxsfWVsc2UgaWYodHlwZW9mIGI9PT0ib2JqZWN0Iil7Yj1jLnBhcmFtKGIsYy5hamF4U2V0dGluZ3MudHJhZGl0aW9uYWwpO2Y9IlBPU1QifXZhciBqPXRoaXM7Yy5hamF4KHt1cmw6YSx0eXBlOmYsZGF0YVR5cGU6Imh0bWwiLGRhdGE6Yixjb21wbGV0ZTpmdW5jdGlvbihpLG8pe2lmKG89PT0ic3VjY2VzcyJ8fG89PT0ibm90bW9kaWZpZWQiKWouaHRtbChlP2MoIjxkaXYgLz4iKS5hcHBlbmQoaS5yZXNwb25zZVRleHQucmVwbGFjZSh0YiwiIikpLmZpbmQoZSk6aS5yZXNwb25zZVRleHQpO2QmJmouZWFjaChkLFtpLnJlc3BvbnNlVGV4dCxvLGldKX19KTtyZXR1cm4gdGhpc30sDQpzZXJpYWxpemU6ZnVuY3Rpb24oKXtyZXR1cm4gYy5wYXJhbSh0aGlzLnNlcmlhbGl6ZUFycmF5KCkpfSxzZXJpYWxpemVBcnJheTpmdW5jdGlvbigpe3JldHVybiB0aGlzLm1hcChmdW5jdGlvbigpe3JldHVybiB0aGlzLmVsZW1lbnRzP2MubWFrZUFycmF5KHRoaXMuZWxlbWVudHMpOnRoaXN9KS5maWx0ZXIoZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5uYW1lJiYhdGhpcy5kaXNhYmxlZCYmKHRoaXMuY2hlY2tlZHx8dWIudGVzdCh0aGlzLm5vZGVOYW1lKXx8dmIudGVzdCh0aGlzLnR5cGUpKX0pLm1hcChmdW5jdGlvbihhLGIpe2E9Yyh0aGlzKS52YWwoKTtyZXR1cm4gYT09bnVsbD9udWxsOmMuaXNBcnJheShhKT9jLm1hcChhLGZ1bmN0aW9uKGQpe3JldHVybntuYW1lOmIubmFtZSx2YWx1ZTpkfX0pOntuYW1lOmIubmFtZSx2YWx1ZTphfX0pLmdldCgpfX0pO2MuZWFjaCgiYWpheFN0YXJ0IGFqYXhTdG9wIGFqYXhDb21wbGV0ZSBhamF4RXJyb3IgYWpheFN1Y2Nlc3MgYWpheFNlbmQiLnNwbGl0KCIgIiksDQpmdW5jdGlvbihhLGIpe2MuZm5bYl09ZnVuY3Rpb24oZCl7cmV0dXJuIHRoaXMuYmluZChiLGQpfX0pO2MuZXh0ZW5kKHtnZXQ6ZnVuY3Rpb24oYSxiLGQsZil7aWYoYy5pc0Z1bmN0aW9uKGIpKXtmPWZ8fGQ7ZD1iO2I9bnVsbH1yZXR1cm4gYy5hamF4KHt0eXBlOiJHRVQiLHVybDphLGRhdGE6YixzdWNjZXNzOmQsZGF0YVR5cGU6Zn0pfSxnZXRTY3JpcHQ6ZnVuY3Rpb24oYSxiKXtyZXR1cm4gYy5nZXQoYSxudWxsLGIsInNjcmlwdCIpfSxnZXRKU09OOmZ1bmN0aW9uKGEsYixkKXtyZXR1cm4gYy5nZXQoYSxiLGQsImpzb24iKX0scG9zdDpmdW5jdGlvbihhLGIsZCxmKXtpZihjLmlzRnVuY3Rpb24oYikpe2Y9Znx8ZDtkPWI7Yj17fX1yZXR1cm4gYy5hamF4KHt0eXBlOiJQT1NUIix1cmw6YSxkYXRhOmIsc3VjY2VzczpkLGRhdGFUeXBlOmZ9KX0sYWpheFNldHVwOmZ1bmN0aW9uKGEpe2MuZXh0ZW5kKGMuYWpheFNldHRpbmdzLGEpfSxhamF4U2V0dGluZ3M6e3VybDpsb2NhdGlvbi5ocmVmLA0KZ2xvYmFsOnRydWUsdHlwZToiR0VUIixjb250ZW50VHlwZToiYXBwbGljYXRpb24veC13d3ctZm9ybS11cmxlbmNvZGVkIixwcm9jZXNzRGF0YTp0cnVlLGFzeW5jOnRydWUseGhyOkEuWE1MSHR0cFJlcXVlc3QmJihBLmxvY2F0aW9uLnByb3RvY29sIT09ImZpbGU6Inx8IUEuQWN0aXZlWE9iamVjdCk/ZnVuY3Rpb24oKXtyZXR1cm4gbmV3IEEuWE1MSHR0cFJlcXVlc3R9OmZ1bmN0aW9uKCl7dHJ5e3JldHVybiBuZXcgQS5BY3RpdmVYT2JqZWN0KCJNaWNyb3NvZnQuWE1MSFRUUCIpfWNhdGNoKGEpe319LGFjY2VwdHM6e3htbDoiYXBwbGljYXRpb24veG1sLCB0ZXh0L3htbCIsaHRtbDoidGV4dC9odG1sIixzY3JpcHQ6InRleHQvamF2YXNjcmlwdCwgYXBwbGljYXRpb24vamF2YXNjcmlwdCIsanNvbjoiYXBwbGljYXRpb24vanNvbiwgdGV4dC9qYXZhc2NyaXB0Iix0ZXh0OiJ0ZXh0L3BsYWluIixfZGVmYXVsdDoiKi8qIn19LGxhc3RNb2RpZmllZDp7fSxldGFnOnt9LGFqYXg6ZnVuY3Rpb24oYSl7ZnVuY3Rpb24gYigpe2Uuc3VjY2VzcyYmDQplLnN1Y2Nlc3MuY2FsbChrLG8saSx4KTtlLmdsb2JhbCYmZigiYWpheFN1Y2Nlc3MiLFt4LGVdKX1mdW5jdGlvbiBkKCl7ZS5jb21wbGV0ZSYmZS5jb21wbGV0ZS5jYWxsKGsseCxpKTtlLmdsb2JhbCYmZigiYWpheENvbXBsZXRlIixbeCxlXSk7ZS5nbG9iYWwmJiEtLWMuYWN0aXZlJiZjLmV2ZW50LnRyaWdnZXIoImFqYXhTdG9wIil9ZnVuY3Rpb24gZihxLHApeyhlLmNvbnRleHQ/YyhlLmNvbnRleHQpOmMuZXZlbnQpLnRyaWdnZXIocSxwKX12YXIgZT1jLmV4dGVuZCh0cnVlLHt9LGMuYWpheFNldHRpbmdzLGEpLGosaSxvLGs9YSYmYS5jb250ZXh0fHxlLG49ZS50eXBlLnRvVXBwZXJDYXNlKCk7aWYoZS5kYXRhJiZlLnByb2Nlc3NEYXRhJiZ0eXBlb2YgZS5kYXRhIT09InN0cmluZyIpZS5kYXRhPWMucGFyYW0oZS5kYXRhLGUudHJhZGl0aW9uYWwpO2lmKGUuZGF0YVR5cGU9PT0ianNvbnAiKXtpZihuPT09IkdFVCIpTi50ZXN0KGUudXJsKXx8KGUudXJsKz0oa2EudGVzdChlLnVybCk/DQoiJiI6Ij8iKSsoZS5qc29ucHx8ImNhbGxiYWNrIikrIj0/Iik7ZWxzZSBpZighZS5kYXRhfHwhTi50ZXN0KGUuZGF0YSkpZS5kYXRhPShlLmRhdGE/ZS5kYXRhKyImIjoiIikrKGUuanNvbnB8fCJjYWxsYmFjayIpKyI9PyI7ZS5kYXRhVHlwZT0ianNvbiJ9aWYoZS5kYXRhVHlwZT09PSJqc29uIiYmKGUuZGF0YSYmTi50ZXN0KGUuZGF0YSl8fE4udGVzdChlLnVybCkpKXtqPWUuanNvbnBDYWxsYmFja3x8Impzb25wIitzYisrO2lmKGUuZGF0YSllLmRhdGE9KGUuZGF0YSsiIikucmVwbGFjZShOLCI9IitqKyIkMSIpO2UudXJsPWUudXJsLnJlcGxhY2UoTiwiPSIraisiJDEiKTtlLmRhdGFUeXBlPSJzY3JpcHQiO0Fbal09QVtqXXx8ZnVuY3Rpb24ocSl7bz1xO2IoKTtkKCk7QVtqXT13O3RyeXtkZWxldGUgQVtqXX1jYXRjaChwKXt9eiYmei5yZW1vdmVDaGlsZChDKX19aWYoZS5kYXRhVHlwZT09PSJzY3JpcHQiJiZlLmNhY2hlPT09bnVsbCllLmNhY2hlPWZhbHNlO2lmKGUuY2FjaGU9PT0NCmZhbHNlJiZuPT09IkdFVCIpe3ZhciByPUooKSx1PWUudXJsLnJlcGxhY2Uod2IsIiQxXz0iK3IrIiQyIik7ZS51cmw9dSsodT09PWUudXJsPyhrYS50ZXN0KGUudXJsKT8iJiI6Ij8iKSsiXz0iK3I6IiIpfWlmKGUuZGF0YSYmbj09PSJHRVQiKWUudXJsKz0oa2EudGVzdChlLnVybCk/IiYiOiI/IikrZS5kYXRhO2UuZ2xvYmFsJiYhYy5hY3RpdmUrKyYmYy5ldmVudC50cmlnZ2VyKCJhamF4U3RhcnQiKTtyPShyPXhiLmV4ZWMoZS51cmwpKSYmKHJbMV0mJnJbMV0hPT1sb2NhdGlvbi5wcm90b2NvbHx8clsyXSE9PWxvY2F0aW9uLmhvc3QpO2lmKGUuZGF0YVR5cGU9PT0ic2NyaXB0IiYmbj09PSJHRVQiJiZyKXt2YXIgej1zLmdldEVsZW1lbnRzQnlUYWdOYW1lKCJoZWFkIilbMF18fHMuZG9jdW1lbnRFbGVtZW50LEM9cy5jcmVhdGVFbGVtZW50KCJzY3JpcHQiKTtDLnNyYz1lLnVybDtpZihlLnNjcmlwdENoYXJzZXQpQy5jaGFyc2V0PWUuc2NyaXB0Q2hhcnNldDtpZighail7dmFyIEI9DQpmYWxzZTtDLm9ubG9hZD1DLm9ucmVhZHlzdGF0ZWNoYW5nZT1mdW5jdGlvbigpe2lmKCFCJiYoIXRoaXMucmVhZHlTdGF0ZXx8dGhpcy5yZWFkeVN0YXRlPT09ImxvYWRlZCJ8fHRoaXMucmVhZHlTdGF0ZT09PSJjb21wbGV0ZSIpKXtCPXRydWU7YigpO2QoKTtDLm9ubG9hZD1DLm9ucmVhZHlzdGF0ZWNoYW5nZT1udWxsO3omJkMucGFyZW50Tm9kZSYmei5yZW1vdmVDaGlsZChDKX19fXouaW5zZXJ0QmVmb3JlKEMsei5maXJzdENoaWxkKTtyZXR1cm4gd312YXIgRT1mYWxzZSx4PWUueGhyKCk7aWYoeCl7ZS51c2VybmFtZT94Lm9wZW4obixlLnVybCxlLmFzeW5jLGUudXNlcm5hbWUsZS5wYXNzd29yZCk6eC5vcGVuKG4sZS51cmwsZS5hc3luYyk7dHJ5e2lmKGUuZGF0YXx8YSYmYS5jb250ZW50VHlwZSl4LnNldFJlcXVlc3RIZWFkZXIoIkNvbnRlbnQtVHlwZSIsZS5jb250ZW50VHlwZSk7aWYoZS5pZk1vZGlmaWVkKXtjLmxhc3RNb2RpZmllZFtlLnVybF0mJnguc2V0UmVxdWVzdEhlYWRlcigiSWYtTW9kaWZpZWQtU2luY2UiLA0KYy5sYXN0TW9kaWZpZWRbZS51cmxdKTtjLmV0YWdbZS51cmxdJiZ4LnNldFJlcXVlc3RIZWFkZXIoIklmLU5vbmUtTWF0Y2giLGMuZXRhZ1tlLnVybF0pfXJ8fHguc2V0UmVxdWVzdEhlYWRlcigiWC1SZXF1ZXN0ZWQtV2l0aCIsIlhNTEh0dHBSZXF1ZXN0Iik7eC5zZXRSZXF1ZXN0SGVhZGVyKCJBY2NlcHQiLGUuZGF0YVR5cGUmJmUuYWNjZXB0c1tlLmRhdGFUeXBlXT9lLmFjY2VwdHNbZS5kYXRhVHlwZV0rIiwgKi8qIjplLmFjY2VwdHMuX2RlZmF1bHQpfWNhdGNoKGdhKXt9aWYoZS5iZWZvcmVTZW5kJiZlLmJlZm9yZVNlbmQuY2FsbChrLHgsZSk9PT1mYWxzZSl7ZS5nbG9iYWwmJiEtLWMuYWN0aXZlJiZjLmV2ZW50LnRyaWdnZXIoImFqYXhTdG9wIik7eC5hYm9ydCgpO3JldHVybiBmYWxzZX1lLmdsb2JhbCYmZigiYWpheFNlbmQiLFt4LGVdKTt2YXIgZz14Lm9ucmVhZHlzdGF0ZWNoYW5nZT1mdW5jdGlvbihxKXtpZigheHx8eC5yZWFkeVN0YXRlPT09MHx8cT09PSJhYm9ydCIpe0V8fA0KZCgpO0U9dHJ1ZTtpZih4KXgub25yZWFkeXN0YXRlY2hhbmdlPWMubm9vcH1lbHNlIGlmKCFFJiZ4JiYoeC5yZWFkeVN0YXRlPT09NHx8cT09PSJ0aW1lb3V0Iikpe0U9dHJ1ZTt4Lm9ucmVhZHlzdGF0ZWNoYW5nZT1jLm5vb3A7aT1xPT09InRpbWVvdXQiPyJ0aW1lb3V0IjohYy5odHRwU3VjY2Vzcyh4KT8iZXJyb3IiOmUuaWZNb2RpZmllZCYmYy5odHRwTm90TW9kaWZpZWQoeCxlLnVybCk/Im5vdG1vZGlmaWVkIjoic3VjY2VzcyI7dmFyIHA7aWYoaT09PSJzdWNjZXNzIil0cnl7bz1jLmh0dHBEYXRhKHgsZS5kYXRhVHlwZSxlKX1jYXRjaCh2KXtpPSJwYXJzZXJlcnJvciI7cD12fWlmKGk9PT0ic3VjY2VzcyJ8fGk9PT0ibm90bW9kaWZpZWQiKWp8fGIoKTtlbHNlIGMuaGFuZGxlRXJyb3IoZSx4LGkscCk7ZCgpO3E9PT0idGltZW91dCImJnguYWJvcnQoKTtpZihlLmFzeW5jKXg9bnVsbH19O3RyeXt2YXIgaD14LmFib3J0O3guYWJvcnQ9ZnVuY3Rpb24oKXt4JiZoLmNhbGwoeCk7DQpnKCJhYm9ydCIpfX1jYXRjaChsKXt9ZS5hc3luYyYmZS50aW1lb3V0PjAmJnNldFRpbWVvdXQoZnVuY3Rpb24oKXt4JiYhRSYmZygidGltZW91dCIpfSxlLnRpbWVvdXQpO3RyeXt4LnNlbmQobj09PSJQT1NUInx8bj09PSJQVVQifHxuPT09IkRFTEVURSI/ZS5kYXRhOm51bGwpfWNhdGNoKG0pe2MuaGFuZGxlRXJyb3IoZSx4LG51bGwsbSk7ZCgpfWUuYXN5bmN8fGcoKTtyZXR1cm4geH19LGhhbmRsZUVycm9yOmZ1bmN0aW9uKGEsYixkLGYpe2lmKGEuZXJyb3IpYS5lcnJvci5jYWxsKGEuY29udGV4dHx8YSxiLGQsZik7aWYoYS5nbG9iYWwpKGEuY29udGV4dD9jKGEuY29udGV4dCk6Yy5ldmVudCkudHJpZ2dlcigiYWpheEVycm9yIixbYixhLGZdKX0sYWN0aXZlOjAsaHR0cFN1Y2Nlc3M6ZnVuY3Rpb24oYSl7dHJ5e3JldHVybiFhLnN0YXR1cyYmbG9jYXRpb24ucHJvdG9jb2w9PT0iZmlsZToifHxhLnN0YXR1cz49MjAwJiZhLnN0YXR1czwzMDB8fGEuc3RhdHVzPT09MzA0fHxhLnN0YXR1cz09PQ0KMTIyM3x8YS5zdGF0dXM9PT0wfWNhdGNoKGIpe31yZXR1cm4gZmFsc2V9LGh0dHBOb3RNb2RpZmllZDpmdW5jdGlvbihhLGIpe3ZhciBkPWEuZ2V0UmVzcG9uc2VIZWFkZXIoIkxhc3QtTW9kaWZpZWQiKSxmPWEuZ2V0UmVzcG9uc2VIZWFkZXIoIkV0YWciKTtpZihkKWMubGFzdE1vZGlmaWVkW2JdPWQ7aWYoZiljLmV0YWdbYl09ZjtyZXR1cm4gYS5zdGF0dXM9PT0zMDR8fGEuc3RhdHVzPT09MH0saHR0cERhdGE6ZnVuY3Rpb24oYSxiLGQpe3ZhciBmPWEuZ2V0UmVzcG9uc2VIZWFkZXIoImNvbnRlbnQtdHlwZSIpfHwiIixlPWI9PT0ieG1sInx8IWImJmYuaW5kZXhPZigieG1sIik+PTA7YT1lP2EucmVzcG9uc2VYTUw6YS5yZXNwb25zZVRleHQ7ZSYmYS5kb2N1bWVudEVsZW1lbnQubm9kZU5hbWU9PT0icGFyc2VyZXJyb3IiJiZjLmVycm9yKCJwYXJzZXJlcnJvciIpO2lmKGQmJmQuZGF0YUZpbHRlcilhPWQuZGF0YUZpbHRlcihhLGIpO2lmKHR5cGVvZiBhPT09InN0cmluZyIpaWYoYj09PQ0KImpzb24ifHwhYiYmZi5pbmRleE9mKCJqc29uIik+PTApYT1jLnBhcnNlSlNPTihhKTtlbHNlIGlmKGI9PT0ic2NyaXB0Inx8IWImJmYuaW5kZXhPZigiamF2YXNjcmlwdCIpPj0wKWMuZ2xvYmFsRXZhbChhKTtyZXR1cm4gYX0scGFyYW06ZnVuY3Rpb24oYSxiKXtmdW5jdGlvbiBkKGksbyl7aWYoYy5pc0FycmF5KG8pKWMuZWFjaChvLGZ1bmN0aW9uKGssbil7Ynx8L1xbXF0kLy50ZXN0KGkpP2YoaSxuKTpkKGkrIlsiKyh0eXBlb2Ygbj09PSJvYmplY3QifHxjLmlzQXJyYXkobik/azoiIikrIl0iLG4pfSk7ZWxzZSFiJiZvIT1udWxsJiZ0eXBlb2Ygbz09PSJvYmplY3QiP2MuZWFjaChvLGZ1bmN0aW9uKGssbil7ZChpKyJbIitrKyJdIixuKX0pOmYoaSxvKX1mdW5jdGlvbiBmKGksbyl7bz1jLmlzRnVuY3Rpb24obyk/bygpOm87ZVtlLmxlbmd0aF09ZW5jb2RlVVJJQ29tcG9uZW50KGkpKyI9IitlbmNvZGVVUklDb21wb25lbnQobyl9dmFyIGU9W107aWYoYj09PXcpYj1jLmFqYXhTZXR0aW5ncy50cmFkaXRpb25hbDsNCmlmKGMuaXNBcnJheShhKXx8YS5qcXVlcnkpYy5lYWNoKGEsZnVuY3Rpb24oKXtmKHRoaXMubmFtZSx0aGlzLnZhbHVlKX0pO2Vsc2UgZm9yKHZhciBqIGluIGEpZChqLGFbal0pO3JldHVybiBlLmpvaW4oIiYiKS5yZXBsYWNlKHliLCIrIil9fSk7dmFyIGxhPXt9LEFiPS90b2dnbGV8c2hvd3xoaWRlLyxCYj0vXihbKy1dPSk/KFtcZCstLl0rKSguKikkLyxXLHZhPVtbImhlaWdodCIsIm1hcmdpblRvcCIsIm1hcmdpbkJvdHRvbSIsInBhZGRpbmdUb3AiLCJwYWRkaW5nQm90dG9tIl0sWyJ3aWR0aCIsIm1hcmdpbkxlZnQiLCJtYXJnaW5SaWdodCIsInBhZGRpbmdMZWZ0IiwicGFkZGluZ1JpZ2h0Il0sWyJvcGFjaXR5Il1dO2MuZm4uZXh0ZW5kKHtzaG93OmZ1bmN0aW9uKGEsYil7aWYoYXx8YT09PTApcmV0dXJuIHRoaXMuYW5pbWF0ZShLKCJzaG93IiwzKSxhLGIpO2Vsc2V7YT0wO2ZvcihiPXRoaXMubGVuZ3RoO2E8YjthKyspe3ZhciBkPWMuZGF0YSh0aGlzW2FdLCJvbGRkaXNwbGF5Iik7DQp0aGlzW2FdLnN0eWxlLmRpc3BsYXk9ZHx8IiI7aWYoYy5jc3ModGhpc1thXSwiZGlzcGxheSIpPT09Im5vbmUiKXtkPXRoaXNbYV0ubm9kZU5hbWU7dmFyIGY7aWYobGFbZF0pZj1sYVtkXTtlbHNle3ZhciBlPWMoIjwiK2QrIiAvPiIpLmFwcGVuZFRvKCJib2R5Iik7Zj1lLmNzcygiZGlzcGxheSIpO2lmKGY9PT0ibm9uZSIpZj0iYmxvY2siO2UucmVtb3ZlKCk7bGFbZF09Zn1jLmRhdGEodGhpc1thXSwib2xkZGlzcGxheSIsZil9fWE9MDtmb3IoYj10aGlzLmxlbmd0aDthPGI7YSsrKXRoaXNbYV0uc3R5bGUuZGlzcGxheT1jLmRhdGEodGhpc1thXSwib2xkZGlzcGxheSIpfHwiIjtyZXR1cm4gdGhpc319LGhpZGU6ZnVuY3Rpb24oYSxiKXtpZihhfHxhPT09MClyZXR1cm4gdGhpcy5hbmltYXRlKEsoImhpZGUiLDMpLGEsYik7ZWxzZXthPTA7Zm9yKGI9dGhpcy5sZW5ndGg7YTxiO2ErKyl7dmFyIGQ9Yy5kYXRhKHRoaXNbYV0sIm9sZGRpc3BsYXkiKTshZCYmZCE9PSJub25lIiYmYy5kYXRhKHRoaXNbYV0sDQoib2xkZGlzcGxheSIsYy5jc3ModGhpc1thXSwiZGlzcGxheSIpKX1hPTA7Zm9yKGI9dGhpcy5sZW5ndGg7YTxiO2ErKyl0aGlzW2FdLnN0eWxlLmRpc3BsYXk9Im5vbmUiO3JldHVybiB0aGlzfX0sX3RvZ2dsZTpjLmZuLnRvZ2dsZSx0b2dnbGU6ZnVuY3Rpb24oYSxiKXt2YXIgZD10eXBlb2YgYT09PSJib29sZWFuIjtpZihjLmlzRnVuY3Rpb24oYSkmJmMuaXNGdW5jdGlvbihiKSl0aGlzLl90b2dnbGUuYXBwbHkodGhpcyxhcmd1bWVudHMpO2Vsc2UgYT09bnVsbHx8ZD90aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgZj1kP2E6Yyh0aGlzKS5pcygiOmhpZGRlbiIpO2ModGhpcylbZj8ic2hvdyI6ImhpZGUiXSgpfSk6dGhpcy5hbmltYXRlKEsoInRvZ2dsZSIsMyksYSxiKTtyZXR1cm4gdGhpc30sZmFkZVRvOmZ1bmN0aW9uKGEsYixkKXtyZXR1cm4gdGhpcy5maWx0ZXIoIjpoaWRkZW4iKS5jc3MoIm9wYWNpdHkiLDApLnNob3coKS5lbmQoKS5hbmltYXRlKHtvcGFjaXR5OmJ9LGEsZCl9LA0KYW5pbWF0ZTpmdW5jdGlvbihhLGIsZCxmKXt2YXIgZT1jLnNwZWVkKGIsZCxmKTtpZihjLmlzRW1wdHlPYmplY3QoYSkpcmV0dXJuIHRoaXMuZWFjaChlLmNvbXBsZXRlKTtyZXR1cm4gdGhpc1tlLnF1ZXVlPT09ZmFsc2U/ImVhY2giOiJxdWV1ZSJdKGZ1bmN0aW9uKCl7dmFyIGo9Yy5leHRlbmQoe30sZSksaSxvPXRoaXMubm9kZVR5cGU9PT0xJiZjKHRoaXMpLmlzKCI6aGlkZGVuIiksaz10aGlzO2ZvcihpIGluIGEpe3ZhciBuPWkucmVwbGFjZShpYSxqYSk7aWYoaSE9PW4pe2Fbbl09YVtpXTtkZWxldGUgYVtpXTtpPW59aWYoYVtpXT09PSJoaWRlIiYmb3x8YVtpXT09PSJzaG93IiYmIW8pcmV0dXJuIGouY29tcGxldGUuY2FsbCh0aGlzKTtpZigoaT09PSJoZWlnaHQifHxpPT09IndpZHRoIikmJnRoaXMuc3R5bGUpe2ouZGlzcGxheT1jLmNzcyh0aGlzLCJkaXNwbGF5Iik7ai5vdmVyZmxvdz10aGlzLnN0eWxlLm92ZXJmbG93fWlmKGMuaXNBcnJheShhW2ldKSl7KGouc3BlY2lhbEVhc2luZz0NCmouc3BlY2lhbEVhc2luZ3x8e30pW2ldPWFbaV1bMV07YVtpXT1hW2ldWzBdfX1pZihqLm92ZXJmbG93IT1udWxsKXRoaXMuc3R5bGUub3ZlcmZsb3c9ImhpZGRlbiI7ai5jdXJBbmltPWMuZXh0ZW5kKHt9LGEpO2MuZWFjaChhLGZ1bmN0aW9uKHIsdSl7dmFyIHo9bmV3IGMuZngoayxqLHIpO2lmKEFiLnRlc3QodSkpelt1PT09InRvZ2dsZSI/bz8ic2hvdyI6ImhpZGUiOnVdKGEpO2Vsc2V7dmFyIEM9QmIuZXhlYyh1KSxCPXouY3VyKHRydWUpfHwwO2lmKEMpe3U9cGFyc2VGbG9hdChDWzJdKTt2YXIgRT1DWzNdfHwicHgiO2lmKEUhPT0icHgiKXtrLnN0eWxlW3JdPSh1fHwxKStFO0I9KHV8fDEpL3ouY3VyKHRydWUpKkI7ay5zdHlsZVtyXT1CK0V9aWYoQ1sxXSl1PShDWzFdPT09Ii09Ij8tMToxKSp1K0I7ei5jdXN0b20oQix1LEUpfWVsc2Ugei5jdXN0b20oQix1LCIiKX19KTtyZXR1cm4gdHJ1ZX0pfSxzdG9wOmZ1bmN0aW9uKGEsYil7dmFyIGQ9Yy50aW1lcnM7YSYmdGhpcy5xdWV1ZShbXSk7DQp0aGlzLmVhY2goZnVuY3Rpb24oKXtmb3IodmFyIGY9ZC5sZW5ndGgtMTtmPj0wO2YtLSlpZihkW2ZdLmVsZW09PT10aGlzKXtiJiZkW2ZdKHRydWUpO2Quc3BsaWNlKGYsMSl9fSk7Ynx8dGhpcy5kZXF1ZXVlKCk7cmV0dXJuIHRoaXN9fSk7Yy5lYWNoKHtzbGlkZURvd246Sygic2hvdyIsMSksc2xpZGVVcDpLKCJoaWRlIiwxKSxzbGlkZVRvZ2dsZTpLKCJ0b2dnbGUiLDEpLGZhZGVJbjp7b3BhY2l0eToic2hvdyJ9LGZhZGVPdXQ6e29wYWNpdHk6ImhpZGUifX0sZnVuY3Rpb24oYSxiKXtjLmZuW2FdPWZ1bmN0aW9uKGQsZil7cmV0dXJuIHRoaXMuYW5pbWF0ZShiLGQsZil9fSk7Yy5leHRlbmQoe3NwZWVkOmZ1bmN0aW9uKGEsYixkKXt2YXIgZj1hJiZ0eXBlb2YgYT09PSJvYmplY3QiP2E6e2NvbXBsZXRlOmR8fCFkJiZifHxjLmlzRnVuY3Rpb24oYSkmJmEsZHVyYXRpb246YSxlYXNpbmc6ZCYmYnx8YiYmIWMuaXNGdW5jdGlvbihiKSYmYn07Zi5kdXJhdGlvbj1jLmZ4Lm9mZj8wOnR5cGVvZiBmLmR1cmF0aW9uPT09DQoibnVtYmVyIj9mLmR1cmF0aW9uOmMuZnguc3BlZWRzW2YuZHVyYXRpb25dfHxjLmZ4LnNwZWVkcy5fZGVmYXVsdDtmLm9sZD1mLmNvbXBsZXRlO2YuY29tcGxldGU9ZnVuY3Rpb24oKXtmLnF1ZXVlIT09ZmFsc2UmJmModGhpcykuZGVxdWV1ZSgpO2MuaXNGdW5jdGlvbihmLm9sZCkmJmYub2xkLmNhbGwodGhpcyl9O3JldHVybiBmfSxlYXNpbmc6e2xpbmVhcjpmdW5jdGlvbihhLGIsZCxmKXtyZXR1cm4gZCtmKmF9LHN3aW5nOmZ1bmN0aW9uKGEsYixkLGYpe3JldHVybigtTWF0aC5jb3MoYSpNYXRoLlBJKS8yKzAuNSkqZitkfX0sdGltZXJzOltdLGZ4OmZ1bmN0aW9uKGEsYixkKXt0aGlzLm9wdGlvbnM9Yjt0aGlzLmVsZW09YTt0aGlzLnByb3A9ZDtpZighYi5vcmlnKWIub3JpZz17fX19KTtjLmZ4LnByb3RvdHlwZT17dXBkYXRlOmZ1bmN0aW9uKCl7dGhpcy5vcHRpb25zLnN0ZXAmJnRoaXMub3B0aW9ucy5zdGVwLmNhbGwodGhpcy5lbGVtLHRoaXMubm93LHRoaXMpOyhjLmZ4LnN0ZXBbdGhpcy5wcm9wXXx8DQpjLmZ4LnN0ZXAuX2RlZmF1bHQpKHRoaXMpO2lmKCh0aGlzLnByb3A9PT0iaGVpZ2h0Inx8dGhpcy5wcm9wPT09IndpZHRoIikmJnRoaXMuZWxlbS5zdHlsZSl0aGlzLmVsZW0uc3R5bGUuZGlzcGxheT0iYmxvY2sifSxjdXI6ZnVuY3Rpb24oYSl7aWYodGhpcy5lbGVtW3RoaXMucHJvcF0hPW51bGwmJighdGhpcy5lbGVtLnN0eWxlfHx0aGlzLmVsZW0uc3R5bGVbdGhpcy5wcm9wXT09bnVsbCkpcmV0dXJuIHRoaXMuZWxlbVt0aGlzLnByb3BdO3JldHVybihhPXBhcnNlRmxvYXQoYy5jc3ModGhpcy5lbGVtLHRoaXMucHJvcCxhKSkpJiZhPi0xMDAwMD9hOnBhcnNlRmxvYXQoYy5jdXJDU1ModGhpcy5lbGVtLHRoaXMucHJvcCkpfHwwfSxjdXN0b206ZnVuY3Rpb24oYSxiLGQpe2Z1bmN0aW9uIGYoail7cmV0dXJuIGUuc3RlcChqKX10aGlzLnN0YXJ0VGltZT1KKCk7dGhpcy5zdGFydD1hO3RoaXMuZW5kPWI7dGhpcy51bml0PWR8fHRoaXMudW5pdHx8InB4Ijt0aGlzLm5vdz10aGlzLnN0YXJ0Ow0KdGhpcy5wb3M9dGhpcy5zdGF0ZT0wO3ZhciBlPXRoaXM7Zi5lbGVtPXRoaXMuZWxlbTtpZihmKCkmJmMudGltZXJzLnB1c2goZikmJiFXKVc9c2V0SW50ZXJ2YWwoYy5meC50aWNrLDEzKX0sc2hvdzpmdW5jdGlvbigpe3RoaXMub3B0aW9ucy5vcmlnW3RoaXMucHJvcF09Yy5zdHlsZSh0aGlzLmVsZW0sdGhpcy5wcm9wKTt0aGlzLm9wdGlvbnMuc2hvdz10cnVlO3RoaXMuY3VzdG9tKHRoaXMucHJvcD09PSJ3aWR0aCJ8fHRoaXMucHJvcD09PSJoZWlnaHQiPzE6MCx0aGlzLmN1cigpKTtjKHRoaXMuZWxlbSkuc2hvdygpfSxoaWRlOmZ1bmN0aW9uKCl7dGhpcy5vcHRpb25zLm9yaWdbdGhpcy5wcm9wXT1jLnN0eWxlKHRoaXMuZWxlbSx0aGlzLnByb3ApO3RoaXMub3B0aW9ucy5oaWRlPXRydWU7dGhpcy5jdXN0b20odGhpcy5jdXIoKSwwKX0sc3RlcDpmdW5jdGlvbihhKXt2YXIgYj1KKCksZD10cnVlO2lmKGF8fGI+PXRoaXMub3B0aW9ucy5kdXJhdGlvbit0aGlzLnN0YXJ0VGltZSl7dGhpcy5ub3c9DQp0aGlzLmVuZDt0aGlzLnBvcz10aGlzLnN0YXRlPTE7dGhpcy51cGRhdGUoKTt0aGlzLm9wdGlvbnMuY3VyQW5pbVt0aGlzLnByb3BdPXRydWU7Zm9yKHZhciBmIGluIHRoaXMub3B0aW9ucy5jdXJBbmltKWlmKHRoaXMub3B0aW9ucy5jdXJBbmltW2ZdIT09dHJ1ZSlkPWZhbHNlO2lmKGQpe2lmKHRoaXMub3B0aW9ucy5kaXNwbGF5IT1udWxsKXt0aGlzLmVsZW0uc3R5bGUub3ZlcmZsb3c9dGhpcy5vcHRpb25zLm92ZXJmbG93O2E9Yy5kYXRhKHRoaXMuZWxlbSwib2xkZGlzcGxheSIpO3RoaXMuZWxlbS5zdHlsZS5kaXNwbGF5PWE/YTp0aGlzLm9wdGlvbnMuZGlzcGxheTtpZihjLmNzcyh0aGlzLmVsZW0sImRpc3BsYXkiKT09PSJub25lIil0aGlzLmVsZW0uc3R5bGUuZGlzcGxheT0iYmxvY2sifXRoaXMub3B0aW9ucy5oaWRlJiZjKHRoaXMuZWxlbSkuaGlkZSgpO2lmKHRoaXMub3B0aW9ucy5oaWRlfHx0aGlzLm9wdGlvbnMuc2hvdylmb3IodmFyIGUgaW4gdGhpcy5vcHRpb25zLmN1ckFuaW0pYy5zdHlsZSh0aGlzLmVsZW0sDQplLHRoaXMub3B0aW9ucy5vcmlnW2VdKTt0aGlzLm9wdGlvbnMuY29tcGxldGUuY2FsbCh0aGlzLmVsZW0pfXJldHVybiBmYWxzZX1lbHNle2U9Yi10aGlzLnN0YXJ0VGltZTt0aGlzLnN0YXRlPWUvdGhpcy5vcHRpb25zLmR1cmF0aW9uO2E9dGhpcy5vcHRpb25zLmVhc2luZ3x8KGMuZWFzaW5nLnN3aW5nPyJzd2luZyI6ImxpbmVhciIpO3RoaXMucG9zPWMuZWFzaW5nW3RoaXMub3B0aW9ucy5zcGVjaWFsRWFzaW5nJiZ0aGlzLm9wdGlvbnMuc3BlY2lhbEVhc2luZ1t0aGlzLnByb3BdfHxhXSh0aGlzLnN0YXRlLGUsMCwxLHRoaXMub3B0aW9ucy5kdXJhdGlvbik7dGhpcy5ub3c9dGhpcy5zdGFydCsodGhpcy5lbmQtdGhpcy5zdGFydCkqdGhpcy5wb3M7dGhpcy51cGRhdGUoKX1yZXR1cm4gdHJ1ZX19O2MuZXh0ZW5kKGMuZngse3RpY2s6ZnVuY3Rpb24oKXtmb3IodmFyIGE9Yy50aW1lcnMsYj0wO2I8YS5sZW5ndGg7YisrKWFbYl0oKXx8YS5zcGxpY2UoYi0tLDEpO2EubGVuZ3RofHwNCmMuZnguc3RvcCgpfSxzdG9wOmZ1bmN0aW9uKCl7Y2xlYXJJbnRlcnZhbChXKTtXPW51bGx9LHNwZWVkczp7c2xvdzo2MDAsZmFzdDoyMDAsX2RlZmF1bHQ6NDAwfSxzdGVwOntvcGFjaXR5OmZ1bmN0aW9uKGEpe2Muc3R5bGUoYS5lbGVtLCJvcGFjaXR5IixhLm5vdyl9LF9kZWZhdWx0OmZ1bmN0aW9uKGEpe2lmKGEuZWxlbS5zdHlsZSYmYS5lbGVtLnN0eWxlW2EucHJvcF0hPW51bGwpYS5lbGVtLnN0eWxlW2EucHJvcF09KGEucHJvcD09PSJ3aWR0aCJ8fGEucHJvcD09PSJoZWlnaHQiP01hdGgubWF4KDAsYS5ub3cpOmEubm93KSthLnVuaXQ7ZWxzZSBhLmVsZW1bYS5wcm9wXT1hLm5vd319fSk7aWYoYy5leHByJiZjLmV4cHIuZmlsdGVycyljLmV4cHIuZmlsdGVycy5hbmltYXRlZD1mdW5jdGlvbihhKXtyZXR1cm4gYy5ncmVwKGMudGltZXJzLGZ1bmN0aW9uKGIpe3JldHVybiBhPT09Yi5lbGVtfSkubGVuZ3RofTtjLmZuLm9mZnNldD0iZ2V0Qm91bmRpbmdDbGllbnRSZWN0ImluIHMuZG9jdW1lbnRFbGVtZW50Pw0KZnVuY3Rpb24oYSl7dmFyIGI9dGhpc1swXTtpZihhKXJldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oZSl7Yy5vZmZzZXQuc2V0T2Zmc2V0KHRoaXMsYSxlKX0pO2lmKCFifHwhYi5vd25lckRvY3VtZW50KXJldHVybiBudWxsO2lmKGI9PT1iLm93bmVyRG9jdW1lbnQuYm9keSlyZXR1cm4gYy5vZmZzZXQuYm9keU9mZnNldChiKTt2YXIgZD1iLmdldEJvdW5kaW5nQ2xpZW50UmVjdCgpLGY9Yi5vd25lckRvY3VtZW50O2I9Zi5ib2R5O2Y9Zi5kb2N1bWVudEVsZW1lbnQ7cmV0dXJue3RvcDpkLnRvcCsoc2VsZi5wYWdlWU9mZnNldHx8Yy5zdXBwb3J0LmJveE1vZGVsJiZmLnNjcm9sbFRvcHx8Yi5zY3JvbGxUb3ApLShmLmNsaWVudFRvcHx8Yi5jbGllbnRUb3B8fDApLGxlZnQ6ZC5sZWZ0KyhzZWxmLnBhZ2VYT2Zmc2V0fHxjLnN1cHBvcnQuYm94TW9kZWwmJmYuc2Nyb2xsTGVmdHx8Yi5zY3JvbGxMZWZ0KS0oZi5jbGllbnRMZWZ0fHxiLmNsaWVudExlZnR8fDApfX06ZnVuY3Rpb24oYSl7dmFyIGI9DQp0aGlzWzBdO2lmKGEpcmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbihyKXtjLm9mZnNldC5zZXRPZmZzZXQodGhpcyxhLHIpfSk7aWYoIWJ8fCFiLm93bmVyRG9jdW1lbnQpcmV0dXJuIG51bGw7aWYoYj09PWIub3duZXJEb2N1bWVudC5ib2R5KXJldHVybiBjLm9mZnNldC5ib2R5T2Zmc2V0KGIpO2Mub2Zmc2V0LmluaXRpYWxpemUoKTt2YXIgZD1iLm9mZnNldFBhcmVudCxmPWIsZT1iLm93bmVyRG9jdW1lbnQsaixpPWUuZG9jdW1lbnRFbGVtZW50LG89ZS5ib2R5O2Y9KGU9ZS5kZWZhdWx0Vmlldyk/ZS5nZXRDb21wdXRlZFN0eWxlKGIsbnVsbCk6Yi5jdXJyZW50U3R5bGU7Zm9yKHZhciBrPWIub2Zmc2V0VG9wLG49Yi5vZmZzZXRMZWZ0OyhiPWIucGFyZW50Tm9kZSkmJmIhPT1vJiZiIT09aTspe2lmKGMub2Zmc2V0LnN1cHBvcnRzRml4ZWRQb3NpdGlvbiYmZi5wb3NpdGlvbj09PSJmaXhlZCIpYnJlYWs7aj1lP2UuZ2V0Q29tcHV0ZWRTdHlsZShiLG51bGwpOmIuY3VycmVudFN0eWxlOw0Kay09Yi5zY3JvbGxUb3A7bi09Yi5zY3JvbGxMZWZ0O2lmKGI9PT1kKXtrKz1iLm9mZnNldFRvcDtuKz1iLm9mZnNldExlZnQ7aWYoYy5vZmZzZXQuZG9lc05vdEFkZEJvcmRlciYmIShjLm9mZnNldC5kb2VzQWRkQm9yZGVyRm9yVGFibGVBbmRDZWxscyYmL150KGFibGV8ZHxoKSQvaS50ZXN0KGIubm9kZU5hbWUpKSl7ays9cGFyc2VGbG9hdChqLmJvcmRlclRvcFdpZHRoKXx8MDtuKz1wYXJzZUZsb2F0KGouYm9yZGVyTGVmdFdpZHRoKXx8MH1mPWQ7ZD1iLm9mZnNldFBhcmVudH1pZihjLm9mZnNldC5zdWJ0cmFjdHNCb3JkZXJGb3JPdmVyZmxvd05vdFZpc2libGUmJmoub3ZlcmZsb3chPT0idmlzaWJsZSIpe2srPXBhcnNlRmxvYXQoai5ib3JkZXJUb3BXaWR0aCl8fDA7bis9cGFyc2VGbG9hdChqLmJvcmRlckxlZnRXaWR0aCl8fDB9Zj1qfWlmKGYucG9zaXRpb249PT0icmVsYXRpdmUifHxmLnBvc2l0aW9uPT09InN0YXRpYyIpe2srPW8ub2Zmc2V0VG9wO24rPW8ub2Zmc2V0TGVmdH1pZihjLm9mZnNldC5zdXBwb3J0c0ZpeGVkUG9zaXRpb24mJg0KZi5wb3NpdGlvbj09PSJmaXhlZCIpe2srPU1hdGgubWF4KGkuc2Nyb2xsVG9wLG8uc2Nyb2xsVG9wKTtuKz1NYXRoLm1heChpLnNjcm9sbExlZnQsby5zY3JvbGxMZWZ0KX1yZXR1cm57dG9wOmssbGVmdDpufX07Yy5vZmZzZXQ9e2luaXRpYWxpemU6ZnVuY3Rpb24oKXt2YXIgYT1zLmJvZHksYj1zLmNyZWF0ZUVsZW1lbnQoImRpdiIpLGQsZixlLGo9cGFyc2VGbG9hdChjLmN1ckNTUyhhLCJtYXJnaW5Ub3AiLHRydWUpKXx8MDtjLmV4dGVuZChiLnN0eWxlLHtwb3NpdGlvbjoiYWJzb2x1dGUiLHRvcDowLGxlZnQ6MCxtYXJnaW46MCxib3JkZXI6MCx3aWR0aDoiMXB4IixoZWlnaHQ6IjFweCIsdmlzaWJpbGl0eToiaGlkZGVuIn0pO2IuaW5uZXJIVE1MPSI8ZGl2IHN0eWxlPSdwb3NpdGlvbjphYnNvbHV0ZTt0b3A6MDtsZWZ0OjA7bWFyZ2luOjA7Ym9yZGVyOjVweCBzb2xpZCAjMDAwO3BhZGRpbmc6MDt3aWR0aDoxcHg7aGVpZ2h0OjFweDsnPjxkaXY+PC9kaXY+PC9kaXY+PHRhYmxlIHN0eWxlPSdwb3NpdGlvbjphYnNvbHV0ZTt0b3A6MDtsZWZ0OjA7bWFyZ2luOjA7Ym9yZGVyOjVweCBzb2xpZCAjMDAwO3BhZGRpbmc6MDt3aWR0aDoxcHg7aGVpZ2h0OjFweDsnIGNlbGxwYWRkaW5nPScwJyBjZWxsc3BhY2luZz0nMCc+PHRyPjx0ZD48L3RkPjwvdHI+PC90YWJsZT4iOw0KYS5pbnNlcnRCZWZvcmUoYixhLmZpcnN0Q2hpbGQpO2Q9Yi5maXJzdENoaWxkO2Y9ZC5maXJzdENoaWxkO2U9ZC5uZXh0U2libGluZy5maXJzdENoaWxkLmZpcnN0Q2hpbGQ7dGhpcy5kb2VzTm90QWRkQm9yZGVyPWYub2Zmc2V0VG9wIT09NTt0aGlzLmRvZXNBZGRCb3JkZXJGb3JUYWJsZUFuZENlbGxzPWUub2Zmc2V0VG9wPT09NTtmLnN0eWxlLnBvc2l0aW9uPSJmaXhlZCI7Zi5zdHlsZS50b3A9IjIwcHgiO3RoaXMuc3VwcG9ydHNGaXhlZFBvc2l0aW9uPWYub2Zmc2V0VG9wPT09MjB8fGYub2Zmc2V0VG9wPT09MTU7Zi5zdHlsZS5wb3NpdGlvbj1mLnN0eWxlLnRvcD0iIjtkLnN0eWxlLm92ZXJmbG93PSJoaWRkZW4iO2Quc3R5bGUucG9zaXRpb249InJlbGF0aXZlIjt0aGlzLnN1YnRyYWN0c0JvcmRlckZvck92ZXJmbG93Tm90VmlzaWJsZT1mLm9mZnNldFRvcD09PS01O3RoaXMuZG9lc05vdEluY2x1ZGVNYXJnaW5JbkJvZHlPZmZzZXQ9YS5vZmZzZXRUb3AhPT1qO2EucmVtb3ZlQ2hpbGQoYik7DQpjLm9mZnNldC5pbml0aWFsaXplPWMubm9vcH0sYm9keU9mZnNldDpmdW5jdGlvbihhKXt2YXIgYj1hLm9mZnNldFRvcCxkPWEub2Zmc2V0TGVmdDtjLm9mZnNldC5pbml0aWFsaXplKCk7aWYoYy5vZmZzZXQuZG9lc05vdEluY2x1ZGVNYXJnaW5JbkJvZHlPZmZzZXQpe2IrPXBhcnNlRmxvYXQoYy5jdXJDU1MoYSwibWFyZ2luVG9wIix0cnVlKSl8fDA7ZCs9cGFyc2VGbG9hdChjLmN1ckNTUyhhLCJtYXJnaW5MZWZ0Iix0cnVlKSl8fDB9cmV0dXJue3RvcDpiLGxlZnQ6ZH19LHNldE9mZnNldDpmdW5jdGlvbihhLGIsZCl7aWYoL3N0YXRpYy8udGVzdChjLmN1ckNTUyhhLCJwb3NpdGlvbiIpKSlhLnN0eWxlLnBvc2l0aW9uPSJyZWxhdGl2ZSI7dmFyIGY9YyhhKSxlPWYub2Zmc2V0KCksaj1wYXJzZUludChjLmN1ckNTUyhhLCJ0b3AiLHRydWUpLDEwKXx8MCxpPXBhcnNlSW50KGMuY3VyQ1NTKGEsImxlZnQiLHRydWUpLDEwKXx8MDtpZihjLmlzRnVuY3Rpb24oYikpYj1iLmNhbGwoYSwNCmQsZSk7ZD17dG9wOmIudG9wLWUudG9wK2osbGVmdDpiLmxlZnQtZS5sZWZ0K2l9OyJ1c2luZyJpbiBiP2IudXNpbmcuY2FsbChhLGQpOmYuY3NzKGQpfX07Yy5mbi5leHRlbmQoe3Bvc2l0aW9uOmZ1bmN0aW9uKCl7aWYoIXRoaXNbMF0pcmV0dXJuIG51bGw7dmFyIGE9dGhpc1swXSxiPXRoaXMub2Zmc2V0UGFyZW50KCksZD10aGlzLm9mZnNldCgpLGY9L15ib2R5fGh0bWwkL2kudGVzdChiWzBdLm5vZGVOYW1lKT97dG9wOjAsbGVmdDowfTpiLm9mZnNldCgpO2QudG9wLT1wYXJzZUZsb2F0KGMuY3VyQ1NTKGEsIm1hcmdpblRvcCIsdHJ1ZSkpfHwwO2QubGVmdC09cGFyc2VGbG9hdChjLmN1ckNTUyhhLCJtYXJnaW5MZWZ0Iix0cnVlKSl8fDA7Zi50b3ArPXBhcnNlRmxvYXQoYy5jdXJDU1MoYlswXSwiYm9yZGVyVG9wV2lkdGgiLHRydWUpKXx8MDtmLmxlZnQrPXBhcnNlRmxvYXQoYy5jdXJDU1MoYlswXSwiYm9yZGVyTGVmdFdpZHRoIix0cnVlKSl8fDA7cmV0dXJue3RvcDpkLnRvcC0NCmYudG9wLGxlZnQ6ZC5sZWZ0LWYubGVmdH19LG9mZnNldFBhcmVudDpmdW5jdGlvbigpe3JldHVybiB0aGlzLm1hcChmdW5jdGlvbigpe2Zvcih2YXIgYT10aGlzLm9mZnNldFBhcmVudHx8cy5ib2R5O2EmJiEvXmJvZHl8aHRtbCQvaS50ZXN0KGEubm9kZU5hbWUpJiZjLmNzcyhhLCJwb3NpdGlvbiIpPT09InN0YXRpYyI7KWE9YS5vZmZzZXRQYXJlbnQ7cmV0dXJuIGF9KX19KTtjLmVhY2goWyJMZWZ0IiwiVG9wIl0sZnVuY3Rpb24oYSxiKXt2YXIgZD0ic2Nyb2xsIitiO2MuZm5bZF09ZnVuY3Rpb24oZil7dmFyIGU9dGhpc1swXSxqO2lmKCFlKXJldHVybiBudWxsO2lmKGYhPT13KXJldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXtpZihqPXdhKHRoaXMpKWouc2Nyb2xsVG8oIWE/ZjpjKGopLnNjcm9sbExlZnQoKSxhP2Y6YyhqKS5zY3JvbGxUb3AoKSk7ZWxzZSB0aGlzW2RdPWZ9KTtlbHNlIHJldHVybihqPXdhKGUpKT8icGFnZVhPZmZzZXQiaW4gaj9qW2E/InBhZ2VZT2Zmc2V0IjoNCiJwYWdlWE9mZnNldCJdOmMuc3VwcG9ydC5ib3hNb2RlbCYmai5kb2N1bWVudC5kb2N1bWVudEVsZW1lbnRbZF18fGouZG9jdW1lbnQuYm9keVtkXTplW2RdfX0pO2MuZWFjaChbIkhlaWdodCIsIldpZHRoIl0sZnVuY3Rpb24oYSxiKXt2YXIgZD1iLnRvTG93ZXJDYXNlKCk7Yy5mblsiaW5uZXIiK2JdPWZ1bmN0aW9uKCl7cmV0dXJuIHRoaXNbMF0/Yy5jc3ModGhpc1swXSxkLGZhbHNlLCJwYWRkaW5nIik6bnVsbH07Yy5mblsib3V0ZXIiK2JdPWZ1bmN0aW9uKGYpe3JldHVybiB0aGlzWzBdP2MuY3NzKHRoaXNbMF0sZCxmYWxzZSxmPyJtYXJnaW4iOiJib3JkZXIiKTpudWxsfTtjLmZuW2RdPWZ1bmN0aW9uKGYpe3ZhciBlPXRoaXNbMF07aWYoIWUpcmV0dXJuIGY9PW51bGw/bnVsbDp0aGlzO2lmKGMuaXNGdW5jdGlvbihmKSlyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKGope3ZhciBpPWModGhpcyk7aVtkXShmLmNhbGwodGhpcyxqLGlbZF0oKSkpfSk7cmV0dXJuInNjcm9sbFRvImluDQplJiZlLmRvY3VtZW50P2UuZG9jdW1lbnQuY29tcGF0TW9kZT09PSJDU1MxQ29tcGF0IiYmZS5kb2N1bWVudC5kb2N1bWVudEVsZW1lbnRbImNsaWVudCIrYl18fGUuZG9jdW1lbnQuYm9keVsiY2xpZW50IitiXTplLm5vZGVUeXBlPT09OT9NYXRoLm1heChlLmRvY3VtZW50RWxlbWVudFsiY2xpZW50IitiXSxlLmJvZHlbInNjcm9sbCIrYl0sZS5kb2N1bWVudEVsZW1lbnRbInNjcm9sbCIrYl0sZS5ib2R5WyJvZmZzZXQiK2JdLGUuZG9jdW1lbnRFbGVtZW50WyJvZmZzZXQiK2JdKTpmPT09dz9jLmNzcyhlLGQpOnRoaXMuY3NzKGQsdHlwZW9mIGY9PT0ic3RyaW5nIj9mOmYrInB4Iil9fSk7QS5qUXVlcnk9QS4kPWN9KSh3aW5kb3cpOw0KDQo=
ENDDATA;
echo base64_decode($data);
?>
</script>
<?php else: ?>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
<?php endif; ?>
<script type="text/javascript">
	if(!this.JSON){this.JSON={};}
	(function(){function f(n){return n<10?'0'+n:n;}
	if(typeof Date.prototype.toJSON!=='function'){Date.prototype.toJSON=function(key){return isFinite(this.valueOf())?this.getUTCFullYear()+'-'+
	f(this.getUTCMonth()+1)+'-'+
	f(this.getUTCDate())+'T'+
	f(this.getUTCHours())+':'+
	f(this.getUTCMinutes())+':'+
	f(this.getUTCSeconds())+'Z':null;};String.prototype.toJSON=Number.prototype.toJSON=Boolean.prototype.toJSON=function(key){return this.valueOf();};}
	var cx=/[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,escapable=/[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,gap,indent,meta={'\b':'\\b','\t':'\\t','\n':'\\n','\f':'\\f','\r':'\\r','"':'\\"','\\':'\\\\'},rep;function quote(string){escapable.lastIndex=0;return escapable.test(string)?'"'+string.replace(escapable,function(a){var c=meta[a];return typeof c==='string'?c:'\\u'+('0000'+a.charCodeAt(0).toString(16)).slice(-4);})+'"':'"'+string+'"';}
	function str(key,holder){var i,k,v,length,mind=gap,partial,value=holder[key];if(value&&typeof value==='object'&&typeof value.toJSON==='function'){value=value.toJSON(key);}
	if(typeof rep==='function'){value=rep.call(holder,key,value);}
	switch(typeof value){case'string':return quote(value);case'number':return isFinite(value)?String(value):'null';case'boolean':case'null':return String(value);case'object':if(!value){return'null';}
	gap+=indent;partial=[];if(Object.prototype.toString.apply(value)==='[object Array]'){length=value.length;for(i=0;i<length;i+=1){partial[i]=str(i,value)||'null';}
	v=partial.length===0?'[]':gap?'[\n'+gap+
	partial.join(',\n'+gap)+'\n'+
	mind+']':'['+partial.join(',')+']';gap=mind;return v;}
	if(rep&&typeof rep==='object'){length=rep.length;for(i=0;i<length;i+=1){k=rep[i];if(typeof k==='string'){v=str(k,value);if(v){partial.push(quote(k)+(gap?': ':':')+v);}}}}else{for(k in value){if(Object.hasOwnProperty.call(value,k)){v=str(k,value);if(v){partial.push(quote(k)+(gap?': ':':')+v);}}}}
	v=partial.length===0?'{}':gap?'{\n'+gap+partial.join(',\n'+gap)+'\n'+
	mind+'}':'{'+partial.join(',')+'}';gap=mind;return v;}}
	if(typeof JSON.stringify!=='function'){JSON.stringify=function(value,replacer,space){var i;gap='';indent='';if(typeof space==='number'){for(i=0;i<space;i+=1){indent+=' ';}}else if(typeof space==='string'){indent=space;}
	rep=replacer;if(replacer&&typeof replacer!=='function'&&(typeof replacer!=='object'||typeof replacer.length!=='number')){throw new Error('JSON.stringify');}
	return str('',{'':value});};}
	if(typeof JSON.parse!=='function'){JSON.parse=function(text,reviver){var j;function walk(holder,key){var k,v,value=holder[key];if(value&&typeof value==='object'){for(k in value){if(Object.hasOwnProperty.call(value,k)){v=walk(value,k);if(v!==undefined){value[k]=v;}else{delete value[k];}}}}
	return reviver.call(holder,key,value);}
	text=String(text);cx.lastIndex=0;if(cx.test(text)){text=text.replace(cx,function(a){return'\\u'+
	('0000'+a.charCodeAt(0).toString(16)).slice(-4);});}
	if(/^[\],:{}\s]*$/.test(text.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,'@').replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,']').replace(/(?:^|:|,)(?:\s*\[)+/g,''))){j=eval('('+text+')');return typeof reviver==='function'?walk({'':j},''):j;}
	throw new SyntaxError('JSON.parse');};}}());
</script>
<script type="text/javascript">
	var akeeba_automation = <?php echo $automation->hasAutomation() ? 'true' : 'false' ?>;

	$(document).ready(function(){
		// Hide 2nd Page
		$('#page2').css('display','none');

		// Translate the GUI
		translateGUI();

		// Hook interaction handlers
		$(document).keyup( closeLightbox );
		$('#kickstart\\.procengine').change( onChangeProcengine );
		$('#checkFTPTempDir').click( oncheckFTPTempDirClick );
		$('#resetFTPTempDir').click( onresetFTPTempDir );
		$('#testFTP').click( onTestFTPClick );
		$('#gobutton').click( onStartExtraction );
		$('#runInstaller').click( onRunInstallerClick );
		$('#runCleanup').click( onRunCleanupClick );
		$('#gotoSite').click(function(event){window.open('index.php','finalstepsite'); window.close();});
		$('#gotoAdministrator').click(function(event){window.open('administrator/index.php','finalstepadmin'); window.close();});
		$('#gotoStart').click( onGotoStartClick );

		// Reset the progress bar
		setProgressBar(0);

		// Do we have automation?
/*		if(akeeba_automation) {
			$('#automode').css('display','block');
			$('#gobutton').click();
		} else {
			// Show warning
			if( jQuery.browser.msie && (jQuery.browser.version.substr(0,1) == '7') )
			{
				$('#ie7Warning').css('display','block');
			}
			$('#preextraction').css('display','block');
			$('#fade').css('display','block');
		}*/
	});

	var translation = {
	<?php echoTranslationStrings(); ?>
	}

	var akeeba_ajax_url = '<?php echo basename(__FILE__); ?>';
	var akeeba_error_callback = onGenericError;
	var akeeba_restoration_stat_inbytes = 0;
	var akeeba_restoration_stat_outbytes = 0;
	var akeeba_restoration_stat_files = 0;
	var akeeba_restoration_stat_total = 0;
	var akeeba_factory = null;

	function translateGUI()
	{
		$('*').each(function(i,e){
			transKey = $(e).text();
			if(array_key_exists(transKey, translation))
			{
				$(e).text( translation[transKey] );
			}
		});
	}

	function trans(key)
	{
		if(array_key_exists(key, translation)) {
			return translation[key];
		} else {
			return key;
		}
	}

	function array_key_exists ( key, search ) {
	   if (!search || (search.constructor !== Array && search.constructor !== Object)){
	        return false;
	    }
	    return key in search;
	}

	function empty (mixed_var) {
	    var key;

	    if (mixed_var === "" ||
	        mixed_var === 0 ||
	        mixed_var === "0" ||
	        mixed_var === null ||
	        mixed_var === false ||
	        typeof mixed_var === 'undefined'
	    ){
	        return true;
	    }

	    if (typeof mixed_var == 'object') {
	        for (key in mixed_var) {
	            return false;
	        }
	        return true;
	    }

	    return false;
	}

	function is_array (mixed_var) {
	    var key = '';
	    var getFuncName = function (fn) {
	        var name = (/\W*function\s+([\w\$]+)\s*\(/).exec(fn);
	        if (!name) {
	            return '(Anonymous)';
	        }
	        return name[1];
	    };

	    if (!mixed_var) {
	        return false;
	    }

	    // BEGIN REDUNDANT
	    this.php_js = this.php_js || {};
	    this.php_js.ini = this.php_js.ini || {};
	    // END REDUNDANT

	    if (typeof mixed_var === 'object') {

	        if (this.php_js.ini['phpjs.objectsAsArrays'] &&  // Strict checking for being a JavaScript array (only check this way if call ini_set('phpjs.objectsAsArrays', 0) to disallow objects as arrays)
	            (
	            (this.php_js.ini['phpjs.objectsAsArrays'].local_value.toLowerCase &&
	                    this.php_js.ini['phpjs.objectsAsArrays'].local_value.toLowerCase() === 'off') ||
	                parseInt(this.php_js.ini['phpjs.objectsAsArrays'].local_value, 10) === 0)
	            ) {
	            return mixed_var.hasOwnProperty('length') && // Not non-enumerable because of being on parent class
	                            !mixed_var.propertyIsEnumerable('length') && // Since is own property, if not enumerable, it must be a built-in function
	                                getFuncName(mixed_var.constructor) !== 'String'; // exclude String()
	        }

	        if (mixed_var.hasOwnProperty) {
	            for (key in mixed_var) {
	                // Checks whether the object has the specified property
	                // if not, we figure it's not an object in the sense of a php-associative-array.
	                if (false === mixed_var.hasOwnProperty(key)) {
	                    return false;
	                }
	            }
	        }

	        // Read discussion at: http://kevin.vanzonneveld.net/techblog/article/javascript_equivalent_for_phps_is_array/
	        return true;
	    }

	    return false;
	}

	/**
	 * Performs an AJAX request and returns the parsed JSON output.
	 * The global akeeba_ajax_url is used as the AJAX proxy URL.
	 * If there is no errorCallback, the global akeeba_error_callback is used.
	 * @param data An object with the query data, e.g. a serialized form
	 * @param successCallback A function accepting a single object parameter, called on success
	 * @param errorCallback A function accepting a single string parameter, called on failure
	 */
	function doAjax(data, successCallback, errorCallback)
	{
		var structure =
		{
			type: "POST",
			url: akeeba_ajax_url,
			cache: false,
			data: data,
			timeout: 600000,
			success: function(msg) {
				// Initialize
				var junk = null;
				var message = "";

				// Get rid of junk before the data
				var valid_pos = msg.indexOf('###');
				if( valid_pos == -1 ) {
					// Valid data not found in the response
					msg = 'Invalid AJAX data received:<br/>' + msg;
					if(errorCallback == null)
					{
						if(akeeba_error_callback != null)
						{
							akeeba_error_callback(msg);
						}
					}
					else
					{
						errorCallback(msg);
					}
					return;
				} else if( valid_pos != 0 ) {
					// Data is prefixed with junk
					junk = msg.substr(0, valid_pos);
					message = msg.substr(valid_pos);
				}
				else
				{
					message = msg;
				}
				message = message.substr(3); // Remove triple hash in the beginning

				// Get of rid of junk after the data
				var valid_pos = message.lastIndexOf('###');
				message = message.substr(0, valid_pos); // Remove triple hash in the end

				try {
					var data = eval('('+message+')');
				} catch(err) {
					var msg = err.message + "\n<br/>\n<pre>\n" + message + "\n</pre>";
					if(errorCallback == null)
					{
						if(akeeba_error_callback != null)
						{
							akeeba_error_callback(msg);
						}
					}
					else
					{
						errorCallback(msg);
					}
					return;
				}

				// Call the callback function
				successCallback(data);
			},
			error: function(Request, textStatus, errorThrown) {
				var message = '<strong>AJAX Loading Error</strong><br/>HTTP Status: '+Request.status+' ('+Request.statusText+')<br/>';
				message = message + 'Internal status: '+textStatus+'<br/>';
				message = message + 'XHR ReadyState: ' + Response.readyState + '<br/>';
				message = message + 'Raw server response:<br/>'+Request.responseText;
				if(errorCallback == null)
				{
					if(akeeba_error_callback != null)
					{
						akeeba_error_callback(message);
					}
				}
				else
				{
					errorCallback(message);
				}
			}
		};
		$.ajax( structure );
	}

	function onChangeProcengine(event)
	{
		if( $('#kickstart\\.procengine').val() == 'ftp' ) {
			$('#ftp-options').show('fast');
		} else {
			$('#ftp-options').hide('fast');
		}
	}

	function closeLightbox(event)
	{
		var closeMe = false;

		if( (event == null) || (event == undefined) ) {
			closeMe = true;
		} else if(event.keyCode == '27') {
			closeMe = true;
		}

		if(closeMe)
		{
			document.getElementById('preextraction').style.display='none';
			document.getElementById('genericerror').style.display='none';
			document.getElementById('fade').style.display='none';
			$(document).unbind('keyup', closeLightbox);
		}
	}

	function onGenericError(msg)
	{
		$('#genericerrorInner').html(msg);
		$('#genericerror').css('display','block');
		$('#fade').css('display','block');
		$(document).keyup(closeLightbox);
	}

	function setProgressBar(percent)
	{
		var newValue = 0;

		if(percent <= 1) {
			newValue = 100 * percent;
		} else {
			newValue = percent;
		}

		$('#progressbar-inner').css('width',percent+'%');
	}

	function oncheckFTPTempDirClick(event)
	{
		var data = {
			'task' : 'checkTempdir',
			'json': JSON.stringify({
				'kickstart.ftp.tempdir': $('#kickstart\\.ftp\\.tempdir').val()
			})
		};

		doAjax(data, function(ret){
			var key = ret.status ? 'FTP_TEMPDIR_WRITABLE' : 'FTP_TEMPDIR_UNWRITABLE';
			alert( trans(key) );
		});
	}

	function onTestFTPClick(event)
	{
		var data = {
			'task' : 'checkFTP',
			'json': JSON.stringify({
				'kickstart.ftp.host':		$('#kickstart\\.ftp\\.host').val(),
				'kickstart.ftp.port':		$('#kickstart\\.ftp\\.port').val(),
				'kickstart.ftp.ssl':		$('#kickstart\\.ftp\\.ssl').is(':checked'),
				'kickstart.ftp.passive':	$('#kickstart\\.ftp\\.passive').is(':checked'),
				'kickstart.ftp.user':		$('#kickstart\\.ftp\\.user').val(),
				'kickstart.ftp.pass':		$('#kickstart\\.ftp\\.pass').val(),
				'kickstart.ftp.dir':		$('#kickstart\\.ftp\\.dir').val(),
				'kickstart.ftp.tempdir':	$('#kickstart\\.ftp\\.tempdir').val()
			})
		};
		doAjax(data, function(ret){
			var key = ret.status ? 'FTP_CONNECTION_OK' : 'FTP_CONNECTION_FAILURE';
			alert( trans(key) + "\n\n" + (ret.status ? '' : ret.message) );
		});
	}

	function onStartExtraction()
	{
		$('#page1').hide('fast');
		$('#page2').show('fast');

		$('#currentFile').text( '' );

		akeeba_error_callback = errorHandler;

		var data = {
			'task' : 'startExtracting',
			'json': JSON.stringify({
<?php if(!$automation->hasAutomation()): ?>
				'kickstart.setup.sourcefile':		$('#kickstart\\.setup\\.sourcefile').val(),
				'kickstart.jps.password':			$('#kickstart\\.jps\\.password').val(),
				'kickstart.tuning.min_exec_time':	$('#kickstart\\.tuning\\.min_exec_time').val(),
				'kickstart.tuning.max_exec_time':	$('#kickstart\\.tuning\\.max_exec_time').val(),
				'kickstart.stealth.enable': 		$('#kickstart\\.stealth\\.enable').is(':checked'),
				'kickstart.stealth.url': 			$('#kickstart\\.stealth\\.url').val(),
				'kickstart.tuning.run_time_bias':	75,
				'kickstart.setup.restoreperms':		0,
				'kickstart.setup.dryrun':			0,
				'kickstart.setup.ignoreerrors':		0,
				'kickstart.enabled':				1,
				'kickstart.security.password':		'',
				'kickstart.procengine':				$('#kickstart\\.procengine').val(),
				'kickstart.ftp.host':				$('#kickstart\\.ftp\\.host').val(),
				'kickstart.ftp.port':				$('#kickstart\\.ftp\\.port').val(),
				'kickstart.ftp.ssl':				$('#kickstart\\.ftp\\.ssl').is(':checked'),
				'kickstart.ftp.passive':			$('#kickstart\\.ftp\\.passive').is(':checked'),
				'kickstart.ftp.user':				$('#kickstart\\.ftp\\.user').val(),
				'kickstart.ftp.pass':				$('#kickstart\\.ftp\\.pass').val(),
				'kickstart.ftp.dir':				$('#kickstart\\.ftp\\.dir').val(),
				'kickstart.ftp.tempdir':			$('#kickstart\\.ftp\\.tempdir').val()
<?php else: ?>
				'kickstart.setup.sourcefile':		<?php echo autoVar('kickstart.setup.sourcefile') ?>,
				'kickstart.jps.password':			<?php echo autoVar('kickstart.jps.password') ?>,
				'kickstart.tuning.min_exec_time':	<?php echo autoVar('kickstart.tuning.min_exec_time', 1) ?>,
				'kickstart.tuning.max_exec_time':	<?php echo autoVar('kickstart.tuning.max_exec_time', 5) ?>,
				'kickstart.stealth.enable': 		false,
				'kickstart.tuning.run_time_bias':	75,
				'kickstart.setup.restoreperms':		0,
				'kickstart.setup.dryrun':			0,
				'kickstart.setup.ignoreerrors':		0,
				'kickstart.enabled':				1,
				'kickstart.security.password':		'',
				'kickstart.procengine':				<?php echo autoVar('kickstart.procengine', 'direct') ?>,
				'kickstart.ftp.host':				<?php echo autoVar('kickstart.ftp.host','localhost') ?>,
				'kickstart.ftp.port':				<?php echo autoVar('kickstart.ftp.port',22) ?>,
				'kickstart.ftp.ssl':				<?php echo autoVar('kickstart.ftp.ssl',0) ?>,
				'kickstart.ftp.passive':			<?php echo autoVar('kickstart.ftp.passive',1) ?>,
				'kickstart.ftp.user':				<?php echo autoVar('kickstart.ftp.user') ?>,
				'kickstart.ftp.pass':				<?php echo autoVar('kickstart.ftp.pass') ?>,
				'kickstart.ftp.dir':				<?php echo autoVar('kickstart.ftp.dir','/') ?>,
				'kickstart.ftp.tempdir':			<?php echo autoVar('kickstart.ftp.tempdir', AKKickstartUtils::getPath().'kicktemp') ?>
<?php endif; ?>
			})
		};
		doAjax(data, function(ret){
			processRestorationStep(ret);
		});
	}

	function processRestorationStep(data)
	{
		// Look for errors
		if(!data.status)
		{
			errorHandler(data.message);
			return;
		}

		// Propagate warnings to the GUI
		if( !empty(data.Warnings) )
		{
			$.each(data.Warnings, function(i, item){
				$('#warnings').append(
					$(document.createElement('div'))
					.html(item)
				);
				$('#warningsBox').show('fast');
			});
		}

		// Parse total size, if exists
		if(array_key_exists('totalsize', data))
		{
			if(is_array(data.filelist))
			{
				akeeba_restoration_stat_total = 0;
				$.each(data.filelist,function(i, item)
				{
					akeeba_restoration_stat_total += item[1];
				});
			}
			akeeba_restoration_stat_outbytes = 0;
			akeeba_restoration_stat_inbytes = 0;
			akeeba_restoration_stat_files = 0;
		}

		// Update GUI
		akeeba_restoration_stat_inbytes += data.bytesIn;
		akeeba_restoration_stat_outbytes += data.bytesOut;
		akeeba_restoration_stat_files += data.files;
		var percentage = 0;
		if( akeeba_restoration_stat_total > 0 )
		{
			percentage = 100 * akeeba_restoration_stat_inbytes / akeeba_restoration_stat_total;
			if(percentage < 0) {
				percentage = 0;
			} else if(percentage > 100) {
				percentage = 100;
			}
		}
		if(data.done) percentage = 100;
		setProgressBar(percentage);
		$('#currentFile').text( data.lastfile );

		if(!empty(data.factory)) akeeba_factory = data.factory;

		post = {
			'task'	: 'continueExtracting',
			'json'	: JSON.stringify({factory: akeeba_factory})
		};

		if(!data.done)
		{
			doAjax(post, function(ret){
				processRestorationStep(ret);
			});
		}
		else
		{
			$('#page2a').hide('fast');
			$('#extractionComplete').show('fast');

			$('#runInstaller').css('display','inline-block');
			if(akeeba_automation) $('#runInstaller').click();
		}
	}

	function onGotoStartClick(event)
	{
		$('#page2').hide('fast');
		$('#error').hide('fast');
		$('#page1').show('fast');
	}

	function onRunInstallerClick(event)
	{
		window.location ='piwik/','installer';
	
		$('#runCleanup').css('display','inline-block');
		$('#runInstaller').hide('fast');
	}

	function onRunCleanupClick(event)
	{
		post = {
			'task'	: 'cleanUp',
			// Passing the factory preserves the renamed files array
			'json'	: JSON.stringify({factory: akeeba_factory})
		};

		doAjax(post, function(ret){
			$('#runCleanup').hide('fast');
			$('#gotoSite').css('display','inline-block');
			$('#gotoAdministrator').css('display','inline-block');
			$('#gotoPostRestorationRroubleshooting').css('display','block');
		});
	}

	function errorHandler(msg)
	{
		$('#errorMessage').html(msg);
		$('#error').show('fast');
	}

	function onresetFTPTempDir(event)
	{
		$('#kickstart\\.ftp\\.tempdir').val('<?php echo addcslashes(AKKickstartUtils::getPath(),'\\\'"') ?>');
	}

		/**
	 * Akeeba Kickstart Update Check
	 */

	var akeeba_update = {version: '0'};
	var akeeba_version = '3.3.2';
	
	function version_compare (v1, v2, operator) {
		// BEGIN REDUNDANT
		this.php_js = this.php_js || {};
		this.php_js.ENV = this.php_js.ENV || {};
		// END REDUNDANT
		// Important: compare must be initialized at 0. 
		var i = 0,
			x = 0,
			compare = 0,
			// vm maps textual PHP versions to negatives so they're less than 0.
			// PHP currently defines these as CASE-SENSITIVE. It is important to
			// leave these as negatives so that they can come before numerical versions
			// and as if no letters were there to begin with.
			// (1alpha is < 1 and < 1.1 but > 1dev1)
			// If a non-numerical value can't be mapped to this table, it receives
			// -7 as its value.
			vm = {
				'dev': -6,
				'alpha': -5,
				'a': -5,
				'beta': -4,
				'b': -4,
				'RC': -3,
				'rc': -3,
				'#': -2,
				'p': -1,
				'pl': -1
			},
			// This function will be called to prepare each version argument.
			// It replaces every _, -, and + with a dot.
			// It surrounds any nonsequence of numbers/dots with dots.
			// It replaces sequences of dots with a single dot.
			//    version_compare('4..0', '4.0') == 0
			// Important: A string of 0 length needs to be converted into a value
			// even less than an unexisting value in vm (-7), hence [-8].
			// It's also important to not strip spaces because of this.
			//   version_compare('', ' ') == 1
			prepVersion = function (v) {
				v = ('' + v).replace(/[_\-+]/g, '.');
				v = v.replace(/([^.\d]+)/g, '.$1.').replace(/\.{2,}/g, '.');
				return (!v.length ? [-8] : v.split('.'));
			},
			// This converts a version component to a number.
			// Empty component becomes 0.
			// Non-numerical component becomes a negative number.
			// Numerical component becomes itself as an integer.
			numVersion = function (v) {
				return !v ? 0 : (isNaN(v) ? vm[v] || -7 : parseInt(v, 10));
			};
		v1 = prepVersion(v1);
		v2 = prepVersion(v2);
		x = Math.max(v1.length, v2.length);
		for (i = 0; i < x; i++) {
			if (v1[i] == v2[i]) {
				continue;
			}
			v1[i] = numVersion(v1[i]);
			v2[i] = numVersion(v2[i]);
			if (v1[i] < v2[i]) {
				compare = -1;
				break;
			} else if (v1[i] > v2[i]) {
				compare = 1;
				break;
			}
		}
		if (!operator) {
			return compare;
		}

		// Important: operator is CASE-SENSITIVE.
		// "No operator" seems to be treated as "<."
		// Any other values seem to make the function return null.
		switch (operator) {
		case '>':
		case 'gt':
			return (compare > 0);
		case '>=':
		case 'ge':
			return (compare >= 0);
		case '<=':
		case 'le':
			return (compare <= 0);
		case '==':
		case '=':
		case 'eq':
			return (compare === 0);
		case '<>':
		case '!=':
		case 'ne':
			return (compare !== 0);
		case '':
		case '<':
		case 'lt':
			return (compare < 0);
		default:
			return null;
		}
	}

	function checkUpdates()
	{
		var structure =
		{
			type: "GET",
			url: 'http://query.yahooapis.com/v1/public/yql',
			data: {
				q: 'SELECT * FROM xml WHERE url="http://cdn.akeebabackup.com/updates/kickstart.xml"',
				format: 'json',
				callback: 'updatesCallback'
			},
			cache: true,
			crossDomain: true,
			jsonp: 'updatesCallback',
			timeout: 15000
		};
		$.ajax( structure );
	}
	
	function updatesCallback(msg)
	{
		$.each(msg.query.results.updates.update, function(i, el){
			var myUpdate = {
				'version'	: el.version,
				'infourl'	: el.infourl['content'],
				'dlurl'		: el.downloads.downloadurl.content
			}
			if(version_compare(myUpdate.version, akeeba_update.version, 'ge')) {
				akeeba_update = myUpdate;
			}
		});
		
		if(version_compare(akeeba_update.version, akeeba_version, 'gt')) {
			notifyAboutUpdates();
		}
	}
	
	function notifyAboutUpdates()
	{
		$('#update-version').text(akeeba_update.version);
		$('#update-dlnow').attr('href', akeeba_update.dlurl);
		$('#update-whatsnew').attr('href', akeeba_update.infourl);
		$('#update-notification').show('slow');
	}
	</script>
</head>
<body>

<div id="page-container">

<div id="page1">
      	<input type="hidden" id="kickstart.setup.sourcefile" value="piwik.zip" />
	<div class="step1">
		<div class="circle">1</div>
		<h2>EXTRACT_FILES</h2>
		<div class="area-container">
			<span></span>
			<span id="gobutton" class="button">BTN_START</span>
		</div>
	</div>

	<div class="clr"><br /></div>
</div>

<div id="page2">
	<div id="page2a">
		<div class="circle">2</div>
		<h2>EXTRACTING</h2>
		<div class="area-container">
			<div id="warn-not-close">DO_NOT_CLOSE_EXTRACT</div>
			<div id="progressbar">
				<div id="progressbar-inner">&nbsp;</div>
			</div>
			<div id="currentFile"></div>
		</div>
	</div>

	<div id="extractionComplete" style="display: none">
		<div class="circle">3</div>
		<h2>RESTACLEANUP</h2>
		<div id="runInstaller" class="button">BTN_RUNINSTALLER</div>
	</div>

	<div id="warningsBox" style="display: none;">
		<div id="warningsHeader">
			<h2>WARNINGS</h2>
		</div>
		<div id="warningsContainer">
		<div id="warnings"></div>
		</div>
	</div>

	<div id="error" style="display: none;">
		<h3>ERROR_OCCURED</h3>
		<p id="errorMessage"></p>
		<div id="gotoStart" class="button">BTN_GOTOSTART</div>
	</div>
</div>
</div>

</body>
</html>
	<?php
}

function createStealthURL()
{
	$filename = AKFactory::get('kickstart.stealth.url', '');
	// We need an HTML file!
	if(empty($filename)) return;
	// Make sure it ends in .html or .htm
	$filename = basename($filename);
	if( (strtolower(substr($filename,-5)) != '.html') && (strtolower(substr($filename,-4)) != '.htm') ) return;

	$filename_quoted = str_replace('.','\\.',$filename);
	$rewrite_base = trim(dirname(AKFactory::get('kickstart.stealth.url', '')),'/');

	// Get the IP
	$userIP = $_SERVER['REMOTE_ADDR'];
	$userIP = str_replace('.', '\.', $userIP);

	// Get the .htaccess contents
	$stealthHtaccess = <<<ENDHTACCESS
RewriteEngine On
RewriteBase /$rewrite_base
RewriteCond %{REMOTE_HOST}		!$userIP
RewriteCond %{REQUEST_URI}		!$filename_quoted
RewriteCond %{REQUEST_URI}		!(\.png|\.jpg|\.gif|\.jpeg|\.bmp|\.swf|\.css|\.js)$
RewriteRule (.*)				$filename	[R=307,L]

ENDHTACCESS;

	// Write the new .htaccess, removing the old one first
	$postproc =& AKFactory::getpostProc();
	$postproc->unlink('.htaccess');
	$tempfile = $postproc->processFilename('.htaccess');
	@file_put_contents($tempfile, $stealthHtaccess);
	$postproc->process();
}

class ExtractionObserver extends AKAbstractPartObserver
{
	public $compressedTotal = 0;
	public $uncompressedTotal = 0;
	public $filesProcessed = 0;
	public $totalSize = null;
	public $fileList = null;
	public $lastFile = '';

	public function update($object, $message)
	{
		if(!is_object($message)) return;

		if( !array_key_exists('type', get_object_vars($message)) ) return;

		if( $message->type == 'startfile' )
		{
			$this->lastFile = $message->content->file;
			$this->filesProcessed++;
			$this->compressedTotal += $message->content->compressed;
			$this->uncompressedTotal += $message->content->uncompressed;
		}
		elseif( $message->type == 'totalsize' )
		{
			$this->totalSize = $message->content->totalsize;
			$this->fileList = $message->content->filelist;
		}
	}

	public function __toString()
	{
		return __CLASS__;
	}

}

$retArray = array(
	'status'	=> true,
	'message'	=> null
);

$task = getQueryParam('task');
$json = getQueryParam('json');
$ajax = true;

switch($task)
{
	case 'checkTempdir':
		$retArray['status'] = false;
		if(!empty($json))
		{
			$data = json_decode($json, true);
			$dir = @$data['kickstart.ftp.tempdir'];
			if(!empty($dir))
			{
				$retArray['status'] = is_writable($dir);
			}
		}
		break;

	case 'checkFTP':
		$retArray['status'] = false;
		if(!empty($json))
		{
			$data = json_decode($json, true);
			foreach($data as $key => $value)
			{
				AKFactory::set($key, $value);
			}
			$ftp = new AKPostprocFTP();
			$retArray['message'] = $ftp->getError();
			$retArray['status'] = empty($retArray['message']);
		}
		break;

	case 'startExtracting':
	case 'continueExtracting':
		// Look for configuration values
		$retArray['status'] = false;
		if(!empty($json))
		{
			if($task == 'startExtracting') AKFactory::nuke();

			$oldJSON = $json;
			$json = json_decode($json, true);
			if(is_null($json)) {
				$json = stripslashes($oldJSON);
				$json = json_decode($json, true);
			}
			if(!empty($json)) foreach($json as $key => $value)
			{
				if( substr($key,0,9) == 'kickstart' ) {
					AKFactory::set($key, $value);
				}
			}

			// A "factory" variable will override all other settings.
			if( array_key_exists('factory', $json) )
			{
				// Get the serialized factory
				$serialized = $json['factory'];
				AKFactory::unserialize($serialized);
				AKFactory::set('kickstart.enabled', true);
			}

			// Make sure that the destination directory is always set (req'd by both FTP and Direct Writes modes)
			$removePath = AKFactory::get('kickstart.setup.destdir','');
			if(empty($removePath)) AKFactory::set('kickstart.setup.destdir', AKKickstartUtils::getPath());

			if($task=='startExtracting')
			{
				// If the Stealth Mode is enabled, create the .htaccess file
				if( AKFactory::get('kickstart.stealth.enable', false) )
				{
					createStealthURL();
				}
			}

			$engine =& AKFactory::getUnarchiver(); // Get the engine
			$observer = new ExtractionObserver(); // Create a new observer
			$engine->attach($observer); // Attach the observer
			$engine->tick();
			$ret = $engine->getStatusArray();

			if( $ret['Error'] != '' )
			{
				$retArray['status'] = false;
				$retArray['done'] = true;
				$retArray['message'] = $ret['Error'];
			}
			elseif( !$ret['HasRun'] )
			{
				$retArray['files'] = $observer->filesProcessed;
				$retArray['bytesIn'] = $observer->compressedTotal;
				$retArray['bytesOut'] = $observer->uncompressedTotal;
				$retArray['status'] = true;
				$retArray['done'] = true;
			}
			else
			{
				$retArray['files'] = $observer->filesProcessed;
				$retArray['bytesIn'] = $observer->compressedTotal;
				$retArray['bytesOut'] = $observer->uncompressedTotal;
				$retArray['status'] = true;
				$retArray['done'] = false;
				$retArray['factory'] = AKFactory::serialize();
			}

			if(!is_null($observer->totalSize))
			{
				$retArray['totalsize'] = $observer->totalSize;
				$retArray['filelist'] = $observer->fileList;
			}

			$retArray['Warnings'] = $ret['Warnings'];
			$retArray['lastfile'] = $observer->lastFile;
		}
		break;

	case 'cleanUp':
		if(!empty($json))
		{
			$json = json_decode($json, true);
			if( array_key_exists('factory', $json) )
			{
				// Get the serialized factory
				$serialized = $json['factory'];
				AKFactory::unserialize($serialized);
				AKFactory::set('kickstart.enabled', true);
			}
		}

		$unarchiver =& AKFactory::getUnarchiver(); // Get the engine
		$engine =& AKFactory::getPostProc();

		// 1. Remove installation
		recursive_remove_directory('installation');

		// 2. Run the renames, backwards
		$renames = $unarchiver->renameFiles;
		if(!empty($renames)) foreach( $renames as $original => $renamed ) {
			$engine->rename( $renamed, $original );
		}

		// 3. Delete the archive
		foreach( $unarchiver->archiveList as $archive )
		{
			$engine->unlink( $archive );
		}

		// 4. Suicide
		$engine->unlink( basename(__FILE__) );

		// 5. Delete translations
		$dh = opendir(AKKickstartUtils::getPath());
		if($dh !== false)
		{
			$basename = basename(__FILE__, '.php');
			while( false !== $file = @readdir($dh) )
			{
				if( strstr($file, $basename.'.ini') )
				{
					$engine->unlink($file);
				}
			}
		}

		// 6. Delete abiautomation.ini
		$engine->unlink('abiautomation.ini');

		break;

	default:
		$ajax = false;
		$automation = AKAutomation::getInstance();
		echoPage();
		break;
}

if($ajax)
{
	// JSON encode the message
	$json = json_encode($retArray);
	// Do I have to encrypt?
	$password = AKFactory::get('kickstart.security.password', null);
	if(!empty($password))
	{
		$json = AKEncryptionAES::AESEncryptCtr($json, $password, 128);
	}

	// Return the message
	echo "###$json###";
}
?>
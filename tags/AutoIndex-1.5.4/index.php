<?php

/***************************************************************************
                  AutoIndex PHP Script, by Justin Hagstrom
                             -------------------

   filename             : index.php
   version              : 1.5.4
   date                 : August 11, 2005

   copyright            : Copyright (C) 2002-2005 Justin Hagstrom
   license              : GNU General Public License (GPL)

   website & forum      : http://autoindex.sourceforge.net
   e-mail               : JustinHagstrom [at] yahoo [dot] com


   AutoIndex PHP Script is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   AutoIndex PHP Script is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

 ***************************************************************************/

//some basic compatibility for PHP 4.0.x
if (!isset($_GET)) { $_GET = &$HTTP_GET_VARS; }
if (!isset($_POST)) { $_POST = &$HTTP_POST_VARS; }
if (!isset($_SESSION)) { $_SESSION = &$HTTP_SESSION_VARS; }
if (!isset($_SERVER)) { $_SERVER = &$HTTP_SERVER_VARS; }
if (!isset($_COOKIE)) { $_COOKIE = &$HTTP_COOKIE_VARS; }
if (!isset($_FILES)) { $_FILES = &$HTTP_POST_FILES; }

/*    OPTIONAL SETTINGS    */
 
$stored_config = 'AutoIndex.conf.php';
$config_generator = 'config.php';

$date_format = 'Y-M-d'; //see http://php.net/date

/*  END OPTIONAL SETTINGS  */


function get_microtime()
{
	list($usec, $sec) = explode(' ', microtime());
	return ((float)$usec + (float)$sec);
}
$start_time = get_microtime();

session_name('AutoIndex');
session_start();

if (@get_magic_quotes_gpc())
//remove any slashes added by the "magic quotes" setting
{
	$_GET = array_map('stripslashes', $_GET);
	$_POST = array_map('stripslashes', $_POST);
}
@set_magic_quotes_runtime(0);

if (ini_get('zlib.output_compression') == '1')
//compensate for compressed output set in php.ini
{
	header('Content-Encoding: gzip');
}

define('VERSION', '1.5.4');

//now we need to include either the stored settings, or the config generator
if (@is_file($stored_config))
{
	if (!@include($stored_config))
	{
		die("<p>Error including file <em>$stored_config</em></p>");
	}
}
else if (@is_file($config_generator))
{
	define('CONFIG', true);
	if (!@include($config_generator))
	{
		die("<p>Error including file <em>$config_generator</em></p>");
	}
	die();
}
else
{
	die("<p>Error: Neither <em>$config_generator</em> nor <em>$stored_config</em> could be found.</p>");
}

$this_file = (($index == '') ? $_SERVER['PHP_SELF'] : $index);
$this_file .= ((strpos($this_file, '?') !== false) ? '&' : '?');
$referrer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'N/A');

//make sure all the variables are set correctly from the stored settings
$config_vars = array('base_dir', 'icon_path', 'stylesheet', 'use_login_system',
'allow_uploads', 'must_login_to_download', 'user_list', 'allow_file_overwrites',
'log_file', 'dont_log_these_ips', 'download_count', 'links_file', 'lang',
'sub_folder_access', 'index', 'hidden_files', 'show_only_these_files',
'force_download', 'bandwidth_limit', 'anti_leech', 'enable_searching',
'show_dir_size', 'folder_expansion', 'show_folder_count', 'banned_list',
'md5_show', 'header', 'footer', 'header_per_folder', 'footer_per_folder',
'description_file', 'thumbnail_height', 'path_to_language_files', 'days_new',
'select_language', 'show_type_column', 'show_size_column', 'show_date_column');
foreach ($config_vars as $this_var)
{
	if (!isset($$this_var))
	{
		die("<p>Error: AutoIndex is not configured properly.
		<br />The variable <strong>$this_var</strong> is not set.</p>
		<p>Delete <em>$stored_config</em> and then run <em>$config_generator</em>.</p>");
	}
}

//find the language the script should be displayed in
if ($select_language && isset($_GET['lang'])
	&& preg_match('/^[a-z]{2}(_[a-z]{2})?$/i', $_GET['lang'])
	&& @is_file($path_to_language_files.$_GET['lang'].'.php'))
{
	$_SESSION['lang'] = $_GET['lang'];
}
else if (!isset($_SESSION['lang']))
{
	$_SESSION['lang'] = $lang;
}
@include($path_to_language_files.$_SESSION['lang'].'.php');

if (!isset($words))
{
	die('<p>Error: You need to include a language.php file that has the variable $words.
	<br />Check the $lang and $path_to_language_files variables.</p>');
}

$global_user_list = ($use_login_system ? @file($user_list) : array());
if ($global_user_list === false)
{
	die("<p>Could not open file <strong>$user_list</strong></p>");
}

function translate_uri($uri)
//rawurlencodes $uri, but not any slashes
{
	$uri = rawurlencode(str_replace('\\', '/', $uri));
	return str_replace(rawurlencode('/'), '/', $uri);
}

function get_basename($fn)
//returns everything after the slash, or the original string if there is no slash
{
	return basename(str_replace('\\', '/', $fn));
}

function match_in_array($string, &$array)
//returns true if $string matches anything in the array
{
	$string = get_basename($string);
	static $replace = array(
		'\*' => '[^\/]*',
		'\+' => '[^\/]+',
		'\?' => '[^\/]?');
	foreach ($array as $m)
	{
		if (preg_match('/^'.strtr(preg_quote(get_basename($m), '/'), $replace).'$/i', $string))
		{
			return true;
		}
	}
	return false;
}

function check_login($user, $pass)
{
	global $global_user_list;
	foreach ($global_user_list as $look)
	{
		if ((strcasecmp(substr(rtrim($look), 33), $user) === 0)
			&& (strcasecmp(substr(rtrim($look), 0, 32), $pass) === 0))
		{
			return true;
		}
	}
	return false;
}

function logged_in()
{
	return (isset($_SESSION['user'], $_SESSION['pass']) &&
		check_login($_SESSION['user'], $_SESSION['pass']));
}

function is_user_admin($user)
{
	global $global_user_list;
	foreach ($global_user_list as $look)
	{
		if (strcasecmp($user, substr(rtrim($look), 33)) === 0)
		{
			return (substr($look, 32, 1) === '1');
		}
	}
	return false;
}

function is_admin()
{
	return is_user_admin($_SESSION['user']);
}

function is_hidden($fn, $is_file = true)
//looks at $hidden_files and $show_only_these_files to see if $fn is hidden
{
	if ($fn == '')
	{
		return true;
	}
	global $use_login_system;
	if ($use_login_system && logged_in() && is_admin())
	//allow admins to view hidden files
	{
		return false;
	}
	global $hidden_files, $show_only_these_files;
	if ($is_file && count($show_only_these_files))
	{
		return (!match_in_array($fn, $show_only_these_files));
	}
	if (!count($hidden_files))
	{
		return false;
	}
	return match_in_array($fn, $hidden_files);
}

function eval_dir($d)
//check $d for "bad" things, and deal with ".."
{
	$d = str_replace('\\', '/', $d);
	if ($d == '' || $d == '/')
	{
		return '';
	}
	$dirs = explode('/', $d);
	for ($i=0; $i<count($dirs); $i++)
	{
		if ($dirs[$i] == '.' || is_hidden($dirs[$i], false))
		{
			array_splice($dirs, $i, 1);
			$i--;
		}
		else if (preg_match('/^\.\./', $dirs[$i])) //if it starts with two dots
		{
			array_splice($dirs, $i-1, 2);
			$i = -1;
		}
	}
	$new_dir = implode('/', $dirs);
	if ($new_dir == '' || $new_dir == '/')
	{
		return '';
	}
	if ($d{0} == '/' && $new_dir{0} != '/')
	{
		$new_dir = '/'.$new_dir;
	}
	if (preg_match('#/$#', $d) && !preg_match('#/$#', $new_dir))
	{
		$new_dir .= '/';
	}
	else if (is_hidden(get_basename($d)))
	{
		return '';
	}
	return $new_dir;
}

//get the user defined variables that are in the URL
$subdir = (isset($_GET['dir']) ? eval_dir(rawurldecode($_GET['dir'])) : '');
$file_dl = (isset($_GET['file']) ? rawurldecode($_GET['file']) : '');
$search = (isset($_GET['search']) ? $_GET['search'] : '');
$search_mode = (isset($_GET['searchMode']) ? $_GET['searchMode'] : '');
while (preg_match('#\\\\|/$#', $file_dl))
{
	$file_dl = substr($file_dl, 0, -1);
}
$file_dl = eval_dir($file_dl);

if (!@is_dir($base_dir))
{
	die('<p>Error: <em>'.htmlentities($base_dir)
	.'</em> is not a valid directory.<br />Check the $base_dir variable.</p>');
}

if (!$sub_folder_access || $subdir == '/')
{
	$subdir = '';
}
else if (preg_match('#[^/\\\\]$#', $subdir))
{
	$subdir .= '/'; //add a slash to the end if there isn't one
}

$dir = $base_dir.$subdir;

//this will be displayed before any HTML output
$html_heading = '';

if ($index == '')
{
	$html_heading .= '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="'.$_SESSION['lang'].'">
<head>';
}
if ($stylesheet != '')
{
	$html_heading .= "\n<link rel=\"stylesheet\" href=\"$stylesheet\" type=\"text/css\" title=\"AutoIndex Default\" />\n";
}
if ($index == '')
{
	$html_heading .= "\n<title>".$words['index of'].' '.htmlentities($dir)
		."</title>\n\n</head><body class='autoindex_body'>\n\n";
}

function show_header()
{
	global $header, $header_per_folder, $dir;
	if ($header != '')
	{
		if ($header_per_folder)
		{
			$header = $dir.$header;
		}
		if (@is_readable($header))
		{
			include($header);
		}
	}
}

function show_footer()
{
	global $footer, $footer_per_folder, $dir;
	if ($footer != '')
	{
		if ($footer_per_folder)
		{
			$footer = $dir.$footer;
		}
		if (@is_readable($footer))
		{
			include($footer);
		}
	}
}

function show_login_box()
{
	global $this_file, $subdir, $icon_path;
	$sd = translate_uri($subdir);
	echo '<p /><table border="0" cellpadding="8" cellspacing="0">
	<tr class="paragraph"><td class="default_td"><img src="', $icon_path,
	'/login.png" width="12" height="14" alt="Login" /> Login:',
	"\n<form method='post' action='{$this_file}dir=$sd'>
	<table><tr class=\"paragraph\"><td>Username:</td>
	<td><input type='text' name='user' />
	</td></tr><tr class=\"paragraph\"><td>Password:</td>
	<td><input type='password' name='pass' /></td></tr></table>
	<p><input class='button' type='submit' value='Login' /></p>
	</form></td></tr></table>";
}

function show_search_box()
{
	global $index, $search, $words, $search_mode, $this_file, $subdir, $icon_path;
	echo '<p /><table border="0" cellpadding="8" cellspacing="0">
	<tr class="paragraph"><td class="default_td"><img src="', $icon_path,
	'/search.png" width="16" height="16" alt="', $words['search'], '" /> ',
	$words['search'], ":<br /><form method='get' action='$this_file'>
	<p><input type='text' name='search' value='", htmlentities($search), "' />\n";
	if ($index != '' && strpos($index, '?') !== false)
	{
		$id_temp = explode('=', $index, 2);
		$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
		echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
	}
	echo "\n<input type='hidden' name='dir' value='", translate_uri($subdir),
		"' /><br /><select name='searchMode'>\n";
	$search_modes = array($words['files'] => 'f', $words['folders'] => 'd', $words['both'] => 'fd');
	foreach ($search_modes as $key => $element)
	{
		$sel = (($search_mode == $element) ? ' selected="selected"' : '');
		echo "\t<option$sel value='$element'>$key</option>\n";
	}
	echo "</select><input type='submit' value='", $words['search'],
		'\' class="button" /></p></form></td></tr></table>';
}

function is_username($user)
{
	global $html_heading, $global_user_list;
	foreach ($global_user_list as $look)
	{
		if (strcasecmp($user, substr(rtrim($look), 33)) === 0)
		{
			return true;
		}
	}
	return false;
}

function num_admins()
//returns the number of accounts with admin rights
{
	global $html_heading, $global_user_list;
	$num = 0;
	foreach ($global_user_list as $look)
	{
		if (substr($look, 32, 1) === '1')
		{
			$num++;
		}
	}
	return $num;
}

function get_filesize($size)
//give a size in bytes, and this will return the appropriate measurement format
{
	$size = max(0, $size);
	static $u = array('&nbsp;B', 'KB', 'MB', 'GB');
	for ($i=0; $size >= 1024 && $i < 4; $i++)
	{
		$size /= 1024;
	}
	return number_format($size, 1).' '.$u[$i];
}

function ext($fn)
//return the lowercase file extension of $fn, not including the leading dot
{
	$fn = get_basename($fn);
	return (strpos($fn, '.') ? strtolower(substr(strrchr($fn, '.'), 1)) : '');
}

function get_all_files($path)
//returns an array of every file in $path, including folders (except ./ and ../)
{
	$list = array();
	if (($hndl = @opendir($path)) === false)
	{
		return $list;
	}
	while (($file=readdir($hndl)) !== false)
	{
		if ($file != '.' && $file != '..')
		{
			$list[] = $file;
		}
	}
	closedir($hndl);
	return $list;
}

function get_file_list($path)
//returns a sorted array of filenames. Filters out "bad" files
{
	global $sub_folder_access, $links_file;
	$f = $d = array();
	foreach (get_all_files($path) as $name)
	{
		if ($sub_folder_access && @is_dir($path.$name) && !is_hidden($name, false))
		{
			$d[] = $name;
		}
		else if (@is_file($path.$name) && !is_hidden($name, true))
		{
			$f[] = $name;
		}
	}
	if ($links_file != '' && ($links = @file($path.$links_file)))
	{
		foreach ($links as $name)
		{
			$p = strpos($name, '|');
			$f[] = (($p === false) ? rtrim($name).'|' : substr(rtrim($name), 0, $p).'|');
		}
	}
	natcasesort($d);
	natcasesort($f);
	return array_merge($d, $f);
}

function dir_size($dir)
//returns the total size of a directory (recursive) in bytes
{
	$totalsize = 0;
	foreach (get_file_list($dir) as $name)
	{
		$totalsize += (@is_dir($dir.$name) ? dir_size("$dir$name/") : (int)@filesize($dir.$name));
	}
	return $totalsize;
}

function match_filename($filename, $string)
{
	if (preg_match_all('/(?<=")[^"]+(?=")|[^ "]+/', $string, $matches))
	{
		foreach ($matches[0] as $w)
		{
			if (preg_match('#[^/\.]+#', $w) && stristr($filename, $w))
			{
				return true;
			}
		}
	}
	return false;
}

function search_dir($sdir, $string)
//returns files/folders (recursive) in $sdir that contain $string
{
	global $search_mode;
	//search_mode: d=folders, f=files, fd=both

	$found = array();
	$list = get_file_list($sdir);
	$d = count($list);
	for ($i=0; $i<$d; $i++)
	{
		$full_name = $sdir.$list[$i];
		if (stristr($search_mode, 'f') && (@is_file($full_name) || preg_match('/\|$/', $list[$i])) && match_filename($list[$i], $string))
		{
			$found[] = $full_name;
		}
		else if (@is_dir($full_name))
		{
			if (stristr($search_mode, 'd') && match_filename($list[$i], $string))
			{
				$found[] = $full_name;
			}
			$found = array_merge($found, search_dir($full_name.'/', $string));
		}
	}
	return $found;
}

function add_num_to_array($num, &$array)
{
	isset($array[$num]) ? $array[$num]++ : $array[$num] = 1;
}

function mkdir_recursive($path)
{
	if (@is_dir($path))
	{
		return true;
	}
	if (!mkdir_recursive(dirname($path)))
	{
		return false;
	}
	return @mkdir($path, 0755);
}

function rmdir_recursive($path)
{
	if (!preg_match('#/$#', $path))
	{
		$path .= '/';
	}
	foreach (get_all_files($path) as $file)
	{
		if ($file == '' || $file == '.' || $file == '..')
		{
			continue;
		}
		if (@is_dir("$path$file/"))
		{
			rmdir_recursive("$path$file/");
		}
		else
		{
			@unlink($path . $file);
		}
	}
	return @rmdir($path);
}

function num_files($dir)
//returns the number of files in $dir (recursive)
{
	$count = 0;
	if (!preg_match('#/$#', $dir))
	{
		$dir .= '/';
	}
	$list = get_file_list($dir);
	$d = count($list);
	for ($i=0; $i<$d; $i++)
	{
		$count += (@is_dir($dir.$list[$i]) ? num_files($dir.$list[$i]) : 1);
	}
	return $count;
}

function redirect($site)
{
	header("Location: $site");
	die('<p>Redirection header could not be sent.<br />'
		."Continue here: <a href=\"$site\">$site</a></p>");
}

function find_mime_type($ext)
{
	static $mime_types = array(
		'application/andrew-inset' => array('ez'),
		'application/mac-binhex40' => array('hqx'),
		'application/mac-compactpro' => array('cpt'),
		'application/mathml+xml' => array('mathml'),
		'application/msword' => array('doc'),
		'application/octet-stream' => array('bin', 'dms', 'lha',
			'lzh', 'exe', 'class', 'so', 'dll', 'dmg'),
		'application/oda' => array('oda'),
		'application/ogg' => array('ogg'),
		'application/pdf' => array('pdf'),
		'application/postscript' => array('ai', 'eps', 'ps'),
		'application/rdf+xml' => array('rdf'),
		'application/smil' => array('smi', 'smil'),
		'application/srgs' => array('gram'),
		'application/srgs+xml' => array('grxml'),
		'application/vnd.mif' => array('mif'),
		'application/vnd.mozilla.xul+xml' => array('xul'),
		'application/vnd.ms-excel' => array('xls'),
		'application/vnd.ms-powerpoint' => array('ppt'),
		'application/vnd.wap.wbxml' => array('wbxml'),
		'application/vnd.wap.wmlc' => array('wmlc'),
		'application/vnd.wap.wmlscriptc' => array('wmlsc'),
		'application/voicexml+xml' => array('vxml'),
		'application/x-bcpio' => array('bcpio'),
		'application/x-cdlink' => array('vcd'),
		'application/x-chess-pgn' => array('pgn'),
		'application/x-cpio' => array('cpio'),
		'application/x-csh' => array('csh'),
		'application/x-director' => array('dcr', 'dir', 'dxr'),
		'application/x-dvi' => array('dvi'),
		'application/x-futuresplash' => array('spl'),
		'application/x-gtar' => array('gtar'),
		'application/x-hdf' => array('hdf'),
		'application/x-javascript' => array('js'),
		'application/x-koan' => array('skp', 'skd', 'skt', 'skm'),
		'application/x-latex' => array('latex'),
		'application/x-netcdf' => array('nc', 'cdf'),
		'application/x-sh' => array('sh'),
		'application/x-shar' => array('shar'),
		'application/x-shockwave-flash' => array('swf'),
		'application/x-stuffit' => array('sit'),
		'application/x-sv4cpio' => array('sv4cpio'),
		'application/x-sv4crc' => array('sv4crc'),
		'application/x-tar' => array('tar'),
		'application/x-tcl' => array('tcl'),
		'application/x-tex' => array('tex'),
		'application/x-texinfo' => array('texinfo', 'texi'),
		'application/x-troff' => array('t', 'tr', 'roff'),
		'application/x-troff-man' => array('man'),
		'application/x-troff-me' => array('me'),
		'application/x-troff-ms' => array('ms'),
		'application/x-ustar' => array('ustar'),
		'application/x-wais-source' => array('src'),
		'application/xhtml+xml' => array('xhtml', 'xht'),
		'application/xslt+xml' => array('xslt'),
		'application/xml' => array('xml', 'xsl'),
		'application/xml-dtd' => array('dtd'),
		'application/zip' => array('zip'),
		'audio/basic' => array('au', 'snd'),
		'audio/midi' => array('mid', 'midi', 'kar'),
		'audio/mpeg' => array('mpga', 'mp2', 'mp3'),
		'audio/x-aiff' => array('aif', 'aiff', 'aifc'),
		'audio/x-mpegurl' => array('m3u'),
		'audio/x-pn-realaudio' => array('ram', 'ra'),
		'application/vnd.rn-realmedia' => array('rm'),
		'audio/x-wav' => array('wav'),
		'chemical/x-pdb' => array('pdb'),
		'chemical/x-xyz' => array('xyz'),
		'image/bmp' => array('bmp'),
		'image/cgm' => array('cgm'),
		'image/gif' => array('gif'),
		'image/ief' => array('ief'),
		'image/jpeg' => array('jpeg', 'jpg', 'jpe'),
		'image/png' => array('png'),
		'image/svg+xml' => array('svg'),
		'image/tiff' => array('tiff', 'tif'),
		'image/vnd.djvu' => array('djvu', 'djv'),
		'image/vnd.wap.wbmp' => array('wbmp'),
		'image/x-cmu-raster' => array('ras'),
		'image/x-icon' => array('ico'),
		'image/x-portable-anymap' => array('pnm'),
		'image/x-portable-bitmap' => array('pbm'),
		'image/x-portable-graymap' => array('pgm'),
		'image/x-portable-pixmap' => array('ppm'),
		'image/x-rgb' => array('rgb'),
		'image/x-xbitmap' => array('xbm'),
		'image/x-xpixmap' => array('xpm'),
		'image/x-xwindowdump' => array('xwd'),
		'model/iges' => array('igs', 'iges'),
		'model/mesh' => array('msh', 'mesh', 'silo'),
		'model/vrml' => array('wrl', 'vrml'),
		'text/calendar' => array('ics', 'ifb'),
		'text/css' => array('css'),
		'text/html' => array('html', 'htm'),
		'text/plain' => array('asc', 'txt'),
		'text/richtext' => array('rtx'),
		'text/rtf' => array('rtf'),
		'text/sgml' => array('sgml', 'sgm'),
		'text/tab-separated-values' => array('tsv'),
		'text/vnd.wap.wml' => array('wml'),
		'text/vnd.wap.wmlscript' => array('wmls'),
		'text/x-setext' => array('etx'),
		'video/mpeg' => array('mpeg', 'mpg', 'mpe'),
		'video/quicktime' => array('qt', 'mov'),
		'video/vnd.mpegurl' => array('mxu', 'm4u'),
		'video/x-msvideo' => array('avi'),
		'video/x-sgi-movie' => array('movie'),
		'x-conference/x-cooltalk' => array('ice')
	);
	foreach ($mime_types as $mime_type => $exts)
	{
		if (in_array($ext, $exts))
		{
			return $mime_type;
		}
	}
	return 'text/plain';
}

function icon($ext)
//find the appropriate icon depending on the extension (returns a link to the image file)
{
	global $icon_path;
	if ($icon_path == '')
	{
		return '';
	}
	if ($ext == '')
	{
		$icon = 'generic';
	}
	else
	{
		$icon = 'unknown';
		static $icon_types = array(
		'binary' => array('bat', 'bin', 'com', 'dmg', 'dms', 'exe', 'msi',
			'msp', 'pif', 'pyd', 'scr', 'so'),
		'binhex' => array('hqx'),
		'cd' => array('bwi', 'bws', 'bwt', 'ccd', 'cdi', 'cue', 'img',
			'iso', 'mdf', 'mds', 'nrg', 'nri', 'sub', 'vcd'),
		'comp' => array('cfg', 'conf', 'inf', 'ini', 'log', 'nfo', 'reg'),
		'compressed' => array('7z', 'a', 'ace', 'ain', 'alz', 'amg', 'arc',
			'ari', 'arj', 'bh', 'bz', 'bz2', 'cab', 'deb', 'dz', 'gz',
			'io', 'ish', 'lha', 'lzh', 'lzs', 'lzw', 'lzx', 'msx', 'pak',
			'rar', 'rpm', 'sar', 'sea', 'sit', 'taz', 'tbz', 'tbz2',
			'tgz', 'tz', 'tzb', 'uc2', 'xxe', 'yz', 'z', 'zip', 'zoo'),
		'dll' => array('386', 'db', 'dll', 'ocx', 'sdb', 'vxd'),
		'doc' => array('abw', 'ans', 'chm', 'cwk', 'dif', 'doc', 'dot',
			'mcw', 'msw', 'pdb', 'psw', 'rtf', 'rtx', 'sdw', 'stw', 'sxw',
			'vor', 'wk4', 'wkb', 'wpd', 'wps', 'wpw', 'wri', 'wsd'),
		'image' => array('adc', 'art', 'bmp', 'cgm', 'dib', 'gif', 'ico',
			'ief', 'jfif', 'jif', 'jp2', 'jpc', 'jpe', 'jpeg', 'jpg', 'jpx',
			'mng', 'pcx', 'png', 'psd', 'psp', 'swc', 'sxd', 'tga',
			'tif', 'tiff', 'wmf', 'wpg', 'xcf', 'xif', 'yuv'),
		'java' => array('class', 'jar', 'jav', 'java', 'jtk'),
		'js' => array('ebs', 'js', 'jse', 'vbe', 'vbs', 'wsc', 'wsf',
			'wsh'),
		'key' => array('aex', 'asc', 'gpg', 'key', 'pgp', 'ppk'),
		'mov' => array('amc', 'dv', 'm4v', 'mac', 'mov', 'mp4v', 'mpg4',
			'pct', 'pic', 'pict', 'pnt', 'pntg', 'qpx', 'qt', 'qti',
			'qtif', 'qtl', 'qtp', 'qts', 'qtx'),
		'movie' => array('asf', 'asx', 'avi', 'div', 'divx', 'dvi', 'm1v',
			'm2v', 'mkv', 'movie', 'mp2v', 'mpa', 'mpe', 'mpeg', 'mpg',
			'mps', 'mpv', 'mpv2', 'ogm', 'ram', 'rmvb', 'rnx', 'rp', 'rv',
			'vivo', 'vob', 'wmv', 'xvid'),
		'pdf' => array('edn', 'fdf', 'pdf', 'pdp', 'pdx'),
		'php' => array('inc', 'php', 'php3', 'php4', 'php5', 'phps',
			'phtml'),
		'ppt' => array('emf', 'pot', 'ppa', 'pps', 'ppt', 'sda', 'sdd',
			'shw', 'sti', 'sxi'),
		'ps' => array('ai', 'eps', 'ps'),
		'sound' => array('aac', 'ac3', 'aif', 'aifc', 'aiff', 'ape', 'apl',
			'au', 'ay', 'bonk', 'cda', 'cdda', 'cpc', 'fla', 'flac',
			'gbs', 'gym', 'hes', 'iff', 'it', 'itz', 'kar', 'kss', 'la',
			'lpac', 'lqt', 'm4a', 'm4p', 'mdz', 'mid', 'midi', 'mka',
			'mo3', 'mod', 'mp+', 'mp1', 'mp2', 'mp3', 'mp4', 'mpc',
			'mpga', 'mpm', 'mpp', 'nsf', 'oda', 'ofr', 'ogg', 'pac', 'pce',
			'pcm', 'psf', 'psf2', 'ra', 'rm', 'rmi', 'rmjb', 'rmm', 'sb',
			'shn', 'sid', 'snd', 'spc', 'spx', 'svx', 'tfm', 'tfmx',
			'voc', 'vox', 'vqf', 'wav', 'wave', 'wma', 'wv', 'wvx', 'xa',
			'xm', 'xmz'),
		'tar' => array('gtar', 'tar'),
		'text' => array('c', 'cc', 'cp', 'cpp', 'cxx', 'diff', 'h', 'hpp',
			'hxx', 'm3u', 'md5', 'patch', 'pls', 'py', 'sfv', 'sh',
			'txt'),
		'uu' => array('uu', 'uud', 'uue'),
		'web' => array('asa', 'asp', 'aspx', 'cfm', 'cgi', 'css', 'dhtml',
			'dtd', 'grxml', 'htc', 'htm', 'html', 'htt', 'htx', 'jsp', 'lnk',
			'mathml', 'mht', 'mhtml', 'perl', 'pl', 'plg', 'rss', 'shtm',
			'shtml', 'stm', 'swf', 'tpl', 'wbxml', 'xht', 'xhtml', 'xml',
			'xsl', 'xslt', 'xul'),
		'xls' => array('csv', 'dbf', 'prn', 'pxl', 'sdc', 'slk', 'stc', 'sxc',
			'xla', 'xlb', 'xlc', 'xld', 'xlr', 'xls', 'xlt', 'xlw'));
		foreach ($icon_types as $png_name => $exts)
		{
			if (in_array($ext, $exts))
			{
				$icon = $png_name;
				break;
			}
		}
	}
	return "<img alt=\"[$ext]\" height=\"16\" width=\"16\" src=\"$icon_path/$icon.png\" /> ";
}

function display_thumbnail($file, $thumbnail_height)
{
	global $html_heading;
	if (!@is_file($file))
	{
		header('HTTP/1.0 404 Not Found');
		die("$html_heading<p>File not found: <em>".htmlentities($file).'</em></p>');
	}
	switch (ext($file))
	{
		case 'gif':
			$src = @imagecreatefromgif($file);
			break;
		case 'jpeg':
		case 'jpg':
		case 'jpe':
			$src = @imagecreatefromjpeg($file);
			break;
		case 'png':
			$src = @imagecreatefrompng($file);
			break;
		default:
			die("$html_heading<p>Unsupported file extension.</p>");
	}
	if ($src === false)
	{
		die("$html_heading<p>Unsupported image type.</p>");
	}
	
	header('Content-Type: image/jpeg');
	header('Cache-Control: public, max-age=3600, must-revalidate');
	header('Expires: '.gmdate('D, d M Y H:i:s', time()+3600).' GMT');
	$src_height = imagesy($src);
	if ($src_height <= $thumbnail_height)
	{
		imagejpeg($src, '', 95);
	}
	else
	{
		$src_width = imagesx($src);
		$thumb_width = $thumbnail_height * ($src_width / $src_height);
		$thumb = imagecreatetruecolor($thumb_width, $thumbnail_height);
		imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumb_width,
			$thumbnail_height, $src_width, $src_height);
		imagejpeg($thumb);
		imagedestroy($thumb);
	}
	imagedestroy($src);
	die();
}

function edit_description($fn, &$desc)
//edits a file's description
{
	global $description_file, $html_heading;
	if ($description_file == '')
	{
		return;
	}
	$wrote = false;
	$l = @file($description_file) or $l = array();
	$h = @fopen($description_file, 'wb') or die("$html_heading<p>Cannot open description file for writing.</p>");
	$count_num = count($l);
	for ($i=0; $i<$count_num; $i++)
	{
		$items = explode('|', rtrim($l[$i]), 2);
		if (count($items) === 2 && $fn == $items[0])
		{
			fwrite($h, "$fn|$desc\n");
			$wrote = true;
		}
		else
		{
			fwrite($h, $l[$i]);
		}
	}
	if (!$wrote && $desc != '')
	{
		fwrite($h, "$fn|$desc\n");
	}
	fclose($h);
}

function add_to_file($item, $outfile)
{
	global $html_heading;
	$counted = false;
	if ($l = @file($outfile))
	{
		$count_num = count($l);
		for ($i=0; $i<$count_num; $i++)
		{
			$thisc = rtrim($l[$i]);
			if ($item == substr($thisc, 0, strpos($thisc, '|')))
			{
				$counted = true;
				break;
			}
		}
	}
	if ($counted)
	{
		$w = @fopen($outfile, 'wb') or die("$html_heading<p>Could not open <em>$outfile</em> file for writing.</p>");
		for ($i=0; $i<$count_num; $i++)
		{
			$items = explode('|', rtrim($l[$i]), 2);
			if (count($items) === 2 && $items[0] == $item)
			{
				$nc = $items[1] + 1;
				fwrite($w, "$item|$nc\n");
			}
			else
			{
				fwrite($w, $l[$i]);
			}
		}
	}
	else
	{
		$w = @fopen($outfile, 'ab') or die("$html_heading<p>Could not open <em>$outfile</em> file for writing.</p>");
		fwrite($w, "$item|1\n");
	}
	fclose($w);
}

function get_stored_info($item, $filename)
{
	if ($contents = @file($filename))
	{
		$count_num = count($contents);
		for ($i=0; $i<$count_num; $i++)
		{
			$items = explode('|', rtrim($contents[$i]), 2);
			if (count($items) === 2 && $item == $items[0])
			{
				return $items[1];
			}
		}
	}
	return '';
}

function table_heading($title, $sortMode, $tooltip)
{
	global $this_file, $subdir;
	echo "\n<th class='default_th'><a class='black_link' title=\"$tooltip\" href=\"",
	$this_file, 'dir=', translate_uri($subdir), '&amp;sort=',
	(($_SESSION['sort'] == 'a' && $_SESSION['sortMode'] == $sortMode) ? 'd' : 'a'),
	'&amp;sortMode=', $sortMode, '">', $title, '</a></th>';
}

//find and store the user's IP address and hostname:
$ip = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'N/A');
if (isset($_SESSION['host']))
{
	$host = $_SESSION['host'];
}
else
{
	$_SESSION['host'] = $host = @gethostbyaddr($ip);
}

if ($banned_list != '' && ($b_list = @file($banned_list)))
//make sure the IP or hostname is not banned
{
	for ($i=0; $i<count($b_list); $i++)
	{
		$b_list[$i] = rtrim($b_list[$i]);
	}
	if (match_in_array($ip, $b_list) || match_in_array($host, $b_list))
	{
		echo $html_heading;
		show_header();
		echo '<p>Sorry, the administrator has blocked your IP address or hostname.</p>';
		show_footer();
		die();
	}
}

function ok_to_log()
//returns true if the ip or hostname is not in $dont_log_these_ips
{
	global $ip, $host, $dont_log_these_ips;
	return (!(match_in_array($ip, $dont_log_these_ips) ||
		($host != 'N/A' && match_in_array($host, $dont_log_these_ips))));
}

if ($use_login_system && isset($_POST['user'], $_POST['pass'])
	&& $_POST['user'] != '' && $_POST['pass'] != '')
//check login
{
	if (check_login($_POST['user'], md5($_POST['pass'])))
	{
		if ($log_file != '' && ok_to_log())
		{
			if ($write = @fopen($log_file, 'ab'))
			{
				fwrite($write, date($date_format)."\t".date('H:i:s')
					."\t$ip\t$host\t$referrer\t$dir\tSuccessful Login (username: "
					.$_POST['user'].")\n");
				fclose($write);
			}
		}
		$_SESSION['user'] = $_POST['user'];
		$_SESSION['pass'] = md5($_POST['pass']);
		unset($_POST['pass'], $_POST['user']);
		redirect($this_file.'dir='.translate_uri($subdir));
	}
	else
	{
		echo '<h3>Invalid Login.</h3>';
		if ($log_file != '' && ok_to_log())
		{
			if ($write = @fopen($log_file, 'ab'))
			{
				fwrite($write, date($date_format)."\t".date('H:i:s')
					."\t$ip\t$host\t$referrer\t$dir\tInvalid Login (username: "
					.$_POST['user'].")\n");
				fclose($write);
			}
		}
		sleep(1); //"freeze" the script for a second to prevent brute force attacks
	}
}

if ($use_login_system && $must_login_to_download && !logged_in())
//must login to download
{
	echo $html_heading;
	show_header();
	echo '<p>You must login to download and view files.</p>';
	show_login_box();
	show_footer();
	die();
}

if ($md5_show && isset($_GET['md5']))
{
	$file = $dir.eval_dir(rawurldecode($_GET['md5']));
	if (!@is_file($file))
	{
		header('HTTP/1.0 404 Not Found');
		die($html_heading.'<p>Error: file does not exist.</p>');
	}
	$size = (int)@filesize($file);
	if ($size <= 0 || $size/1048576 > $md5_show)
	{
		die($html_heading.'<p><strong>Error</strong>: empty file, or file too big to find the md5sum of (according to the $md5_show variable).</p>');
	}
	die(md5_file($file));
}

if ($thumbnail_height > 0 && isset($_GET['thumbnail']) && $_GET['thumbnail'] != '')
{
	$file = $dir.eval_dir(rawurldecode($_GET['thumbnail']));
	display_thumbnail($file, $thumbnail_height);
}

if (isset($_GET['sort']))
{
	$_SESSION['sort'] = $_GET['sort'];
}
else if (!isset($_SESSION['sort']))
{
	//'a' is ascending, 'd' is descending
	$_SESSION['sort'] = 'a';
}

if (isset($_GET['sortMode']))
{
	$_SESSION['sortMode'] = $_GET['sortMode'];
}
else if (!isset($_SESSION['sortMode']))
{
	/*
	 * 'f' is filename
	 * 't' is filetype
	 * 'h' is downloads (hits)
	 * 's' is size
	 * 'm' is date (modified)
	 * 'd' is description
	 */
	$_SESSION['sortMode'] = 'f';
}

//size of the "chunks" that are read at a time from the file (when $force_download is on)
$speed = ($bandwidth_limit ? $bandwidth_limit : 8);

if ($folder_expansion)
{
	if (!isset($_SESSION['expanded']))
	{
		$_SESSION['expanded'] = array();
	}
	if (isset($_GET['expand']) && $_GET['expand'] != '')
	{
		$temp = $dir.eval_dir(rawurldecode($_GET['expand']));
		if (@is_dir($temp) && !in_array($temp, $_SESSION['expanded']))
		{
			$_SESSION['expanded'][] = $temp;
		}
	}
	if (isset($_GET['collapse']) && $_GET['collapse'] != '')
	{
		$temp = $dir.eval_dir(rawurldecode($_GET['collapse']));
		if (in_array($temp, $_SESSION['expanded']))
		{
			array_splice($_SESSION['expanded'], array_search($temp, $_SESSION['expanded']), 1);
		}
	}
}

if ($allow_uploads && (!$use_login_system || logged_in()))
//upload a file
{
	if ($count_files = count($_FILES))
	{
		echo $html_heading;
		show_header();
		$uploaded_files = $errors = '';
		for ($i=0; $i<$count_files; $i++)
		{
			$filename = get_basename($_FILES[$i]['name']);
			if ($filename == '')
			{
				continue;
			}
			if (is_hidden($filename))
			{
				$errors .= "<li>$filename [filename is listed as a hidden file]</li>";
				continue;
			}
			$filepath = $base_dir.eval_dir(rawurldecode($_POST['dir']));
			$fullpathname = realpath($filepath).'/'.$filename;
			if (!$allow_file_overwrites && @file_exists($fullpathname))
			{
				$errors .= "<li>$filename [file already exists]</li>";
			}
			else if (@move_uploaded_file($_FILES[$i]['tmp_name'], $fullpathname))
			{
				@chmod($fullpathname, 0644);
				$uploaded_files .= "<li>$filename</li>";
				if ($log_file != '' && ok_to_log() && ($write = @fopen($log_file, 'ab')))
				{
					fwrite($write, date($date_format)."\t".date('H:i:s')
					. "\t$ip\t$host\t$referrer\t$dir\tFile uploaded: $filepath$filename\n");
					fclose($write);
				}
			}
			else
			{
				$errors .= "<li>$filename</li>";
			}
		}
		if ($errors == '')
		{
			$errors = '<br />[None]';
		}
		if ($uploaded_files == '')
		{
			$uploaded_files = '<br />[None]';
		}
		echo "<p><strong>Uploaded files</strong>: $uploaded_files</p><p><strong>Failed files</strong>: $errors</p>",
			'<p><a class="default_a" href="', $this_file, 'dir=',
			$_POST['dir'], '">Continue.</a></p>';
		show_footer();
		die();
	}
	else if (isset($_POST['numUpload']))
	{
		echo $html_heading;
		show_header();
		echo "<table border='0' cellpadding='8' cellspacing='0'><tr class='paragraph'><td class='default_td'>
		<form enctype='multipart/form-data' action='$this_file' method='post'>
		<input type='hidden' name='dir' value='", $_POST['dir'], "' />\n";
		$num = (int)$_POST['numUpload'];
		for ($i=0; $i<$num; $i++)
		{
			$n = $i + 1;
			echo "\t\t{$words['file']} $n : <input name='$i' type='file' /><br />\n";
		}
		echo '<p><input class="button" type="submit" value="Upload Files" />
		</p></form></td></tr></table>';
		show_footer();
		die();
	}
}

if ($use_login_system && logged_in() && is_admin())
{
	$con = '<p><a class="default_a" href="'.$this_file.'dir='
		.translate_uri($subdir).'">Continue.</a></p>';

	if (isset($_GET['getcreate']))
	{
		echo $html_heading;
		show_header();
		echo "<table border='0' cellpadding='8' cellspacing='0'><tr class='paragraph'><td class='default_td'>
		Enter the name of the folder you would like to create:
		<form method='get' action='$this_file'>
		<input type='hidden' name='dir' value='", translate_uri($subdir), "' />";
		if ($index != '' && strpos($index, '?') !== false)
		{
			$id_temp = explode('=', $index, 2);
			$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
			echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
		}
		echo '<p><input type="text" name="create" /></p>
		<p><input class="button" type="submit" value="Create" /></p>
		</form></td></tr></table>';
		show_footer();
		die();
	}
	else if (isset($_GET['create']) && $_GET['create'] != '')
	{
		$p = $dir.eval_dir($_GET['create']);
		$msg = (@file_exists($p) ? 'Folder already exists: ' : (mkdir_recursive($p) ? 'Folder successfully created: ' : 'Could not create folder: '));
		echo $html_heading;
		show_header();
		echo $msg, htmlentities($p), $con;
		show_footer();
		die();
	}
	else if ($description_file != '' && isset($_GET['descFile']) && $_GET['descFile'] != '')
	{
		if (isset($_GET['desc']))
		{
			$desc = trim(rawurldecode($_GET['desc']));
			$descFile = trim(rawurldecode($_GET['descFile']));
			edit_description($dir.$descFile, $desc);
		}
		else
		{
			$filen = rawurldecode($_GET['descFile']);
			$filen_display = htmlentities($filen);
			echo $html_heading;
			show_header();
			echo "<table border='0' cellpadding='8' cellspacing='0'>
			<tr class='paragraph'><td class='default_td'>
			Enter the new description for the file <em>$filen_display</em>:
			<form method='get' action='$this_file'>
			<input type='hidden' name='dir' value='", translate_uri($subdir), "' />
			<input type='hidden' name='descFile' value='", translate_uri($filen), '\' />';
			if ($index != '' && strpos($index, '?') !== false)
			{
				$id_temp = explode('=', $index, 2);
				$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
				echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
			}
			echo '<p><input type="text" name="desc" size="50" value="',
			htmlentities(get_stored_info($dir.$filen, $description_file)), '" /></p>
			<p><input class="button" type="submit" value="Change" /></p>
			</form></td></tr></table>';
			show_footer();
			die();
		}
	}
	else if (isset($_GET['edit_links']))
	{
		echo $html_heading;
		show_header();
		echo '<table border="0" cellpadding="8" cellspacing="0">
			<tr class="paragraph"><td class="default_td">';
		if ($links_file == '')
		{
			echo '<p>The link system is not in use.<br />To turn it on, set the $links_file variable.</p>';
		}
		else if (isset($_GET['link'], $_GET['name']) && $_GET['link'] != '')
		{
			if ($handle = @fopen($dir.$links_file, 'ab'))
			{
				fwrite($handle, $_GET['link'].'|'.$_GET['name']."\n");
				fclose($handle);
				echo '<p>Link added.</p>';
			}
			else
			{
				echo '<p>Could not open links_file for writing.</p>';
			}
		}
		else if (isset($_GET['remove']))
		{
			if (($list = @file($dir.$links_file)) && ($handle = @fopen($dir.$links_file, 'wb')))
			{
				for ($i=0; $i<count($list); $i++)
				{
					if (rtrim($list[$i]) != rtrim($_GET['remove']))
					{
						fwrite($handle, $list[$i]);
					}
				}
				fclose($handle);
				echo '<p>Link removed.</p>';
			}
			else
			{
				echo '<p>Could not open links_file.</p>';
			}
		}
		else
		{
			echo '<h3>Add a new link:</h3><div class"small">for the directory <em>', htmlentities($dir),
			"</em></div><form method='get' action='$this_file'>",
			'<input type="hidden" name="dir" value="', translate_uri($subdir),
			'" /><p>URL: <input type="text" name="link" size="40" value="http://" />
			<br />Name: <input type="text" name="name" size="35" />
			<br /><span class="small">(Leave "name" blank for the URL itself to be shown.)</span></p>';
			if ($index != '' && strpos($index, '?') !== false)
			{
				$id_temp = explode('=', $index, 2);
				$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
				echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
			}
			echo '<input type="hidden" name="edit_links" value="true" />
			<p><input class="button" type="submit" value="Add" /></p></form></td></tr></table></p>',
			'<p><table border="0" cellpadding="8" cellspacing="0"><tr class="paragraph"><td class="default_td">',
			'<h3>Remove a link:</h3>', "<form method='get' action='$this_file'>";
			if ($index != '' && strpos($index, '?') !== false)
			{
				$id_temp = explode('=', $index, 2);
				$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
				echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
			}
			echo '<input type="hidden" name="dir" value="', translate_uri($subdir), '" />',
			'<input type="hidden" name="edit_links" value="true" />';
			$list = @file($dir.$links_file) or $list = array();
			echo '<select name="remove">';
			for ($i=0; $i<count($list); $i++)
			{
				echo '<option>'.$list[$i].'</option>';
			}
			echo '</select><p><input class="button" type="submit" value="Delete" /></form></p>';
		}
		echo '</p></td></tr></table>', $con;
		show_footer();
		die();
	}
	else if (isset($_GET['copyFile'], $_GET['protocol']))
	{
		echo $html_heading;
		show_header();
		if ($_GET['copyFile'] == '')
		{
			echo '<p>Please go back and enter a file to copy.</p>', $con;
			show_footer();
			die();
		}
		$remote = $_GET['protocol'].$_GET['copyFile'];
		$local = $dir.get_basename($remote);
		if (!$allow_file_overwrites && @file_exists($local))
		{
			echo "File already exists: <em>$local</em>$con";
			show_footer();
			die();
		}
		$r = @fopen($remote, 'rb') or die("<p>Cannot open remote file for reading: <em>$remote</em></p>$con");
		$l = @fopen($local, 'wb') or die("<p>Cannot open local file for writing: <em>$local</em></p>$con");
		while (true)
		{
			$temp = fread($r, 8192);
			if ($temp === '')
			{
				break;
			}
			fwrite($l, $temp);
		}
		fclose($l);
		fclose($r);
		echo "<p>Remote file <em>$remote</em> successfully copied to <em>$local</em></p>$con";
		show_footer();
		die();
	}
	else if (isset($_GET['copyURL']))
	{
		echo $html_heading;
		show_header();
		echo "<table border='0' cellpadding='8' cellspacing='0'>
		<tr class='paragraph'><td class='default_td'>
		Enter the name of the remote file you would like to copy:
		<form method='get' action='$this_file'>
		<input type='hidden' name='dir' value='", translate_uri($subdir), "' />";
		if ($index != '' && strpos($index, '?') !== false)
		{
			$id_temp = explode('=', $index, 2);
			$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
			echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
		}
		echo '<p><input type="radio" name="protocol" value="http://" checked="checked" />http://
		<br /><input type="radio" name="protocol" value="ftp://" />ftp://
		<input type="text" name="copyFile" /></p>
		<p><input class="button" type="submit" value="Copy" /></p>
		</form></td></tr></table>';
		show_footer();
		die();
	}
	else if (isset($_GET['rename']) && $_GET['rename'] != '')
	{
		echo $html_heading;
		show_header();
		echo '<table border="0" cellpadding="8" cellspacing="0">
		<tr class="paragraph"><td class="default_td">';
		$p = $dir.eval_dir(rawurldecode($_GET['rename']));
		if (isset($_GET['newName']) && $_GET['newName'] != '')
		{
			$new_name = $dir.eval_dir(rawurldecode($_GET['newName']));
			if ($p == $new_name)
			{
				$msg = 'The filename is unchanged for ';
			}
			else if (@rename($p, $new_name))
			{
				$msg = 'Rename successful for ';
				if ($download_count != '')
				{
					$l = @file($download_count) or $l = array();
					if ($h = @fopen($download_count, 'wb'))
					{
						for ($i=0; $i<count($l); $i++)
						{
							$regex = '/^'.preg_quote($p, '/').'/';
							fwrite($h, preg_replace($regex, $new_name, $l[$i]));
						}
						fclose($h);
					}
				}
				if ($description_file != '')
				{
					$l = @file($description_file) or $l = array();
					if ($h = @fopen($description_file, 'wb'))
					{
						for ($i=0; $i<count($l); $i++)
						{
							$regex = '/^'.preg_quote($p, '/').'/';
							fwrite($h, preg_replace($regex, $new_name, $l[$i]));
						}
						fclose($h);
					}
				}
			}
			else
			{
				$msg = 'Rename failed for ';
			}
			echo $msg, htmlentities($p), $con, '</td></tr></table>';
			show_footer();
			die();
		}
		echo '<p>Renaming <em>', htmlentities($p), "</em></p><p>New Filename:
		<br /><span class='small'>(you can also move the file by specifying a path)</span>
		</p><form method='get' action='$this_file'>
		<input type='hidden' name='dir' value='", translate_uri($subdir), "' />
		<input type='hidden' name='rename' value='", translate_uri($_GET['rename']), '\' />';
		if ($index != '' && strpos($index, '?') !== false)
		{
			$id_temp = explode('=', $index, 2);
			$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
			echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
		}
		echo '<input type="text" name="newName" size="40" value="', $_GET['rename'], '" />
		<p><input class="button" type="submit" value="Rename" /></p></form></td></tr></table>';
		show_footer();
		die();
	}
	else if (isset($_GET['delete']) && $_GET['delete'] != '')
	{
		echo $html_heading;
		show_header();
		echo '<table border="0" cellpadding="8" cellspacing="0">
		<tr class="paragraph"><td class="default_td">';
		$_GET['delete'] = rawurldecode($_GET['delete']);
		$p = $dir.eval_dir($_GET['delete']);
		if (isset($_GET['sure'])) //delete the file
		{
			if (@is_dir($p))
			{
				$msg = (rmdir_recursive($p) ? 'Folder successfully deleted: '
					: 'Could not delete folder: ');
			}
			else if (@is_file($p))
			{
				$msg = (@unlink($p) ? 'File successfully deleted: '
					: 'Could not delete file: ');
			}
			else
			{
				$msg = 'File or folder does not exist: ';
			}
		}
		else //ask user for confirmation
		{
			$msg = 'Are you sure you want to delete <em>';
			$con = '</em><p><a class="default_a" href="'.$this_file.'dir='
				.translate_uri($subdir).'&amp;delete='.translate_uri($_GET['delete'])
				.'&amp;sure=true">Yes, delete it.</a></p><p><a class="default_a" href="'
				.$this_file.'dir='.translate_uri($subdir).'">No, go back.</a></p>';
		}
		echo $msg, htmlentities($p), $con, '</td></tr></table>';
		show_footer();
		die();
	}
	else if (isset($_GET['config']))
	{
		if (@is_file($config_generator))
		{
			define('CONFIG', true);
			if (!@include($config_generator))
			{
				die("$html_heading<p>Error including file <em>$config_generator</em></p>");
			}
			die();
		}
		else
		{
			die("$html_heading<p>File <em>$config_generator</em> not found.</p>");
		}
	}
	else if (isset($_GET['edit_ban']))
	{
		echo $html_heading;
		show_header();
		echo '<table border="0" cellpadding="8" cellspacing="0">
			<tr class="paragraph"><td class="default_td">';
		if ($banned_list == '')
		{
			echo '<p>The banning system is not in use.<br />To turn it on, set the $banned_list variable.</p>';
		}
		else if (isset($_GET['add_ban']))
		{
			if ($handle = @fopen($banned_list, 'ab'))
			{
				fwrite($handle, $_GET['add_ban']."\n");
				fclose($handle);
				echo '<p>Ban added.</p>';
			}
			else
			{
				echo '<p>Could not open ban_list file for writing.</p>';
			}
		}
		else if (isset($_GET['del_ban']))
		{
			$del_ban = rtrim($_GET['del_ban']);
			if (($list = @file($banned_list)) && ($handle = @fopen($banned_list, 'wb')))
			{
				for ($i=0; $i<count($list); $i++)
				{
					if (rtrim($list[$i]) != $del_ban)
					{
						fwrite($handle, $list[$i]);
					}
				}
				fclose($handle);
				echo '<p>Ban removed.</p>';
			}
			else
			{
				echo '<p>Could not open ban_list file.</p>';
			}
		}
		else
		{
			echo '<h3>Add a new ban:</h3>',
			"<form method='get' action='$this_file'>",
			'IP address or hostname: <input type="text" name="add_ban" size="35" />
			<br /><span class="small">You can use wildcards if you want (*, ?, +)</span></p>';
			if ($index != '' && strpos($index, '?') !== false)
			{
				$id_temp = explode('=', $index, 2);
				$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
				echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
			}
			echo '<input type="hidden" name="edit_ban" value="true" />
			<p><input class="button" type="submit" value="Add" /></p></form></td></tr></table></p>',
			'<table border="0" cellpadding="8" cellspacing="0"><tr class="paragraph"><td class="default_td">',
			'<h3>Remove a ban:</h3>'."<form method='get' action='$this_file'>";
			if ($index != '' && strpos($index, '?') !== false)
			{
				$id_temp = explode('=', $index, 2);
				$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
				echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
			}
			echo '<input type="hidden" name="edit_ban" value="true" />';
			$list = @file($banned_list) or $list = array();
			echo '<select name="del_ban">';
			for ($i=0; $i<count($list); $i++)
			{
				echo '<option>'.$list[$i].'</option>';
			}
			echo '</select><p><input class="button" type="submit" value="Remove" /></form>';
		}
		echo '</p></td></tr></table>', $con;
		show_footer();
		die();
	}
}

function get_change_color($num)
{
	if ($num > 0)
	{
		return '<span style="color: #00FF00;">+';
	}
	if ($num < 0)
	{
		return '<span style="color: #FF0000;">';
	}
	return '<span style="color: #000000;">';
}

if ($use_login_system && isset($_GET['log']))
//logfile viewer
{
	echo $html_heading;
	show_header();
	if (!logged_in() || !is_admin())
	{
		echo '<p>You must be logged in as an admin to access this page.</p>';
	}
	else if ($log_file == '')
	{
		echo '<p>The logging system is not in use.
		<br />To turn it on, set the $log_file variable.</p>';
	}
	else if (isset($_GET['view']))
	{
		$log = @file($log_file) or die("Cannot open log file: <em>$log_file</em>");
		$count_log = count($log);
		$max_to_display = (int)$_GET['view'];
		$num = (($max_to_display == 0) ? $count_log : min($max_to_display, $count_log));
		echo "<p>Last $num log entries (of $count_log".')</p><table width="100%"><tr>
		<th class="default_th">&nbsp;</th><th class="default_th">Date</th>
		<th class="default_th">Time</th><th class="default_th">IP</th>
		<th class="default_th">Hostname</th><th class="default_th">Referrer</th>
		<th class="default_th">File/Folder Viewed</th><th class="default_th">Other</th></tr>';
		for ($i=0; $i<$num; $i++)
		{
			$entries = explode("\t", rtrim($log[$count_log-$i-1]));
			$num_entries = count($entries);
			if ($num_entries > 5)
			{
				echo "\n<tr class=", (($i % 2) ? '"dark_row">' : '"light_row">'),
					'<td class="default_td"><strong>', ($i + 1), '</strong></td>';
				for ($j=0; $j<$num_entries; $j++)
				{
					echo '<td class="default_td">', (($j == 4 && $entries[4] != 'N/A') ?
						'<a class="default_a" href="'.$entries[$j].'">'.htmlentities($entries[$j]).'</a>' :
						htmlentities($entries[$j])).'</td>';
				}
				if ($num_entries === 6)
				{
					echo '<td class="default_td">&nbsp;</td>';
				}
				echo '</tr>';
			}
		}
		echo '</table>';
	}
	else if (isset($_GET['stats']))
	{
		if (!@include($path_to_language_files.'country_codes.php'))
		{
			die("<p>File not found: <em>{$path_to_language_files}country_codes.php</em></p>");
		}
		$extensions = $dates = $unique_hits = $countries = array();
		$total_hits = 0;
		$h = @fopen($log_file, 'rb') or die("<p>Cannot open log file: <em>$log_file</em></p>");
		while (!feof($h))
		{
			$entries = explode("\t", rtrim(fgets($h, 1024)));
			if (count($entries) > 5)
			{
				//find the number of unique visits
				if ($entries[5] == $base_dir)
				{
					$total_hits++;
					if (!in_array($entries[3], $unique_hits))
					{
						$unique_hits[] = htmlentities($entries[3]);
					}
	
					//find country codes by hostnames
					$cc = ext($entries[3]);
					if (preg_match('/^[a-z]+$/i', $cc))
					{
						add_num_to_array($cc, $countries);
					}
	
					//find the dates of the visits
					add_num_to_array($entries[0], $dates);
				}
	
				//find file extensions
				else if (($ext = ext($entries[5])) && preg_match('/^[\w-]+$/', $ext))
				{
					add_num_to_array($ext, $extensions);
				}
			}
		}
		fclose($h);
		$num_days = count($dates);
		$avg = round($total_hits/$num_days);

		echo '<table width="40%"><tr><th class="default_th">&nbsp;</th>
		<th class="default_th">Total</th><th class="default_th">Daily</th></tr>',
		"<tr class='light_row'><td class='default_td'>Hits</td>
		<td class='default_td'>$total_hits</td><td class='default_td'>$avg",
		'</td></tr><tr class="light_row"><td class="default_td">Unique Hits</td>
		<td class="default_td">'.count($unique_hits).'</td><td class="default_td">',
		round(count($unique_hits)/$num_days),
		'</td></tr></table><p>Percent Unique: ',
		number_format(count($unique_hits)/$total_hits*100, 1), '</p>';

		arsort($extensions);
		arsort($countries);

		$date_nums = array_values($dates);
		echo '<p /><table width="75%" border="0"><tr><th class="default_th">Date</th>
		<th class="default_th">Hits That Day</th><th class="default_th">Change From Previous Day</th>
		<th class="default_th">Difference From Average ('.$avg.')</th></tr>';
		$i = 0;
		foreach ($dates as $day => $num)
		{
			$diff = $num - $avg;
			$change = (($i > 0) ? ($num - $date_nums[$i-1]) : 0);
			$change_color = get_change_color($change);
			$diff_color = get_change_color($diff);

			$class = (($i++ % 2) ? 'dark_row' : 'light_row');
			echo "<tr class='$class'><td class='default_td'>$day</td>
			<td class='default_td'>$num</td>
			<td class='default_td'>$change_color$change</span></td>
			<td class='default_td'>$diff_color$diff</span></td></tr>";
		}
		
		echo '</table><p /><table width="75%" border="0">
		<tr><th class="default_th">Downloads based on file extensions</th>
		<th class="default_th">Total</th><th class="default_th">Daily</th></tr>';
		$i = 0;
		foreach ($extensions as $ext => $num)
		{
			$class = (($i++ % 2) ? 'dark_row' : 'light_row');
			echo "<tr class='$class'><td class='default_td'>$ext</td>
			<td class='default_td'>$num</td><td class='default_td'>",
			number_format($num/$num_days, 1), '</td></tr>';
		}
		
		echo '</table><p /><table width="75%" border="0"><tr>
		<th class="default_th">Hostname ISP extension</th>
		<th class="default_th">Total</th><th class="default_th">Daily</th></tr>';
		$i = 0;
		foreach ($countries as $c => $num)
		{
			$c_code = (isset($country_codes[strtolower($c)]) ? ' <span class="small">('.$country_codes[strtolower($c)].')</span>' : '');
			$class = (($i++ % 2) ? 'dark_row' : 'light_row');
			echo "<tr class='$class'><td class='default_td'>$c{$c_code}</td><td class='default_td'>$num</td><td class='default_td'>",
				number_format($num / $num_days, 1), "</td></tr>\n";
		}
		echo '</table>';
	}
	else
	{
		echo '<table border="0" cellpadding="8" cellspacing="0">
		<tr class="paragraph"><td class="default_td">'
		."<form method='get' action='$this_file'>
		<input type='hidden' name='log' value='true' />";
		if ($index != '' && strpos($index, '?') !== false)
		{
			$id_temp = explode('=', $index, 2);
			$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
			echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
		}
		echo '<p>List the latest <input type="text" size="2" name="view" /> enties in the log file (0 to view all).<input class="button" type="submit" value="Go" /></p></form>
		<p>or <a class="default_a" href="', $this_file, 'log=true&amp;stats=true">view statistics</a>.</p></td></tr></table>';
	}
	echo '<p><a class="default_a" href="', $this_file, '">Continue.</a></p>';
	show_footer();
	die();
}

if ($use_login_system && (isset($_POST['admin']) || isset($_GET['admin'])))
//user admin section
{
	echo $html_heading;
	show_header();
	if (!logged_in() || !is_admin())
	{
		echo '<p>You must be logged in as an admin to access this page.</p>';
	}
	else if (isset($_POST['username'], $_POST['password1'], $_POST['password2'], $_POST['admin']))
	{
		$pwd_reg_exp = '^[A-Za-z0-9_-]+$';
		if (strlen($_POST['password1']) < 6)
		{
			echo '<p>Password must be at least 6 characters long.</p>';
		}
		else if (!ereg($pwd_reg_exp, $_POST['username']))
		{
			echo 'The username must only contain alpha-numeric characters, underscores, or dashes.',
				'<br /><span class="small">It must match the regular expression: <strong>',
				htmlentities($pwd_reg_exp), '</strong></span>';
		}
		else if ($_POST['password1'] != $_POST['password2'])
		{
			echo '<p>Passwords do not match.</p>';
		}
		else if (is_username($_POST['username']))
		{
			echo '<p>That username already exists.</p>';
		}
		else
		{
			$handle = @fopen($user_list, 'ab') or die("<p>Could not open file <em>$user_list</em> for writing.</p>");
			fwrite($handle, md5($_POST['password1']).$_POST['admin'].$_POST['username']."\n");
			fclose($handle);
			echo '<p>User added. <a class="default_a" href="', $this_file, '">Continue.</a></p>';
		}
	}
	else if (isset($_POST['deluser'], $_POST['doit']))
	{
		if ($_POST['doit'])
		{
			if (is_user_admin($_POST['deluser']) && num_admins() < 2)
			{
				echo '<p>You cannot remove this user because it is the only admin.
				<br />Create another user with admin rights, then delete this user.</p>
				<p><a class="default_a" href="', $this_file, '">Continue.</a></p>';
			}
			else
			{
				$handle = @fopen($user_list, 'wb') or die("<p>Could not open file <strong>$user_list</strong> for writing.</p>");
				foreach ($global_user_list as $look)
				{
					if (strcasecmp($_POST['deluser'], substr(rtrim($look), 33)) !== 0)
					{
						fwrite($handle, $look);
					}
				}
				fclose($handle);
				echo '<p>User <strong>'.$_POST['deluser'].'</strong> has been removed. <a class="default_a" href="'
					.$this_file.'">Continue.</a></p>';
			}
		}
		else
		{
			echo '<table border="0" cellpadding="8" cellspacing="0"><tr class="paragraph"><td class="default_td">',
			'Are you sure you want to remove <strong>', $_POST['deluser'], "</strong>?<p><form method='post' action='$this_file'>",
			'<input type="hidden" name="doit" value="1" /><input type="hidden" name="admin" value="true" /><input type="hidden" name="deluser" value="',
			$_POST['deluser'], '" /><input class="button" type="submit" value="Yes, do it." />',
			'</form></td></tr></table>';
		}
	}
	else
	{
		echo "
		<table border='0' cellpadding='8' cellspacing='0'>
		<tr class='paragraph'><td class='default_td'>
		<h3>Add a user:</h3>
		<form method='post' action='$this_file'>
		<p><input type='hidden' name='admin' value='true' />
		Username: <input type='text' name='username' />
		<br />Password: <input type='password' name='password1' />
		<br />Password: <input type='password' name='password2' />
		<br />Is Admin?: <select name='admin'>
		<option selected='selected' value='0'>No</option>
		<option value='1'>Yes</option></select></p>
		<p><input class='button' type='submit' value='Add User' /></p>
		</form></td></tr></table>

		<p /><table border='0' cellpadding='8' cellspacing='0'>
		<tr class='paragraph'><td class='default_td'>
		<h3>Delete a user:</h3>
		<form method='post' action='$this_file'>
		<p><input type='hidden' name='admin' value='true' />
		Select user to delete: <select name='deluser'>";
		foreach ($global_user_list as $look)
		{
			echo '<option>', substr($look, 33), '</option>';
		}
		echo '</select><input type="hidden" name="doit" value="0" /></p>
		<p><input class="button" type="submit" value="Delete" /></p></form>
		</td></tr></table>';
	}
	show_footer();
	die();
}
else if ($use_login_system && isset($_GET['logout']))
//logout
{
	session_unset();
	session_destroy();
	redirect($this_file);
}
else if ($use_login_system && (isset($_POST['passwd']) || isset($_GET['passwd'])))
//change password
{
	echo $html_heading;
	show_header();
	if (!logged_in())
	{
		echo '<p>You must login to access this page.</p>';
	}
	else if (isset($_POST['oldpass'], $_POST['newpass1'], $_POST['newpass2']))
	{
		if (strlen($_POST['newpass1']) < 6)
		{
			echo '<p>New password too short (must be at least 6 characters).</p>';
		}
		else if ($_POST['newpass1'] != $_POST['newpass2'])
		{
			echo '<p>New passwords do not match.</p>';
		}
		else if (check_login($_SESSION['user'], md5($_POST['oldpass'])))
		{
			$handle = @fopen($user_list, 'wb') or die("<p>Could not open file <strong>$user_list</strong> for writing.</p>");
			foreach ($global_user_list as $look)
			{
				fwrite($handle, ((strcasecmp($_SESSION['user'] , substr(rtrim($look), 33)) === 0) ?
					md5($_POST['newpass1']).substr($look, 32) : $look));
			}
			fclose($handle);
			echo '<p>Password for <strong>'.$_SESSION['user'].'</strong> has been changed.<p>You must now <a class="default_a" href="'
				.$this_file.'">logout</a>.</p>';
		}
		else
		{
			echo '<p>Incorrect old password.</p>';
		}
	}
	else
	{
		echo "<table border='0' cellpadding='8' cellspacing='0'>
		<tr class='paragraph'><td class='default_td'>
		<form method='post' action='$this_file'>
		<input type='hidden' name='passwd' value='true' />
		Old Password: <input type='password' name='oldpass' />
		<br />New Password: <input type='password' name='newpass1' />
		<br />New Password: <input type='password' name='newpass2' />
		<p><input class='button' type='submit' value='Change Password' />
		</form></td></tr></table>";
	}
	show_footer();
	die();
}

$total_bytes = 0;

if ($links_file != '' && isset($_GET['link']))
//redirect to a link
{
	if (ok_to_log())
	{
		if ($log_file != '')
		{
			if ($write = @fopen($log_file, 'ab'))
			{
				fwrite($write, date($date_format)."\t".date('H:i:s')
					."\t$ip\t$host\t$referrer\t"
					.$_GET['link']."\tLink file\n");
				fclose($write);
			}
		}
		if ($download_count != '')
		{
			add_to_file($_GET['link'], $download_count);
		}
	}
	redirect($_GET['link']);
}

if ($file_dl != '')
//if the user specified a file to download, download it now
{
	if (!@is_file($dir.$file_dl))
	{
		header('HTTP/1.0 404 Not Found');
		echo $html_heading;
		show_header();
		echo '<h3>Error 404: file not found</h3>',
			htmlentities($dir . $file_dl), ' was not found on this server.';
		show_footer();
		die();
	}

	if ($anti_leech && !isset($_SESSION['ref']) && ($referrer == 'N/A' || !stristr($referrer, $_SERVER['SERVER_NAME'])))
	{
		if ($log_file != '' && ok_to_log())
		{
			if ($write = @fopen($log_file, 'ab'))
			{
				fwrite($write, date($date_format)."\t".date('H:i:s')
					."\t$ip\t$host\t$referrer\t$dir$file_dl\tLeech Attempt\n");
				fclose($write);
			}
		}
		$ref = (($referrer == 'N/A') ? 'typing it in the address bar...' : $referrer);
		echo $html_heading;
		show_header();
		echo '<h3>This PHP Script has an Anti-Leech feature turned on.<p>Make sure you are accessing this file directly from <a class="default_a" href="http://',
		$_SERVER['SERVER_NAME'], '">', htmlentities($_SERVER['SERVER_NAME']), '</a></h3>',
		'It seems you are trying to get it from <strong>', "$ref</strong><p>Your IP address has been logged.<br />$ip ($host)";
		$index_link = 'http://'.$_SERVER['SERVER_NAME'].$this_file.'dir='.translate_uri($subdir);
		echo '<p>Here is a link to the directory index the file is in:<br /><a class="default_a" href="',
			$index_link, '">', htmlentities($index_link), '</a></p>';
		show_footer();
		die();
	}
	
	if (ok_to_log())
	{
		if ($download_count != '')
		{
			add_to_file($dir.$file_dl, $download_count);
		}
		if ($log_file != '')
		{
			if ($write = @fopen($log_file, 'ab'))
			{
				fwrite($write, date($date_format)."\t".date('H:i:s')
					."\t$ip\t$host\t$referrer\t$dir$file_dl\n");
				fclose($write);
			}
		}
	}

	if ($force_download) //use php to read the file, and tell the browser to download it
	{
		if (!($fn = @fopen($dir.$file_dl, 'rb')))
		{
			die($html_heading.'<h3>Error 401: permission denied</h3> you cannot access <em>'
				.htmlentities($file_dl).'</em> on this server.');
		}
		$outname = get_basename($file_dl);
		$size = @filesize($dir.$file_dl);
		if ($size !== false)
		{
			header('Content-Length: '.$size);
		}
		header('Content-Type: '.find_mime_type(ext($outname)).'; name="'.$outname.'"');
		header('Content-Disposition: attachment; filename="'.$outname.'"');
		@set_time_limit(0);
		while (true)
		{
			$temp = @fread($fn, (int)($speed * 1024));
			if ($temp === '')
			{
				break;
			}
			echo $temp;
			flush();
			if ($bandwidth_limit)
			{
				sleep(1);
			}
		}
		fclose($fn);
		die();
	}
	redirect(translate_uri($dir.$file_dl));
}

if ($log_file != '' && ok_to_log())
//write to the logfile
{
	if ($write = @fopen($log_file, 'ab'))
	{
		$log_str = date($date_format)."\t".date('H:i:s')
			."\t$ip\t$host\t$referrer\t$dir";
		if ($search != '')
		{
			$log_str .= "\tSearch: $search";
		}
		fwrite($write, $log_str."\n");
		fclose($write);
	}
	else
	{
		echo '<p>Error: Could not write to logfile.</p>';
	}
}

if ($anti_leech && !isset($_SESSION['ref']))
{
	$_SESSION['ref'] = 1;
}

echo $html_heading;
show_header();

if (!@is_dir($dir))
//make sure the subfolder exists
{
	echo '<p><strong>Error: The folder <em>'.htmlentities($dir)
		.'</em> does not exist.</strong></p>';
	$dir = $base_dir;
	$subdir = '';
}

if ($enable_searching && $search != '')
//show the results of a search
{
	echo '<table border="0" cellpadding="8" cellspacing="0">
		<tr class="paragraph"><td class="default_td"><p><strong>',
		$words['search results'], '</strong> :<br /><span class="small">for <em>',
		htmlentities($dir), '</em> and its subdirectories</span></p><p>';
	$results = search_dir($dir, $search);
	natcasesort($results);
	if ($_SESSION['sort'] == 'd' && $_SESSION['sortMode'] == 'f')
	{
			$results = array_reverse($results);
	}
	for ($i=0; $i<count($results); $i++)
	{
		$file = substr($results[$i], strlen($base_dir));
		echo '<a class="default_a" href="'.$this_file;
		if (is_dir($base_dir.$file))
		{
			echo 'dir='.translate_uri($file).'/">';
			if ($icon_path != '')
			{
				echo '<img height="16" width="16" alt="[dir]" src="', $icon_path, '/dir.png" /> ';
			}
			echo htmlentities($file)."/</a><br />\n";
		}
		else if (preg_match('/\|$/', $file))
		{
			$file = substr($file, 0, -1);
			$display = get_stored_info($file, $dir.$links_file);
			if ($display == '')
			{
				$display = $file;
			}
			echo 'dir=', translate_uri($subdir), '&amp;link=',
			translate_uri($file), '" title="Link to: ', $file, '">',
			icon(ext($display)), htmlentities($display), '</a><br />';
		}
		else
		{
			echo 'dir=', translate_uri(dirname($file)).'/&amp;file=',
			translate_uri(get_basename($file)), '">',
			icon(ext($file)), htmlentities($file), "</a><br />\n";
		}
	}
	if (!count($results))
	{
		echo '</p><p><strong>[ ', $words['no results'], ' ]</strong></p>';
	}
	echo '</p><p>', $words['end of results'], ' (', count($results), ' ',
		$words['found'], ')</p></td></tr></table>';
	show_search_box();
	echo '<p><a class="default_a" href="', $this_file, 'dir=',
		translate_uri($subdir), '">Go back.</a></p>';
	show_footer();
	die();
}

//path navigation at the top
echo '<div>', $words['index of'], ' <a class="default_a" href="', $this_file,
	'dir=">', htmlentities(substr(str_replace('/', ' / ', $base_dir), 0, -2)),
	'</a>/ ';
$exploded = explode('/', $subdir);
$c = count($exploded) - 1;
for ($i=0; $i<$c; $i++)
{
	echo '<a class="default_a" href="', $this_file, 'dir=';
	for ($j=0; $j<=$i; $j++)
	{
		echo translate_uri($exploded[$j]), '/';
	}
	echo '">', htmlentities($exploded[$i]), '</a> / ';
}

//begin the table
echo "</div>\n\n", '<table width="100%" border="0" cellpadding="0" cellspacing="2"><tr>';
table_heading($words['file'], 'f', 'Sort by Filename');
if ($show_type_column)
{
	table_heading('Type', 't', 'Sort by Type');
}
if ($download_count != '')
{
	table_heading('Downloads', 'h', 'Sort by Hits');
}
if ($show_size_column)
{
	table_heading($words['size'], 's', 'Sort by Size');
}
if ($show_date_column)
{
	table_heading($words['modified'], 'm', 'Sort by Date');
}
if ($description_file != '')
{
	table_heading('Description', 'd', 'Sort by Description');
}
echo '</tr>';

if ($subdir != '')
//if they are not in the root folder, have a link to the parent directory
{
	echo '<tr class="light_row"><td class="default_td" colspan="6"><a class="default_a" href="', $this_file, 'dir=';
	$subdir = substr($subdir, 0, -1);
	echo translate_uri(substr($subdir, 0, strrpos($subdir,'/'))), '/">';
	if ($icon_path != '')
	{
		echo "<img height=\"16\" width=\"16\" src=\"$icon_path/back.png\" alt=\"[dir]\" /> ";
	}
	echo $words['parent directory'], '</a></td></tr>';
	$subdir .= '/';
}

flush();

$file_array = get_file_list($dir);
$size_array = $date_a_array = $date_m_array = $desc_array = $hit_array = $type_array = array();

$c = count($file_array);
for ($i=0; $i<$c; $i++)
{
	$thisf = $dir.$file_array[$i];
	if (preg_match('/\|$/', $thisf)) //it is a link
	{
		$thisf = substr($thisf, 0, -1);
		$type_array[] = ($show_type_column ? ext(get_stored_info(substr($file_array[$i], 0, -1), $dir.$links_file)) : '');
		$hit_array[] = (($download_count != '' && !@is_dir($thisf)) ? (int)(get_stored_info(substr($file_array[$i], 0, -1), $download_count)) : 0);
		$date_m_array[] = 'N/A';
		$date_a_array[] = 'N/A';
		$size_array[] = '[Link]';
	}
	else //it is an actual file or folder
	{
		$size_array[] = ($show_size_column ? (@is_dir($thisf) ? ($show_dir_size ? dir_size("$thisf/") : 0) : max((int)@filesize($thisf), 0)) : 0);
		$type_array[] = (($show_type_column && !@is_dir($thisf)) ? ext($thisf) : '');
		$hit_array[] = (($download_count != '' && !@is_dir($thisf)) ? (int)(get_stored_info($thisf, $download_count)) : 0);
		if ($show_date_column)
		{
			$date_m_array[] = filemtime($thisf);
			$date_a_array[] = fileatime($thisf);
		}
		else
		{
			$date_m_array[] = 0;
			$date_a_array[] = 0;
		}
	}
	$desc_array[] = (($description_file == '') ? '' : get_stored_info($thisf, $description_file));
}

switch (strtolower($_SESSION['sortMode']))
{
	case 's':
		array_multisort($size_array, $file_array, $date_m_array,
			$date_a_array, $hit_array, $desc_array, $type_array);
		break;
	case 'm':
		array_multisort($date_m_array, $file_array, $size_array,
			$date_a_array, $hit_array, $desc_array, $type_array);
		break;
	case 'd':
		array_multisort($desc_array, $file_array, $date_m_array,
			$size_array, $date_a_array, $hit_array, $type_array);
		break;
	case 'h':
		array_multisort($hit_array, $file_array, $date_m_array,
			$size_array, $date_a_array, $desc_array, $type_array);
		break;
	case 't':
		array_multisort($type_array, $file_array, $hit_array,
			$date_m_array, $size_array, $date_a_array, $desc_array);
}

if (strtolower($_SESSION['sort']) === 'd')
//if the current sort mode is set to descending, reverse all the arrays
{
	$file_array = array_reverse($file_array);
	$size_array = array_reverse($size_array);
	$date_m_array = array_reverse($date_m_array);
	$date_a_array = array_reverse($date_a_array);
	$desc_array = array_reverse($desc_array);
	$hit_array = array_reverse($hit_array);
	$type_array = array_reverse($type_array);
}

$folder_count = $file_count = $dl_count = 0;

for ($i=0; $i<$c; $i++)
//display the list of files
{
	$value = $file_array[$i];
	echo "\n<tr class=", (($i % 2 == ($subdir == '')) ? '"dark_row">' : '"light_row">');

	//file column
	echo '<td class="default_td" align="left" valign="top"><a class="default_a" href="', $this_file;
	$npart = $dir . $value;
	if (preg_match('/\|$/', $value)) //it is a link, not an actual file
	{
		$value = substr($value, 0, -1);
		$npart = substr($npart, 0, -1);
		$display = get_stored_info($value, $dir.$links_file);
		if ($display == '')
		{
			$display = $value;
		}
		echo 'dir=', translate_uri($subdir), '&amp;link=',
			translate_uri($value), '" title="Link to: ', $value, '">',
			icon(ext($display)), htmlentities($display), '</a>';
	}
	else //it is a real file or folder
	{
		if (@is_dir($npart))
		{
			$folder_count++;
			if ($icon_path != '')
			{
				if ($folder_expansion)
				{
					$listVal = (in_array($npart, $_SESSION['expanded']) ? 'collapse' : 'expand');
					echo 'dir=', translate_uri($subdir), "&amp;$listVal=", translate_uri($value),
					'"><img height="16" width="16" alt="[dir]" src="',
					$icon_path.'/dir.png" /></a> ',
					'<a class="default_a" href="', $this_file, 'dir=',
					translate_uri($subdir . $value), '/">';
				}
				else
				{
					echo 'dir=', translate_uri($subdir . $value), '/">',
					'<img height="16" width="16" alt="[dir]" src="', $icon_path, '/dir.png" /> ';
				}
			}
			else
			{
				echo 'dir=', translate_uri($subdir . $value), '/">';
			}
			echo htmlentities($value).'</a>';
			if ($show_folder_count)
			{
				$n = num_files($npart);
				$s = (($n == 1) ? $words['file'] : $words['files']);
				echo " [$n $s]";
			}
		}
		else //is a file
		{
			$file_count++;
			echo 'dir=', translate_uri($subdir), '&amp;file=',
			translate_uri($value), "\">",
			icon(ext($npart)), htmlentities($value), '</a>';
			if ($md5_show && $size_array[$i] > 0 && $size_array[$i] / 1048576 <= $md5_show)
			{
				echo ' [<a class="default_a" href="', $this_file,
				'dir=', translate_uri($subdir), '&amp;md5=',
				translate_uri($value), '"><span class="small">get md5sum</span></a>]';
			}
		}
		if ($use_login_system && logged_in() && is_admin())
		{
			echo ' [<a class="default_a" href="', $this_file, 'dir='.translate_uri($subdir),
			'&amp;delete=', translate_uri($value), '"><span class="small">delete</span></a>, ',
			'<a class="default_a" href="', $this_file, 'dir=', translate_uri($subdir),
			'&amp;rename=', translate_uri($value), '"><span class="small">rename/move</span></a>]';
		}
		$age = (time() - $date_m_array[$i]) / 86400;
		$age_r = round($age, 1);
		$s = (($age_r == 1) ? '' : 's');
		if ($days_new && $age <= $days_new)
		{
			echo (($icon_path == '') ? ' <span class="small" style="color: #FF0000;">[New]</span>'
				: ' <img alt="'."$age_r day$s".' old" height="14" width="28" src="'.$icon_path.'/new.png" />');
		}
		if ($folder_expansion && @is_dir($npart) && in_array($npart, $_SESSION['expanded']))
		{
			$ex_array = get_file_list($npart.'/');
			if ($_SESSION['sort'] == 'd' && $_SESSION['sortMode'] == 'f')
			{
					$ex_array = array_reverse($ex_array);
			}
			echo '<ul>';
			for ($j=0; $j<count($ex_array); $j++)
			{
				$element = $ex_array[$j];
				echo '<li><a class="default_a" href="'.$this_file
					.((@is_file("$npart/$element")) ? 'dir='.translate_uri($subdir.$value).'/&amp;file='
					.translate_uri($element).'">' : 'dir='.translate_uri("$subdir$value/$element/").'">');
				if (@is_file("$npart/$element"))
				{
					echo icon(ext($element));
				}
				else if ($icon_path != '')
				{
					echo '<img height="16" width="16" alt="[dir]" src="',
						$icon_path, '/dir.png" /> ';
				}
				echo htmlentities($element), "</a></li>\n";
			}
			echo '</ul>';
		}
	}
	if ($use_login_system && $description_file != '' && logged_in() && is_admin())
	//"edit description" link
	{
		echo ' [<a class="default_a" href="', $this_file, 'dir=',
		translate_uri($subdir), '&amp;descFile=', translate_uri($value),
		'"><span class="small">change description</span></a>]';
	}
	
	if ($thumbnail_height > 0 && in_array(ext($value), array('png', 'jpg', 'jpeg', 'gif')) && @is_file($npart))
	//display the thumbnail image
	{
		echo ' <a href="'.$this_file.'dir=', translate_uri($subdir), '&amp;file=',
		translate_uri($value), '"><img src="', $this_file,
		'dir=', translate_uri($subdir), '&amp;thumbnail=', translate_uri($value),
		'" alt="Thumbnail of ', $value, '" /></a>';
	}
	
	echo '</td>'; //end filename column
	
	//filetype column
	if ($show_type_column)
	{
		echo '<td class="default_td" align="left" valign="top">',
		(($type_array[$i] == '') ? '&nbsp;' : htmlentities($type_array[$i])), '</td>';
	}

	//hits column
	if ($download_count != '')
	{
		$dl_count += $hit_array[$i];
		echo '<td class="default_td" align="right" valign="top">',
		((!@is_dir($npart)) ? $hit_array[$i] : '&nbsp;'), '</td>';
	}

	//size column
	if ($show_size_column)
	{
		echo '<td class="default_td" align="right" valign="top">';
		$ds = $size_array[$i];
		if ($ds === '[Link]')
		{
			echo $ds;
		}
		else
		{
			$total_bytes += $ds;
			$size_h = get_filesize($ds);
			echo (@is_dir($npart) ?
			($show_dir_size ? "<a title=\"$value/\n".number_format($ds, 0, '.', ',')." bytes ($size_h)\">$size_h</a>" : '[dir]')
			: "<a title=\"$value\n".number_format($ds, 0, '.', ',')." bytes ($size_h)\">$size_h</a>");
		}
		echo '</td>';
	}

	//date column
	if ($show_date_column)
	{
		echo '<td class="default_td" align="right" valign="top">';
		if ($date_a_array[$i] == 'N/A')
		{
			echo 'N/A';
		}
		else
		{
			$a = date($date_format.' h:i:s A', $date_a_array[$i]);
			$m = date($date_format.' h:i:s A', $date_m_array[$i]);
			echo "<a title=\"$value\nLast Modified: $m\nLast Accessed: $a\">",
				date($date_format, $date_m_array[$i]), '</a>';
		}
		echo '</td>';
	}
	
	//description column
	if ($description_file != '')
	{
		echo '<td class="default_td" align="left" valign="top">',
			(($desc_array[$i] != '') ? $desc_array[$i] : '&nbsp;'), '</td>';
	}
	
	echo "</tr>\n";
}

//footer of the table
echo '<tr><th class="default_th"><span class="small">', "\n$file_count ",
	$words[(($file_count == 1) ? 'file' : 'files')],
	" - $folder_count ", $words['folders'], '</span></th>';
if ($show_type_column)
{
	echo "<th class='default_th'>&nbsp;</th>";
}
if ($download_count != '')
{
	echo "<th class='default_th'><span class='small'>Total: $dl_count</span></th>";
}
if ($show_size_column)
{
	echo '<th class="default_th"><span class="small">', $words['total size'], ': <a title="' ,$words['total size'], ":\n",
		number_format($total_bytes, 0, '.', ','), ' bytes (', get_filesize($total_bytes), ')">',
		get_filesize($total_bytes), "</a></span></th>\n";
}
if ($show_date_column)
{
	echo '<th class="default_th">&nbsp;</th>';
}
if ($description_file != '')
{
	echo '<th class="default_th">&nbsp;</th>';
}
echo '</tr></table><div class="small" style="text-align: right;">Powered by <a class="default_a" href="http://autoindex.sourceforge.net/">AutoIndex PHP Script</a></div>';
		/*
		 * We request that you do not remove the link to the AutoIndex website.
		 * This not only gives respect to the large amount of time given freely by the
		 * developer, but also helps build interest, traffic, and use of AutoIndex.
		 */

echo "\n", '<table width="100%" border="0" cellpadding="0" cellspacing="2">
<tr valign="top"><td>';
if ($enable_searching)
{
	show_search_box();
}

if ($use_login_system)
{
	if (!logged_in())
	{
		echo '</td><td>';
		show_login_box();
	}
	else //show user options
	{
		echo '<br /><table border="0" cellpadding="8" cellspacing="0"><tr class="paragraph"><td class="default_td">';
		if (is_admin())
		{
			echo '<p><a class="default_a" href="'.$this_file.'config=true">Reconfigure script</a></p>',
			'<p><a class="default_a" href="'.$this_file.'admin=true">User account management</a>',
			'<br /><a class="default_a" href="'.$this_file.'log=true">Log file viewer / statistics</a>',
			'<br /><a class="default_a" href="'.$this_file.'edit_links=true&amp;dir='.translate_uri($subdir).'">Links file editor</a>',
			'<br /><a class="default_a" href="'.$this_file.'edit_ban=true">Edit ban list</a></p>',
			'<p><a class="default_a" href="'.$this_file.'getcreate=true&amp;dir='.translate_uri($subdir).'">Create a folder (in current directory)</a>',
			'<br /><a class="default_a" href="'.$this_file.'copyURL=true&amp;dir='.translate_uri($subdir).'">Copy a remote file (to current directory)</a></p>';
		}
		echo '<p><a class="default_a" href="', $this_file,
		'passwd=true">Change password</a><br /><a class="default_a" href="', $this_file,
		'logout=true">Log out [ ', $_SESSION['user'], ' ]</a></p></td></tr></table>';
	}
}
echo '</td></tr></table>';

if ($allow_uploads && (!$use_login_system || logged_in()))
{
	echo "<form method='post' action='$this_file'>
	<input type='hidden' name='dir' value='$subdir' />
	Upload <select size='1' name='numUpload'>";
	for ($i=1; $i<=10; $i++)
	{
		echo "\t<option>$i</option>\n";
	}
	echo '</select> file(s) to this folder <input type="submit" value="Go" /></form>';
}

if ($select_language)
{
	echo '<p style="text-align: left;">Select Language:</p>',
		"<form method='get' action='$this_file'><div><select name='lang'>";
	$l = get_all_files($path_to_language_files);
	sort($l);
	for ($i=0; $i<count($l); $i++)
	{
		if (@is_file($path_to_language_files.$l[$i]) &&
			preg_match('/^[a-z]{2}(_[a-z]{2})?\.php$/i', $l[$i]))
		{
			$f = substr(get_basename($l[$i]), 0, -4);
			$sel = (($f == $_SESSION['lang']) ? ' selected="selected"' : '');
			echo "\t<option$sel>$f</option>\n";
		}
	}
	echo '</select><input type="submit" value="Change" />';
	if ($index != '' && strpos($index, '?') !== false)
	{
		$id_temp = explode('=', $index, 2);
		$id_temp[0] = substr(strstr($id_temp[0], '?'), 1);
		echo "<input type='hidden' name='$id_temp[0]' value='$id_temp[1]' />";
	}
	echo '</div></form>';
}

show_footer();

//find time it took for the page to generate, in milliseconds
$page_time = round((get_microtime() - $start_time) * 1000, 1);

echo '
<!--

Powered by AutoIndex PHP Script (version '.VERSION.')
Copyright (C) 2002-2005 Justin Hagstrom
http://autoindex.sourceforge.net

Page generated in ', $page_time, ' milliseconds.

-->
'; //We request that you retain the above copyright notice.

if ($index == '')
{
	echo '</body></html>';
}

?>
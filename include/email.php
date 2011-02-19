<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

require PUN_ROOT.'include/utf8/utils/ascii.php';

//
// Validate an email address
//
function is_valid_email($email)
{
	if (strlen($email) > 80)
		return false;

	return preg_match('/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|("[^"]+"))@((\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])|(([a-zA-Z\d\-]+\.)+[a-zA-Z]{2,}))$/', $email);
}


//
// Check if $email is banned
//
function is_banned_email($email)
{
	global $pun_bans;

	foreach ($pun_bans as $cur_ban)
	{
		if ($cur_ban['email'] != '' &&
			($email == $cur_ban['email'] ||
			(strpos($cur_ban['email'], '@') === false && stristr($email, '@'.$cur_ban['email']))))
			return true;
	}

	return false;
}


//
// Only encode with base64, if there is at least one unicode character in the string
//
function encode_mail_text($str)
{
	if (utf8_is_ascii($str))
		return $str;

	return '=?UTF-8?B?'.base64_encode($str).'?=';
}


//
// Wrapper for PHP's mail()
//
function pun_mail($to, $subject, $message, $reply_to_email = '', $reply_to_name = '')
{
	global $pun_config, $lang_common;

	// Default sender/return address
	$from_name = str_replace('"', '', $pun_config['o_board_title'].' '.$lang_common['Mailer']);
	$from_email = $pun_config['o_webmaster_email'];

	// Do a little spring cleaning
	$to = pun_trim(preg_replace('#[\n\r]+#s', '', $to));
	$subject = pun_trim(preg_replace('#[\n\r]+#s', '', $subject));
	$from_email = pun_trim(preg_replace('#[\n\r:]+#s', '', $from_email));
	$from_name = pun_trim(preg_replace('#[\n\r:]+#s', '', str_replace('"', '', $from_name)));
	$reply_to_email = pun_trim(preg_replace('#[\n\r:]+#s', '', $reply_to_email));
	$reply_to_name = pun_trim(preg_replace('#[\n\r:]+#s', '', str_replace('"', '', $reply_to_name)));

	// Set up some headers to take advantage of UTF-8
	$from = encode_mail_text($from_name).' <'.$from_email.'>';
	$subject = encode_mail_text($subject);

	$headers = 'From: '.$from."\r\n".'Date: '.gmdate('r')."\r\n".'MIME-Version: 1.0'."\r\n".'Content-transfer-encoding: 8bit'."\r\n".'Content-type: text/plain; charset=utf-8'."\r\n".'X-Mailer: FluxBB Mailer';

	// If we specified a reply-to email, we deal with it here
	if (!empty($reply_to_email))
	{
		$reply_to = encode_mail_text($reply_to_name).' <'.$reply_to_email.'>';

		$headers .= "\r\n".'Reply-To: '.$reply_to;
	}

	// Make sure all linebreaks are CRLF in message (and strip out any NULL bytes)
	$message = str_replace(array("\n", "\0"), array("\r\n", ''), pun_linebreaks($message));

	if ($pun_config['o_smtp_host'] != '')
		smtp_mail($to, $subject, $message, $headers);
	else
	{
		// Change the linebreaks used in the headers according to OS
		if (strtoupper(substr(PHP_OS, 0, 3)) == 'MAC')
			$headers = str_replace("\r\n", "\r", $headers);
		else if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN')
			$headers = str_replace("\r\n", "\n", $headers);

		mail($to, $subject, $message, $headers);
	}
}


//
// This function was originally a part of the phpBB Group forum software phpBB2 (http://www.phpbb.com)
// They deserve all the credit for writing it. I made small modifications for it to suit PunBB and it's coding standards
//
function server_parse($socket, $expected_response)
{
	$server_response = '';
	while (substr($server_response, 3, 1) != ' ')
	{
		if (!($server_response = fgets($socket, 256)))
			error('Couldn\'t get mail server response codes. Please contact the forum administrator.', __FILE__, __LINE__);
	}

	if (!(substr($server_response, 0, 3) == $expected_response))
		error('Unable to send email. Please contact the forum administrator with the following error message reported by the SMTP server: "'.$server_response.'"', __FILE__, __LINE__);
}


//
// This function was originally a part of the phpBB Group forum software phpBB2 (http://www.phpbb.com)
// They deserve all the credit for writing it. I made small modifications for it to suit PunBB and it's coding standards.
//
function smtp_mail($to, $subject, $message, $headers = '')
{
	global $pun_config;

	$recipients = explode(',', $to);

	// Sanitize the message
	$message = str_replace("\r\n.", "\r\n..", $message);
	$message = (substr($message, 0, 1) == '.' ? '.'.$message : $message);

	// Are we using port 25 or a custom port?
	if (strpos($pun_config['o_smtp_host'], ':') !== false)
		list($smtp_host, $smtp_port) = explode(':', $pun_config['o_smtp_host']);
	else
	{
		$smtp_host = $pun_config['o_smtp_host'];
		$smtp_port = 25;
	}

	if ($pun_config['o_smtp_ssl'] == '1')
		$smtp_host = 'ssl://'.$smtp_host;

	if (!($socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 15)))
		error('Could not connect to smtp host "'.$pun_config['o_smtp_host'].'" ('.$errno.') ('.$errstr.')', __FILE__, __LINE__);

	server_parse($socket, '220');

	if ($pun_config['o_smtp_user'] != '' && $pun_config['o_smtp_pass'] != '')
	{
		fwrite($socket, 'EHLO '.$smtp_host."\r\n");
		server_parse($socket, '250');

		fwrite($socket, 'AUTH LOGIN'."\r\n");
		server_parse($socket, '334');

		fwrite($socket, base64_encode($pun_config['o_smtp_user'])."\r\n");
		server_parse($socket, '334');

		fwrite($socket, base64_encode($pun_config['o_smtp_pass'])."\r\n");
		server_parse($socket, '235');
	}
	else
	{
		fwrite($socket, 'HELO '.$smtp_host."\r\n");
		server_parse($socket, '250');
	}

	fwrite($socket, 'MAIL FROM: <'.$pun_config['o_webmaster_email'].'>'."\r\n");
	server_parse($socket, '250');

	foreach ($recipients as $email)
	{
		fwrite($socket, 'RCPT TO: <'.$email.'>'."\r\n");
		server_parse($socket, '250');
	}

	fwrite($socket, 'DATA'."\r\n");
	server_parse($socket, '354');

	fwrite($socket, 'Subject: '.$subject."\r\n".'To: <'.implode('>, <', $recipients).'>'."\r\n".$headers."\r\n\r\n".$message."\r\n");

	fwrite($socket, '.'."\r\n");
	server_parse($socket, '250');

	fwrite($socket, 'QUIT'."\r\n");
	fclose($socket);

	return true;
}

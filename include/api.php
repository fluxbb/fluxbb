<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */


require_once PUN_ROOT.'include/functions.php';


/**
 * Validate topic data.
 * 
 * @param  array  $data
 * @return bool
 */
function flux_is_invalid_topic($data)
{
	global $pun_config, $lang_post;

	$subject = pun_trim($data['subject']);

	if ($pun_config['o_censoring'] == '1')
		$censored_subject = pun_trim(censor_words($subject));

	$errors = array();

	if ($subject == '')
		$errors[] = $lang_post['No subject'];
	else if ($pun_config['o_censoring'] == '1' && $censored_subject == '')
		$errors[] = $lang_post['No subject after censoring'];
	else if (pun_strlen($subject) > 70)
		$errors[] = $lang_post['Too long subject'];
	else if ($pun_config['p_subject_all_caps'] == '0' && is_all_uppercase($subject) && !$pun_user['is_admmod'])
		$errors[] = $lang_post['All caps subject'];

	return $errors;
}

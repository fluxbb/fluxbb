<?php

// Language definitions used in post.php and edit.php
return array(

// Post validation stuff (many are similiar to those in edit.php)
'no_subject'		=>	'Topics must contain a subject.',
'no_subject_after_censoring'	=>	'Topics must contain a subject. After applying censoring filters, your subject was empty.',
'too_long_subject'	=>	'Subjects cannot be longer than 70 characters.',
'no_message'		=>	'You must enter a message.',
'no_message_after_censoring'	=>	'You must enter a message. After applying censoring filters, your message was empty.',
'too_long_message'	=>	'Posts cannot be longer than %s bytes.',
'all_caps_subject'	=>	'Subjects cannot contain only capital letters.',
'all_caps_message'	=>	'Posts cannot contain only capital letters.',
'empty_after_strip'	=>	'It seems your post consisted of empty BBCodes only. It is possible that this happened because e.g. the innermost quote was discarded because of the maximum quote depth level.',

// Posting
'post_errors'		=>	'Post errors',
'post_errors_info'	=>	'The following errors need to be corrected before the message can be posted:',
'post_preview'		=>	'Post preview',
'guest_name'		=>	'Name', // For guests (instead of Username)
'post_redirect'		=>	'Post entered. Redirecting …',
'post_a_reply'		=>	'Post a reply',
'post_new_topic'	=>	'Post new topic',
'hide_smilies'		=>	'Never show smilies as icons for this post',
'subscribe'			=>	'Subscribe to this topic',
'stay_subscribed'	=>	'Stay subscribed to this topic',
'topic_review'		=>	'Topic review (newest first)',
'flood_start'		=>	'At least',
'flood_end'			=>	'seconds have to pass between posts. Please wait a little while and try posting again.',
'preview'			=>	'Preview', // submit button to preview message

// Edit post
'edit_post_legend'	=>	'Edit the post and submit changes',
'silent_edit'		=>	'Silent edit (don\'t display "Edited by ..." in topic view)',
'edit_post'			=>	'Edit post',
'edit_redirect'		=>	'Post updated. Redirecting …'

);

<?php

return array(

	'viewforum' => 
		array(
			'url'		=> 'forum/{id}',
			'action'	=> 'home@forum',
		),
	'viewtopic' =>
		array(
			'url'		=> 'topic/{id}',
			'action'	=> 'home@topic',
		),
	'viewpost' =>
		array(
			'url'		=> 'post/{id}',
			'action'	=> 'home@post',
		),
	'index' =>
		array(
			'url'		=> '',
			'action'	=> 'home@index',
		),
	'profile' =>
		array(
			'url'		=> 'profile/{id}/{username}',
			'action'	=> 'user@profile',
		),
	'userlist' =>
		array(
			'url'		=> 'users',
			'action'	=> 'user@list',
		),
	'register' =>
		array(
			'url'		=> 'register',
			'action'	=> 'auth@register',
		),
	'login' =>
		array(
			'url'		=> 'login',
			'action'	=> 'auth@login',
		),
	'forgot_password' =>
		array(
			'url'		=> 'forgot_password.html',
			'action'	=> 'auth@forgot',
		),
	'logout' =>
		array(
			'url'		=> 'logout',
			'action'	=> 'auth@logout',
		),
	'rules' =>
		array(
			'url'		=> 'rules',
			'action'	=> 'misc@rules',
		),
	'email' =>
		array(
			'url'		=> 'email/{id}',
			'action'	=> 'misc@email',
		),
	'search' =>
		array(
			'url'		=> 'search',
			'action'	=> 'search@index',
		),
	'post_report' =>
		array(
			'url'		=> 'post/{id}/report',
			'action'	=> 'misc@report',
		),
	'post_delete' =>
		array(
			'url'		=> 'post/{id}/delete',
			'action'	=> 'posting@delete',
		),
	'post_edit' =>
		array(
			'url'		=> 'post/{id}/edit',
			'action'	=> 'posting@edit',
		),
	'post_quote' =>
		array(
			'url'		=> 'post/{id}/quote',
			'action'	=> 'posting@quote',
		),
	'reply' =>
		array(
			'url'		=> 'topic/{id}/reply',
			'action'	=> 'posting@reply',
		),
	'new_topic' =>
		array(
			'url'		=> 'forum/{id}/topic/new',
			'action'	=> 'posting@topic',
		),

);

<?php
/**
 * FluxBB - fast, light, user-friendly PHP forum software
 * Copyright (C) 2008-2012 FluxBB.org
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public license for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category	FluxBB
 * @package		Core
 * @copyright	Copyright (c) 2008-2012 FluxBB (http://fluxbb.org)
 * @license		http://www.gnu.org/licenses/gpl.html	GNU General Public License
 */

namespace FluxBB\Controllers;

use FluxBB\Routing\Controller;
use FluxBB\Models\Post,
	FluxBB\Models\Topic,
	FluxBB\Models\Forum,
	FluxBB\Models\User,
	FluxBB\Models\Config;

class Posting extends Controller
{

	public function get_reply($tid)
	{
		$topic = Topic::with(array(
			'forum',
			'forum.perms',
		))
		->where('id', '=', $tid)
		->first();

		if (is_null($topic))
		{
			return $this->show404();
		}

		return $this->view('posting.post')
			->with('topic', $topic)
			->with('action', t('post.post_a_reply'));
	}

	public function post_reply($tid)
	{
		$topic = Topic::with(array(
			'forum',
			'forum.perms',
		))
		->where('id', '=', $tid)
		->first();

		if (is_null($topic))
		{
			return $this->show404();
		}

		// TODO: Flood protection
		$rules = array(
			// TODO: PUN_MAX_POSTSIZE, censor, All caps message
			'req_message'		=> 'required',
		);
		// TODO: More validation

		if ($this->guest())
		{
			if (Config::enabled('p_force_guest_email') || $this->input('email') != '')
			{
				$rules['req_email']	= 'required|email';
			}

			// TODO: banned email
		}

		$validation = $this->validator($this->input(), $rules);
		if ($validation->fails())
		{
			return $this->redirect('posting@reply', array($tid))->withInput()->withErrors($validation);
		}

		$post_data = array(
			'poster'			=> User::current()->username,
			'poster_id'			=> User::current()->id,
			'poster_ip'			=> $this->app['request']->getClientIp(),
			'message'			=> $this->input('req_message'),
			'hide_smilies'		=> $this->hasInput('hide_smilies') ? '1' : '0',
			'posted'			=> time(), // TODO: Use SERVER_TIME
			'topic_id'			=> $tid
		);

		if ($this->guest())
		{
			$post_data['poster'] = $this->input('req_username');
			$post_data['poster_email'] = Config::enabled('p_force_guest_email') ? $this->input('req_email') : $this->input('email');
		}

		// Insert the new post
		$post = Post::create($post_data);

		// To subscribe or not to subscribe
		$topic->subscribe($this->hasInput('subscribe'));

		// Update topic
		$topic->num_replies += 1;
		$topic->last_post = time(); // TODO: REQUEST_TIME
		$topic->last_post_id = $post->id;
		$topic->last_poster = $post_data['poster'];
		$topic->save();

		// Update forum (maybe $forum->update_forum() ?)
		$forum = $topic->forum;
		$forum->num_posts += 1;
		$forum->last_post = $topic->last_post;
		$forum->last_post_id = $topic->last_post_id;
		$forum->last_poster = $topic->last_poster;
		$forum->save();

		// TODO: update_search_index();

		// If the posting user is logged in, increment his/her post count
		$user = User::current();
		if ($this->check())
		{
			$user->num_posts += 1;
			$user->last_post = time(); // TODO: Request_time
			$user->save();
			// TODO: Promote this user to a new group if enabled
		}
		else
		{
			// TODO: Session!
			$user->online()->update(array('last_post' => time())); // TODO: REquest_time
		}


		return $this->redirect('post', array($post->id))->with('message', t('post.post_added'));
	}

	public function get_topic($fid)
	{
		$forum = Forum::with(array(
			'perms',
		))
		->where('id', '=', $fid)
		->first();

		if (is_null($forum))
		{
			return $this->show404();
		}

		return $this->view('posting.post')
			->with('forum', $forum)
			->with('action', t('forum.post_topic'));
	}

	public function post_topic($fid)
	{
		$forum = Forum::with(array(
			'perms',
		))
		->where('id', '=', $fid)
		->first();

		if (is_null($forum))
		{
			return $this->show404();
		}

		// TODO: Flood protection
		$rules = array(
			// TODO: censored words, All caps subject
			'req_subject'	=> 'Required|Max:70',
			// TODO: PUN_MAX_POSTSIZE, censor, All caps message
			'req_message'	=> 'Required',
		);
		// TODO: More validation

		if ($this->guest())
		{
			if (Config::enabled('p_force_guest_email') || $this->input('email') != '')
			{
				$rules['req_email']	= 'required|email';
			}

			// TODO: banned email
		}

		$validation = $this->validator($this->input(), $rules);
		if ($validation->fails())
		{
			return $this->redirect('new_topic', array($fid))->withInput()->withErrors($validation);
		}

		$topic_data = array(
			'poster'			=> User::current()->username,
			'subject'			=> $this->input('req_subject'),
			'posted'			=> time(),
			'last_post'			=> time(), // TODO: Use REQUEST_TIME!
			'last_poster'		=> User::current()->username,
			'sticky'			=> $this->hasInput('stick_topic') ? '1' : '0',
			'forum_id'			=> $fid,
		);

		if ($this->guest())
		{
			$topic_data['poster'] = $topic_data['last_poster'] = $this->input('req_username');
		}

		// Create the topic
		$topic = Topic::create($topic_data);

		// To subscribe or not to subscribe
		$topic->subscribe($this->input('subscribe'));

		$post_data = array(
			'poster'			=> User::current()->username,
			'poster_id'			=> User::current()->id,
			'poster_ip'			=> '127.0.0.1', // TODO: Get IP from request
			'message'			=> $this->input('req_message'),
			'hide_smilies'		=> $this->hasInput('hide_smilies') ? '1' : '0',
			'posted'			=> time(), // TODO: Use REQUEST_TIME
			'topic_id'			=> $topic->id
		);

		if (\FluxBB\Auth::guest())
		{
			$post_data['poster'] = $this->input('req_username');
			$post_data['poster_email'] = Config::enabled('p_force_guest_email') ? $this->input('req_email') : $this->input('email');
		}

		// Create the post ("topic post")
		$post = Post::create($post_data);

		// Update the topic with last_post_id
		$topic->last_post_id = $topic->first_post_id = $post->id;
		$topic->save();

		// Update forum (maybe $forum->update_forum() ?)
		$forum->num_posts += 1;
		$forum->num_topics += 1;
		$forum->last_post = $topic->last_post;
		$forum->last_post_id = $topic->last_post_id;
		$forum->last_poster = $topic->last_poster;
		$forum->save();

		// TODO: update_search_index();
		$user = User::current();

		// If the posting user is logged in, increment his/her post count
		if ($this->check())
		{
			$user->num_posts += 1;
			$user->last_post = time(); // TODO: Use request time
			$user->save();
			// TODO: Promote this user to a new group if enabled
		}
		else
		{
			// TODO: Session!
			//$this->session->put('last_post', time()); // TODO: Use Request time
		}

		return $this->redirect('viewtopic', $topic)->with('message', t('topic.topic_added'));
	}
}

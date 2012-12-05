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

namespace FluxBB\Session;

use FluxBB\Auth,
	FluxBB\Models\Config,
	FluxBB\Models\User,
	Illuminate\Http\Request,
	Illuminate\Session\DatabaseStore,
	Illuminate\Session\Sweeper,
	Symfony\Component\HttpFoundation\Response;

class Store extends DatabaseStore implements Sweeper
{

	/**
	 * The request instance.
	 *
	 * @var Illuminate\Http\Request
	 */
	protected $request;

	/**
	 * Set the request instance.
	 *
	 * @param  Illuminate\Http\Request  $request
	 * @return void
	 */
	public function setRequest(Request $request)
	{
		$this->request = $request;
	}

	/**
	 * Create a new session in storage.
	 *
	 * @param  string  $id
	 * @param  array   $session
	 * @param  Symfony\Component\HttpFoundation\Response  $response
	 * @return void
	 */
	public function createSession($id, array $session, Response $response)
	{
		$data = array(
			'id'			=> $id,
			'user_id'		=> 1, // TODO: Huh?
			'created'		=> $this->request->server('REQUEST_TIME', time()),
			'last_visit'	=> $session['last_activity'],
			'last_ip'		=> $this->request->getClientIp(),
			'payload'		=> $this->encrypter->encrypt($session),
		);

		$this->table()->insert($data);
	}

	/**
	 * Update an existing session in storage.
	 *
	 * @param  string  $id
	 * @param  array   $session
	 * @param  Symfony\Component\HttpFoundation\Response  $response
	 * @return void
	 */
	public function updateSession($id, array $session, Response $response)
	{
		$data = array(
			'last_visit'	=> $session['last_activity'],
			'last_ip'		=> $this->request->getClientIp(),
			'payload'		=> $this->encrypter->encrypt($session),
		);

		$this->table()->where('id', '=', $id)->update($data);
	}

	/**
	 * Remove session records older than a given expiration.
	 *
	 * @param  int   $expiration
	 * @return void
	 */
	public function sweep($expiration)
	{
		$expiration = $this->request->server('REQUEST_TIME', time()) - Config::get('o_timeout_online');
		
		// Fetch all sessions that are older than o_timeout_online
		$result = $this->table()->where('user_id', '!=', 1)->where('last_visit', '<', $expiration)->get();

		$deleteIds = array();
		foreach ($result as $curSession)
		{
			$deleteIds[] = $curSession->id;
			$result = $this->connection->table('users')->where('id', '=', $curSession->user_id)->update(array('last_visit' => $curSession->last_visit));
		}

		// Make sure logged-in users have no more than ten sessions alive
		if (Auth::check())
		{
			$uid = User::current()->id;

			$sessionIds = $this->table()->where('user_id', '=', $uid)->orderBy('last_visit', 'desc')->lists('id');
			$pruneIds = array_slice($sessionIds, 10);

			$deleteIds = array_merge($deleteIds, $pruneIds);
		}

		if (!empty($deleteIds))
		{
			$this->table()->whereIn('id', $deleteIds)->orWhere('last_visit', '<', $expiration)->delete();
		}
	}

	/**
	 * Delete a session from storage by a given ID.
	 *
	 * @param  string  $id
	 * @return void
	 */
	public function delete($id)
	{
		$this->table()->delete($id);
	}

	/**
	 * Create a fresh session payload.
	 *
	 * @return array
	 */
	protected function createFreshSession()
	{
		// Fetch a guest session with the same IP address
		$oldGuestSession = $this->table()
			->where('user_id', '=', 1)
			->where('last_ip', '=', $this->request->getClientIp())
			->first();

		// We will simply generate an empty session payload array, using an ID
		// that is either not currently assigned to any existing session or
		// that belongs to a guest with the same IP address.
		if (is_null($oldGuestSession))
		{
			$id = $this->createSessionID();
		}
		else
		{
			$id = $oldGuestSession->id;
			$this->exists = true;
		}

		$flash = $this->createData();

		return array('id' => $id, 'data' => $flash);
	}
	
}
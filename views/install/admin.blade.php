@extends('install.layout.main')

@section('main')

	<h2>Admin User</h2>

	<form method="POST">
		<label>Username</label>
		<input type="text" name="username" />
		<br>
		<label>Email</label>
		<input type="text" name="email" />
		<br>
		<label>Password</label>
		<input type="password" name="password" />
		<input type="password" name="password_confirmation" />
		<br><br>
		<input type="submit" value="Weiter" />
	</form>

@stop

@extends('install.layout.main')

@section('main')

	<h2>Database Configuration</h2>

	<form method="POST">
		<label>Host</label>
		<input type="text" name="db_host" />
		<br>
		<label>Name</label>
		<input type="text" name="db_name" />
		<br>
		<label>User</label>
		<input type="text" name="db_user" />
		<br>
		<label>Password</label>
		<input type="password" name="db_pass" />
		<br><br>
		<input type="submit" value="Weiter" />
	</form>

@stop

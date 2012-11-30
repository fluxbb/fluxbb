@extends('install.layout.main')

@section('main')

	<h2>Board Configuration</h2>

	<form method="POST">
		<label>Board name</label>
		<input type="text" name="title" />
		<br>
		<label>Description</label>
		<input type="text" name="description" />
		<br><br>
		<input type="submit" value="Weiter" />
	</form>

@stop

@extends('install.layout.main')

@section('main')

	<p>This installer is going to guide you through the process.</p>
	<form method="POST">
		<label for="language">Installation language</label>
		<select name="language" id="language">
			<option value="en">English</option>
		</select>
		<input type="submit" name="submit" value="Start!" />
	</form>

@stop

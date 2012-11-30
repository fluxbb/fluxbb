@extends('install.layout.main')

@section('main')

	<h2>Installation successful</h2>
	<h3>Script output:</h3>
	<pre>{{ htmlspecialchars($output) }}</pre>

@stop

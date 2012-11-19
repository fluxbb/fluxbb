@extends('layout.main')

@section('main')
<div id="rules">
	<h2>{{ trans('register.forum_rules') }}</h2>
	<div class="usercontent">{{ $rules }}</div>
</div>
@stop
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<body>

<div id="brdheader">
	<h1><a href="{{ route('index') }}">Board title</a></h1>
	<div id="brddesc">Board description</div>
	<div id="brdmenu">
		<ul>
			<!-- TODO: Class isactive -->
			<li id="navindex" class="isactive"><a href="{{ route('index') }}">{{ t('common.index') }}</a></li>
@if (FluxBB\Models\User::current()->group->g_read_board == '1' && FluxBB\Models\User::current()->group->g_view_users == '1')
			<li id="navuserlist"><a href="{{ route('userlist') }}">{{ t('common.user_list') }}</a></li>
@endif
@if (FluxBB\Models\Config::enabled('o_rules') && (FluxBB\Auth::check() || FluxBB\Models\User::current()->group->g_read_board == '1' || FluxBB\Models\Config::enabled('o_regs_allow')))
			<li id="navrules"><a href="{{ route('rules') }}">{{ t('common.rules') }}</a></li>
@endif
@if (FluxBB\Models\User::current()->group->g_read_board == '1' && FluxBB\Models\User::current()->group->g_search == '1')
			<li id="navsearch"><a href="{{ route('search') }}">{{ t('common.search') }}</a></li>
@endif
@if (FluxBB\Auth::guest())
			<li id="navregister"><a href="{{ route('register') }}">{{ t('common.register') }}</a></li>
			<li id="navlogin"><a href="{{ route('login') }}">{{ t('common.login') }}</a></li>
@else
			<li id="navprofile"><a href="{{ route('profile', FluxBB\Auth::user()) }}">{{ t('common.profile') }}</a></li>
	@if (FluxBB\Models\User::current()->isAdmin())
			<li id="navadmin"><a href="{{ route('admin') }}">{{ t('common.admin') }}</a></li>
	@endif
			<li id="navlogout"><a href="{{ route('logout') }}">{{ t('common.logout') }}</a></li>
@endif
		</ul>
	</div>
</div>

<div id="brdmain">

@yield('alerts')

@yield('main')

</div>

@include('layout.partials.footer')

</body>
</html>

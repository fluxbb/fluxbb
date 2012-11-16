<!DOCTYPE html>

<html>
<head>
	<title>FluxBB</title>
</head>

<body>

	<h1>Not installed</h1>
	<p>It looks like FluxBB has not been installed yet.</p>
@if ($has_installer)
	<p>Please visit {{ HTML::link_to_action('fluxbb_installer::home@start') }} to install the software.</p>
@else
	<p>As you do not seem to have the graphical installer in your system, you can install FluxBB by running the following commands from the command line:</p>
<pre>
php artisan install:config mysql host dbname user:pass prefix
php artisan --env=fluxbb install:database
php artisan --env=fluxbb install:board "Board name" "Board description"
php artisan --env=fluxbb install:admin username password email
</pre>
@endif

</body>

</html>
<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure we have built in support for MySQL
if (!function_exists('mysql_connect'))
	exit('This PHP environment doesn\'t have MySQL support built in. MySQL support is required if you want to use a MySQL database to run this forum. Consult the PHP documentation for further assistance.');


class DBLayer
{
	var $prefix;
	var $link_id;
	var $query_result;

	var $saved_queries = array();
	var $num_queries = 0;

	var $error_no = false;
	var $error_msg = 'Unknown';

	var $datatype_transformations = array(
		'%^SERIAL$%'	=>	'INT(10) UNSIGNED AUTO_INCREMENT'
	);


	function DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect)
	{
		$this->prefix = $db_prefix;

		if ($p_connect)
			$this->link_id = @mysql_pconnect($db_host, $db_username, $db_password);
		else
			$this->link_id = @mysql_connect($db_host, $db_username, $db_password);

		if ($this->link_id)
		{
			if (!@mysql_select_db($db_name, $this->link_id))
				error('Unable to select database. MySQL reported: '.mysql_error(), __FILE__, __LINE__);
		}
		else
			error('Unable to connect to MySQL server. MySQL reported: '.mysql_error(), __FILE__, __LINE__);

		// Setup the client-server character set (UTF-8)
		if (!defined('FORUM_NO_SET_NAMES'))
			$this->set_names('utf8');

		return $this->link_id;
	}


	function start_transaction()
	{
		return;
	}


	function end_transaction()
	{
		return;
	}


	function query($sql, $unbuffered = false)
	{
		if (defined('PUN_SHOW_QUERIES'))
			$q_start = get_microtime();

		if ($unbuffered)
			$this->query_result = @mysql_unbuffered_query($sql, $this->link_id);
		else
			$this->query_result = @mysql_query($sql, $this->link_id);

		if ($this->query_result)
		{
			if (defined('PUN_SHOW_QUERIES'))
				$this->saved_queries[] = array($sql, sprintf('%.5f', get_microtime() - $q_start));

			++$this->num_queries;

			return $this->query_result;
		}
		else
		{
			if (defined('PUN_SHOW_QUERIES'))
				$this->saved_queries[] = array($sql, 0);

			$this->error_no = @mysql_errno($this->link_id);
			$this->error_msg = @mysql_error($this->link_id);

			return false;
		}
	}


	function result($query_id = 0, $row = 0, $col = 0)
	{
		return ($query_id) ? @mysql_result($query_id, $row, $col) : false;
	}


	function fetch_assoc($query_id = 0)
	{
		return ($query_id) ? @mysql_fetch_assoc($query_id) : false;
	}


	function fetch_row($query_id = 0)
	{
		return ($query_id) ? @mysql_fetch_row($query_id) : false;
	}


	function num_rows($query_id = 0)
	{
		return ($query_id) ? @mysql_num_rows($query_id) : false;
	}


	function affected_rows()
	{
		return ($this->link_id) ? @mysql_affected_rows($this->link_id) : false;
	}


	function insert_id()
	{
		return ($this->link_id) ? @mysql_insert_id($this->link_id) : false;
	}


	function get_num_queries()
	{
		return $this->num_queries;
	}


	function get_saved_queries()
	{
		return $this->saved_queries;
	}


	function free_result($query_id = false)
	{
		return ($query_id) ? @mysql_free_result($query_id) : false;
	}


	function escape($str)
	{
		if (is_array($str))
			return '';
		else if (function_exists('mysql_real_escape_string'))
			return mysql_real_escape_string($str, $this->link_id);
		else
			return mysql_escape_string($str);
	}


	function error()
	{
		$result['error_sql'] = @current(@end($this->saved_queries));
		$result['error_no'] = $this->error_no;
		$result['error_msg'] = $this->error_msg;

		return $result;
	}


	function close()
	{
		if ($this->link_id)
		{
			if ($this->query_result)
				@mysql_free_result($this->query_result);

			return @mysql_close($this->link_id);
		}
		else
			return false;
	}

	function get_names()
	{
		$result = $this->query('SHOW VARIABLES LIKE \'character_set_connection\'');
		return $this->result($result, 0, 1);
	}


	function set_names($names)
	{
		return $this->query('SET NAMES \''.$this->escape($names).'\'');
	}


	function get_version()
	{
		$result = $this->query('SELECT VERSION()');

		return array(
			'name'		=> 'MySQL Standard',
			'version'	=> preg_replace('%^([^-]+).*$%', '\\1', $this->result($result))
		);
	}


	function table_exists($table_name, $no_prefix = false)
	{
		$result = $this->query('SHOW TABLES LIKE \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\'');
		return $this->num_rows($result) > 0;
	}


	function field_exists($table_name, $field_name, $no_prefix = false)
	{
		$result = $this->query('SHOW COLUMNS FROM '.($no_prefix ? '' : $this->prefix).$table_name.' LIKE \''.$this->escape($field_name).'\'');
		return $this->num_rows($result) > 0;
	}


	function index_exists($table_name, $index_name, $no_prefix = false)
	{
		$exists = false;

		$result = $this->query('SHOW INDEX FROM '.($no_prefix ? '' : $this->prefix).$table_name);
		while ($cur_index = $this->fetch_assoc($result))
		{
			if (strtolower($cur_index['Key_name']) == strtolower(($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name))
			{
				$exists = true;
				break;
			}
		}

		return $exists;
	}


	function create_table($table_name, $schema, $no_prefix = false)
	{
		if ($this->table_exists($table_name, $no_prefix))
			return true;

		$query = 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$table_name." (\n";

		// Go through every schema element and add it to the query
		foreach ($schema['FIELDS'] as $field_name => $field_data)
		{
			$field_data['datatype'] = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_data['datatype']);

			$query .= $field_name.' '.$field_data['datatype'];

			if (isset($field_data['collation']))
				$query .= 'CHARACTER SET utf8 COLLATE utf8_'.$field_data['collation'];

			if (!$field_data['allow_null'])
				$query .= ' NOT NULL';

			if (isset($field_data['default']))
				$query .= ' DEFAULT '.$field_data['default'];

			$query .= ",\n";
		}

		// If we have a primary key, add it
		if (isset($schema['PRIMARY KEY']))
			$query .= 'PRIMARY KEY ('.implode(',', $schema['PRIMARY KEY']).'),'."\n";

		// Add unique keys
		if (isset($schema['UNIQUE KEYS']))
		{
			foreach ($schema['UNIQUE KEYS'] as $key_name => $key_fields)
				$query .= 'UNIQUE KEY '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$key_name.'('.implode(',', $key_fields).'),'."\n";
		}

		// Add indexes
		if (isset($schema['INDEXES']))
		{
			foreach ($schema['INDEXES'] as $index_name => $index_fields)
				$query .= 'KEY '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name.'('.implode(',', $index_fields).'),'."\n";
		}

		// We remove the last two characters (a newline and a comma) and add on the ending
		$query = substr($query, 0, strlen($query) - 2)."\n".') ENGINE = '.(isset($schema['ENGINE']) ? $schema['ENGINE'] : 'MyISAM').' CHARACTER SET utf8';

		return $this->query($query) ? true : false;
	}


	function drop_table($table_name, $no_prefix = false)
	{
		if (!$this->table_exists($table_name, $no_prefix))
			return true;

		return $this->query('DROP TABLE '.($no_prefix ? '' : $this->prefix).$table_name) ? true : false;
	}


	function rename_table($old_table, $new_table, $no_prefix = false)
	{
		// If there new table exists and the old one doesn't, then we're happy
		if ($this->table_exists($new_table, $no_prefix) && !$this->table_exists($old_table, $no_prefix))
			return true;

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$old_table.' RENAME TO '.($no_prefix ? '' : $this->prefix).$new_table) ? true : false;
	}


	function add_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		if ($this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);

		if ($default_value !== null && !is_int($default_value) && !is_float($default_value))
			$default_value = '\''.$this->escape($default_value).'\'';

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' ADD '.$field_name.' '.$field_type.($allow_null ? ' ' : ' NOT NULL').($default_value !== null ? ' DEFAULT '.$default_value : ' ').($after_field != null ? ' AFTER '.$after_field : '')) ? true : false;
	}


	function alter_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);

		if ($default_value !== null && !is_int($default_value) && !is_float($default_value))
			$default_value = '\''.$this->escape($default_value).'\'';

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' MODIFY '.$field_name.' '.$field_type.($allow_null ? ' ' : ' NOT NULL').($default_value !== null ? ' DEFAULT '.$default_value : ' ').($after_field != null ? ' AFTER '.$after_field : '')) ? true : false;
	}


	function drop_field($table_name, $field_name, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' DROP '.$field_name) ? true : false;
	}


	function add_index($table_name, $index_name, $index_fields, $unique = false, $no_prefix = false)
	{
		if ($this->index_exists($table_name, $index_name, $no_prefix))
			return true;

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' ADD '.($unique ? 'UNIQUE ' : '').'INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name.' ('.implode(',', $index_fields).')') ? true : false;
	}


	function drop_index($table_name, $index_name, $no_prefix = false)
	{
		if (!$this->index_exists($table_name, $index_name, $no_prefix))
			return true;

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' DROP INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name) ? true : false;
	}

	function truncate_table($table_name, $no_prefix = false)
	{
		return $this->query('TRUNCATE TABLE '.($no_prefix ? '' : $this->prefix).$table_name) ? true : false;
	}
}

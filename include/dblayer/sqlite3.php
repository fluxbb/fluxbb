<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure we have built in support for SQLite3
if (!extension_loaded('pdo_sqlite'))
	exit('This PHP environment doesn\'t have SQLite3 support built in. SQLite3 support is required if you want to use a SQLite3 database to run this forum. Consult the PHP documentation for further assistance.');


class DBLayer
{
	var $prefix;
	var $pdo;
	var $query_result;

	var $saved_queries = array();
	var $num_queries = 0;

	var $error_no = false;
	var $error_msg = 'Unknown';

	var $datatype_transformations = array(
		'%^SERIAL$%'															=>	'INTEGER',
		'%^(TINY|SMALL|MEDIUM|BIG)?INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$%i'	=>	'INTEGER',
		'%^(TINY|MEDIUM|LONG)?TEXT$%i'											=>	'TEXT'
	);


	function DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect)
	{
		// Prepend $db_name with the path to the forum root directory
		$db_name = PUN_ROOT.$db_name;

		$this->prefix = $db_prefix;

		// TODO: p_connect?
		try
		{
			$this->pdo = new PDO('sqlite:'.$db_name);
		}
		catch (PDOException $e)
		{
			error('Unable to open database \''.$db_name.'\'. SQLite3 reported: '.$e->getMessage(), __FILE__, __LINE__);
		}

		return $this->pdo;
	}


	function start_transaction()
	{
		return $this->pdo->beginTransaction();
	}


	function end_transaction()
	{
		if ($this->pdo->commit())
			return true;
		else
		{
			$this->pdo->rollBack();
			return false;
		}
	}


	function query($sql, $unbuffered = false)
	{
		if (defined('PUN_SHOW_QUERIES'))
			$q_start = get_microtime();

		// TODO: What about unbuffered queries?
		$this->query_result = $this->pdo->query($sql);

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

			$error = $this->pdo->errorInfo();
			$this->error_no = $error[1];
			$this->error_msg = $error[2];

			if ($this->pdo->inTransaction())
			{
				$this->pdo->rollBack();
			}

			return false;
		}
	}


	function result($result = 0, $row = 0, $col = 0)
	{
		if ($result)
		{
			if ($row !== 0 && @sqlite_seek($result, $row) === false)
				return false;

			$cur_row = @sqlite_current($result);
			if ($cur_row === false)
				return false;

			return $cur_row[$col];
		}
		else
			return false;
	}


	function fetch_assoc($result = 0)
	{
		return $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
	}


	function fetch_row($result = 0)
	{
		return $result ? $result->fetch(PDO::FETCH_NUM) : false;
	}


	function num_rows($result = 0)
	{
		return $result ? $result->rowCount() : false;
	}


	function affected_rows()
	{
		return $this->query_result->rowCount();
	}


	function insert_id()
	{
		return $this->pdo ? (int) $this->pdo->lastInsertId() : false;
	}


	function get_num_queries()
	{
		return $this->num_queries;
	}


	function get_saved_queries()
	{
		return $this->saved_queries;
	}


	function free_result($result = false)
	{
		return $result ? $result->closeCursor() : true;
	}


	function escape($str)
	{
		return is_array($str) ? '' : substr($this->pdo->quote($str), 1, -1);
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
		if ($this->pdo)
		{
			if ($this->pdo->inTransaction())
			{
				if (defined('PUN_SHOW_QUERIES'))
					$this->saved_queries[] = array('COMMIT', 0);

				$this->pdo->commit();
			}

			$this->pdo = null;
			return true;
		}
		else
			return false;
	}


	function get_names()
	{
		return '';
	}


	function set_names($names)
	{
		return true;
	}


	function get_version()
	{
		$version = SQLite3::version();

		return array(
			'name'		=> 'SQLite',
			'version'	=> $version['versionString']
		);
	}


	function table_exists($table_name, $no_prefix = false)
	{
		$result = $this->query('SELECT 1 FROM sqlite_master WHERE name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\' AND type=\'table\'');
		return $this->num_rows($result) > 0;
	}


	function field_exists($table_name, $field_name, $no_prefix = false)
	{
		$result = $this->query('SELECT sql FROM sqlite_master WHERE name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\' AND type=\'table\'');
		if (!$this->num_rows($result))
			return false;

		return preg_match('%[\r\n]'.preg_quote($field_name, '%').' %', $this->result($result));
	}


	function index_exists($table_name, $index_name, $no_prefix = false)
	{
		$result = $this->query('SELECT 1 FROM sqlite_master WHERE tbl_name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\' AND name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_'.$this->escape($index_name).'\' AND type=\'index\'');
		return $this->num_rows($result) > 0;
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
				$query .= 'UNIQUE ('.implode(',', $key_fields).'),'."\n";
		}

		// We remove the last two characters (a newline and a comma) and add on the ending
		$query = substr($query, 0, strlen($query) - 2)."\n".')';

		$result = $this->query($query) ? true : false;

		// Add indexes
		if (isset($schema['INDEXES']))
		{
			foreach ($schema['INDEXES'] as $index_name => $index_fields)
				$result &= $this->add_index($table_name, $index_name, $index_fields, false, $no_prefix);
		}

		return $result;
	}


	function drop_table($table_name, $no_prefix = false)
	{
		if (!$this->table_exists($table_name, $no_prefix))
			return true;

		return $this->query('DROP TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name)) ? true : false;
	}


	function rename_table($old_table, $new_table, $no_prefix = false)
	{
		// If the old table does not exist
		if (!$this->table_exists($old_table, $no_prefix))
			return false;
		// If the table names are the same
		else if ($old_table == $new_table)
			return true;
		// If the new table already exists
		else if ($this->table_exists($new_table, $no_prefix))
			return false;

		$table = $this->get_table_info($old_table, $no_prefix);

		// Create new table
		$query = str_replace('CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($old_table).' (', 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($new_table).' (', $table['sql']);
		$result = $this->query($query) ? true : false;

		// Recreate indexes
		if (!empty($table['indices']))
		{
			foreach ($table['indices'] as $cur_index)
			{
				$query = str_replace('CREATE INDEX '.($no_prefix ? '' : $this->prefix).$this->escape($old_table), 'CREATE INDEX '.($no_prefix ? '' : $this->prefix).$this->escape($new_table), $cur_index);
				$query = str_replace('ON '.($no_prefix ? '' : $this->prefix).$this->escape($old_table), 'ON '.($no_prefix ? '' : $this->prefix).$this->escape($new_table), $query);
				$result &= $this->query($query) ? true : false;
			}
		}

		// Copy content across
		$result &= $this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($new_table).' SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($old_table)) ? true : false;

		// Drop the old table if the new one exists
		if ($this->table_exists($new_table, $no_prefix))
			$result &= $this->drop_table($old_table, $no_prefix);

		return $result;
	}


	function get_table_info($table_name, $no_prefix = false)
	{
		// Grab table info
		$result = $this->query('SELECT sql FROM sqlite_master WHERE tbl_name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\' ORDER BY type DESC') or error('Unable to fetch table information', __FILE__, __LINE__, $this->error());
		$num_rows = $this->num_rows($result);

		if ($num_rows == 0)
			return;

		$table = array();
		$table['indices'] = array();
		while ($cur_index = $this->fetch_assoc($result))
		{
			if (empty($cur_index['sql']))
				continue;

			if (!isset($table['sql']))
				$table['sql'] = $cur_index['sql'];
			else
				$table['indices'][] = $cur_index['sql'];
		}

		// Work out the columns in the table currently
		$table_lines = explode("\n", $table['sql']);
		$table['columns'] = array();
		foreach ($table_lines as $table_line)
		{
			$table_line = trim($table_line, " \t\n\r,"); // trim spaces, tabs, newlines, and commas
			if (substr($table_line, 0, 12) == 'CREATE TABLE')
				continue;
			else if (substr($table_line, 0, 11) == 'PRIMARY KEY')
				$table['primary_key'] = $table_line;
			else if (substr($table_line, 0, 6) == 'UNIQUE')
				$table['unique'] = $table_line;
			else if (substr($table_line, 0, strpos($table_line, ' ')) != '')
				$table['columns'][substr($table_line, 0, strpos($table_line, ' '))] = trim(substr($table_line, strpos($table_line, ' ')));
		}

		return $table;
	}


	function add_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		if ($this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		$table = $this->get_table_info($table_name, $no_prefix);

		// Create temp table
		$now = time();
		$tmptable = str_replace('CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (', 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' (', $table['sql']);
		$result = $this->query($tmptable) ? true : false;
		$result &= $this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name)) ? true : false;

		// Create new table sql
		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);
		$query = $field_type;

		if (!$allow_null)
			$query .= ' NOT NULL';
		
		if ($default_value === '')
			$default_value = '\'\'';

		if (!is_null($default_value))
			$query .= ' DEFAULT '.$default_value;

		$old_columns = array_keys($table['columns']);

		// Determine the proper offset
		if (!is_null($after_field))
			$offset = array_search($after_field, array_keys($table['columns']), true) + 1;
		else
			$offset = count($table['columns']);

		// Out of bounds checks
		if ($offset > count($table['columns']))
			$offset = count($table['columns']);
		else if ($offset < 0)
			$offset = 0;

		if (!is_null($field_name) && $field_name !== '')
			$table['columns'] = array_merge(array_slice($table['columns'], 0, $offset), array($field_name => $query), array_slice($table['columns'], $offset));

		$new_table = 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (';

		foreach ($table['columns'] as $cur_column => $column_details)
			$new_table .= "\n".$cur_column.' '.$column_details.',';

		if (isset($table['unique']))
			$new_table .= "\n".$table['unique'].',';

		if (isset($table['primary_key']))
			$new_table .= "\n".$table['primary_key'].',';

		$new_table = trim($new_table, ',')."\n".');';

		// Drop old table
		$result &= $this->drop_table($table_name, $no_prefix);

		// Create new table
		$result &= $this->query($new_table) ? true : false;

		// Recreate indexes
		if (!empty($table['indices']))
		{
			foreach ($table['indices'] as $cur_index)
				$result &= $this->query($cur_index) ? true : false;
		}

		// Copy content back
		$result &= $this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' ('.implode(', ', $old_columns).') SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now) ? true : false;

		// Drop temp table
		$result &= $this->drop_table($table_name.'_t'.$now, $no_prefix);

		return $result;
	}


	function alter_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		// Unneeded for SQLite
		return true;
	}


	function drop_field($table_name, $field_name, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		$table = $this->get_table_info($table_name, $no_prefix);

		// Create temp table
		$now = time();
		$tmptable = str_replace('CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (', 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' (', $table['sql']);
		$result = $this->query($tmptable) ? true : false;
		$result &= $this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name)) ? true : false;

		// Work out the columns we need to keep and the sql for the new table
		unset($table['columns'][$field_name]);
		$new_columns = array_keys($table['columns']);

		$new_table = 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (';

		foreach ($table['columns'] as $cur_column => $column_details)
			$new_table .= "\n".$cur_column.' '.$column_details.',';

		if (isset($table['unique']))
			$new_table .= "\n".$table['unique'].',';

		if (isset($table['primary_key']))
			$new_table .= "\n".$table['primary_key'].',';

		$new_table = trim($new_table, ',')."\n".');';

		// Drop old table
		$result &= $this->drop_table($table_name, $no_prefix);

		// Create new table
		$result &= $this->query($new_table) ? true : false;

		// Recreate indexes
		if (!empty($table['indices']))
		{
			foreach ($table['indices'] as $cur_index)
				if (!preg_match('%\('.preg_quote($field_name, '%').'\)%', $cur_index))
					$result &= $this->query($cur_index) ? true : false;
		}

		// Copy content back
		$result &= $this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' SELECT '.implode(', ', $new_columns).' FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now) ? true : false;

		// Drop temp table
		$result &= $this->drop_table($table_name.'_t'.$now, $no_prefix);

		return $result;
	}


	function add_index($table_name, $index_name, $index_fields, $unique = false, $no_prefix = false)
	{
		if ($this->index_exists($table_name, $index_name, $no_prefix))
			return true;

		return $this->query('CREATE '.($unique ? 'UNIQUE ' : '').'INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name.' ON '.($no_prefix ? '' : $this->prefix).$table_name.'('.implode(',', $index_fields).')') ? true : false;
	}


	function drop_index($table_name, $index_name, $no_prefix = false)
	{
		if (!$this->index_exists($table_name, $index_name, $no_prefix))
			return true;

		return $this->query('DROP INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name) ? true : false;
	}

	function truncate_table($table_name, $no_prefix = false)
	{
		return $this->query('DELETE FROM '.($no_prefix ? '' : $this->prefix).$table_name) ? true : false;
	}
}

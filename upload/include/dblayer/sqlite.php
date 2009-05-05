<?php
/***********************************************************************

  Copyright (C) 2002-2005  Rickard Andersson (rickard@punbb.org)

  This file is part of PunBB.

  PunBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  PunBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


// Make sure we have built in support for SQLite
if (!function_exists('sqlite_open'))
	exit('This PHP environment doesn\'t have SQLite support built in. SQLite support is required if you want to use a SQLite database to run this forum. Consult the PHP documentation for further assistance.');


class DBLayer
{
	var $prefix;
	var $link_id;
	var $query_result;
	var $in_transaction = 0;

	var $saved_queries = array();
	var $num_queries = 0;

	var $error_no = false;
	var $error_msg = 'Unknown';

	var $datatype_transformations = array(
		'/^SERIAL$/'															=>	'INTEGER',
		'/^(TINY|SMALL|MEDIUM|BIG)?INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$/i'	=>	'INTEGER',
		'/^(TINY|MEDIUM|LONG)?TEXT$/i'											=>	'TEXT'
	);


	function DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect)
	{
		// Prepend $db_name with the path to the forum root directory
		$db_name = PUN_ROOT.$db_name;

		$this->prefix = $db_prefix;

		if (!file_exists($db_name))
		{
			@touch($db_name);
			@chmod($db_name, 0666);
			if (!file_exists($db_name))
				error('Unable to create new database \''.$db_name.'\'. Permission denied', __FILE__, __LINE__);
		}

		if (!is_readable($db_name))
			error('Unable to open database \''.$db_name.'\' for reading. Permission denied', __FILE__, __LINE__);

		if (!is_writable($db_name))
			error('Unable to open database \''.$db_name.'\' for writing. Permission denied', __FILE__, __LINE__);

		if ($p_connect)
			$this->link_id = @sqlite_popen($db_name, 0666, $sqlite_error);
		else
			$this->link_id = @sqlite_open($db_name, 0666, $sqlite_error);

		if (!$this->link_id)
			error('Unable to open database \''.$db_name.'\'. SQLite reported: '.$sqlite_error, __FILE__, __LINE__);
		else
			return $this->link_id;
	}


	function start_transaction()
	{
		++$this->in_transaction;

		return (@sqlite_query($this->link_id, 'BEGIN')) ? true : false;
	}


	function end_transaction()
	{
		--$this->in_transaction;

		if (@sqlite_query($this->link_id, 'COMMIT'))
			return true;
		else
		{
			@sqlite_query($this->link_id, 'ROLLBACK');
			return false;
		}
	}


	function query($sql, $unbuffered = false)
	{
		if (strlen($sql) > 140000)
			exit('Insane query. Aborting.');

		if (defined('PUN_SHOW_QUERIES'))
			$q_start = get_microtime();

		if ($unbuffered)
			$this->query_result = @sqlite_unbuffered_query($this->link_id, $sql);
		else
			$this->query_result = @sqlite_query($this->link_id, $sql);

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

			$this->error_no = @sqlite_last_error($this->link_id);
			$this->error_msg = @sqlite_error_string($this->error_no);

			if ($this->in_transaction)
				@sqlite_query($this->link_id, 'ROLLBACK');

			--$this->in_transaction;

			return false;
		}
	}


	function result($query_id = 0, $row = 0, $col = 0)
	{
		if ($query_id)
		{
			if ($row != 0)
				@sqlite_seek($query_id, $row);

			$cur_row = @sqlite_current($query_id);
			return $cur_row[$col];
		}
		else
			return false;
	}


	function fetch_assoc($query_id = 0)
	{
		if ($query_id)
		{
			$cur_row = @sqlite_fetch_array($query_id, SQLITE_ASSOC);
			if ($cur_row)
			{
				// Horrible hack to get rid of table names and table aliases from the array keys
				while (list($key, $value) = @each($cur_row))
				{
				    $dot_spot = strpos($key, '.');
				    if ($dot_spot !== false)
				    {
				        unset($cur_row[$key]);
				        $key = substr($key, $dot_spot+1);
				        $cur_row[$key] = $value;
				    }
				}
			}

			return $cur_row;
		}
		else
			return false;
	}


	function fetch_row($query_id = 0)
	{
		return ($query_id) ? @sqlite_fetch_array($query_id, SQLITE_NUM) : false;
	}


	function num_rows($query_id = 0)
	{
		return ($query_id) ? @sqlite_num_rows($query_id) : false;
	}


	function affected_rows()
	{
		return ($this->query_result) ? @sqlite_changes($this->query_result) : false;
	}


	function insert_id()
	{
		return ($this->link_id) ? @sqlite_last_insert_rowid($this->link_id) : false;
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
		return true;
	}


	function escape($str)
	{
		return is_array($str) ? '' : sqlite_escape_string($str);
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
			if ($this->in_transaction)
			{
				if (defined('PUN_SHOW_QUERIES'))
					$this->saved_queries[] = array('COMMIT', 0);

				@sqlite_query($this->link_id, 'COMMIT');
			}

			return @sqlite_close($this->link_id);
		}
		else
			return false;
	}

	function set_names($names)
	{
		return;
	}


	function get_version()
	{
		return array(
			'name'		=> 'SQLite',
			'version'	=> sqlite_libversion()
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

		return preg_match('/[\r\n]'.preg_quote($field_name).' /', $this->result($result));
	}


	function index_exists($table_name, $index_name, $no_prefix = false)
	{
		$result = $this->query('SELECT 1 FROM sqlite_master WHERE tbl_name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\' AND name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_'.$this->escape($index_name).'\' AND type=\'index\'');
		return $this->num_rows($result) > 0;
	}


	function create_table($table_name, $schema, $no_prefix = false)
	{
		if ($this->table_exists($table_name, $no_prefix))
			return;

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

		$this->query($query) or error('Unable to create table '.($no_prefix ? '' : $this->prefix).$table_name, __FILE__, __LINE__, $this->error());

		// Add indexes
		if (isset($schema['INDEXES']))
		{
			foreach ($schema['INDEXES'] as $index_name => $index_fields)
				$this->add_index($table_name, $index_name, $index_fields, false, $no_prefix);
		}
	}


	function drop_table($table_name, $no_prefix = false)
	{
		if (!$this->table_exists($table_name, $no_prefix))
			return;

		$this->query('DROP TABLE '.($no_prefix ? '' : $this->prefix).$table_name) or error('Unable to drop table '.($no_prefix ? '' : $this->prefix).$table_name, __FILE__, __LINE__, $this->error());
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
			$table_line = pun_trim($table_line);
			if (substr($table_line, 0, 12) == 'CREATE TABLE') 
				continue;
			else if (substr($table_line, 0, 11) == 'PRIMARY KEY')
				$table['primary_key'] = $table_line;
			else if (substr($table_line, 0, 6) == 'UNIQUE')
				$table['unique'] = $table_line;
			else if (substr($table_line, 0, strpos($table_line, ' ')) != '')
				$table['columns'][substr($table_line, 0, strpos($table_line, ' '))] = pun_trim(substr($table_line, strpos($table_line, ' ')));
		}

		return $table;
	}


	function add_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = 0, $no_prefix = false)
	{
		if ($this->field_exists($table_name, $field_name, $no_prefix))
			return;

		$table = $this->get_table_info($table_name, $no_prefix);

		// Create temp table
		$now = time();
		$tmptable = str_replace('CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (', 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' (', $table['sql']);
		$this->query($tmptable) or error('Unable to create temporary table', __FILE__, __LINE__, $this->error());
		$this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name)) or error('Unable to insert data into temporary table', __FILE__, __LINE__, $this->error());

		// Create new table sql
		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);
		$query = $field_type;
		if (!$allow_null)
			$query .= ' NOT NULL';
		if ($default_value === null || $default_value === '')
			$default_value = '\'\'';

		$query .= ' DEFAULT '.$default_value;

		$old_columns = array_keys($table['columns']);
		array_insert($table['columns'], $after_field, $query.',', $field_name);

		$new_table = 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (';

		foreach ($table['columns'] as $cur_column => $column_details)
			$new_table .= "\n".$cur_column.' '.$column_details;
			
		if (isset($table['unique']))
			$new_table .= "\n".$table['unique'].',';
			
		if (isset($table['primary_key']))
			$new_table .= "\n".$table['primary_key'];
			
		$new_table = trim($new_table, ',')."\n".');';

		// Drop old table
		$this->drop_table(($no_prefix ? '' : $this->prefix).$this->escape($table_name));

		// Create new table
		$this->query($new_table) or error('Unable to recreate table', __FILE__, __LINE__, $this->error());

		// Recreate indexes
		if (!empty($table['indices']))
		{
			foreach ($table['indices'] as $cur_index)
				$this->query($cur_index) or error('Unable to add index', __FILE__, __LINE__, $this->error());
		}

		// Copy content back
		$this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' ('.implode(', ', $old_columns).') SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now) or error('Unable to insert data into recreated table', __FILE__, __LINE__, $this->error());

		// Drop temp table
		$this->drop_table(($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now);
	}


	function alter_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = 0, $no_prefix = false)
	{
		return;
	}


	function drop_field($table_name, $field_name, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return;

		$table = $this->get_table_info($table_name, $no_prefix);

		// Create temp table
		$now = time();
		$tmptable = str_replace('CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (', 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' (', $table['sql']);
		$this->query($tmptable) or error('Unable to create temporary table', __FILE__, __LINE__, $this->error());
		$this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name)) or error('Unable to insert data into temporary table', __FILE__, __LINE__, $this->error());

		// Work out the columns we need to keep and the sql for the new table
		unset($table['columns'][$field_name]);
		$new_columns = array_keys($table['columns']);

		$new_table = 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (';

		foreach ($table['columns'] as $cur_column => $column_details)
			$new_table .= "\n".$cur_column.' '.$column_details;
			
		if (isset($table['unique']))
			$new_table .= "\n".$table['unique'].',';
			
		if (isset($table['primary_key']))
			$new_table .= "\n".$table['primary_key'];
			
		$new_table = trim($new_table, ',')."\n".');';

		// Drop old table
		$this->drop_table(($no_prefix ? '' : $this->prefix).$this->escape($table_name));

		// Create new table
		$this->query($new_table) or error('Unable to recreate table', __FILE__, __LINE__, $this->error());

		// Recreate indexes
		if (!empty($table['indices']))
		{
			foreach ($table['indices'] as $cur_index)
				$this->query($cur_index) or error('Unable to add index', __FILE__, __LINE__, $this->error());
		}

		// Copy content back
		$this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' SELECT '.implode(', ', $new_columns).' FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now) or error('Unable to reinsert data', __FILE__, __LINE__, $this->error());

		// Drop temp table
		$this->drop_table(($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now);
	}


	function add_index($table_name, $index_name, $index_fields, $unique = false, $no_prefix = false)
	{
		if ($this->index_exists($table_name, $index_name, $no_prefix))
			return;

		$this->query('CREATE '.($unique ? 'UNIQUE ' : '').'INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name.' ON '.($no_prefix ? '' : $this->prefix).$table_name.'('.implode(',', $index_fields).')') or error('Unable to add index', __FILE__, __LINE__, $this->error());
	}


	function drop_index($table_name, $index_name, $no_prefix = false)
	{
		if (!$this->index_exists($table_name, $index_name, $no_prefix))
			return;

		$this->query('DROP INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name) or error('Unable to drop index', __FILE__, __LINE__, $this->error());
	}
}

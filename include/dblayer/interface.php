<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

interface DBLayer
{
	public function start_transaction();

	public function end_transaction();

	public function query($sql, $unbuffered = false);

	public function result($query_id = 0, $row = 0, $col = 0);

	public function fetch_assoc($query_id = 0);

	public function fetch_row($query_id = 0);

	public function has_rows($query_id);

	public function affected_rows();

	public function insert_id();

	public function get_num_queries();

	public function get_saved_queries();

	public function free_result($query_id = false);

	public function escape($str);

	public function error();

	public function close();

	public function get_names();

	public function set_names($names);

	public function get_version();

	public function table_exists($table_name, $no_prefix = false);

	public function field_exists($table_name, $field_name, $no_prefix = false);

	public function index_exists($table_name, $index_name, $no_prefix = false);

	public function create_table($table_name, $schema, $no_prefix = false);

	public function drop_table($table_name, $no_prefix = false);

	public function rename_table($old_table, $new_table, $no_prefix = false);

	public function add_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false);

	public function alter_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false);

	public function drop_field($table_name, $field_name, $no_prefix = false);

	public function add_index($table_name, $index_name, $index_fields, $unique = false, $no_prefix = false);

	public function drop_index($table_name, $index_name, $no_prefix = false);

	public function truncate_table($table_name, $no_prefix = false);
}

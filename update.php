<?php

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';

$query = $db->createTable('forums_track');
$query->field('user_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
$query->field('forum_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
$query->field('mark_time', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');

$query->index('PRIMARY', array('user_id', 'forum_id'));
$query->run();

unset ($query);

$query = $db->createTable('topics_track');
$query->field('user_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
$query->field('topic_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
$query->field('forum_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
$query->field('mark_time', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');

$query->index('PRIMARY', array('user_id', 'topic_id'));
$query->index('forum_id_idx', array('forum_id'));
$query->run();

unset ($query);

$query = $db->addField('users');
$query->field('last_mark', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
$query->run();

unset ($query);
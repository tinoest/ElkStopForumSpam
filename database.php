<?php

$db 		= database();
$db_table 	= db_table();

$db_table->db_add_column(
	'{db_prefix}members',
	array (
		'name' => 'is_spammer',
		'type' => 'TINYINT',
		'size' => '3',
		'null' => '',
		'default' => '0',
		'auto' => ''
	),
	'',
	''
);


?>

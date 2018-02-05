<?php
include_once 'include/db_object.class.php';
class Note_Comment extends DB_Object
{
	protected $_load_permission_level = PERM_VIEWNOTE;
	protected $_save_permission_level = PERM_EDITNOTE;

	protected static function _getFields()
	{
		return Array(
				'noteid'	=> Array(
								'type'			=> 'int',
								'references'	=> 'abstract_note',
								'editable'		=> false,
								'label'			=> 'Response to',
							   ),
				'creator'		=> Array(
									'type'			=> 'int',
									'editable'		=> false,
									'references'	=> 'person',
								   ),
				'created'		=> Array(
									'type'			=> 'datetime',
									'readonly'		=> true,
								   ),
				'contents'		=> Array(
									'type'		=> 'text',
									'width'		=> 50,
									'height'	=> 5,
									'initial_cap'	=> true,
									'label'		=> '',
									'class' => 'initial-focus'
								   ),
			   );
	}

	function getInitSQL($table_name=NULL)
	{
		return "
			CREATE TABLE `note_comment` (
			  `id` int(11) NOT NULL auto_increment,
			  `noteid` int(11) NOT NULL default '0',
			  `creator` int(11) NOT NULL default '0',
			  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
			  `contents` text NOT NULL,
			  PRIMARY KEY  (`id`)
			) ENGINE=InnoDB ;
		";
	}

	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] = '('.$res['from'].') LEFT OUTER JOIN person creator ON note_comment.creator = creator.id';
		$res['select'][] = 'creator.first_name as creator_fn';
		$res['select'][] = 'creator.last_name as creator_ln';
		return $res;

	}

}
?>

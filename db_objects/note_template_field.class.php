<?php
include_once 'include/db_object.class.php';
class Note_Template_Field extends db_object
{
	var $_save_permission_level = PERM_SYSADMIN;

	public function __construct($id=NULL) {
		parent::__construct($id);
	}

	function _getFields()
	{
		return Array(
			'templateid'	=> Array(
							'type'		=> 'reference',
							'references' => 'field_template',
							'allow_empty'	=> false,
					   ),
			'rank'	=> Array(
							'type'			=> 'int',
							'editable'		=> true,
							'allow_empty'	=> false,
						),
			'customfieldid'	=> Array(
							'type'		=> 'reference',
							'references' => 'custom_field',
							'allow_empty'	=> true,
					   ),
			// You specify EITHER a customfieldid above, OR the three fields below
			'label'	=> Array(
							'type'		=> 'text',
							'allow_empty'	=> true,
							'attrs' => Array('placeholder' => 'Enter name'),
						   ),
			'type'	=> Array(
							'type'		=> 'select',
							'options' => Array(
								'text' => 'Text',
								'date' => 'Date',
								'select' => 'Selection',
							),
							'attrs' => Array(
										'data-toggle' => 'visible',
										'data-target' => 'row .indepfield-options',
										'data-match-attr' => 'data-indeptype',
										),
							),
			'params'	=> Array(
							'type'		=> 'serialise',
							'allow_empty'	=> true,
							'default'	=> Array(),
						   ),
		);
	}

	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] .= "\n LEFT JOIN custom_field cf ON cf.id = note_template_field.customfieldid \n";
		$res['select'][] = 'cf.name as customfieldname';
		$res['select'][] = 'note_template_field.params';
		return $res;
	}

	public function getForeignKeys()
	{
		return Array(
			'templateid'  => 'note_template(id) ON DELETE CASCADE',
			'customfieldid'  => 'custom_field(id) ON DELETE CASCADE',
		);
	}


	function delete()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		parent::delete();

		// delete data here

		$GLOBALS['system']->doTransaction('COMMIT');
	}

	function printFieldInterface($fieldname, $prefix)
	{
		switch ($fieldname) {
			case 'params':
				?>
				<div class="indepfield-options" data-indeptype="select">
					<?php
					Custom_Field::printParamsSelect($prefix, $this->getValue('params'));
					?>
				</div>
				<?php
				break;
			default:
				return parent::printFieldInterface($fieldname, $prefix);
		}
	}

	public function processFieldInterface($fieldname, $prefix)
	{
		switch ($fieldname) {
			case 'params':
				$options = Array();
				$toDelete = array_get($_REQUEST, $prefix.'options_delete', Array());
				foreach ($_REQUEST[$prefix.'option_ids'] as $i => $id) {
					if (!in_array($id, $toDelete)) {
						$o = trim($_REQUEST[$prefix.'option_values'][$i]);
						if ($o !== '') {
							$options[] = $o;
						}
					}
				}
				$this->setValue('params', Array('options' => $options));
				break;
			default:
				return parent::processFieldInterface($fieldname, $prefix);
		}

	}


}
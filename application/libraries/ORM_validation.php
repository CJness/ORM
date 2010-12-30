<?php

class ORM_Validation extends CI_Form_validation {

	
	/**
	 * validate function.
	 * 
	 * @access public
	 * @param object &$object
	 * @param array $rules
	 * @return boolean
	 */
	function validate(&$object, $rules)
	{
		$this->object =& $object;
		$this->set_rules($rules);
		$this->run();
		
		return (count($this->_error_array) == 0);
	}
	
	/**
	 * Set Rules
	 *
	 * This function takes an array of field names and validation
	 * rules as input, validates the info, and stores it
	 *
	 * @access	public
	 * @param	mixed
	 * @param	string
	 * @return	void
	 */
	function set_rules($field, $label = '', $rules = '')
	{
		// If an array was passed via the first parameter instead of indidual string
		// values we cycle through it and recursively call this function.
		if (is_array($field))
		{
			foreach ($field as $row)
			{
				// Houston, we have a problem...
				if ( ! isset($row['field']) OR ! isset($row['rules']))
				{
					continue;
				}

				// If the field label wasn't passed we use the field name
				$label = ( ! isset($row['label'])) ? $row['field'] : $row['label'];

				// Here we go!
				$this->set_rules($row['field'], $label, $row['rules']);
			}
			return;
		}
		
		// No fields? Nothing to do...
		if ( ! is_string($field) OR  ! is_string($rules) OR $field == '')
		{
			return;
		}

		// If the field label wasn't passed we use the field name
		$label = ($label == '') ? $field : $label;

		// Is the field name an array?  We test for the existence of a bracket "[" in
		// the field name to determine this.  If it is an array, we break it apart
		// into its components so that we can fetch the corresponding POST data later		
		$indexes 	= array();
		$is_array	= FALSE;
		
		// Build our master array		
		$this->_field_data[$field] = array(
			'field'				=> $field, 
			'label'				=> $label, 
			'rules'				=> $rules,
			'is_array'			=> $is_array,
			'keys'				=> $indexes,
			'postdata'			=> NULL,
			'error'				=> ''
		);
	}
	
	/**
	 * Run the Validator
	 *
	 * This function does all the work.
	 *
	 * @access	public
	 * @return	bool
	 */		
	function run($group = '')
	{
		// Does the _field_data array containing the validation rules exist?
		// If not, we look to see if they were assigned via a config file
		if (count($this->_field_data) == 0)
		{
			// No validation rules?  We're done...
			if (count($this->_config_rules) == 0)
			{
				return FALSE;
			}
			
			// Is there a validation rule for the particular URI being accessed?
			$uri = ($group == '') ? trim($this->CI->uri->ruri_string(), '/') : $group;
			
			if ($uri != '' AND isset($this->_config_rules[$uri]))
			{
				$this->set_rules($this->_config_rules[$uri]);
			}
			else
			{
				$this->set_rules($this->_config_rules);
			}
	
			// We're we able to set the rules correctly?
			if (count($this->_field_data) == 0)
			{
				log_message('debug', "Unable to find validation rules");
				return FALSE;
			}
		}
	
		// Load the language file containing error messages
		$this->CI->lang->load('form_validation');
							
		// Cycle through the rules for each field, match the 
		// corresponding $data item and test for errors
		foreach ($this->_field_data as $field => $row)
		{		
			// Fetch the data from the corresponding $data array and cache it in the _field_data array.
			// Depending on whether the field name is an array or a string will determine where we get it from.
			
			if (isset($this->object->{$field}) AND $this->object->{$field} != "")
			{
				$this->_field_data[$field]['postdata'] = $this->object->{$field};
			}
		
			$this->_execute($row, explode('|', $row['rules']), $this->_field_data[$field]['postdata']);		
		}

		// Did we end up with any errors?
		$total_errors = count($this->_error_array);

		if ($total_errors > 0)
		{
			$this->_safe_object_data = TRUE;
		}

		// Now we need to re-set the data with the new, processed data
		$this->_reset_data_array();
		
		// No errors, validation passes!
		if ($total_errors == 0)
		{
			return TRUE;
		}

		// Validation fails
		$validation_errors = $this->CI->config->item('orm_validation_errors');
		$validation_errors[ $this->object->table() ] = $this->_error_array;
		$this->CI->config->set_item('orm_validation_errors', $validation_errors);
		
		return FALSE;
	}
	
	/**
	 * Re-populate the data array with our finalized and processed data
	 *
	 * @access	private
	 * @return	null
	 */		
	function _reset_data_array()
	{
		foreach ($this->_field_data as $field => $row)
		{
			if ( ! is_null($row['postdata']))
			{
				if ($row['is_array'] == FALSE)
				{
					if (isset($this->object->{$row['field']}))
					{
						$this->object->{$row['field']} = $this->prep_for_form($row['postdata']);
					}
				}
				else
				{
					// start with a reference
					$data_ref =& $object;
					
					// before we assign values, make a reference to the right POST key
					if (count($row['keys']) == 1)
					{
						$data_ref =& $data_ref[current($row['keys'])];
					}
					else
					{
						foreach ($row['keys'] as $val)
						{
							$data_ref =& $data_ref[$val];
						}
					}

					if (is_array($row['postdata']))
					{
						$array = array();
						foreach ($row['postdata'] as $k => $v)
						{
							$array[$k] = $this->prep_for_form($v);
						}

						$data_ref = $array;
					}
					else
					{
						$data_ref = $this->prep_for_form($row['postdata']);
					}
				}
			}
		}
	}
	
	/**
	 * Match one field to another
	 *
	 * @access	public
	 * @param	string
	 * @param	field
	 * @return	bool
	 */
	function matches($str, $field)
	{
		if ( ! isset($this->object->{$field}))
		{
			return FALSE;				
		}

		return ($str !== $this->object->{$field});
	}
	
	/**
	 * Checks if given value is unique in database
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function unique($value) 
	{
		$field = array_search($value, get_object_vars($this->object));
		
		$where = array();
		$where[ $field.' !='] = $value;
		
		if ($this->object->exists())
		{
			$where['id !='] = $this->object->id;
		}
		
		return ($this->object->count($where) === '0');
	}

}
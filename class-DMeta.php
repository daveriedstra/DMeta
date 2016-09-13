<?php

/**
* Meta Manager
*
* Manages all meta for a theme
*
* - singleton
* - register meta options before or in add_meta_boxes hook with register_meta
*		pass the name for a queue (a group of meta values to be rendered saved)
* - pass render_queue a post ID and queue name to render the inputs (in order)
* - pass save_queue a post ID and queue name to save the queue
*/
class DMeta extends DSingleton {
	private static $_queues = array();

	/**
	*		Types supported by ::sanitize_value
	*/
	const DATA_TYPES = array(
		'STRING' => 'string',
		'RICH_TEXT' => 'rich_text',
		'INT' => 'int',
		'FLOAT' => 'float',
		'BOOLEAN' => 'bool'
	);
	
	/**
	*	Register a meta value to the queue
	*
	*/
	public function register_meta($args, $queue_name = '') {
		if (!isset($args['name']) || $queue_name === '') {
			error_log('Attempted to register meta to queue "' . $queue_name . '" with missing data. $args: ' . json_encode($args));
			return;
		}

		$args['storage_type'] = 'meta';
		if (!array_key_exists('data_type', $args)) {
			$args['data_type'] = self::DATA_TYPES['STRING'];
		}
		if ($args['input_type'] == 'checkbox') {
			$args['data_type'] = self::DATA_TYPES['BOOLEAN'];
		}

		self::ensure_queue_exists($queue_name);

		// append this to the meta queue to be rendered and saved when appropriate
		static::$_queues[$queue_name][] = array(
			'args' => $args
		);
	}

	/**
	*	Render inputs for a queue
	*
	*/
	public function render_queue($post_id, $queue_name = '') {
		if (!self::queue_exists($queue_name) || !isset($post_id)) return;

		$len = count(static::$_queues[$queue_name]);

		for ($i = 0; $i < $len; $i++) {
			$args = static::$_queues[$queue_name][$i]['args'];
			$args['post_id'] = $post_id;

			self::render_meta_input($args);
		}
	}

	/**
	*	Save a queue
	*
	*	@param int $post_id
	*	@param string $queue_name
	*/
	public function save_queue($post_id, $queue_name = '') {
		if (!self::queue_exists($queue_name) || !isset($post_id)) {
			error_log('cannot save queue "' . $queue_name .'" for post ' . $post_id);
			return;
		}

		foreach (static::$_queues[$queue_name] as $item) {
			$new_val = self::get_val_from_post($item['args']['name'], $item['args']['data_type']);

			// call before_save if set
			if (array_key_exists('before_save', $item['args']) && is_callable($item['args']['before_save']))
				$item['args']['before_save']($post_id, $new_val);

			update_post_meta($post_id, $item['args']['name'], $new_val);

			// call after_save if set
			if (array_key_exists('after_save', $item['args']) && is_callable($item['args']['after_save']))
				$item['args']['after_save']($post_id, $new_val);
		}
	}

	/**
	*	Does a queue exist? Is it an array?
	*	
	*/
	private function queue_exists($queue_name) {
		return array_key_exists($queue_name, static::$_queues);
	}

	/**
	*	Ensure a queue exists
	*
	*/
	private function ensure_queue_exists($queue_name) {
		if (!self::queue_exists($queue_name))
			static::$_queues[$queue_name] = array();
	}

	/**
	*	Echoes the appropriate markup for a post meta input
	*
	*	@param mixed[] $args Array of arguments describing the input, passed directly to input rendering elements. Required attributes:
	*		- post_id: the ID of the post
	*		- input_type: the type of input element required. May be any type which is valid for an <input>, or select, img, or rich_text.
	*		- name: name of the value. Used in storage and as DOM element's name attribute
	*
	*/
	private function render_meta_input($args) {
		// enqure required properties exist
		$required = array('post_id', 'input_type', 'name');
		foreach ($required as $key) {
			if (!array_key_exists($key, $args)) {
				error_log('DMeta->render_meta_input missing required key ' . $key);
				return;
			}
		}

		// determine the required input type and render the appropriate markup
		switch ($args['input_type']) {
			case 'checkbox':
				echo '<div class="dried-input dried-input--checkbox"><label class="dried-input__label">';
				self::render_input($args);
				echo ' ' . $args['label'] . '</label>';
				self::maybe_render_description($args);
				echo '</div>';
				break;

			case 'radio':
				echo '<div class="dried-input dried-input--radio"><label class="dried-input__label" for="' . $args['name'] . '">' . $args['label'] . '</label>';
				self::render_radios($args);
				self::maybe_render_description($args);
				echo '</div>';
				break;
			
			case 'select':
				echo '<div class="dried-input dried-input--select"><label class="dried-input__label" for="' . $args['name'] . '">' . $args['label'] . '</label>';
				self::render_select($args);
				self::maybe_render_description($args);
				echo '</div>';
				break;
			
			case 'img':
				echo '<div class="dried-input dried-input--img-input"><label class="dried-input__label" for="' . $args['name'] . '">' . $args['label'] . '</label>';
				self::render_img_picker($args);
				self::maybe_render_description($args);
				echo '</div>';
				break;
			
			case 'rich_text':
				self::render_rich_text_editor($args);
				break;
			
			default:
				echo '<div class="dried-input dried-input--' . $args['input_type'] . '"><label class="dried-input__label" for="' . $args['name'] . '">' . $args['label'] . '</label>';
				self::render_input($args);
				self::maybe_render_description($args);
				echo '</div>';
				break;
		}
	}	

	/**
	*		Renders a description paragraph if needed.
	*	
	*		@param mixed[] $args the meta's args array
	*/
	private static function maybe_render_description($args) {
		if (isset($args['description']) && strlen($args['description']) > 0) {
			echo '<p class="dried-input__description">' . $args['description'] . '</p>';
		}
	}


	/*
	**
	**	suite of input element rendering functions
	**	all prefill values with appropriate theme option or post meta
	**
	*/

	/**
	*		Renders an <input>
	*	
	*		@param mixed[] $args Array of arguments. 
	*		- post_id (int)
	*		- name (string)
	*		-	input_type (string)
	*	 	- storage_type (string) 'option' | 'meta'
	*		-	other attributes appropriate to input_type (eg, precision for number)
	*/
	public static function render_input($args) {
		$value = self::get_value($args);
		echo '<input type="' . $args['input_type'] . '"';

		// options for various input types
		if ($args['input_type'] == 'number') {
			echo ' step="' . $args['step'] . '"';
			if (isset($args['precision'])) {
				$value = number_format(floatval($value), $args['precision']);
			}
		} else if ($args['input_type'] == 'checkbox') {
			if ($value == 'true') {
				echo 'checked';
			}
			// value of checkbox control is always "true"
			$value = "true";
		} else if ($args['input_type'] == 'text') {
			$value = htmlspecialchars($value);
		}

		// fullwidth
		if (in_array('fullwidth', $args) && $args['fullwidth'] === true) {
			echo ' class="fullwidth"';
		}

		echo ' name="' . $args['name'] . '" value="' . $value . '" />';
	}

	/**
	*		Renders a set of radio inputs
	*
	*		@param mixed[] $args Array of arguments. 
	*		- post_id (int)
	*		- name (string)
	*		- storage_type (string) 'option' | 'meta'
	*		- options (array) ( 'value' => 'name', ... )
	*		- get_options (callable) a callable which returns options. Passed post_id
	*/
	public static function render_radios($args) {
		$cur_value = self::get_value($args);

		if (array_key_exists('get_options', $args) && is_callable($args['get_options']))
			$args['options'] = $args['get_options']($args['post_id']);

		foreach ($args['options'] as $opt_key => $opt_value) {
			echo '<label>' . $opt_value;
			echo ' <input class="dried-radio__radio" type="radio" name="' . $args['name'] . '" value="' . $opt_key . '"';
			if ($cur_value == $opt_key)
				echo ' checked="true" ';
			echo '/>';
			echo '</label>';
		}
	}

	/**
	*		Renders a <select>
	*	
	*		@param mixed[] $args Array of arguments. 
	*		- post_id (int)
	*		- name (string)
	*	 	- storage_type (string) 'option' | 'meta'
	*		-	options (array) ( 'value' => 'name', ... )
	*		- get_options (callable) a callable which returns options. Passed post_id
	*/
	public static function render_select($args) {
		$value = self::get_value($args);

		if (array_key_exists('get_options', $args) && is_callable($args['get_options']))
			$args['options'] = $args['get_options']($args['post_id']);
		
		echo '<select class="dried-select__select" name="' . $args['name'] . '">';
		foreach ($args['options'] as $opt_key => $opt_value) {
			echo '<option value="' . $opt_key . '"';
			if ($value == $opt_key)
				echo ' selected';
			echo ' >' . $opt_value . "</option>";
		}
		echo '</select>';
	}

	/**
	*		Renders the image input. Be sure to enqueue the options.js script!
	*	
	*		@param mixed[] $args Array of arguments. 
	*		- post_id (int)
	*		- name (string)
	*	 	- storage_type (string) 'option' | 'meta'
	*/
	public static function render_img_picker($args) {
		$this_image = self::get_value($args);

		$this_image_src = wp_get_attachment_image_src($this_image, 'medium');
		$this_image_src = $this_image_src[0];

		echo '<span id="' . $this_image . '" class="ft-options-img uploader dried-uploader" style="display:block; width:100%;">
		<img class="dried-img-url" src="' . $this_image_src . '" style="min-height:6em; min-width:6em; max-width: 12em;"/>
		<input type="button" name="' . $args['name'] .'-button" id="' . $args['name'] .'-button" class="button" style="vertical-align:bottom;" value="Choose" />
		<a href="#" class="dried-remove-img">Remove</a>
		<input type="hidden" name="' . $args['name'] . '" value="' . $this_image . '" class="dried-img-id" />
		</span>';
	}

	/**
	*		Renders the rich text editor
	*	
	*		@param mixed[] $args Array of arguments.
	*		- post_id (int)
	*		- name (string)
	*		- storage_type (string) 'option' | 'meta'
	*		- label (string)
	*		- media_buttons (bool)
	*/
	public static function render_rich_text_editor($args) {
		$cur_content = self::get_value($args);
		$media_buttons = isset($args['media_buttons']) ? $args['media_buttons'] : true;

		echo '<div class="dried-input dried-input--rich-text>';

		if (isset($args['label']))
			echo '<label class="dried-input__label" for="' . $args['name'] . '">' . $args['label'] . '</label>';

		wp_editor($cur_content, str_replace('-', '_', $args['name']) . '_ed', array(
			'media_buttons' => $media_buttons,
			'textarea_name' => $args['name']
			)
		);

		echo '</div>';
	}

	/**
	*		Returns the value of an option or a meta
	*
	*		@param mixed[] $args Array of arguments
	*		- post_id (int)
	*		-	name (string)
	*		- storage_type (string) 'option' | 'meta'
	*
	*		@return mixed value of option or meta in question
	*/
	public static function get_value($args) {
		if ($args['storage_type'] == 'option')
			return get_option($args['name']);
		else if ($args['storage_type'] == 'meta')
			return get_post_meta($args['post_id'], $args['name'], true);
	}

	/**
	*		Returns the sanitized value
	*
	*		@param string $value Value to sanitize; assumed to be straight from $_POST
	*		@param string $type Type of value expected
	*
	*		@return mixed The sanitized value
	*/
	public static function get_val_from_post($name, $type = self::DATA_TYPES['STRING']) {
		if ($type == '') {
			$type = self::DATA_TYPES['string'];
		}
		if (!in_array($type, self::DATA_TYPES)) {
			error_log('Attempted to sanitize unknown type: "' . $type . '"');
			return;
		}

		switch ($type) {
			case self::DATA_TYPES['INT']:
				return intval($_POST[$name]);
				break;

			case self::DATA_TYPES['FLOAT']:
				return floatval($_POST[$name]);
				break;

			case self::DATA_TYPES['BOOLEAN']:
				return array_key_exists($name, $_POST) && boolval($_POST[$name]);
				break;

			case self::DATA_TYPES['RICH_TEXT']:
				return apply_filters('the_content', $_POST[$name]);
				break;
			
			case self::DATA_TYPES['STRING']:
				return sanitize_text_field($_POST[$name]);
				break;

			default:
				return sanitize_text_field($_POST[$name]);
				break;
		}
	}
}
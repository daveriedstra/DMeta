# DMeta
### A WordPress meta manager utility

This class manages markup, sanitation, and storage for WordPress post meta. Just register your meta settings and tell it where to put the markup and when to save.

## Usage

DMeta is a singleton. To use it, you'll need to get a reference to its instance by calling `get_instance` like so:
```php
$DM = DMeta::get_instance();
```
For maximum flexibility, DMeta uses *queues* to organize the meta you'll save. This way, a set of meta options can be used across different post types or templates.

#### Registering a meta option

Pass `register_meta` an array describing the option and a string naming which queue. Markup generation and value saving are done in the order in which the meta are registered.

```php
$DM->register_meta(
  array(
    'name' => 'example-meta',
    'label' => 'Example meta input',
    'description' => 'This is an example input.',
    'input_type' => 'select',
    'data_type' => DMeta::DATA_TYPES['INT'],
    'options' => array('1' => 'One', '2' => 'Two')
  ), 
  'my_queue' // name of the meta queue
);
```
outputs
```html
<div class="dried-input dried-select">
  <label class="dried-input__label" for="example-meta">Example meta input</label>
  <select class="dried-select__select" name="example-meta">
    <option value="1">One</option>
    <option value="2">Two</option>
  </select>
  <p class="dried-input__description">This is an example input.</p>
</div>
```

#### Rendering meta inputs

Just call `render_queue` in your `add_meta_box` callback:
```php
function render_meta_box( $post ) {
  wp_nonce_field('render_meta_box', 'section_nonce');

  $DM = DMeta::get_instance();
  $DM->render_queue($post->ID, 'my_queue');
}
```

#### Saving meta inputs
Just as easy. Call `save_queue` in the `save_post` hook after checking the wp nonce:
```php
function save_post_meta ($post_id) {
  if (!can_save_meta($post_id, 'render_meta_box', 'section_nonce'))
    return $post_id;

  $DM = DMeta::get_instance();
  $DM->save_queue($post_id, 'my_queue');
}
add_action('save_post', 'save_post_meta');
```

## Options
This section describes the arguments that can be passed to `register_meta`

- `name` - the name of the meta value and the name attribute of the input element. *Required.*
- `label` - the text inside of the input label
- `description` - text description of the meta option
- `input_type` - the type of input. Can be one of "select", "checkbox", "rich_text", or any type which can be set as an `input` element type. *Required.*
- `data_type` - the type of data. Should be one of `DMeta::DATA_TYPES['STRING']`, `DMeta::DATA_TYPES['RICH_TEXT']`, `DMeta::DATA_TYPES['INT']`, `DMeta::DATA_TYPES['FLOAT']`, or `DMeta::DATA_TYPES['BOOLEAN']`. If not set, `DMeta::DATA_TYPES['STRING']` is assumed. If `input_type` is set to "checkbox", `data_type` will be set to `DMeta::DATA_TYPES['BOOLEAN']`.
- `options` - an array of options for radio inputs or the select element, in the structure of `array('value' => 'label')`.
- `get_options` - a callable which is passed the post ID and which returns the options array for radio or select. If this is set, the given options array will be ignored.
- `before_save` - a callable which is executed before the meta is saved. Passed the post ID and the new value.
- `after_save` - a callable which is executred after the meta is saved. Passed the post ID and the new value.

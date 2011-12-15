<?php
class MetaBox
{
    // Stores all metabox info
    private static $boxs = array();
    
    // List of unique names and ids
    private static $ids = array();
    private static $names = array();
    
    // Used to retrieve all stored metainformation
    private static $meta_name = 'MetaBox';
    
    // The ID of the metabox attached to this instance of the class
    private $box;

    // Temporary option storage. Associative array.
    // All instanced helper methods access this variable.
    private $opts;


    function __construct( $id, $type = 'page', $location = 'side', $priority = 'high' ){
    
        $this->opts = array(
            'title' => $id,
            'type' => $type,
            'location' => $location,
            'priority' => $priority,
            'fields' => array()
        );

        if( !$this->extend($id) )
            $this->opts['id'] = self::slug($id);

        $this->addBox();
    }
    
// OBJECT HELPERS

    // Method used to extend the options array with user
    // defined parameters.
    private function extend( $map ){
    
        // Exit if parameter is a string or nonexistent
        if( is_string( $map ) || !$map )
            return false;
    
        // Overrite the default options
        foreach( $map as $key => $val )
            $this->opts[$key] = $val;
            
        return true;
    }


    // Validate metabox info and add to array
    private function addBox(){
        $this->valid('id');
        $this->unique('id');
    
        $this->box = $this->opts['id'];
    
        self::$boxs[$this->box] = $this->opts;
    }
    
    // Validate field info and add to array
    private function addField(){
        $this->valid('id');
        $this->unique('id');
        $this->valid('name');
        $this->unique('name');
        
        self::$boxs[$this->box]['fields'][] = $this->opts;
    }
    
    // Validate slug and alert user if correction needs to be made
    private function valid( $param ){
        $value = $this->opts[$param];
    
        $slug = self::slug( $value );
        if( $value != $slug )
            die( "'{$value}' contains illegal characters.<br>\nTry '{$slug}' instead." );
    }
    
    // Validate the uniqueness of a name and alert user if a correction needs to be made
    private function unique( $param ){
        eval('$list = self::$'.$param.'s;');
    
        $value = $this->opts[$param];
                
        foreach( $list as $entry )
            if( $value == $entry ) die( "The ID '{$value}' already exists." );
        
        eval('self::$'.$param.'s[] = $value;');
    }
    
// CLASS HELPER FUNCTIONS

    // Creates a valid slug
    private static function slug( $string ){
        return strtolower( preg_replace( '/[^A-Za-z0-9-]+/', '-', $string ) );
    }
    
// CLASS METHODS

    // Jump starts the MetaBox class by adding appropriate actions
    public static function init(){
        foreach( self::$boxs as $id => $box ){
            add_meta_box($box['id'], $box['title'], 'MetaBox::display', $box['type'], $box['location'], $box['priority'], $box['location']);
        }
        add_action('save_post', 'MetaBox::save');
        
        add_action('admin_head', 'MetaBox::loadScripts');
    }
    
    // Method called by Wordpress to display the corresponding metabox
    public static function display( $post = false, $box = false ){
    
        $id = $box['id'];
        $location = $box['args'];
        $fields = self::$boxs[$id]['fields'];
        
        $nonce = wp_create_nonce( basename(__FILE__) );
        
        echo "<input type='hidden' name='{$id}_nonce' value='{$nonce}' />";
        echo "<table class='form-table'>";
        
        $meta = get_post_meta( $post->ID, self::$meta_name, true );
    
        foreach( $fields as $field ){
        
            $value = $meta[ $field['name'] ];
            $call_show_func = 'self::show'.$field['type'];
            
            echo "<tr><th style='padding:10px 0 2px'><label for='{$field['id']}'>{$field['title']}</label><div style='padding: 4px 0 0 8px;font-size:10px'>{$field['desc']}</div></th></tr><tr><td style='padding:0'>";
            call_user_func( $call_show_func, $field, $value );
            echo "</td></tr>";
        }
        
        echo "</table>";
    }
    
    // Method called by Wordpress upon post save
    public static function save( $post_id = false, $post = false ){
    
        // check autosave
        if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
            return $post_id;
            
        // check permissions
        /* ADD CODE HERE */

        $meta = get_post_meta( $post_id, self::$meta_name, true );

        foreach( self::$boxs as $id => $box ){
            if( $_POST['post_type'] == $box['type'] ) {
     
                foreach( $box['fields'] as $field ){
                    $name = $field['name'];
                
                    $old = $meta[$name];
                    $new = $_POST[$name];
                    
                    if( $new && $new != $old )
                        $meta[$name] = $new;
                }
            }
        }
        
        update_post_meta( $post_id, self::$meta_name, $meta );

        return $post_id;
    }
    
    public static function loadScripts(){
    ?>
    <script type='text/javascript'>
        (function($){
            $(function(){
                
                $sortable = $('#sortable');
                
                $('input.metabox-upload').click( function(){
                
                    var id = $(this).data('target');
                    
                    tb_show('', 'media-upload.php?type=image&amp;TB_iframe=1');

                    window.send_to_editor = function(html){
                        var $img = $('img', html),
                            url = $img.attr('src'),
                            $clone = $sortable.find('li').first().clone();
                            
                        $clone.find('img').attr({src: url});
                        $sortable.append( $clone );
                        $clone.find('input').val( url );
                            
                        $(id).val(url);
                        tb_remove();
                    };
                });
                
                $sortable.sortable({
                    placeholder: 'ui-state-highlight',
                    forcePlaceholderSize: true
                });
            });
        })(jQuery);
    </script>
    <style>
        #sortable li {width:100px;float:left;background:#ddd;padding:2px;margin:5px 5px;border:3px solid #ddd}
        #sortable img {width:100%;height:auto}
        .metabox-image-url {width:100px;border:none;margin:0}
        .fix {height:1px;clear:both}
    </style>
    <?php
    }

//
// ADD SUPPORTED FIELDS HERE
//
// ----------------------------

// TEXT FIELD
    
    public function text( $id, $desc = '' ){

        $this->opts = array(
            'type' => 'text',
            'title' => $id,
            'desc' => $desc,
        );

        if( !$this->extend($id) ) {
            $this->opts['id'] = self::slug($id);
            $this->opts['name'] = self::slug($id);
        }

        $this->addField();
    }

    private static function showText( $field, $meta ){
        echo "<input id='{$field['id']}' name='{$field['name']}' type='text' value='{$meta}' style='width:97%' />";
    }
    
// CHECKBOX FIELD
    
    public function checkbox( $id, $desc = '' ){

        $this->opts = array(
            'type' => 'checkbox',
            'title' => $id,
            'desc' => $desc
        );

        if( !$this->extend($id) ) {
            $this->opts['id'] = self::slug($id);
            $this->opts['name'] = self::slug($id);
        }

        $this->addField();
    }

    private static function showCheckbox( $field, $meta ){
    
        $checked = $meta ? "checked" : "";
        echo "<input id='{$field['id']}' name='{$field['name']}' type='checkbox' {$checked}>";
    }
    
// EDITOR FIELD

    public function editor( $id, $desc = '' ){

        $this->opts = array(
            'type' => 'editor',
            'title' => $id,
            'desc' => $desc,
        );

        if( !$this->extend($id) ) {
            $this->opts['id'] = self::slug($id);
            $this->opts['name'] = self::slug($id);
        }

        $this->addField();
    }

    private static function showEditor( $field, $meta ){
        echo "<div style='width:97%;border:1px solid #DFDFDF;background:#fff'><textarea id='{$field['id']}' name='{$field['name']}' class='theEditor' style='width:97%'>{$meta}</textarea></div>";
    }
    
// TEXTAREA FIELD

    public function textarea( $id, $desc = '' ){

        $this->opts = array(
            'type' => 'textarea',
            'title' => $id,
            'desc' => $desc,
        );

        if( !$this->extend($id) ) {
            $this->opts['id'] = self::slug($id);
            $this->opts['name'] = self::slug($id);
        }

        $this->addField();
    }

    private static function showTextarea( $field, $meta ){
        echo "<div style='width:100%'><textarea id='{$field['id']}' name='{$field['name']}' style='width:97%'>{$meta}</textarea></div>";
    }

// UPLOAD FIELD

    public function upload( $id, $desc = '' ){

        $this->opts = array(
            'type' => 'upload',
            'title' => $id,
            'desc' => $desc,
        );

        if( !$this->extend($id) ) {
            $this->opts['id'] = self::slug($id);
            $this->opts['name'] = self::slug($id);
        }

        $this->addField();
    }

    private static function showUpload( $field, $meta ){
        $len = count($meta);
    
        echo "<ul id='sortable'>";
        for( $i = 0; $i < $len; $i++ ){
            if( $i != $len && $meta[$i] == null )
                unset( $meta[$i] );
            else
                echo "<li><img src='{$meta[$i]}'><br><input type='text' name='{$field['name']}[]' class='metabox-image-url' value='{$meta[$i]}'></li>";
        }
        echo "</ul>";
        echo "<div class='fix'></div>";
        echo "<input id='{$field['id']}' name='{$field['name']}[]' style='width:50%;margin-bottom:5px' />";
        echo "<script>document.write(\"<input type='button' class='button-primary metabox-upload' data-target='#{$field['id']}' value='Upload'/>\");</script>";
    } 
    
// RADIO FIELD

    public function radio( $id, $options = array(), $desc = '' ){

        $this->opts = array(
            'type' => 'radio',
            'title' => $id,
            'desc' => $desc,
            'options' => $options
        );

        if( !$this->extend($id) ) {
            $this->opts['id'] = self::slug($id);
            $this->opts['name'] = self::slug($id);
        }

        $this->addField();
    }

    private static function showRadio( $field, $meta ){
    
        echo "<div id='{$field['id']}'>";
        foreach( $field['options'] as $option => $value ){
            $checked = $meta == $value ? 'checked' : '';
            echo "<input type='radio' name='{$field['name']}' value='{$value}' {$checked}>{$option}<br>";
        }
        echo "</div>";
    }

// SELECT FIELD

    public function select( $id, $options = array(), $selected = array(), $desc = '' ){

        $this->opts = array(
            'type' => 'select',
            'title' => $id,
            'desc' => $desc,
            'options' => $options,
            'selected' => $selected,
            'size' => 1,
            'multiple' => count( $selected ),
            'disabled' => array(),
            'tabindex' => false
        );

        if( !$this->extend($id) ) {
            $this->opts['id'] = self::slug($id);
            $this->opts['name'] = self::slug($id);
        }

        $this->addField();
    }

    private static function showSelect( $field, $meta ){
        $multiple = $field['multiple'] ? 'multiple' : '';
    
        echo "<select id='{$field['id']}' name='{$field['name']}' size='{$field['size']}' {$multiple}>";
        foreach( $field['options'] as $option ){
            $selected = $option == $meta ? 'selected' : '';
            foreach( $field['selected'] as $select ){
                if( $select == $option )
                    $selected = 'selected';
            }
            echo "<option {$selected}>{$option}</option>";
        }
        echo "</select>";
    }
     
}
add_action('admin_menu', 'MetaBox::init');

/*
$meep = new MetaBox('Test Box', 'page', 'advanced');
$meep->text('My Text');
$meep->editor('My Editor');
$meep->textarea('My Textarea');
$meep->radio('My Radio', array( 'test' => 'test', 'blah' => 'blah' ) );
$meep->checkbox('My Checkbox');
$meep->select('My Select', array( 'ford', 'toyota', 'chevy' ) );
$meep->upload('My Upload');
*/
?>
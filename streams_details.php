<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PyroCMS Streams Details
 *
 * Library that provides methods to help the PyroCMS developer to create their modules under streams.
 *
 * @author     Lorenzo García <contact@lorenzo-garcia.com>
 * @license    http://creativecommons.org/licenses/by-sa/3.0/ Creative Commons Attribution-ShareAlike 3.0 Unported
 * @link       https://github.com/LorenzoGarcia/pyrocms-streams-details
 */

class Streams_details
{
    public $namespace;

    public function __construct()
    {
        $this->ci =& get_instance();

        $this->ci->load->driver('streams');
        $this->ci->load->model('streams_core/streams_m');
        $this->ci->load->model('streams_core/fields_m');
    }

    /**
     * set_namespace()
     *
     * This library can be loaded many times by some modules and namespace must be updated.
     *
     * @param string $namespace Streams namespace
     */
    public function set_namespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * create_sections()
     *
     * Generate sections to be used in the control panel.
     *
     * @param  array  $sections  Array of sections.
     * @param  array  $shortcuts Array of shortcuts for each section.
     * @return array             Full array of sections and shortcuts.
     */
    public function create_sections($sections, $shortcuts)
    {
        $array = array();

        foreach ($sections as $section)
        {
            if(isset($shortcuts[$section]) and $shortcuts)
                $current_shortcuts = $shortcuts[$section];
            else
                $current_shortcuts = array();

            $array[$section] = array(
                'name' => $this->namespace.':label:'.$section,
                'uri' => 'admin/'.$this->namespace.'/'.$section,
                'shortcuts' => $current_shortcuts,
                );
        }

        return $array;
    }

    /**
     * create_admin_menu()
     *
     * Generate a menu to be added to the admin menu in control panel.
     *
     * @param  array $sections Array of sections.
     * @return array           Array of sections with its link.
     */
    public function create_admin_menu($sections)
    {
        $array = array();
        foreach ($sections as $section)
            $array[$this->lang($section)] = 'admin/'.$this->namespace.'/'.$section;

        return $array;
    }

    /**
     * insert_streams()
     *
     * Adding the streams to the DB
     *
     * @param  array $streams         Array of streams.
     * @param  array $streams_options Streams options like about description, view_options and title_column.
     * @return array                  Array of streams ID.
     */
    public function insert_streams($streams, $streams_options)
    {
        $streams_id = array();
        foreach ($streams as $stream)
        {
            if ( ! $this->ci->streams->streams->add_stream($this->lang($stream), $stream, $this->namespace, $this->namespace.'_', null))
                return false;
            else
                $streams_id[$stream] = $this->ci->streams->streams->get_stream($stream, $this->namespace)->id;

            $this->update_stream_options($stream, $streams_options[$stream]);
        }

        return $streams_id;
    }

    /**
     * update_stream_options()
     *
     * Update the stream options.
     *
     * @param  array $stream_slug    Array of streams.
     * @param  array $stream_options Streams options like about description, view_options and title_column.
     * @return
     */
    public function update_stream_options($stream_slug, $stream_options)
    {
        // Update about, title_column and view options
        $update_data = array(
            'about'        => lang($stream_slug, 'about'),
            'view_options' => $stream_options['view_options'],
            'title_column' => $stream_options['title_column']
            );
        $this->ci->streams->streams->update_stream($stream_slug, $this->namespace, $update_data);
    }

    public function get_stream($stream_slug)
    {
        return $this->ci->streams->streams->get_stream($stream_slug, $this->namespace);
    }

    public function get_streams_id($streams)
    {
        foreach ($streams as $stream_slug)
        {
            $streams_id[$stream_slug] = $this->ci->streams->streams->get_stream($stream_slug, $this->namespace);
            $streams_id[$stream_slug] = $streams_id[$stream_slug]->id;
        }

        return $streams_id;
    }

    /**
     * build_field_template()
     *
     * Create a template for adding fields or adding field assigments
     *
     * @param  string $field_slug       Field slug
     * @param  string $field_assignment Is a field assignment?
     * @return
     */
    public function build_field_template($field_slug, $field_assignment = null)
    {
        if($field_assignment)
            return array('slug' => $field_slug, 'title_column' => false, 'required' => true, 'unique' => false);
        else
            return array('name' => $this->lang($field_slug), 'slug' => $field_slug, 'namespace' => $this->namespace, 'type' => 'text', 'locked' => true);
    }

    /**
     * create_foders()
     *
     * Create a folder with the namespace and then, create more folders inside of it.
     *
     * @param  array $array Array of folders names.
     * @return array        Array of folders ID.
     */
    public function create_folders($array)
    {
        $this->ci->load->library('files/files');
        $this->ci->load->model('files/file_folders_m');

        $folder = Files::search($this->namespace);
        if( ! $folder['status'])
            Files::create_folder($parent = '0', $folder_name = $this->namespace);
        $folders[$this->namespace] = $this->ci->file_folders_m->get_by('name', $this->namespace);

        foreach ($array as $label)
        {
            $folder = Files::search($label);
            if( ! $folder['status'])
                Files::create_folder($parent = $folders[$this->namespace]->id, $folder_name = $label);
            $folders[$label] = $this->ci->file_folders_m->get_by('name', $label);
        }

        return $folders;
    }

    /**
     * insert_fields()
     *
     * Insert fields into the DB.
     *
     * @param  array $fields Array of fields
     * @return
     */
    public function insert_fields($fields)
    {
        foreach($fields AS $key => &$field)
            $field = array_merge($this->build_field_template($key), $field);

        $this->ci->streams->fields->add_fields($fields);
    }

    /**
     * insert_field_assignments()
     *
     * Create all the field assignments.
     *
     * @param  array $streams           Streams.
     * @param  array $fields            Fields.
     * @param  array $field_assignments Field assignments
     * @param  array $instructions      Instructions for each field.
     * @return
     */
    public function insert_field_assignments($streams, $fields, $field_assignments)
    {
        foreach ($streams as $stream)
        {
            $assign_data = array();
            foreach($field_assignments[$stream] as $field_assignment)
            {
                if (!isset($fields[$field_assignment]))
                {
                    $fields[$field_assignment] = get_object_vars($this->ci->fields_m->get_field_by_slug($field_assignment, $this->namespace));

                    $fields[$field_assignment]['name'] = $fields[$field_assignment]['field_name'];
                    unset($fields[$field_assignment]['field_name']);
                    $fields[$field_assignment]['slug'] = $fields[$field_assignment]['field_slug'];
                    unset($fields[$field_assignment]['field_slug']);
                    $fields[$field_assignment]['type'] = $fields[$field_assignment]['field_type'];
                    unset($fields[$field_assignment]['field_type']);
                    $fields[$field_assignment]['extra'] = $fields[$field_assignment]['field_data'];
                    unset($fields[$field_assignment]['field_data']);
                }

                $assign_data[] = array_merge($this->build_field_template($field_assignment, $stream), $fields[$field_assignment]);
            }

            foreach($assign_data as $assign_data_row)
            {
                if(lang_label($this->lang($assign_data_row['type'], 'instruction')))
                    $assign_data_row['instructions'] = $this->lang($assign_data_row['type'], 'instruction');

                if(lang_label($this->lang($assign_data_row['slug'], 'instruction')))
                    $assign_data_row['instructions'] = $this->lang($assign_data_row['slug'], 'instruction');

                $field_slug = $assign_data_row['slug'];
                unset($assign_data_row['name']);
                unset($assign_data_row['slug']);
                unset($assign_data_row['type']);
                unset($assign_data_row['extra']);

                $this->ci->streams->fields->assign_field($this->namespace, $stream, $field_slug, $assign_data_row);
            }
        }
    }

    /**
     * build_choice_field()
     *
     * Create choice fields
     *
     * @param  array   $array         Array of values.
     * @param  string  $label         Field slug.
     * @param  string  $choice_type   Choice type: dropdown|checkboxes|radio.
     * @param  integer $default_value Default value.
     * @return array                  Field data.
     */
    public function build_choice_field($array, $field_slug, $choice_type, $default_value = 0, $locked = true)
    {
        $flag = true;
        $string = '';
        foreach ($array AS $key)
        {
            if($flag)
                $flag = false;
            else
                $string .= "\n";

            $string .= "$key : ".$this->lang($key);
        }

        return array('name' => $this->lang($field_slug), 'slug' => $field_slug, 'type' => 'choice', 'extra' => array('choice_data' => $string, 'choice_type' => $choice_type, 'default_value' => $default_value), 'locked' => $locked);
    }

    /**
     * lang()
     *
     * Format a lang string.
     *
     * @param  string $label Label
     * @param  string $type  Type of string, i.e. label, message, about, instruction, route
     * @return string        Lang string formatted.
     */
    public function lang($label, $type = 'label')
    {
        return 'lang:'.$this->namespace.':'.$type.':'.$label;
    }

    public function delete_streams($streams_slug)
    {
        foreach ($streams_slug as $stream_slug)
        {
            if ($this->ci->streams_m->check_table_exists($stream_slug, $this->namespace.'_') !== false)
                $this->ci->streams->streams->delete_stream($stream_slug, $this->namespace);
        }
    }

    public function delete_fields($fields_slug)
    {
        foreach ($fields_slug as $field_slug)
        {
            $this->ci->streams->fields->delete_field($field_slug, $this->namespace);
        }
    }

}

/* End of file streams_details.php */

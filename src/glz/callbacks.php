<?php

// -------------------------------------------------------------
// replaces the default custom fields under write tab
function glz_custom_fields_replace($event, $step, $data, $rs) {
  global $all_custom_sets, $date_picker;
  // get all custom fields & keep only the ones which are set, filter by step
  $arr_custom_fields = glz_check_custom_set($all_custom_sets, $step);

  // DEBUG
  // dmp($arr_custom_fields);

  $out = ' ';

  if ( is_array($arr_custom_fields) && !empty($arr_custom_fields) ) {
    // get all custom fields values for this article
    $arr_article_customs = glz_custom_fields_MySQL("article_customs", glz_get_article_id(), '', $arr_custom_fields);

    // DEBUG
    // dmp($arr_article_customs);

    if ( is_array($arr_article_customs) )
      extract($arr_article_customs);

    // let's see which custom fields are set
    foreach ( $arr_custom_fields as $custom => $custom_set ) {
      // get all possible/default value(s) for this custom set from custom_fields table
      $arr_custom_field_values = glz_custom_fields_MySQL("values", $custom, '', array('custom_set_name' => $custom_set['name']));

      // DEBUG
      // dmp($arr_custom_field_values);

      //custom_set formatted for id e.g. custom_1_set => custom-1 - don't ask...
      $custom_id = glz_custom_number($custom, "-");
      //custom_set without "_set" e.g. custom_1_set => custom_1
      $custom = glz_custom_number($custom);

      // if current article holds no value for this custom field and we have no default value, make it empty
      $custom_value = (!empty($$custom) ? $$custom : '');
      // DEBUG
      // dmp("custom_value: {$custom_value}");

      // check if there is a default value
      // if there is, strip the { }
      $default_value = glz_clean_default(glz_default_value($arr_custom_field_values));
      // DEBUG
      // dmp("default_value: {$default_value}");

      // now that we've found our default, we need to clean our custom_field values
      if (is_array($arr_custom_field_values))
        array_walk($arr_custom_field_values, "glz_clean_default_array_values");

      // DEBUG
      // dmp($arr_custom_field_values);

      // the way our custom field value is going to look like
      list($custom_set_value, $custom_class) = glz_format_custom_set_by_type($custom, $custom_id, $custom_set['type'], $arr_custom_field_values, $custom_value, $default_value);

      // DEBUG
      // dmp($custom_set_value);

      $out .= n.tag(
          n.tag('<label for="'.$custom_id.'">'.$custom_set["name"].'</label>', 'div',' class="txp-form-field-label"').
          n.tag($custom_set_value, 'div',' class="txp-form-field-value"'),
          'div',
          ' class="txp-form-field '.str_replace('_', '-', $custom_class).' '.glz_idify(str_replace('_', '-', $custom_set["name"])).' '.$custom_id.'"'
      );

    }
  }

  // DEBUG
  // dmp($out);

  // if we're writing textarea custom fields, we need to include the excerpt as well
  if ($step == "body") {
    $out = $data.$out;
  }

  return $out;
}


// -------------------------------------------------------------
// prep custom fields values for db (convert multiple values into a string e.g. multi-selects, checkboxes & radios)
function glz_custom_fields_before_save() {

    // iterate over POST vars
    foreach ($_POST as $key => $value) {
        // extract custom_{} keys with multiple values as arrays
        if ( strstr($key, 'custom_') && is_array($value) ) {
            // convert to delimited string …
            $value = implode($value, '|');
            // and feed back into $_POST
            $_POST[$key] = $value;
        }
    }

    // DEBUG
    // dmp($_POST);
}


// -------------------------------------------------------------
// inject css & js into admin head
function glz_custom_fields_inject_css_js() {
    global $date_picker, $time_picker, $prefs, $use_minified;
    $msg = array();
    // glz_cf stylesheets
    $css = '<link rel="stylesheet" type="text/css" media="all" href="'.$prefs['glz_cf_css_url'].'/glz_custom_fields'.($use_minified ? '.min' : '').'.css">'.n;
    // glz_cf javascript
    $js = '';

    // if a date picker field exists
    if ( $date_picker ) {
        $css .= '<link rel="stylesheet" type="text/css" media="all" href="'.$prefs['datepicker_url'].'/datePicker'.($use_minified ? '.min' : '').'.css" />'.n;
        foreach (array('date'.($use_minified ? '.min' : '').'.js', 'datePicker'.($use_minified ? '.min' : '').'.js') as $file) {
            $js .= '<script src="'.$prefs['datepicker_url']."/".$file.'"></script>'.n;
        }
    $js_datepicker_msg = '<span class="messageflash error" role="alert" aria-live="assertive"><span class="ui-icon ui-icon-alert"></span> <a href="'.$PROTOCOL.ahu.'?event=plugin_prefs.glz_custom_fields">'.gTxt('glz_cf_public_error_datepicker').'</a> <a class="close" role="button" title="Close" href="#close"><span class="ui-icon ui-icon-close">Close</span></a></span>';
    $js .= <<<EOF
<script>
$(function() {
    if ($(".date-picker").length > 0) {
        try {
            Date.firstDayOfWeek = {$prefs['datepicker_first_day']};
            Date.format = '{$prefs['datepicker_format']}';
            Date.fullYearStart = '19';
            $(".date-picker").datePicker({startDate:'{$prefs['datepicker_start_date']}'});
        } catch(err) {
            $('#messagepane').html('{$js_datepicker_msg}');
        }
    }
});
</script>
EOF;
    }

    // if a time picker field exists
    if ( $time_picker ) {
        $css .= '<link rel="stylesheet" type="text/css" media="all" href="'.$prefs['timepicker_url'].'/timePicker'.($use_minified ? '.min' : '').'.css" />'.n;
        $js  .= '<script src="'.$prefs['timepicker_url'].'/timePicker'.($use_minified ? '.min' : '').'.js"></script>'.n;
        $js_timepicker_msg = '<span class="messageflash error" role="alert" aria-live="assertive"><span class="ui-icon ui-icon-alert"></span> <a href="'.$PROTOCOL.ahu.'?event=plugin_prefs.glz_custom_fields">'.gTxt('glz_cf_public_error_timepicker').'</a> <a class="close" role="button" title="Close" href="#close"><span class="ui-icon ui-icon-close">Close</span></a></span>';
        $js  .= <<<EOF
<script>
$(function() {
    if ($(".time-picker").length > 0) {
        try {
            $(".time-picker").timePicker({
                startTime: '{$prefs['timepicker_start_time']}',
                endTime: '{$prefs['timepicker_end_time']}',
                step: {$prefs['timepicker_step']},
                show24Hours: {$prefs['timepicker_show_24']}
            });
        } catch(err) {
            $("#messagepane").html('{$js_timepicker_msg}');
        }
    }
});
</script>
EOF;

    }

    // localisable jquery message strings for prefs pane
    $js_textarea_msg = gTxt('glz_cf_js_textarea_msg');
    $js_script_msg = gTxt('glz_cf_js_script_msg');
    $js_configure_msg = gTxt('glz_cf_js_configure_msg');
    $js  .= <<<EOF
<script>
$(function() {
    var GLZ_CUSTOM_FIELDS;
    if (GLZ_CUSTOM_FIELDS == undefined) {
        GLZ_CUSTOM_FIELDS = {};
        GLZ_CUSTOM_FIELDS.special_custom_types  = ["date-picker", "time-picker"];
        GLZ_CUSTOM_FIELDS.no_value_custom_types = ["text_input", "textarea"];
        GLZ_CUSTOM_FIELDS.messages = {
            'textarea' : "{$js_textarea_msg}",
            'script'   : "{$js_script_msg}",
            'configure': "{$js_configure_msg}"
        }
    }
});
</script>
EOF;
    $js .= '<script src="'.$prefs['glz_cf_js_url'].'/glz_custom_fields'.($use_minified ? '.min' : '').'.js"></script>';

    // displays the notices we have gathered throughout the entire plugin
    if ( count($msg) > 0 ) {
        // let's turn our notices into a string
        $msg = join("<br>", array_unique($msg));

        $js .= '<script>
<!--//--><![CDATA[//><!--
$(document).ready(function() {
    // add our notices
    $("#messagepane").html(\''.$msg.'\');
});
//--><!]]>
</script>';
    }

    echo $js.n.t.
        $css.n.t;
}


// -------------------------------------------------------------
// set up pre-requisite values for glz_custom_fields
function before_glz_custom_fields() {
    // we will be reusing these globals across the whole plugin
    global $all_custom_sets, $prefs, $date_picker, $time_picker;

    // glz_notice collects all plugin notices
    // $msg = array();

    // get all custom field sets from prefs
    $all_custom_sets = glz_custom_fields_MySQL("all");

    // do we have a date-picker or time-picker custom field
    $date_picker = glz_custom_fields_MySQL("custom_set_exists", "date-picker");
    $time_picker = glz_custom_fields_MySQL("custom_set_exists", "time-picker");
}


// -------------------------------------------------------------
// install glz_cf tables and prefs
function glz_custom_fields_install() {
    global $all_custom_sets, $prefs;
    $msg = '';

    // change 'html' key of default custom fields from 'custom_set'
    // to 'text_input' to avoid confusion with glz set_types()
    safe_update('txp_prefs', "html = 'text_input'", "event = 'custom' AND html = 'custom_set'");

    // set plugin preferences
    $plugin_prefs = array(
        'values_ordering'       => 'custom',
        'multiselect_size'      => '5',
        'datepicker_url'        => hu.'plugins/glz_custom_fields/jquery.datePicker',
        'datepicker_format'     => 'dd/mm/yyyy',
        'datepicker_first_day'  => 1,
        'datepicker_start_date' => '01/01/2017',
        'timepicker_url'        => hu.'plugins/glz_custom_fields/jquery.timePicker',
        'timepicker_start_time' => '00:00',
        'timepicker_end_time'   => '23:30',
        'timepicker_step'       => 30,
        'timepicker_show_24'    => true,
        'custom_scripts_path'   => $prefs['path_to_site'].'/plugins/glz_custom_fields',
        'glz_cf_css_url'        => hu.'plugins/glz_custom_fields',
        'glz_cf_js_url'         => hu.'plugins/glz_custom_fields'
    );
    // set prefs (but don't reset already set prefs)
    glz_custom_fields_MySQL('set_plugin_prefs', $plugin_prefs, '', $no_reset = true);

    // PLUGIN PREFS in future under Admin > Preferences so no longer remove
/*
  // let's update plugin preferences, make sure they won't appear under Admin > Preferences
  safe_query("
    UPDATE
      `".PFX."txp_prefs`
    SET
      `type` = '10'
    WHERE
      `event` = 'glz_custom_f'
  ");
*/

    // Create a search section if not already available (for searching by custom fields)
    if (empty(safe_row("name", 'txp_section', "name='search'"))) {

        // Retrieve skin name used for 'default' section
        $current_skin = safe_field('skin', 'txp_section', "name='default'");

//    if( !getRow("SELECT name FROM `".PFX."txp_section` WHERE name='search'") ) {
        safe_insert('txp_section',"
            name         = 'search',
            title        = 'Search',
            skin         = '".$current_skin."',
            page         = 'default',
            css          = 'default',
            description  = '',
            on_frontpage = '0',
            in_rss       = '0',
            searchable   = '0'
        ");
/*
    safe_query("
      INSERT INTO
        `".PFX."txp_section` (`name`, `page`, `css`, `in_rss`, `on_frontpage`, `searchable`, `title`)
      VALUES
        ('search', 'default', 'default', '0', '0', '0', 'Search')
    ");
*/
        $msg = gTxt('glz_cf_search_section_created');
    }

    // create 'custom_fields' table if it does not already exist
    safe_create(
        'custom_fields',
        "`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL default '',
        `value` varchar(255) NOT NULL default '',
        PRIMARY KEY (id),
        KEY (`name`(50))",
        "ENGINE=MyISAM"
    );
/*
    safe_query("
      CREATE TABLE `".PFX."custom_fields` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL default '',
        `value` varchar(255) NOT NULL default '',
        PRIMARY KEY (id),
        KEY (`name`(50))
      ) ENGINE=MyISAM
    ");
*/

    // add an 'id' column to an existing legacy 'custom_fields' table
    if (!getRows("SHOW COLUMNS FROM ".safe_pfx('custom_fields')." LIKE 'id'")) {
        safe_alter(
            'custom_fields',
            "ADD `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT KEY"
        );
    }
/*
      safe_query("
        ALTER TABLE ".safe_pfx('custom_fields')."
          ADD `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT KEY
      ");
*/

    // Migrate existing custom_field data to new 'custom_fields' table

    // skip if glz_cf migration has already been performed
    if ( isset($prefs['migrated']) ) {
        return;
    }

    // skip if 'custom_fields' table already contains values (don't overwrite anything)
    if (($count = safe_count('custom_fields', "1 = 1")) !== false) {
        // set flag in txp_prefs that migration has already been performed
        set_pref("migrated", "1", "glz_custom_f");
        $msg = gTxt('glz_cf_migration_skip');
        return;
    }

   // iterate over all custom_fields and retrieve all values
   // in custom field columns in textpattern table
   foreach ($all_custom_sets as $custom => $custom_set) {

        // check only custom fields that have been set (have a name)
        if ( $custom_set['name'] ) {

            // get all existing custom values for ALL articles
            $all_values = glz_custom_fields_MySQL('all_values',
                glz_custom_number($custom),
                '',
                array('custom_set_name' => $custom_set['name'],
                'status' => 0)
            );

            // if we have results, assemble SQL insert statement to add them to custom_fields table
            if ( count($all_values) > 0 ) {
                $insert = '';
                foreach ( $all_values as $escaped_value => $value ) {
                    // skip empty values or values > 255 characters (=probably textareas?)
                    if ( !empty($escaped_value) && strlen($escaped_value) < 255 ) {
                        $insert .= "('{$custom}','{$escaped_value}'),";
                    }
                }
                // trim final comma and space
                $insert = rtrim($insert, ', ');
                $query = "
                    INSERT INTO
                        ".safe_pfx('custom_fields')." (`name`,`value`)
                    VALUES
                        {$insert}
                    ";

                if ( isset($query) && !empty($query) ) {

                    // add all custom field values to 'custom_fields' table
                    safe_query($query);

                    // update the type of this custom field to select
                    // (might want to make this user-adjustable at some point)
                    glz_custom_fields_MySQL("update", $custom, safe_pfx('txp_prefs'), array(
                            'custom_set_name'     => $custom_set['name'],
                            'custom_set_type'     => "select",
                            'custom_set_position' => $custom_set['position']
                        )
                    );
                    $msg = gTxt('glz_cf_migration_success');
                }
            }
        }
    }

    // set flag in txp_prefs that migration has been performed
    set_pref("migrated", "1", "glz_custom_f");
}

?>

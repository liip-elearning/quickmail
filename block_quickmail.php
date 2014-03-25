<?php

// Written at Louisiana State University
//
require_once($CFG->dirroot . '/blocks/quickmail/lib.php');

class block_quickmail extends block_list {
    function init() {
        $this->title = quickmail::_s('pluginname');
    }

    function applicable_formats() {
        global $USER;

        // DWE -> temp variable to make it always evaluate to admin email
        // @todo -> figure out how to tell if the user is an admin or not
        
        $admin_email = true;
        if($admin_email){
            return array('site' => false, 'my' => false, 'course-view' => true);
        }
        else{
        return array('site' => true, 'my' => false, 'course-view' => false);
        }
    }
    function has_config() {
        return true;
    }
    /**
     * Disable multiple instances of this block
     * @return bool Returns false
     */
    function instance_allow_multiple() {
        return false;
    }
    
    function get_content() {
        global $CFG, $COURSE, $OUTPUT, $USER;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $context = context_course::instance($COURSE->id);

        $config = quickmail::load_config($COURSE->id);
        $permission = has_capability('block/quickmail:cansend', $context);

        $can_send = ($permission or !empty($config['allowstudents']));

        $icon_class = array('class' => 'icon');

        $cparam = array('courseid' => $COURSE->id);
        
        
        if(is_siteadmin($USER->id)){
            $send_email_str = get_string('send_admin_email', 'block_quickmail');
            $send_email_href = new moodle_url('/blocks/quickmail/adminemail.php');
            $send_email = html_writer::link($send_email_href, $send_email_str);
            $this->content->items[] = $send_email;

            $this->content->icons[] =
                $OUTPUT->pix_icon('i/email', $send_email_str,
                    'moodle', array('class' => 'icon'));
            //return $this->content;

        }
         
         
        
        
        
        if ($can_send) {
            $send_email_str = quickmail::_s('composenew');
            $send_email = html_writer::link(
                new moodle_url('/blocks/quickmail/email.php', $cparam),
                $send_email_str
            );
            $this->content->items[] = $send_email;
            $this->content->icons[] = $OUTPUT->pix_icon('t/email', $send_email_str, 'moodle', $icon_class);

            $signature_str = quickmail::_s('signature');
            $signature = html_writer::link(
                new moodle_url('/blocks/quickmail/signature.php', $cparam),
                $signature_str
            );
            $this->content->items[] = $signature;
            $this->content->icons[] = $OUTPUT->pix_icon('i/edit', $signature_str, 'moodle', $icon_class);

            $draft_params = $cparam + array('type' => 'drafts');
            $drafts_email_str = quickmail::_s('drafts');
            $drafts = html_writer::link(
                new moodle_url('/blocks/quickmail/emaillog.php', $draft_params),
                $drafts_email_str
            );
            $this->content->items[] = $drafts;
            $this->content->icons[] = $OUTPUT->pix_icon('i/settings', $drafts_email_str, 'moodle', $icon_class);

            $history_str = quickmail::_s('history');
            $history = html_writer::link(
                new moodle_url('/blocks/quickmail/emaillog.php', $cparam),
                $history_str
            );
            $this->content->items[] = $history;
            $this->content->icons[] = $OUTPUT->pix_icon('i/settings', $history_str, 'moodle', $icon_class);
        }

        if (has_capability('block/quickmail:allowalternate', $context)) {
            $alt_str = quickmail::_s('alternate');
            $alt = html_writer::link(
                new moodle_url('/blocks/quickmail/alternate.php', $cparam),
                $alt_str
            );

            $this->content->items[] = $alt;
            $this->content->icons[] = $OUTPUT->pix_icon('i/edit', $alt_str, 'moodle', $icon_class);
        }

        if (has_capability('block/quickmail:canconfig', $context)) {
            $config_str = quickmail::_s('config');
            $config = html_writer::link(
                new moodle_url('/blocks/quickmail/config.php', $cparam),
                $config_str
            );
            $this->content->items[] = $config;
            $this->content->icons[] = $OUTPUT->pix_icon('i/settings', $config_str, 'moodle', $icon_class);
        }

        return $this->content;
    }
}

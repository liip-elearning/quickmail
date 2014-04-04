<?php

// Written at Louisiana State University

require_once $CFG->libdir . '/formslib.php';

class admin_email_form extends moodleform {
    function definition() {
        global $CFG, $DB, $USER, $PAGE;
        $PAGE->requires->js('/blocks/quickmail/validation.js');

        $mform =& $this->_form;
        
        $mform->addElement('text', 'additional_emails', 'Additional Email Addresses',array('style'=>'width: 50%;'));
        $mform->setType('additional_emails', PARAM_TEXT);       
         $mform->addRule('additional_emails', 'One or more email addresses is invalid', 'callback', 'mycallback', 'client');
         
        $mform->addElement('text', 'subject', get_string('subject', 'block_admin_email'));
        $mform->setType('subject', PARAM_TEXT);
        
        $mform->addElement('text', 'noreply', get_string('noreply', 'block_admin_email'));
        $mform->setType('noreply', PARAM_TEXT);
        
        //$mform->addElement('editor', 'message_editor',  get_string('body', 'block_admin_email'));

        $mform->addElement('editor', 'message_editor', quickmail::_s('message'),
            null, $this->_customdata['editor_options']);
        
        
        $buttons = array(
            $mform->createElement('submit', 'send', get_string('send_email', 'block_admin_email')),
            //$mform->createElement('submit', 'draft', quickmail::_s('save_draft')),
            $mform->createElement('cancel', 'cancel', get_string('cancel'))
        );
        
        $mform->addElement(
            'filemanager', 'attachments', quickmail::_s('attachment'),
            null, array('subdirs' => 1, 'accepted_types' => '*')
        );
        
        
        $mform->addGroup($buttons, 'actions', '&nbsp;', array(' '), false);

        $mform->addRule('subject', null, 'required', 'client');
        $mform->addRule('noreply', null, 'required', 'client');
        $mform->addRule('message_editor', null, 'required');

        $options = $this->_customdata['sigs'] + array(-1 => 'No '. quickmail::_s('sig'));

        $mform->addElement('select', 'sigid', quickmail::_s('signature'), $options);

    }

    function validation($data, $files) {
        $errors = array();
        foreach(array('subject', 'body', 'noreply') as $field) {
            if(empty($data[$field]))
                $errors[$field] = get_string('email_error_field', 'block_admin_email', $field);
        }
    }
}

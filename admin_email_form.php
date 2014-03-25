<?php

// Written at Louisiana State University

require_once $CFG->libdir . '/formslib.php';

class admin_email_form extends moodleform {
    function definition() {
        global $CFG;
        
        $mform =& $this->_form;
        
        $mform->addElement('text', 'additional_emails', 'Additional Email Addresses',array('style'=>'width: 50%;'));
        $mform->setType('additional_emails', PARAM_TEXT);       
        
        $mform->addElement('text', 'subject', get_string('subject', 'block_admin_email'));
        $mform->setType('subject', PARAM_TEXT);
        
        $mform->addElement('text', 'noreply', get_string('noreply', 'block_admin_email'));
        $mform->setType('noreply', PARAM_TEXT);
        
        $mform->addElement('editor', 'body',  get_string('body', 'block_admin_email'));

        $buttons = array(
            $mform->createElement('submit', 'send', get_string('send_email', 'block_admin_email')),
            $mform->createElement('cancel', 'cancel', get_string('cancel'))
        );
        
        $mform->addElement(
            'filemanager', 'attachments', quickmail::_s('attachment'),
            null, array('subdirs' => 1, 'accepted_types' => '*')
        );
        
        
        $mform->addGroup($buttons, 'actions', '&nbsp;', array(' '), false);

        $mform->addRule('subject', null, 'required', 'client');
        $mform->addRule('noreply', null, 'required', 'client');
        $mform->addRule('body', null, 'required');
        
        // DWE -> Retrieve the signatures here
        //$options = $this->_customdata['sigs'] + array(-1 => 'No '. quickmail::_s('sig'));
        //$options = ' ';
        $mform->addElement('select', 'sigid', quickmail::_s('signature'));

    }

    function validation($data, $files) {
        $errors = array();
        foreach(array('subject', 'body', 'noreply') as $field) {
            if(empty($data[$field]))
                $errors[$field] = get_string('email_error_field', 'block_admin_email', $field);
        }
    }
}

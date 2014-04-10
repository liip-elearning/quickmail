<?php

//
// Written at Louisiana State University
// by Jason Peak and Davington Welliottsworth
//
class Message {

    public $subject,
            $text,
            $html,
            $mailto,
            $admins,
            $warnings,
            $noreply,
            $sentUsers,
            $startTime,
            $endTime,
            // DWE -> From Dave's Admin Email + Quickmail Merge 
            // additional variables that are needed for the new boxes. 
            $attachment,
            $messagewithsig,
            $context,
            $type,
            $typeid,
            $data,
            $message_id;
          

    /**
     * 
     * @global type $DB
     * @global type $USER
     * @global type $COURSE
     * @param type $data -- from the admin_email_form 
     * @param int[] $mailto array of user ids
     * @param type $type
     * @param type $typeid
     */
    public function __construct($data, $mailto, $type, $typeid, $sigs) {
        global $DB, $USER, $COURSE;
        $this->data = $data;
        $this->messagewithsig = "";
        $this->additional_emails = array();
        $this->warnings     = array();
        $this->subject      = $data->subject;
        $this->html         = $data->message_editor['text'];
        $this->text         = strip_tags($data->message_editor['text']);
        $this->noreply      = $data->noreply;
        //$this->warnings     = array();
        $this->mailto       = array_values($DB->get_records_list('user', 'id', $mailto));
        $this->attachment   = quickmail::attachment_names($data->attachments);
        $this->context      = context_system::instance();
        $this->additional_emails    = $data->additional_emails;
        $this->type         = $type;
        $this->typeid       = $typeid;
        $data -> mailto       = implode(',',$mailto);
        $data -> message      = $this->text;
        $data -> attachment   = $this->attachment;
        $data -> userid       = $USER->id;
        $data -> courseid     = $COURSE->id;
        $data -> time         = time();
        
        // Store data; id is needed for file storage
        if ( isset($data->send) ) {
            $data->id = $DB->insert_record('block_quickmail_log', $data);
            $table = 'log';
        }
        
        else if ( isset($data->draft) ) {
            $table = 'drafts';

            if ( !empty($typeid) and $type == 'drafts' ) {
                $data->id = $typeid;
                $DB->update_record('block_quickmail_drafts', $data);
            }
            
            else {
                $data->id = $DB->insert_record('block_quickmail_drafts', $data);
            }
            
                
        }
        $this -> message_id = $data->id;

        $editor_options = array(
            'trusttext'     =>      true,
            'subdirs'       =>      1,
            'maxfiles'      =>      EDITOR_UNLIMITED_FILES,
            'accepted_types'=>      '*',
            'context'       =>      $this->context
        );


        $data = file_postupdate_standard_editor(
                $data, 'message', $editor_options, $this->context, 'block_quickmail', $table, $data->id
        );

        $DB->update_record('block_quickmail_' . $table, $data);

        //DWE -> WHATS THIS BUSINESS BELOW
        
        //$prepender = $config['prepend_class'];
//        if (!empty($prepender) and !empty($course->$prepender)) {
//            $subject = "[{$course->$prepender}] $data->subject";
//        } else {
//            $subject = $data->subject;
//        }        

        file_save_draft_area_files(
                $data->attachments, $this->context->id, 'block_quickmail', 'attachment_' . $table, $data->id, $editor_options
        );

        // Send emails
        if ( isset($data->send) ) {
            if ( $type == 'drafts' ) {
                quickmail::draft_cleanup($this->context->id, $typeid);
            }

            if ( !empty($sigs) and $data->sigid > -1 ) {
                $sig = $sigs[$data->sigid];

                $signaturetext = file_rewrite_pluginfile_urls($sig->signature, 'pluginfile.php', $this->context->id, 'block_quickmail', 'signature', $sig->id, $editor_options);


                $data->messagewithsig = $data->message . "\n\n" . $signaturetext;
                $this->messagewithsig = $data->message . "\n\n" . $signaturetext;
                
            }

            // Append links to attachments, if any
            $data->message .= quickmail::process_attachments(
                            $this->context, $data, $table, $data->id
            );

            // Prepare html content of message
            $data->message = file_rewrite_pluginfile_urls($data->message, 'pluginfile.php', $this->context->id, 'block_quickmail', $table, $data->id, $editor_options);
            $this->text = strip_tags($data->message);
            $this->html = $data->message;
            // Same user, alternate email
            if ( !empty($data->alternateid) ) {
                $user = clone($USER);
                $user->email = $alternates[$data->alternateid];
            }
            else {
                $user = $USER;
            }
        }
    }

    /**
     * 
     * @global type $DB
     * @param stdClass[] user objects $users
     * @param boolean $sendToEmails 
     */
    public function send($users = null, $sendToEmails = true) {
        GLOBAL $DB;
        $data = new stdClass();
        $data->failuserids = array();
        $data->id = $this->message_id;
        $this->startTime = time();
        $data->messagewithsig = $this->messagewithsig;
        //$data->messagewithsig = 
        $users = empty($users) ? $this->mailto : $users;

        $noreplyUser = new stdClass();
        $noreplyUser->firstname = 'Moodle';
        $noreplyUser->lastname = 'Administrator';
        $noreplyUser->username = 'moodleadmin';
        $noreplyUser->email = $this->noreply;
        $noreplyUser->maildisplay = 2;
        $noreplyUser->alternatename = "";
        $noreplyUser->firstnamephonetic = "";
        $noreplyUser->lastnamephonetic = "";
        $noreplyUser->middlename = "";

        foreach ($users as $user) {
            
            $success = email_to_user(
                    $user, // to
                    $noreplyUser, // from
                    $this->subject, // subj
                    $this->text, // body in plain text
                    $this->html, // body in HTML
                    '', // attachment
                    '', // attachment name
                    true, // user true address ($USER)
                    $this->noreply, // reply-to address
                    get_string('pluginname', 'block_admin_email') // reply-to name
            );

            // DWE -> temp make all emails screw up
            //$success = FALSE;
            if ( !$success ){
                $this->warnings[] = get_string('message_failure', 'block_quickmail', $user);
                $data->failuserids[] = $user->id;
                $this->sentUsers[] = "";
            }
            else {
                $this->sentUsers[] = $user->username;
            }
            //  DWE -> create a fake user here and email them
            // need to turn this into a for each loop
        }
        
        if($sendToEmails == true){
            create_and_email_fake_users(explode(',',$this->additional_emails), $user, $this->subject, $data, $this->warnings);
        }



        
//        $this->endTime = time();
//        $data->failuserids = implode(',', $data->failuserids);
//        if($sendToEmails){
//            $DB->update_record('block_quickmail_log', $data);
//        }
    }

    public function buildAdminReceipt() {
        global $CFG, $DB;
        //var_dump($this->mailto);
        $adminIds       = explode(',', $CFG->siteadmins);
        $this->admins   = $DB->get_records_list('user', 'id', $adminIds);

        $usersLine      = sprintf("Message sent to %d/%d users.<br/>", count($this->sentUsers), count($this->mailto));
        $timeLine       = sprintf("Time elapsed: %d seconds<br/>", $this->endTime - $this->startTime);
        $warnline       = sprintf("Warnings: %d<br/>", count($this->warnings));
        $msgLine        = sprintf("message body as follows<br/><br/><hr/>%s<hr/>", $this->html);
        
        $names = "";
        foreach($this->mailto as $person){
            $names .= $person->username . ', ';
        }
        
        if($this->additional_emails){
            $recipLine      = sprintf("sent successfully to the following users:<br/><br/>%s", $names . "<br />and the following email addresses: " . $this->additional_emails);
        }
        else{
            $recipLine      = sprintf("sent successfully to the following users:<br/><br/>%s", $names);
        }
        return $usersLine . $warnline . $timeLine . $msgLine . $recipLine;
    }

    public function sendAdminReceipt() {
        $this->html     = $this->buildAdminReceipt();
        $this->text     = $this->buildAdminReceipt();
        $this->subject  = "Admin Email send receipt";
        
        $this->send($this->admins, FALSE);
    }
}

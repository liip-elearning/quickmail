<?php

// Written at Louisiana State University
global $CFG, $USER, $SESSION, $PAGE, $SITE, $OUTPUT, $DB, $COURSE;
require_once '../../config.php';
require_once "$CFG->dirroot/course/lib.php";
require_once "$CFG->libdir/adminlib.php";
require_once "$CFG->dirroot/user/filters/lib.php";
require_once 'lib.php';
require_once 'admin_email_form.php';
require_once 'message.php';
require_login();

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$sort = optional_param('sort', '', PARAM_ACTION);
$direction = optional_param('dir', 'ASC', PARAM_ACTION);
$type = optional_param('type', '', PARAM_ALPHA);
$typeid = optional_param('typeid', 0, PARAM_INT);
$messageIDresend = optional_param('fmid', 0, PARAM_INT);

$blockname = get_string('pluginname', 'block_quickmail');
$header = get_string('send_email', 'block_quickmail');

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/blocks/quickmail/adminemail.php');
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($header);
$PAGE->set_heading($SITE->shortname . ': ' . $blockname);

// Get Our users
$fields = array(
    'realname' => 1,
    'lastname' => 1,
    'firstname' => 1,
    'email' => 1,
    'city' => 1,
    'country' => 1,
    'confirmed' => 1,
    'suspended' => 1,
    'profile' => 1,
    'courserole' => 0,
    'systemrole' => 0,
    'username' => 0,
    'cohort' => 1,
    'firstaccess' => 1,
    'lastaccess' => 0,
    'neveraccessed' => 1,
    'timemodified' => 1,
    'nevermodified' => 1,
    'auth' => 1,
    'mnethostid' => 1,
    'language' => 1,
    'firstnamephonetic' => 1,
    'lastnamephonetic' => 1,
    'middlename' => 1,
    'alternatename' => 1
);

$ufiltering = new user_filtering($fields);
list($sql, $params) = $ufiltering->get_sql_filter();
$totalusers = get_users(false, '', true, null, '', '', '', '', '', '*', $sql, $params);

if ( empty($sort) ) {
    $sort = 'lastname';
}

$display_users = empty($sql) ? array() :
        get_users_listing($sort, $direction, $page * $perpage, $perpage, '', '', '', $sql, $params);

$users = empty($sql) ? array() :
        get_users_listing($sort, $direction, $page * $perpage, 0, '', '', '', $sql, $params);

$sigs = $DB->get_records('block_quickmail_signatures', array('userid' => $USER->id), 'default_flag DESC');

$editor_options = array(
    'trusttext' => true,
    'subdirs' => 1,
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'accepted_types' => '*',
    'context' => $context
);


$form = new admin_email_form(null, array(
    'editor_options' => $editor_options,
    'sigs' => array_map(function($sig) {
        return $sig->title;
    }, $sigs))
);

// Process data submission
if ( $form->is_cancelled() ) {
    unset($SESSION->user_filtering);
    redirect(new moodle_url('/blocks/quickmail/adminemail.php'));
}

else if ( $data = $form->get_data() ) {

    $message = new Message($data, array_keys($users), $type, $typeid, $sigs);

    $message->send();
    $message->sendAdminReceipt();
    // Finished processing
    // Empty errors mean that you can go back home
    if ( empty($message->warnings) ) {
        redirect(new moodle_url('/blocks/quickmail/emaillog.php', array('courseid' => $COURSE->id)));
    }
    else {
        redirect(new moodle_url('/blocks/quickmail/emaillog.php', array('courseid' => $COURSE->id)));
    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading($header);

echo html_writer::link('emaillog.php?courseid=1', get_string('log', 'block_quickmail'));


// Notify the admin.
if ( !empty($message->warnings) ) {

    foreach ($message->warnings as $warning) {

        echo $OUTPUT->notification($warning);
    }
}


// Start work

////////////////////////////////////////////////////////
////////////////////   LOG MESSAGE /////////////////////
////////////////////////////////////////////////////////
if ( $type == 'log' ) {
// get the DB record and seperate the emails and ids to repopulate the form

    $get_emails_and_users = $DB->get_record('block_quickmail_' . $type, array('id' => $typeid));

    if ( $messageIDresend == '1' ) {
        list($get_emails_and_users->mailto, $get_emails_and_users->additional_emails) = quickmail::clean($get_emails_and_users->failuserids);
        echo html_writer::tag('h2', get_string('previously_failed', 'block_quickmail'));

        
    }

    $users = $get_emails_and_users->mailto;

    $totalusers = count(explode(',', $get_emails_and_users->mailto));

    if ( !empty($get_emails_and_users->mailto) ) {
        $sql = 'id IN (' . $get_emails_and_users->mailto . ')';
    }


    $display_users = $DB->get_records_list('user', 'id', explode(',', $get_emails_and_users->mailto));

    $columns = array('firstname', 'lastname', 'email', 'city', 'lastaccess');

    foreach ($columns as $column) {
        $direction = ($sort == $column and $direction == "ASC") ? "DESC" : "ASC";
        $$column = html_writer::link('index.php?sort=' . $column . '&dir=' .
                        $direction, get_string($column));
    }

    $get_emails_and_users->messageformat = $USER->mailformat;

    $get_emails_and_users = file_prepare_standard_editor(
            $get_emails_and_users, 'message', $editor_options, $context, 'block_quickmail', $type, $get_emails_and_users->id
    );

    if ( empty($get_emails_and_users->attachments) ) {

        if ( !empty($type) ) {

            $attachid = file_get_submitted_draft_itemid('attachment');

            file_prepare_draft_area(
                    $attachid, $context->id, 'block_quickmail', 'attachment_' . $type, $typeid
            );

            $get_emails_and_users->attachments = $attachid;
        }
    }

    $form->set_data($get_emails_and_users);
}
////////////////////////////////////////////////////////
////////////////////   NEW MESSAGE /////////////////////
////////////////////////////////////////////////////////
else {
    $ufiltering->display_add();

    $ufiltering->display_active();


    if ( !empty($sql) ) {
        echo $OUTPUT->heading("Found $totalusers User(s)");
    }

    if ( !empty($display_users) ) {
        $columns = array('firstname', 'lastname', 'email', 'city', 'lastaccess');

        foreach ($columns as $column) {
            $direction = ($sort == $column and $direction == "ASC") ? "DESC" : "ASC";
            $$column = html_writer::link('index.php?sort=' . $column . '&dir=' .
                            $direction, get_string($column));
        }
    }
}

if(!empty($display_users)){
    
    quickmail::printNget_paging_bar($page, $perpage, $sort, $direction, $type, $typeid, $totalusers);

    
    $table = new html_table();

    $table->head = array("$firstname / $lastname", $email, $city, $lastaccess);

    $table->data = array_map(function($user) {
        $fullname = fullname($user);
        $email = $user->email;
        $city = $user->city;
        $lastaccess_time = isset($user->lastaccess) ?
                format_time(time() - $user->lastaccess) : get_string('never');

        return array($fullname, $email, $city, $lastaccess_time);
    }, $display_users);

    echo html_writer::table($table);



    if ( !empty($get_emails_and_users->mailto) ) {
        $form->set_data(array('noreply' => $CFG->noreplyaddress, 'additional_emails' => $get_emails_and_users->additional_emails));
    }

    $form->set_data(array('noreply' => $CFG->noreplyaddress));
    echo $form->display();

}

 

echo $OUTPUT->footer();

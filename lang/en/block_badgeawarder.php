<?php
// This file is part of the plugin Block Badgeawarder
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version details
 *
 * @package    block_badgeawarder
 * @copyright  2013 Learning Technology Services, www.lts.ie - Lead Developer: Bas Brands
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['accountscreated'] = 'The number of accounts created: {$a}';
$string['awardall'] = 'Award to all users, create non-existing users';
$string['awardbadges'] = 'Award badges';
$string['awarderrors'] = 'Award errors: {$a}';
$string['awardexisting'] = 'Award to existing users only';
$string['awardnew'] = 'Award to new users only';
$string['awardresult'] = 'Result';
$string['awardtotal'] = 'Total awarded: {$a}';
$string['badge'] = 'Badge';
$string['badgeawarder:addinstance'] = 'Add a Badge Awarder block';
$string['badgeawarder:myaddinstance'] = 'Add a Badge Awarder block';
$string['badgeawarder:uploadcsv'] = 'Upload a CSV file to the Badge Awarder block';
$string['badgecsv'] = 'Badge csv upload';
$string['badgecsvpreview'] = 'Preview the badge awarding about to be done';
$string['blockname'] = 'Badge Awarder';
$string['completion'] = 'Completion';
$string['selectcountry'] = 'Select Default Country';
$string['csv'] = 'The badge CSV file';
$string['csvdelimiter'] = 'CSV delimiter';
$string['csvfileerror'] = 'There was an error in your CSV upload file';
$string['csvformaterror'] = '<br>The CSV manager failed to find all required fields, The BadgeAwarder requires
the fields firstname, lastname, badge, email on the first row.<br>If you receive this message either one of these fields were missing or you have not chosen the correct field delimiter.';
$string['csvline'] = 'CSV line';
$string['defaultuploadtype'] = 'Default upload type';
$string['defaultdelimiter'] = 'Default delimiter';
$string['defaultencoding'] = 'Default encoding';
$string['defaultpreviewrows'] = 'Default number of preview rows';
$string['emailawardsubject'] = 'You have received a badge';
$string['emailawardtextnew'] = 'Congratulations you have received a {$a->badgename} badge.<br>
<br>
To access Moodle, got to: {$a->siteurl}
<br>
Your current login information is now:<br>
   username: {$a->username}<br>
   password: {$a->newpassword}<br>
';
$string['emailawardtextexisting'] = 'Congratulations you have received a {$a->badgename} badge.<br>
<br>
To access your badge visit {$a->siteurl}
';
$string['encoding'] = 'Encoding';
$string['enrolment'] = 'Enrolment';
$string['emailsend'] = 'Email send';
$string['importoptions'] = 'Import options';
$string['line'] = 'Line';
$string['missingbadge'] = 'Badge not found';
$string['mode'] = 'Upload mode';
$string['mode_help'] = 'This allows you to specify if badges can be created and/or updated.';
$string['nothingtodo'] = 'There are no users in the CSV file that can be awarded a Badge';
$string['nothingtodobutton'] = 'Go Back';
$string['pluginname'] = 'Badge Awarder';
$string['preview'] = 'Preview:';
$string['previewskipexisting'] = 'Skipping Existing user';
$string['previewupdateexisting'] = 'Updating Existing user';
$string['previewskipnonexisting'] = 'Skipping Non Existing user';
$string['previewcreatenew'] = 'Creating new user';
$string['result'] = 'Result';
$string['returntocourse'] = 'Return to course';
$string['rowpreviewnum'] = 'Preview rows';
$string['rowpreviewnum_help'] = 'Number of rows from the CSV file that will be previewed in the next page. This option exists in
order to limit the next page size.';
$string['samplecsv'] = 'Download a sample csv file';
$string['showextendedoption'] = 'Show Extended Options';
$string['statusok'] = 'Ok';
$string['statusgetuserfailed'] = 'Creating or getting user failed';
$string['statusbadgealreadyawarded'] = 'Badge already awarded';
$string['statusbadgenotactive'] = 'Badge not active';
$string['statusbadgenotexist'] = 'Badge does not exist';
$string['statusemailinvited'] = 'Emailed new user';
$string['statusemailnotified'] = 'Existing user notified';
$string['statusemailfailed'] = 'Could not send email';
$string['statusskipinvalidemail'] = 'Invalid email address';
$string['statusskipnewuser'] = 'Skipping new user';
$string['statusskipexistinguser'] = 'Skipping existing user';
$string['statusmissingfields'] = 'Missing required fields';
$string['statuscoursebadgeonly'] = 'Not a course badge';
$string['statusbadgecriteriaerror'] = 'Badge not manually awarded or has additional criteria';
$string['uploadbadgespreview'] = 'Upload Badges Preview';
$string['uploadcsv'] = 'Upload CSV';
$string['uploadbadgecsv'] = 'Upload Badges CSV';
$string['uploadbadgecsv_help'] = ' Each line of the file contains one record
 Each record is a series of data separated by commas (or other delimiters)
 The first record contains a list of fieldnames defining the format of the rest of the file
 Required fieldnames are firstname, lastname, email, badge';
$string['usersawarded'] = 'Users Awarded';
$string['usersenrolled'] = 'Users Enrolled: {$a}';
$string['usersettings'] = 'Default New User Settings';

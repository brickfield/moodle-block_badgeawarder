moodle-block_badgeawarder
==================================
Badge Awarder block

License: GPL v3 
Author: Learning Technology Services, www.lts.ie 
Lead Developer: Bas Brands 

Moodle versions: 2.5, 2.6, 2.7, 2.8

Github URL - https://github.com/learningtechnologyservices/moodle-block_badgeawarder 

Documentation - https://github.com/learningtechnologyservices/moodle-block_badgeawarder/blob/master/README.txt 

INTRODUCTION

This block has been created to enable quick and simple awarding of pre-existing badges in a given course. Both existing students and non-students can be awarded badges using this block. In the case of non-students, their details are used to generate new student accounts, they are then enrolled on the relevant course and are emailed their Moodle login details.

It allows a teacher to upload a CSV file and processes the file based on the specified columns, and these field values must also be included in the CSV file's first line. The badge information required is the course badge name, viewable under Course badges.

CSV file format
firstname,lastname,email,badge

Prerequisites

In order to award a badge by using the Badge Awarder block, the badge itself must have been set up already and enabled, and particularly, set with the single criteria of "Manual issue by role". If the badge itself is set to either of the other two options, Course completion or Activity completion, it will not be successfully awarded by the Badge Awarder block.
In order to enrol either existing students or non-students as part of their badge awarding, the relevant course itself must be enabled for manual enrolments.
The Badge Awarder block also expects to work with a sitewide unique user emails policy, which the CSV upload file must also follow.
Enrolment of non-students may be inadvisable for Moodle sites which serve as MNet service providers, due to user account identification and authentication limitations.

USAGE

Install the block in the /blocks/ folder 
Ensure the plugin folder is called "badgeawarder" 
Navigate to Site Administration -> Notifications to start installation 

How to use Badge Awarder in a course? 
----------------------------------------- 
Login as an Administrator or Teacher or an account with course editing privilege. 

Navigate to the course you wish to award the badges for.

Turn editing on and add the Badge Awarder block within the new course.

You will then see the Badge Awarder block with its link, "Upload Badges CSV". Once clicked, this will bring you to the Badge CSV upload page. 

The Badge CSV upload page uses the File Picker for you to select the relevant CSV file to upload, and also allows you to select the delimiter, encoding and number of rows to show on the Preview screen.

There are also three Import modes: 
1) Award to new users only - this will parse the CSV file and only process those users which do not already exist on the Moodle site 
2) Award to all users, create non-existing users - this will parse the CSV file and process all rows. All existing users will be enrolled on the course and awarded their badge. All non-existing users will be enrolled, have their login details mailed to their email account, and then also be enrolled on the course and awarded their badge. 
3) Award to existing users only - this will parse the CSV file and only process those users which do already exist on the Moodle site

Click on the Preview button to submit the CSV file and view the pending user details before they are finally submitted, to review in case any changes are needed.

On the Preview page, if you are processing new users, you will also need to select their Country and City for the submission process.

Once satisfied with the preview details, click on the "Award badges" button and the CSV file will be fully processed. Once completed, the page will display the results of the CSV file upload, including 
1) Total [Badges] awarded 
2) Number of accounts created 
3) [Existing] Users Enrolled 
4) Any award errors 

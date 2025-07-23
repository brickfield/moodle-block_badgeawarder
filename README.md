# Badge CSV Awarder
Copyright (C) 2025 [Brickfield Education Labs](https://www.brickfield.ie)

## What is Badge CSV Awarder?
Save time and effort awarding multiple course badges to your students, by using one CSV file upload instead.

You also have an optional feature to create users using their details, if enabled in the configuration, and to enrol them onto the badge’s course.

## License
2025 Onward [Brickfield Education Labs](https://www.brickfield.ie)

## Version support
This plugin has been developed to work on Moodle releases 4.01, 4.04, and 4.05.

## Funding credits
Initial funding for this plugin was provided by the HSA.

## Development
This plugin has been developed by [Learning Technology Services](https://www.lts.ie) and is maintained by Brickfield Education Labs.

Lead Developer: Bas Brands 

## Important Links
* [Code repository](https://github.com/brickfield/moodle-block_badgeawarder)
* [Badge CSV Awarder user guide](https://docs.brickfield.ie/block-badgeawarder/)

## Installation
1. Unzip and copy the "badgeawarder" folder into Moodle's "blocks/" folder
2. Visit the admin page to install the plugin

Further installation instructions can be found on the
"[Installing plugins](http://docs.moodle.org/en/Installing_contributed_modules_or_plugins)" Moodle documentation page.

## Usage
How to use Badge Awarder in a course? 
----------------------------------------- 
Login as an Administrator or Teacher or an account with course editing privilege. 

Navigate to the course you wish to award the badges for.

Turn editing on and add the Badge Awarder block within the new course.

You will then see the Badge Awarder block with its link, "Upload Badges CSV". 
Once clicked, this will bring you to the Badge CSV upload page. 

The Badge CSV upload page uses the File Picker for you to select the relevant CSV file to upload. 
This also allows you to select the delimiter, encoding and number of rows to show on the Preview screen.

There are also three Import modes: 
1) Award to new users only - this will parse the CSV file and only process those users which do not 
already exist on the Moodle site 
2) Award to all users, create non-existing users - this will parse the CSV file and process all rows. 
All existing users will be enrolled on the course and awarded their badge. 
All non-existing users will be enrolled, have their login details mailed to their email account, 
and then also be enrolled on the course and awarded their badge. 
3) Award to existing users only - this will parse the CSV file and only process those users which do already exist on the Moodle site

Click on the Preview button to submit the CSV file and view the pending user details before they are finally submitted, 
to review in case any changes are needed.

On the Preview page, if you are processing new users, you will also need to select their Country and City for the submission process.

Once satisfied with the preview details, click on the "Award badges" button and the CSV file will be fully processed. 
Once completed, the page will display the results of the CSV file upload, including 
1) Total [Badges] awarded 
2) Number of accounts created 
3) [Existing] Users Enrolled 
4) Any award errors 
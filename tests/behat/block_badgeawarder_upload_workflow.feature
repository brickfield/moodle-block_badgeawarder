@block @block_badgeawarder @_file_upload
Feature: Award badges by CSV upload
  In order to award course badges to many students at once
  As a teacher
  I need to upload a CSV file, preview the pending awards, and confirm the award

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "core_badges > Badges" exist:
      | name         | course | description               | image                         | status | type |
      | Course Badge | C1     | Course badge description | badges/tests/behat/badge.png  | active | 2    |
    And the following "core_badges > Criteria" exists:
      | badge | Course Badge   |
      | role  | editingteacher |
    And I am on the "C1" "block_badgeawarder > upload" page logged in as "teacher1"

  @javascript
  Scenario: Teacher uploads a CSV and sees the pending awards preview
    When I upload "blocks/badgeawarder/tests/fixtures/badge_award_existing_user.csv" file to "File" filemanager
    And I press "Preview"
    Then I should see "Preview: Award to all users, create non-existing users"
    And I should see "Student"
    And I should see "student1@example.com"
    And I should see "Course Badge"
    And "Award badges" "button" should exist

  @javascript
  Scenario: Teacher completes the badge award process and sees a results summary
    When I upload "blocks/badgeawarder/tests/fixtures/badge_award_existing_user.csv" file to "File" filemanager
    And I press "Preview"
    And I press "Award badges"
    Then I should see "Total awarded: 1"
    And I should see "The number of accounts created: 0"
    And I should see "Users Enrolled: 1"
    And I should see "Award errors: 0"
    And I should see "Return to course"

  # Missing-columns and empty-file uploads both make the processor throw an uncaught
  # moodle_exception, landing on Moodle's default error page. Moodle's Behat harness treats any
  # such exception page as an automatic step failure (behat_base::look_for_exceptions()), so
  # these can't be asserted here — they belong to PHPUnit instead (see the "Tests to add" list
  # in docs/state/security-analysis.md, which already anticipated this).

  Scenario: Cancelling the upload form returns to the course page
    When I press "Cancel"
    Then I should see "Course 1"
    And I should not see "Badge csv upload"

  @javascript
  Scenario: Cancelling the preview step returns to the upload form
    When I upload "blocks/badgeawarder/tests/fixtures/badge_award_existing_user.csv" file to "File" filemanager
    And I press "Preview"
    And I press "Cancel"
    Then I should see "Badge csv upload"
    And I should see "Upload Badges CSV"

  @javascript
  Scenario: Preview page shows a "nothing to do" message when no row is eligible
    When I upload "blocks/badgeawarder/tests/fixtures/badge_unknown_badge.csv" file to "File" filemanager
    And I press "Preview"
    Then I should see "There are no users in the CSV file that can be awarded a Badge"
    And I should see "Go Back"
    And "Award badges" "button" should not exist

  Scenario: A sample CSV download link is available on the upload page
    Then I should see "Download a sample csv file"
    And "Download a sample csv file" "link" should exist

  @javascript
  Scenario: The award results table has named, visible column headers
    # Whether each header is a genuine <th scope="col"> (rather than just visible text) isn't
    # practically assertable with standard Behat steps — that belongs to accessibility review
    # (bf-accessibility), not Behat. This only confirms the header text itself is present.
    When I upload "blocks/badgeawarder/tests/fixtures/badge_award_existing_user.csv" file to "File" filemanager
    And I press "Preview"
    And I press "Award badges"
    Then I should see "CSV line"
    And I should see "Result"
    And I should see "First name"
    And I should see "Last name"
    And I should see "Email address"
    And I should see "Badge"
    And I should see "Status"

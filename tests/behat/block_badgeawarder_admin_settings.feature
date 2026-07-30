@block @block_badgeawarder @_file_upload
Feature: Configure the Badge Awarder upload options
  In order to control how much choice teachers get over the CSV upload process
  As an admin
  I need to show or hide the extended upload options and set the enforced defaults

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

  Scenario: Extended options are hidden from the upload form by default
    Given I am on the "C1" "block_badgeawarder > upload" page logged in as "teacher1"
    Then I should not see "CSV delimiter"
    And I should not see "Encoding"
    And I should not see "Upload mode"
    And I should not see "Preview rows"

  Scenario: Admin enables extended options and the upload form shows delimiter, encoding, preview rows, and mode fields
    Given the following config values are set as admin:
      | showextendedoption | 1 | block_badgeawarder |
    And I am on the "C1" "block_badgeawarder > upload" page logged in as "teacher1"
    Then I should see "CSV delimiter"
    And I should see "Encoding"
    And I should see "Upload mode"
    And I should see "Preview rows"

  @javascript
  Scenario: A configured default upload mode is enforced even though the mode field is hidden
    # The client can't tamper with this value directly (that trust boundary is already proven
    # against block_badgeawarder_resolve_mode() in tests/locallib_test.php) — this only confirms
    # the admin-configured default actually takes effect for a real upload, end to end.
    Given the following "core_badges > Badges" exist:
      | name         | course | description               | image                        | status | type |
      | Course Badge | C1     | Course badge description | badges/tests/behat/badge.png | active | 2    |
    And the following "core_badges > Criteria" exists:
      | badge | Course Badge   |
      | role  | editingteacher |
    And the following config values are set as admin:
      | showextendedoption | 0 | block_badgeawarder |
      | defaultuploadtype  | 3 | block_badgeawarder |
    And I am on the "C1" "block_badgeawarder > upload" page logged in as "teacher1"
    When I upload "blocks/badgeawarder/tests/fixtures/badge_award_new_user.csv" file to "File" filemanager
    And I press "Preview"
    Then I should see "Skipping new user"
    And "Award badges" "button" should not exist

  @javascript
  Scenario: Country and city fields are not shown when the import mode is existing users only
    Given the following "core_badges > Badges" exist:
      | name         | course | description               | image                        | status | type |
      | Course Badge | C1     | Course badge description | badges/tests/behat/badge.png | active | 2    |
    And the following "core_badges > Criteria" exists:
      | badge | Course Badge   |
      | role  | editingteacher |
    And the following config values are set as admin:
      | showextendedoption | 1 | block_badgeawarder |
    And I am on the "C1" "block_badgeawarder > upload" page logged in as "teacher1"
    And I set the field "Upload mode" to "Award to existing users only"
    And I upload "blocks/badgeawarder/tests/fixtures/badge_award_existing_user.csv" file to "File" filemanager
    And I press "Preview"
    Then I should not see "Default New User Settings"
    And I should not see "City"

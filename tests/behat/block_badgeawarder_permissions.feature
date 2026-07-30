@block @block_badgeawarder
Feature: Badge Awarder visibility and access control
  In order to keep the CSV badge-awarding workflow restricted to authorised staff
  As a teacher, student, or site admin
  I need the Badge Awarder block and its upload page to show or hide correctly for my role

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Teacher   | 1        | teacher1@example.com  |
      | student1 | Student   | 1        | student1@example.com  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "blocks" exist:
      | blockname    | contextlevel | reference |
      | badgeawarder | Course       | C1        |

  Scenario: Teacher sees the Upload Badges CSV link in the Badge Awarder block
    Given I am on the "C1" "course" page logged in as "teacher1"
    Then I should see "Upload Badges CSV" in the "Badge Awarder" "block"

  Scenario: Student does not see the Upload Badges CSV link in the block
    # With no capability, get_content() returns fully empty content, so the block renders no
    # title or shell at all (rather than an empty "Badge Awarder" box) — nothing to scope into.
    Given I am on the "C1" "course" page logged in as "student1"
    Then I should not see "Upload Badges CSV"
    And I should not see "Badge Awarder"

  Scenario: Badge Awarder block shows a disabled message when site badges are turned off
    Given the following config values are set as admin:
      | enablebadges | 0 |
    And I am on the "C1" "course" page logged in as "teacher1"
    Then I should see "Badges are not enabled on this site." in the "Badge Awarder" "block"
    And I should not see "Upload Badges CSV" in the "Badge Awarder" "block"

  Scenario: Teacher can open the Badge CSV upload page directly
    Given I am on the "C1" "block_badgeawarder > upload" page logged in as "teacher1"
    Then I should see "Badge csv upload"
    And I should see "Upload Badges CSV"
    And I should see "Download a sample csv file"

  Scenario: Student is redirected away from the Badge CSV upload page
    # This exercises the controller's own capability check (badgeawarder.php), not just the
    # block's link visibility — the upload page must not be reachable by URL alone.
    Given I am on the "C1" "block_badgeawarder > upload" page logged in as "student1"
    Then I should not see "Badge csv upload"
    And I should see "Course 1"

  Scenario: Visiting the upload page without a course id redirects to the site home
    Given I log in as "student1"
    # The redirect target is the bare site root, which resolves to whichever home page the
    # site is configured for — this only asserts the upload controller itself is never
    # reached, not which specific home page is shown.
    When I visit "/blocks/badgeawarder/badgeawarder.php"
    Then I should not see "Badge csv upload"

@local @local_eledia_exam2pdf @javascript
Feature: Download a PDF certificate after passing a quiz
  In order to have a compliance proof for a passed exam
  As a student
  I can download the automatically generated PDF after submitting a passing attempt

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Stu       | Dent     | student1@test.com |
      | teacher1 | Teach     | Er       | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name       | questiontext      |
      | Test questions   | truefalse | TF question | The sky is blue. |
    And the following "activities" exist:
      | activity | name              | course | idnumber | sumgrades | grade | gradepass |
      | quiz     | Compliance Exam   | C1     | quiz1    | 1         | 10    | 5         |
    And quiz "Compliance Exam" contains the following questions:
      | question    | page |
      | TF question | 1    |
    And the following config values are set as admin:
      | studentdownload | 1 | local_eledia_exam2pdf |

  Scenario: Student passes the quiz and sees a download button on the review page
    Given user "student1" has attempted "Compliance Exam" with responses:
      | slot | response |
      | 1    | True     |
    And the exam2pdf observer has processed the attempt for "student1" in "Compliance Exam"
    And the exam2pdf PDF record for "student1" in "Compliance Exam" should exist
    When I log in as "student1"
    And I am on the "Compliance Exam" "quiz activity" page
    And I follow "Review"
    Then I should see "Download certificate"
    And "Download certificate" "link" should exist

  Scenario: A failed attempt does not show the download button
    Given user "student1" has attempted "Compliance Exam" with responses:
      | slot | response |
      | 1    | False    |
    And the exam2pdf observer has processed the attempt for "student1" in "Compliance Exam"
    When I log in as "student1"
    And I am on the "Compliance Exam" "quiz activity" page
    And I follow "Review"
    Then I should not see "Download certificate"

  Scenario: The certificate link points at the plugin download endpoint
    Given user "student1" has attempted "Compliance Exam" with responses:
      | slot | response |
      | 1    | True     |
    And the exam2pdf observer has processed the attempt for "student1" in "Compliance Exam"
    And the exam2pdf PDF record for "student1" in "Compliance Exam" should exist
    When I log in as "student1"
    And I am on the "Compliance Exam" "quiz activity" page
    And I follow "Review"
    Then "Download certificate" "link" should exist

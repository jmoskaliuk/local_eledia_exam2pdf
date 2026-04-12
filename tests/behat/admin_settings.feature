@local @local_eledia_exam2pdf
Feature: Administrator configures the eLeDia exam2pdf global settings
  In order to control how PDF certificates are produced across the site
  As an administrator
  I can persist global defaults on the plugin settings page

  Background:
    Given I log in as "admin"

  Scenario: The settings page is reachable from site administration
    When I navigate to "Plugins > Local plugins > eLeDia exam2pdf" in site administration
    Then I should see "Output mode"
    And I should see "Retention period"
    And the "Output mode" select box should contain "Download"
    And the "Output mode" select box should contain "Email"
    And the "Output mode" select box should contain "Download and email"

  Scenario: Output mode and retention period are persisted
    When I navigate to "Plugins > Local plugins > eLeDia exam2pdf" in site administration
    And I set the following fields to these values:
      | Output mode       | Email |
      | Retention period  | 90    |
    And I press "Save changes"
    Then I should see "Changes saved"
    When I navigate to "Plugins > Local plugins > eLeDia exam2pdf" in site administration
    Then the field "Output mode" matches value "Email"
    And the field "Retention period" matches value "90"

  Scenario: Optional PDF fields can be toggled
    When I navigate to "Plugins > Local plugins > eLeDia exam2pdf" in site administration
    And I set the field "Show score on PDF" to ""
    And I set the field "Show duration on PDF" to ""
    And I press "Save changes"
    Then I should see "Changes saved"
    When I navigate to "Plugins > Local plugins > eLeDia exam2pdf" in site administration
    Then the field "Show score on PDF" matches value ""
    And the field "Show duration on PDF" matches value ""

  Scenario: Retention period accepts zero meaning never expire
    When I navigate to "Plugins > Local plugins > eLeDia exam2pdf" in site administration
    And I set the field "Retention period" to "0"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I navigate to "Plugins > Local plugins > eLeDia exam2pdf" in site administration
    Then the field "Retention period" matches value "0"

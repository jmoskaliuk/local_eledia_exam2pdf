@local @local_eledia_exam2pdf
Feature: Administrator configures the eLeDia | exam2pdf global settings
  In order to control how PDF certificates are produced across the site
  As an administrator
  I can persist global defaults on the plugin settings page

  Background:
    Given I log in as "admin"

  Scenario: The settings page is reachable and shows all settings
    When I navigate to "Plugins > Local plugins > eLeDia | exam2pdf" in site administration
    Then I should see "PDF generation mode"
    And I should see "PDF scope"
    And I should see "Student may download"
    And I should see "Bulk download format"
    And I should see "Output mode"
    And I should see "Retention period"
    And the "PDF generation mode" select box should contain "On submission (automatic)"
    And the "PDF generation mode" select box should contain "On demand (on click)"
    And the "PDF scope" select box should contain "Passed attempts only"
    And the "PDF scope" select box should contain "All finished attempts"
    And the "Bulk download format" select box should contain "ZIP with individual PDFs"
    And the "Bulk download format" select box should contain "One merged PDF"
    And the "Output mode" select box should contain "Download"
    And the "Output mode" select box should contain "Email"
    And the "Output mode" select box should contain "Download and email"

  Scenario: PDF generation settings are persisted
    When I navigate to "Plugins > Local plugins > eLeDia | exam2pdf" in site administration
    And I set the following fields to these values:
      | PDF generation mode | On demand (on click) |
      | PDF scope           | All finished attempts |
      | Bulk download format | One merged PDF       |
    And I press "Save changes"
    Then I should see "Changes saved"
    When I navigate to "Plugins > Local plugins > eLeDia | exam2pdf" in site administration
    Then the field "PDF generation mode" matches value "On demand (on click)"
    And the field "PDF scope" matches value "All finished attempts"
    And the field "Bulk download format" matches value "One merged PDF"

  Scenario: Output mode and retention period are persisted
    When I navigate to "Plugins > Local plugins > eLeDia | exam2pdf" in site administration
    And I set the following fields to these values:
      | Output mode       | Email |
      | Retention period  | 90    |
    And I press "Save changes"
    Then I should see "Changes saved"
    When I navigate to "Plugins > Local plugins > eLeDia | exam2pdf" in site administration
    Then the field "Output mode" matches value "Email"
    And the field "Retention period" matches value "90"

  @javascript
  Scenario: Optional PDF fields can be toggled
    When I navigate to "Plugins > Local plugins > eLeDia | exam2pdf" in site administration
    And I set the following fields to these values:
      | Show score on PDF    | 0 |
      | Show duration on PDF | 0 |
    And I press "Save changes"
    # Re-navigate to confirm persistence rather than relying on the flash
    # notification which BrowserKit may not render for checkbox-only changes.
    And I navigate to "Plugins > Local plugins > eLeDia | exam2pdf" in site administration
    Then the field "Show score on PDF" matches value ""
    And the field "Show duration on PDF" matches value ""

  Scenario: Retention period accepts zero meaning never expire
    When I navigate to "Plugins > Local plugins > eLeDia | exam2pdf" in site administration
    And I set the field "Retention period" to "0"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I navigate to "Plugins > Local plugins > eLeDia | exam2pdf" in site administration
    Then the field "Retention period" matches value "0"

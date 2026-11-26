@local @local_kopere_recert @javascript
Feature: Save course recertification settings
  In order to configure course recertification
  As an administrator
  I need to save settings without submitting hidden fields

  Background:
    Given the following "courses" exist:
      | fullname         | shortname |
      | Recertification  | RECERT    |
    And I am on the "RECERT" "course" page logged in as "admin"
    And I navigate to "Recertification configuration" in current page administration

  Scenario: Enable recertification without manual recertification
    When I set the field "id_enabled" to "1"
    And I set the field "id_triggertype" to "completion"
    And I set the field "id_intervaldays" to "1"
    And I set the field "id_selfenabled" to "0"
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "id_enabled" matches value "1"
    And the field "id_intervaldays" matches value "1"

  Scenario: Save a fixed date and disable recertification
    When I set the field "id_enabled" to "1"
    And I set the field "id_triggertype" to "fixeddate"
    And I set the field "id_fixedmonth" to "9"
    And I set the field "id_fixedday" to "15"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I set the field "id_enabled" to "0"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I set the field "id_enabled" to "1"
    Then the field "id_fixedmonth" matches value "9"
    And the field "id_fixedday" matches value "15"

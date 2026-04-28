# SPEC: Add Tests for Untested Admin Controller Methods

## Problem
8 admin/field controller methods lack feature tests:
- AdminUserController::update()
- AdminProviderController::store()
- AdminProviderController::test()
- AdminTemplateController::store()
- AdminTemplateController::activate()
- AdminSettingController::store()
- ProjectFieldController::update()

## Solution
Create feature tests covering these methods.

### AdminUserControllerTest
- Test admin can update user role
- Test admin cannot deactivate own account
- Test admin cannot remove last admin
- Test non-admin gets 403

### AdminProviderControllerTest
- Test admin can create provider with valid data
- Test validation rejects invalid provider_type
- Test API key is not exposed in response
- Test non-admin gets 403

### AdminSettingControllerTest
- Test admin can update retention_days within bounds
- Test admin can update max_upload_bytes within bounds
- Test validation rejects out-of-range values
- Test non-admin gets 403

### ProjectFieldControllerTest
- Test user can update field value
- Test user cannot update another user's field
- Test status transitions (missing -> edited, suggested -> confirmed)
- Test max:65535 validation on final_value

## Files to Create
- `503c-assistant/tests/Feature/AdminUserControllerTest.php`
- `503c-assistant/tests/Feature/AdminProviderControllerTest.php`
- `503c-assistant/tests/Feature/AdminSettingControllerTest.php`
- `503c-assistant/tests/Feature/ProjectFieldControllerTest.php`

## Acceptance Criteria
- All new tests pass
- Existing 116 tests still pass
- Each untested method has at least 2 test cases
- Test count increases to 130+

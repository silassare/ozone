<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

return [
	// generic errors
	'OZ_ERROR_BAD_REQUEST'                   => 'Your request is invalid.',
	'OZ_ERROR_INTERNAL'                      => 'An internal error occurred.',
	'OZ_ERROR_RUNTIME'                       => 'An internal error occurred while processing the request.',
	'OZ_ERROR_NOT_FOUND'                     => 'The requested resource was not found.',
	'OZ_ERROR_METHOD_NOT_ALLOWED'            => 'The request method is not allowed.',
	'OZ_ERROR_NOT_ALLOWED'                   => 'An error occurred. You may not be allowed to perform this action.',
	'OZ_ERROR_INVALID_FORM'                  => 'The submitted data is invalid.',
	'OZ_ERROR_UNAUTHENTICATED'               => 'You must sign in first.',
	'OZ_ERROR_RATE_LIMIT_REACHED'            => 'You have reached the request limit.',
	'OZ_RATE_LIMIT_EXCEEDED'                 => 'Too many requests. Please try again later.',
	'OZ_RECURSIVE_REDIRECTION'               => 'Too many redirections.',
	'OZ_CROSS_SITE_REQUEST_NOT_ALLOWED'      => 'Requests from this origin are not allowed.',
	'OZ_CSRF_TOKEN_INVALID'                  => 'The security token is missing, invalid or expired. Reload the page and try again.',

	// roles and access rights
	'OZ_ERROR_YOU_ARE_NOT_ADMIN'             => 'You are not an administrator.',
	'OZ_ERROR_YOU_ARE_NOT_EDITOR'            => 'You are not an editor.',
	'OZ_ERROR_YOU_ARE_NOT_SUPER_ADMIN'       => 'You are not a super administrator.',
	'OZ_ERROR_USER_IS_MISSING_REQUIRED_ROLE' => 'You do not have the role this action requires.',
	'OZ_ERROR_USER_TYPE_NOT_ALLOWED'         => 'Your account type is not allowed here.',
	'OZ_ERROR_MISSING_ACCESS_RIGHTS'         => 'You do not have the access rights this action requires.',
	'OZ_ACCESS_RIGHT_DESCRIPTION'            => 'Allows the {action} action.',
	'OZ_MISSING_ACCESS_RIGHT'                => 'You are not allowed to perform the {action} action.',

	// API keys and authorizations
	'OZ_MISSING_API_KEY'                     => 'An API key is required.',
	'OZ_YOUR_API_KEY_IS_NOT_VALID'           => 'The API key is invalid.',
	'OZ_AUTH_AUTHORIZED'                     => 'Authorization granted.',
	'OZ_AUTH_REFRESHED'                      => 'The authorization has been refreshed.',
	'OZ_AUTH_DISABLED'                       => 'This authorization has been disabled.',
	'OZ_AUTH_EXPIRED'                        => 'This authorization has expired.',
	'OZ_AUTH_HAS_EXPIRED'                    => 'This authorization has expired.',
	'OZ_AUTH_NOT_FOUND'                      => 'Authorization not found.',
	'OZ_AUTH_INVALID_OR_DELETED_REF'         => 'This authorization does not exist or was deleted.',
	'OZ_AUTH_MISSING_REF'                    => 'The authorization reference is missing.',
	'OZ_AUTH_REF_NOT_PROVIDED'               => 'The authorization reference was not provided.',
	'OZ_AUTH_MISSING_SECRET'                 => 'The code or token is missing.',
	'OZ_AUTH_CODE_INVALID'                   => 'The code is invalid or has already been used.',
	'OZ_AUTH_INVALID_CODE'                   => 'The code is invalid.',
	'OZ_AUTH_INVALID_TOKEN'                  => 'The token is invalid.',
	'OZ_AUTH_INVALID_REFRESH_KEY'            => 'The refresh key is invalid.',
	'OZ_AUTH_REFRESH_KEY_INVALID'            => 'The refresh key is invalid or has expired.',
	'OZ_AUTH_INVALID_PROVIDER'               => 'These credentials cannot be used to sign in.',
	'OZ_AUTH_NOT_AUTHORIZED'                 => 'These credentials have not been authorized yet.',

	// sign in, sign up and sessions
	'OZ_AUTH_USER_UNKNOWN'                      => 'No user matches these sign-in details.',
	'OZ_AUTH_INVALID_CREDENTIALS'               => 'Invalid credentials.',
	'OZ_AUTH_USER_UNVERIFIED'                   => 'This account has not been verified yet.',
	'OZ_AUTH_TOO_MUCH_ATTEMPT'                  => 'Too many attempts. Please try again later.',
	'OZ_2FA_REQUIRED'                           => 'A second authentication factor is required.',
	'OZ_2FA_NO_PENDING_USER'                    => 'No sign-in is waiting for a second factor.',
	'OZ_2FA_SESSION_MISMATCH'                   => 'This verification belongs to another session.',
	'OZ_USER_SIGN_UP_SUCCESS'                   => 'Sign-up successful.',
	'OZ_USER_SIGN_IN_DONE'                      => 'You are signed in.',
	'OZ_USER_LOGOUT_DONE'                       => 'You are signed out.',
	'OZ_USER_LOG_ON_FAIL'                       => 'Sign-in failed.',
	'OZ_USER_LOG_OUT_FAIL'                      => 'Sign-out failed.',
	'OZ_ACCOUNT_RECOVERY_SUCCESS'               => 'Your account has been recovered.',
	'OZ_PASSWORD_EDIT_SUCCESS'                  => 'Your password has been changed.',
	'OZ_PASSWORD_SAME_OLD_AND_NEW_PASS'         => 'The new password must differ from the current one.',
	'OZ_SESSION_HIJACKING_DETECTED'             => 'Your session was ended for security reasons.',
	'OZ_SESSION_DELETION_FAILED'                => 'The session could not be deleted.',
	'OZ_SESSION_DISTINCT_USER_CANT_ATTACH_USER' => 'Another user is already signed in on this session.',

	// fields
	'OZ_FIELD_PHONE_ALREADY_REGISTERED'      => 'The number {phone} is already linked to another account.',
	'OZ_FIELD_EMAIL_ALREADY_REGISTERED'      => 'The email address {email} is already linked to another account.',
	'OZ_FIELD_EMAIL_INVALID'                 => 'The email address is invalid.',
	'OZ_FIELD_PHONE_INVALID'                 => 'The phone number is invalid.',
	'OZ_FIELD_PHONE_NOT_REGISTERED'          => 'This phone number is not registered.',
	'OZ_FIELD_EMAIL_NOT_REGISTERED'          => 'This email address is not registered.',
	'OZ_FIELD_COUNTRY_INVALID'               => 'The country is invalid.',
	'OZ_FIELD_COUNTRY_UNKNOWN'               => 'Unknown country.',
	'OZ_FIELD_COUNTRY_NOT_ALLOWED'           => 'This country is not allowed. The service may not be available in your country yet.',
	'OZ_FIELD_USER_NAME_INVALID'             => 'The username is invalid.',
	'OZ_FIELD_USER_NAME_INVALID_CHARACTERS'  => 'The username contains characters that are not allowed.',
	'OZ_FIELD_USER_NAME_TOO_SHORT'           => 'The username is too short.',
	'OZ_FIELD_USER_NAME_TOO_LONG'            => 'The username is too long.',
	'OZ_FIELD_USER_NAME_ALREADY_REGISTERED'  => 'This username is already taken.',
	'OZ_FIELD_USER_NAME_NOT_REGISTERED'      => 'No account is linked to this username.',
	'OZ_FIELD_URL_INVALID'                   => 'The address is invalid.',
	'OZ_FIELD_URL_HOST_NOT_ALLOWED'          => 'This address is not allowed.',
	'OZ_FIELD_GENDER_INVALID'                => 'The gender is invalid.',
	'OZ_FIELD_PASS_INVALID'                  => 'The password is incorrect.',
	'OZ_FIELD_PASS_NOT_SECURE'               => 'The password is not secure enough.',
	'OZ_FIELD_PASS_TOO_LONG'                 => 'The password is too long.',
	'OZ_FIELD_PASS_TOO_SHORT'                => 'The password is too short.',
	'OZ_FIELD_SHOULD_BE_A_LIST'              => 'A list of values is expected.',
	'OZ_FIELD_SHOULD_HAVE_SAME_VALUE'        => 'The fields {field} and {field_confirm} must have the same value.',

	// files
	'OZ_FILE_INVALID'                        => 'The file is invalid.',
	'OZ_FILE_MIME_INVALID'                   => 'This file type is not allowed.',
	'OZ_FILE_SIZE_OUT_OF_RANGE'              => 'The file size is out of the allowed range.',
	'OZ_FILE_COUNT_OUT_OF_RANGE'             => 'The number of files is out of the allowed range.',
	'OZ_FILE_TOTAL_SIZE_EXCEED_LIMIT'        => 'The files exceed the total size allowed.',
	'OZ_FILE_UPLOAD_FAILS'                   => 'The file upload failed.',
	'OZ_FILE_UPLOAD_IS_EMPTY'                => 'No file was uploaded.',
	'OZ_FILE_UPLOAD_TOO_BIG'                 => 'The file is too big.',
	'OZ_FILE_INFECTED'                       => 'The file contains a virus and was rejected.',
	'OZ_FILE_SCAN_FAILED'                    => 'The file could not be checked for viruses, please try again.',
	'OZ_CAPTCHA_CODE_EXPIRED'                => 'The captcha has expired.',
	'OZ_QR_CODE_HAS_EXPIRED'                 => 'The QR code has expired.',
	'OZ_LINK_EXPIRED'                        => 'This link has expired.',

	// forms and form sessions
	'OZ_FORM_MISSING_REQUIRED_FIELD'         => 'A required field is missing.',
	'OZ_FORM_PROVIDER_NOT_FOUND'             => 'Form "{provider}" not found.',
	'OZ_FORM_PROVIDER_REQUIRES_REAL_CONTEXT' => 'This form must be filled in through its route.',
	'OZ_FORM_RESUME_EXPIRED'                 => 'This form session has expired.',
	'OZ_FORM_RESUME_NOT_YET_ACTIVE'          => 'This form is not open yet.',
	'OZ_FORM_RESUME_INVALID_ACTION'          => 'Unknown form action "{action}".',
	'OZ_FORM_SESSION_NOT_FOUND'              => 'Form session not found or expired.',
	'OZ_FORM_SESSION_REF_MISSING'            => 'The form session reference is missing.',
	'OZ_FORM_SESSION_ACCESS_DENIED'          => 'You cannot access this form session.',
	'OZ_FORM_SESSION_PROVIDER_MISMATCH'      => 'This form session belongs to another form.',
	'OZ_FORM_SESSION_ROUTE_MISMATCH'         => 'This form session belongs to another route.',
	'OZ_FORM_SESSION_ALREADY_DONE'           => 'This form session is already complete.',
	'OZ_FORM_SESSION_NOT_DONE'               => 'This form session is not complete yet.',
	'OZ_FORM_SESSION_NOT_YET_ACTIVE'         => 'This form session cannot start yet.',
	'OZ_FORM_SESSION_NOT_REVERSIBLE'         => 'This form does not allow going back.',
	'OZ_FORM_SESSION_NO_HISTORY'             => 'You are already on the first step.',

	// REST relations
	'OZ_RELATION_NOT_DEFINED'                                                  => 'Unknown relation requested.',
	'OZ_RELATION_ARRAY_EXPECTED'                                               => 'The relation data must be a list.',
	'OZ_RELATION_IS_PAGINATED_ARRAY_OF_ARRAY_EXPECTED'                         => 'This paginated relation expects a list of items.',
	'OZ_RELATION_IS_PAGINATED_AND_SHOULD_BE_RETRIEVED_WITH_DEDICATED_ENDPOINT' => 'This relation is paginated: fetch it through its own endpoint.',
	'OZ_RELATION_PROCESSING_FAILED'                                            => 'The relation could not be processed.',

	// used in views templates
	'OZ_VIEW_GO_HOME_BTN'                    => 'Home',
	'OZ_VIEW_GO_BACK_BTN'                    => 'Back',
	'OZ_VIEW_REDIRECT_TITLE'                 => 'You are being redirected.',
	'OZ_VIEW_REDIRECT_MESSAGE'               => 'If you are not redirected automatically, follow this <a href="{url}">link</a>.',
	'OZ_VIEW_AUTH_LINK_CONFIRM_TITLE'        => 'Confirmation',
	'OZ_VIEW_AUTH_LINK_CONFIRM_MESSAGE'      => 'Click the button below to confirm.',
	'OZ_VIEW_AUTH_LINK_CONFIRM_BTN'          => 'Confirm',

	// access grant form view
	'OZ_ACCESS_GRANT_FORM_TITLE'             => 'Authorization required',
	'OZ_ACCESS_GRANT_FORM_SUBMIT_BTN'        => 'Authorize',

	// welcome page view
	'OZ_WELCOME_PAGE_TITLE'                  => 'Welcome',
	'OZ_WELCOME_PAGE_API_DOC_LINK'           => 'API documentation',
];

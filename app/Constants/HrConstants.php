<?php

namespace App\Constants;

/**
 * HR Module Constants
 * 
 * Centralized constants for HR module including error messages,
 * status values, and configuration options.
 */
class HrConstants
{
    // Error Messages
    const ERROR_EMPLOYEE_NOT_FOUND = 'Employee not found';
    const ERROR_EMPLOYEE_ALREADY_EXISTS = 'Employee with this email already exists';
    const ERROR_EMPLOYEE_ID_EXISTS = 'Employee ID already exists';
    const ERROR_INVALID_EMPLOYEE_STATUS = 'Invalid employee status';
    const ERROR_CANNOT_ARCHIVE_ACTIVE = 'Cannot archive active employee. Please offboard first.';
    const ERROR_LEAVE_BALANCE_INSUFFICIENT = 'Insufficient leave balance';
    const ERROR_LEAVE_OVERLAPPING = 'Leave request overlaps with existing request';
    const ERROR_LEAVE_TYPE_NOT_FOUND = 'Leave type not found';
    const ERROR_LEAVE_REQUEST_NOT_FOUND = 'Leave request not found';
    const ERROR_PAYSLIP_NOT_FOUND = 'Payslip not found';
    const ERROR_PAYSLIP_ALREADY_EXISTS = 'Payslip for this period already exists';
    const ERROR_DOCUMENT_NOT_FOUND = 'Document not found';
    const ERROR_PERFORMANCE_NOTE_NOT_FOUND = 'Performance note not found';
    const ERROR_UNAUTHORIZED_ACCESS = 'Unauthorized access to this resource';
    const ERROR_INVALID_MANAGER = 'Invalid manager assignment';
    const ERROR_EMPLOYEE_ID_GENERATION_FAILED = 'Failed to generate employee ID';
    const ERROR_DEPARTMENT_NOT_FOUND = 'Department not found';
    const ERROR_DEPARTMENT_ALREADY_EXISTS = 'Department with this name already exists';
    const ERROR_DESIGNATION_NOT_FOUND = 'Designation not found';
    const ERROR_DESIGNATION_ALREADY_EXISTS = 'Designation with this name already exists';
    const ERROR_USER_ACCOUNT_EXISTS = 'User account with this email already exists';
    const ERROR_PROFILE_PICTURE_UPLOAD_FAILED = 'Failed to upload profile picture';
    const ERROR_ONBOARDING_NOT_COMPLETE = 'Onboarding is not complete. Please complete all required items before activating employee.';
    const ERROR_ONBOARDING_CHECKLIST_NOT_FOUND = 'Onboarding checklist item not found';
    const ERROR_ONBOARDING_TASK_NOT_FOUND = 'Onboarding task not found';
    const ERROR_ONBOARDING_TEMPLATE_NOT_FOUND = 'Onboarding template not found';
    const ERROR_INVALID_ONBOARDING_STATUS = 'Invalid onboarding status';

    // Success Messages
    const SUCCESS_EMPLOYEE_CREATED = 'Employee created successfully';
    const SUCCESS_EMPLOYEE_UPDATED = 'Employee updated successfully';
    const SUCCESS_EMPLOYEE_ARCHIVED = 'Employee archived successfully';
    const SUCCESS_EMPLOYEE_ACTIVATED = 'Employee activated successfully';
    const SUCCESS_LEAVE_REQUEST_CREATED = 'Leave request created successfully';
    const SUCCESS_LEAVE_BALANCE_ADJUSTED = 'Leave balance adjusted successfully';
    const SUCCESS_PAYSLIP_UPLOADED = 'Payslip uploaded successfully';
    const SUCCESS_DOCUMENT_UPLOADED = 'Document uploaded successfully';
    const SUCCESS_DOCUMENT_VERIFIED = 'Document verified successfully';
    const SUCCESS_DOCUMENT_REJECTED = 'Document rejected successfully';
    const SUCCESS_PERFORMANCE_NOTE_CREATED = 'Performance note created successfully';
    const SUCCESS_DEPARTMENT_CREATED = 'Department created successfully';
    const SUCCESS_DEPARTMENT_UPDATED = 'Department updated successfully';
    const SUCCESS_DEPARTMENT_DELETED = 'Department deleted successfully';
    const SUCCESS_DESIGNATION_CREATED = 'Designation created successfully';
    const SUCCESS_DESIGNATION_UPDATED = 'Designation updated successfully';
    const SUCCESS_DESIGNATION_DELETED = 'Designation deleted successfully';
    const SUCCESS_PROFILE_PICTURE_UPLOADED = 'Profile picture uploaded successfully';
    const SUCCESS_USER_ACCOUNT_CREATED = 'User account created and linked successfully';
    const SUCCESS_USER_ACCOUNT_LINKED = 'User account linked successfully';
    const SUCCESS_ONBOARDING_CHECKLIST_CREATED = 'Onboarding checklist created successfully';
    const SUCCESS_ONBOARDING_CHECKLIST_COMPLETED = 'Checklist item completed successfully';
    const SUCCESS_ONBOARDING_TASK_CREATED = 'Onboarding task created successfully';
    const SUCCESS_ONBOARDING_TASK_COMPLETED = 'Task completed successfully';
    const SUCCESS_ONBOARDING_COMPLETED = 'Onboarding completed successfully';
    const SUCCESS_ONBOARDING_TEMPLATE_CREATED = 'Onboarding template created successfully';
    const SUCCESS_ONBOARDING_TEMPLATE_UPDATED = 'Onboarding template updated successfully';

    // Employment Status
    const STATUS_ONBOARDING = 'onboarding';
    const STATUS_ACTIVE = 'active';
    const STATUS_ON_LEAVE = 'on_leave';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_OFFBOARDED = 'offboarded';
    const STATUS_ON_NOTICE = 'on_notice';

    // Onboarding Checklist Status
    const CHECKLIST_STATUS_PENDING = 'pending';
    const CHECKLIST_STATUS_IN_PROGRESS = 'in_progress';
    const CHECKLIST_STATUS_COMPLETED = 'completed';
    const CHECKLIST_STATUS_SKIPPED = 'skipped';

    // Onboarding Task Status
    const TASK_STATUS_PENDING = 'pending';
    const TASK_STATUS_IN_PROGRESS = 'in_progress';
    const TASK_STATUS_COMPLETED = 'completed';
    const TASK_STATUS_CANCELLED = 'cancelled';

    // Onboarding Task Type
    const TASK_TYPE_HR = 'hr_task';
    const TASK_TYPE_MANAGER = 'manager_task';
    const TASK_TYPE_IT = 'it_task';
    const TASK_TYPE_FINANCE = 'finance_task';
    const TASK_TYPE_LEGAL = 'legal_task';

    // Onboarding Task Priority
    const TASK_PRIORITY_LOW = 'low';
    const TASK_PRIORITY_MEDIUM = 'medium';
    const TASK_PRIORITY_HIGH = 'high';
    const TASK_PRIORITY_URGENT = 'urgent';

    // Onboarding Checklist Category
    const CHECKLIST_CATEGORY_HR = 'hr';
    const CHECKLIST_CATEGORY_MANAGER = 'manager';
    const CHECKLIST_CATEGORY_IT = 'it';
    const CHECKLIST_CATEGORY_FINANCE = 'finance';
    const CHECKLIST_CATEGORY_LEGAL = 'legal';

    // Employment Types
    const TYPE_FULL_TIME = 'full_time';
    const TYPE_PART_TIME = 'part_time';
    const TYPE_CONTRACT = 'contract';
    const TYPE_INTERN = 'intern';

    // Leave Request Status
    const LEAVE_STATUS_PENDING = 'pending';
    const LEAVE_STATUS_APPROVED = 'approved';
    const LEAVE_STATUS_REJECTED = 'rejected';
    const LEAVE_STATUS_CANCELLED = 'cancelled';

    // Document Categories
    const DOC_CATEGORY_CONTRACT = 'contract';
    const DOC_CATEGORY_ID_DOCUMENT = 'id_document';
    const DOC_CATEGORY_QUALIFICATION = 'qualification';
    const DOC_CATEGORY_PERFORMANCE = 'performance';
    const DOC_CATEGORY_DISCIPLINARY = 'disciplinary';
    const DOC_CATEGORY_PROFILE_PICTURE = 'profile_picture';
    const DOC_CATEGORY_ONBOARDING = 'onboarding';
    const DOC_CATEGORY_OTHER = 'other';

    // Document Verification Status
    const DOC_VERIFICATION_STATUS_PENDING = 'pending';
    const DOC_VERIFICATION_STATUS_VERIFIED = 'verified';
    const DOC_VERIFICATION_STATUS_REJECTED = 'rejected';

    // Performance Note Visibility
    const VISIBILITY_HR_ONLY = 'hr_only';
    const VISIBILITY_MANAGER = 'manager';
    const VISIBILITY_EMPLOYEE = 'employee';

    // Employee ID Format
    const EMPLOYEE_ID_PREFIX = 'EMP';
    const EMPLOYEE_ID_FORMAT = 'EMP-{year}-{sequence}';

    // Payslip Number Format
    const PAYSLIP_PREFIX = 'PAY';
    const PAYSLIP_FORMAT = 'PAY-{year}-{month}-{sequence}';

    // Induction Content Types
    const INDUCTION_CONTENT_TYPE_DOCUMENT = 'document';
    const INDUCTION_CONTENT_TYPE_VIDEO = 'video';
    const INDUCTION_CONTENT_TYPE_BOTH = 'both';

    // Induction Content Categories
    const INDUCTION_CATEGORY_INDUCTION = 'induction';
    const INDUCTION_CATEGORY_POLICY = 'policy';
    const INDUCTION_CATEGORY_TRAINING = 'training';

    // Induction Content Status
    const INDUCTION_STATUS_DRAFT = 'draft';
    const INDUCTION_STATUS_PUBLISHED = 'published';
    const INDUCTION_STATUS_ARCHIVED = 'archived';

    // Induction Target Audience Types
    const INDUCTION_TARGET_ALL_EMPLOYEES = 'all_employees';
    const INDUCTION_TARGET_ONBOARDING_ONLY = 'onboarding_only';
    const INDUCTION_TARGET_DEPARTMENT_SPECIFIC = 'department_specific';

    // Induction Assignment Status
    const INDUCTION_ASSIGNMENT_STATUS_PENDING = 'pending';
    const INDUCTION_ASSIGNMENT_STATUS_IN_PROGRESS = 'in_progress';
    const INDUCTION_ASSIGNMENT_STATUS_COMPLETED = 'completed';
    const INDUCTION_ASSIGNMENT_STATUS_OVERDUE = 'overdue';

    // Audit Actions
    const AUDIT_EMPLOYEE_CREATED = 'employee.created';
    const AUDIT_EMPLOYEE_UPDATED = 'employee.updated';
    const AUDIT_EMPLOYEE_ARCHIVED = 'employee.archived';
    const AUDIT_EMPLOYEE_ACTIVATED = 'employee.activated';
    const AUDIT_LEAVE_REQUEST_CREATED = 'leave.request.created';
    const AUDIT_LEAVE_REQUEST_CANCELLED = 'leave.request.cancelled';
    const AUDIT_LEAVE_BALANCE_ADJUSTED = 'leave.balance.adjusted';
    const AUDIT_LEAVE_BALANCE_ACCRUED = 'leave.balance.accrued';
    const AUDIT_PAYSLIP_UPLOADED = 'payslip.uploaded';
    const AUDIT_PAYSLIP_DOWNLOADED = 'payslip.downloaded';
    const AUDIT_PAYSLIP_DELETED = 'payslip.deleted';
    const AUDIT_DOCUMENT_UPLOADED = 'document.uploaded';
    const AUDIT_DOCUMENT_DOWNLOADED = 'document.downloaded';
    const AUDIT_DOCUMENT_DELETED = 'document.deleted';
    const AUDIT_DOCUMENT_VERIFIED = 'document.verified';
    const AUDIT_DOCUMENT_REJECTED = 'document.rejected';
    const AUDIT_PERFORMANCE_NOTE_CREATED = 'performance_note.created';
    const AUDIT_PERFORMANCE_NOTE_UPDATED = 'performance_note.updated';
    const AUDIT_PERFORMANCE_NOTE_DELETED = 'performance_note.deleted';
    const AUDIT_ONBOARDING_CHECKLIST_CREATED = 'onboarding.checklist.created';
    const AUDIT_ONBOARDING_CHECKLIST_COMPLETED = 'onboarding.checklist.completed';
    const AUDIT_ONBOARDING_TASK_CREATED = 'onboarding.task.created';
    const AUDIT_ONBOARDING_TASK_COMPLETED = 'onboarding.task.completed';
    const AUDIT_ONBOARDING_COMPLETED = 'onboarding.completed';

    // Default Leave Types (for seeding)
    const DEFAULT_LEAVE_TYPES = [
        ['name' => 'Annual Leave', 'code' => 'AL', 'accrues_monthly' => true, 'max_balance' => 25, 'carry_forward' => true],
        ['name' => 'Sick Leave', 'code' => 'SL', 'accrues_monthly' => false, 'max_balance' => 30, 'carry_forward' => false],
        ['name' => 'Family Responsibility Leave', 'code' => 'FRL', 'accrues_monthly' => false, 'max_balance' => 3, 'carry_forward' => false],
        ['name' => 'Unpaid Leave', 'code' => 'UL', 'accrues_monthly' => false, 'max_balance' => null, 'carry_forward' => false],
    ];
}


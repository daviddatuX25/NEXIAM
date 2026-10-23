<?php

namespace App\Models\Foundation;

enum IntakeFieldKey: string
{
    // Identity & Demographics (Core)
    case FirstName = 'first_name';
    case LastName = 'last_name';
    case MiddleName = 'middle_name';
    case Suffix = 'suffix';
    case Birthdate = 'birthdate';
    case Sex = 'sex';

    // Contact & Address
    case ContactEmail = 'contact_email';
    case ContactPhone = 'contact_phone';
    case AddressLine = 'address_line';
    case City = 'city';
    case Province = 'province';
    case ZipCode = 'zip_code';

    // Academic Background
    case Strand = 'strand';
    case LastSchoolEnrolled = 'last_school_enrolled';
    case ApplicantType = 'applicant_type';

    // Program Choices
    case CoursePreference1 = 'course_preference_1';
    case CoursePreference2 = 'course_preference_2';
    case CoursePreference3 = 'course_preference_3';

    public function label(): string
    {
        return match ($this) {
            self::FirstName => 'First Name',
            self::LastName => 'Last Name',
            self::MiddleName => 'Middle Name',
            self::Suffix => 'Suffix (e.g. Jr, III)',
            self::Birthdate => 'Date of Birth',
            self::Sex => 'Sex / Gender',
            self::ContactEmail => 'Email Address',
            self::ContactPhone => 'Mobile Phone Number',
            self::AddressLine => 'Street Address',
            self::City => 'City / Municipality',
            self::Province => 'Province',
            self::ZipCode => 'Postal / ZIP Code',
            self::Strand => 'Senior High Strand',
            self::LastSchoolEnrolled => 'Last School Enrolled',
            self::ApplicantType => 'Applicant Type (New / Transferee)',
            self::CoursePreference1 => '1st Choice Program',
            self::CoursePreference2 => '2nd Choice Program',
            self::CoursePreference3 => '3rd Choice Program',
        };
    }

    public function fieldGroup(): string
    {
        return match ($this) {
            self::FirstName, self::LastName, self::MiddleName, self::Suffix, self::Birthdate, self::Sex => 'identity',
            self::ContactEmail, self::ContactPhone, self::AddressLine, self::City, self::Province, self::ZipCode => 'contact',
            self::Strand, self::LastSchoolEnrolled, self::ApplicantType => 'academic',
            self::CoursePreference1, self::CoursePreference2, self::CoursePreference3 => 'program_choices',
        };
    }

    public function isSystemMandatory(): bool
    {
        return in_array($this, [
            self::FirstName,
            self::LastName,
            self::Birthdate,
            self::Sex,
            self::CoursePreference1,
        ], true);
    }

    public function defaultLevel(): IntakeRequirementLevel
    {
        return match ($this) {
            self::FirstName, self::LastName, self::Birthdate, self::Sex, self::CoursePreference1 => IntakeRequirementLevel::SystemMandatory,
            self::ContactEmail, self::Strand, self::LastSchoolEnrolled => IntakeRequirementLevel::InstitutionRequired,
            default => IntakeRequirementLevel::Optional,
        };
    }

    /**
     * Common alternate aliases in user spreadsheets for auto-mapping.
     *
     * @return list<string>
     */
    public function importAliases(): array
    {
        return match ($this) {
            self::FirstName => ['first_name', 'firstname', 'first name', 'given_name', 'given name'],
            self::LastName => ['last_name', 'lastname', 'last name', 'family_name', 'family name', 'surname'],
            self::MiddleName => ['middle_name', 'middlename', 'middle name', 'middle_initial', 'middle initial', 'mi'],
            self::Suffix => ['suffix', 'name_suffix', 'ext'],
            self::Birthdate => ['birthdate', 'birth_date', 'date_of_birth', 'dob', 'birthday'],
            self::Sex => ['sex', 'gender'],
            self::ContactEmail => ['email', 'contact_email', 'email_address', 'e-mail'],
            self::ContactPhone => ['phone', 'contact_phone', 'mobile', 'cellphone', 'tel_no'],
            self::AddressLine => ['address', 'address_line', 'street', 'street_address'],
            self::City => ['city', 'municipality', 'town'],
            self::Province => ['province', 'state', 'region'],
            self::ZipCode => ['zip', 'zip_code', 'postal', 'postal_code'],
            self::Strand => ['strand', 'shs_strand', 'track', 'shs_track', 'prev_course', 'previous_course'],
            self::LastSchoolEnrolled => ['school', 'last_school', 'school_origin', 'school_name', 'last_school_enrolled'],
            self::ApplicantType => ['applicant_type', 'type', 'classification', 'applicant_classification'],
            self::CoursePreference1 => ['course_preference_1', 'course_applied', 'course', 'program_applied', '1st_choice', 'first_choice'],
            self::CoursePreference2 => ['course_preference_2', '2nd_choice', 'second_choice'],
            self::CoursePreference3 => ['course_preference_3', '3rd_choice', 'third_choice'],
        };
    }
}
